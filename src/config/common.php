<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Search\Module;

/**
 * Yii2-конфиг модуля для движка yiisoft/config (группа `common` — общий для всех приложений).
 *
 * Объявляется через `extra.config-plugin`, собирается modman в merge-plan и мёржится в рантайме.
 * Содержит регистрацию модуля и URL-правило страницы поиска. Меню (adminMenu) и миграции остаются
 * вкладами modman. Значения берутся из статических методов {@see Module} — единый источник.
 *
 * Правило `search` объявлено вкладом в именованный компонент `frontendUrlManager` (как у блога):
 * компонент определён и во фронте, и в бэкенде, поэтому группа `common`, а не `app-frontend` —
 * админка тоже должна уметь построить ссылку на страницу поиска. Правило встаёт перед catch-all
 * ядра и снимается вместе с модулем, если тот деактивирован в modman.
 */
return [
    'modules' => [
        Module::moduleId() => array_merge(
            ['class' => Module::class],
            Module::moduleConfig(),
            ['version' => Module::moduleVersion()],
        ),
    ],
    'components' => [
        'frontendUrlManager' => [
            'rules' => [
                'search' => 'Search/search/index',
            ],
        ],
    ],
];
