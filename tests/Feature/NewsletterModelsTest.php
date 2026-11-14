<?php

declare(strict_types=1);

use Filagora\Newsletters\Enums\NewsletterBlockType;
use Filagora\Newsletters\Enums\NewsletterEventType;
use Filagora\Newsletters\Enums\NewsletterSendStatus;
use Filagora\Newsletters\Enums\NewsletterStatus;
use Filagora\Newsletters\Enums\NewsletterSubscriberStatus;
use Filagora\Newsletters\Models\Newsletter;
use Filagora\Newsletters\Models\NewsletterBlock;
use Filagora\Newsletters\Models\NewsletterEvent;
use Filagora\Newsletters\Models\NewsletterLinkClick;
use Filagora\Newsletters\Models\NewsletterSend;
use Filagora\Newsletters\Models\NewsletterSubscriber;
use Filagora\Newsletters\Support\Models;
use Filagora\Newsletters\Tests\Support\TestUser;

function aNewsletter(array $attributes = []): Newsletter
{
    return Newsletter::create(array_merge(['title' => 'November', 'subject' => 'News', 'body_html' => '<p>Hi</p>'], $attributes));
}

function aSubscriber(string $email = 'a@example.test', array $attributes = []): NewsletterSubscriber
{
    return NewsletterSubscriber::create(array_merge(['email' => $email, 'normalized_email' => $email], $attributes));
}

it('uses the prefixed table names', function () {
    expect((new Newsletter)->getTable())->toBe('filagora_newsletters')
        ->and((new NewsletterSubscriber)->getTable())->toBe('filagora_newsletter_subscribers')
        ->and((new NewsletterSend)->getTable())->toBe('filagora_newsletter_sends')
        ->and((new NewsletterEvent)->getTable())->toBe('filagora_newsletter_events')
        ->and((new NewsletterBlock)->getTable())->toBe('filagora_newsletter_blocks')
        ->and((new NewsletterLinkClick)->getTable())->toBe('filagora_newsletter_link_clicks');
});

it('starts a newsletter as an editable draft with the default colours', function () {
    $newsletter = aNewsletter()->refresh();

    expect($newsletter->status)->toBe(NewsletterStatus::Draft)
        ->and($newsletter->background_color)->toBe('#F4F1EA')
        ->and($newsletter->inner_background_color)->toBe('#FBF8F2')
        ->and($newsletter->isDeletable())->toBeTrue();
});

it('is not deletable once it has been queued, or is no longer a draft', function () {
    $newsletter = aNewsletter();
    NewsletterSend::create(['newsletter_id' => $newsletter->id, 'email' => 'a@example.test', 'normalized_email' => 'a@example.test']);

    expect($newsletter->isDeletable())->toBeFalse()
        ->and(aNewsletter(['status' => NewsletterStatus::Sent])->isDeletable())->toBeFalse();
});

it('links sends, events, clicks and the subscriber together', function () {
    $newsletter = aNewsletter();
    $subscriber = aSubscriber();
    $send = NewsletterSend::create(['newsletter_id' => $newsletter->id, 'newsletter_subscriber_id' => $subscriber->id, 'email' => 'a@example.test', 'normalized_email' => 'a@example.test']);
    $event = NewsletterEvent::create(['newsletter_send_id' => $send->id, 'type' => NewsletterEventType::Delivered, 'occurred_at' => now()]);
    $click = NewsletterLinkClick::create(['newsletter_id' => $newsletter->id, 'newsletter_send_id' => $send->id, 'block_key' => 'cta', 'destination_url' => 'https://example.test', 'clicked_at' => now()]);

    expect($send->refresh()->status)->toBe(NewsletterSendStatus::Queued)
        ->and($newsletter->sends->modelKeys())->toBe([$send->id])
        ->and($newsletter->linkClicks->modelKeys())->toBe([$click->id])
        ->and($subscriber->newsletterSends->modelKeys())->toBe([$send->id])
        ->and($send->subscriber->is($subscriber))->toBeTrue()
        ->and($send->events->modelKeys())->toBe([$event->id])
        ->and($send->linkClicks->modelKeys())->toBe([$click->id])
        ->and($event->send->is($send))->toBeTrue()
        ->and($click->send->is($send))->toBeTrue()
        ->and($click->newsletter->is($newsletter))->toBeTrue();
});

it('relates a subscriber, a newsletter and a block to the configured user model', function () {
    $user = TestUser::make();

    expect(aSubscriber('u@example.test', ['user_id' => $user->id])->user->is($user))->toBeTrue()
        ->and(aNewsletter(['created_by' => $user->id])->creator->is($user))->toBeTrue()
        ->and(NewsletterBlock::create(['key' => 'promo', 'name' => 'Promo', 'type' => NewsletterBlockType::Text, 'updated_by' => $user->id])->updater->is($user))->toBeTrue();
});

it('says which subscribers can be sent to', function () {
    aSubscriber('on@example.test', ['status' => NewsletterSubscriberStatus::Subscribed]);
    aSubscriber('wait@example.test', ['status' => NewsletterSubscriberStatus::Pending]);
    aSubscriber('off@example.test', ['status' => NewsletterSubscriberStatus::Unsubscribed]);

    expect(NewsletterSubscriber::sendable()->pluck('email')->all())->toBe(['on@example.test'])
        ->and(NewsletterSubscriber::where('email', 'off@example.test')->first()->isSendable())->toBeFalse()
        ->and(NewsletterSubscriberStatus::Bounced->isSuppressed())->toBeTrue()
        ->and(NewsletterSubscriberStatus::Pending->isSuppressed())->toBeFalse();
});

it('filters active blocks and keeps system blocks out of the choices', function () {
    NewsletterBlock::create(['key' => 'off', 'name' => 'Off', 'type' => NewsletterBlockType::Text, 'is_active' => false]);

    expect(NewsletterBlock::active()->pluck('key')->all())->not->toContain('off')
        ->and(NewsletterBlockType::selectableOptions())->toBe(['text' => 'Text section', 'image' => 'Image', 'button' => 'Button']);
});

it('resolves model classes from config, and a swapped class is followed by the relations', function () {
    expect(Models::class('send'))->toBe(NewsletterSend::class);

    $swapped = new class extends NewsletterSend {};
    config(['filagora-newsletters.models.send' => $swapped::class]);

    $newsletter = aNewsletter();
    $swapped::create(['newsletter_id' => $newsletter->id, 'email' => 'a@example.test', 'normalized_email' => 'a@example.test']);

    expect($newsletter->sends()->first())->toBeInstanceOf($swapped::class)
        ->and(fn () => Models::class('nothing'))->toThrow(InvalidArgumentException::class);
});
