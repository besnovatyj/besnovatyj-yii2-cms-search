<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\services;

use Besnovatyj\Contracts\search\SearchDocument;
use Besnovatyj\Search\contracts\IndexableDocument;
use Besnovatyj\Search\entities\SearchDocumentRecord;
use Besnovatyj\Search\results\IndexReport;
use Besnovatyj\Search\settings\SearchSettings;
use RuntimeException;
use Throwable;
use Yii;

/**
 * Полная переиндексация: контент модулей → каталог документов → индекс ядра.
 *
 * Инкрементального обновления здесь намеренно нет. Полная пересборка каталога сайта на тысячу
 * записей занимает секунды, а инкрементальность стоит дорого не в вычислениях, а в ошибках:
 * пропущенное событие, каскадное удаление в базе мимо ActiveRecord, изменение категории у
 * родителя — и в выдаче месяцами живут ссылки на удалённые страницы. Пересборка с нуля таких
 * состояний не имеет в принципе. Когда объём вырастет настолько, что это станет заметно,
 * инкрементальность добавит ядро (см. {@see \Besnovatyj\Search\contracts\EngineCapabilities::$incremental}),
 * а не фасад.
 *
 * Порядок работы важен:
 *  1. каталог обновляется upsert'ом по паре «источник + сущность» — идентификаторы строк
 *     остаются стабильными, поэтому ядро можно пересобирать независимо от каталога;
 *  2. строки, не подтверждённые текущим прогоном, удаляются — так исчезают документы удалённых
 *     записей, отключённых источников и снятых с публикации материалов;
 *  3. индекс ядра собирается рядом с рабочим и подменяется в самом конце — во время пересборки
 *     сайт продолжает искать по старому индексу, а не отдаёт пустую выдачу.
 */
final class Indexer
{
    /** Сколько документов каталога передаётся ядру за один вызов. */
    private const int ENGINE_BATCH = 200;

    /**
     * Предел длины индексируемого текста одного документа. Ограничение прагматичное: страница
     * длиннее полумиллиона символов — это выгрузка каталога или залипший импорт, и класть её в
     * индекс целиком незачем.
     */
    private const int MAX_CONTENT_LENGTH = 100000;

    public function __construct(
        private readonly SourceRegistry $sources,
        private readonly TextExtractor $text,
        private readonly EngineResolver $engines,
        private readonly IndexState $state,
        private readonly SearchSettings $settings,
    ) {
    }

    /**
     * Пересобрать индекс целиком.
     *
     * @param callable(string):void|null $progress Обратный вызов для вывода хода работы
     *                                            (консоль печатает, админка игнорирует).
     *
     * @throws RuntimeException если выбранного ядра нет в системе — молча собирать индекс
     *                          «в никуда» нельзя, это выглядело бы как успешная операция.
     */
    public function rebuild(?callable $progress = null): IndexReport
    {
        $startedAt = microtime(true);
        $stamp = time();

        $engineKey = $this->settings->engine;
        $engine = $this->engines->engine($engineKey);

        if ($engine === null) {
            throw new RuntimeException(
                "Ядро поиска «{$engineKey}» недоступно: модуль ядра не установлен или выключен.",
            );
        }

        $bySource = $this->refreshCatalog($stamp, $progress);
        $removed = $this->removeStale($stamp);

        $this->report($progress, 'Сборка индекса ядром «' . $engineKey . '»…');

        try {
            // Начало сборки — тоже внутри try: если ядро упадёт на создании индекса, недоделанный
            // слот должен быть убран той же веткой отката, а не остаться до следующего запуска.
            $engine->beginRebuild();

            $documents = 0;
            foreach ($this->catalogBatches() as $batch) {
                $engine->addDocuments($batch);
                $documents += count($batch);
            }

            $engine->commitRebuild();
        } catch (Throwable $e) {
            $engine->cancelRebuild();
            Yii::error('Пересборка индекса прервана: ' . $e->getMessage(), 'search/index');

            throw $e;
        }

        $this->state->markRebuilt($engineKey, $documents);

        return new IndexReport(
            engine: $engineKey,
            bySource: $bySource,
            documents: $documents,
            removed: $removed,
            seconds: round(microtime(true) - $startedAt, 2),
        );
    }

