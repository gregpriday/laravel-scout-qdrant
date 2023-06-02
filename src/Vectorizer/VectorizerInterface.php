<?php

namespace GregPriday\LaravelScoutQdrant\Vectorizer;

use Qdrant\Models\Request\VectorParams;

interface VectorizerInterface
{
    public function vectorParams(): VectorParams;
    public function embedDocument(string $document): array;
    public function embedQuery(string $document): array;
}
