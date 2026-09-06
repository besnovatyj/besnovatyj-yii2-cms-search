<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Search\services\EngineResolver;
use Besnovatyj\Search\services\SearchSettings;
use Besnovatyj\Search\services\SourceRegistry;

/**
 * Конфигурация DI-контейнера для модуля поиска (способ A: только для самого модуля).
 *
 * Явно определяется единственная зависимость, которую контейнер не может собрать сам, —
 * {@see SearchSettings}: её конструктор принимает массив `params`, а не класс. Всё остальное
 * (реестр источников, резолвер ядра, индексатор, сервис поиска) резолвится по тайп-хинтам
 * конструкторов автоматически.
 *
 * Синглтоны у реестра и резолвера не ради экономии, а ради консистентности в пределах запроса:
 * реестр кэширует найденные модули-провайдеры, резолвер — однократную проверку доступности ядра,
 * и обе кэш-памяти должны быть общими для всех потребителей.
 */
return function (\yii\di\Container $container): void {
    $container->setSingleton(SearchSettings::class, static fn (): SearchSettings => SearchSettings::current());
    $container->setSingleton(SourceRegistry::class);
    $container->setSingleton(EngineResolver::class);
};
