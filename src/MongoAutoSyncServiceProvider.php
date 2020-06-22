<?php

namespace OfflineAgency\MongoAutoSync;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use OfflineAgency\MongoAutoSync\Console\DropCollection;
use OfflineAgency\MongoAutoSync\Console\GenerateModelDocumentation;

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
        $this->app->bind('command.model-doc:generate', GenerateModelDocumentation::class);
        $this->app->bind('command.drop:collection', DropCollection::class);

        $this->commands([
            'command.model-doc:generate',
        ]);

        $this->commands([
            'command.drop:collection',
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
     * @param $path
     * @return string
     */
    private function packagePath($path)
    {
        return __DIR__.'/../'.$path;
    }
}
