<?php

declare(strict_types=1);

use Filagora\Newsletters\Enums\NewsletterSendStatus;
use Filagora\Newsletters\Enums\NewsletterStatus;
use Filagora\Newsletters\Enums\NewsletterSubscriberStatus;
use Filagora\Newsletters\Jobs\SendCampaignEmail;
use Filagora\Newsletters\Mail\CampaignMail;
use Filagora\Newsletters\Models\Newsletter;
use Filagora\Newsletters\Models\NewsletterSend;
use Filagora\Newsletters\Models\NewsletterSubscriber;
use Filagora\Newsletters\Services\CampaignMailer;
use Filagora\Newsletters\Services\CampaignSender;
use Filagora\Newsletters\Services\ContentRenderer;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

function readySubscriber(string $email, array $attributes = []): NewsletterSubscriber
{
    return NewsletterSubscriber::create(array_merge(['email' => $email, 'normalized_email' => strtolower($email), 'name' => 'Sub', 'status' => NewsletterSubscriberStatus::Subscribed, 'subscribed_at' => now()], $attributes));
}

function draftNewsletter(array $attributes = []): Newsletter
{
    return Newsletter::create(array_merge([
        'title' => 'Spring', 'subject' => 'Spring news', 'body_html' => '',
        'content_blocks' => app(ContentRenderer::class)->preparedBlocks([['type' => 'text', 'heading' => 'Hello']]),
    ], $attributes));
}

beforeEach(function () {
    config(['mail.default' => 'array']);
});

it('queues one send per sendable subscriber, skips suppressed and unconfirmed people, and marks the newsletter as sending', function () {
    Queue::fake();
    readySubscriber('a@example.test');
    readySubscriber('b@example.test');
    readySubscriber('pending@example.test', ['status' => NewsletterSubscriberStatus::Pending]);
    readySubscriber('off@example.test', ['status' => NewsletterSubscriberStatus::Unsubscribed]);
    readySubscriber('erased@example.test', ['suppression_hash' => str_repeat('a', 64)]);
    $newsletter = draftNewsletter();

    $result = app(CampaignSender::class)->queue($newsletter);

    expect($result)->toBe(['queued' => 2, 'dispatched' => 2, 'skipped' => 0])
        ->and($newsletter->refresh()->status)->toBe(NewsletterStatus::Sending)
        ->and(NewsletterSend::orderBy('id')->pluck('email')->all())->toBe(['a@example.test', 'b@example.test'])
        ->and(NewsletterSend::first()->status)->toBe(NewsletterSendStatus::Queued);

    Queue::assertPushed(SendCampaignEmail::class, 2);
});

it('leaves a newsletter with nobody to send to as it was', function () {
    Queue::fake();
    $newsletter = draftNewsletter();

    expect(app(CampaignSender::class)->queue($newsletter))->toBe(['queued' => 0, 'dispatched' => 0, 'skipped' => 0])
        ->and($newsletter->refresh()->status)->toBe(NewsletterStatus::Draft);
});

it('refuses to queue a newsletter that is not a draft or scheduled, or that already has deliveries', function () {
    Queue::fake();
    readySubscriber('a@example.test');

    expect(fn () => app(CampaignSender::class)->queue(draftNewsletter(['status' => NewsletterStatus::Sent])))->toThrow(RuntimeException::class, 'Only draft or scheduled');

    $newsletter = draftNewsletter();
    NewsletterSend::create(['newsletter_id' => $newsletter->id, 'email' => 'x@example.test', 'normalized_email' => 'x@example.test']);

    expect(fn () => app(CampaignSender::class)->queue($newsletter))->toThrow(RuntimeException::class, 'already has delivery records');
});

it('releases no more recipient jobs than the daily limit allows, and the rest on a later day', function () {
    Queue::fake();
    config(['filagora-newsletters.delivery.campaign_daily_limit' => 2]);
    foreach (['a', 'b', 'c'] as $name) {
        readySubscriber("{$name}@example.test");
    }
    $newsletter = draftNewsletter();

    $sender = app(CampaignSender::class);

    expect($sender->queue($newsletter)['dispatched'])->toBe(2)
        ->and($sender->remainingDailyCapacity())->toBe(0)
        ->and($sender->dispatchQueuedSends())->toBe(0);

    $this->travel(1)->days();

    expect($sender->remainingDailyCapacity())->toBe(2)
        ->and($sender->dispatchQueuedSends())->toBe(1);

    config(['filagora-newsletters.delivery.campaign_daily_limit' => 0]);
    expect($sender->remainingDailyCapacity())->toBe(0);
});

