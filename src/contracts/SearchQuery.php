<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\contracts;

/**
 * Нормализованный поисковый запрос, передаваемый фасадом в ядро {@see SearchEngineInterface}.
 *
 * Всё, что нужно ядру для выдачи ОДНОЙ готовой страницы результатов: текст, фильтр по типам,
 * окно пагинации и флаги дополнительных вычислений. Фильтрация и пагинация намеренно входят
 * в запрос, а не выполняются фасадом поверх результата: иначе контракт молча ограничивал бы
 * систему объёмом, который влезает в один ответ, и рост сайта упирался бы в архитектуру, а не
 * в возможности движка. Ядро, которое чего-то не умеет, выполняет это своими средствами и
 * честно сообщает об этом в {@see EngineCapabilities}.
 *
 * Текст сюда приходит уже подготовленным фасадом: обрезанным по длине и расширенным синонимами.
 * Стемминг/лемматизация — забота ядра, потому что они обязаны совпадать с тем, что применялось
 * при индексации.
 */
final readonly class SearchQuery
{
    /** Сортировка по релевантности — умеет любое ядро. */
    public const string SORT_RELEVANCE = 'relevance';

    /** Сортировка по дате публикации, свежие первыми — только если {@see EngineCapabilities::$sortByDate}. */
    public const string SORT_DATE = 'date';

    /**
     * @param string        $text       Текст запроса, уже нормализованный фасадом (непустой).
     * @param list<string>  $types      Фильтр по ключам источников (`blog.post`). Пустой массив —
     *                                  искать по всем типам.
     * @param int           $offset     Смещение окна выдачи (0 — первая страница).
     * @param int           $limit      Размер страницы.
     * @param bool          $withFacets Посчитать распределение совпадений по типам (для вкладок).
     * @param bool          $highlight  Вернуть подсвеченные фрагменты в {@see SearchHit::$highlight}.
     * @param string        $sort       Один из `SORT_*`.
     */
    public function __construct(
        public string $text,
        public array $types = [],
        public int $offset = 0,
        public int $limit = 20,
        public bool $withFacets = false,
        public bool $highlight = false,
        public string $sort = self::SORT_RELEVANCE,
    ) {
    }
}