    /**
     * Забрать документы у всех включённых источников и обновить каталог.
     *
     * @param callable(string):void|null $progress
     * @return array<string,int> сколько документов дал каждый источник
     */
    private function refreshCatalog(int $stamp, ?callable $progress): array
    {
        $bySource = [];

        foreach ($this->sources->enabledSources() as $type => $source) {
            $provider = $this->sources->providerFor($type);

            if ($provider === null) {
                continue;
            }

            $boost = $this->sources->boostFor($type);
            $count = 0;

            foreach ($provider->searchDocuments($type) as $document) {
                if (!$document instanceof SearchDocument) {
                    Yii::warning(
                        "Источник «{$type}» вернул не " . SearchDocument::class . ' — документ пропущен.',
                        'search/index',
                    );
                    continue;
                }

                if ($document->type !== $type) {
                    Yii::warning(
                        "Источник «{$type}» вернул документ чужого типа «{$document->type}» — пропущен.",
                        'search/index',
                    );
                    continue;
                }

                $this->store($document, $boost, $stamp);
                $count++;
            }

            $bySource[$type] = $count;
            $this->report($progress, sprintf('  %-24s %d', $source->label, $count));
        }

        return $bySource;
    }

    /**
     * Записать один документ в каталог, сохранив идентификатор строки при повторной индексации.
     */
    private function store(SearchDocument $document, float $sourceBoost, int $stamp): void
    {
        $content = mb_substr($this->text->toPlainText($document->text), 0, self::MAX_CONTENT_LENGTH);
        $excerpt = $document->excerpt === null ? null : $this->text->toPlainText($document->excerpt);

        $attributes = SearchDocumentRecord::attributesFrom(
            document: $document,
            content: $content,
            excerpt: $excerpt === '' ? null : $excerpt,
            boost: $sourceBoost * $document->boost,
            indexedAt: $stamp,
        );

        // Upsert по уникальной паре «тип + сущность»: id строки не меняется от прогона к прогону.
        Yii::$app->db->createCommand()
            ->upsert(SearchDocumentRecord::tableName(), $attributes, $attributes)
            ->execute();
    }

    /**
     * Убрать из каталога всё, что не подтверждено текущим прогоном.
     *
     * Сюда попадают удалённые записи, снятые с публикации материалы и документы источников,
     * которые администратор отключил или чей модуль удалён из системы.
     */
    private function removeStale(int $stamp): int
    {
        return SearchDocumentRecord::deleteAll(['<', 'indexed_at', $stamp]);
    }

    /**
     * Каталог, нарезанный на пачки документов для ядра.
     *
     * @return iterable<list<IndexableDocument>>
     */
    private function catalogBatches(): iterable
    {
        $query = SearchDocumentRecord::find()->orderBy(['id' => SORT_ASC]);

        foreach ($query->batch(self::ENGINE_BATCH) as $rows) {
            $batch = [];

            /** @var SearchDocumentRecord $row */
            foreach ($rows as $row) {
                $batch[] = new IndexableDocument(
                    documentId: (int)$row->id,
                    type: $row->type,
                    title: $row->title,
                    text: $row->content,
                    keywords: (string)$row->keywords,
                    date: $row->published_at === null ? null : (int)$row->published_at,
                    boost: (float)$row->boost,
                );
            }

            yield $batch;
        }
    }

    /**
     * @param callable(string):void|null $progress
     */
    private function report(?callable $progress, string $message): void
    {
        if ($progress !== null) {
            $progress($message);
        }
    }
}
