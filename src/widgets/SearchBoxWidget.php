<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\widgets;

use Besnovatyj\Search\Module;
use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * Строка поиска для шапки сайта.
 *
 * Обычная GET-форма без JavaScript: она работает при выключенных скриптах, её результат — обычный
 * адрес, которым можно поделиться, а поисковые подсказки при необходимости навешиваются темой
 * поверх готовой разметки.
 *
 * Виджет ничего не ищет и не знает про ядро — только ведёт на страницу выдачи. Если модуль поиска
 * в системе отключён, виджет молча не рисуется: тема не должна проверять это сама.
 *
 * Пример:
 * ```php
 * echo SearchBoxWidget::widget(['placeholder' => 'Поиск по сайту']);
 * ```
 */
class SearchBoxWidget extends Widget
{
    /** Подсказка в поле ввода. */
    public string $placeholder = 'Поиск по сайту';

    /** Подпись кнопки; пустая строка — кнопку не рисовать (отправка по Enter). */
    public string $buttonLabel = 'Найти';

    /** CSS-классы кнопки. */
    public string $buttonClass = 'btn btn-outline-secondary';

    /** Ограничить поиск одним разделом контента (ключ источника, напр. `blog.post`). */
    public ?string $type = null;

    /** HTML-атрибуты формы. */
    public array $options = [];

    public function run(): string
    {
        if (!Yii::$app->hasModule(Module::MODULE_ID)) {
            return '';
        }

        $options = array_merge(['class' => 'd-flex', 'role' => 'search'], $this->options);
        $options['method'] = 'get';
        $options['action'] = Url::to(['/Search/search/index']);

        $html = Html::beginTag('form', $options);
        $html .= Html::beginTag('div', ['class' => 'input-group']);

        $html .= Html::textInput('q', (string)Yii::$app->request->get('q', ''), [
            'class' => 'form-control',
            'placeholder' => $this->placeholder,
            'aria-label' => $this->placeholder,
            'maxlength' => 128,
        ]);

        if ($this->type !== null) {
            $html .= Html::hiddenInput('type', $this->type);
        }

        if ($this->buttonLabel !== '') {
            $html .= Html::submitButton(Html::encode($this->buttonLabel), ['class' => $this->buttonClass]);
        }

        $html .= Html::endTag('div');
        $html .= Html::endTag('form');

        return $html;
    }
}
