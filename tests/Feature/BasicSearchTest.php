<?php

namespace GregPriday\LaravelScoutQdrant\Tests\Feature;

use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Artisan;
use GregPriday\LaravelScoutQdrant\Tests\Models\Article;
use GregPriday\LaravelScoutQdrant\Tests\TestCase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Embeddings\CreateResponse;

class BasicSearchTest extends TestCase
{
    use RefreshDatabase, InteractsWithDatabase, WithFaker;

    // run this before every test
    protected function setUp(): void
    {
        parent::setUp();

        // Refresh the scout index for Brand
        Artisan::call('scout:flush', ['model' => Article::class]);
    }

    public function test_reindexing_document()
    {
        $article = Article::create([
            'title' => 'An Article About Rock Climbing',
            'body' => 'This is an article about rock climbing. You will learn all the basics.',
        ]);

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'title' => 'An Article About Rock Climbing',
            'body' => 'This is an article about rock climbing. You will learn all the basics.',
        ]);

        // Check that the hashes exist
        $this->assertDatabaseHas('vectorization_metadata', [
            'vectorizable_id' => $article->id,
            'vectorizable_type' => Article::class,
            'vectorizer' => 'openai',
            'field_name' => 'title',
            'field_hash' => hash('sha256', $article->title),
        ]);

        $this->assertFalse($article->hasVectorFieldChanged('title', $article->title));
        $this->assertTrue($article->hasVectorFieldChanged('title', $article->title . ' foo...'));

        // Get the current title hash
        $titleHash = $article->getVectorFieldHash('title');
        $bodyHash = $article->getVectorFieldHash('body');

        // Now test reindexing the document
        $article->title = 'A New Title';
        $article->save();

        // Check that only the title hash changed
        $this->assertNotEquals($titleHash, $article->getVectorFieldHash('title'));
        $this->assertEquals($bodyHash, $article->getVectorFieldHash('body'));
    }

    public function test_search_field_vectors()
    {
        $article = Article::create([
            'title' => 'This is an article about space!',
            'body' => 'More specifically, about the Hubble Space Telescope.',
        ]);

        $scoreTitle = Article::search('Hubble Space Telescope')->options(['field' => 'title'])->get()->first()->search_score;
        $scoreBody = Article::search('Hubble Space Telescope')->options(['field' => 'body'])->get()->first()->search_score;

        $this->assertNotEquals($scoreTitle, $scoreBody);
        $this->assertGreaterThan($scoreTitle, $scoreBody);
    }

    /**
     * A basic feature test example.
     */
    public function test_basic_create(): void
    {
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
            ->paginate(10)
            ->pluck('search_score', 'title');

        // Check that the top result is the article about Rome
        $this->assertEquals('The History of Rome', $result->keys()->first());
    }
}
