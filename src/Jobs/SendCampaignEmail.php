<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Jobs;

use Filagora\Newsletters\Services\CampaignMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SendCampaignEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $newsletterSendId,
    ) {
        $queue = config('filagora-newsletters.delivery.queue');

        if (is_string($queue) && $queue !== '') {
            $this->onQueue($queue);
        }
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('newsletter-send:'.$this->newsletterSendId))
                ->expireAfter(300),
        ];
    }

    public function handle(CampaignMailer $mailer): void
    {
        $mailer->sendCampaignRecipient($this->newsletterSendId);
    }
}
