<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Filament\Resources\NewsletterBlocks\Schemas;

use Filagora\Newsletters\Contracts\NewsletterMedia;
use Filagora\Newsletters\Enums\NewsletterBlockType;
use Filagora\Newsletters\Filament\RichEditor\EditorSetup;
use Filagora\Newsletters\Models\NewsletterBlock;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class NewsletterBlockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Block')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (callable $set, ?string $state, ?NewsletterBlock $record): void {
                                if ($record instanceof NewsletterBlock) {
                                    return;
                                }

                                $set('key', Str::slug($state ?? ''));
                            }),
                        TextInput::make('key')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?NewsletterBlock $record): bool => $record instanceof NewsletterBlock)
                            ->dehydrated()
                            ->helperText('Stable identifier used by campaigns that reference this block. Cannot be changed after creation.'),
                        Select::make('type')
                            ->options(NewsletterBlockType::selectableOptions())
                            ->required()
                            ->live()
                            ->disabled(fn (?NewsletterBlock $record): bool => $record instanceof NewsletterBlock && $record->is_system),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive blocks are hidden from campaign authoring but keep rendering in campaigns that already reference them.'),
                    ])
                    ->columns(2),
                Section::make('System block')
                    ->schema([
                        Placeholder::make('system_block_note')
                            ->label('')
                            ->content('This block\'s content is driven by Newsletter Settings (logo, eyebrow, footer copy, unsubscribe link) rather than fields here.')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (?NewsletterBlock $record): bool => $record instanceof NewsletterBlock && $record->is_system),
                Section::make('Content')
                    ->schema([
                        TextInput::make('content.heading')
                            ->label('Heading')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('type') === 'text'),
                        RichEditor::make('content.body_html')
                            ->label('Content')
                            ->extraInputAttributes(['style' => 'min-height: 16rem;'])
                            ->columnSpanFull()
                            ->required(fn (Get $get): bool => $get('type') === 'text')
                            ->visible(fn (Get $get): bool => $get('type') === 'text')
                            ->plugins(EditorSetup::plugins())
                            ->enableToolbarButtons(EditorSetup::toolbarButtons()),
                        Select::make('content.asset_id')
                            ->label('Image Asset')
                            ->options(fn (): array => self::imageAssetOptions())
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get): bool => $get('type') === 'image')
                            ->visible(fn (Get $get): bool => $get('type') === 'image'),
                        TextInput::make('content.asset_alt')
                            ->label('Image Alt Text')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('type') === 'image'),
                        Textarea::make('content.caption')
                            ->label('Caption')
                            ->rows(3)
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => $get('type') === 'image'),
                        TextInput::make('content.button_label')
                            ->label('Button Label')
                            ->required(fn (Get $get): bool => $get('type') === 'button')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('type') === 'button'),
                        TextInput::make('content.button_url')
                            ->label('Button URL')
                            ->url()
                            ->required(fn (Get $get): bool => $get('type') === 'button')
                            ->visible(fn (Get $get): bool => $get('type') === 'button'),
                    ])
                    ->columns(2)
                    ->visible(fn (?NewsletterBlock $record): bool => ! ($record instanceof NewsletterBlock && $record->is_system)),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function imageAssetOptions(): array
    {
        return app(NewsletterMedia::class)->imageOptions();
    }
}
