<?php

declare(strict_types=1);

use Filagora\Newsletters\Enums\NewsletterSubscriberStatus;
use Filagora\Newsletters\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use Filagora\Newsletters\Filament\Resources\NewsletterSubscribers\Pages\CreateNewsletterSubscriber;
use Filagora\Newsletters\Filament\Resources\NewsletterSubscribers\Pages\EditNewsletterSubscriber;
use Filagora\Newsletters\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use Filagora\Newsletters\Models\NewsletterSubscriber;
use Filagora\Newsletters\Tests\Support\TestUser;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

function aSub(array $attributes = []): NewsletterSubscriber
{
    return NewsletterSubscriber::create(array_merge([
        'email' => 'subscriber@example.test', 'normalized_email' => 'subscriber@example.test', 'name' => 'Subscriber',
        'status' => NewsletterSubscriberStatus::Pending, 'source' => 'public_signup',
    ], $attributes));
}

it('is on the panel with the plugin', function () {
    Filament::setCurrentPanel('admin');

    expect(Filament::getCurrentPanel()->getResources())->toContain(NewsletterSubscriberResource::class);
});

it('shuts out someone without the newsletter ability', function () {
    $subscriber = aSub();
    actingAs(TestUser::make('Nobody'));
    Filament::setCurrentPanel('admin');

    $this->get(NewsletterSubscriberResource::getUrl('index'))->assertForbidden();
    $this->get(NewsletterSubscriberResource::getUrl('view', ['record' => $subscriber]))->assertForbidden();
});

it('shows staff the list, form and record pages, and never lets anyone delete a subscriber', function () {
    $subscriber = aSub();
    newsletterManager();

    foreach ([NewsletterSubscriberResource::getUrl('index'), NewsletterSubscriberResource::getUrl('create'), NewsletterSubscriberResource::getUrl('view', ['record' => $subscriber]), NewsletterSubscriberResource::getUrl('edit', ['record' => $subscriber])] as $url) {
        $this->get($url)->assertOk();
    }

    expect(NewsletterSubscriberResource::canDelete($subscriber))->toBeFalse()
        ->and(NewsletterSubscriberResource::canDeleteAny())->toBeFalse();
});

it('unsubscribes and re-confirms a subscriber from the table, keeping the reason', function () {
    newsletterManager();
    $subscriber = aSub(['status' => NewsletterSubscriberStatus::Subscribed, 'subscribed_at' => now()->subWeek(), 'confirmed_at' => now()->subWeek()]);

    Livewire::test(ListNewsletterSubscribers::class)->callTableAction('unsubscribe', $subscriber);

    $subscriber->refresh();
    expect($subscriber->status)->toBe(NewsletterSubscriberStatus::Unsubscribed)
        ->and($subscriber->unsubscribe_reason)->toBe('admin')
        ->and($subscriber->unsubscribed_at)->not->toBeNull();

    Livewire::test(ListNewsletterSubscribers::class)->callTableAction('confirm_subscription', $subscriber, ['reason' => 'Subscriber requested resubscribe by support ticket.']);

    $subscriber->refresh();
    expect($subscriber->status)->toBe(NewsletterSubscriberStatus::Subscribed)
        ->and($subscriber->source)->toBe('admin')
        ->and($subscriber->consent_text)->toContain('Subscriber requested resubscribe')
        ->and($subscriber->consent_version)->toBe('admin-v1')
        ->and($subscriber->unsubscribe_reason)->toBeNull();
});

it('normalises the address on the server when staff create or edit a subscriber', function () {
    newsletterManager();

    Livewire::test(CreateNewsletterSubscriber::class)->fillForm(['email' => 'AdminCreate@Example.TEST', 'name' => 'Admin Created', 'source' => 'admin'])->call('create')->assertHasNoFormErrors();

    $subscriber = NewsletterSubscriber::where('normalized_email', 'admincreate@example.test')->firstOrFail();
    expect($subscriber->email)->toBe('AdminCreate@Example.TEST')->and($subscriber->status)->toBe(NewsletterSubscriberStatus::Pending);

    Livewire::test(EditNewsletterSubscriber::class, ['record' => $subscriber->getRouteKey()])->fillForm(['email' => 'UpdatedAdmin@Example.TEST', 'name' => 'Updated Admin', 'source' => 'admin'])->call('save')->assertHasNoFormErrors();

    $subscriber->refresh();
    expect($subscriber->email)->toBe('UpdatedAdmin@Example.TEST')->and($subscriber->normalized_email)->toBe('updatedadmin@example.test')->and($subscriber->name)->toBe('Updated Admin');
});

it('unsubscribes the selected subscribers in bulk and touches no one else', function () {
    newsletterManager();
    $subscribed = aSub(['email' => 'bulk-a@example.test', 'normalized_email' => 'bulk-a@example.test', 'status' => NewsletterSubscriberStatus::Subscribed, 'subscribed_at' => now()->subWeek()]);
    $already = aSub(['email' => 'bulk-b@example.test', 'normalized_email' => 'bulk-b@example.test', 'status' => NewsletterSubscriberStatus::Unsubscribed, 'unsubscribed_at' => now()->subDay()]);
    $untouched = aSub(['email' => 'bulk-c@example.test', 'normalized_email' => 'bulk-c@example.test', 'status' => NewsletterSubscriberStatus::Subscribed]);

    Livewire::test(ListNewsletterSubscribers::class)
        ->selectTableRecords([$subscribed->id, $already->id])
        ->callAction(TestAction::make('bulk_unsubscribe')->table()->bulk())
        ->assertNotified();

    expect($subscribed->refresh()->status)->toBe(NewsletterSubscriberStatus::Unsubscribed)
        ->and($subscribed->unsubscribe_reason)->toBe('admin_bulk')
        ->and($already->refresh()->status)->toBe(NewsletterSubscriberStatus::Unsubscribed)
        ->and($untouched->refresh()->status)->toBe(NewsletterSubscriberStatus::Subscribed);
});

it('exports only the selected subscribers to CSV', function () {
    newsletterManager();
    $included = aSub(['email' => 'exported-a@example.test', 'normalized_email' => 'exported-a@example.test', 'status' => NewsletterSubscriberStatus::Subscribed]);
    aSub(['email' => 'excluded-b@example.test', 'normalized_email' => 'excluded-b@example.test', 'status' => NewsletterSubscriberStatus::Subscribed]);

    $response = Livewire::test(ListNewsletterSubscribers::class)->selectTableRecords([$included->id])->callAction(TestAction::make('bulk_export')->table()->bulk());

    $response->assertFileDownloaded();
    $content = base64_decode((string) data_get($response->effects, 'download.content'));

    expect($content)->toContain('exported-a@example.test')->not->toContain('excluded-b@example.test');
});
