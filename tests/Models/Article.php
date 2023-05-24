<?php

namespace GregPriday\LaravelScoutQdrant\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Article extends Model
{
    use Searchable;

    protected $fillable = [
        'title',
        'body',
    ];

    public function toSearchableArray()
    {
        return [
            'vector' => '#' . $this->title . "\n\n" . $this->body,
        ];
    }
}
