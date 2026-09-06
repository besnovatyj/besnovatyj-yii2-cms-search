<?php

/*
 * Copyright (c) 2026 Besnovatyj. Licensed under the MIT License.
 */

declare(strict_types=1);

namespace Besnovatyj\Search\entities;

use Besnovatyj\Contracts\search\SearchDocument;
use yii\db\ActiveRecord;
use yii\helpers\Json;
use yii\helpers\Url;

/**
 * Строка каталога документов сквозного поиска.
 *
 * Не доменная сущность, а проекция чужого контента: живёт ровно до следующей переиндексации и
 * создаётся только индексатором. Поэтому здесь нет ни create/edit-логики, ни валидации — модуль,
 * которому документ принадлежит, остаётся единственным владельцем настоящих данных.
 *
 * @property int         $id
 * @property string      $type
 * @property string      $entity_id
 * @property string      $route
 * @property string|null $params_json
 * @property string      $title
 * @property string|null $excerpt
 * @property string      $content
 * @property string|null $keywords
 * @property string|null $image
 * @property int|null    $published_at
 * @property float       $boost
 * @property int         $indexed_at
 */
class SearchDocumentRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%search_documents}}';
    }

    /**
     * Параметры роута в виде массива.
     *
     * @return array<string, mixed>
     */
    public function params(): array
    {
        if ($this->params_json === null || $this->params_json === '') {
            return [];
        }

        try {
            $params = Json::decode($this->params_json);
        } catch (\Throwable) {
            return [];
        }

        return is_array($params) ? $params : [];
    }

    /**
     * Ссылка на документ, собираемая в момент вывода.
     *
     * Строится из `route` + `params` каждый раз заново, а не берётся готовой из базы: короткий URL
     * сущности мог измениться в модуле алиасов уже после индексации.
     */
    public function url(): string
    {
        return Url::to(array_merge([$this->route], $this->params()));
    }

    /**
     * Фрагмент текста для карточки выдачи, когда ядро не вернуло подсветку.
     *
     * Берётся готовый анонс от модуля-провайдера, а при его отсутствии — начало очищенного текста,
     * обрезанное по границе слова.
     */
    public function snippet(int $length = 240): string
    {
        $text = trim((string)($this->excerpt !== null && $this->excerpt !== '' ? $this->excerpt : $this->content));

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $cut = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim($lastSpace !== false ? mb_substr($cut, 0, $lastSpace) : $cut, " \t\n\r\0\x0B.,;:—-") . '…';
    }

    /**
     * Атрибуты строки каталога из контрактного документа модуля-провайдера.
     *
     * Текст сюда приходит уже нормализованным индексатором: HTML вычищен, шорткоды раскрыты.
     *
     * @param SearchDocument $document документ провайдера
     * @param string         $content  нормализованный текст
     * @param string|null    $excerpt  нормализованный анонс (null — соберётся из текста при выводе)
     * @param float          $boost    итоговый вес документа с учётом веса источника
     * @param int            $indexedAt метка текущего прогона переиндексации
     *
     * @return array<string, mixed>
     */
    public static function attributesFrom(
        SearchDocument $document,
        string $content,
        ?string $excerpt,
        float $boost,
        int $indexedAt,
    ): array {
        return [
            'type'         => $document->type,
            'entity_id'    => (string)$document->entityId,
            'route'        => $document->route,
            'params_json'  => $document->params === [] ? null : Json::encode($document->params),
            'title'        => mb_substr(trim($document->title), 0, 500),
            'excerpt'      => $excerpt,
            'content'      => $content,
            'keywords'     => $document->keywords === '' ? null : $document->keywords,
            'image'        => $document->image,
            'published_at' => $document->date,
            'boost'        => $boost,
            'indexed_at'   => $indexedAt,
        ];
    }
}
