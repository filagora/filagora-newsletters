<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Filament\Resources\Newsletters;

use BackedEnum;
use Filagora\Core\Filament\Concerns\GatedByAbility;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filagora\Newsletters\Filament\Resources\Newsletters\Pages\CreateNewsletter;
use Filagora\Newsletters\Filament\Resources\Newsletters\Pages\EditNewsletter;
use Filagora\Newsletters\Filament\Resources\Newsletters\Pages\ListNewsletters;
use Filagora\Newsletters\Filament\Resources\Newsletters\Pages\ViewNewsletter;
use Filagora\Newsletters\Filament\Resources\Newsletters\RelationManagers\NewsletterLinkClicksRelationManager;
use Filagora\Newsletters\Filament\Resources\Newsletters\RelationManagers\NewsletterSendsRelationManager;
use Filagora\Newsletters\Filament\Resources\Newsletters\Schemas\NewsletterForm;
use Filagora\Newsletters\Filament\Resources\Newsletters\Schemas\NewsletterInfolist;
use Filagora\Newsletters\Filament\Resources\Newsletters\Tables\NewslettersTable;
use Filagora\Newsletters\Models\Newsletter;
use Filagora\Newsletters\Support\Gates;
use Filagora\Newsletters\Support\Models;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class NewsletterResource extends Resource
{
    use GatedByAbility;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModel(): string
    {
        return Models::class('newsletter');
    }

    /**
     * Staff who manage newsletters can see and start one. A campaign can be changed only while it
     * is a draft or scheduled, and deleted only before it has ever been queued (deleting would
     * destroy the delivery history of a campaign that went out).
     */
    protected static function abilityFor(string|UnitEnum $action, ?Model $record): ?string
    {
        return match ($action) {
            'viewAny', 'view', 'create' => Gates::MANAGE,
            'update' => $record === null || ($record instanceof Newsletter && $record->status->isEditable()) ? Gates::MANAGE : null,
            'delete' => $record instanceof Newsletter && $record->isDeletable() ? Gates::MANAGE : null,
            default => null,
        };
    }

    public static function getNavigationLabel(): string
    {
        return __('filagora-newsletters::messages.newsletters.navigation_label');
    }

    public static function form(Schema $schema): Schema
    {
        return NewsletterForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NewsletterInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewslettersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            NewsletterSendsRelationManager::class,
            NewsletterLinkClicksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletters::route('/'),
            'create' => CreateNewsletter::route('/create'),
            'view' => ViewNewsletter::route('/{record}'),
            'edit' => EditNewsletter::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('newsletters');
    }
}
