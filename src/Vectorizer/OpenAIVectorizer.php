<?php

namespace GregPriday\LaravelScoutQdrant\Vectorizer;

use OpenAI\Client;
use OpenAI\Laravel\Facades\OpenAI;
use Qdrant\Models\Request\VectorParams;

class OpenAIVectorizer implements VectorizerInterface
{
    public function vectorParams(): VectorParams
    {
        return new VectorParams(1536, VectorParams::DISTANCE_COSINE);
    }

    public function embedDocument(string $document): array
    {
        $response = OpenAI::embeddings()->create([
            'model' => 'text-embedding-ada-002',
            'input' => $document,
        ]);

        return $response->embeddings[0]->embedding;
    }

    public function embedQuery(string $query): array
    {
        return $this->embedDocument($query);
    }

    public function version(): string
    {
        return 'text-embedding-ada-002';
    }
}
