<?php

declare(strict_types=1);

use Filagora\Core\Models\Setting;
use Filagora\Newsletters\Enums\NewsletterStatus;
use Filagora\Newsletters\Filament\Pages\NewsletterSettings;
use Filagora\Newsletters\Filament\Resources\Newsletters\NewsletterResource;
use Filagora\Newsletters\Models\Newsletter;
use Filagora\Newsletters\Models\NewsletterSend;
use Filagora\Newsletters\Services\BrandingSettings;
use Filagora\Newsletters\Tests\Support\TestUser;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

function draft(array $attributes = []): Newsletter
{
    return Newsletter::create(array_merge(['title' => 'T', 'subject' => 'S', 'body_html' => ''], $attributes));
}

it('puts the campaign resource and the settings page on a panel with the plugin', function () {
    Filament::setCurrentPanel('admin');

    expect(Filament::getCurrentPanel()->getResources())->toContain(NewsletterResource::class)
        ->and(Filament::getCurrentPanel()->getPages())->toContain(NewsletterSettings::class);
});

it('lets a campaign be changed only while it is a draft or scheduled, and deleted only before it was queued', function () {
    newsletterManager();

    $sent = draft(['status' => NewsletterStatus::Sent]);
    $sending = draft(['status' => NewsletterStatus::Sending]);
    $scheduled = draft(['status' => NewsletterStatus::Scheduled, 'scheduled_at' => now()->addDay()]);
    $queued = draft();
    NewsletterSend::create(['newsletter_id' => $queued->id, 'email' => 'a@example.test', 'normalized_email' => 'a@example.test']);

    expect(NewsletterResource::canEdit(draft()))->toBeTrue()
        ->and(NewsletterResource::canEdit($scheduled))->toBeTrue()
        ->and(NewsletterResource::canEdit($sent))->toBeFalse()
        ->and(NewsletterResource::canEdit($sending))->toBeFalse()
        ->and(NewsletterResource::canDelete(draft()))->toBeTrue()
        ->and(NewsletterResource::canDelete($queued))->toBeFalse()
        ->and(NewsletterResource::canDelete($sent))->toBeFalse()
        ->and(NewsletterResource::canViewAny())->toBeTrue()
        ->and(NewsletterResource::canCreate())->toBeTrue()
        ->and(NewsletterResource::canDeleteAny())->toBeFalse();
});

it('gives someone without the ability none of it, including the settings page and the preview', function () {
    $newsletter = draft();
    actingAs(TestUser::make('Nobody'));
    Filament::setCurrentPanel('admin');

    expect(NewsletterResource::canViewAny())->toBeFalse()
        ->and(NewsletterResource::canCreate())->toBeFalse()
        ->and(NewsletterSettings::canAccess())->toBeFalse();

    $this->get(route('newsletters.preview', $newsletter))->assertForbidden();
});

it('saves the shared branding from the settings page', function () {
    newsletterManager();

    Livewire::test(NewsletterSettings::class)
        ->fillForm(['brand_name' => 'Acme', 'header_eyebrow' => 'Weekly', 'footer_text' => 'Bye for now', 'manage_link_label' => 'Leave'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified();

    expect(Setting::where('key', BrandingSettings::BRAND_NAME_KEY)->value('value'))->toBe('Acme')
        ->and(app(BrandingSettings::class)->settings())->toMatchArray(['brand_name' => 'Acme', 'header_eyebrow' => 'Weekly', 'footer_text' => 'Bye for now', 'manage_link_label' => 'Leave']);
});

it('asks the settings page for the branding it already has', function () {
    newsletterManager();
    config(['filagora-newsletters.branding.footer_text' => 'Default footer']);

    Livewire::test(NewsletterSettings::class)->assertFormSet(['footer_text' => 'Default footer']);
});
