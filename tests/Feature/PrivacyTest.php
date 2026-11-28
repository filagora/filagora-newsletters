<?php

declare(strict_types=1);

use Filagora\Core\Privacy\PrivacyContributorRegistry;
use Filagora\Newsletters\Enums\NewsletterEventType;
use Filagora\Newsletters\Enums\NewsletterSubscriberStatus;
use Filagora\Newsletters\Models\Newsletter;
use Filagora\Newsletters\Models\NewsletterEvent;
use Filagora\Newsletters\Models\NewsletterLinkClick;
use Filagora\Newsletters\Models\NewsletterSend;
use Filagora\Newsletters\Models\NewsletterSubscriber;
use Filagora\Newsletters\Services\Subscribers;
use Filagora\Newsletters\Tests\Support\TestUser;

function privacyContributor(): object
{
    $contributors = app(PrivacyContributorRegistry::class)->all();

    expect($contributors)->toHaveKey('newsletters');

    return $contributors['newsletters'];
}

function delivery(NewsletterSubscriber $row, string $address, string $subject = 'News'): NewsletterSend
{
    $newsletter = Newsletter::create(['title' => $subject, 'subject' => $subject, 'body_html' => '<p>x</p>']);

    return NewsletterSend::create([
        'newsletter_id' => $newsletter->id,
        'newsletter_subscriber_id' => $row->id,
        'email' => $address,
        'normalized_email' => strtolower($address),
        'name' => 'Member',
        'error' => "550 {$address} rejected",
        'metadata' => ['to' => $address],
    ]);
}

function changeAddress(TestUser $user, string $to): void
{
    $old = $user->email;
    $user->forceFill(['email' => $to])->save();
    app(Subscribers::class)->unlinkUserForEmailChange($user, $old);
}

it('erases a member from the list and from its delivery history', function () {
    $user = TestUser::make('Member');
    $address = $user->email;
    $row = app(Subscribers::class)->subscribeFromProfile($user, '203.0.113.7', 'Agent/1');
    $send = delivery($row, $address);

    privacyContributor()->erase($user);

    expect($row->refresh()->email)->toBe("erased-subscriber-{$row->id}@anonymized.invalid")
        ->and($row->status)->toBe(NewsletterSubscriberStatus::Unsubscribed)
        ->and($row->consent_ip)->toBeNull()
        ->and(NewsletterSubscriber::query()->sendable()->count())->toBe(0)
        ->and($send->refresh()->email)->toBe("erased-send-{$send->id}@anonymized.invalid")
        ->and($send->name)->toBeNull()
        ->and($send->error)->toBeNull()
        ->and($send->metadata)->toBeNull();
});

it('erases the subscription left under an address the member changed from', function () {
    $user = TestUser::make('Member');
    $old = $user->email;
    $row = app(Subscribers::class)->subscribeFromProfile($user);
    $send = delivery($row, $old);
    changeAddress($user, 'new-address@example.test');

    privacyContributor()->erase($user);

    expect($row->refresh()->email)->toBe("erased-subscriber-{$row->id}@anonymized.invalid")
        ->and($row->metadata)->toBeNull()
        ->and($send->refresh()->email)->toBe("erased-send-{$send->id}@anonymized.invalid");
});

it('erases history sent to an earlier address of a linked subscription', function () {
    $user = TestUser::make('Member');
    $row = app(Subscribers::class)->subscribeFromProfile($user);
    $send = delivery($row, 'earlier@example.test');

    privacyContributor()->erase($user);

    expect($send->refresh()->email)->toBe("erased-send-{$send->id}@anonymized.invalid");
});

it('leaves an old address that another account has since taken up', function () {
    $user = TestUser::make('Member');
    $old = $user->email;
    app(Subscribers::class)->subscribeFromProfile($user);
    changeAddress($user, 'new-address@example.test');

    $other = TestUser::make('Other');
    $other->forceFill(['email' => $old])->save();
    $row = app(Subscribers::class)->subscribeFromProfile($other);

    privacyContributor()->erase($user);

    expect($row->refresh()->email)->toBe($old)
        ->and($row->status)->toBe(NewsletterSubscriberStatus::Subscribed);
});

it('leaves other people alone', function () {
    $other = TestUser::make('Other');
    $row = app(Subscribers::class)->subscribeFromProfile($other);
    $send = delivery($row, $other->email);

    privacyContributor()->erase(TestUser::make('Member'));

    expect($row->refresh()->email)->toBe($other->email)
        ->and($send->refresh()->name)->toBe('Member');
});

it('exports a member\'s subscriptions and deliveries, under old addresses too', function () {
    $user = TestUser::make('Member');
    $old = $user->email;
    $row = app(Subscribers::class)->subscribeFromProfile($user, '203.0.113.7', 'Agent/1');
    $send = delivery($row, $old, 'March news');
    NewsletterEvent::create(['newsletter_send_id' => $send->id, 'type' => NewsletterEventType::Delivered, 'occurred_at' => now()]);
    NewsletterLinkClick::create(['newsletter_id' => $send->newsletter_id, 'newsletter_send_id' => $send->id, 'destination_url' => 'https://example.test/offer', 'clicked_at' => now()]);
    changeAddress($user, 'new-address@example.test');
    $other = TestUser::make('Other');
    delivery(app(Subscribers::class)->subscribeFromProfile($other), $other->email);

    $export = privacyContributor()->export($user);

    expect($export['subscriptions'])->toHaveCount(1)
        ->and($export['subscriptions'][0])->toMatchArray(['email' => $old, 'status' => 'subscribed', 'consent_ip' => '203.0.113.7', 'consent_user_agent' => 'Agent/1', 'metadata' => []])
        ->and($export['deliveries'])->toHaveCount(1)
        ->and($export['deliveries'][0])->toMatchArray(['newsletter' => 'March news', 'email' => $old])
        ->and($export['deliveries'][0]['events'][0]['type'])->toBe(NewsletterEventType::Delivered->value)
        ->and($export['deliveries'][0]['clicks'][0]['url'])->toBe('https://example.test/offer')
        ->and(json_encode($export))->not->toContain($other->email);
});
