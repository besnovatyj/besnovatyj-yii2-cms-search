<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\widgets\dashboard;

use Besnovatyj\Search\services\EngineRegistry;
use Besnovatyj\Search\services\EngineResolver;
use Besnovatyj\Search\services\IndexState;
use Throwable;
use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

/**
 * Плитка дашборда: когда собран поисковый индекс, сколько в нём документов и кнопка пересборки.
 *
 * Отвечает одним взглядом на вопрос «ищет ли поиск сейчас». Показательны не столько сами числа,
 * сколько их расхождение: «в каталоге 420, в индексе 180» означает, что контент правили после
 * последней сборки, и выдача отстаёт от сайта. Поэтому оба счётчика стоят рядом, а не порознь.
 *
 * Пересборка бьётся AJAX-POST'ом в штатный эндпойнт модуля `/Search/backend/index/rebuild`
 * (тот же, что у кнопки на странице состояния) и возвращает обновлённые счётчики, которые плитка
 * подставляет на месте. Уводить администратора с главной ради операции, которая на обычном сайте
 * занимает секунды, незачем; на большом объёме сборку всё равно запускают консолью.
 * JS — самодостаточный инлайн (fetch) в POS_END: без внешних ассетов и без зависимости от jQuery,
 * что согласуется с тем, что ассеты в админке подключает пользователь сам.
 *
 * Сервисы берутся из контейнера прямо в `run()`: плитка инстанцируется дашбордом как обычный
 * виджет, без передачи зависимостей, и это единственное место, где их можно получить.
 */
class SearchIndexTile extends Widget
{
    public function run(): string
    {
        $state = Yii::$container->get(IndexState::class);
        $resolver = Yii::$container->get(EngineResolver::class);
        $registry = Yii::$container->get(EngineRegistry::class);

        $engineKey = $resolver->configuredKey();
        $indexed = $state->documentCount();
        $catalog = $state->catalogCount();

        $this->registerRebuildJs();

        return $this->counters($indexed, $catalog)
            . $this->built($state, $registry)
            . $this->warning($state, $engineKey, $registry)
            . $this->actions();
    }

    /**
     * Два счётчика рядом: сколько документов ищется прямо сейчас и сколько их набрано в каталоге.
     */
    private function counters(int $indexed, int $catalog): string
    {
        $big = Html::tag('span', (string)$indexed, [
            'class' => 'display-6 fw-bold lh-1',
            'data-search-indexed' => true,
        ]);
        $caption = Html::tag('span', 'документов в индексе', ['class' => 'text-muted ms-2']);

        $lag = Html::tag('span', 'индекс отстаёт', [
            'class' => 'badge text-bg-warning ms-1',
            'data-search-lag' => true,
            'hidden' => $indexed === $catalog,
        ]);

        $catalogLine = Html::tag(
            'div',
            'В каталоге: ' . Html::tag('span', (string)$catalog, ['data-search-catalog' => true]) . ' ' . $lag,
            ['class' => 'text-muted small mt-1'],
        );

        return Html::tag('div', $big . $caption, ['class' => 'd-flex align-items-baseline']) . $catalogLine;
    }

    /**
     * Когда и каким ядром собран индекс. Ядро названо здесь намеренно: после смены движка в
     * настройках старый индекс перестаёт соответствовать запросам, и «собран ядром X» — первая
     * подсказка, почему выдача изменилась.
     */
    private function built(IndexState $state, EngineRegistry $registry): string
    {
        $rebuiltAt = $state->rebuiltAt();

        if ($rebuiltAt === null) {
            $text = 'Индекс ещё не собирался.';
        } else {
            $text = 'Собран ' . Yii::$app->formatter->asRelativeTime($rebuiltAt)
                . ' ядром «' . Html::encode($registry->labelFor((string)$state->builtWithEngine())) . '»';
        }

        return Html::tag('div', $text, [
            'class' => 'text-muted small',
            'data-search-built' => true,
        ]);
    }

