<?php

namespace GregPriday\LaravelScoutQdrant\Scout;

use GregPriday\LaravelScoutQdrant\Models\Vectorizable;
use GregPriday\LaravelScoutQdrant\Vectorizer\VectorizerEngineManager;
use GregPriday\LaravelScoutQdrant\Vectorizer\VectorizerInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Engines\Engine;
use Qdrant\Exception\InvalidArgumentException;
use Qdrant\Models\Filter\Condition\MatchBool;
use Qdrant\Models\MultiVectorStruct;
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
    private VectorizerEngineManager $vectorizerEngineManager;
    private string $vectorField;

    public function __construct(Qdrant $qdrant, VectorizerEngineManager $vectorizerEngineManager)
    {
        $this->qdrant = $qdrant;
        $this->vectorizerEngineManager = $vectorizerEngineManager;
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

            // Get the current point from the collection
            try{
                $currentPoint = $this->qdrant->collections($collectionName)->points()->id($model->getScoutKey());
                $currentVectors = $currentPoint['result']['vector'];
            } catch (InvalidArgumentException $e) {
                $currentPoint = null;
            }

            $vectors = new MultiVectorStruct();

            foreach ($model->getVectorizers() as $name => $vectorizerClass) {
                $data = $searchableData[$name] ?? null;
                if (!$data) {
                    continue;
                }

                // If the field hasn't changed, fetch the vector from $currentPoint
                if (!$model->hasVectorFieldChanged($name, $data) && isset($currentVectors[$name])) {
                    $vectorizedData = $currentVectors[$name];
                }
                // If the field has changed or doesn't exist in $currentPoint, use the vectorizer
                else {
                    $vectorizer = $this->vectorizerEngineManager->driver($vectorizerClass);
                    $vectorizedData = $vectorizer->embedDocument($data);
                    $model->setVectorFieldHash($name, $data);
                }

                $vectors->addVector($name, $vectorizedData);
                unset($searchableData[$name]); // Remove the vectorized field from the searchable data
            }

            $points->addPoint(
                new PointStruct(
                    (int) $model->getScoutKey(),
                    // Vectors are stored as a MultiVectorStruct
                    $vectors,
                    // We're using the remaining searchable data as the payload
                    $searchableData
                )
            );
        }

        // Perform the bulk upsert operation
        if ($points->count()) {
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

        $this->qdrant->collections($collectionName)->points()->delete($ids);
    }

    public function search(Builder $builder)
    {
        return $this->performSearch($builder, [
            'limit' => $builder->limit ?? 100,
            'field' => $builder->options['field'] ?? null
        ]);
    }

    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, [
            'limit' => $perPage ?? 100,
            'offset' => ($page - 1) * $perPage,
            'field' => $builder->options['field'] ?? null
        ]);
    }

    protected function performSearch(Builder $builder, array $options = [])
    {
        $collectionName = $builder->index ?: $builder->model->searchableAs();
        $vectorField = $options['field'] ?? $builder->model->getDefaultVectorField();

        $vectorizer = $this->vectorizerEngineManager->driver($builder->model->getVectorizers()[$vectorField] ?? 'openai');
        $embedding = $vectorizer->embedQuery($builder->query);
        $vector = new VectorStruct($embedding, $vectorField);

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

    public function getTotalCount($results): int
    {
        return count($results['result']);
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
        // Use getModelForTable
        $model = $this->getModelForTableName($name);
        $createCollection = new CreateCollection();

        foreach ($model->getVectorizers() as $vectorField => $vectorizerClass) {
            // Using the engine manager to get the driver
            $vectorizer = $this->vectorizerEngineManager->driver($vectorizerClass);
            $createCollection->addVector($vectorizer->vectorParams(), $vectorField);
        }

        $indexName = $model->searchableAs();

        $this->qdrant->collections($indexName)->create($createCollection);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function deleteIndex($name)
    {
        return $this->qdrant->collections($name)->delete();
    }

    protected function usesSoftDelete($model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }

    /**
     * For a given table name, return an instance of that model.
     *
     * @param string $tableName The table name
     * @return Model|null
     */
    private function getModelForTableName(string $tableName)
    {
        static $models = [];
        if(isset($models[$tableName])) {
            return $models[$tableName];
        }

        foreach( get_declared_classes() as $class ) {
            if(
                // subclass of eloquent model AND implements Vectorizable
                is_subclass_of( $class, 'Illuminate\Database\Eloquent\Model' ) &&
                in_array(Vectorizable::class, class_implements($class) )
            ) {
                $model = new $class;
                if ($model->getTable() === $tableName){
                    $models[$tableName] = $model;
                    return $models[$tableName];
                }
            }
        }

        return null;
    }
}
