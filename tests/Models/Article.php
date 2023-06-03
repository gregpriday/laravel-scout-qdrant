<?php

namespace GregPriday\LaravelScoutQdrant\Tests\Models;

use GregPriday\LaravelScoutQdrant\Models\UsesVectorization;
use GregPriday\LaravelScoutQdrant\Models\Vectorizable;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Article extends Model implements Vectorizable
{
    use Searchable, UsesVectorization;

    protected $fillable = [
        'title',
        'body',
    ];

    protected $vectorizers = [
        'title' => 'openai',
        'body' => 'openai',
    ];

    public function toSearchableArray(): array
    {
        return $this->toArray();
    }
}
