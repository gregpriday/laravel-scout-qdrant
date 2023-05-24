<?php

namespace GregPriday\LaravelScoutQdrant\Scout;

use GregPriday\LaravelScoutQdrant\Vectorizer\VectorizerInterface;
use Laravel\Scout\Engines\Engine;
use OpenAI\Client;
use Qdrant\Exception\InvalidArgumentException;
use Qdrant\Models\Filter\Condition\MatchBool;
use Qdrant\Models\Request\VectorParams;
use Qdrant\Qdrant;
use Qdrant\Models\PointsStruct;
use Qdrant\Models\PointStruct;
use Qdrant\Models\VectorStruct;
use Qdrant\Models\Request\CreateCollection;
use Qdrant\Models\Request\SearchRequest;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;

use Qdrant\Models\Filter\Condition\MatchString;
use Qdrant\Models\Filter\Condition\MatchInt;
use Qdrant\Models\Filter\Filter;

class QdrantScoutEngine extends Engine
{
    private Qdrant $qdrant;
    private VectorizerInterface $vectorizer;

    public function __construct(Qdrant $qdrant, VectorizerInterface $vectorizer)
    {
        $this->qdrant = $qdrant;
        $this->vectorizer = $vectorizer;
    }

    public function update($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $collectionName = $models->first()->searchableAs();
        $points = new PointsStruct();

        foreach ($models as $model) {
            $searchableData = $model->toSearchableArray();

            if (empty($searchableData)) {
                continue;
            }

            $embedding = $this->vectorizer->embedDocument($searchableData['vector'] ?? json_encode($searchableData));
            $vector = new VectorStruct($embedding, 'vector');

            $points->addPoint(
                new PointStruct(
                    (int) $model->getScoutKey(),
                    $vector,
                    $searchableData
                )
            );
        }

        if (!empty($points)) {
            $this->qdrant->collections($collectionName)->points()->upsert($points);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function delete($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $collectionName = $models->first()->searchableAs();
        $ids = $models->map->getScoutKey()->all();

        foreach ($ids as $id) {
            $this->qdrant->collections($collectionName)->points()->delete($id);
        }
    }

    public function search(Builder $builder)
    {
        return $this->performSearch($builder, [
            'limit' => $builder->limit ?? 100,
        ]);
    }

    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, [
            'limit' => $perPage ?? 100,
            'offset' => ($page - 1) * $perPage,
        ]);
    }

    protected function performSearch(Builder $builder, array $options = [])
    {
        $collectionName = $builder->index ?: $builder->model->searchableAs();

        $embedding = $this->vectorizer->embedQuery($builder->query);
        $vector = new VectorStruct($embedding, 'vector');

        $searchRequest = new SearchRequest($vector);
        $searchRequest->setLimit($options['limit']);

        // Loop through the builder's wheres and add them to the filter
        if(!empty($builder->wheres)){
            $filter = new Filter();

            foreach ($builder->wheres as $field => $value) {
                if(is_numeric($value)) {
                    $filter->addMust(
                        new MatchInt($field, $value)
                    );
                } elseif(is_bool($value)){
                    $filter->addMust(
                        new MatchBool($field, $value)
                    );
                } else {
                    $filter->addMust(
                        new MatchString($field, $value)
                    );
                }
            }

            // Attach the filter to the search request
            $searchRequest->setFilter($filter);
        }

        if (isset($options['offset'])) {
            $searchRequest->setOffset($options['offset']);
        }

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $this->qdrant,
                $builder->query,
                $options
            );
        }

        return $this->qdrant->collections($collectionName)->points()->search($searchRequest);
    }

    public function mapIds($results)
    {
        return collect($results['result'])->pluck('id')->values();
    }

    public function map(Builder $builder, $results, $model)
    {
        if (count($results['result']) === 0) {
            return $model->newCollection();
        }

        $objectIds = collect($results['result'])->pluck('id')->values()->all();
        $objectIdPositions = array_flip($objectIds);
        $objectScores = collect($results['result'])->pluck('score', 'id')->all();

        return $model->getScoutModelsByIds(
            $builder, $objectIds
        )->filter(function ($model) use ($objectIds) {
            return in_array($model->getScoutKey(), $objectIds);
        })->sortBy(function ($model) use ($objectIdPositions) {
            return $objectIdPositions[$model->getScoutKey()];
        })->values()->each(function ($model) use ($objectScores) {
            $model->search_score = $objectScores[$model->getScoutKey()];
        });
    }

    public function lazyMap(Builder $builder, $results, $model)
    {
        if (count($results['result']) === 0) {
            return LazyCollection::make($model->newCollection());
        }

        $objectIds = collect($results['result'])->pluck('id')->values()->all();
        $objectIdPositions = array_flip($objectIds);
        $objectScores = collect($results['result'])->pluck('score', 'id')->all();

        return $model->queryScoutModelsByIds(
            $builder, $objectIds
        )->cursor()->filter(function ($model) use ($objectIds) {
            return in_array($model->getScoutKey(), $objectIds);
        })->sortBy(function ($model) use ($objectIdPositions) {
            return $objectIdPositions[$model->getScoutKey()];
        })->values()->each(function ($model) use ($objectScores) {
            $model->search_score = $objectScores[$model->getScoutKey()];
        });
    }

    public function getTotalCount($results)
    {
        return $results['total'];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function flush($model)
    {
        $collectionName = $model->searchableAs();

        $this->deleteIndex($collectionName);
        $this->createIndex($collectionName);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function createIndex($name, array $options = [])
    {
        $dimensions = $options['dimensions'] ?? 1536;
        $createCollection = new CreateCollection();
        $createCollection->addVector(new VectorParams($dimensions, VectorParams::DISTANCE_COSINE), 'vector');
        $this->qdrant->collections($name)->create($name, $createCollection);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function deleteIndex($name)
    {
        return $this->qdrant->collections($name)->delete($name);
    }

    protected function usesSoftDelete($model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }
}
