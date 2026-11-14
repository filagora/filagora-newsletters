# AGENTS

Guidance for AI coding agents working in `filagora-newsletters`. `CLAUDE.md` imports this file; edit here.

## What this is

Newsletters, subscribers, sends and events (independent of any one mail provider) for Filagora, a family of MIT-licensed Laravel 13 / Filament 5 packages. **Under construction, unreleased:** it is being extracted from the host code of the Zen Cart support site (`filagora-zencart-site`). The design and the slice order are in the private planning repository (`newsletters-design.md`); the first slice (tables, models, enums) is built. Composer vendor `filagora`, namespace `Filagora\Newsletters\`, default table prefix `filagora_`, env `FILAGORA_NEWSLETTERS_*`. Pre-1.0 (`0.x`): the API may change between minor versions.

## Working here

- **Production's tables are the truth.** `database/schema.php` equals the live shape (identical to the profile's `schema-site.php` entries); the migration must reproduce it exactly, index and foreign key names included. Never reshape a table to tidy it; the parity tests in `tests/Feature/NewslettersSchemaTest.php` guard this.
- Resolve model classes through `Support\Models`, table names through `Tables::name`, users through `UserModel`. Do not name a model class or a user class directly.
- Behaviour comes across from the site unchanged; the site's own tests are the regression net, so move behaviour with its tests.
- Provider webhook endpoints, the Mailchimp import, the profile page integration and the `webhook_events` table stay in the host (see the design).
- Run everything through DDEV: `ddev composer test`, `ddev composer lint`, `ddev composer analyse`.
- Public repository: no private hostnames, IP addresses or personal email addresses anywhere.
