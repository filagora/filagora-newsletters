<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Privacy;

use Filagora\Core\Contracts\PrivacyContributor;
use Filagora\Newsletters\Models\NewsletterEvent;
use Filagora\Newsletters\Models\NewsletterLinkClick;
use Filagora\Newsletters\Models\NewsletterSend;
use Filagora\Newsletters\Models\NewsletterSubscriber;
use Filagora\Newsletters\Services\Subscribers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * A person's subscriptions and what was sent to them, for their data export and erasure. It covers
 * addresses their account had before, too. Erasure keeps a hash of each address, so it is not
 * silently added to the list again.
 */
final class NewslettersDataContributor implements PrivacyContributor
{
    public function __construct(private readonly Subscribers $subscribers) {}

    public function key(): string
    {
        return 'newsletters';
    }

    public function export(Model $user): array
    {
        $subscriptions = $this->subscribers->allFor($user);

        return [
            'subscriptions' => $subscriptions->map(fn (NewsletterSubscriber $row): array => [
                'email' => $row->email,
                'name' => $row->name,
                'status' => $row->status?->value,
                'source' => $row->source,
                'subscribed_at' => $row->subscribed_at?->toIso8601String(),
                'confirmed_at' => $row->confirmed_at?->toIso8601String(),
                'unsubscribed_at' => $row->unsubscribed_at?->toIso8601String(),
                'unsubscribe_reason' => $row->unsubscribe_reason,
                'consent_text' => $row->consent_text,
                'consent_version' => $row->consent_version,
                'consent_ip' => $row->consent_ip,
                'consent_user_agent' => $row->consent_user_agent,
                'metadata' => Arr::except($row->metadata ?? [], Subscribers::FORMER_USER),
            ])->values()->all(),
            'deliveries' => $this->subscribers->deliveriesFor($user, $subscriptions)
                ->load(['newsletter', 'events', 'linkClicks'])
                ->map(fn (NewsletterSend $send): array => [
                    'newsletter' => $send->newsletter?->subject,
                    'email' => $send->email,
                    'name' => $send->name,
                    'status' => $send->status->value,
                    'sent_at' => $send->sent_at?->toIso8601String(),
                    'events' => $send->events->map(fn (NewsletterEvent $event): array => [
                        'type' => $event->type->value,
                        'occurred_at' => $event->occurred_at->toIso8601String(),
                    ])->values()->all(),
                    'clicks' => $send->linkClicks->map(fn (NewsletterLinkClick $click): array => [
                        'url' => $click->destination_url,
                        'clicked_at' => $click->clicked_at->toIso8601String(),
                    ])->values()->all(),
                ])->values()->all(),
        ];
    }

    public function erase(Model $user): void
    {
        $this->subscribers->anonymizeForErasure($user);
    }
}
