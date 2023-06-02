<?php

namespace GregPriday\LaravelScoutQdrant\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Laravel\Scout\ScoutServiceProvider;
use OpenAI\Laravel\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use GregPriday\LaravelScoutQdrant\LaravelScoutQdrantServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();


    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelScoutQdrantServiceProvider::class,
            ScoutServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('scout.driver', 'qdrant');

        config()->set('openai.api_key', env('OPENAI_API_KEY'));
        config()->set('openai.organization', env('OPENAI_ORGANIZATION'));

        $migration = include __DIR__.'/migrations/create_article_table.php';
        $migration->up();

        $migration = include __DIR__.'/../database/migrations/create_vectorization_metadata_table.php';
        $migration->up();
    }
}
