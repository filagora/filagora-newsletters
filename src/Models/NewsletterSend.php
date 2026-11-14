<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Models;

use Filagora\Core\Support\Tables;
use Filagora\Newsletters\Enums\NewsletterSendStatus;
use Filagora\Newsletters\Support\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $newsletter_id
 * @property int|null $newsletter_subscriber_id
 * @property string $email
 * @property string $normalized_email
 * @property string|null $name
 * @property NewsletterSendStatus $status
 * @property Carbon|null $queued_at
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $skipped_at
 * @property string|null $provider_message_id
 * @property string|null $error
 * @property array<string, mixed>|null $metadata
 */
class NewsletterSend extends Model
{
    public function getTable(): string
    {
        return Tables::name('newsletter_sends');
    }

    protected $fillable = [
        'newsletter_id',
        'newsletter_subscriber_id',
        'email',
        'normalized_email',
        'name',
        'status',
        'queued_at',
        'dispatched_at',
        'sent_at',
        'failed_at',
        'skipped_at',
        'provider_message_id',
        'error',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'queued',
    ];

    protected function casts(): array
    {
        return [
            'status' => NewsletterSendStatus::class,
            'queued_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'skipped_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Newsletter, $this>
     */
    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Models::class('newsletter'), 'newsletter_id');
    }

    /**
     * @return BelongsTo<NewsletterSubscriber, $this>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Models::class('subscriber'), 'newsletter_subscriber_id');
    }

    /**
     * @return HasMany<NewsletterEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Models::class('event'), 'newsletter_send_id');
    }

    /**
     * @return HasMany<NewsletterLinkClick, $this>
     */
    public function linkClicks(): HasMany
    {
        return $this->hasMany(Models::class('link_click'), 'newsletter_send_id');
    }
}
