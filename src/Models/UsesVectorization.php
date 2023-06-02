<?php

namespace GregPriday\LaravelScoutQdrant\Models;

trait UsesVectorization
{
    public function getDefaultVectorizer()
    {
        return $this->defaultVectorizer ?? config('scout-qdrant.vectorizer');
    }

    public function getDefaultVectorField()
    {
        // First try get the default vector field from the model  $this->defaultVectorField
        // Then try get the default vector field from the config scout-qdrant.vector_field
        // Then try get it from getVectorizers (the first key)
        // If all that fails, return 'document'
        return $this->defaultVectorField ?? config('scout-qdrant.vector_field') ?? array_key_first($this->getVectorizers()) ?? 'document';
    }

    public function getVectorizers(): array
    {
        return $this->vectorizers ?? [];
    }

    public function getVectorizer(string $field): string
    {
        return $this->getVectorizers()[$field] ?? $this->getDefaultVectorizer();
    }
}
