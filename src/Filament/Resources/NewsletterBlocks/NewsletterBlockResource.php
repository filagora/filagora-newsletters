<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Filament\Resources\NewsletterBlocks;

use BackedEnum;
use Filagora\Core\Filament\Concerns\GatedByAbility;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filagora\Newsletters\Filament\Resources\NewsletterBlocks\Pages\CreateNewsletterBlock;
use Filagora\Newsletters\Filament\Resources\NewsletterBlocks\Pages\EditNewsletterBlock;
use Filagora\Newsletters\Filament\Resources\NewsletterBlocks\Pages\ListNewsletterBlocks;
use Filagora\Newsletters\Filament\Resources\NewsletterBlocks\Pages\ViewNewsletterBlock;
use Filagora\Newsletters\Filament\Resources\NewsletterBlocks\Schemas\NewsletterBlockForm;
use Filagora\Newsletters\Filament\Resources\NewsletterBlocks\Schemas\NewsletterBlockInfolist;
use Filagora\Newsletters\Filament\Resources\NewsletterBlocks\Tables\NewsletterBlocksTable;
use Filagora\Newsletters\Support\Gates;
use Filagora\Newsletters\Support\Models;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class NewsletterBlockResource extends Resource
{
    use GatedByAbility;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModel(): string
    {
        return Models::class('block');
    }

    /**
     * Staff who manage newsletters can see, add and change blocks, and delete one that is not a
     * system block (the shared header and footer stay).
     */
    protected static function abilityFor(string|UnitEnum $action, ?Model $record): ?string
    {
        return match ($action) {
            'viewAny', 'view', 'create', 'update' => Gates::MANAGE,
            'delete' => $record !== null && ! $record->getAttribute('is_system') ? Gates::MANAGE : null,
            default => null,
        };
    }

    public static function getNavigationLabel(): string
    {
        return __('filagora-newsletters::messages.blocks.navigation_label');
    }

    public static function form(Schema $schema): Schema
    {
        return NewsletterBlockForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NewsletterBlockInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewsletterBlocksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterBlocks::route('/'),
            'create' => CreateNewsletterBlock::route('/create'),
            'view' => ViewNewsletterBlock::route('/{record}'),
            'edit' => EditNewsletterBlock::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('newsletters');
    }
}
