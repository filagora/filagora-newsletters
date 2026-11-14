<?php

declare(strict_types=1);

use Filagora\Newsletters\Enums\NewsletterEventType;
use Filagora\Newsletters\Enums\NewsletterSubscriberStatus;
use Filagora\Newsletters\Models\Newsletter;
use Filagora\Newsletters\Models\NewsletterEvent;
use Filagora\Newsletters\Models\NewsletterLinkClick;
use Filagora\Newsletters\Models\NewsletterSend;
use Filagora\Newsletters\Models\NewsletterSubscriber;
use Filagora\Newsletters\Services\Subscribers;
use Filagora\Newsletters\Tests\Support\TestUser;

function subscribers(): Subscribers
{
    return app(Subscribers::class);
}

it('normalises addresses and hashes the normalised form', function () {
    expect(subscribers()->normalizeEmail('  Mixed@Example.TEST '))->toBe('mixed@example.test')
        ->and(subscribers()->suppressionHash('MIXED@example.test '))->toBe(hash('sha256', 'mixed@example.test'));
});

it('subscribes a member from their profile with their consent recorded, and keeps the first dates', function () {
    $user = TestUser::make('Member');

    $first = subscribers()->subscribeFromProfile($user, '203.0.113.7', 'Agent/1');

    expect($first->status)->toBe(NewsletterSubscriberStatus::Subscribed)
        ->and($first->source)->toBe('profile')
        ->and($first->user_id)->toBe($user->id)
        ->and($first->consent_version)->toBe('profile-v1')
        ->and($first->consent_ip)->toBe('203.0.113.7')
        ->and($first->consent_user_agent)->toBe('Agent/1');

    $firstDate = $first->subscribed_at;
    $this->travel(2)->days();
    subscribers()->unsubscribeFromProfile($user);
    $again = subscribers()->subscribeFromProfile($user);

    expect(NewsletterSubscriber::count())->toBe(1)
        ->and($again->subscribed_at->equalTo($firstDate))->toBeTrue()
        ->and($again->unsubscribed_at)->toBeNull();
});

it('finds a member by account or by address, preferring the linked row', function () {
    $user = TestUser::make('Member');
    NewsletterSubscriber::create(['email' => $user->email, 'normalized_email' => strtolower($user->email)]);
    $linked = NewsletterSubscriber::create(['user_id' => $user->id, 'email' => 'other@example.test', 'normalized_email' => 'other@example.test']);

    expect(subscribers()->forUser($user)->is($linked))->toBeTrue()
        ->and(subscribers()->forUser(TestUser::make('Nobody')))->toBeNull();
});

it('keeps the old address with its consent when the member changes their email', function () {
    $user = TestUser::make('Member');
    $row = subscribers()->subscribeFromProfile($user);

    expect(subscribers()->unlinkUserForEmailChange($user, ' '.strtoupper($user->email)))->toBe(1)
        ->and($row->refresh()->user_id)->toBeNull()
        ->and(subscribers()->unlinkUserForEmailChange($user, ''))->toBe(0);
});

it('does not reset an already subscribed person who signs up again, and sets a pending one back to pending', function () {
    $subscribed = subscribers()->requestPublicSubscription('on@example.test');
    subscribers()->confirm($subscribed);

    expect(subscribers()->requestPublicSubscription('ON@example.test')->status)->toBe(NewsletterSubscriberStatus::Subscribed);

    $off = subscribers()->requestPublicSubscription('off@example.test');
    subscribers()->unsubscribe($off, 'test');

    expect(subscribers()->requestPublicSubscription('off@example.test', 'Name')->status)->toBe(NewsletterSubscriberStatus::Pending)
        ->and(NewsletterSubscriber::count())->toBe(2);
});

it('finds a subscriber by hash after erasure, so a suppressed address is not re-added silently', function () {
    $user = TestUser::make('Member');
    $row = subscribers()->subscribeFromProfile($user);

    subscribers()->anonymizeForErasure($user);

    $row->refresh();

    expect($row->email)->toBe("erased-subscriber-{$row->id}@anonymized.invalid")
        ->and($row->status)->toBe(NewsletterSubscriberStatus::Unsubscribed)
        ->and($row->user_id)->toBeNull()
        ->and($row->consent_ip)->toBeNull()
        ->and(subscribers()->forEmail($user->email)?->is($row))->toBeTrue();
});

it('erases the delivery history of the address and severs its clicks', function () {
    $user = TestUser::make('Member');
    $row = subscribers()->subscribeFromProfile($user);
    $newsletter = Newsletter::create(['title' => 'T', 'subject' => 'S', 'body_html' => '<p>x</p>']);
    $send = NewsletterSend::create(['newsletter_id' => $newsletter->id, 'newsletter_subscriber_id' => $row->id, 'email' => $user->email, 'normalized_email' => strtolower($user->email), 'name' => 'Member']);
    $event = NewsletterEvent::create(['newsletter_send_id' => $send->id, 'type' => NewsletterEventType::Delivered, 'occurred_at' => now(), 'payload' => ['to' => $user->email]]);
    $click = NewsletterLinkClick::create(['newsletter_id' => $newsletter->id, 'newsletter_send_id' => $send->id, 'destination_url' => 'https://example.test', 'clicked_at' => now()]);

    subscribers()->anonymizeForErasure($user);

    expect($send->refresh()->email)->toBe("erased-send-{$send->id}@anonymized.invalid")
        ->and($send->name)->toBeNull()
        ->and($event->refresh()->payload)->toBeNull()
        ->and($click->refresh()->newsletter_send_id)->toBeNull();
});
