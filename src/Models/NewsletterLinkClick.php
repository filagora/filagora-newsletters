<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Models;

use Filagora\Core\Support\Tables;
use Filagora\Newsletters\Support\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * @return BelongsTo<Model, $this>
     */
    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Models::class('newsletter'), 'newsletter_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function send(): BelongsTo
    {
        return $this->belongsTo(Models::class('send'), 'newsletter_send_id');
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function libraryBlock(): BelongsTo
    {
        return $this->belongsTo(Models::class('block'), 'newsletter_block_id');
    }
}
