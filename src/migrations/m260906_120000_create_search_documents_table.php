<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\migrations;

use Besnovatyj\Kernel\migration\BaseMigration;
use yii\base\NotSupportedException;

/**
 * Каталог документов сквозного поиска.
 *
 * Единое денормализованное представление контента всех модулей: то, что показывается в выдаче
 * (заголовок, ссылка, анонс, картинка, дата) и то, по чему ищет ядро (очищенный текст, ключевые
 * слова). Таблица принадлежит фасаду и одинакова для любого ядра — благодаря этому переключение
 * движка не трогает ни контент-модули, ни вёрстку выдачи.
 *
 * Ссылка хранится разобранной на `route` + `params_json`, а не готовой строкой: короткий URL
 * сущности администратор может изменить в модуле алиасов, и сохранённый URL молча протух бы.
 *
 * Содержимое полностью восстановимо переиндексацией, поэтому таблицу можно исключить из дампа
 * базы — как и служебные таблицы самого ядра.
 */
class m260906_120000_create_search_documents_table extends BaseMigration
{
    public const string TABLE_NAME = '{{%search_documents}}';

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
            'id'           => $this->primaryKey(),
            'type'         => $this->string(64)->notNull()
                ->comment('Ключ источника: <модуль>.<сущность>, напр. blog.post'),
            'entity_id'    => $this->string(64)->notNull()
                ->comment('Первичный ключ сущности в таблице её модуля'),
            'route'        => $this->string(255)->notNull()
                ->comment('Внутренний роут фронтенда, напр. /Blog/post/view'),
            'params_json'  => $this->text()->null()->defaultValue(null)
                ->comment('JSON параметров роута, напр. {"id":42}'),
            'title'        => $this->string(500)->notNull()
                ->comment('Заголовок документа (выводится ссылкой в выдаче)'),
            'excerpt'      => $this->text()->null()->defaultValue(null)
                ->comment('Анонс для карточки выдачи; NULL — собирается из текста'),
            // MEDIUMTEXT, а не TEXT: в utf8mb4 TEXT — это 65 535 БАЙТ, то есть примерно 32 тыс.
            // кириллических символов, и длинная статья с описанием обрезалась бы молча.
            'content'      => $this->getDb()->driverName === 'mysql'
                ? 'MEDIUMTEXT NOT NULL'
                : $this->text()->notNull(),
            'keywords'     => $this->text()->null()->defaultValue(null)
                ->comment('Дополнительные слова для индекса, не показываемые в выдаче'),
            'image'        => $this->string(500)->null()->defaultValue(null)
                ->comment('Превью для карточки выдачи'),
            'published_at' => $this->integer()->null()->defaultValue(null)
                ->comment('Дата публикации, Unix-timestamp — сортировка по свежести'),
            'boost'        => $this->float()->notNull()->defaultValue(1)
                ->comment('Итоговый множитель важности документа'),
            'indexed_at'   => $this->integer()->notNull()->defaultValue(0)
                ->comment('Метка прогона переиндексации: строки со старой меткой удаляются'),
        ], $this->tableOptions);

        $this->addCommentOnColumn(
            static::TABLE_NAME,
            'content',
            'Текст для поиска: HTML вычищен, шорткоды раскрыты',
        );

        $this->addCommentOnTable(static::TABLE_NAME, 'Каталог документов сквозного поиска');

        // Адрес документа: пара «источник + сущность» уникальна, по ней идёт upsert при переиндексации,
        // поэтому id строки остаётся стабильным и ядро можно пересобирать независимо.
        $this->createIndexes(static::TABLE_NAME, ['type', 'entity_id'], false, true);
        $this->createIndexes(static::TABLE_NAME, 'type', false, false);
        $this->createIndexes(static::TABLE_NAME, 'indexed_at', false, false);
        $this->createIndexes(static::TABLE_NAME, 'published_at', false, false);
    }

    /**
     * Удаление таблицы (вместе с индексами и внешними ключами) выполняет {@see BaseMigration::safeDown()}
     * по `static::TABLE_NAME`.
     *
     * @throws NotSupportedException
     */
    public function safeDown(): void
    {
        parent::safeDown();
    }
}
