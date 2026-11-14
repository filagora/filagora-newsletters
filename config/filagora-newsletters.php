<?php

declare(strict_types=1);

use Filagora\Newsletters\Models\Newsletter;
use Filagora\Newsletters\Models\NewsletterBlock;
use Filagora\Newsletters\Models\NewsletterEvent;
use Filagora\Newsletters\Models\NewsletterLinkClick;
use Filagora\Newsletters\Models\NewsletterSend;
use Filagora\Newsletters\Models\NewsletterSubscriber;

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | The model classes the package uses. Point a key at a subclass of your own to swap it;
    | every relation and query follows.
    |
    */
    'models' => [
        'newsletter' => Newsletter::class,
        'block' => NewsletterBlock::class,
        'event' => NewsletterEvent::class,
        'link_click' => NewsletterLinkClick::class,
        'send' => NewsletterSend::class,
        'subscriber' => NewsletterSubscriber::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation group
    |--------------------------------------------------------------------------
    |
    | The Filament navigation group this package's resources are placed in.
    | `env()` is only ever used in config files, and config holds no closures.
    |
    */
    'navigation_group' => env('FILAGORA_NEWSLETTERS_NAVIGATION_GROUP', 'Newsletters'),

];
