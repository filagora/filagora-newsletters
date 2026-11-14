<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Models;

use Filagora\Core\Support\Tables;
use Filagora\Newsletters\Enums\NewsletterEventType;
use Filagora\Newsletters\Support\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $newsletter_send_id
 * @property NewsletterEventType $type
 * @property Carbon $occurred_at
 * @property array<string, mixed>|null $payload
 */
class NewsletterEvent extends Model
{
    public function getTable(): string
    {
        return Tables::name('newsletter_events');
    }

    protected $fillable = [
        'newsletter_send_id',
        'type',
        'occurred_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'type' => NewsletterEventType::class,
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<NewsletterSend, $this>
     */
    public function send(): BelongsTo
    {
        return $this->belongsTo(Models::class('send'), 'newsletter_send_id');
    }
}
