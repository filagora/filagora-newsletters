<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Models;

use Filagora\Core\Support\Tables;
use Filagora\Newsletters\Support\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $newsletter_id
 * @property int|null $newsletter_send_id
 * @property int|null $newsletter_block_id
 * @property string|null $block_key
 * @property string|null $label
 * @property string $destination_url
 * @property Carbon $clicked_at
 * @property string|null $ip_hash
 */
class NewsletterLinkClick extends Model
{
    public function getTable(): string
    {
        return Tables::name('newsletter_link_clicks');
    }

    protected $fillable = [
        'newsletter_id',
        'newsletter_send_id',
        'newsletter_block_id',
        'block_key',
        'label',
        'destination_url',
        'clicked_at',
        'ip_hash',
    ];

    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
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
     * @return BelongsTo<NewsletterSend, $this>
     */
    public function send(): BelongsTo
    {
        return $this->belongsTo(Models::class('send'), 'newsletter_send_id');
    }

    /**
     * @return BelongsTo<NewsletterBlock, $this>
     */
    public function libraryBlock(): BelongsTo
    {
        return $this->belongsTo(Models::class('block'), 'newsletter_block_id');
    }
}
