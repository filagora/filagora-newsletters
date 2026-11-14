<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Support;

/**
 * The class-name markers a rich-text editor puts on a link to ask for click tracking. The content
 * renderer reads them; the editor plugin that writes them lives with the Filament screens.
 */
final class TrackedLinkMarkers
{
    public const CLASS_MARKER = 'nl-tracked-link';

    public const ID_MARKER_PREFIX = 'nl-tracked-link-id--';

    public const STYLE_MARKER_PREFIX = 'nl-tracked-link--';
}
