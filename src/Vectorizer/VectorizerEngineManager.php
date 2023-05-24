<?php

namespace GregPriday\LaravelScoutQdrant\Vectorizer;

use Closure;
use GregPriday\LaravelScoutQdrant\Vectorizer\OpenAIVectorizer;
use Illuminate\Support\Manager;
use InvalidArgumentException;

class VectorizerEngineManager extends Manager
{
    protected function createOpenaiDriver()
    {
        return $this->container->make(OpenAIVectorizer::class);
    }

    public function extend($driver, Closure $callback)
    {
        return $this->drivers[$driver] = $callback($this->app);
    }

    public function getDefaultDriver()
    {
        return config('scout-qdrant.vectorizer');
    }

    public function create($driver)
    {
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        }

        $method = 'create' . ucfirst($driver) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        throw new InvalidArgumentException("Vectorizer driver not supported: {$driver}");
    }
}
