<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\results;

/**
 * Готовая страница поисковой выдачи со всем, что нужно шаблону.
 *
 * Вьюха ничего не досчитывает и никуда не ходит: у неё есть карточки, пагинация, вкладки фасетов,
 * подсказка и статус, объясняющий пустой экран. Благодаря этому тема может переопределить
 * оформление выдачи, не зная ни про ядро, ни про устройство индекса.
 */
final readonly class SearchResultPage
{
    /**
     * @param list<SearchResultItem>                                      $items      Карточки текущей страницы.
     * @param int                                                         $total      Всего совпадений.
     * @param int                                                         $page       Текущая страница, с 1.
     * @param int                                                         $perPage    Размер страницы.
     * @param list<array{type:string,label:string,count:int,active:bool}> $facets     Вкладки по типам контента.
     *                                                                                Пустой список — фасеты
     *                                                                                не поддержаны ядром либо
     *                                                                                источник всего один.
     * @param string                                                      $query      Нормализованный запрос —
     *                                                                                его же показываем в поле ввода.
     * @param list<string>                                                $types      Активный фильтр по типам.
     * @param string|null                                                 $suggestion «Возможно, вы имели в виду».
     * @param SearchStatus                                                $status     Чем закончился поиск.
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public array $facets,
        public string $query,
        public array $types,
        public ?string $suggestion,
        public SearchStatus $status,
    ) {
    }

    /**
     * Пустая выдача с объяснением причины.
     *
     * @param list<string> $types
     */
    public static function empty(SearchStatus $status, string $query = '', array $types = [], int $perPage = 20): self
    {
        return new self(
            items: [],
            total: 0,
            page: 1,
            perPage: $perPage,
            facets: [],
            query: $query,
            types: $types,
            suggestion: null,
            status: $status,
        );
    }

    /** Всего страниц выдачи. */
    public function pageCount(): int
    {
        return $this->perPage > 0 ? (int)ceil($this->total / $this->perPage) : 1;
    }

    /** Есть ли что показывать. */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
