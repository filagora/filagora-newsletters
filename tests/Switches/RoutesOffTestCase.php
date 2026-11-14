<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Tests\Switches;

use Filagora\Newsletters\Tests\TestCase;

/**
 * A host that serves its own newsletter pages and previews: every route switch is off.
 */
abstract class RoutesOffTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('filagora-newsletters.routes.enabled', false);
        $app['config']->set('filagora-newsletters.preview.enabled', false);
    }
}
