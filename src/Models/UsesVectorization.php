<?php

namespace GregPriday\LaravelScoutQdrant\Models;

use GregPriday\LaravelScoutQdrant\Vectorizer\VectorizerEngineManager;
use Illuminate\Support\Facades\DB;

trait UsesVectorization
{
    public static function bootUsesVectorization()
    {
        // When the model is deleted we need to delete the vectorization metadata
        static::deleted(function ($model) {
            DB::table('vectorization_metadata')
                ->where('model', get_class($model))
                ->where('model_id', $model->id)
                ->delete();
        });
    }

    public function getDefaultVectorizer(): string
    {
        return $this->defaultVectorizer ?? config('scout-qdrant.vectorizer') ?? 'openai';
    }

    public function getDefaultVectorField(): string
    {
        return $this->defaultVectorField ?? array_key_first($this->getVectorizers());
    }

    public function getVectorizers(): array
    {
        return $this->vectorizers ?? [
            'document' => $this->getDefaultVectorizer(),
        ];
    }

    public function hasVectorFieldChanged($name, $value): bool
    {
        $vectorizerName = $this->getVectorizers()[$name];
        $vectorizer = app(VectorizerEngineManager::class)->driver($vectorizerName);

        return ! DB::table('vectorization_metadata')
            ->where('vectorizable_id', $this->getKey())
            ->where('vectorizable_type', get_class($this))
            ->where('vectorizer', $vectorizerName)
            ->where('vectorizer_version', $vectorizer->version())
            ->where('field_name', $name)
            ->where('field_hash', '=', hash('sha256', $value))
            ->exists();
    }

    public function setVectorFieldHash($name, $value)
    {
        $vectorizerName = $this->getVectorizers()[$name];
        $vectorizer = app(VectorizerEngineManager::class)->driver($vectorizerName);

        DB::table('vectorization_metadata')
            ->updateOrInsert(
                [
                    'vectorizable_id' => $this->getKey(),
                    'vectorizable_type' => get_class($this),
                    'vectorizer' => $vectorizerName,
                    'vectorizer_version' => $vectorizer->version(),
                    'field_name' => $name,
                ],
                [
                    'field_hash' => hash('sha256', $value),
                ]
            );
    }

    public function getVectorFieldHash($name): string|null
    {
        return DB::table('vectorization_metadata')
            ->where('vectorizable_id', $this->getKey())
            ->where('vectorizable_type', get_class($this))
            ->where('vectorizer', $this->getVectorizers()[$name])
            ->where('field_name', $name)
            ->value('field_hash');
    }
}
