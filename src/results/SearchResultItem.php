<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\results;

/**
 * Одна карточка поисковой выдачи — готовая к выводу, без обращения к базе из шаблона.
 *
 * Вьюха получает уже собранные заголовок, ссылку и фрагмент: ссылка построена из роута и
 * параметров в момент запроса (короткий URL мог поменяться после индексации), а фрагмент — либо
 * подсветка от ядра, либо анонс из каталога.
 */
final readonly class SearchResultItem
{
    /**
     * @param string      $type      Ключ источника (`blog.post`).
     * @param string      $typeLabel Подпись источника для метки на карточке («Статьи блога»).
     * @param string      $title     Заголовок документа.
     * @param string      $url       Готовая ссылка на документ.
     * @param string      $snippet   Фрагмент текста. Может содержать `<mark>` — см. `$snippetIsHtml`.
     * @param bool        $snippetIsHtml Фрагмент пришёл от ядра с подсветкой и уже безопасен:
     *                                   исходный текст экранирован ядром, добавлены только `<mark>`.
     *                                   Если false — вьюха обязана экранировать фрагмент сама.
     * @param int|null    $date      Дата публикации, Unix-timestamp.
     * @param string|null $image     Превью для карточки.
     * @param float       $score     Оценка релевантности (для отладки, пользователю не показывается).
     */
    public function __construct(
        public string $type,
        public string $typeLabel,
        public string $title,
        public string $url,
        public string $snippet,
        public bool $snippetIsHtml,
        public ?int $date,
        public ?string $image,
        public float $score,
    ) {
    }
}
