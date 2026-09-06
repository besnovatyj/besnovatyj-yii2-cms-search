<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\contracts;

/**
 * Одно совпадение, возвращаемое ядром: ссылка на документ каталога плюс оценка релевантности.
 *
 * Ядро возвращает ИДЕНТИФИКАТОРЫ, а не содержимое: сами документы (заголовок, ссылка, анонс,
 * картинка) хранит фасад в своей таблице `search_documents` и собирает их сам. Благодаря этому
 * выдача выглядит и работает одинаково при любом движке, а ядру не нужно дублировать поля,
 * которые ему не нужны для поиска.
 */
final readonly class SearchHit
{
    /**
     * @param int         $documentId Первичный ключ строки каталога `search_documents`.
     * @param float       $score      Оценка релевантности. Сопоставима только внутри одной выдачи
     *                                одного ядра — абсолютные значения у разных движков разные,
     *                                показывать их пользователю не нужно.
     * @param string|null $highlight  Подсвеченный фрагмент текста с совпадением (HTML с `<mark>`),
     *                                если запрошен и ядро это умеет. null — фасад покажет
     *                                обычный анонс документа.
     */
    public function __construct(
        public int $documentId,
        public float $score,
        public ?string $highlight = null,
    ) {
    }
}
