<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Filament\Pages;

use BackedEnum;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filagora\Newsletters\Filament\Support\AssetPreview;
use Filagora\Newsletters\Services\BrandingSettings;
use Filagora\Newsletters\Support\Gates;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * @property Schema $form
 */
class NewsletterSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'newsletter-settings';

    protected string $view = 'filagora-newsletters::filament.pages.newsletter-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('filagora-newsletters::messages.settings.navigation_label');
    }

    public static function canAccess(): bool
    {
        return Gate::allows(Gates::MANAGE);
    }

    public function getTitle(): string
    {
        return __('filagora-newsletters::messages.settings.title');
    }

    public function mount(BrandingSettings $brandingService): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill($brandingService->formData());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Branding')
                    ->description('Shared header, logo, and footer content used by all newsletter campaigns.')
                    ->schema([
                        TextInput::make('brand_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('header_eyebrow')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Small shared label shown above the newsletter subject.'),
                        Select::make('logo_asset_id')
                            ->label('Logo Asset')
                            ->options(fn (): array => app(BrandingSettings::class)->imageAssetOptions())
                            ->searchable()
                            ->preload()
                            ->placeholder('No shared logo'),
                        Placeholder::make('logo_asset_preview')
                            ->label('Selected Logo')
                            ->content(fn (Get $get): HtmlString|string => $this->logoAssetPreviewMarkup($get('logo_asset_id')))
                            ->visible(fn (Get $get): bool => is_numeric($get('logo_asset_id')))
                            ->columnSpanFull(),
                        TextInput::make('logo_alt')
                            ->maxLength(255)
                            ->helperText('Alt text used when a shared logo is configured.'),
                        Textarea::make('footer_text')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('manage_link_label')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Text used for the shared unsubscribe/manage link.'),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(BrandingSettings $brandingService): void
    {
        $state = $this->form->getState();
        $brandingService->save($state);

        Notification::make()
            ->title('Newsletter settings saved')
            ->success()
            ->send();
    }

    private function logoAssetPreviewMarkup(mixed $assetId): HtmlString|string
    {
        return AssetPreview::markup($assetId, 220, 96);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('newsletters');
    }
}
