<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

use Besnovatyj\Contracts\search\SearchSource;
use Besnovatyj\Search\contracts\EngineCapabilities;
use Besnovatyj\Search\contracts\SearchEngineInterface;
use Besnovatyj\Search\services\IndexState;
use yii\helpers\Html;
use yii\web\View;

/**
 * Состояние поискового индекса.
 *
 * @var View                          $this
 * @var string                        $engineKey
 * @var string                        $engineLabel
 * @var SearchEngineInterface|null    $engine
 * @var EngineCapabilities|null       $capabilities
 * @var bool                          $engineAvailable
 * @var list<array{key:string,label:string,available:bool}> $installedEngines
 * @var string                        $fallbackKey
 * @var array<string, SearchSource>   $sources
 * @var array<string, SearchSource>   $disabled
 * @var array<string, int>            $counts
 * @var IndexState                    $state
 * @var string|null                   $staleReason
 */

$this->title = 'Поисковый индекс';
$this->params['breadcrumbs'][] = $this->title;

$yesNo = static fn (bool $value): string => $value
    ? '<span class="text-success">да</span>'
    : '<span class="text-muted">нет</span>';
?>
<div class="search-index-status">
    <h1 class="h4 mb-3"><?= Html::encode($this->title) ?></h1>

    <?php if ($installedEngines === []): ?>
        <div class="alert alert-danger">
            В системе нет ни одного ядра поиска. Установите и включите модуль ядра
            (например, «Поиск: TNTSearch» или «Поиск: Manticore») — до этого страница поиска
            будет отдавать пустую выдачу.
        </div>
    <?php elseif ($engine === null): ?>
        <div class="alert alert-danger">
            Ядро «<?= Html::encode($engineKey === '' ? 'не выбрано' : $engineKey) ?>» недоступно:
            модуль ядра не установлен или выключен в менеджере модулей. Выберите ядро из установленных
            в настройках приложения (раздел «Search»).
        </div>
    <?php elseif (!$engineAvailable): ?>
        <div class="alert alert-warning">
            Ядро «<?= Html::encode($engineLabel) ?>» включено, но сейчас не отвечает.
            <?php if ($fallbackKey !== ''): ?>
                Выдачу обслуживает запасное ядро «<?= Html::encode($fallbackKey) ?>».
            <?php else: ?>
                Запасное ядро не задано, поэтому выдача остаётся пустой.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($staleReason !== null): ?>
        <div class="alert alert-warning"><?= Html::encode($staleReason) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Индекс</div>
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <th style="width: 45%">Ядро</th>
                            <td><?= Html::encode($engineKey === '' ? 'не выбрано' : $engineLabel) ?></td>
                        </tr>
                        <tr>
                            <th>Собран</th>
                            <td>
                                <?= $state->rebuiltAt() === null
                                    ? 'никогда'
                                    : Yii::$app->formatter->asDatetime($state->rebuiltAt()) ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Документов в каталоге</th>
                            <td><?= $state->catalogCount() ?></td>
                        </tr>
                        <tr>
                            <th>Документов в индексе</th>
                            <td><?= $state->documentCount() ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Возможности ядра</div>
                <?php if ($capabilities === null): ?>
                    <div class="card-body text-muted">Ядро недоступно.</div>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr><th style="width: 45%">Поиск с опечатками</th><td><?= $yesNo($capabilities->fuzzy) ?></td></tr>
                            <tr><th>Лемматизация</th><td><?= $yesNo($capabilities->lemmatization) ?></td></tr>
                            <tr><th>Подсветка совпадений</th><td><?= $yesNo($capabilities->highlight) ?></td></tr>
                            <tr><th>Подсказки «имели в виду»</th><td><?= $yesNo($capabilities->suggestion) ?></td></tr>
                            <tr><th>Вкладки по разделам</th><td><?= $yesNo($capabilities->facets) ?></td></tr>
                            <tr><th>Обновление по одной записи</th><td><?= $yesNo($capabilities->incremental) ?></td></tr>
                            <?php if ($capabilities->comfortableSize !== null): ?>
                                <tr>
                                    <th>Комфортный объём</th>
                                    <td>
                                        до <?= Yii::$app->formatter->asInteger($capabilities->comfortableSize) ?> документов
                                        <?php if ($state->catalogCount() > $capabilities->comfortableSize): ?>
                                            <span class="badge text-bg-warning">превышен</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Установленные ядра</div>
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Ключ</th>
                    <th>Ядро</th>
                    <th>Отвечает</th>
                    <th>Роль</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($installedEngines as $row): ?>
                    <tr>
                        <td><code><?= Html::encode($row['key']) ?></code></td>
                        <td><?= Html::encode($row['label']) ?></td>
                        <td><?= $yesNo($row['available']) ?></td>
                        <td>
                            <?php if ($row['key'] === $engineKey): ?>
                                <span class="badge text-bg-primary">активное</span>
                            <?php elseif ($row['key'] === $fallbackKey): ?>
                                <span class="badge text-bg-secondary">запасное</span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($installedEngines === []): ?>
                    <tr>
                        <td colspan="4" class="text-muted">
                            Ни один модуль не объявил ядро поиска: нужен модуль, реализующий
                            <code>SearchEngineProvider</code>.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="card-footer text-muted small">
            Список равен составу включённых модулей-ядер: выключенное в менеджере модулей ядро
            исчезает и отсюда, и из выбора в настройках.
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Разделы контента</div>
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Ключ</th>
                    <th>Раздел</th>
                    <th class="text-end">В каталоге</th>
                    <th>Состояние</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sources as $type => $source): ?>
                    <tr>
                        <td><code><?= Html::encode($type) ?></code></td>
                        <td><?= Html::encode($source->label) ?></td>
                        <td class="text-end">
                            <?php $count = $counts[$type] ?? 0; ?>
                            <?= $count > 0
                                ? $count
                                : '<span class="text-warning">0</span>' ?>
                        </td>
                        <td><span class="text-success">включён</span></td>
                    </tr>
                <?php endforeach; ?>

                <?php foreach ($disabled as $type => $source): ?>
                    <tr class="text-muted">
                        <td><code><?= Html::encode($type) ?></code></td>
                        <td><?= Html::encode($source->label) ?></td>
                        <td class="text-end">—</td>
                        <td>отключён в настройках</td>
                    </tr>
                <?php endforeach; ?>

                <?php if ($sources === [] && $disabled === []): ?>
                    <tr>
                        <td colspan="4" class="text-muted">
                            Ни один модуль не объявил контент для поиска: нужен модуль, реализующий
                            <code>SearchableProvider</code>.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?= Html::beginForm(['rebuild'], 'post') ?>
        <?= Html::submitButton('Собрать индекс заново', [
            'class' => 'btn btn-primary',
            'data' => [
                'confirm' => 'Полная пересборка индекса. На время сборки поиск продолжает работать по '
                    . 'старому индексу. Продолжить?',
            ],
        ]) ?>
        <span class="ms-2 text-muted small">
            На большом объёме используйте консоль: <code>php yii Search/index/rebuild</code>
        </span>
    <?= Html::endForm() ?>
</div>