it('only releases recipients of newsletters that are being sent', function () {
    Queue::fake();
    $newsletter = draftNewsletter(['status' => NewsletterStatus::Canceled]);
    $subscriber = readySubscriber('a@example.test');
    NewsletterSend::create(['newsletter_id' => $newsletter->id, 'newsletter_subscriber_id' => $subscriber->id, 'email' => 'a@example.test', 'normalized_email' => 'a@example.test']);

    expect(app(CampaignSender::class)->dispatchQueuedSends())->toBe(0);
});

it('sends to a recipient with their own unsubscribe link, marks it sent and completes the newsletter', function () {
    Mail::fake();
    $subscriber = readySubscriber('a@example.test');
    $newsletter = draftNewsletter(['status' => NewsletterStatus::Sending]);
    $send = NewsletterSend::create(['newsletter_id' => $newsletter->id, 'newsletter_subscriber_id' => $subscriber->id, 'email' => 'a@example.test', 'normalized_email' => 'a@example.test']);

    app(CampaignMailer::class)->sendCampaignRecipient($send->id);

    Mail::assertSent(CampaignMail::class, fn (CampaignMail $mail) => $mail->hasTo('a@example.test')
        && $mail->newsletterSendId === $send->id
        && str_contains($mail->unsubscribeUrl, '/newsletter/unsubscribe/'.$subscriber->id));

    expect($send->refresh()->status)->toBe(NewsletterSendStatus::Sent)
        ->and($send->sent_at)->not->toBeNull()
        ->and($newsletter->refresh()->status)->toBe(NewsletterStatus::Sent)
        ->and($newsletter->sent_at)->not->toBeNull();
});

it('does not send twice to a recipient already sent to', function () {
    Mail::fake();
    $subscriber = readySubscriber('a@example.test');
    $newsletter = draftNewsletter(['status' => NewsletterStatus::Sending]);
    $send = NewsletterSend::create(['newsletter_id' => $newsletter->id, 'newsletter_subscriber_id' => $subscriber->id, 'email' => 'a@example.test', 'normalized_email' => 'a@example.test', 'status' => NewsletterSendStatus::Sent]);

    app(CampaignMailer::class)->sendCampaignRecipient($send->id);

    Mail::assertNothingSent();
});

it('skips a recipient who unsubscribed after being queued, and leaves the newsletter open while others are waiting', function () {
    Mail::fake();
    $gone = readySubscriber('gone@example.test');
    $waiting = readySubscriber('wait@example.test');
    $newsletter = draftNewsletter(['status' => NewsletterStatus::Sending]);
    $send = NewsletterSend::create(['newsletter_id' => $newsletter->id, 'newsletter_subscriber_id' => $gone->id, 'email' => 'gone@example.test', 'normalized_email' => 'gone@example.test']);
    NewsletterSend::create(['newsletter_id' => $newsletter->id, 'newsletter_subscriber_id' => $waiting->id, 'email' => 'wait@example.test', 'normalized_email' => 'wait@example.test']);
    $gone->update(['status' => NewsletterSubscriberStatus::Unsubscribed]);

    app(CampaignMailer::class)->sendCampaignRecipient($send->id);

    Mail::assertNothingSent();
    expect($send->refresh()->status)->toBe(NewsletterSendStatus::Skipped)
        ->and($send->error)->toBe('Subscriber is no longer sendable.')
        ->and($newsletter->refresh()->status)->toBe(NewsletterStatus::Sending);
});

it('records a failed send with the reason and lets the job retry', function () {
    $subscriber = readySubscriber('a@example.test');
    $newsletter = draftNewsletter(['status' => NewsletterStatus::Sending]);
    $send = NewsletterSend::create(['newsletter_id' => $newsletter->id, 'newsletter_subscriber_id' => $subscriber->id, 'email' => 'a@example.test', 'normalized_email' => 'a@example.test']);

    Mail::shouldReceive('mailer')->andThrow(new RuntimeException('Mail server unreachable'));

    expect(fn () => app(CampaignMailer::class)->sendCampaignRecipient($send->id))->toThrow(RuntimeException::class, 'Mail server unreachable');

    expect($send->refresh()->status)->toBe(NewsletterSendStatus::Failed)
        ->and($send->error)->toBe('Mail server unreachable')
        ->and($send->failed_at)->not->toBeNull();
});

