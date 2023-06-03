<?php

namespace GregPriday\LaravelScoutQdrant\Vectorizer;

use Qdrant\Models\Request\VectorParams;

interface VectorizerInterface
{
    public function vectorParams(): VectorParams;
    public function embedDocument(string $document): array;
    public function embedQuery(string $document): array;

    public function setOptions(array $options);
    public function getOptions(): array;
    public function getOption(string $option): mixed;
}
