<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\contracts;

/**
 * Ответ ядра на {@see SearchQuery}: одна страница совпадений плюс метаданные выдачи.
 *
 * `total` обязателен и считается ПО ВСЕМ совпадениям, а не по возвращённому окну — иначе фасад
 * не сможет построить пагинацию. Ядро, которое не умеет считать точное число, обязано вернуть
 * хотя бы количество, до которого досчитало (тогда пагинация будет консервативной, но корректной).
 */
final readonly class SearchResult
{
    /**
     * @param list<SearchHit>    $hits       Совпадения текущей страницы, в порядке выдачи.
     * @param int                $total      Всего совпадений по запросу (без учёта окна).
     * @param array<string,int>  $facets     Распределение совпадений по типам: `['blog.post' => 12]`.
     *                                       Пустой массив, если фасеты не запрашивались или ядро
     *                                       их не умеет.
     * @param string|null        $suggestion Исправленный вариант запроса («возможно, вы имели в виду»),
     *                                       если ядро умеет подсказки и нашла что предложить.
     */
    public function __construct(
        public array $hits,
        public int $total,
        public array $facets = [],
        public ?string $suggestion = null,
    ) {
    }

    /**
     * Пустая выдача — для несостоявшегося запроса и для деградации недоступного ядра.
     */
    public static function empty(): self
    {
        return new self([], 0);
    }
}
