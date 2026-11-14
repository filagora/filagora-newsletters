<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Services;

use Filagora\Newsletters\Contracts\EventHandler;

/**
 * Finds the handler for a mail provider's webhook events, by the provider's name. Mailgun and
 * Mailtrap are built in; a host adds another under `events.handlers` in the config, naming a
 * class that implements `EventHandler`.
 */
class DeliveryEvents
{
    public function handler(string $provider): ?EventHandler
    {
        $class = config('filagora-newsletters.events.handlers.'.strtolower($provider));

        return is_string($class) ? app($class) : null;
    }

    /**
     * Hands one decoded event to the provider's handler. Null when the provider has no handler or
     * the event is not about a newsletter.
     *
     * @param  array<string, mixed>  $eventData
     */
    public function handle(string $provider, array $eventData): ?string
    {
        return $this->handler($provider)?->handle($eventData);
    }
}
