<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Services;

use Filagora\Newsletters\Models\Newsletter;
use Illuminate\Support\Facades\URL;

class LinkTracker
{
    public function trackedUrl(Newsletter $newsletter, string $blockKey, ?int $newsletterSendId): string
    {
        $parameters = [
            'newsletter' => $newsletter->getKey(),
            'blockKey' => $blockKey,
        ];

        if ($newsletterSendId !== null) {
            $parameters['send'] = $newsletterSendId;
        }

        return URL::signedRoute('newsletter.link-click', $parameters);
    }
}
