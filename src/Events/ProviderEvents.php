<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Events;

use Carbon\CarbonInterface;
use Filagora\Newsletters\Contracts\EventHandler;
use Filagora\Newsletters\Enums\NewsletterEventType;
use Filagora\Newsletters\Enums\NewsletterSendStatus;
use Filagora\Newsletters\Enums\NewsletterSubscriberStatus;
use Filagora\Newsletters\Models\NewsletterSend;
use Filagora\Newsletters\Services\Subscribers;
use Filagora\Newsletters\Support\Models;
use Illuminate\Support\Str;

/**
 * What every provider's handler does once it has read the provider's event: find the send, keep
 * an event row, and update the send and the subscriber. A provider's class only says how to read
 * its own event shape.
 */
abstract class ProviderEvents implements EventHandler
{
    /**
     * The provider's short name, used in the reasons kept on a subscriber ("mailgun_bounce").
     */
    abstract protected function provider(): string;

    /**
     * What the send's error says for a complaint.
     */
    abstract protected function complaintError(): string;

    /**
     * @param  array<string, mixed>  $eventData
     */
    abstract protected function newsletterSendId(array $eventData): ?int;

    /**
     * @param  array<string, mixed>  $eventData
     */
    abstract protected function providerMessageId(array $eventData): ?string;

    /**
     * @param  array<string, mixed>  $eventData
     */
    abstract protected function recipient(array $eventData): ?string;

    /**
     * @param  array<string, mixed>  $eventData
     */
    abstract protected function eventType(array $eventData): ?NewsletterEventType;

    /**
     * What the send's error says for a failure.
     *
     * @param  array<string, mixed>  $eventData
     */
    abstract protected function failureMessage(array $eventData): string;

    /**
     * Whether a failure is a permanent bounce, which suppresses the subscriber.
     *
     * @param  array<string, mixed>  $eventData
     */
    abstract protected function isPermanentBounce(array $eventData): bool;

    public function handle(array $eventData): ?string
    {
        $send = $this->resolveSend($eventData);

        if (! $send instanceof NewsletterSend) {
            return null;
        }

        $eventType = $this->eventType($eventData);

        if ($eventType instanceof NewsletterEventType) {
            Models::class('event')::query()->create([
                'newsletter_send_id' => $send->id,
                'type' => $eventType,
                'occurred_at' => $this->occurredAt($eventData),
                'payload' => $eventData,
            ]);
        }

        $providerMessageId = $this->providerMessageId($eventData);

        if ($providerMessageId !== null && $send->provider_message_id === null) {
            $send->provider_message_id = $providerMessageId;
        }

        match ($eventType) {
            NewsletterEventType::Delivered => $this->markDelivered($send),
            NewsletterEventType::Failed, NewsletterEventType::Bounced => $this->markFailed($send, $eventData),
            NewsletterEventType::Complained => $this->markComplained($send),
            NewsletterEventType::Unsubscribed => $this->markUnsubscribed($send),
            default => $send->save(),
        };

        return 'Newsletter event recorded.';
    }

    /**
     * The send an event is about: by the send id we tagged the mail with, else by the provider's
     * message id, else by the recipient's most recent send still in flight or sent.
     *
     * @param  array<string, mixed>  $eventData
     */
    private function resolveSend(array $eventData): ?NewsletterSend
    {
        $newsletterSendId = $this->newsletterSendId($eventData);

        if ($newsletterSendId !== null) {
            return Models::class('send')::query()->find($newsletterSendId);
        }

        $providerMessageId = $this->providerMessageId($eventData);

        if ($providerMessageId !== null) {
            $send = Models::class('send')::query()
                ->where('provider_message_id', $providerMessageId)
                ->latest('id')
                ->first();

            if ($send instanceof NewsletterSend) {
                return $send;
            }
        }

        $recipient = $this->recipient($eventData);

        if ($recipient === null) {
            return null;
        }

        return Models::class('send')::query()
            ->where('normalized_email', Str::lower($recipient))
            ->whereIn('status', [
                NewsletterSendStatus::Queued->value,
                NewsletterSendStatus::Sending->value,
                NewsletterSendStatus::Sent->value,
            ])
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    private function occurredAt(array $eventData): CarbonInterface
    {
        $timestamp = $eventData['timestamp'] ?? null;

        return is_numeric($timestamp) ? now()->setTimestamp((int) $timestamp) : now();
    }

    private function markDelivered(NewsletterSend $send): void
    {
        $send->fill([
            'status' => NewsletterSendStatus::Sent,
            'sent_at' => $send->sent_at ?? now(),
            'failed_at' => null,
            'error' => null,
        ]);
        $send->save();
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    private function markFailed(NewsletterSend $send, array $eventData): void
    {
        $send->fill([
            'status' => NewsletterSendStatus::Failed,
            'failed_at' => now(),
            'error' => $this->failureMessage($eventData),
        ]);
        $send->save();

        if ($this->isPermanentBounce($eventData)) {
            $subscriber = $send->subscriber;

            if ($subscriber !== null) {
                $subscriber->fill([
                    'status' => NewsletterSubscriberStatus::Bounced,
                    'bounced_at' => $subscriber->bounced_at ?? now(),
                    'unsubscribe_reason' => $this->provider().'_bounce',
                ]);
                $subscriber->save();
            }
        }
    }

    private function markComplained(NewsletterSend $send): void
    {
        $send->fill([
            'status' => NewsletterSendStatus::Failed,
            'failed_at' => now(),
            'error' => $this->complaintError(),
        ]);
        $send->save();

        $subscriber = $send->subscriber;

        if ($subscriber !== null) {
            $subscriber->fill([
                'status' => NewsletterSubscriberStatus::Complained,
                'complained_at' => $subscriber->complained_at ?? now(),
                'unsubscribe_reason' => $this->provider().'_complaint',
            ]);
            $subscriber->save();
        }
    }

    private function markUnsubscribed(NewsletterSend $send): void
    {
        $send->save();

        $subscriber = $send->subscriber;

        if ($subscriber !== null) {
            app(Subscribers::class)->unsubscribe($subscriber, $this->provider().'_unsubscribe');
        }
    }
}
