<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\settings;

/**
 * Чтение `params` модуля с приведением к нужному типу.
 *
 * Модуль настроек `yii2-cms-config` умеет писать в `modules.<Id>.params.*` только скаляры, поэтому
 * всё, что по смыслу является списком или картой, хранится строкой: «blog.post, shop.product»,
 * «blog.post: 2, page.page: 1.5», словарь синонимов по строке на группу. Разбор этих строк собран
 * здесь, в одном месте, и переиспользуется ядрами поиска — иначе каждый пакет разбирал бы их
 * по-своему.
 *
 * Класс намеренно ничего не знает ни о Yii, ни о конкретных ключах: он получает готовый массив
 * параметров и отдаёт типизированные значения. Кто и откуда взял этот массив — забота
 * composition root (см. `config/common.php` пакетов).
 */
final readonly class ParamReader
{
    /**
     * @param array<string, mixed> $params `params` модуля
     */
    public function __construct(private array $params)
    {
    }

    /** Строковое значение с обрезанными пробелами по краям. */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->params[$key] ?? null;

        return is_scalar($value) ? trim((string)$value) : $default;
    }

    /**
     * Целое значение, зажатое в допустимый диапазон.
     *
     * Границы применяются молча и намеренно: значение приходит из админки, и «perPage = 0»
     * должен превращаться в разумный минимум, а не ронять выдачу делением на ноль.
     */
    public function int(string $key, int $default, ?int $min = null, ?int $max = null): int
    {
        $value = $this->params[$key] ?? null;
        $result = is_scalar($value) && $value !== '' ? (int)$value : $default;

        if ($min !== null) {
            $result = max($min, $result);
        }

        if ($max !== null) {
            $result = min($max, $result);
        }

        return $result;
    }

    /**
     * Логическое значение.
     *
     * Галочка из админки приходит строкой «1»/«0», поэтому приведение идёт через int:
     * `(bool)'0'` в PHP равно false, но полагаться на это неявно не стоит.
     */
    public function bool(string $key, bool $default): bool
    {
        $value = $this->params[$key] ?? null;

        if ($value === null || $value === '' || !is_scalar($value)) {
            return $default;
        }

        return (bool)(int)$value;
    }

    /**
     * Список, записанный через запятую и/или переводы строк.
     *
     * @return list<string>
     */
    public function list(string $key): array
    {
        // Именно \r\n перечислением: escape-последовательность \R (любой перевод строки) внутри
        // символьного класса PCRE недопустима и роняет компиляцию шаблона.
        $parts = preg_split('/[,\r\n]+/u', $this->string($key)) ?: [];

        return array_values(array_filter(
            array_map('trim', $parts),
            static fn (string $value): bool => $value !== '',
        ));
    }

    /**
     * Карта «ключ: число», записанная одной строкой: `blog.post: 2, page.page: 1.5`.
     *
     * Запятая как десятичный разделитель принимается наравне с точкой — набранное вручную
     * «1,5» не должно молча превращаться в единицу.
     *
     * @return array<string, float>
     */
    public function floatMap(string $key): array
    {
        $map = [];

        foreach ($this->list($key) as $pair) {
            $parts = explode(':', $pair, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $name = trim($parts[0]);
            $value = (float)str_replace(',', '.', trim($parts[1]));

            if ($name !== '' && $value > 0) {
                $map[$name] = $value;
            }
        }

        return $map;
    }

    /**
     * Группы слов: одна группа в строке, слова внутри — через запятую.
     *
     * Знак «=» принимается как синоним запятой: словари синонимов удобно писать в виде
     * «врач = доктор, терапевт». Группы из одного слова отбрасываются — они ничего не задают.
     *
     * @return list<list<string>>
     */
    public function groups(string $key): array
    {
        $groups = [];

        foreach (preg_split('/\R/u', $this->string($key)) ?: [] as $line) {
            $words = array_values(array_filter(
                array_map(
                    static fn (string $word): string => mb_strtolower(trim($word)),
                    explode(',', str_replace('=', ',', $line)),
                ),
                static fn (string $word): bool => $word !== '',
            ));

            if (count($words) > 1) {
                $groups[] = $words;
            }
        }

        return $groups;
    }
}
