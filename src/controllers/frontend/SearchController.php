<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\controllers\frontend;

use Besnovatyj\Search\forms\frontend\SearchForm;
use Besnovatyj\Search\services\SearchService;
use Yii;
use yii\data\Pagination;
use yii\web\Controller;

/**
 * Страница сквозного поиска по сайту (ЧПУ `/search`).
 *
 * Контроллер намеренно тонкий: читает запрос в форму, отдаёт его сервису и рендерит готовую
 * страницу выдачи. Ни ядро поиска, ни устройство индекса ему не известны — благодаря этому
 * страница не меняется при смене движка.
 */
class SearchController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly SearchService $search,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Выдача по запросу из адресной строки.
     */
    public function actionIndex(): string
    {
        $form = new SearchForm();
        $form->load(Yii::$app->request->queryParams);
        $form->validate();

        $result = $this->search->search($form->q, $form->types(), $form->page);

        // Пагинация строится уже по факту: общее число совпадений известно только после запроса
        // к ядру. Номер страницы Pagination берёт из того же параметра `page`, что и форма.
        $pagination = new Pagination([
            'totalCount' => $result->total,
            'pageSize' => $result->perPage,
            'defaultPageSize' => $result->perPage,
            'forcePageParam' => false,
        ]);

        $this->view->title = $result->query === ''
            ? 'Поиск по сайту'
            : 'Поиск: ' . $result->query;

        // Страницы выдачи не должны попадать в индекс поисковых систем: это бесконечное
        // пространство адресов с дублирующимся контентом.
        $this->view->registerMetaTag(['name' => 'robots', 'content' => 'noindex, follow']);

        return $this->render('index', [
            'result' => $result,
            'form' => $form,
            'pagination' => $pagination,
        ]);
    }
}
