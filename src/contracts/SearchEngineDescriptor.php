<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\contracts;

/**
 * Паспорт ядра поиска: под каким ключом оно живёт, как называется для администратора и каким
 * классом реализовано.
 *
 * Объявляется модулем самого ядра через {@see SearchEngineProvider} — фасад ни одного движка
 * поимённо не знает и знать не должен. Отсюда же берётся список вариантов в настройках: он равен
 * составу установленных и включённых ядер, а не переписанной от руки копии этого состава.
 */
final readonly class SearchEngineDescriptor
{
    /**
     * @param string $key         Стабильный ключ ядра (`tnt`, `manticore`). ПОПАДАЕТ В НАСТРОЙКИ и
     *                            в состояние индекса (им помечается, каким ядром индекс собран),
     *                            поэтому после запуска не меняется.
     * @param string $label       Подпись для администратора — она же пункт выпадающего списка
     *                            в настройках («TNTSearch (в базе проекта)»).
     * @param string $engineClass Класс, реализующий {@see SearchEngineInterface}. Создаётся
     *                            контейнером, поэтому зависимости ядра внедряются обычным образом.
     *
     * @phpstan-param class-string<SearchEngineInterface> $engineClass
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $engineClass,
    ) {
    }
}
