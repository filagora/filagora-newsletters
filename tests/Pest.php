<?php

declare(strict_types=1);

use Filagora\Newsletters\Support\Gates;
use Filagora\Newsletters\Tests\Support\GrantedKeys;
use Filagora\Newsletters\Tests\Support\TestUser;
use Filagora\Newsletters\Tests\Switches\RoutesOffTestCase;
use Filagora\Newsletters\Tests\TestCase;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/**
 * A person who may manage newsletters, signed in on the admin panel.
 */
function newsletterManager(string $name = 'Manager'): TestUser
{
    $user = TestUser::make($name);
    app(GrantedKeys::class)->give($user, Gates::MANAGE);
    actingAs($user);
    Filament::setCurrentPanel('admin');

    return $user;
}

pest()->extend(RoutesOffTestCase::class)->use(RefreshDatabase::class)->in('Switches/RoutesOff');
