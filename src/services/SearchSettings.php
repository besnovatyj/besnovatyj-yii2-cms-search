<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\services;

use Besnovatyj\Search\Module;
use Yii;

/**
 * Типизированное чтение настроек модуля поиска.
 *
 * Модуль настроек `yii2-cms-config` умеет писать только в `modules.<Id>.params.*` и только
 * скаляры, поэтому списки хранятся строками («blog.post, shop.product», «врач = доктор, терапевт»).
 * Весь разбор этих строк собран здесь, чтобы формат парсился в одном месте, а остальной код
 * работал с массивами и числами.
 *
 * Класс намеренно не кэширует ничего между запросами: значения уже лежат в собранном конфиге
 * приложения, читать их повторно ничего не стоит.
 */
final class SearchSettings
{
    /**
     * @param array<string, mixed> $params `params` модуля поиска
     */
    public function __construct(private readonly array $params)
    {
    }

    /**
     * Настройки активного модуля поиска. Если модуль не подключён — дефолты пакета.
     */
    public static function current(): self
    {
        $module = Yii::$app->hasModule(Module::MODULE_ID) ? Yii::$app->getModule(Module::MODULE_ID) : null;

        return new self($module instanceof Module ? $module->params : []);
    }

    /** Ключ активного ядра поиска (`tnt`, `manticore`, ...). */
    public function engine(): string
    {
        return (string)($this->params['engine'] ?? '');
    }

    /**
     * Карта «ключ ядра => FQCN реализации {@see \Besnovatyj\Search\contracts\SearchEngineInterface}».
     *
     * @return array<string, string>
     */
    public function adapters(): array
    {
        $adapters = $this->params['adapters'] ?? [];

        return is_array($adapters) ? $adapters : [];
    }

    /**
     * Ключ запасного ядра, на которое фасад переключается, если активное недоступно.
     * Пустая строка — деградировать в пустую выдачу.
     */
    public function fallbackEngine(): string
    {
        return (string)($this->params['fallbackEngine'] ?? '');
    }

    /**
     * Отключённые администратором источники — их документы не индексируются и не ищутся.
     *
     * @return list<string>
     */
    public function disabledSources(): array
    {
        return $this->splitList((string)($this->params['disabledSources'] ?? ''));
    }

    /**
     * Переопределённые веса источников: `blog.post: 2, shop.product: 0.5`.
     *
     * @return array<string, float>
     */
    public function boosts(): array
    {
        $boosts = [];

        foreach ($this->splitList((string)($this->params['boosts'] ?? '')) as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $type = trim($parts[0]);
            $value = (float)str_replace(',', '.', trim($parts[1]));

            if ($type !== '' && $value > 0) {
                $boosts[$type] = $value;
            }
        }

        return $boosts;
    }

    /**
     * Словарь синонимов: одна группа в строке, слова через запятую.
     * Любое слово группы подставляет в запрос все остальные.
     *
     * @return list<list<string>>
     */
    public function synonymGroups(): array
    {
        $raw = (string)($this->params['synonyms'] ?? '');
        $groups = [];

        foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
            $words = array_values(array_filter(array_map(
                static fn (string $word): string => mb_strtolower(trim($word)),
                explode(',', str_replace('=', ',', $line)),
            ), static fn (string $word): bool => $word !== ''));

            if (count($words) > 1) {
                $groups[] = $words;
            }
        }

        return $groups;
    }

    /** Минимальная длина запроса — короче не ищем (защита от «а» и от ботов). */
    public function minQueryLength(): int
    {
        return max(1, (int)($this->params['minQueryLength'] ?? 3));
    }

    /** Предельная длина запроса — всё лишнее отбрасывается до передачи в ядро. */
    public function maxQueryLength(): int
    {
        return max(16, (int)($this->params['maxQueryLength'] ?? 128));
    }

    /** Размер страницы выдачи. */
    public function perPage(): int
    {
        return max(1, (int)($this->params['perPage'] ?? 20));
    }

    /** Искать с учётом опечаток, если активное ядро это умеет. */
    public function fuzzy(): bool
    {
        return (bool)($this->params['fuzzy'] ?? true);
    }

    /** Длина текстового фрагмента в карточке выдачи. */
    public function snippetLength(): int
    {
        return max(80, (int)($this->params['snippetLength'] ?? 240));
    }

    /**
     * Разбор списка, записанного через запятую и/или переводы строк.
     *
     * @return list<string>
     */
    private function splitList(string $raw): array
    {
        $parts = preg_split('/[,\R]+/u', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $v): bool => $v !== ''));
    }
}
