<?php

declare(strict_types=1);

namespace Filagora\Newsletters;

use Filagora\Newsletters\Filament\Resources\NewsletterBlocks\NewsletterBlockResource;
use Filagora\Newsletters\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

class NewslettersPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filagora-newsletters';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            NewsletterBlockResource::class,
            NewsletterSubscriberResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}
}
