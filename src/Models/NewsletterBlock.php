<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Models;

use Filagora\Core\Support\Tables;
use Filagora\Core\Support\UserModel;
use Filagora\Newsletters\Enums\NewsletterBlockType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterBlock extends Model
{
    public function getTable(): string
    {
        return Tables::name('newsletter_blocks');
    }

    protected $fillable = [
        'created_by',
        'updated_by',
        'key',
        'name',
        'type',
        'content',
        'is_system',
        'is_active',
    ];

    protected $attributes = [
        'is_system' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'type' => NewsletterBlockType::class,
            'content' => 'array',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
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
     * @param  Builder<NewsletterBlock>  $query
     * @return Builder<NewsletterBlock>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
