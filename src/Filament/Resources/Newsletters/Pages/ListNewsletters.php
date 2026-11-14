<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Filament\Resources\Newsletters\Pages;

use Filagora\Newsletters\Filament\Resources\Newsletters\NewsletterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNewsletters extends ListRecords
{
    protected static string $resource = NewsletterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
