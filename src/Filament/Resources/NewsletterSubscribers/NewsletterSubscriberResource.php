<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Filament\Resources\NewsletterSubscribers;

use BackedEnum;
use Filagora\Core\Filament\Concerns\GatedByAbility;
use Filagora\Core\Navigation\NavigationGroupRegistry;
use Filagora\Newsletters\Filament\Resources\NewsletterSubscribers\Pages\CreateNewsletterSubscriber;
use Filagora\Newsletters\Filament\Resources\NewsletterSubscribers\Pages\EditNewsletterSubscriber;
use Filagora\Newsletters\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use Filagora\Newsletters\Filament\Resources\NewsletterSubscribers\Pages\ViewNewsletterSubscriber;
use Filagora\Newsletters\Filament\Resources\NewsletterSubscribers\Schemas\NewsletterSubscriberForm;
use Filagora\Newsletters\Filament\Resources\NewsletterSubscribers\Schemas\NewsletterSubscriberInfolist;
use Filagora\Newsletters\Filament\Resources\NewsletterSubscribers\Tables\NewsletterSubscribersTable;
use Filagora\Newsletters\Support\Gates;
use Filagora\Newsletters\Support\Models;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class NewsletterSubscriberResource extends Resource
{
    use GatedByAbility;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'email';

    public static function getModel(): string
    {
        return Models::class('subscriber');
    }

    /**
     * Staff who manage newsletters can see, add and change subscribers; nobody deletes one here
     * (a subscriber is unsubscribed, which keeps the consent record).
     */
    protected static function abilityFor(string|UnitEnum $action, ?Model $record): ?string
    {
        return in_array($action, ['viewAny', 'view', 'create', 'update'], true) ? Gates::MANAGE : null;
    }

    public static function getNavigationLabel(): string
    {
        return __('filagora-newsletters::messages.subscribers.navigation_label');
    }

    public static function form(Schema $schema): Schema
    {
        return NewsletterSubscriberForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return NewsletterSubscriberInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewsletterSubscribersTable::configure($table);
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
            'index' => ListNewsletterSubscribers::route('/'),
            'create' => CreateNewsletterSubscriber::route('/create'),
            'view' => ViewNewsletterSubscriber::route('/{record}'),
            'edit' => EditNewsletterSubscriber::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return app(NavigationGroupRegistry::class)->label('newsletters');
    }
}
