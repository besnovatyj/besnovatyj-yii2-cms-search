<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search;

use Besnovatyj\Contracts\dashboard\DashboardWidgetDescriptor;
use Besnovatyj\Contracts\dashboard\ProvidesDashboardWidgets;
use Besnovatyj\Contracts\module\DeclaresModule;
use Besnovatyj\Contracts\module\ProvidesAdminMenu;
use Besnovatyj\Contracts\module\ProvidesDependencies;
use Besnovatyj\Contracts\module\ProvidesMigrations;
use Besnovatyj\Contracts\module\ProvidesOptions;
use Besnovatyj\Kernel\module\CmsModule;
use Besnovatyj\Search\widgets\dashboard\SearchIndexTile;

/**
 * Модуль-фасад сквозного поиска по сайту.
 *
 * Сам искать не умеет: держит каталог документов, собирает контент у модулей через контракт
 * {@see \Besnovatyj\Contracts\search\SearchableProvider} и рисует выдачу, а работу со словами
 * делегирует ядру — отдельному модулю, который объявляет себя через
 * {@see contracts\SearchEngineProvider} и реализует {@see contracts\SearchEngineInterface}.
 *
 * Модулем оформлен намеренно, по образцу фасада редактора: так выбор ядра становится параметром
 * `params.engine`, которым управляет модуль настроек `yii2-cms-config` (он умеет писать только в
 * `modules.<Id>.params.*`), а страница состояния индекса и пункт меню появляются в админке штатным
 * образом.
 *
 * Контентные модули о поиске не знают: они реализуют нейтральный контракт провайдера и не зависят
 * ни от этого пакета, ни от движка. Ядра, наоборот, зависят от фасада — но он о них не знает
 * ничего, кроме того, что они сами о себе объявили.
 */
class Module extends CmsModule implements
    DeclaresModule,
    ProvidesAdminMenu,
    ProvidesDashboardWidgets,
    ProvidesDependencies,
    ProvidesMigrations,
    ProvidesOptions
{
    public const bool EDITABLE = true;
    public const string VERSION = '1.0.0';
    public const string MODULE_ID = 'Search';

    public static function moduleId(): string { return self::MODULE_ID; }
    public static function moduleVersion(): string { return self::VERSION; }
    public static function isEditable(): bool { return self::EDITABLE; }
    public static function adminMenu(): array { return require __DIR__ . '/config/adminMenu.php'; }
    public static function moduleConfig(): array { return require __DIR__ . '/config/config.php'; }
    public static function options(): array { return require __DIR__ . '/config/options.php'; }
    public static function dependencies(): array { return require __DIR__ . '/config/dependencies.php'; }
    public static function migrationPath(): string { return __DIR__ . '/migrations'; }
    public static function migrationNamespace(): ?string { return __NAMESPACE__ . '\\migrations'; }

    /**
     * Плитка дашборда: состояние индекса (когда собран, каким ядром, сколько документов) и кнопка
     * пересборки. Приоритет соседний с картой сайта — обе плитки отвечают на вопрос «видит ли
     * сайт свой контент», и рядом читаются как одна пара.
     *
     * @return DashboardWidgetDescriptor[]
     */
    public static function dashboardWidgets(): array
    {
        return [
            new DashboardWidgetDescriptor(
                id: self::MODULE_ID . '.index',
                title: 'Поисковый индекс',
                tileClass: SearchIndexTile::class,
                iconClass: 'bi bi-search',
                priority: 410,
            ),
        ];
    }
}
