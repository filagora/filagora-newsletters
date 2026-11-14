<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Tests\Support;

use Filagora\Newsletters\Contracts\EventHandler;

class SendgridEvents implements EventHandler
{
    public function handle(array $eventData): ?string
    {
        return 'sendgrid saw '.$eventData['event'];
    }
}
