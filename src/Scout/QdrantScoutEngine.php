<?php

namespace GregPriday\LaravelScoutQdrant\Scout;

use GregPriday\LaravelScoutQdrant\Vectorizer\Manager\VectorizerEngineManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Qdrant\Exception\InvalidArgumentException;
use Qdrant\Models\Filter\Condition\MatchBool;
use Qdrant\Models\Filter\Condition\MatchInt;
use Qdrant\Models\Filter\Condition\MatchString;
use Qdrant\Models\Filter\Filter;
use Qdrant\Models\MultiVectorStruct;
use Qdrant\Models\PointsStruct;
use Qdrant\Models\PointStruct;
use Qdrant\Models\Request\CreateCollection;
use Qdrant\Models\Request\RecommendRequest;
use Qdrant\Models\Request\SearchRequest;
use Qdrant\Models\VectorStruct;
use Qdrant\Qdrant;

class QdrantScoutEngine extends Engine
{
    private Qdrant $qdrant;
    private VectorizerEngineManager $vectorizerEngineManager;

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
        $options = array_merge($builder->options, [
            'limit' => $builder->limit ?? 100,
        ]);
        return $this->performSearch($builder, $options);
    }

    public function paginate(Builder $builder, $perPage, $page)
    {
        $options = array_merge($builder->options, [
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ]);
        return $this->performSearch($builder, $options);
    }

    protected function performSearch(Builder $builder, array $options = [])
    {
        $collectionName = $builder->index ?: $builder->model->searchableAs();
        $vectorField = $options['field'] ?? $builder->model->getDefaultVectorField();
        $qdrantRequest = null;
        $filter = new Filter();

        if($builder->query instanceof Model){
            // Create a RecommendRequest
            $qdrantRequest = new RecommendRequest([$builder->query->getScoutKey()]);
            $qdrantRequest->setUsing($vectorField);
        } else {
            // Create a SearchRequest
            $vectorizer = $this->vectorizerEngineManager->driver($builder->model->getVectorizers()[$vectorField] ?? 'openai');
            $embedding = $vectorizer->embedQuery($builder->query);
            $vector = new VectorStruct($embedding, $vectorField);

            $qdrantRequest = new SearchRequest($vector);
        }

        $qdrantRequest->setLimit($options['limit']);

        // Loop through the builder's wheres and add them to the filter
        if(!empty($builder->wheres)){
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
            $qdrantRequest->setFilter($filter);
        }

        if (isset($options['offset'])) {
            $qdrantRequest->setOffset($options['offset']);
        }

        if(isset($options['score_threshold'])) {
            $qdrantRequest->setScoreThreshold($options['score_threshold']);
        }

        if ($builder->callback) {
            $options['qdrantRequest'] = $qdrantRequest;

            return call_user_func(
                $builder->callback,
                $this->qdrant,
                $builder->query,
                $options
            );
        }

        // If the request is a RecommendRequest, call the recommend endpoint, else call the search endpoint
        if($qdrantRequest instanceof RecommendRequest){
            $result = $this->qdrant->collections($collectionName)->points()->recommend($qdrantRequest);
        } else {
            $result = $this->qdrant->collections($collectionName)->points()->search($qdrantRequest);
        }

        return [
            'result' => $result['result'],

            // We need to know the original collection, filters and score_threshold to paginate results
            'limit' => $options['limit'],
            'filter' => $filter,
            'score_threshold' => $options['score_threshold'] ?? null,
            'collection' => $collectionName,

            // This is a workaround for Qdrant not supporting score_threshold in counting
            'request' => $qdrantRequest,
        ];
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

    /**
     * @param $results
     * @return int
     * @throws InvalidArgumentException
     */
    public function getTotalCount($results): int
    {
        // Otherwise, we need to get the total count from Qdrant.
        if(empty($results['score_threshold'])) {
            // We can use core functionality to get the total count
            $countResponse = $this->qdrant
                ->collections($results['collection'])
                ->points()
                ->count($results['filter']);

            return $countResponse['result']['count'];
        }
        else {
            // Because Qdrant doesn't support counting with a score threshold, we need to get all the results and count them ourselves.
            // TODO - Remove this once Qdrant supports counting with a score threshold https://github.com/qdrant/qdrant/issues/2091

            $qdrantRequest = $results['request'];

            // Set a very high limit and an offset of 0 to get all the results
            $qdrantRequest->setLimit(10_000);
            $qdrantRequest->setOffset(0);

            // If the request is a RecommendRequest, call the recommend endpoint, else call the search endpoint
            if($qdrantRequest instanceof RecommendRequest){
                $result = $this->qdrant->collections($results['collection'])->points()->recommend($qdrantRequest);
            } else {
                $result = $this->qdrant->collections($results['collection'])->points()->search($qdrantRequest);
            }

            // Perform the count and return the number of results
            return collect($result['result'])
                ->filter(fn($item) => $item['score'] >= $results['score_threshold'])
                ->count();
        }
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
        $model = $this->getModelForSearchableName($name);
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
     * @param string $name The table name
     * @return Model|null
     * @note This function requires that you run `composer dumpautoload` after creating a new model.
     */
    private function getModelForSearchableName(string $name): ?Model
    {
        static $models = [];
        if (isset($models[$name])) {
            return $models[$name];
        }

        $composer = require base_path() . '/vendor/autoload.php';

        // Define the root namespace and directory for your application
        $rootNamespace = 'App\\Models\\';

        // Additional models defined by the user
        $modelClasses = config('scout-qdrant.models', []);

        $allClasses = collect(array_merge($modelClasses, array_keys($composer->getClassMap())))
            ->filter(fn($class) => str_starts_with($class, $rootNamespace) || in_array($class, $modelClasses))
            ->filter(fn($class) => is_subclass_of($class, 'Illuminate\Database\Eloquent\Model'));

        foreach ($allClasses as $class) {
            // Check if the class is within your application's namespace
            $model = new $class;
            if ( method_exists($model, 'searchableAs') && $model->searchableAs() === $name){
                $models[$name] = $model;
                return $models[$name];
            }
        }

        // No model was found
        return null;
    }
}
