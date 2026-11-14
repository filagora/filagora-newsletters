<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Filament\RichEditor;

use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;

/**
 * The plugins and toolbar buttons the newsletter rich-text editors get: the tracked-link button,
 * and whatever the host adds under `editor` in the config (for example its media library's
 * image button).
 */
final class EditorSetup
{
    /**
     * @return list<RichContentPlugin>
     */
    public static function plugins(): array
    {
        $plugins = [];

        foreach ((array) config('filagora-newsletters.editor.plugins', []) as $class) {
            if (is_string($class) && is_a($class, RichContentPlugin::class, true)) {
                $plugins[] = app($class);
            }
        }

        $plugins[] = InsertTrackedLinkPlugin::make();

        return $plugins;
    }

    /**
     * @return list<string>
     */
    public static function toolbarButtons(): array
    {
        $buttons = array_values(array_filter((array) config('filagora-newsletters.editor.toolbar_buttons', []), is_string(...)));

        return [...$buttons, 'insertTrackedLink'];
    }
}
