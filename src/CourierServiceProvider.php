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
        $this->loadRoutesFrom(__DIR__.'/../routes/webhook.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/courier.php' => config_path('courier.php'),
            ], 'courier-config');

            $this->publishes([
                __DIR__.'/../database/migrations/2026_07_22_000001_create_courier_api_logs_table.php' => database_path('migrations/2026_07_22_000001_create_courier_api_logs_table.php'),
                __DIR__.'/../database/migrations/2026_07_22_000002_create_courier_webhook_logs_table.php' => database_path('migrations/2026_07_22_000002_create_courier_webhook_logs_table.php'),
            ], 'courier-migrations');

            $this->commands([
                \Laraditz\Courier\Console\Commands\PruneCourierLogsCommand::class,
            ]);
        }
    }
}
