<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\services;

use Besnovatyj\Contracts\shortcode\ShortcodeTextResolver;
use Yii;

/**
 * Приведение контента модулей к чистому тексту для индексации.
 *
 * Единая политика на весь сайт: провайдеры отдают сырые поля как есть, а решение «что считается
 * текстом» принимается здесь. Иначе десять модулей вычистили бы HTML десятью разными способами,
 * а изменение политики потребовало бы правки десяти пакетов.
 *
 * Что делается и почему:
 *  - текстовые шорткоды (`%staticHost%`) разворачиваются через {@see ShortcodeTextResolver}, если
 *    модуль шорткодов установлен — иначе в индекс попал бы служебный токен;
 *  - виджетные шорткоды (`[gallery id=5]`) вырезаются целиком: это разметка вызова, а не текст,
 *    и по слову «gallery» никто искать не должен;
 *  - `<script>` и `<style>` удаляются вместе с содержимым — иначе в индекс попадёт JS-код;
 *  - блочные теги заменяются пробелом до `strip_tags()`, чтобы «конец абзаца<p>Начало» не
 *    склеилось в одно несуществующее слово.
 *
 * Работает в том числе в консоли, где нет ни запроса, ни сессии, поэтому ничего из веб-контекста
 * здесь не используется.
 */
final class TextExtractor
{
    /** Теги, содержимое которых текстом не является. */
    private const array DROP_TAGS = ['script', 'style', 'noscript', 'iframe', 'svg'];

    /**
     * Привести произвольный контент (HTML с шорткодами) к чистому тексту в одну строку.
     */
    public function toPlainText(?string $content): string
    {
        if ($content === null || trim($content) === '') {
            return '';
        }

        $text = $this->resolveShortcodes($content);
        $text = $this->dropWidgetShortcodes($text);
        $text = $this->dropNonTextTags($text);

        // Разделяем блоки пробелом, иначе соседние абзацы склеятся в несуществующее слово.
        $text = preg_replace('~<(br|/p|/div|/li|/h[1-6]|/td|/tr|/table|/blockquote)[^>]*>~iu', ' $0', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Неразрывные пробелы (U+00A0) — тоже пробелы: без замены слова слипаются в один токен.
        $text = str_replace(["\u{00A0}", "\u{200B}", "\u{FEFF}"], ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Собрать текст из нескольких полей сущности, отбросив пустые.
     *
     * @param array<int, string|null> $parts
     */
    public function join(array $parts, string $glue = ' '): string
    {
        $clean = [];
        foreach ($parts as $part) {
            $value = $this->toPlainText($part);
            if ($value !== '') {
                $clean[] = $value;
            }
        }

        return implode($glue, $clean);
    }

    /**
     * Развернуть текстовые шорткоды, если модуль шорткодов установлен.
     *
     * Компонент проверяется по контракту, а не по имени класса: без модуля шорткодов строка
     * остаётся как есть, и индексация продолжается.
     */
    private function resolveShortcodes(string $content): string
    {
        if (!str_contains($content, '%')) {
            return $content;
        }

        $shortcode = Yii::$app->has('shortcode') ? Yii::$app->get('shortcode', false) : null;

        if (!$shortcode instanceof ShortcodeTextResolver) {
            return $content;
        }

        try {
            return $shortcode->resolveText($content);
        } catch (\Throwable $e) {
            Yii::warning('Не удалось развернуть шорткоды при индексации: ' . $e->getMessage(), 'search/index');

            return $content;
        }
    }

    /**
     * Вырезать виджетные шорткоды `[name ...]` вместе с парным закрывающим тегом.
     */
    private function dropWidgetShortcodes(string $content): string
    {
        if (!str_contains($content, '[')) {
            return $content;
        }

        return preg_replace('~\[/?[a-z0-9_-]+[^\]]*\]~iu', ' ', $content) ?? $content;
    }

    /**
     * Удалить теги, содержимое которых не является текстом страницы.
     */
    private function dropNonTextTags(string $content): string
    {
        $pattern = '~<(' . implode('|', self::DROP_TAGS) . ')\b[^>]*>.*?</\1\s*>~isu';
        $content = preg_replace($pattern, ' ', $content) ?? $content;

        // Незакрытый script/style: обрезаем от открывающего тега до конца, чтобы код не попал в индекс.
        return preg_replace('~<(' . implode('|', self::DROP_TAGS) . ')\b[^>]*>.*$~isu', ' ', $content) ?? $content;
    }
}
