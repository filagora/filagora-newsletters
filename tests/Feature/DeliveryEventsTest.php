<?php

declare(strict_types=1);

use Filagora\Newsletters\Enums\NewsletterEventType;
use Filagora\Newsletters\Enums\NewsletterSendStatus;
use Filagora\Newsletters\Enums\NewsletterSubscriberStatus;
use Filagora\Newsletters\Events\MailgunEvents;
use Filagora\Newsletters\Events\MailtrapEvents;
use Filagora\Newsletters\Models\Newsletter;
use Filagora\Newsletters\Models\NewsletterEvent;
use Filagora\Newsletters\Models\NewsletterSend;
use Filagora\Newsletters\Models\NewsletterSubscriber;
use Filagora\Newsletters\Services\DeliveryEvents;
use Filagora\Newsletters\Tests\Support\SendgridEvents;

/**
 * @return array{0: NewsletterSend, 1: NewsletterSubscriber}
 */
function sentTo(string $email = 'a@example.test', NewsletterSendStatus $status = NewsletterSendStatus::Sent): array
{
    $subscriber = NewsletterSubscriber::create(['email' => $email, 'normalized_email' => $email, 'status' => NewsletterSubscriberStatus::Subscribed, 'subscribed_at' => now()]);
    $newsletter = Newsletter::create(['title' => 'T', 'subject' => 'S', 'body_html' => '']);
    $send = NewsletterSend::create(['newsletter_id' => $newsletter->id, 'newsletter_subscriber_id' => $subscriber->id, 'email' => $email, 'normalized_email' => $email, 'status' => $status]);

    return [$send, $subscriber];
}

/**
 * The same facts as each provider words them.
 *
 * @return array<string, array<string, mixed>>
 */
function providerEvent(string $provider, string $kind, NewsletterSend $send, array $extra = []): array
{
    $mailgun = [
        'delivered' => ['event' => 'delivered'],
        'hard_bounce' => ['event' => 'failed', 'severity' => 'permanent', 'reason' => 'bounce', 'delivery-status' => ['message' => 'Mailbox does not exist']],
        'soft_failure' => ['event' => 'failed', 'severity' => 'temporary', 'reason' => 'generic'],
        'complaint' => ['event' => 'complained'],
        'unsubscribe' => ['event' => 'unsubscribed'],
        'opened' => ['event' => 'opened'],
        'clicked' => ['event' => 'clicked'],
        'accepted' => ['event' => 'accepted'],
    ];
    $mailtrap = [
        'delivered' => ['event' => 'delivery'],
        'hard_bounce' => ['event' => 'bounce', 'response' => 'Mailbox does not exist'],
        'soft_failure' => ['event' => 'soft bounce', 'response' => 'Mailbox full'],
        'complaint' => ['event' => 'spam'],
        'unsubscribe' => ['event' => 'unsubscribe'],
        'opened' => ['event' => 'open'],
        'clicked' => ['event' => 'click'],
        'accepted' => ['event' => 'something else'],
    ];

    return $provider === 'mailgun'
        ? array_merge($mailgun[$kind], ['recipient' => $send->email, 'user-variables' => ['newsletter_send_id' => $send->id]], $extra)
        : array_merge($mailtrap[$kind], ['email' => $send->email, 'custom_variables' => ['newsletter_send_id' => $send->id]], $extra);
}

dataset('providers', ['mailgun', 'mailtrap']);

it('records a delivery on the send and keeps the event', function (string $provider) {
    [$send] = sentTo(status: NewsletterSendStatus::Sending);

    expect(app(DeliveryEvents::class)->handle($provider, providerEvent($provider, 'delivered', $send, ['timestamp' => 1700000000])))->toBe('Newsletter event recorded.');

    $send->refresh();
    $event = NewsletterEvent::firstOrFail();

    expect($send->status)->toBe(NewsletterSendStatus::Sent)
        ->and($send->sent_at)->not->toBeNull()
        ->and($event->type)->toBe(NewsletterEventType::Delivered)
        ->and($event->occurred_at->timestamp)->toBe(1700000000)
        ->and($event->payload)->toBeArray();
})->with('providers');

it('marks a hard bounce on the send and suppresses the subscriber with the provider as the reason', function (string $provider) {
    [$send, $subscriber] = sentTo();

    app(DeliveryEvents::class)->handle($provider, providerEvent($provider, 'hard_bounce', $send));

    expect($send->refresh()->status)->toBe(NewsletterSendStatus::Failed)
        ->and($send->error)->toBe('Mailbox does not exist')
        ->and($subscriber->refresh()->status)->toBe(NewsletterSubscriberStatus::Bounced)
        ->and($subscriber->unsubscribe_reason)->toBe("{$provider}_bounce")
        ->and($subscriber->bounced_at)->not->toBeNull();
})->with('providers');

it('marks a soft failure on the send without suppressing the subscriber', function (string $provider) {
    [$send, $subscriber] = sentTo();

    app(DeliveryEvents::class)->handle($provider, providerEvent($provider, 'soft_failure', $send));

    expect($send->refresh()->status)->toBe(NewsletterSendStatus::Failed)
        ->and($subscriber->refresh()->status)->toBe(NewsletterSubscriberStatus::Subscribed);
})->with('providers');

it('suppresses a subscriber who complained', function (string $provider) {
    [$send, $subscriber] = sentTo();

    app(DeliveryEvents::class)->handle($provider, providerEvent($provider, 'complaint', $send));

    expect($subscriber->refresh()->status)->toBe(NewsletterSubscriberStatus::Complained)
        ->and($subscriber->unsubscribe_reason)->toBe("{$provider}_complaint")
        ->and($send->refresh()->error)->toContain('complaint');
})->with('providers');

