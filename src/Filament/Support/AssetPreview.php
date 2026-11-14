<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Filament\Support;

use Filagora\Newsletters\Contracts\NewsletterMedia;
use Illuminate\Support\HtmlString;

/**
 * The small picture shown under an image picker, found through the media contract.
 */
final class AssetPreview
{
    public static function exists(mixed $assetId): bool
    {
        return is_numeric($assetId) && app(NewsletterMedia::class)->find((int) $assetId) !== null;
    }

    public static function markup(mixed $assetId, int $maxWidth, int $maxHeight): HtmlString|string
    {
        if (! is_numeric($assetId)) {
            return '';
        }

        $asset = app(NewsletterMedia::class)->find((int) $assetId);

        if ($asset === null || blank($asset['url'])) {
            return __('filagora-newsletters::messages.settings.asset_unavailable');
        }

        $url = e((string) $asset['url']);
        $alt = e((string) $asset['alt']);

        return new HtmlString(<<<HTML
<div style="display:flex;align-items:center;justify-content:center;min-height:120px;padding:1rem;border:1px solid #d6d3d1;border-radius:0.85rem;background:#ffffff;">
    <img src="{$url}" alt="{$alt}" style="max-width:{$maxWidth}px;max-height:{$maxHeight}px;width:auto;height:auto;">
</div>
HTML);
    }
}
