<?php

// config for GregPriday/LaravelScoutQdrant

return [
    'qdrant' => [
        'host' => env('QDRANT_HOST', 'http://localhost'),
        'key' => env('QDRANT_API_KEY', null),
        'storage' => env('QDRANT_STORAGE', 'database/qdrant'),
    ],
    'vectorizer' => env('QDRANT_VECTORIZER', 'openai'),

    // Add full model classnames for any classes that exist outside the App\Models namespace.
    'models' => [],
];
