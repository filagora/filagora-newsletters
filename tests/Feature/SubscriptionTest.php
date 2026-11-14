<?php

declare(strict_types=1);

use Filagora\Newsletters\Enums\NewsletterSubscriberStatus;
use Filagora\Newsletters\Models\NewsletterSubscriber;
use Filagora\Newsletters\Notifications\ConfirmSubscription;
use Filagora\Newsletters\Services\Subscribers;
use Filagora\Newsletters\Tests\Support\TestUser;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

function pendingSubscriber(string $email = 'pending@example.test'): NewsletterSubscriber
{
    return NewsletterSubscriber::create(['email' => $email, 'normalized_email' => $email, 'status' => NewsletterSubscriberStatus::Pending, 'source' => 'public_signup']);
}

it('creates a pending subscriber and sends the confirmation to the address as typed', function () {
    Notification::fake();
    config(['filagora-newsletters.name' => 'Zen Cart newsletter']);

    $this->post(route('newsletter.store'), ['name' => 'Reader', 'email' => ' READER@example.TEST ', 'newsletter_consent' => '1'])
        ->assertRedirect(route('newsletter.show'))
        ->assertSessionHas('newsletter_status', 'If that email can be subscribed, a confirmation link has been sent.');

    $subscriber = NewsletterSubscriber::where('normalized_email', 'reader@example.test')->firstOrFail();

    expect($subscriber->email)->toBe('READER@example.TEST')
        ->and($subscriber->status)->toBe(NewsletterSubscriberStatus::Pending)
        ->and($subscriber->source)->toBe('public_signup')
        ->and($subscriber->consent_text)->toBe('I agree to receive the Zen Cart newsletter by email.')
        ->and($subscriber->consent_version)->toBe('public-v1');

    Notification::assertSentOnDemand(ConfirmSubscription::class, function (ConfirmSubscription $notification, array $channels, object $notifiable): bool {
        $mail = $notification->toMail($notifiable);

        return ($notifiable->routes['mail'] ?? null) === 'READER@example.TEST'
            && $mail->subject === 'Confirm your Zen Cart newsletter subscription';
    });
});

it('does not email a confirmation to someone already subscribed, and says the same thing', function () {
    Notification::fake();
    NewsletterSubscriber::create(['email' => 'on@example.test', 'normalized_email' => 'on@example.test', 'status' => NewsletterSubscriberStatus::Subscribed]);

    $this->post(route('newsletter.store'), ['email' => 'on@example.test', 'newsletter_consent' => '1'])
        ->assertSessionHas('newsletter_status', 'If that email can be subscribed, a confirmation link has been sent.');

    Notification::assertSentOnDemandTimes(ConfirmSubscription::class, 0);
});

it('rejects a bot that fills the honeypot and a missing consent', function () {
    Notification::fake();

    $this->from(route('newsletter.show'))->post(route('newsletter.store'), ['email' => 'bot@example.test', 'newsletter_consent' => '1', 'website' => 'https://spam.example.test'])
        ->assertSessionHasErrors('website');
    $this->from(route('newsletter.show'))->post(route('newsletter.store'), ['email' => 'nope@example.test'])
        ->assertSessionHasErrors('newsletter_consent');

    expect(NewsletterSubscriber::count())->toBe(0);
    Notification::assertNothingSent();
});

it('limits the signup form per address', function () {
    Notification::fake();
    config(['filagora-newsletters.routes.signup_per_minute' => 2]);
    RateLimiter::clear('newsletter-signup:127.0.0.1');

    foreach (range(1, 2) as $i) {
        $this->post(route('newsletter.store'), ['email' => "r{$i}@example.test", 'newsletter_consent' => '1'])->assertRedirect();
    }

    $this->post(route('newsletter.store'), ['email' => 'r3@example.test', 'newsletter_consent' => '1'])->assertStatus(429);
});

it('confirms a pending subscriber from the signed link only', function () {
    $subscriber = pendingSubscriber();

    $this->get(route('newsletter.confirm', $subscriber))->assertForbidden();
    expect($subscriber->refresh()->status)->toBe(NewsletterSubscriberStatus::Pending);

    $this->get(app(Subscribers::class)->signedConfirmationUrl($subscriber))->assertOk()->assertSee('Subscription confirmed.');

    $subscriber->refresh();

    expect($subscriber->status)->toBe(NewsletterSubscriberStatus::Subscribed)
        ->and($subscriber->subscribed_at)->not->toBeNull()
        ->and($subscriber->confirmed_at)->not->toBeNull()
        ->and($subscriber->unsubscribed_at)->toBeNull();
});

it('expires the confirmation link after seven days', function () {
    $subscriber = pendingSubscriber();
    $url = app(Subscribers::class)->signedConfirmationUrl($subscriber);

    $this->travel(8)->days();
    $this->get($url)->assertForbidden();
});

it('unsubscribes from the signed link and records why', function () {
    $subscriber = NewsletterSubscriber::create(['email' => 'on@example.test', 'normalized_email' => 'on@example.test', 'status' => NewsletterSubscriberStatus::Subscribed, 'subscribed_at' => now()->subWeek()]);

    $this->get(route('newsletter.unsubscribe', $subscriber))->assertForbidden();
    $this->get(app(Subscribers::class)->signedUnsubscribeUrl($subscriber))->assertOk()->assertSee('You are unsubscribed.');

    $subscriber->refresh();

    expect($subscriber->status)->toBe(NewsletterSubscriberStatus::Unsubscribed)
        ->and($subscriber->unsubscribed_at)->not->toBeNull()
        ->and($subscriber->unsubscribe_reason)->toBe('signed_link');
});

it('shows a guest the form and a signed-in person their status', function () {
    $this->get(route('newsletter.show'))->assertOk()->assertSee('Send Confirmation Link');

    $user = TestUser::make('Reader');
    $this->actingAs($user)->get(route('newsletter.show'))->assertOk()->assertSee('You are not subscribed.')->assertDontSee('Send Confirmation Link');

    app(Subscribers::class)->subscribeFromProfile($user);
    $this->actingAs($user)->get(route('newsletter.show'))->assertSee('You are subscribed.');
});

it('lets a host name its own pages', function () {
    view()->addNamespace('host', __DIR__.'/../Support/views');
    config(['filagora-newsletters.routes.views.confirmed' => 'host::confirmed']);
    $subscriber = pendingSubscriber();

    $this->get(app(Subscribers::class)->signedConfirmationUrl($subscriber))->assertOk()->assertSee('Host confirmed page');
});
