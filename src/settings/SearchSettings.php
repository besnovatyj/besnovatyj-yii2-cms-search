<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\settings;

/**
 * Настройки сквозного поиска — готовые значения, ничего не вычисляющие и никуда не ходящие.
 *
 * Строки из админки уже разобраны, диапазоны применены: потребитель получает список, карту или
 * число и работает с ними, не зная ни про формат хранения, ни про модуль настроек. Собирает объект
 * {@see SearchSettingsFactory}, а `params` ему передаёт composition root (`config/common.php`) —
 * поэтому здесь нет ни одного обращения к `Yii`, и класс можно создать в тесте одной строкой.
 */
final readonly class SearchSettings
{
    /**
     * @param string                $engine          Ключ выбранного ядра ({@see \Besnovatyj\Search\contracts\SearchEngineDescriptor::$key}).
     * @param string                $fallbackEngine  Ключ запасного ядра; пустая строка — деградировать
     *                                               в пустую выдачу.
     * @param list<string>          $disabledSources Источники, исключённые администратором из поиска.
     * @param array<string, float>  $boosts          Переопределённые веса источников.
     * @param list<list<string>>    $synonymGroups   Словарь синонимов: группа взаимозаменяемых слов.
     * @param int                   $minQueryLength  Короче — в индекс не идём.
     * @param int                   $maxQueryLength  Всё сверх — отбрасывается.
     * @param int                   $perPage         Размер страницы выдачи.
     * @param bool                  $fuzzy           Искать с опечатками, если ядро это умеет.
     * @param int                   $snippetLength   Длина фрагмента в карточке выдачи.
     */
    public function __construct(
        public string $engine,
        public string $fallbackEngine,
        public array $disabledSources,
        public array $boosts,
        public array $synonymGroups,
        public int $minQueryLength,
        public int $maxQueryLength,
        public int $perPage,
        public bool $fuzzy,
        public int $snippetLength,
    ) {
    }
}
