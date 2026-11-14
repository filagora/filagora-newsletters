<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Media;

use Filagora\Newsletters\Contracts\NewsletterMedia;

/**
 * The default: no media library, so no image is ever found.
 */
final class NoMedia implements NewsletterMedia
{
    public function find(int $id): ?array
    {
        return null;
    }

    public function imageOptions(): array
    {
        return [];
    }
}
