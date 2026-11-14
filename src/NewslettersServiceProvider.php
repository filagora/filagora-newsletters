<?php

declare(strict_types=1);

namespace Filagora\Newsletters;

use Filagora\Core\Modules\Module;
use Filagora\Core\Modules\ModuleRegistry;
use Filagora\Core\Schema\PackageSchema;
use Filagora\Core\Schema\SchemaRegistry;
use Filagora\Newsletters\Console\ExportSubscribersCommand;
use Filagora\Newsletters\Console\QueueScheduledCommand;
use Filagora\Newsletters\Contracts\NewsletterMedia;
use Filagora\Newsletters\Media\NoMedia;
use Filagora\Newsletters\Services\BrandingSettings;
use Filagora\Newsletters\Services\CampaignMailer;
use Filagora\Newsletters\Services\CampaignSender;
use Filagora\Newsletters\Services\ContentRenderer;
use Filagora\Newsletters\Services\DeliveryEvents;
use Filagora\Newsletters\Services\DeliveryTransport;
use Filagora\Newsletters\Services\LinkTracker;
use Filagora\Newsletters\Services\Subscribers;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class NewslettersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filagora-newsletters.php', 'filagora-newsletters');

        $this->app->bindIf(NewsletterMedia::class, NoMedia::class);
        $this->app->singleton(Subscribers::class);
        $this->app->singleton(LinkTracker::class);
        $this->app->singleton(ContentRenderer::class);
        $this->app->singleton(BrandingSettings::class);
        $this->app->singleton(DeliveryTransport::class);
        $this->app->singleton(DeliveryEvents::class);
        $this->app->singleton(CampaignSender::class);
        $this->app->singleton(CampaignMailer::class);
    }

    public function boot(): void
    {
        $this->app->make(ModuleRegistry::class)->register(
            new Module('newsletters', 'Filagora Newsletters', 'filagora/filagora-newsletters', requires: ['core']),
        );

        $this->app->make(SchemaRegistry::class)->register(
            PackageSchema::fromFile('newsletters', __DIR__.'/../database/schema.php', __DIR__.'/../database/migrations'),
        );

        RateLimiter::for('newsletter-signup', fn (Request $request): Limit => Limit::perMinute((int) config('filagora-newsletters.routes.signup_per_minute', 5))
            ->by('newsletter-signup:'.($request->ip() ?: 'unknown')));

        if (config('filagora-newsletters.routes.enabled', false)) {
            Route::prefix((string) config('filagora-newsletters.routes.prefix', 'newsletter'))
                ->middleware((array) config('filagora-newsletters.routes.middleware', ['web']))
                ->group(__DIR__.'/../routes/newsletter.php');
        }

        if (config('filagora-newsletters.schedule.enabled', true)) {
            $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $schedule->command('filagora:newsletters:queue-scheduled')->everyMinute()->withoutOverlapping());
        }

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filagora-newsletters');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filagora-newsletters');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([QueueScheduledCommand::class, ExportSubscribersCommand::class]);

            $this->publishes([
                __DIR__.'/../config/filagora-newsletters.php' => config_path('filagora-newsletters.php'),
            ], 'filagora-newsletters-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'filagora-newsletters-migrations');
        }
    }
}
