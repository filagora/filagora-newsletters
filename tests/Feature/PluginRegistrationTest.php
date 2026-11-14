<?php

declare(strict_types=1);

use Filagora\Newsletters\NewslettersPlugin;
use Filament\Panel;

it('merges the package config', function () {
    expect(config('filagora-newsletters.navigation_group'))->toBe('Newsletters');
});

it('registers on a panel', function () {
    $panel = Panel::make()->id('admin')->plugin(NewslettersPlugin::make());

    expect($panel->hasPlugin('filagora-newsletters'))->toBeTrue();
});
