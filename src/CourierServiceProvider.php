<?php

namespace Laraditz\Courier;

use Illuminate\Support\ServiceProvider;

class CourierServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/courier.php', 'courier');

        $this->app->singleton('courier', fn ($app) => new CourierManager($app));
        $this->app->alias('courier', CourierManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/courier.php' => config_path('courier.php'),
            ], 'courier-config');
        }
    }
}
