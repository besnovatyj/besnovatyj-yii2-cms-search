<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\services;

/**
 * Итог переиндексации — то, что показывается администратору в консоли и в админке.
 *
 * Разбивка по источникам здесь не для красоты: «в индексе 0 документов типа `shop.product`»
 * — единственный способ заметить, что провайдер модуля молча отдал пустой список (например,
 * из-за фильтра публикации), не открывая базу.
 */
final readonly class IndexReport
{
    /**
     * @param string            $engine    Ключ ядра, которым собран индекс.
     * @param array<string,int> $bySource  Сколько документов дал каждый источник.
     * @param int               $documents Всего документов в индексе.
     * @param int               $removed   Сколько устаревших строк каталога удалено.
     * @param float             $seconds   Длительность полной пересборки.
     */
    public function __construct(
        public string $engine,
        public array $bySource,
        public int $documents,
        public int $removed,
        public float $seconds,
    ) {
    }
}
