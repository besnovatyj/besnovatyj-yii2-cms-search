<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\controllers\backend;

use Besnovatyj\Search\entities\SearchDocumentRecord;
use Besnovatyj\Search\services\EngineRegistry;
use Besnovatyj\Search\services\EngineResolver;
use Besnovatyj\Search\services\Indexer;
use Besnovatyj\Search\services\IndexPurger;
use Besnovatyj\Search\services\IndexState;
use Besnovatyj\Search\services\SourceRegistry;
use Besnovatyj\Search\settings\SearchSettings;
use Throwable;
use Yii;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\Controller;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

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
        private readonly EngineRegistry $registry,
        private readonly SourceRegistry $sources,
        private readonly IndexState $state,
        private readonly Indexer $indexer,
        private readonly IndexPurger $purger,
        private readonly SearchSettings $settings,
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
                    'purge' => ['POST'],
                    'storage' => ['POST'],
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
            'engineLabel' => $this->registry->labelFor($engineKey),
            'engine' => $engine,
            'capabilities' => $engine?->capabilities(),
            'engineAvailable' => $engine?->isAvailable() ?? false,
            'engineReason' => $engine?->unavailableReason(),
            'installedEngines' => $this->installedEngines(),
            'fallbackKey' => $this->settings->fallbackEngine,
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
    public function actionRebuild(): Response|array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }

        if (Yii::$app->request->getIsAjax()) {
            return $this->rebuildAsJson();
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
     * Та же пересборка, но для плитки дашборда: ответ — обновлённые счётчики, а не редирект.
     *
     * Сделано ответвлением одного экшена, а не вторым: операция ровно та же, и раздваивать
     * её значило бы получить две точки, где однажды разойдутся таймаут, права и поведение.
     * Различается только конверт ответа.
     *
     * Ошибки отдаются нативным JSON-конвертом Yii ({@see \yii\web\ErrorHandler}) с реальным
     * HTTP-статусом: формат выставлен до сборки, поэтому и исключение уйдёт как JSON.
     *
     * @return array{engine:string,documents:int,catalog:int,seconds:float,built:string}
     *
     * @throws ServerErrorHttpException если сборка не удалась — причина уже в журнале.
     */
    private function rebuildAsJson(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        try {
            $report = $this->indexer->rebuild();
        } catch (Throwable $e) {
            Yii::error('Пересборка индекса из дашборда не удалась: ' . $e->getMessage(), 'search/index');

            throw new ServerErrorHttpException('Не удалось собрать индекс: ' . $e->getMessage(), 0, $e);
        }

        $label = $this->registry->labelFor($report->engine);

        return [
            'engine' => $label,
            'documents' => $report->documents,
            'catalog' => $this->state->catalogCount(),
            'seconds' => round($report->seconds, 1),
            'built' => sprintf(
                'Собран %s ядром «%s»',
                Yii::$app->formatter->asRelativeTime(time()),
                $label,
            ),
        ];
    }

    /**
     * Стереть всё поисковое: каталог документов и данные всех установленных ядер.
     *
     * Пересборки следом нет намеренно: очистка нужна, чтобы освободить место (например, перед
     * дампом базы), а собрать индекс заново — отдельное осознанное действие.
     *
     * Как и {@see actionRebuild()}, один экшен с двумя конвертами: AJAX-запрос менеджера очистки
     * (`yii2-cms-clear-manager`, строка объявлена в `params.endpoints.clear`) получает JSON
     * `{status, message}`, а форма на странице состояния — флеш и редирект. Ошибка AJAX уходит
     * нативным JSON-конвертом Yii с реальным HTTP-статусом.
     *
     * @throws ServerErrorHttpException если очистка не удалась — причина уже в журнале.
     */
    public function actionPurge(): Response|array
    {
        if (Yii::$app->request->getIsAjax()) {
            Yii::$app->response->format = Response::FORMAT_JSON;

            try {
                $this->purger->purge();
            } catch (Throwable $e) {
                Yii::error('Очистка поискового индекса не удалась: ' . $e->getMessage(), 'search/index');

                throw new ServerErrorHttpException('Не удалось очистить поисковый индекс: ' . $e->getMessage(), 0, $e);
            }

            return ['status' => 'success', 'message' => 'Поисковый индекс очищен'];
        }

        try {
            $this->purger->purge();
            Yii::$app->session->setFlash('success', 'Поисковый индекс очищен. Соберите его заново, когда понадобится.');
        } catch (Throwable $e) {
            Yii::error('Очистка поискового индекса не удалась: ' . $e->getMessage(), 'search/index');
            Yii::$app->session->setFlash('error', 'Не удалось очистить поисковый индекс: ' . $e->getMessage());
        }

        return $this->redirect(['index']);
    }

    /**
     * Сколько занимает поисковое — строка «Данные» в менеджере очистки.
     *
     * Ответ по соглашению `yii2-cms-clear-manager`: `{status: 'success', data: '<готовая строка>'}`.
     *
     * @throws BadRequestHttpException если запрос не AJAX
     */
    public function actionStorage(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->getIsAjax()) {
            throw new BadRequestHttpException('Ожидается AJAX-запрос.');
        }

        return [
            'status' => 'success',
            'data' => sprintf(
                'Документов: %s · %s',
                Yii::$app->formatter->asInteger($this->state->catalogCount()),
                Yii::$app->formatter->asShortSize($this->purger->storageBytes(), 2),
            ),
        ];
    }

    /**
     * Установленные ядра с их состоянием — чтобы на странице было видно, из чего вообще есть выбор
     * и почему выбранное не работает.
     *
     * @return list<array{key:string,label:string,available:bool,reason:string|null}>
     */
    private function installedEngines(): array
    {
        $rows = [];

        foreach ($this->registry->descriptors() as $key => $descriptor) {
            $engine = $this->registry->engine($key);

            $rows[] = [
                'key' => $key,
                'label' => $descriptor->label,
                'available' => $engine?->isAvailable() ?? false,
                'reason' => $engine?->unavailableReason() ?? 'Ядро не удалось создать — см. журнал приложения.',
            ];
        }

        return $rows;
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
