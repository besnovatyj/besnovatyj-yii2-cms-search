<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\services;

use Besnovatyj\Search\contracts\SearchEngineInterface;
use Yii;
use yii\base\InvalidConfigException;

/**
 * Резолвер активного ядра поиска.
 *
 * Ядра объявлены в конфиге модуля картой «ключ => FQCN» строками — ровно как адаптеры редактора:
 * пока пакет ядра не установлен, строка безвредна (автозагрузку не триггерит), а резолвер проверяет
 * `class_exists()` перед созданием. Добавить движок = поставить пакет и дописать строку в карту.
 *
 * Здесь же живёт деградация: если активное ядро не отвечает (упавший демон, потерянное соединение),
 * фасад молча переходит на запасное ядро, которое работает в той же базе и всегда под рукой.
 * Посетитель получает результаты, а не HTTP 500 — при двух установленных ядрах отказ движка
 * перестаёт быть аварией.
 */
final class EngineResolver
{
    /** @var array<string, SearchEngineInterface|null> */
    private array $instances = [];

    private ?string $resolvedKey = null;

    public function __construct(private readonly SearchSettings $settings)
    {
    }

    /**
     * Ключ ядра, которое реально обслуживает запросы (с учётом подмены на запасное).
     * Пустая строка — рабочего ядра нет.
     */
    public function activeKey(): string
    {
        $this->active();

        return (string)$this->resolvedKey;
    }

    /** Ключ ядра, выбранного администратором (без учёта деградации). */
    public function configuredKey(): string
    {
        return $this->settings->engine();
    }

    /**
     * Рабочее ядро: выбранное в настройках, иначе запасное, иначе null.
     *
     * Проверка доступности выполняется один раз за запрос — результат запоминается, чтобы каждый
     * поиск на странице не дёргал соединение заново.
     */
    public function active(): ?SearchEngineInterface
    {
        if ($this->resolvedKey !== null) {
            return $this->instances[$this->resolvedKey] ?? null;
        }

        $configured = $this->settings->engine();
        $engine = $this->engine($configured);

        if ($engine !== null && $engine->isAvailable()) {
            $this->resolvedKey = $configured;

            return $engine;
        }

        $fallbackKey = $this->settings->fallbackEngine();
        if ($fallbackKey !== '' && $fallbackKey !== $configured) {
            $fallback = $this->engine($fallbackKey);
            if ($fallback !== null && $fallback->isAvailable()) {
                Yii::warning(
                    "Ядро поиска «{$configured}» недоступно, выдача обслуживается запасным «{$fallbackKey}».",
                    'search/engine',
                );
                $this->resolvedKey = $fallbackKey;

                return $fallback;
            }
        }

        Yii::error("Ни одно ядро поиска недоступно (выбрано «{$configured}»).", 'search/engine');
        $this->resolvedKey = '';

        return null;
    }

    /**
     * Конкретное ядро по ключу — без проверки доступности и без подмены.
     * Нужен индексатору: собирать индекс надо именно тем ядром, которое выбрано.
     */
    public function engine(string $key): ?SearchEngineInterface
    {
        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $this->instances)) {
            return $this->instances[$key];
        }

        $class = $this->settings->adapters()[$key] ?? null;

        if ($class === null) {
            Yii::warning("Ядро поиска «{$key}» не объявлено в карте adapters.", 'search/engine');

            return $this->instances[$key] = null;
        }

        if (!class_exists($class)) {
            Yii::warning("Пакет ядра поиска «{$key}» не установлен (нет класса {$class}).", 'search/engine');

            return $this->instances[$key] = null;
        }

        try {
            $engine = Yii::createObject($class);
        } catch (InvalidConfigException $e) {
            Yii::error("Не удалось создать ядро поиска «{$key}»: " . $e->getMessage(), 'search/engine');

            return $this->instances[$key] = null;
        }

        if (!$engine instanceof SearchEngineInterface) {
            Yii::error(
                "Класс {$class} не реализует " . SearchEngineInterface::class . ".",
                'search/engine',
            );

            return $this->instances[$key] = null;
        }

        return $this->instances[$key] = $engine;
    }

    /**
     * Установленные ядра — для выпадающего списка в админке и страницы состояния.
     *
     * @return array<string, SearchEngineInterface>
     */
    public function installed(): array
    {
        $engines = [];

        foreach (array_keys($this->settings->adapters()) as $key) {
            $engine = $this->engine((string)$key);
            if ($engine !== null) {
                $engines[(string)$key] = $engine;
            }
        }

        return $engines;
    }
}
