<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Console;

use Filagora\Newsletters\Enums\NewsletterStatus;
use Filagora\Newsletters\Models\Newsletter;
use Filagora\Newsletters\Services\CampaignSender;
use Filagora\Newsletters\Support\Models;
use Illuminate\Console\Command;
use RuntimeException;

class QueueScheduledCommand extends Command
{
    protected $signature = 'filagora:newsletters:queue-scheduled';

    protected $description = 'Queue due scheduled newsletter campaigns for sending.';

    /**
     * @var list<string>
     */
    protected $aliases = ['newsletter:queue-scheduled'];

    public function __construct(
        private readonly CampaignSender $newsletterCampaignSender,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $processed = 0;
        $queued = 0;
        $dispatched = 0;

        Models::class('newsletter')::query()
            ->where('status', NewsletterStatus::Scheduled->value)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->cursor()
            ->each(function (Newsletter $newsletter) use (&$processed, &$queued, &$dispatched): void {
                $processed++;

                try {
                    $result = $this->newsletterCampaignSender->queue($newsletter);
                    $queued += $result['queued'];
                    $dispatched += $result['dispatched'];
                    $this->info("Queued newsletter [{$newsletter->id}] with {$result['queued']} delivery record(s); dispatched {$result['dispatched']} recipient job(s).");
                } catch (RuntimeException $exception) {
                    $this->warn("Skipped newsletter [{$newsletter->id}]: {$exception->getMessage()}");
                }
            });

        $released = $this->newsletterCampaignSender->dispatchQueuedSends();
        $dispatched += $released;

        $this->info("Processed {$processed} due scheduled newsletter(s); queued {$queued} delivery record(s); dispatched {$dispatched} recipient job(s).");

        return self::SUCCESS;
    }
}
