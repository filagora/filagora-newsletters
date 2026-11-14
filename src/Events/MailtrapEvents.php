<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Events;

use Filagora\Newsletters\Enums\NewsletterEventType;
use Illuminate\Support\Str;

/**
 * Reads Mailtrap's webhook events.
 */
class MailtrapEvents extends ProviderEvents
{
    protected function provider(): string
    {
        return 'mailtrap';
    }

    protected function complaintError(): string
    {
        return 'Mailtrap spam complaint event.';
    }

    protected function newsletterSendId(array $eventData): ?int
    {
        $variables = $eventData['custom_variables'] ?? [];
        $id = is_array($variables) ? ($variables['newsletter_send_id'] ?? null) : null;

        return is_numeric($id) ? (int) $id : null;
    }

    protected function providerMessageId(array $eventData): ?string
    {
        $value = $eventData['message_id'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    protected function recipient(array $eventData): ?string
    {
        $recipient = $eventData['email'] ?? null;

        return is_string($recipient) && trim($recipient) !== '' ? trim($recipient) : null;
    }

    protected function eventType(array $eventData): ?NewsletterEventType
    {
        return match (Str::lower((string) ($eventData['event'] ?? ''))) {
            'delivery' => NewsletterEventType::Delivered,
            'bounce', 'reject' => NewsletterEventType::Bounced,
            'soft bounce' => NewsletterEventType::Failed,
            'spam' => NewsletterEventType::Complained,
            'unsubscribe' => NewsletterEventType::Unsubscribed,
            'open' => NewsletterEventType::Opened,
            'click' => NewsletterEventType::Clicked,
            default => null,
        };
    }

    protected function failureMessage(array $eventData): string
    {
        return (string) ($eventData['response'] ?? 'Mailtrap delivery failure.');
    }

    protected function isPermanentBounce(array $eventData): bool
    {
        return in_array(Str::lower((string) ($eventData['event'] ?? '')), ['bounce', 'reject'], true);
    }
}