    /**
     * Одно предупреждение, а не список: плитка сообщает о самой ранней поломке в цепочке
     * «ядро есть → ядро отвечает → индекс свеж», потому что следующие звенья при сломанном
     * предыдущем ни о чём не говорят. Подробности — на странице состояния.
     */
    private function warning(IndexState $state, string $engineKey, EngineRegistry $registry): string
    {
        $engine = $registry->engine($engineKey);

        if ($engine === null) {
            return $this->alert(
                'danger',
                $engineKey === ''
                    ? 'Ядро поиска не выбрано — выдача пуста.'
                    : 'Ядро «' . Html::encode($engineKey) . '» не установлено или выключено.',
            );
        }

        // Доступность спрашиваем у ядра: у сетевого движка это обращение к демону, и именно его
        // молчание — самая частая причина «поиск вдруг перестал искать».
        try {
            $available = $engine->isAvailable();
            $reason = $available ? null : $engine->unavailableReason();
        } catch (Throwable $e) {
            Yii::error('Плитка поиска: проверка ядра не удалась: ' . $e->getMessage(), 'search/dashboard');
            $available = false;
            $reason = 'ядро не отвечает';
        }

        if (!$available) {
            return $this->alert('danger', 'Ядро не обслуживает поиск: ' . Html::encode((string)$reason));
        }

        $staleReason = $state->staleReason($engineKey);

        return $staleReason === null
            ? ''
            : $this->alert('warning', Html::encode($staleReason), ['data-search-stale' => true]);
    }

    /**
     * @param array<string, mixed> $options дополнительные атрибуты контейнера
     */
    private function alert(string $kind, string $text, array $options = []): string
    {
        return Html::tag(
            'div',
            '<i class="bi bi-exclamation-triangle me-1"></i>' . $text,
            array_merge(['class' => 'text-' . $kind . ' small mt-1'], $options),
        );
    }

    /**
     * Кнопка пересборки и ссылка на страницу состояния — разбивка по разделам, список ядер и их
     * возможности живут там, в плитке им тесно.
     */
    private function actions(): string
    {
        $button = Html::button('<i class="bi bi-arrow-repeat me-1"></i>Пересобрать', [
            'id' => $this->rebuildButtonId(),
            'type' => 'button',
            'class' => 'btn btn-sm btn-outline-primary',
        ]);

        $link = Html::a(
            '<i class="bi bi-search me-1"></i>К индексу',
            Url::to(['/Search/backend/index/index']),
            ['class' => 'btn btn-sm btn-outline-secondary'],
        );

        $status = Html::tag('span', '', ['data-search-status' => true, 'class' => 'text-muted small']);

        return Html::tag(
            'div',
            $button . $link . $status,
            ['class' => 'd-flex align-items-center gap-2 flex-wrap mt-3'],
        );
    }

    private function rebuildButtonId(): string
    {
        return 'dash-search-rebuild-' . $this->getId();
    }

    /**
     * Обработчик кнопки. Сборка синхронная и может занять заметное время, поэтому кнопка на это
     * время блокируется, а рядом висит «Собираю…»: без этого администратор жмёт второй раз и
     * запускает вторую полную пересборку.
     *
     * Ошибку берём из тела ответа: эндпойнт отдаёт нативный JSON-конверт ошибки Yii
     * (`{name, message, ...}`) с реальным HTTP-статусом.
     */
    private function registerRebuildJs(): void
    {
        $buttonId = $this->rebuildButtonId();
        $endpoint = Json::encode(Url::to(['/Search/backend/index/rebuild']));
        $csrfHeader = Json::encode(Yii::$app->request->csrfHeader);
        $csrfToken = Json::encode(Yii::$app->request->getCsrfToken());

        $this->view->registerJs(
            <<<JS
            (function () {
                var btn = document.getElementById('{$buttonId}');
                if (!btn) { return; }
                var card = btn.closest('.card') || btn.parentNode.parentNode;
                var status = card.querySelector('[data-search-status]');
                var indexed = card.querySelector('[data-search-indexed]');
                var catalog = card.querySelector('[data-search-catalog]');
                var built = card.querySelector('[data-search-built]');
                btn.addEventListener('click', function () {
                    btn.disabled = true;
                    status.textContent = 'Собираю…';
                    var headers = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
                    headers[{$csrfHeader}] = {$csrfToken};
                    fetch({$endpoint}, { method: 'POST', headers: headers })
                        .then(function (r) {
                            return r.json().then(function (body) {
                                return r.ok ? body : Promise.reject(body);
                            });
                        })
                        .then(function (body) {
                            indexed.textContent = body.documents;
                            catalog.textContent = body.catalog;
                            built.textContent = body.built;
                            var lag = card.querySelector('[data-search-lag]');
                            if (lag) { lag.hidden = body.documents === body.catalog; }
                            var stale = card.querySelector('[data-search-stale]');
                            if (stale) { stale.remove(); }
                            status.textContent = 'Готово за ' + body.seconds + ' с';
                        })
                        .catch(function (body) {
                            status.textContent = (body && body.message) || 'Ошибка сборки';
                        })
                        .finally(function () { btn.disabled = false; });
                });
            })();
            JS,
            View::POS_END,
        );
    }
}
