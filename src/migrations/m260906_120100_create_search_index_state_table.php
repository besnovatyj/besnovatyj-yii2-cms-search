<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\migrations;

use Besnovatyj\Kernel\migration\BaseMigration;
use yii\base\NotSupportedException;

/**
 * Состояние поискового индекса — маленькое key-value хранилище фасада.
 *
 * Хранит то, что нельзя вычислить из самого каталога: когда индекс собирали в последний раз,
 * каким ядром (смена движка в настройках требует полной пересборки — иначе выдача пойдёт из
 * индекса, собранного другим стеммером) и не помечен ли индекс устаревшим после правки контента.
 *
 * Именно в базе, а не в кэше: apcu на боевом сервере живёт в каждом процессе PHP-FPM отдельно,
 * и флаг, выставленный в одном воркере, остальные бы не увидели.
 */
class m260906_120100_create_search_index_state_table extends BaseMigration
{
    public const string TABLE_NAME = '{{%search_index_state}}';

    /**
     * @throws NotSupportedException
     */
    public function safeUp(): void
    {
        parent::safeUp();

        if ($this->existTable(static::TABLE_NAME)) {
            return;
        }

        $this->createTable(static::TABLE_NAME, [
            'name'       => $this->string(64)->notNull()
                ->comment('Ключ состояния'),
            'value'      => $this->text()->null()->defaultValue(null)
                ->comment('Значение (скаляр или JSON)'),
            'updated_at' => $this->integer()->notNull()->defaultValue(0)
                ->comment('Время последней записи, Unix-timestamp'),
        ], $this->tableOptions);

        $this->addCommentOnTable(static::TABLE_NAME, 'Состояние поискового индекса');

        $this->createIndexes(static::TABLE_NAME, 'name', true);
    }

    /**
     * @throws NotSupportedException
     */
    public function safeDown(): void
    {
        parent::safeDown();
    }
}
