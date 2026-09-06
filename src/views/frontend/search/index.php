<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Search\forms\frontend\SearchForm;
use Besnovatyj\Search\services\SearchResultItem;
use Besnovatyj\Search\services\SearchResultPage;
use Besnovatyj\Search\services\SearchStatus;
use yii\bootstrap5\LinkPager;
use yii\data\Pagination;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;

/**
 * Базовая вью страницы сквозного поиска (пакетный фолбэк; тема может переопределить).
 *
 * Ничего не досчитывает: сервис отдал готовые карточки, вкладки и статус. Единственное решение,
 * принимаемое здесь, — экранировать ли фрагмент: подсветку от ядра выводим как HTML (ядро уже
 * экранировало текст и добавило только `<mark>`), обычный анонс экранируем сами.
 *
 * @var View             $this
 * @var SearchResultPage $result
 * @var SearchForm       $form
 * @var Pagination       $pagination
 */

$queryParams = static function (array $override) use ($result): array {
    return array_merge(['/Search/search/index', 'q' => $result->query], $override);
};
?>
<div class="container py-4">
    <h1 class="h3 mb-3"><?= Html::encode($this->title) ?></h1>

    <form method="get" action="<?= Url::to(['/Search/search/index']) ?>" class="mb-4" role="search">
        <div class="input-group">
            <?= Html::textInput('q', $result->query, [
                'class' => 'form-control',
                'placeholder' => 'Что ищем?',
                'maxlength' => 128,
                'aria-label' => 'Поиск по сайту',
                'autofocus' => $result->query === '',
            ]) ?>
            <?php if ($result->types !== []): ?>
                <?= Html::hiddenInput('type', $result->types[0]) ?>
            <?php endif; ?>
            <?= Html::submitButton('Найти', ['class' => 'btn btn-primary']) ?>
        </div>
    </form>

    <?php if ($result->suggestion !== null): ?>
        <p class="mb-3">
            Возможно, вы имели в виду:
            <?= Html::a(Html::encode($result->suggestion), $queryParams(['q' => $result->suggestion])) ?>
        </p>
    <?php endif; ?>

    <?php if ($result->facets !== []): ?>
        <ul class="nav nav-pills mb-4">
            <li class="nav-item">
                <?= Html::a(
                    'Везде',
                    $queryParams(['type' => null]),
                    ['class' => 'nav-link' . ($result->types === [] ? ' active' : '')],
                ) ?>
            </li>
            <?php foreach ($result->facets as $facet): ?>
                <li class="nav-item">
                    <?= Html::a(
                        Html::encode($facet['label']) . ' <span class="badge text-bg-secondary">' . $facet['count'] . '</span>',
                        $queryParams(['type' => $facet['type']]),
                        ['class' => 'nav-link' . ($facet['active'] ? ' active' : '')],
                    ) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($result->status === SearchStatus::EngineUnavailable): ?>
        <div class="alert alert-warning">Поиск временно недоступен. Попробуйте позже.</div>
    <?php elseif ($result->status === SearchStatus::IndexEmpty): ?>
        <div class="alert alert-warning">Поисковый индекс ещё не собран.</div>
    <?php elseif ($result->status === SearchStatus::TooShort): ?>
        <div class="alert alert-info">Слишком короткий запрос — введите хотя бы несколько букв.</div>
    <?php elseif ($result->status === SearchStatus::NoQuery): ?>
        <p class="text-muted">Введите слово или фразу, чтобы найти материалы на сайте.</p>
    <?php elseif ($result->isEmpty()): ?>
        <div class="alert alert-light border">
            По запросу «<?= Html::encode($result->query) ?>» ничего не найдено.
            Попробуйте изменить формулировку или использовать одно слово.
        </div>
    <?php else: ?>
        <p class="text-muted mb-3">Найдено: <?= $result->total ?></p>

        <div class="list-group list-group-flush mb-4">
            <?php foreach ($result->items as $item): ?>
                <?php /** @var SearchResultItem $item */ ?>
                <article class="list-group-item px-0 py-3">
                    <div class="d-flex gap-3">
                        <?php if ($item->image !== null): ?>
                            <div class="flex-shrink-0 d-none d-sm-block">
                                <?= Html::a(
                                    Html::img($item->image, [
                                        'alt' => $item->title,
                                        'class' => 'rounded',
                                        'style' => 'width:96px;height:96px;object-fit:cover;',
                                        'loading' => 'lazy',
                                    ]),
                                    $item->url,
                                ) ?>
                            </div>
                        <?php endif; ?>

                        <div class="flex-grow-1">
                            <h2 class="h6 mb-1">
                                <?= Html::a(Html::encode($item->title), $item->url) ?>
                            </h2>

                            <div class="small text-muted mb-1">
                                <?= Html::encode($item->typeLabel) ?>
                                <?php if ($item->date !== null): ?>
                                    · <?= Yii::$app->formatter->asDate($item->date) ?>
                                <?php endif; ?>
                            </div>

                            <p class="mb-0">
                                <?= $item->snippetIsHtml ? $item->snippet : Html::encode($item->snippet) ?>
                            </p>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?= LinkPager::widget(['pagination' => $pagination]) ?>
    <?php endif; ?>
</div>
