<?php

namespace GregPriday\LaravelScoutQdrant\Vectorizer\Manager;

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

    public function driver($driver = null)
    {
        // If there is a '::' in the name, then we consider everything after that JSON encoded options
        $options = [];
        if (str_contains($driver, '::')) {
            [$driver, $options] = explode('::', $driver, 2);
            $options = json_decode($options, true);
        }

        return parent::driver($driver)->setOptions($options);
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
