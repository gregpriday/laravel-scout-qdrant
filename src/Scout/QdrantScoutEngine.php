<?php

namespace GregPriday\LaravelScoutQdrant\Scout;

use GregPriday\LaravelScoutQdrant\Vectorizer\VectorizerEngineManager;
use GregPriday\LaravelScoutQdrant\Vectorizer\VectorizerInterface;
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

    const DEFAULT_VECTOR_FIELD = 'document';

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

        foreach ($models as $model) {
            $searchableData = $model->toSearchableArray();

            if (empty($searchableData)) {
                continue;
            }

            $vectors = new MultiVectorStruct();

            foreach ($searchableData as $name => $data) {
                // If a '^' character is found at the start of the name, vectorize the data
                if (str_starts_with($name, '^')) {
                    $name = substr($name, 1); // Extract the actual name of the field
                    $vectorizedData = $this->vectorizerEngineManager->driver($model->getVectorizer($name))
                        ->embedDocument($data);
                    $vectors->addVector($name, $vectorizedData);
                    unset($searchableData[$name]); // Remove the vectorized field from the searchable data
                }
            }

            // If no vectors were added, create a 'document' vector from the joined string values
            if ($vectors->count() === 0) {
                $documentData = implode("\n\n", array_map('strval', $searchableData));
                $documentVector = $this->vectorizerEngineManager->driver($model->getDefaultVectorizer())
                    ->embedDocument($documentData);
                $vectors->addVector(self::DEFAULT_VECTOR_FIELD, $documentVector);
            }

            $points = new PointsStruct();

            $points->addPoint(
                new PointStruct(
                    (int) $model->getScoutKey(),
                    $vectors,
                    $searchableData
                )
            );

            if (!empty($points)) {
                $this->qdrant->collections($collectionName)->points()->upsert($points);
            }
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
        ]);
    }

    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, [
            'limit' => $perPage ?? 100,
            'offset' => ($page - 1) * $perPage,
        ]);
    }

    public function setVectorField(string $vectorField): self
    {
        $this->vectorField = $vectorField;
        return $this;
    }

    public function getVectorField(): string
    {
        return $this->vectorField ?? self::DEFAULT_VECTOR_FIELD;
    }

    protected function performSearch(Builder $builder, array $options = [])
    {
        $collectionName = $builder->index ?: $builder->model->searchableAs();
        $vectorField = $this->getVectorField() ?? $builder->model->getVectorField();

        $embedding = $this->vectorizer->embedQuery($builder->query);
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
        if (!class_exists($name)) {
            throw new InvalidArgumentException('Index name must be a fully qualified class name');
        }

        $model = new $name;

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
        if (!class_exists($name)) {
            throw new InvalidArgumentException('Index name must be a fully qualified class name');
        }
        $model = new $name;
        $indexName = $model->searchableAs();

        return $this->qdrant->collections($indexName)->delete();
    }

    protected function usesSoftDelete($model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }
}
