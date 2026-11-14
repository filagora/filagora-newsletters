<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Support;

/**
 * The access keys behind the package's abilities, as set in `filagora-newsletters.gates`
 * (`ability => key`). A host remaps a key there; the ability keeps its name.
 */
final class Gates
{
    public const MANAGE = 'filagora.newsletters.manage';

    public static function key(string $ability): string
    {
        $key = ((array) config('filagora-newsletters.gates', []))[$ability] ?? null;

        return is_string($key) && $key !== '' ? $key : $ability;
    }
}
