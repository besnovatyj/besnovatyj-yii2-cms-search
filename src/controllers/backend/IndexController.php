<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\controllers\backend;

use Besnovatyj\Search\entities\SearchDocumentRecord;
use Besnovatyj\Search\services\EngineResolver;
use Besnovatyj\Search\services\Indexer;
use Besnovatyj\Search\services\IndexState;
use Besnovatyj\Search\services\SourceRegistry;
use Throwable;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;

/**
 * Состояние поискового индекса и его пересборка из админки.
 *
 * Страница отвечает на вопрос «почему поиск ничего не находит» до того, как он будет задан:
 * показывает, каким ядром собран индекс, когда это было, сколько документов дал каждый раздел
 * и не устарел ли индекс после смены настроек.
 */
class IndexController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly EngineResolver $engines,
        private readonly SourceRegistry $sources,
        private readonly IndexState $state,
        private readonly Indexer $indexer,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'rebuild' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Сводка: ядро, возможности, состояние индекса, разбивка каталога по разделам.
     */
    public function actionIndex(): string
    {
        $engineKey = $this->engines->configuredKey();
        $engine = $this->engines->engine($engineKey);

        return $this->render('index', [
            'engineKey' => $engineKey,
            'engine' => $engine,
            'capabilities' => $engine?->capabilities(),
            'engineAvailable' => $engine?->isAvailable() ?? false,
            'sources' => $this->sources->enabledSources(),
            'disabled' => array_diff_key($this->sources->allSources(), $this->sources->enabledSources()),
            'counts' => $this->catalogCounts(),
            'state' => $this->state,
            'staleReason' => $this->state->staleReason($engineKey),
        ]);
    }

    /**
     * Полная пересборка индекса.
     *
     * Выполняется синхронно: для сайта в тысячи документов это секунды, и синхронный ответ честнее
     * очереди — администратор сразу видит, сколько документов дал каждый раздел. На заметно большем
     * объёме операцию следует запускать консолью: там нет ни лимита времени веб-сервера, ни
     * занятого воркера PHP-FPM.
     */
    public function actionRebuild(): Response
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }

        try {
            $report = $this->indexer->rebuild();

            Yii::$app->session->setFlash('success', sprintf(
                'Индекс собран ядром «%s»: %d документов за %s с (устаревших строк удалено: %d).',
                $report->engine,
                $report->documents,
                $report->seconds,
                $report->removed,
            ));
        } catch (Throwable $e) {
            Yii::error('Пересборка индекса из админки не удалась: ' . $e->getMessage(), 'search/index');
            Yii::$app->session->setFlash('error', 'Не удалось собрать индекс: ' . $e->getMessage());
        }

        return $this->redirect(['index']);
    }

    /**
     * Сколько документов лежит в каталоге по каждому разделу.
     *
     * @return array<string, int>
     */
    private function catalogCounts(): array
    {
        $rows = SearchDocumentRecord::find()
            ->select(['type', 'total' => 'COUNT(*)'])
            ->groupBy('type')
            ->asArray()
            ->all();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string)$row['type']] = (int)$row['total'];
        }

        return $counts;
    }
}
