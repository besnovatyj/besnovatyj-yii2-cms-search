<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\services;

use Besnovatyj\Search\contracts\SearchEngineInterface;
use Besnovatyj\Search\contracts\SearchHit;
use Besnovatyj\Search\contracts\SearchQuery;
use Besnovatyj\Search\entities\SearchDocumentRecord;
use Throwable;
use Yii;

/**
 * Сценарий поиска: пользовательский ввод → ядро → готовая страница выдачи.
 *
 * Здесь собрано всё, что одинаково при любом движке: очистка и расширение запроса, выбор ядра
 * с деградацией на запасное, сборка документов по идентификаторам, вкладки-фасеты и пагинация.
 * Ядро отвечает ровно за одно — какие документы и в каком порядке соответствуют словам запроса.
 *
 * Отказ ядра здесь не превращается в ошибку страницы: посетителю показывается объяснимая пустая
 * выдача, а причина уходит в лог. Поиск — не та функция, ради которой стоит отдавать HTTP 500.
 */
final class SearchService
{
    public function __construct(
        private readonly EngineResolver $engines,
        private readonly SourceRegistry $sources,
        private readonly QueryNormalizer $normalizer,
        private readonly SearchSettings $settings,
    ) {
    }

    /**
     * Выполнить поиск и собрать страницу выдачи.
     *
     * @param list<string> $types фильтр по типам контента (пустой — искать везде)
     */
    public function search(?string $rawQuery, array $types = [], int $page = 1): SearchResultPage
    {
        $perPage = $this->settings->perPage();
        $types = $this->sources->filterTypes($types);
        $query = $this->normalizer->normalize($rawQuery);

        if ($query === '') {
            return SearchResultPage::empty(SearchStatus::NoQuery, '', $types, $perPage);
        }

        if (!$this->normalizer->isSearchable($query)) {
            return SearchResultPage::empty(SearchStatus::TooShort, $query, $types, $perPage);
        }

        $engine = $this->engines->active();

        if ($engine === null) {
            return SearchResultPage::empty(SearchStatus::EngineUnavailable, $query, $types, $perPage);
        }

        $capabilities = $engine->capabilities();
        $page = max(1, $page);

        $engineQuery = new SearchQuery(
            text: $this->normalizer->expand($query),
            types: $types,
            offset: ($page - 1) * $perPage,
            limit: $perPage,
            withFacets: $capabilities->facets && count($this->sources->enabledSources()) > 1,
            highlight: $capabilities->highlight,
        );

        try {
            $result = $engine->query($engineQuery);
        } catch (Throwable $e) {
            Yii::error('Ошибка ядра поиска: ' . $e->getMessage(), 'search/query');

            return SearchResultPage::empty(SearchStatus::EngineUnavailable, $query, $types, $perPage);
        }

        $items = $this->buildItems($result->hits, $capabilities->highlight ? $engine : null, $query);

        return new SearchResultPage(
            items: $items,
            total: $result->total,
            page: $page,
            perPage: $perPage,
            facets: $this->buildFacets($result->facets, $types),
            query: $query,
            types: $types,
            suggestion: $result->suggestion,
            status: $items === [] && $result->total === 0 && $this->isIndexEmpty()
                ? SearchStatus::IndexEmpty
                : SearchStatus::Ok,
        );
    }

    /**
     * Собрать карточки выдачи по идентификаторам, сохранив порядок ранжирования ядра.
     *
     * Документы читаются одним запросом; строки, исчезнувшие из каталога между индексацией и
     * выводом, просто пропускаются — это нормальная гонка, а не ошибка.
     *
     * Подсветка берётся из ответа ядра, если оно вернуло готовый фрагмент (так делает движок,
     * хранящий текст у себя). Ядро, которое текста не хранит, подсвечивает фрагмент из каталога
     * по запросу — своей морфологией, иначе по запросу «ботинок» не подсветилось бы «ботинках».
     *
     * @param list<SearchHit>            $hits
     * @param SearchEngineInterface|null $highlighter ядро, умеющее подсветку, либо null
     * @return list<SearchResultItem>
     */
    private function buildItems(array $hits, ?SearchEngineInterface $highlighter, string $query): array
    {
        if ($hits === []) {
            return [];
        }

        $ids = array_map(static fn (SearchHit $hit): int => $hit->documentId, $hits);

        /** @var array<int, SearchDocumentRecord> $records */
        $records = SearchDocumentRecord::find()
            ->where(['id' => $ids])
            ->indexBy('id')
            ->all();

        $snippetLength = $this->settings->snippetLength();
        $items = [];

        foreach ($hits as $hit) {
            $record = $records[$hit->documentId] ?? null;

            if ($record === null) {
                continue;
            }

            $snippet = $record->snippet($snippetLength);
            $snippetIsHtml = false;

            if ($hit->highlight !== null && trim($hit->highlight) !== '') {
                $snippet = $hit->highlight;
                $snippetIsHtml = true;
            } elseif ($highlighter !== null) {
                $snippet = $highlighter->highlight($snippet, $query);
                $snippetIsHtml = true;
            }

            $items[] = new SearchResultItem(
                type: $record->type,
                typeLabel: $this->sources->labelFor($record->type),
                title: $record->title,
                url: $record->url(),
                snippet: $snippet,
                snippetIsHtml: $snippetIsHtml,
                date: $record->published_at === null ? null : (int)$record->published_at,
                image: $record->image,
                score: $hit->score,
            );
        }

        return $items;
    }

    /**
     * Вкладки по типам контента: только те источники, где что-то нашлось.
     *
     * @param array<string,int> $facets
     * @param list<string>      $activeTypes
     * @return list<array{type:string,label:string,count:int,active:bool}>
     */
    private function buildFacets(array $facets, array $activeTypes): array
    {
        if ($facets === []) {
            return [];
        }

        $active = array_flip($activeTypes);
        $tabs = [];

        foreach ($facets as $type => $count) {
            if ($count <= 0) {
                continue;
            }

            $tabs[] = [
                'type' => (string)$type,
                'label' => $this->sources->labelFor((string)$type),
                'count' => (int)$count,
                'active' => isset($active[$type]),
            ];
        }

        usort($tabs, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $tabs;
    }

    /**
     * Каталог пуст — значит индекс ни разу не собирали (или собрали вхолостую).
     */
    private function isIndexEmpty(): bool
    {
        return !SearchDocumentRecord::find()->exists();
    }
}
