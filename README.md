# Filagora Newsletters

Newsletters for Filagora: subscribers with their consent record, campaigns built from content
blocks, scheduled sends, link tracking and delivery events, independent of any one mail provider.
Requires [`filagora/filagora-core`](https://github.com/filagora/filagora-core).

> **In development, not yet released.** So far it has the tables, models and enums (with a schema
> snapshot that matches the live tables of the site the package was extracted from, zen-cart.com,
> so that site can adopt them as they are), subscribers (signup, confirmation, unsubscribe and
> consent), campaign rendering (content blocks, branding, tracked links and the campaign mail) and
> sending (queueing a campaign, per-recipient jobs, the scheduler and a daily limit). Delivery
> events (bounces, complaints, opens) and the Filament screens arrive in later slices.
> It will be at `0.x`: the API may change between minor versions.

## What it provides

### Overview

Newsletters for Filagora: subscribers with consent, campaigns built from content blocks, scheduled sends, link tracking, and delivery events, independent of any one mail provider.

### Tables

`newsletter_subscribers`, `newsletters`, `newsletter_sends`, `newsletter_events`, `newsletter_blocks` and `newsletter_link_clicks`, prefixed `filagora_` by default. A site that already has them can run `php artisan filagora:adopt` instead of migrating. The migration also adds the two shared system blocks (`shared_header`, `shared_footer`) when they are missing.

### Commands

| Command | What it does |
| --- | --- |
| `filagora:newsletters:queue-scheduled` (alias `newsletter:queue-scheduled`) | Queues campaigns whose scheduled time has come, then releases queued recipients up to the daily limit. The package schedules it every minute (`schedule.enabled`). |
| `filagora:newsletters:export-subscribers {path}` (alias `newsletter:export-subscribers`) | Writes the sendable subscribers to a CSV, each with a signed unsubscribe link, for an external sending provider. |

### Events

This package fires no events yet.

### Access

This package defines no abilities yet.

### Using it

- **Subscribers.** `Subscribers` (resolve it from the container) does the work: `subscribeFromProfile($user, $ip, $userAgent)`, `unsubscribeFromProfile($user)`, `requestPublicSubscription($email, $name, $ip, $userAgent)` (pending until confirmed), `confirm()`, `unsubscribe()`, `forUser()`, `forEmail()`, `unlinkUserForEmailChange($user, $oldEmail)` (call it when a member changes their address, so the list keeps the old address with its consent) and `anonymizeForErasure($user)` (keeps a hash so an erased address is not silently re-added). The words a person agreed to are stored with their subscription; set them, and a version label, under `consent` in the config.
- **Rendering.** `ContentRenderer` turns a newsletter's content blocks (header, text, image, button, footer, or a reference to a reusable library block) into email HTML and text. `preparedBlocks()` shapes blocks for storage: the shared header goes first, the shared footer last, and library references stay live so an edit to a reusable block reaches campaigns not yet sent. `BrandingSettings` holds the shared brand name, header line, logo, footer text and unsubscribe-link label (saved as settings; `branding` in the config gives the defaults). `CampaignMail` is the mailable; its views are `filagora-newsletters::mail.*` and can be overridden.
- **Tracked links.** Button blocks, and links in rich text that carry the `nl-tracked-link` marker classes, are rewritten to a signed first-party redirect (`newsletter.link-click`, part of the public routes). A click is recorded against the send and the library block, then redirects to the link's *current* destination, so a corrected link works for mail already sent. Only `http` and `https` destinations are followed.
- **Images.** The package has no media library: bind `Contracts\NewsletterMedia` to yours (it answers an image's URL and alt text by number, and lists the choices for the editor). The default finds no images.
- **Sending.** `CampaignSender::queue($newsletter)` turns a draft or scheduled newsletter into one `queued` send per sendable subscriber (confirmed, not unsubscribed, no suppression hash), marks the newsletter `sending`, and releases recipient jobs. It refuses a newsletter that is not a draft or scheduled, or that already has deliveries. `CampaignSender::dispatchQueuedSends()` releases waiting recipients up to the daily limit (`delivery.campaign_daily_limit`, 90 by default; the rest wait for the next day, which the every-minute command picks up). Each recipient is a `SendCampaignEmail` job (three tries, with a back-off; `delivery.queue` names the queue) that calls `CampaignMailer`: it skips someone who unsubscribed since being queued, sends with their own signed unsubscribe link, marks the send `sent` or `failed` with the reason, and marks the newsletter `sent` when nothing is left waiting. `CampaignMailer::sendPreview()` sends a preview to any address.
- **Delivery.** `DeliveryTransport` sends through the mailer named by `delivery.provider` (the application's default when null); `CampaignMail` tags outgoing mail with the send id for Mailgun or Mailtrap so their events can be matched to a send.
- **Public pages (off by default).** Set `filagora-newsletters.routes.enabled` to serve `GET|POST /newsletter` (the signup page and form, with a honeypot field and a per-address rate limit), and the signed `confirm` and `unsubscribe` links (confirmation links last seven days). The routes are named `newsletter.show`, `newsletter.store`, `newsletter.confirm` and `newsletter.unsubscribe`. The packaged pages are bare; name your own under `routes.views` to use your layout. The confirmation mail is `ConfirmSubscription`; its wording is in `resources/lang`, with `name` (for example "Zen Cart newsletter") filled in.
- **Models** are resolved through `config('filagora-newsletters.models')`, so a host can swap any of the six for a subclass and every relation follows.
- **Enums:** `NewsletterStatus`, `NewsletterBlockType`, `NewsletterEventType`, `NewsletterSendStatus` and `NewsletterSubscriberStatus`.
- **Users:** the optional links from subscribers, newsletters and blocks to a user go through the configured user model (`filagora.user_model`).

## Requirements

PHP 8.4, Laravel 13 and Filament 5. Tested against MariaDB 11.4. Requires [`filagora/filagora-core`](https://github.com/filagora/filagora-core).

## Install

Not yet on Packagist. Once released:

```bash
composer require filagora/filagora-newsletters
php artisan vendor:publish --tag=filagora-newsletters-config
php artisan migrate
```

A site that already has the tables runs `php artisan filagora:adopt --package=newsletters` instead of `migrate`.

## Configuration

`config/filagora-newsletters.php` holds the swappable `models`, the newsletter `name` (`FILAGORA_NEWSLETTERS_NAME`), the `consent` wording and versions, the public `routes` (switch, prefix, middleware, rate limit, page views) and the Filament `navigation_group` (`FILAGORA_NEWSLETTERS_NAVIGATION_GROUP`).

## Extending

Swap a model by pointing its key under `filagora-newsletters.models` at your own subclass.

## Testing

```bash
composer test       # Pest, on MariaDB
composer lint       # Pint
composer analyse    # Larastan
```

## Local development (DDEV)

`ddev start`, `ddev restart` once (it creates the `testing` database), `ddev composer install`, then `ddev composer test`.

## Licence

MIT. See [LICENSE](LICENSE).
