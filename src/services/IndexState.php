<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\services;

use Besnovatyj\Search\entities\SearchDocumentRecord;
use Yii;
use yii\db\Connection;
use yii\db\Exception;

/**
 * Состояние поискового индекса: когда собран, каким ядром, актуален ли.
 *
 * Отвечает на два практических вопроса, которые иначе всплывают в проде как «поиск ничего не
 * находит»:
 *  - индекс собран другим ядром, чем выбрано сейчас (сменили движок в настройках — стеммер стал
 *    другим, и старый индекс больше не соответствует запросам);
 *  - контент правился после последней сборки.
 *
 * В обоих случаях администратор видит на странице модуля предупреждение с кнопкой пересборки,
 * а не гадает, почему выдача пустая.
 */
final class IndexState
{
    private const string TABLE = '{{%search_index_state}}';

    private const string KEY_REBUILT_AT = 'rebuilt_at';
    private const string KEY_ENGINE = 'engine';
    private const string KEY_DOCUMENTS = 'documents';
    private const string KEY_DIRTY = 'dirty';

    /** Время последней успешной пересборки (Unix-timestamp) или null, если индекса ещё не было. */
    public function rebuiltAt(): ?int
    {
        $value = $this->get(self::KEY_REBUILT_AT);

        return $value === null ? null : (int)$value;
    }

    /** Ключ ядра, которым собран текущий индекс. */
    public function builtWithEngine(): ?string
    {
        return $this->get(self::KEY_ENGINE);
    }

    /** Сколько документов попало в индекс при последней сборке. */
    public function documentCount(): int
    {
        return (int)($this->get(self::KEY_DOCUMENTS) ?? 0);
    }

    /** Сколько документов лежит в каталоге прямо сейчас. */
    public function catalogCount(): int
    {
        return (int)SearchDocumentRecord::find()->count();
    }

    /**
     * Индекс требует пересборки: его никогда не собирали, собрали другим ядром или контент
     * помечен изменившимся.
     */
    public function isStale(string $currentEngine): bool
    {
        return $this->rebuiltAt() === null
            || $this->builtWithEngine() !== $currentEngine
            || $this->get(self::KEY_DIRTY) === '1';
    }

    /** Человекочитаемая причина устаревания — для сообщения в админке. */
    public function staleReason(string $currentEngine): ?string
    {
        if ($this->rebuiltAt() === null) {
            return 'Индекс ещё ни разу не собирался.';
        }

        $builtWith = $this->builtWithEngine();
        if ($builtWith !== $currentEngine) {
            return sprintf(
                'Индекс собран ядром «%s», а сейчас выбрано «%s» — нужна полная пересборка.',
                (string)$builtWith,
                $currentEngine,
            );
        }

        if ($this->get(self::KEY_DIRTY) === '1') {
            return 'Контент или настройки индексации менялись после последней сборки.';
        }

        return null;
    }

    /**
     * Отметить индекс устаревшим.
     *
     * Точка расширения: сейчас флаг никто не выставляет, потому что инкрементального обновления
     * индекса нет — переиндексация всегда полная, и «устарел» определяется по ядру и времени
     * сборки. Метод останется нужным, когда контентные модули начнут сообщать о правках.
     */
    public function markDirty(): void
    {
        $this->set(self::KEY_DIRTY, '1');
    }

    /**
     * Зафиксировать успешную пересборку.
     */
    public function markRebuilt(string $engine, int $documents): void
    {
        $this->set(self::KEY_REBUILT_AT, (string)time());
        $this->set(self::KEY_ENGINE, $engine);
        $this->set(self::KEY_DOCUMENTS, (string)$documents);
        $this->set(self::KEY_DIRTY, '0');
    }

    private function db(): Connection
    {
        return Yii::$app->db;
    }

    private function get(string $name): ?string
    {
        $value = $this->db()
            ->createCommand('SELECT [[value]] FROM ' . self::TABLE . ' WHERE [[name]] = :name', [':name' => $name])
            ->queryScalar();

        return $value === false || $value === null ? null : (string)$value;
    }

    /**
     * @throws Exception
     */
    private function set(string $name, string $value): void
    {
        $this->db()->createCommand()->upsert(
            self::TABLE,
            ['name' => $name, 'value' => $value, 'updated_at' => time()],
            ['value' => $value, 'updated_at' => time()],
        )->execute();
    }
}
