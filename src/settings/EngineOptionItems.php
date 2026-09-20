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
 *
 * Первым идёт пустой вариант, повторяющий дефолт `params.engine` из `config/config.php`.
 * Без него в `<select>` нет варианта, равного текущему значению, браузер выделяет первый
 * попавшийся движок и отправляет его при любом сохранении формы настроек — администратор
 * получает выбранное за него ядро с пометкой «изменено», ничего не выбирав.
 */
final class EngineOptionItems implements OptionItemsProvider
{
    /** Значение «ядро не выбрано»: поиск не выполняется, выдача остаётся пустой. */
    private const string NONE = '';

    public function __construct(private readonly EngineRegistry $engines)
    {
    }

    /**
     * @return array<string, string>
     */
    public function items(): array
    {
        $items = [self::NONE => 'Не выбрано'];

        foreach ($this->engines->descriptors() as $descriptor) {
            $items[$descriptor->key] = $descriptor->label;
        }

        return $items;
    }
}
