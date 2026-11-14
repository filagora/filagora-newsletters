<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Http\Controllers;

use Filagora\Newsletters\Services\ContentRenderer;
use Filagora\Newsletters\Support\Models;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

/**
 * A signed click on a tracked link: records it, then sends the reader to the link's current
 * destination (read live from the campaign, so a corrected link works for mail already sent).
 */
class LinkClickController extends Controller
{
    public function __invoke(Request $request, string $newsletter, string $blockKey, ContentRenderer $renderer): RedirectResponse
    {
        $newsletter = Models::class('newsletter')::query()->findOrFail($newsletter);
        $link = $renderer->resolveBlockLink($newsletter, $blockKey);

        abort_if($link === null || ! Str::startsWith($link['url'], ['http://', 'https://']), 404);

        $sendId = $request->integer('send') ?: null;
        $send = $sendId !== null ? Models::class('send')::query()->find($sendId) : null;

        Models::class('link_click')::query()->create([
            'newsletter_id' => $newsletter->id,
            'newsletter_send_id' => $send?->id,
            'newsletter_block_id' => $link['newsletter_block_id'],
            'block_key' => $blockKey,
            'label' => $link['label'],
            'destination_url' => $link['url'],
            'clicked_at' => now(),
            'ip_hash' => hash('sha256', $request->ip().config('app.key')),
        ]);

        return redirect()->away($link['url']);
    }
}
