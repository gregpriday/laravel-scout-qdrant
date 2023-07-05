<?php

namespace GregPriday\LaravelScoutQdrant\Models;

use Laravel\Scout\Builder;
use Qdrant\Exception\InvalidArgumentException;

trait HasRecommendations
{
    /**
     * Gets recommendations using the Qdrant recommend feature.
     *
     * @return Builder
     * @throws InvalidArgumentException
     */
    public function recommended(): Builder
    {
        return static::search($this);
    }
}
