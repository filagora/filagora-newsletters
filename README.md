# Filagora Newsletters

Newsletters for Filagora: subscribers with their consent record, campaigns built from content
blocks, scheduled sends, link tracking and delivery events, independent of any one mail provider.
Requires [`filagora/filagora-core`](https://github.com/filagora/filagora-core).

> **In development, not yet released.** This first slice ships the tables, models and enums only,
> with a schema snapshot that matches the live tables of the site the package was extracted from
> (zen-cart.com), so that site can adopt them as they are. Subscribers, campaigns, delivery events
> and the Filament screens arrive in later slices; until they do, the package does not send mail.
> It will be at `0.x`: the API may change between minor versions.

## What it provides

### Overview

Newsletters for Filagora: subscribers with consent, campaigns built from content blocks, scheduled sends, link tracking, and delivery events, independent of any one mail provider.

### Tables

`newsletter_subscribers`, `newsletters`, `newsletter_sends`, `newsletter_events`, `newsletter_blocks` and `newsletter_link_clicks`, prefixed `filagora_` by default. A site that already has them can run `php artisan filagora:adopt` instead of migrating. The migration also adds the two shared system blocks (`shared_header`, `shared_footer`) when they are missing.

### Commands

This package adds no commands yet.

### Events

This package fires no events yet.

### Access

This package defines no abilities yet.

### Using it

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

`config/filagora-newsletters.php` holds the swappable `models` and the Filament `navigation_group` (`FILAGORA_NEWSLETTERS_NAVIGATION_GROUP`).

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
