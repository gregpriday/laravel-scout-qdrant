<?php

namespace GregPriday\LaravelScoutQdrant\Vectorizer;

use OpenAI\Client;
use OpenAI\Laravel\Facades\OpenAI;
use Qdrant\Models\Request\VectorParams;

class OpenAIVectorizer implements VectorizerInterface
{
    use HasOptions;

    private array $defaultOptions = [
        'model' => 'text-embedding-ada-002',
    ];

    static array $vectorDimensions = [
        'text-embedding-ada-002' => 1536,
    ];

    public function vectorParams(): VectorParams
    {
        return new VectorParams(
            static::$vectorDimensions[$this->getOption('model')],
            VectorParams::DISTANCE_COSINE
        );
    }

    public function embedDocument(string $document): array
    {
        $response = OpenAI::embeddings()->create([
            'model' => $this->getOption('model'),
            'input' => $document,
        ]);

        return $response->embeddings[0]->embedding;
    }

    public function embedQuery(string $query): array
    {
        return $this->embedDocument($query);
    }
}
