<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Tests\Support;

use Filagora\Core\FilagoraCorePlugin;
use Filagora\Newsletters\NewslettersPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(FilagoraCorePlugin::make())
            ->plugin(NewslettersPlugin::make());
    }
}
