<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\services;

use Besnovatyj\Search\contracts\SearchEngineInterface;
use Besnovatyj\Search\settings\SearchSettings;
use Yii;

/**
 * Выбор ядра, которое обслуживает запросы прямо сейчас.
 *
 * Кто установлен — знает {@see EngineRegistry}; здесь решается, кем из установленных работать:
 * выбранным в настройках, а если оно не отвечает (упавший демон, потерянное соединение) —
 * запасным. Посетитель получает результаты, а не HTTP 500: при двух установленных ядрах отказ
 * движка перестаёт быть аварией.
 *
 * Проверка доступности выполняется один раз за запрос: она стоит обращения к демону, а на странице
 * поиск вызывается не единожды.
 */
final class EngineResolver
{
    /** Ключ ядра, которым решено работать; null — решение ещё не принято, '' — рабочего ядра нет. */
    private ?string $resolvedKey = null;

    public function __construct(
        private readonly EngineRegistry $registry,
        private readonly SearchSettings $settings,
    ) {
    }

    /** Ключ ядра, выбранного администратором (без учёта деградации). */
    public function configuredKey(): string
    {
        return $this->settings->engine;
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

    /**
     * Рабочее ядро: выбранное в настройках, иначе запасное, иначе null.
     */
    public function active(): ?SearchEngineInterface
    {
        if ($this->resolvedKey !== null) {
            return $this->registry->engine($this->resolvedKey);
        }

        $configured = $this->settings->engine;
        $engine = $this->registry->engine($configured);

        if ($engine !== null && $engine->isAvailable()) {
            $this->resolvedKey = $configured;

            return $engine;
        }

        $fallbackKey = $this->settings->fallbackEngine;

        if ($fallbackKey !== '' && $fallbackKey !== $configured) {
            $fallback = $this->registry->engine($fallbackKey);

            if ($fallback !== null && $fallback->isAvailable()) {
                Yii::warning(
                    sprintf(
                        'Ядро поиска «%s» недоступно (%s), выдача обслуживается запасным «%s».',
                        $configured,
                        $engine?->unavailableReason() ?? 'ядро не установлено',
                        $fallbackKey,
                    ),
                    'search/engine',
                );
                $this->resolvedKey = $fallbackKey;

                return $fallback;
            }
        }

        Yii::error(
            sprintf(
                'Ни одно ядро поиска недоступно (выбрано «%s»: %s).',
                $configured,
                $engine?->unavailableReason() ?? 'ядро не установлено',
            ),
            'search/engine',
        );
        $this->resolvedKey = '';

        return null;
    }

    /**
     * Конкретное ядро по ключу — без проверки доступности и без подмены.
     * Нужен индексатору: собирать индекс надо именно тем ядром, которое выбрано.
     */
    public function engine(string $key): ?SearchEngineInterface
    {
        return $this->registry->engine($key);
    }
}
