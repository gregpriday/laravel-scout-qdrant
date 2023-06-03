<?php

namespace GregPriday\LaravelScoutQdrant\Models;

interface Vectorizable
{
    /**
     * @return array An array of field => vectorizer pairs.
     */
    public function getVectorizers(): array;

    public function getDefaultVectorField(): string;
}
