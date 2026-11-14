<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Services;

use Filagora\Newsletters\Enums\NewsletterSendStatus;
use Filagora\Newsletters\Enums\NewsletterStatus;
use Filagora\Newsletters\Mail\CampaignMail;
use Filagora\Newsletters\Models\Newsletter;
use Filagora\Newsletters\Support\Models;
use Throwable;

class CampaignMailer
{
    public function __construct(
        private readonly DeliveryTransport $transport,
    ) {}

    public function sendPreview(Newsletter $newsletter, string $email): void
    {
        $this->transport->send($email, new CampaignMail(
            newsletter: $newsletter,
            unsubscribeUrl: route('newsletter.show'),
            preview: true,
            deliveryProvider: $this->transport->provider(),
        ));
    }

    public function sendCampaignRecipient(int $newsletterSendId): void
    {
        $send = Models::class('send')::query()
            ->with(['newsletter', 'subscriber'])
            ->findOrFail($newsletterSendId);

        if ($send->status === NewsletterSendStatus::Sent) {
            return;
        }

        $subscriber = $send->subscriber;
        if ($subscriber === null || ! $subscriber->isSendable() || $subscriber->suppression_hash !== null) {
            $send->fill([
                'status' => NewsletterSendStatus::Skipped,
                'skipped_at' => now(),
                'error' => 'Subscriber is no longer sendable.',
            ]);
            $send->save();
            $this->completeNewsletterIfReady($send->newsletter_id);

            return;
        }

        $send->fill([
            'status' => NewsletterSendStatus::Sending,
            'error' => null,
        ]);
        $send->save();

        try {
            $this->transport->send($send->email, new CampaignMail(
                newsletter: $send->newsletter,
                unsubscribeUrl: app(Subscribers::class)->signedUnsubscribeUrl($subscriber),
                newsletterSendId: $send->id,
                deliveryProvider: $this->transport->provider(),
            ));

            $send->fill([
                'status' => NewsletterSendStatus::Sent,
                'sent_at' => now(),
                'failed_at' => null,
                'error' => null,
            ]);
            $send->save();
            $this->completeNewsletterIfReady($send->newsletter_id);
        } catch (Throwable $throwable) {
            $send->fill([
                'status' => NewsletterSendStatus::Failed,
                'failed_at' => now(),
                'error' => $throwable->getMessage(),
            ]);
            $send->save();
            $this->completeNewsletterIfReady($send->newsletter_id);

            throw $throwable;
        }
    }

    private function completeNewsletterIfReady(int $newsletterId): void
    {
        $newsletter = Models::class('newsletter')::query()->find($newsletterId);
        if ($newsletter === null) {
            return;
        }

        $hasPendingSends = $newsletter->sends()
            ->whereIn('status', [
                NewsletterSendStatus::Queued->value,
                NewsletterSendStatus::Sending->value,
            ])
            ->exists();

        if ($hasPendingSends) {
            return;
        }

        $newsletter->fill([
            'status' => NewsletterStatus::Sent,
            'sent_at' => $newsletter->sent_at ?? now(),
        ]);
        $newsletter->save();
    }
}
