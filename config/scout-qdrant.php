<?php

// config for GregPriday/LaravelScoutQdrant
return [
    'qdrant' => [
        'host' => env('QDRANT_HOST', 'http://localhost'),
        'key' => env('QDRANT_API_KEY', null),
    ],
    'vectorizer' => env('QDRANT_VECTORIZER', 'openai'),
];
