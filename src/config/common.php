<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Search\Module;
use Besnovatyj\Search\services\EngineRegistry;
use Besnovatyj\Search\services\EngineResolver;
use Besnovatyj\Search\services\SourceRegistry;
use Besnovatyj\Search\settings\SearchSettings;
use Besnovatyj\Search\settings\SearchSettingsFactory;
use yii\di\Container;

/**
 * Yii2-конфиг модуля для движка yiisoft/config (группа `common` — общий для всех приложений).
 *
 * Объявляется через `extra.config-plugin`, собирается modman в merge-plan и мёржится в рантайме.
 * Это единственный composition root пакета: регистрация модуля, URL-правило страницы поиска и
 * DI-проводка — всё здесь. Файла `config/container.php` у модуля намеренно нет: он выполняется
 * только при инициализации модуля, а поиск вызывают отовсюду (виджет в шапке темы, консоль,
 * страница состояния в админке), и проводка обязана существовать независимо от того, зашёл ли
 * запрос в модуль.
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
    'container' => [
        'singletons' => [
            /**
             * Настройки собираются один раз за запрос из `params` модуля.
             *
             * Замыкание — единственное место пакета, которое знает о `Yii::$app`: модуль настроек
             * `yii2-cms-config` применяет сохранённые значения прямо к объекту модуля, поэтому
             * читать их можно только после подъёма приложения. Ленивость это и обеспечивает —
             * объект собирается при первом обращении, а не при сборке конфига.
             */
            SearchSettings::class => static fn (Container $c): SearchSettings => $c
                ->get(SearchSettingsFactory::class)
                ->create((array)(Yii::$app->getModule(Module::MODULE_ID)?->params ?? [])),

            /**
             * Синглтоны — там, где важна общая на запрос память: реестры кэшируют найденные модули
             * и созданные ядра, резолвер — однократную проверку доступности. Сервисы без состояния
             * (SearchService, Indexer, QueryNormalizer, TextExtractor, IndexState) контейнер
             * собирает по тайп-хинтам сам, объявлять их незачем.
             */
            SearchSettingsFactory::class => SearchSettingsFactory::class,
            EngineRegistry::class => EngineRegistry::class,
            SourceRegistry::class => SourceRegistry::class,
            EngineResolver::class => EngineResolver::class,
        ],
    ],
];
