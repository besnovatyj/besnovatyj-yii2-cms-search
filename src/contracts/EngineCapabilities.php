<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\contracts;

/**
 * Паспорт возможностей ядра поиска.
 *
 * Нужен потому, что движки различаются не настройками, а умениями: лемматизация есть только у
 * Manticore, подсказки — не у всех, инкрементальное обновление индекса — тоже. Фасад спрашивает
 * паспорт ДО того, как рисовать интерфейс: не выводит вкладки фасетов, если ядро их не считает,
 * не обещает «возможно, вы имели в виду», если подсказок нет, и не предлагает сортировку по дате
 * ядру, которое её не поддерживает.
 *
 * Молчаливое игнорирование неподдерживаемой опции (как это делает адаптер редактора) здесь
 * не годится: от возможностей движка зависит вёрстка страницы выдачи, а не только внутренний конфиг.
 */
final readonly class EngineCapabilities
{
    /**
     * @param bool     $fuzzy           Поиск с опечатками (нечёткое совпадение слов).
     * @param bool     $lemmatization   Настоящая лемматизация («мыши» → «мышь», «шла» → «идти»),
     *                                  а не только отсечение окончаний стеммером.
     * @param bool     $highlight       Умеет вернуть подсвеченный фрагмент — {@see SearchEngineInterface::highlight()}.
     * @param bool     $suggestion      Умеет предложить исправление запроса.
     * @param bool     $facets          Считает распределение совпадений по типам.
     * @param bool     $sortByDate      Поддерживает {@see SearchQuery::SORT_DATE}.
     * @param bool     $incremental     Умеет обновлять индекс по одному документу
     *                                  ({@see SearchEngineInterface::indexDocument()}) без полной пересборки.
     * @param int|null $comfortableSize Ориентировочный предел применимости ядра в документах, при
     *                                  превышении которого стоит перейти на более сильное ядро.
     *                                  null — ядро масштабируется и предела не заявляет.
     *                                  Значение справочное: оно показывается администратору на
     *                                  странице состояния индекса, но ничего не блокирует.
     */
    public function __construct(
        public bool $fuzzy = false,
        public bool $lemmatization = false,
        public bool $highlight = false,
        public bool $suggestion = false,
        public bool $facets = false,
        public bool $sortByDate = false,
        public bool $incremental = false,
        public ?int $comfortableSize = null,
    ) {
    }
}
