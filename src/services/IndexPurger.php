<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\services;

use Besnovatyj\Search\contracts\PurgeableEngine;
use Besnovatyj\Search\entities\SearchDocumentRecord;
use RuntimeException;
use Throwable;
use Yii;
use yii\db\Connection;

/**
 * Полная очистка поискового индекса: каталог фасада и хранилища всех установленных ядер.
 *
 * Зачем: всё поисковое восстановимо пересборкой, а места в базе (и в её дампе) занимает много —
 * полный текст материалов в каталоге плюс словари и списки вхождений ядра. Очистка отдаёт это
 * место, пересборка — не её забота: после очистки индекс находится в состоянии «ещё ни разу не
 * собирали», и страница состояния сама это покажет.
 *
 * Чистятся ВСЕ установленные ядра, а не только активное: после смены ядра в настройках данные
 * прежнего остаются в базе и занимают место так же.
 */
final class IndexPurger
{
    /** Таблицы фасада. */
    private const array TABLES = ['{{%search_documents}}', '{{%search_index_state}}'];

    public function __construct(
        private readonly EngineRegistry $registry,
    ) {
    }

    /**
     * Стереть всё поисковое.
     *
     * Сбой одного ядра (например, демон Manticore не отвечает) не останавливает очистку остальных
     * и каталога: данные в базе проекта всё равно должны быть убраны. Но и не глотается — после
     * очистки бросается исключение со списком ядер, которые очистить не удалось.
     *
     * @throws RuntimeException если не удалось очистить хотя бы одно ядро
     */
    public function purge(): void
    {
        $failed = [];

        foreach ($this->registry->engines() as $key => $engine) {
            if (!$engine instanceof PurgeableEngine) {
                continue;
            }

            try {
                $engine->purge();
            } catch (Throwable $e) {
                Yii::error("Не удалось очистить ядро поиска «{$key}»: {$e->getMessage()}", 'search/index');
                $failed[] = $this->registry->labelFor($key) . ': ' . $e->getMessage();
            }
        }

        // TRUNCATE, а не DELETE: каталог целиком восстановим, а место таблица отдаёт сразу.
        $this->db()->createCommand()->truncateTable(SearchDocumentRecord::tableName())->execute();
        $this->db()->createCommand()->delete('{{%search_index_state}}')->execute();

        if ($failed !== []) {
            throw new RuntimeException('Каталог очищен, но не все ядра: ' . implode('; ', $failed));
        }
    }

    /**
     * Сколько места занимает всё поисковое, в байтах: таблицы фасада плюс то, что сообщили ядра.
     *
     * Ядро, не умеющее посчитать свой размер или упавшее при подсчёте, просто не добавляет ничего:
     * строка в менеджере очистки справочная, ронять её из-за одного ядра незачем.
     */
    public function storageBytes(): int
    {
        $this->disableStatsCache();

        $bytes = $this->tablesBytes($this->db(), self::TABLES);

        foreach ($this->registry->engines() as $key => $engine) {
            if (!$engine instanceof PurgeableEngine) {
                continue;
            }

            try {
                $bytes += $engine->storageBytes() ?? 0;
            } catch (Throwable $e) {
                Yii::warning("Не удалось узнать размер ядра поиска «{$key}»: {$e->getMessage()}", 'search/index');
            }
        }

        return $bytes;
    }

    /**
     * Размер таблиц базы проекта по `information_schema` (данные + индексы).
     *
     * Публичный и статический, потому что тот же подсчёт нужен ядрам, хранящим индекс в базе
     * проекта: так запрос к `information_schema` живёт в одном месте.
     *
     * @param list<string> $tables имена таблиц; `{{%…}}` раскрывается с префиксом, имена без
     *                             плейсхолдера берутся как есть
     */
    public static function tablesBytes(Connection $db, array $tables): int
    {
        if ($tables === []) {
            return 0;
        }

        $names = array_map(
            static fn (string $table): string => $db->getSchema()->getRawTableName($table),
            $tables,
        );

        return (int)$db->createCommand(
            'SELECT COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH), 0) FROM information_schema.TABLES'
            . ' WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME IN (' . implode(', ', array_map([$db, 'quoteValue'], $names)) . ')',
        )->queryScalar();
    }

    /**
     * Отключить кэш статистики `information_schema` для текущего соединения.
     *
     * В MySQL 8 размеры таблиц в `information_schema.TABLES` по умолчанию кэшируются на сутки
     * (`information_schema_stats_expiry = 86400`): без этого сразу после очистки строка показывала
     * бы прежние мегабайты. Переменная сессионная и действует только в этом запросе. На сервере
     * без такой переменной (MySQL 5.7, MariaDB) кэша нет и делать нечего — ошибку игнорируем.
     */
    private function disableStatsCache(): void
    {
        try {
            $this->db()->createCommand('SET SESSION information_schema_stats_expiry = 0')->execute();
        } catch (Throwable) {
        }
    }

    private function db(): Connection
    {
        return Yii::$app->db;
    }
}
