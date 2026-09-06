<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\contracts;

/**
 * Документ в том виде, в каком его получает ядро на индексацию.
 *
 * Отличается от контрактного {@see \Besnovatyj\Contracts\search\SearchDocument} тем, что здесь
 * уже НЕТ ничего лишнего для поиска: HTML вычищен, шорткоды раскрыты, ссылка и картинка остались
 * в каталоге фасада. Ядру передаётся только то, по чему оно ищет, и идентификатор, который оно
 * должно вернуть в {@see SearchHit::$documentId}.
 *
 * Разделение полей `title` / `text` / `keywords` сохранено намеренно: у совпадения в заголовке
 * вес выше, чем в теле, и ядро вправе реализовать это как умеет — через полевые веса (Manticore)
 * или через повтор заголовка в индексируемой строке (TNTSearch).
 */
final readonly class IndexableDocument
{
    /**
     * @param int      $documentId Первичный ключ строки каталога `search_documents`.
     * @param string   $type       Ключ источника (`blog.post`) — для фильтра и фасетов.
     * @param string   $title      Заголовок, чистый текст.
     * @param string   $text       Основной текст, уже без HTML и шорткодов.
     * @param string   $keywords   Дополнительные слова (теги, категория, артикул), чистый текст.
     * @param int|null $date       Дата публикации, Unix-timestamp — для сортировки по свежести.
     * @param float    $boost      Итоговый множитель важности документа (вес источника из настроек,
     *                             умноженный на собственный вес документа). Ядро применяет его так,
     *                             как умеет; ядро без поддержки весов вправе его проигнорировать.
     */
    public function __construct(
        public int $documentId,
        public string $type,
        public string $title,
        public string $text,
        public string $keywords = '',
        public ?int $date = null,
        public float $boost = 1.0,
    ) {
    }
}
