<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Events;

use Filagora\Newsletters\Enums\NewsletterEventType;
use Illuminate\Support\Str;

/**
 * Reads Mailgun's webhook events.
 */
class MailgunEvents extends ProviderEvents
{
    protected function provider(): string
    {
        return 'mailgun';
    }

    protected function complaintError(): string
    {
        return 'Mailgun complaint event.';
    }

    protected function newsletterSendId(array $eventData): ?int
    {
        $variables = $eventData['user-variables'] ?? $eventData['user_variables'] ?? [];

        if (is_string($variables)) {
            $decoded = json_decode($variables, true);
            $variables = is_array($decoded) ? $decoded : [];
        }

        $id = is_array($variables) ? ($variables['newsletter_send_id'] ?? null) : null;

        return is_numeric($id) ? (int) $id : null;
    }

    protected function providerMessageId(array $eventData): ?string
    {
        $value = $eventData['message']['headers']['message-id']
            ?? $eventData['message']['headers']['message_id']
            ?? $eventData['Message-Id']
            ?? $eventData['message-id']
            ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value, '<> ') : null;
    }

    protected function recipient(array $eventData): ?string
    {
        $recipient = $eventData['recipient'] ?? null;

        return is_string($recipient) && trim($recipient) !== '' ? trim($recipient) : null;
    }

    protected function eventType(array $eventData): ?NewsletterEventType
    {
        $event = Str::lower((string) ($eventData['event'] ?? ''));
        $reason = Str::lower((string) ($eventData['reason'] ?? ''));

        return match ($event) {
            'delivered' => NewsletterEventType::Delivered,
            'failed' => str_contains($reason, 'bounce') ? NewsletterEventType::Bounced : NewsletterEventType::Failed,
            'complained' => NewsletterEventType::Complained,
            'unsubscribed' => NewsletterEventType::Unsubscribed,
            'opened' => NewsletterEventType::Opened,
            'clicked' => NewsletterEventType::Clicked,
            default => null,
        };
    }

    protected function failureMessage(array $eventData): string
    {
        return (string) ($eventData['delivery-status']['message'] ?? $eventData['reason'] ?? 'Mailgun delivery failure.');
    }

    protected function isPermanentBounce(array $eventData): bool
    {
        return ($eventData['severity'] ?? null) === 'permanent';
    }
}
