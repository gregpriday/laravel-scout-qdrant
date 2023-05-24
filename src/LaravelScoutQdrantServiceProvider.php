<?php

namespace GregPriday\LaravelScoutQdrant;

use GregPriday\LaravelScoutQdrant\Scout\QdrantScoutEngine;
use GregPriday\LaravelScoutQdrant\Vectorizer\VectorizerEngineManager;
use Laravel\Scout\EngineManager;
use OpenAI\Client;
use Qdrant\Config;
use Qdrant\Http\GuzzleClient;
use Qdrant\Qdrant;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelScoutQdrantServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-scout-qdrant')
            ->hasConfigFile('scout-qdrant');
    }

    public function packageRegistered()
    {
        $this->app->singleton(Qdrant::class, function () {
            $key = config('scout-qdrant.qdrant.key');

            $config = new Config(config('scout-qdrant.qdrant.host'));
            if($key) {
                $config->setApiKey($key);
            }

            return new Qdrant(new GuzzleClient($config));
        });

        $this->app->singleton(VectorizerEngineManager::class, function ($app) {
            return new VectorizerEngineManager($app);
        });
    }

    public function packageBooted()
    {
        resolve(EngineManager::class)->extend('qdrant', function () {
            return new QdrantScoutEngine(
                app(Qdrant::class),
                app(VectorizerEngineManager::class)->driver()
            );
        });
    }
}
