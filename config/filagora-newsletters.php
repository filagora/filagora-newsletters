<?php

declare(strict_types=1);

use Filagora\Newsletters\Events\MailgunEvents;
use Filagora\Newsletters\Events\MailtrapEvents;
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
    | Delivery
    |--------------------------------------------------------------------------
    |
    | `provider` names the Laravel mailer (config/mail.php) campaign mail goes through, and decides
    | which outbound tagging (Mailgun or Mailtrap variables) is attached so delivery events can be
    | matched to a send. Null uses the application's default mailer. It must name a mailer that
    | exists in config/mail.php. `campaign_daily_limit` caps how many recipient jobs are released per
    | day (the rest wait for tomorrow); zero or less releases none. `queue` names the queue the
    | recipient jobs use (null: the default queue).
    |
    */
    'delivery' => [
        'provider' => env('FILAGORA_NEWSLETTERS_DELIVERY_PROVIDER'),
        'campaign_daily_limit' => (int) env('FILAGORA_NEWSLETTERS_DAILY_LIMIT', 90),
        'queue' => env('FILAGORA_NEWSLETTERS_QUEUE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery events
    |--------------------------------------------------------------------------
    |
    | The class that reads each mail provider's webhook events, by provider name. The host serves
    | the webhook (and checks its signature) and passes the decoded event to
    | `DeliveryEvents::handle($provider, $event)`. Add a provider by naming a class that implements
    | `Contracts\EventHandler`.
    |
    */
    'events' => [
        'handlers' => [
            'mailgun' => MailgunEvents::class,
            'mailtrap' => MailtrapEvents::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule
    |--------------------------------------------------------------------------
    |
    | When on, the package adds `filagora:newsletters:queue-scheduled` to the scheduler every
    | minute: it queues campaigns whose time has come and releases queued recipients up to the
    | daily limit. Turn it off if your application schedules the command itself.
    |
    */
    'schedule' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Branding defaults
    |--------------------------------------------------------------------------
    |
    | What the shared header and footer say until an editor saves their own in the newsletter
    | settings (those are stored as settings and win over these).
    |
    */
    'branding' => [
        'brand_name' => 'Newsletter',
        'header_eyebrow' => 'Newsletter',
        'logo_alt' => '',
        'footer_text' => 'You are receiving this because you subscribed to the newsletter.',
        'manage_link_label' => 'Unsubscribe or manage newsletter signup',
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