it('sends a preview to any address without touching the subscribers', function () {
    Mail::fake();

    app(CampaignMailer::class)->sendPreview(draftNewsletter(), 'editor@example.test');

    Mail::assertSent(CampaignMail::class, fn (CampaignMail $mail) => $mail->hasTo('editor@example.test') && $mail->preview);
    expect(NewsletterSend::count())->toBe(0);
});

it('runs a recipient job on the configured queue, one at a time per recipient, and retries with a back-off', function () {
    config(['filagora-newsletters.delivery.queue' => 'mail']);
    $job = new SendCampaignEmail(12);

    expect($job->queue)->toBe('mail')
        ->and($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([30, 120, 300])
        ->and($job->middleware()[0]->key)->toBe('newsletter-send:12');

    config(['filagora-newsletters.delivery.queue' => null]);
    expect((new SendCampaignEmail(1))->queue)->toBeNull();
});

it('queues due scheduled campaigns and not future ones, under both command names', function () {
    Queue::fake();
    readySubscriber('a@example.test');
    $due = draftNewsletter(['status' => NewsletterStatus::Scheduled, 'scheduled_at' => now()->subMinute()]);
    $later = draftNewsletter(['status' => NewsletterStatus::Scheduled, 'scheduled_at' => now()->addHour(), 'title' => 'Later']);
    $draft = draftNewsletter(['scheduled_at' => now()->subMinute(), 'title' => 'Draft']);

    $this->artisan('newsletter:queue-scheduled')->expectsOutputToContain('Processed 1 due scheduled newsletter(s)')->assertExitCode(0);

    expect($due->refresh()->status)->toBe(NewsletterStatus::Sending)
        ->and($later->refresh()->status)->toBe(NewsletterStatus::Scheduled)
        ->and($draft->refresh()->status)->toBe(NewsletterStatus::Draft);

    $this->artisan('filagora:newsletters:queue-scheduled')->expectsOutputToContain('Processed 0')->assertExitCode(0);
});

it('puts the queue command on the scheduler every minute', function () {
    $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command, 'filagora:newsletters:queue-scheduled'));

    expect($events)->toHaveCount(1)->and($events->first()->expression)->toBe('* * * * *')->and($events->first()->withoutOverlapping)->toBeTrue();
});

it('exports only sendable subscribers, each with an unsubscribe link', function () {
    $path = sys_get_temp_dir().'/newsletter-export-'.uniqid().'.csv';
    readySubscriber('on@example.test', ['confirmed_at' => now()]);
    readySubscriber('off@example.test', ['status' => NewsletterSubscriberStatus::Unsubscribed]);
    readySubscriber('erased@example.test', ['suppression_hash' => str_repeat('b', 64)]);

    $this->artisan('newsletter:export-subscribers', ['path' => $path])->expectsOutputToContain('Exported 1 sendable')->assertExitCode(0);

    $rows = array_map('str_getcsv', file($path, FILE_IGNORE_NEW_LINES));
    unlink($path);

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['email', 'name', 'status', 'source', 'subscribed_at', 'confirmed_at', 'unsubscribe_url'])
        ->and($rows[1][0])->toBe('on@example.test')
        ->and($rows[1][2])->toBe('subscribed')
        ->and($rows[1][6])->toContain('/newsletter/unsubscribe/');
});

it('refuses to export to a path it cannot write', function () {
    $this->artisan('newsletter:export-subscribers', ['path' => '/proc/nope/export.csv'])->assertExitCode(1);
});

it('creates a missing destination directory for the export', function () {
    $directory = sys_get_temp_dir().'/newsletter-export-'.uniqid();
    readySubscriber('on@example.test');

    $this->artisan('newsletter:export-subscribers', ['path' => $directory.'/nested/export.csv'])->assertExitCode(0);

    expect(is_file($directory.'/nested/export.csv'))->toBeTrue();
    unlink($directory.'/nested/export.csv');
    rmdir($directory.'/nested');
    rmdir($directory);
});
