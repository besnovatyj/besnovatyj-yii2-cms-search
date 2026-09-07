<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\services;

use Besnovatyj\Contracts\search\SearchableProvider;
use Besnovatyj\Contracts\search\SearchSource;
use Besnovatyj\Search\settings\SearchSettings;
use Yii;

/**
 * Реестр источников контента: находит модули-провайдеры и сводит их объявления в один список.
 *
 * Обход зарегистрированных модулей с проверкой `instanceof` — тот же приём, что в
 * {@see \Besnovatyj\Menu\services\MenuTargetRegistry}: модуль поиска не знает имён контентных
 * модулей, а они не знают о нём. Отключённый в modman модуль в конфиг приложения не попадает,
 * поэтому и в реестре не появится — отдельной проверки активности не нужно.
 *
 * Поверх объявлений накладываются настройки администратора: выключенные источники исчезают
 * из выдачи и из индексации, веса переопределяются.
 */
final class SourceRegistry
{
    /** @var array<string, SearchableProvider>|null */
    private ?array $providers = null;

    /** @var array<string, SearchSource>|null */
    private ?array $sources = null;

    /** @var array<string, string>|null карта «тип источника => id модуля-провайдера» */
    private ?array $owners = null;

    public function __construct(private readonly SearchSettings $settings)
    {
    }

    /**
     * Модули, объявившие себя провайдерами контента. Ключ — id модуля.
     *
     * @return array<string, SearchableProvider>
     */
    public function providers(): array
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        $providers = [];
        foreach (array_keys(Yii::$app->getModules()) as $id) {
            $module = Yii::$app->getModule((string)$id);
            if ($module instanceof SearchableProvider) {
                $providers[(string)$id] = $module;
            }
        }

        return $this->providers = $providers;
    }

    /**
     * Все объявленные источники, включая отключённые администратором. Ключ — тип источника.
     *
     * @return array<string, SearchSource>
     */
    public function allSources(): array
    {
        if ($this->sources !== null) {
            return $this->sources;
        }

        $sources = [];
        $owners = [];

        foreach ($this->providers() as $moduleId => $provider) {
            foreach ($provider->searchSources() as $source) {
                if (isset($sources[$source->type])) {
                    Yii::warning(
                        "Источник поиска «{$source->type}» объявлен дважды: модулями "
                        . "«{$owners[$source->type]}» и «{$moduleId}». Взят первый.",
                        'search/registry',
                    );
                    continue;
                }

                $sources[$source->type] = $source;
                $owners[$source->type] = (string)$moduleId;
            }
        }

        $this->owners = $owners;

        return $this->sources = $sources;
    }

    /**
     * Источники, участвующие в поиске сейчас.
     *
     * @return array<string, SearchSource>
     */
    public function enabledSources(): array
    {
        $disabled = array_flip($this->settings->disabledSources);

        return array_filter(
            $this->allSources(),
            static fn (string $type): bool => !isset($disabled[$type]),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Ключи включённых источников.
     *
     * @return list<string>
     */
    public function enabledTypes(): array
    {
        return array_keys($this->enabledSources());
    }

    /**
     * Провайдер, которому принадлежит источник, либо null для неизвестного типа.
     */
    public function providerFor(string $type): ?SearchableProvider
    {
        $this->allSources();
        $moduleId = $this->owners[$type] ?? null;

        return $moduleId === null ? null : ($this->providers()[$moduleId] ?? null);
    }

    /**
     * Подпись источника для вкладок выдачи; для незнакомого типа — сам ключ, чтобы в интерфейсе
     * не появлялись пустые вкладки после удаления модуля.
     */
    public function labelFor(string $type): string
    {
        return $this->allSources()[$type]->label ?? $type;
    }

    /**
     * Итоговый вес источника: значение из настроек, иначе объявленное модулем.
     */
    public function boostFor(string $type): float
    {
        $overrides = $this->settings->boosts;
        if (isset($overrides[$type])) {
            return $overrides[$type];
        }

        return $this->allSources()[$type]->boost ?? 1.0;
    }

    /**
     * Оставить из запрошенных типов только существующие и включённые.
     *
     * @param list<string> $types
     * @return list<string>
     */
    public function filterTypes(array $types): array
    {
        $enabled = $this->enabledSources();

        return array_values(array_filter(
            $types,
            static fn (string $type): bool => isset($enabled[$type]),
        ));
    }
}
