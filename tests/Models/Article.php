<?php

namespace GregPriday\LaravelScoutQdrant\Tests\Models;

use GregPriday\LaravelScoutQdrant\Models\UsesVectorization;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Article extends Model
{
    use Searchable, UsesVectorization;

    protected $fillable = [
        'title',
        'body',
    ];

    protected $vectorizers = [
        'title' => 'openai',
        'document' => 'openai'
    ];

    public function toSearchableArray()
    {
        return [
            'title' => $this->title,
            'document' => '#' . $this->title . "\n\n" . $this->body,
        ];
    }
}
