<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Support;

use Filagora\Newsletters\Models\Newsletter;
use Filagora\Newsletters\Models\NewsletterBlock;
use Filagora\Newsletters\Models\NewsletterEvent;
use Filagora\Newsletters\Models\NewsletterLinkClick;
use Filagora\Newsletters\Models\NewsletterSend;
use Filagora\Newsletters\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Resolves the package's model classes. The package never names a model class directly:
 * a host swaps one under `filagora.models.<key>` (or `filagora-newsletters.models.<key>`) and
 * every relation and query follows.
 */
final class Models
{
    /**
     * @return ($key is 'newsletter' ? class-string<Newsletter> : ($key is 'block' ? class-string<NewsletterBlock> : ($key is 'event' ? class-string<NewsletterEvent> : ($key is 'link_click' ? class-string<NewsletterLinkClick> : ($key is 'send' ? class-string<NewsletterSend> : ($key is 'subscriber' ? class-string<NewsletterSubscriber> : class-string<Model>))))))
     */
    public static function class(string $key): string
    {
        /** @var class-string<Model>|null $class */
        $class = config("filagora.models.{$key}") ?? config("filagora-newsletters.models.{$key}");

        return $class ?? throw new InvalidArgumentException("No Filagora newsletters model is registered for [{$key}].");
    }
}
