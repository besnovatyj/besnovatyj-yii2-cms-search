<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\settings;

/**
 * Сборка {@see SearchSettings} из `params` модуля.
 *
 * Единственное место, где знают, как называются параметры и какие у них дефолты и границы.
 * Дефолты дублируют `config/config.php` намеренно: конфиг модуля — это то, что видит и правит
 * администратор, а эти значения — страховка на случай, когда модуль настроек ещё не применил
 * ничего (первый запуск, сброс настроек, вызов из теста).
 */
final class SearchSettingsFactory
{
    /**
     * @param array<string, mixed> $params `params` модуля поиска
     */
    public function create(array $params): SearchSettings
    {
        $reader = new ParamReader($params);

        return new SearchSettings(
            engine: $reader->string('engine'),
            fallbackEngine: $reader->string('fallbackEngine'),
            disabledSources: $reader->list('disabledSources'),
            boosts: $reader->floatMap('boosts'),
            synonymGroups: $reader->groups('synonyms'),
            minQueryLength: $reader->int('minQueryLength', 3, min: 1, max: 10),
            maxQueryLength: $reader->int('maxQueryLength', 128, min: 16, max: 1000),
            perPage: $reader->int('perPage', 20, min: 1, max: 100),
            fuzzy: $reader->bool('fuzzy', true),
            snippetLength: $reader->int('snippetLength', 240, min: 80, max: 600),
        );
    }
}
