<?php

namespace OfflineAgency\MongoAutoSync;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
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

        $this->commands([
            'command.model-doc:generate'
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
        return __DIR__ . '/../' .  $path;
    }
}
