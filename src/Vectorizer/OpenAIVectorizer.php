<?php

namespace GregPriday\LaravelScoutQdrant\Vectorizer;

use OpenAI\Client;
use Qdrant\Models\Request\VectorParams;

class OpenAIVectorizer implements VectorizerInterface
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function vectorParams(): VectorParams
    {
        return new VectorParams(1536, VectorParams::DISTANCE_COSINE);
    }

    public function embedDocument(string $document): array
    {
        $response = $this->client->embeddings()->create([
            'model' => 'text-embedding-ada-002',
            'input' => $document,
        ]);

        return $response->embeddings[0]->embedding;
    }

    public function embedQuery(string $query): array
    {
        return $this->embedDocument($query);
    }
}
