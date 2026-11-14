<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Filament\Resources\NewsletterSubscribers\Pages;

use Filagora\Newsletters\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewNewsletterSubscriber extends ViewRecord
{
    protected static string $resource = NewsletterSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
