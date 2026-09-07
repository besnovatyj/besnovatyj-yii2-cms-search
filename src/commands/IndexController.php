<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\commands;

use Besnovatyj\Search\services\EngineRegistry;
use Besnovatyj\Search\services\EngineResolver;
use Besnovatyj\Search\services\Indexer;
use Besnovatyj\Search\services\IndexState;
use Besnovatyj\Search\services\SourceRegistry;
use Throwable;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Консольное управление поисковым индексом.
 *
 *  - `php yii Search/index/rebuild` — полная пересборка (для крона и деплоя)
 *  - `php yii Search/index/status`  — состояние индекса и список источников
 *
 * Консоль — основной способ переиндексации на боевом сервере: нет лимита времени веб-сервера и
 * не занимается воркер PHP-FPM. На проде команду следует запускать от пользователя веб-сервера
 * (`sudo -u www-data php yii ...`), иначе процесс не прочитает секреты подключения к базе.
 */
final class IndexController extends Controller
{
    public function __construct(
        $id,
        $module,
        private readonly Indexer $indexer,
        private readonly IndexState $state,
        private readonly EngineResolver $engines,
        private readonly EngineRegistry $registry,
        private readonly SourceRegistry $sources,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Собрать индекс заново: контент модулей → каталог → индекс ядра.
     */
    public function actionRebuild(): int
    {
        $this->stdout("Пересборка поискового индекса\n", Console::BOLD);

        try {
            $report = $this->indexer->rebuild(function (string $message): void {
                $this->stdout($message . "\n");
            });
        } catch (Throwable $e) {
            $this->stderr('Ошибка: ' . $e->getMessage() . "\n", Console::FG_RED);

            return ExitCode::UNAVAILABLE;
        }

        $this->stdout(sprintf(
            "Готово: %d документов, ядро «%s», %s с. Удалено устаревших строк: %d.\n",
            $report->documents,
            $report->engine,
            $report->seconds,
            $report->removed,
        ), Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Показать состояние индекса и объявленные источники контента.
     */
    public function actionStatus(): int
    {
        $engineKey = $this->engines->configuredKey();
        $engine = $this->engines->engine($engineKey);

        $this->stdout("Ядро:      ", Console::BOLD);
        $this->stdout($engineKey === '' ? "не выбрано\n" : $engineKey . "\n");

        $available = $engine?->isAvailable() ?? false;
        $this->stdout("Доступно:  ", Console::BOLD);
        $this->stdout($available ? "да\n" : "нет\n", $available ? Console::FG_GREEN : Console::FG_RED);

        if (!$available) {
            $this->stdout('           ' . ($engine?->unavailableReason() ?? 'ядро не установлено') . "\n", Console::FG_YELLOW);
        }

        $rebuiltAt = $this->state->rebuiltAt();
        $this->stdout("Собран:    ", Console::BOLD);
        $this->stdout($rebuiltAt === null ? "никогда\n" : date('Y-m-d H:i:s', $rebuiltAt) . "\n");

        $this->stdout("Документов: ", Console::BOLD);
        $this->stdout($this->state->catalogCount() . "\n");

        $reason = $this->state->staleReason($engineKey);
        if ($reason !== null) {
            $this->stdout('! ' . $reason . "\n", Console::FG_YELLOW);
        }

        $this->stdout("\nУстановленные ядра:\n", Console::BOLD);
        foreach ($this->registry->descriptors() as $key => $descriptor) {
            $installed = $this->registry->engine($key);
            $ready = $installed?->isAvailable() ?? false;
            $this->stdout(sprintf(
                "  %-12s %-40s %s\n",
                $key,
                $descriptor->label,
                $ready ? 'готово' : ($installed?->unavailableReason() ?? 'ядро не удалось создать'),
            ), $ready ? Console::FG_GREEN : Console::FG_YELLOW);
        }

        if ($this->registry->descriptors() === []) {
            $this->stdout("  нет ни одного: установите модуль ядра поиска\n", Console::FG_YELLOW);
        }

        $this->stdout("\nИсточники:\n", Console::BOLD);
        foreach ($this->sources->allSources() as $type => $source) {
            $enabled = isset($this->sources->enabledSources()[$type]);
            $this->stdout(sprintf(
                "  %-24s %-28s %s\n",
                $type,
                $source->label,
                $enabled ? 'включён' : 'отключён',
            ), $enabled ? Console::FG_GREEN : Console::FG_GREY);
        }

        return ExitCode::OK;
    }
}
