<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\forms\frontend;

use Besnovatyj\Forms\BaseForm;

/**
 * Форма строки поиска на фронте.
 *
 * Имя формы пустое, чтобы адрес выдачи оставался читаемым и делился ссылкой: `/search?q=...&type=...`.
 * Валидация здесь минимальная — она защищает от мусора в типах и номере страницы, а очистку самого
 * текста запроса выполняет {@see \Besnovatyj\Search\services\QueryNormalizer}: она общая для всех
 * входов в поиск, включая виджет строки поиска в шапке.
 */
class SearchForm extends BaseForm
{
    /** Текст запроса. */
    public string $q = '';

    /** Фильтр по одному разделу контента (ключ источника); пусто — искать везде. */
    public string $type = '';

    /** Номер страницы выдачи, с 1. */
    public int $page = 1;

    public function rules(): array
    {
        return [
            [['q', 'type'], 'string', 'max' => 128],
            [['q', 'type'], 'trim'],
            ['page', 'integer', 'min' => 1],
            ['page', 'default', 'value' => 1],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'q'    => 'Поиск по сайту',
            'type' => 'Раздел',
        ];
    }

    /**
     * Форма без имени: параметры уходят в адрес как есть.
     */
    public function formName(): string
    {
        return '';
    }

    /**
     * Фильтр по типам в виде, который принимает сервис поиска.
     *
     * @return list<string>
     */
    public function types(): array
    {
        return $this->type === '' ? [] : [$this->type];
    }
}
