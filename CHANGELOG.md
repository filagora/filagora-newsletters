# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

## [0.1.0] - 2026-11-14

### Added

- The six newsletter tables (subscribers, newsletters, sends, events, content blocks, link clicks) with a schema snapshot equal to the live tables of the site the package was extracted from, so `filagora:adopt` accepts them.
- Models, swappable through `filagora-newsletters.models`, and the five status and type enums.
- Subscribers: the `Subscribers` service (profile and public subscription, confirmation, unsubscribe, email-change unlinking, erasure that keeps a suppression hash), the `ConfirmSubscription` mail, and optional public routes (`routes.enabled`) for the signup page and the signed confirm and unsubscribe links, with a honeypot and a rate limit. Consent wording and the newsletter name are configurable.
- Rendering: `ContentRenderer` (header, text, image, button and footer blocks and live reusable-block references), `BrandingSettings`, `CampaignMail` and its views, signed click tracking (`LinkClick` route, `LinkTracker`) for buttons and tracked links in rich text, `DeliveryTransport`, and the `NewsletterMedia` contract for images (default: none).
- Sending: `CampaignSender` (queue a campaign, release recipients up to a daily limit), `CampaignMailer` (one recipient, or a preview), the `SendCampaignEmail` job, and the `filagora:newsletters:queue-scheduled` and `filagora:newsletters:export-subscribers` commands (also under their old names `newsletter:queue-scheduled` and `newsletter:export-subscribers`). The package schedules the queue command every minute unless `schedule.enabled` is false.
- Delivery events: `DeliveryEvents` and the `MailgunEvents` and `MailtrapEvents` handlers (one shared base, `ProviderEvents`, so a provider only says how to read its own event shape), behind the `EventHandler` contract and the `events.handlers` config.
- Filament: the `NewslettersPlugin`, the subscriber and block resources (with the system blocks protected from deletion), the tracked-link rich-text button and the `editor` config for host plugins, the `filagora.newsletters.manage` ability (`gates` config, declared with the entitlements package), and the *Newsletters* navigation group.
- Filament, campaigns: the `NewsletterResource` (wizard form, view page with delivery and engagement figures, sends and link-click relation managers, send test, schedule, reschedule, cancel and queue actions), the settings page for the shared branding, and the staff preview route (`newsletters.preview`, `preview` in the config).
