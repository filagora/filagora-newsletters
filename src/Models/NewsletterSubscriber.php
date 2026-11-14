<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Models;

use Filagora\Core\Support\Tables;
use Filagora\Core\Support\UserModel;
use Filagora\Newsletters\Enums\NewsletterSubscriberStatus;
use Filagora\Newsletters\Support\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $email
 * @property string $normalized_email
 * @property string|null $suppression_hash
 * @property string|null $name
 * @property NewsletterSubscriberStatus|null $status
 * @property string $source
 * @property string|null $mailchimp_id
 * @property Carbon|null $subscribed_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $unsubscribed_at
 * @property Carbon|null $bounced_at
 * @property Carbon|null $complained_at
 * @property string|null $unsubscribe_reason
 * @property string|null $consent_text
 * @property string|null $consent_version
 * @property string|null $consent_ip
 * @property string|null $consent_user_agent
 * @property array<string, mixed>|null $metadata
 */
class NewsletterSubscriber extends Model
{
    public function getTable(): string
    {
        return Tables::name('newsletter_subscribers');
    }

    protected $fillable = [
        'user_id',
        'email',
        'normalized_email',
        'suppression_hash',
        'name',
        'status',
        'source',
        'mailchimp_id',
        'subscribed_at',
        'confirmed_at',
        'unsubscribed_at',
        'bounced_at',
        'complained_at',
        'unsubscribe_reason',
        'consent_text',
        'consent_version',
        'consent_ip',
        'consent_user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => NewsletterSubscriberStatus::class,
            'subscribed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'bounced_at' => 'datetime',
            'complained_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class(), 'user_id');
    }

    /**
     * @return HasMany<NewsletterSend, $this>
     */
    public function newsletterSends(): HasMany
    {
        return $this->hasMany(Models::class('send'), 'newsletter_subscriber_id');
    }

    /**
     * @param  Builder<NewsletterSubscriber>  $query
     * @return Builder<NewsletterSubscriber>
     */
    public function scopeSendable(Builder $query): Builder
    {
        return $query->where('status', NewsletterSubscriberStatus::Subscribed->value);
    }

    public function isSendable(): bool
    {
        return $this->status?->isSendable() ?? false;
    }
}
