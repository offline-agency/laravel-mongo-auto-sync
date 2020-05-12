<?php

namespace OfflineAgency\MongoAutoSync\Providers;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use src\Console\GenerateModelDocumentation\GenerateModelDocumentation;

/**
 * Service provider.
 */
class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        /*$this->app->singleton(
            'laravel-mongo-auto-sync',
            function ($app) {
                return new ServiceProvider($app);
            }
        );*/

        $this->mergeConfigFrom(__DIR__ . '/.../config/app.php', 'laravel-mongo-auto-sync');
    }

    public function bootForConsole()
    {
        $this->publishes([
            __DIR__ . '/.../config/app.php' => config_path('app.php')
        ], 'config');

        $this->commands([GenerateModelDocumentation::class]);
    }
}
