<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\services;

use Besnovatyj\Search\contracts\SearchEngineDescriptor;
use Besnovatyj\Search\contracts\SearchEngineInterface;
use Besnovatyj\Search\contracts\SearchEngineProvider;
use Throwable;
use Yii;

/**
 * Реестр установленных ядер поиска.
 *
 * Находит модули, объявившие себя поставщиками движков ({@see SearchEngineProvider}), — тем же
 * обходом зарегистрированных модулей с проверкой `instanceof`, каким {@see SourceRegistry} находит
 * поставщиков контента. Один приём на весь пакет: фасад не знает ни одного ядра поимённо, а ядра
 * не знают друг о друге.
 *
 * Отключённый в менеджере модуль в конфиг приложения не попадает, поэтому и в реестре не появится:
 * «ядро установлено» и «модуль ядра включён» — одно утверждение, отдельных карт адаптеров и
 * проверок `class_exists()` не требуется.
 *
 * Экземпляры движков кэшируются на время запроса: у ядра есть состояние пересборки (открытый слот,
 * накопленная пачка документов), и оно обязано принадлежать одному объекту.
 */
final class EngineRegistry
{
    /** @var array<string, SearchEngineDescriptor>|null объявленные ядра, ключ — ключ ядра */
    private ?array $descriptors = null;

    /** @var array<string, SearchEngineInterface|null> созданные движки; null — создать не удалось */
    private array $engines = [];

    /**
     * Все объявленные ядра.
     *
     * @return array<string, SearchEngineDescriptor>
     */
    public function descriptors(): array
    {
        if ($this->descriptors !== null) {
            return $this->descriptors;
        }

        $descriptors = [];

        foreach (array_keys(Yii::$app->getModules()) as $id) {
            $module = Yii::$app->getModule((string)$id);

            if (!$module instanceof SearchEngineProvider) {
                continue;
            }

            foreach ($module->searchEngines() as $descriptor) {
                if (isset($descriptors[$descriptor->key])) {
                    Yii::warning(
                        "Ядро поиска «{$descriptor->key}» объявлено дважды; взято первое.",
                        'search/engine',
                    );
                    continue;
                }

                $descriptors[$descriptor->key] = $descriptor;
            }
        }

        return $this->descriptors = $descriptors;
    }

    /** Объявлено ли ядро с таким ключом. */
    public function has(string $key): bool
    {
        return isset($this->descriptors()[$key]);
    }

    /**
     * Движок по ключу — без проверки доступности.
     *
     * Создаётся контейнером, поэтому зависимости ядра (настройки, соединение, схема индекса)
     * внедряются обычным образом и объявлены в `config/common.php` его пакета.
     */
    public function engine(string $key): ?SearchEngineInterface
    {
        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $this->engines)) {
            return $this->engines[$key];
        }

        $descriptor = $this->descriptors()[$key] ?? null;

        if ($descriptor === null) {
            Yii::warning(
                "Ядро поиска «{$key}» не найдено: модуль ядра не установлен или выключен.",
                'search/engine',
            );

            return $this->engines[$key] = null;
        }

        try {
            $engine = Yii::$container->get($descriptor->engineClass);
        } catch (Throwable $e) {
            Yii::error(
                "Не удалось создать ядро поиска «{$key}»: {$e->getMessage()}",
                'search/engine',
            );

            return $this->engines[$key] = null;
        }

        if (!$engine instanceof SearchEngineInterface) {
            Yii::error(
                "Класс {$descriptor->engineClass} не реализует " . SearchEngineInterface::class . '.',
                'search/engine',
            );

            return $this->engines[$key] = null;
        }

        return $this->engines[$key] = $engine;
    }

    /**
     * Все ядра, которые удалось создать, — для страницы состояния индекса.
     *
     * @return array<string, SearchEngineInterface>
     */
    public function engines(): array
    {
        $engines = [];

        foreach (array_keys($this->descriptors()) as $key) {
            $engine = $this->engine($key);

            if ($engine !== null) {
                $engines[$key] = $engine;
            }
        }

        return $engines;
    }

    /**
     * Подпись ядра для интерфейса; для неизвестного ключа — сам ключ, чтобы в админке было видно,
     * что именно выбрано и не найдено.
     */
    public function labelFor(string $key): string
    {
        return $this->descriptors()[$key]->label ?? $key;
    }
}
