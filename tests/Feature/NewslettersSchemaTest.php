<?php

declare(strict_types=1);

use Filagora\Core\Schema\Adopter;
use Filagora\Core\Schema\AdoptionStatus;
use Filagora\Core\Schema\SchemaRegistry;
use Filagora\Core\Schema\TableShape;
use Filagora\Core\Support\Tables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The tables must match the site they were extracted from, so an existing site can adopt them as
 * they are. The snapshot is that site's live shape; the migration must reproduce it exactly.
 */

const NEWSLETTERS_MIGRATION = '2026_11_14_000000_create_filagora_newsletter_tables';

const NEWSLETTER_TABLES = ['newsletter_subscribers', 'newsletters', 'newsletter_sends', 'newsletter_events', 'newsletter_blocks', 'newsletter_link_clicks'];

function newslettersMigration(): object
{
    return require __DIR__.'/../../database/migrations/'.NEWSLETTERS_MIGRATION.'.php';
}

afterEach(function () {
    foreach (array_reverse(NEWSLETTER_TABLES) as $table) {
        Schema::dropIfExists($table);
    }

    config(['filagora.table_prefix' => 'filagora_']);

    if (! Schema::hasTable(Tables::name('newsletters'))) {
        newslettersMigration()->up();
    }
});

it('ships a snapshot of the six tables that matches the migration, column order included', function () {
    $snapshot = app(SchemaRegistry::class)->get('newsletters');

    expect(array_keys($snapshot->tables))->toBe(NEWSLETTER_TABLES)
        ->and($snapshot->migrationNames())->toBe([NEWSLETTERS_MIGRATION]);

    foreach (NEWSLETTER_TABLES as $table) {
        expect(TableShape::inspect($table)->differencesFrom($snapshot->tables[$table], compareColumnOrder: true))->toBe([], $table);
    }
});

it('adopts the tables of an existing site and records the migration', function () {
    newslettersMigration()->down();
    config(['filagora.table_prefix' => '']);
    newslettersMigration()->up();
    DB::table('migrations')->where('migration', NEWSLETTERS_MIGRATION)->delete();

    $plan = app(Adopter::class)->planFor(app(SchemaRegistry::class)->get('newsletters'));

    expect($plan->status)->toBe(AdoptionStatus::Adoptable, implode('; ', $plan->differences))
        ->and($plan->pendingMigrations)->toBe([NEWSLETTERS_MIGRATION]);

    $this->artisan('filagora:adopt', ['--package' => 'newsletters'])->expectsOutputToContain('Recorded 1 migration(s) as run.')->assertExitCode(0);
});

it('refuses tables that lost an index, and is already adopted on a site that ran the migration', function () {
    expect(app(Adopter::class)->planFor(app(SchemaRegistry::class)->get('newsletters'))->status)->toBe(AdoptionStatus::AlreadyAdopted);

    newslettersMigration()->down();
    config(['filagora.table_prefix' => '']);
    newslettersMigration()->up();
    Schema::table('newsletter_subscribers', fn ($table) => $table->dropIndex('newsletter_subscribers_suppression_hash_index'));

    $plan = app(Adopter::class)->planFor(app(SchemaRegistry::class)->get('newsletters'));

    expect($plan->status)->toBe(AdoptionStatus::Mismatch)
        ->and($plan->differences)->toContain('newsletter_subscribers: index newsletter_subscribers_suppression_hash_index is missing');
});

it('keeps the names the live site gave its keys', function () {
    expect(TableShape::inspect('newsletter_sends')->indexes['newsletter_sends_newsletter_id_newsletter_subscriber_id_unique'])
        ->toMatchArray(['columns' => ['newsletter_id', 'newsletter_subscriber_id'], 'unique' => true])
        ->and(TableShape::inspect('newsletter_subscribers')->indexes['newsletter_subscribers_normalized_email_unique']['unique'])->toBeTrue();
});

it('seeds the shared header and footer as active system blocks', function () {
    $blocks = DB::table(Tables::name('newsletter_blocks'))->orderBy('key')->get(['key', 'type', 'is_system', 'is_active']);

    expect($blocks->pluck('type', 'key')->all())->toBe(['shared_footer' => 'footer', 'shared_header' => 'header'])
        ->and($blocks->every(fn ($block) => (bool) $block->is_system && (bool) $block->is_active))->toBeTrue();
});
