<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Contracts;

/**
 * Where a newsletter gets its images: the logo and the image blocks name a media item by number.
 * The package has no media library of its own, so a host binds this to its own (the Zen Cart
 * profile uses the CMS media library). The default knows no images.
 */
interface NewsletterMedia
{
    /**
     * The public address and alternative text of one image, or null when it is not there.
     *
     * @return array{url: string|null, alt: string|null}|null
     */
    public function find(int $id): ?array;

    /**
     * The images an editor can choose from, as number => label.
     *
     * @return array<int, string>
     */
    public function imageOptions(): array;
}
