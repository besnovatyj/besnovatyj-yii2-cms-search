<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\settings;

use Besnovatyj\Contracts\config\OptionItemsProvider;
use Besnovatyj\Search\services\EngineRegistry;

/**
 * Тот же список ядер, что и у активного ядра, плюс вариант «не подстраховывать».
 *
 * Отдельный класс, а не флаг у {@see EngineOptionItems}: поставщик вариантов получает контейнер,
 * а не аргументы опции, и «список ядер» с «списком ядер и пустого значения» — это два разных
 * списка, а не один с параметром.
 */
final class FallbackEngineOptionItems implements OptionItemsProvider
{
    /** Значение «запасного ядра нет»: при отказе выдача останется пустой. */
    private const string NONE = '';

    public function __construct(private readonly EngineRegistry $engines)
    {
    }

    /**
     * @return array<string, string>
     */
    public function items(): array
    {
        $items = [self::NONE => 'Нет'];

        foreach ($this->engines->descriptors() as $descriptor) {
            $items[$descriptor->key] = $descriptor->label;
        }

        return $items;
    }
}
