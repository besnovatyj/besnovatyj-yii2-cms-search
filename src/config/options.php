<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Search\settings\EngineOptionItems;
use Besnovatyj\Search\settings\FallbackEngineOptionItems;

/**
 * Опции модуля настроек `yii2-cms-config` для фасада поиска.
 *
 * Пути указывают в `modules.Search.params.*` — оттуда их читает
 * {@see \Besnovatyj\Search\settings\SearchSettingsFactory}.
 *
 * Список ядер не перечисляется: он равен составу установленных модулей-ядер, поэтому собирается
 * поставщиком вариантов ({@see \Besnovatyj\Contracts\config\OptionItemsProvider}) в момент показа
 * формы. Сам файл при этом остаётся статичным — менеджер модулей считает от него контрольную сумму
 * манифеста, и «плавающий» список помечал бы фасад изменившимся при каждой установке ядра.
 * Правило «значение из списка» модуль настроек добавляет к таким опциям сам.
 *
 * Смена ядра или правка списка синонимов/весов требуют пересборки индекса: стеммер и веса
 * «запекаются» в индекс при индексации. Модуль сообщает об этом на своей странице состояния,
 * поэтому опции намеренно не пытаются перестраивать индекс сами — переиндексация на боевом
 * сайте должна оставаться осознанным действием администратора.
 */
return [
    'search_engine' => [
        'path'        => 'modules.Search.params.engine',
        'label'       => '[Search] Активное ядро поиска',
        'description' => 'После смены ядра индекс нужно собрать заново',
        'category'    => 'Search',
        'rules'       => [
            ['required'],
        ],
        'inputOptions' => [
            'type'          => 'dropdown',
            'itemsProvider' => EngineOptionItems::class,
        ],
    ],

    'search_fallback_engine' => [
        'path'        => 'modules.Search.params.fallbackEngine',
        'label'       => '[Search] Запасное ядро',
        'description' => 'Используется, если активное ядро не отвечает; «Нет» — выдача остаётся пустой',
        'category'    => 'Search',
        'inputOptions' => [
            'type'          => 'dropdown',
            'itemsProvider' => FallbackEngineOptionItems::class,
        ],
    ],

    'search_disabled_sources' => [
        'path'        => 'modules.Search.params.disabledSources',
        'label'       => '[Search] Исключённые из поиска разделы',
        'description' => 'Ключи источников через запятую, напр.: blog.post, shop.product',
        'category'    => 'Search',
        'rules'       => [
            ['string', 'max' => 1000],
        ],
        'inputOptions' => [
            'type' => 'text',
        ],
    ],

    'search_boosts' => [
        'path'        => 'modules.Search.params.boosts',
        'label'       => '[Search] Веса разделов',
        'description' => 'Ключ: множитель через запятую, напр.: blog.post: 2, page.page: 1.5',
        'category'    => 'Search',
        'rules'       => [
            ['string', 'max' => 1000],
        ],
        'inputOptions' => [
            'type' => 'text',
        ],
    ],

    'search_synonyms' => [
        'path'        => 'modules.Search.params.synonyms',
        'label'       => '[Search] Синонимы',
        'description' => 'Одна группа в строке, слова через запятую: врач, доктор, терапевт',
        'category'    => 'Search',
        'rules'       => [
            ['string', 'max' => 20000],
        ],
        'inputOptions' => [
            'type' => 'textarea',
        ],
    ],

    'search_min_query_length' => [
        'path'        => 'modules.Search.params.minQueryLength',
        'label'       => '[Search] Минимальная длина запроса',
        'description' => 'Более короткие запросы не выполняются',
        'category'    => 'Search',
        'rules'       => [
            ['required'],
            ['integer', 'min' => 1, 'max' => 10],
        ],
        'inputOptions' => [
            'type' => 'number',
        ],
    ],

    'search_max_query_length' => [
        'path'        => 'modules.Search.params.maxQueryLength',
        'label'       => '[Search] Предельная длина запроса',
        'description' => 'Всё, что длиннее, отбрасывается до обращения к ядру',
        'category'    => 'Search',
        'rules'       => [
            ['required'],
            ['integer', 'min' => 16, 'max' => 1000],
        ],
        'inputOptions' => [
            'type' => 'number',
        ],
    ],

    'search_per_page' => [
        'path'        => 'modules.Search.params.perPage',
        'label'       => '[Search] Результатов на странице',
        'category'    => 'Search',
        'rules'       => [
            ['required'],
            ['integer', 'min' => 5, 'max' => 100],
        ],
        'inputOptions' => [
            'type' => 'number',
        ],
    ],

    'search_fuzzy' => [
        'path'        => 'modules.Search.params.fuzzy',
        'label'       => '[Search] Поиск с опечатками',
        'description' => 'Применяется, только если активное ядро это умеет',
        'category'    => 'Search',
        'rules'       => [
            ['boolean'],
        ],
        'inputOptions' => [
            'type' => 'checkbox',
        ],
    ],

    'search_snippet_length' => [
        'path'        => 'modules.Search.params.snippetLength',
        'label'       => '[Search] Длина фрагмента в выдаче',
        'category'    => 'Search',
        'rules'       => [
            ['required'],
            ['integer', 'min' => 80, 'max' => 600],
        ],
        'inputOptions' => [
            'type' => 'number',
        ],
    ],
];
