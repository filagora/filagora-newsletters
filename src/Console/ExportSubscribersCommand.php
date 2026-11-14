<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Console;

use Filagora\Newsletters\Models\NewsletterSubscriber;
use Filagora\Newsletters\Services\Subscribers;
use Filagora\Newsletters\Support\Models;
use Illuminate\Console\Command;
use SplFileObject;

class ExportSubscribersCommand extends Command
{
    /**
     * @var list<string>
     */
    protected $aliases = ['newsletter:export-subscribers'];

    protected $signature = 'filagora:newsletters:export-subscribers
        {path : Destination CSV path for the sendable subscriber export}';

    protected $description = 'Export sendable newsletter subscribers to CSV for an external sending provider.';

    public function __construct(
        private readonly Subscribers $newsletterSubscriberService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $this->error("Unable to create export directory [{$directory}].");

            return self::FAILURE;
        }

        if ((file_exists($path) && ! is_writable($path)) || ! is_writable($directory)) {
            $this->error("Export path [{$path}] is not writable.");

            return self::FAILURE;
        }

        $file = new SplFileObject($path, 'w');
        $file->fputcsv([
            'email',
            'name',
            'status',
            'source',
            'subscribed_at',
            'confirmed_at',
            'unsubscribe_url',
        ]);

        $count = 0;

        Models::class('subscriber')::query()
            ->sendable()
            ->whereNull('suppression_hash')
            ->orderBy('id')
            ->cursor()
            ->each(function (NewsletterSubscriber $subscriber) use ($file, &$count): void {
                $file->fputcsv([
                    $subscriber->email,
                    $subscriber->name,
                    $subscriber->status?->value,
                    $subscriber->source,
                    $subscriber->subscribed_at?->toIso8601String(),
                    $subscriber->confirmed_at?->toIso8601String(),
                    $this->newsletterSubscriberService->signedUnsubscribeUrl($subscriber),
                ]);

                $count++;
            });

        $this->info("Exported {$count} sendable newsletter subscriber(s) to {$path}.");

        return self::SUCCESS;
    }
}
