<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Notifications;

use Filagora\Newsletters\Models\NewsletterSubscriber;
use Filagora\Newsletters\Services\Subscribers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Asks someone who used the public signup form to confirm their address with a signed link.
 */
class ConfirmSubscription extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly NewsletterSubscriber $subscriber) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subscribers = app(Subscribers::class);
        $name = (string) config('filagora-newsletters.name');

        return (new MailMessage)
            ->subject(__('filagora-newsletters::messages.confirm.subject', ['name' => $name]))
            ->line(__('filagora-newsletters::messages.confirm.intro', ['name' => $name]))
            ->action(__('filagora-newsletters::messages.confirm.action'), $subscribers->signedConfirmationUrl($this->subscriber))
            ->line(__('filagora-newsletters::messages.confirm.ignore'))
            ->line(__('filagora-newsletters::messages.confirm.unsubscribe', ['url' => $subscribers->signedUnsubscribeUrl($this->subscriber)]));
    }
}
