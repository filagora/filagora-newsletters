<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Contracts;

/**
 * Turns one delivery event from a mail provider's webhook into what the newsletter keeps: an
 * event row, the send's status, and, for a hard bounce, a complaint or an unsubscribe, the
 * subscriber's. The host owns the webhook endpoint (signature check, replay guard, anything
 * else the provider reports); it hands the decoded event here.
 */
interface EventHandler
{
    /**
     * Records the event when it belongs to a newsletter send. Answers a short note saying so, or
     * null when the event is not about a newsletter, so the host can handle it as it likes.
     *
     * @param  array<string, mixed>  $eventData
     */
    public function handle(array $eventData): ?string;
}
