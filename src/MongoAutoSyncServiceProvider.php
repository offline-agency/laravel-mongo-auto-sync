<?php

namespace OfflineAgency\MongoAutoSync;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use OfflineAgency\MongoAutoSync\Console\AnalyseDatabase;
use OfflineAgency\MongoAutoSync\Console\BenchmarkCommand;
use OfflineAgency\MongoAutoSync\Console\CheckModelConfig;
use OfflineAgency\MongoAutoSync\Console\DropCollection;
use OfflineAgency\MongoAutoSync\Console\GenerateModelDocumentation;
use OfflineAgency\MongoAutoSync\Console\SyncCollection;

/**
 * Service provider.
 */
class MongoAutoSyncServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            $this->packagePath('config/laravel-mongo-auto-sync.php') => config_path('laravel-mongo-auto-sync.php'),
        ], 'config');

        $this->app->bind('command.model-doc:generate', GenerateModelDocumentation::class);
        $this->app->bind('command.drop:collection', DropCollection::class);
        $this->app->bind('command.mongo-sync:analyse', AnalyseDatabase::class);
        $this->app->bind('command.mongo-sync:check-config', CheckModelConfig::class);
        $this->app->bind('command.mongo-sync:sync-collection', SyncCollection::class);
        $this->app->bind('command.mongo-sync:benchmark', BenchmarkCommand::class);

        $this->commands([
            'command.model-doc:generate',
            'command.drop:collection',
            'command.mongo-sync:analyse',
            'command.mongo-sync:check-config',
            'command.mongo-sync:sync-collection',
            'command.mongo-sync:benchmark',
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            $this->packagePath('config/laravel-mongo-auto-sync.php'),
            'laravel-mongo-auto-sync'
        );
    }

    /**
     * @param  string  $path
     * @return string
     */
    private function packagePath($path)
    {
        return __DIR__.'/../'.$path;
    }
}
