<?php

namespace GregPriday\LaravelScoutQdrant\Models;

use GregPriday\LaravelScoutQdrant\Vectorizer\Manager\VectorizerEngineManager;
use Illuminate\Support\Facades\DB;

trait UsesVectorization
{
    public static function bootUsesVectorization()
    {
        // When the model is deleted we need to delete the vectorization metadata
        static::deleted(function ($model) {
            DB::table('vectorization_metadata')
                ->where('vectorizable_type', get_class($model))
                ->where('vectorizable_id', $model->id)
                ->delete();
        });
    }

    public function getDefaultVectorizer(): string
    {
        return $this->defaultVectorizer ?? config('scout-qdrant.vectorizer', 'openai');
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
            ->where('vectorizer_options', json_encode($vectorizer->getOptions()))
            ->where('field_name', $name)
            ->where('field_hash', '=', hash('sha256', $value))
            ->exists();
    }

    public function setVectorFieldHash($name, $value)
    {
        $vectorizerName = $this->getVectorizers()[$name];
        $vectorizer = app(VectorizerEngineManager::class)->driver($vectorizerName);

        // Delete the old hash data
        DB::table('vectorization_metadata')
            ->where('vectorizable_id', $this->getKey())
            ->where('vectorizable_type', get_class($this))
            ->where('field_name', $name)
            ->delete();

        DB::table('vectorization_metadata')
            ->insert([
                'vectorizable_id' => $this->getKey(),
                'vectorizable_type' => get_class($this),
                'vectorizer' => $vectorizerName,
                'vectorizer_options' => json_encode($vectorizer->getOptions()),
                'field_name' => $name,
                'field_hash' => hash('sha256', $value),
            ]);
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
