<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\settings;

use Besnovatyj\Contracts\config\OptionItemsProvider;
use Besnovatyj\Search\services\EngineRegistry;

/**
 * Список ядер для выпадающего поля «Активное ядро поиска» в настройках.
 *
 * Варианты берутся из реестра установленных ядер, а не переписываются в `options.php`: список
 * ядер — это состав системы, и держать его копию значило бы обновлять её при каждом новом пакете
 * и показывать администратору движки, которых нет. Заодно этот же список служит правилом
 * валидации — см. {@see OptionItemsProvider}.
 */
final class EngineOptionItems implements OptionItemsProvider
{
    public function __construct(private readonly EngineRegistry $engines)
    {
    }

    /**
     * @return array<string, string>
     */
    public function items(): array
    {
        $items = [];

        foreach ($this->engines->descriptors() as $descriptor) {
            $items[$descriptor->key] = $descriptor->label;
        }

        return $items;
    }
}
