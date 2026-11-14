<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Filament\Resources\NewsletterSubscribers\Pages;

use Filagora\Newsletters\Enums\NewsletterSubscriberStatus;
use Filagora\Newsletters\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use Filagora\Newsletters\Services\Subscribers;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsletterSubscriber extends CreateRecord
{
    protected static string $resource = NewsletterSubscriberResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $email = trim((string) ($data['email'] ?? ''));

        $data['email'] = $email;
        $data['normalized_email'] = app(Subscribers::class)->normalizeEmail($email);
        $data['status'] = NewsletterSubscriberStatus::Pending;
        $data['source'] = trim((string) ($data['source'] ?? '')) ?: 'admin';

        return $data;
    }
}
