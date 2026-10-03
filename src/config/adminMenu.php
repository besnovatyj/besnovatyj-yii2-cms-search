<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Contracts\adminMenu\AdminMenuLocation;
use Besnovatyj\Contracts\adminMenu\AdminMenuPlacement;

return [
    // Состояние поискового индекса и его пересборка
    [
        'label'     => 'Поисковый индекс',
        'iconClass' => 'bi bi-search me-1',
        'url'       => ['/Search/backend/index/index'],
        'active'    => static function () {
            return str_contains(\Yii::$app->request->url, 'Search/backend/index');
        },
        '_meta' => [
            'placements' => [
                new AdminMenuPlacement(
                    location: AdminMenuLocation::RightSidebar,
                    group: 'Search',
                    groupIcon: 'bi bi-search',
                    groupPriority: 700,
                    priority: 100,
                ),
            ],
        ],
    ],
];
