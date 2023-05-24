<?php

namespace GregPriday\LaravelScoutQdrant\Tests\Feature;

use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Artisan;
use GregPriday\LaravelScoutQdrant\Tests\Models\Article;
use GregPriday\LaravelScoutQdrant\Tests\TestCase;

class BasicSearchTest extends TestCase
{
    use RefreshDatabase, InteractsWithDatabase, WithFaker;

    /**
     * A basic feature test example.
     */
    public function test_basic_create(): void
    {
        // Refresh the scout index for Brand
        Artisan::call('scout:flush', ['model' => Article::class]);

        Article::create([
            'title' => 'An Article About Rock Climbing',
            'body' => 'This is an article about rock climbing. You will learn all the basics.',
        ]);

        Article::create([
            'title' => 'The Art of Painting',
            'body' => 'This article covers all you need to know about painting techniques.',
        ]);

        Article::create([
            'title' => 'Exploring Space Travel',
            'body' => 'An article exploring the future of human space travel and colonization.',
        ]);

        Article::create([
            'title' => 'Veganism and its Health Benefits',
            'body' => 'An article detailing the health benefits of a vegan diet.',
        ]);

        Article::create([
            'title' => 'Understanding Quantum Physics',
            'body' => 'A beginner’s guide to quantum physics and its potential applications.',
        ]);

        Article::create([
            'title' => 'The History of Rome',
            'body' => 'An extensive article covering the history of ancient Rome.',
        ]);

        $result = Article::search('how many days did it take to build Rome?')
            ->get()
            ->pluck('search_score', 'title');

        // Check that the top result is the article about Rome
        $this->assertEquals('The History of Rome', $result->keys()->first());
    }
}
