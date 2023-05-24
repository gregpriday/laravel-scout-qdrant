<?php

namespace GregPriday\LaravelScoutQdrant\Vectorizer;

interface VectorizerInterface
{
    public function embedDocument(string $document): array;
    public function embedQuery(string $document): array;
}