it('unsubscribes a subscriber who used the provider unsubscribe link', function (string $provider) {
    [$send, $subscriber] = sentTo();

    app(DeliveryEvents::class)->handle($provider, providerEvent($provider, 'unsubscribe', $send));

    expect($subscriber->refresh()->status)->toBe(NewsletterSubscriberStatus::Unsubscribed)
        ->and($subscriber->unsubscribe_reason)->toBe("{$provider}_unsubscribe");
})->with('providers');

it('keeps opens and clicks as events and changes nothing else', function (string $provider) {
    [$send, $subscriber] = sentTo();

    app(DeliveryEvents::class)->handle($provider, providerEvent($provider, 'opened', $send));
    app(DeliveryEvents::class)->handle($provider, providerEvent($provider, 'clicked', $send));

    expect(NewsletterEvent::orderBy('id')->pluck('type')->all())->toBe([NewsletterEventType::Opened, NewsletterEventType::Clicked])
        ->and($send->refresh()->status)->toBe(NewsletterSendStatus::Sent)
        ->and($subscriber->refresh()->status)->toBe(NewsletterSubscriberStatus::Subscribed);
})->with('providers');

it('answers null for an event that is not about a newsletter, so the host can handle it', function (string $provider) {
    $event = $provider === 'mailgun' ? ['event' => 'delivered', 'recipient' => 'stranger@example.test'] : ['event' => 'delivery', 'email' => 'stranger@example.test'];

    expect(app(DeliveryEvents::class)->handle($provider, $event))->toBeNull()
        ->and(NewsletterEvent::count())->toBe(0);
})->with('providers');

it('keeps an event of a kind it does not know on the send without an event row', function (string $provider) {
    [$send] = sentTo();

    expect(app(DeliveryEvents::class)->handle($provider, providerEvent($provider, 'accepted', $send)))->toBe('Newsletter event recorded.')
        ->and(NewsletterEvent::count())->toBe(0);
})->with('providers');

it('finds the send by the provider message id, remembering it once, and by the recipient as a last resort', function () {
    [$send] = sentTo('m@example.test');

    app(DeliveryEvents::class)->handle('mailgun', ['event' => 'delivered', 'recipient' => 'M@example.test']);
    expect(NewsletterEvent::count())->toBe(1);

    app(DeliveryEvents::class)->handle('mailgun', ['event' => 'opened', 'recipient' => 'm@example.test', 'message' => ['headers' => ['message-id' => '<abc@mg.example.test>']]]);
    expect($send->refresh()->provider_message_id)->toBe('abc@mg.example.test');

    app(DeliveryEvents::class)->handle('mailgun', ['event' => 'clicked', 'recipient' => 'someone-else@example.test', 'message' => ['headers' => ['message-id' => 'abc@mg.example.test']]]);
    app(DeliveryEvents::class)->handle('mailgun', ['event' => 'opened', 'recipient' => 'someone-else@example.test', 'message' => ['headers' => ['message-id' => 'zzz']]]);

    expect(NewsletterEvent::count())->toBe(3)->and($send->refresh()->provider_message_id)->toBe('abc@mg.example.test');

    [$other] = sentTo('t@example.test');
    app(DeliveryEvents::class)->handle('mailtrap', ['event' => 'open', 'message_id' => 'trap-1', 'email' => 't@example.test']);
    app(DeliveryEvents::class)->handle('mailtrap', ['event' => 'click', 'message_id' => 'trap-1', 'email' => 'unrelated@example.test']);

    expect($other->refresh()->provider_message_id)->toBe('trap-1')
        ->and(NewsletterEvent::where('newsletter_send_id', $other->id)->count())->toBe(2);
});

it('reads Mailgun user variables sent as a JSON string, and ignores a recipient whose send is finished', function () {
    [$send] = sentTo('j@example.test');

    app(DeliveryEvents::class)->handle('mailgun', ['event' => 'delivered', 'recipient' => 'x@example.test', 'user-variables' => json_encode(['newsletter_send_id' => $send->id])]);
    expect(NewsletterEvent::where('newsletter_send_id', $send->id)->count())->toBe(1);

    [$failed] = sentTo('f@example.test', NewsletterSendStatus::Failed);
    expect(app(DeliveryEvents::class)->handle('mailgun', ['event' => 'delivered', 'recipient' => 'f@example.test']))->toBeNull();
});

it('does not move a suppressed subscriber back by a later delivery', function () {
    [$send, $subscriber] = sentTo();

    app(DeliveryEvents::class)->handle('mailgun', providerEvent('mailgun', 'hard_bounce', $send));
    app(DeliveryEvents::class)->handle('mailgun', providerEvent('mailgun', 'delivered', $send));

    expect($subscriber->refresh()->status)->toBe(NewsletterSubscriberStatus::Bounced);
});

it('has a handler for each built-in provider, none for an unknown one, and takes more from config', function () {
    $events = app(DeliveryEvents::class);

    expect($events->handler('Mailgun'))->toBeInstanceOf(MailgunEvents::class)
        ->and($events->handler('mailtrap'))->toBeInstanceOf(MailtrapEvents::class)
        ->and($events->handler('sendgrid'))->toBeNull()
        ->and($events->handle('sendgrid', ['event' => 'x']))->toBeNull();

    config(['filagora-newsletters.events.handlers.sendgrid' => SendgridEvents::class]);

    expect($events->handle('SendGrid', ['event' => 'x']))->toBe('sendgrid saw x');
});
