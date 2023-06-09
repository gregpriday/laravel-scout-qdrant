<?php

namespace GregPriday\LaravelScoutQdrant;

use GregPriday\LaravelScoutQdrant\Commands\QdrantInstallCommand;
use GregPriday\LaravelScoutQdrant\Commands\QdrantRestartCommand;
use GregPriday\LaravelScoutQdrant\Commands\QdrantStartCommand;
use GregPriday\LaravelScoutQdrant\Commands\QdrantStatusCommand;
use GregPriday\LaravelScoutQdrant\Commands\QdrantStopCommand;
use GregPriday\LaravelScoutQdrant\Commands\QdrantUpdateCommand;
use GregPriday\LaravelScoutQdrant\Scout\QdrantScoutEngine;
use GregPriday\LaravelScoutQdrant\Vectorizer\Manager\VectorizerEngineManager;
use Laravel\Scout\EngineManager;
use Qdrant\Config;
use Qdrant\Http\GuzzleClient;
use Qdrant\Qdrant;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelScoutQdrantServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-scout-qdrant')
            ->hasConsoleCommands([
                QdrantInstallCommand::class,
                QdrantRestartCommand::class,
                QdrantStartCommand::class,
                QdrantStatusCommand::class,
                QdrantStopCommand::class,
                QdrantUpdateCommand::class,
            ])
            ->hasConfigFile('scout-qdrant')
            ->hasMigration('create_vectorization_metadata_table');
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
        resolve(EngineManager::class)->extend('qdrant', function ($app) {
            return new QdrantScoutEngine(
                app(Qdrant::class),
                app(VectorizerEngineManager::class)
            );
        });
    }
}
