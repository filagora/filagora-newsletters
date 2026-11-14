<?php

declare(strict_types=1);

namespace Filagora\Newsletters;

use Filagora\Core\Modules\Module;
use Filagora\Core\Modules\ModuleRegistry;
use Filagora\Core\Schema\PackageSchema;
use Filagora\Core\Schema\SchemaRegistry;
use Illuminate\Support\ServiceProvider;

class NewslettersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filagora-newsletters.php', 'filagora-newsletters');
    }

    public function boot(): void
    {
        $this->app->make(ModuleRegistry::class)->register(
            new Module('newsletters', 'Filagora Newsletters', 'filagora/filagora-newsletters', requires: ['core']),
        );

        $this->app->make(SchemaRegistry::class)->register(
            PackageSchema::fromFile('newsletters', __DIR__.'/../database/schema.php', __DIR__.'/../database/migrations'),
        );

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filagora-newsletters');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filagora-newsletters');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/filagora-newsletters.php' => config_path('filagora-newsletters.php'),
            ], 'filagora-newsletters-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'filagora-newsletters-migrations');
        }
    }
}
