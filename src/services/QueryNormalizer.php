<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\services;

/**
 * Подготовка пользовательского запроса перед передачей в ядро.
 *
 * Две задачи, обе — общие для всех движков, поэтому решаются в фасаде, а не в ядрах.
 *
 * Первая — обезвредить ввод. Синтаксис запросов у движков свой (`+`, `-`, `*`, кавычки,
 * `@поле`), и пришедшая из адресной строки строка не должна в него попадать: пользователь не
 * обязан знать язык запросов, а бот, подбирающий спецсимволы, не должен ронять поиск. Поэтому
 * от запроса остаются только буквы, цифры и дефисы внутри слов.
 *
 * Вторая — расширить запрос синонимами из настроек. Это дешёвая замена «понимающему» поиску:
 * словарь настраивается в админке, работает одинаково на любом ядре и не требует ни модели,
 * ни внешнего API.
 */
final class QueryNormalizer
{
    public function __construct(private readonly SearchSettings $settings)
    {
    }

    /**
     * Привести сырую строку из запроса к безопасному виду.
     *
     * Возвращает пустую строку, если после очистки не осталось ничего осмысленного.
     */
    public function normalize(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        $text = mb_substr(trim($raw), 0, $this->settings->maxQueryLength());

        // Оставляем буквы, цифры, пробелы и дефис — остальное могло бы попасть в синтаксис движка.
        $text = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $text) ?? '';

        // Дефис допустим только внутри слова («интернет-магазин»), но не как оператор исключения.
        $text = preg_replace('/(?<![\p{L}\p{N}])-+|-+(?![\p{L}\p{N}])/u', ' ', $text) ?? $text;

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Достаточно ли запрос содержателен, чтобы вообще идти в индекс.
     *
     * Односимвольные запросы дают бессмысленную выдачу и лишнюю нагрузку — их отбрасываем
     * до обращения к ядру.
     */
    public function isSearchable(string $normalized): bool
    {
        return mb_strlen($normalized) >= $this->settings->minQueryLength();
    }

    /**
     * Дополнить запрос синонимами из настроек.
     *
     * Синонимы добавляются в конец строки, а не заменяют исходные слова: собственные слова
     * запроса остаются самыми частыми, поэтому точные совпадения по-прежнему ранжируются выше
     * найденных по синониму.
     */
    public function expand(string $normalized): string
    {
        $groups = $this->settings->synonymGroups();

        if ($groups === [] || $normalized === '') {
            return $normalized;
        }

        $words = preg_split('/\s+/u', mb_strtolower($normalized)) ?: [];
        $present = array_flip($words);
        $additions = [];

        foreach ($groups as $group) {
            $matched = false;
            foreach ($group as $word) {
                if (isset($present[$word])) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                continue;
            }

            foreach ($group as $word) {
                if (!isset($present[$word]) && !isset($additions[$word])) {
                    $additions[$word] = true;
                }
            }
        }

        return $additions === []
            ? $normalized
            : $normalized . ' ' . implode(' ', array_keys($additions));
    }
}
