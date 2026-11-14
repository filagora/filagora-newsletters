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
    | Name
    |--------------------------------------------------------------------------
    |
    | What the newsletter is called in the mail and the pages the package sends and shows
    | ("Confirm your :name subscription"). Hosts set their own, for example "Zen Cart newsletter".
    |
    */
    'name' => env('FILAGORA_NEWSLETTERS_NAME', 'newsletter'),

    /*
    |--------------------------------------------------------------------------
    | Consent
    |--------------------------------------------------------------------------
    |
    | The words a person agrees to are stored with their subscription, with a version label.
    | A null text uses the packaged sentence with the name above. Change the version whenever
    | the wording changes, so each subscriber's record says what they agreed to.
    |
    */
    'consent' => [
        'profile_text' => null,
        'profile_version' => 'profile-v1',
        'public_text' => null,
        'public_version' => 'public-v1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The public signup page, the signed confirm and unsubscribe links. Off by default: turn it on
    | to have the package serve them (named newsletter.show, .store, .confirm and .unsubscribe),
    | or leave it off and serve your own. `views` name the pages, so a host can point them at its
    | own layout. `signup_per_minute` limits the signup form per address.
    |
    */
    'routes' => [
        'enabled' => false,
        'prefix' => 'newsletter',
        'middleware' => ['web'],
        'signup_per_minute' => 5,
        'views' => [
            'show' => 'filagora-newsletters::show',
            'confirmed' => 'filagora-newsletters::confirmed',
            'unsubscribed' => 'filagora-newsletters::unsubscribed',
        ],
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
