<?php

namespace GregPriday\LaravelScoutQdrant\Models;

interface Vectorizable
{
    /**
     * @return string The default vectorizer to use for this model.
     */
    public function getDefaultVectorizer(): string;

    /**
     * @return string The default field to use for search.
     */
    public function getDefaultVectorField(): string;

    /**
     * @return array An array of field => vectorizer pairs.
     */
    public function getVectorizers(): array;

    /**
     * @param string $field
     * @return string The vectorizer to use for the given field.
     */
    public function getVectorizer(string $field): string;
}
