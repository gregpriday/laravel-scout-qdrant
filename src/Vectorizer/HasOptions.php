<?php

namespace GregPriday\LaravelScoutQdrant\Vectorizer;

trait HasOptions
{
    private array $options = [];

    public function setOptions(array $options): static
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    public function getOptions(): array
    {
        return array_merge($this->options, $this->defaultOptions ?? []);
    }

    public function getOption(string $option): mixed
    {
        return $this->getOptions()[$option] ?? null;
    }
}
