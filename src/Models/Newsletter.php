<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Models;

use Filagora\Core\Support\Tables;
use Filagora\Core\Support\UserModel;
use Filagora\Newsletters\Enums\NewsletterStatus;
use Filagora\Newsletters\Support\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property string $title
 * @property string $subject
 * @property string|null $preheader
 * @property string $background_color
 * @property string $inner_background_color
 * @property string $body_html
 * @property string|null $body_text
 * @property list<array<string, mixed>>|null $content_blocks
 * @property NewsletterStatus $status
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $sent_at
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Newsletter extends Model
{
    public function getTable(): string
    {
        return Tables::name('newsletters');
    }

    protected $fillable = [
        'created_by',
        'updated_by',
        'title',
        'subject',
        'preheader',
        'background_color',
        'inner_background_color',
        'body_html',
        'body_text',
        'content_blocks',
        'status',
        'scheduled_at',
        'sent_at',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'draft',
        'background_color' => '#F4F1EA',
        'inner_background_color' => '#FBF8F2',
    ];

    protected function casts(): array
    {
        return [
            'status' => NewsletterStatus::class,
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'content_blocks' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(UserModel::class(), 'created_by');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(UserModel::class(), 'updated_by');
    }

    /**
     * @return HasMany<NewsletterSend, $this>
     */
    public function sends(): HasMany
    {
        return $this->hasMany(Models::class('send'), 'newsletter_id');
    }

    /**
     * @return HasMany<NewsletterLinkClick, $this>
     */
    public function linkClicks(): HasMany
    {
        return $this->hasMany(Models::class('link_click'), 'newsletter_id');
    }

    /**
     * A newsletter is only safe to delete before it has ever been queued for sending —
     * deleting cascades to `newsletter_sends`/`newsletter_events`/`newsletter_link_clicks`,
     * which would destroy real delivery/audit history for a campaign that actually went out.
     */
    public function isDeletable(): bool
    {
        return $this->status->isEditable() && ! $this->sends()->exists();
    }
}
