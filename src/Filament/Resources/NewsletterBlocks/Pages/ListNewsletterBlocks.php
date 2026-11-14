<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Filament\Resources\NewsletterBlocks\Pages;

use Filagora\Newsletters\Filament\Resources\NewsletterBlocks\NewsletterBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNewsletterBlocks extends ListRecords
{
    protected static string $resource = NewsletterBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
