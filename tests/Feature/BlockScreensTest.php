<?php

declare(strict_types=1);

use Filagora\Newsletters\Contracts\NewsletterMedia;
use Filagora\Newsletters\Enums\NewsletterBlockType;
use Filagora\Newsletters\Filament\Resources\NewsletterBlocks\NewsletterBlockResource;
use Filagora\Newsletters\Filament\Resources\NewsletterBlocks\Pages\CreateNewsletterBlock;
use Filagora\Newsletters\Filament\Resources\NewsletterBlocks\Pages\EditNewsletterBlock;
use Filagora\Newsletters\Filament\RichEditor\EditorSetup;
use Filagora\Newsletters\Filament\RichEditor\InsertTrackedLinkPlugin;
use Filagora\Newsletters\Models\Newsletter;
use Filagora\Newsletters\Models\NewsletterBlock;
use Filagora\Newsletters\Models\NewsletterLinkClick;
use Filagora\Newsletters\Services\ContentRenderer;
use Filagora\Newsletters\Tests\Support\TestUser;
use Filament\Facades\Filament;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('is on the panel with the plugin, and shuts out someone without the ability', function () {
    Filament::setCurrentPanel('admin');
    expect(Filament::getCurrentPanel()->getResources())->toContain(NewsletterBlockResource::class);

    actingAs(TestUser::make('Nobody'));
    $this->get(NewsletterBlockResource::getUrl('index'))->assertForbidden();
});

it('lets staff create a reusable text block with a key made from its name', function () {
    newsletterManager();

    Livewire::test(CreateNewsletterBlock::class)
        ->fillForm(['name' => 'Spring Sale Banner', 'type' => 'text', 'content.heading' => 'Spring sale is live', 'content.body_html' => '<p>20% off everything.</p>'])
        ->call('create')
        ->assertHasNoFormErrors();

    $block = NewsletterBlock::where('name', 'Spring Sale Banner')->firstOrFail();

    expect($block->key)->toBe('spring-sale-banner')
        ->and($block->type)->toBe(NewsletterBlockType::Text)
        ->and($block->content['heading'])->toBe('Spring sale is live')
        ->and($block->is_active)->toBeTrue()
        ->and($block->is_system)->toBeFalse();
});

it('lets staff edit a reusable block and records who did', function () {
    $user = newsletterManager();
    $block = NewsletterBlock::create(['key' => 'promo-banner', 'name' => 'Promo banner', 'type' => NewsletterBlockType::Text, 'content' => ['heading' => 'Original copy']]);

    Livewire::test(EditNewsletterBlock::class, ['record' => $block->getRouteKey()])
        ->fillForm(['content.heading' => 'Updated copy', 'content.body_html' => '<p>Updated body</p>', 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    $block->refresh();

    expect($block->content['heading'])->toBe('Updated copy')->and($block->is_active)->toBeFalse()->and($block->updater->is($user))->toBeTrue();
});

it('never deletes a system block, and deletes any other', function () {
    newsletterManager();
    $header = NewsletterBlock::where('key', 'shared_header')->firstOrFail();
    $other = NewsletterBlock::create(['key' => 'retired', 'name' => 'Retired', 'type' => NewsletterBlockType::Text]);

    expect($header->is_system)->toBeTrue()
        ->and(NewsletterBlockResource::canDelete($header))->toBeFalse()
        ->and(NewsletterBlockResource::canDelete($other))->toBeTrue()
        ->and(NewsletterBlockResource::canEdit($header))->toBeTrue();
});

it('shows the aggregate click performance of a block on its page', function () {
    $block = NewsletterBlock::create(['key' => 'performance', 'name' => 'Performance banner', 'type' => NewsletterBlockType::Button, 'content' => ['button_label' => 'Shop now', 'button_url' => 'https://example.test/shop']]);
    $newsletter = Newsletter::create(['title' => 'P', 'subject' => 'P', 'body_html' => '', 'content_blocks' => app(ContentRenderer::class)->preparedBlocks([['type' => 'reusable', 'newsletter_block_id' => $block->id]])]);
    NewsletterLinkClick::create(['newsletter_id' => $newsletter->id, 'newsletter_block_id' => $block->id, 'block_key' => $newsletter->content_blocks[1]['block_key'], 'label' => 'Shop now', 'destination_url' => 'https://example.test/shop', 'clicked_at' => now()]);
    newsletterManager();

    $this->get(NewsletterBlockResource::getUrl('view', ['record' => $block]))->assertOk()->assertSee('Click Performance')->assertSee('Total Clicks');
});

it('gives every rich-text editor the tracked-link button, plus what the host adds', function () {
    expect(EditorSetup::toolbarButtons())->toBe(['insertTrackedLink'])
        ->and(EditorSetup::plugins())->toHaveCount(1)->and(EditorSetup::plugins()[0])->toBeInstanceOf(InsertTrackedLinkPlugin::class);

    config(['filagora-newsletters.editor.plugins' => [InsertTrackedLinkPlugin::class], 'filagora-newsletters.editor.toolbar_buttons' => ['insertMediaLibraryImage']]);

    expect(EditorSetup::toolbarButtons())->toBe(['insertMediaLibraryImage', 'insertTrackedLink'])->and(EditorSetup::plugins())->toHaveCount(2);
});

it('offers the images the media contract lists when picking an image block', function () {
    app()->instance(NewsletterMedia::class, new class implements NewsletterMedia
    {
        public function find(int $id): ?array
        {
            return null;
        }

        public function imageOptions(): array
        {
            return [3 => 'logo.png'];
        }
    });
    newsletterManager();

    Livewire::test(CreateNewsletterBlock::class)->fillForm(['type' => 'image'])->assertFormFieldExists('content.asset_id', checkFieldUsing: fn ($field) => $field->getOptions() === [3 => 'logo.png']);
});
