<?php

declare(strict_types=1);

use Filagora\Newsletters\Contracts\NewsletterMedia;
use Filagora\Newsletters\Enums\NewsletterBlockType;
use Filagora\Newsletters\Mail\CampaignMail;
use Filagora\Newsletters\Models\Newsletter;
use Filagora\Newsletters\Models\NewsletterBlock;
use Filagora\Newsletters\Models\NewsletterLinkClick;
use Filagora\Newsletters\Models\NewsletterSend;
use Filagora\Newsletters\Services\BrandingSettings;
use Filagora\Newsletters\Services\ContentRenderer;
use Filagora\Newsletters\Services\DeliveryTransport;
use Filagora\Newsletters\Services\LinkTracker;
use Filagora\Newsletters\Support\TrackedLinkMarkers;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

function renderer(): ContentRenderer
{
    return app(ContentRenderer::class);
}

/**
 * @param  array<int, array<string, mixed>>  $blocks
 */
function campaign(array $blocks, array $attributes = []): Newsletter
{
    return Newsletter::create(array_merge([
        'title' => 'Spring', 'subject' => 'Spring news', 'body_html' => '',
        'content_blocks' => renderer()->preparedBlocks($blocks),
    ], $attributes));
}

function buttonCampaign(string $url = 'https://example.test/sale', string $label = 'Shop now'): Newsletter
{
    return campaign([['type' => 'button', 'button_label' => $label, 'button_url' => $url]]);
}

function trackedLinkCampaign(string $url, string $label, string $style = 'button'): array
{
    $id = (string) Str::uuid();
    $html = '<p>Hello <a href="'.$url.'" class="'.TrackedLinkMarkers::CLASS_MARKER.' '.TrackedLinkMarkers::ID_MARKER_PREFIX.$id.' '.TrackedLinkMarkers::STYLE_MARKER_PREFIX.$style.'">'.$label.'</a></p>';

    return [campaign([['type' => 'text', 'heading' => 'Hi', 'body_html' => $html]]), $id];
}

it('puts the shared header first and the shared footer last, and keeps block keys', function () {
    $blocks = renderer()->preparedBlocks([['type' => 'text', 'heading' => 'Hi']]);

    expect($blocks)->toHaveCount(3)
        ->and($blocks[0]['type'])->toBe('reusable')
        ->and($blocks[0]['newsletter_block_id'])->toBe(NewsletterBlock::where('key', 'shared_header')->value('id'))
        ->and($blocks[2]['newsletter_block_id'])->toBe(NewsletterBlock::where('key', 'shared_footer')->value('id'))
        ->and($blocks[1]['block_key'])->not->toBe('')
        ->and(renderer()->stripSystemChrome($blocks))->toHaveCount(1);
});

it('drops blocks without a type and any header or footer an editor tried to add', function () {
    $blocks = renderer()->preparedBlocks([['heading' => 'No type'], ['type' => 'shared_header'], ['type' => 'text', 'heading' => 'Keep']]);

    expect($blocks)->toHaveCount(3)->and($blocks[1]['heading'])->toBe('Keep');
});

it('renders the subject, the content, the footer text and the unsubscribe link', function () {
    $newsletter = campaign([['type' => 'text', 'heading' => 'Big news', 'body_html' => '<p>Inside</p>']]);

    $html = renderer()->renderHtml($newsletter, ['branding' => app(BrandingSettings::class)->settings(), 'unsubscribe_url' => 'https://example.test/unsub']);
    $text = renderer()->renderText($newsletter, ['branding' => app(BrandingSettings::class)->settings(), 'unsubscribe_url' => 'https://example.test/unsub']);

    expect($html)->toContain('Spring news')->toContain('Big news')->toContain('<p>Inside</p>')->toContain('https://example.test/unsub')
        ->and($text)->toContain('Newsletter')->toContain('Spring news')->toContain("Big news\nInside")->toContain('https://example.test/unsub');
});

it('keeps images in rich text within the width of the email', function () {
    $newsletter = campaign([['type' => 'text', 'body_html' => '<p>Café</p>'
        .'<img src="https://example.test/wide.png" alt="Wide">'
        .'<img src="https://example.test/sized.png" alt="Sized" width="300" height="200" style="width: 300px; height: 200px">'
        .'<img src="https://example.test/capped.png" alt="Capped" style="max-width: 120px">']]);

    $html = renderer()->renderHtml($newsletter);

    expect($html)
        ->toContain('<img src="https://example.test/wide.png" alt="Wide" style="max-width:100%;height:auto;">')
        ->toContain('width="300" height="200" style="width: 300px; height: 200px;max-width:100%;height:auto;">')
        ->toContain('<img src="https://example.test/capped.png" alt="Capped" style="max-width: 120px">')
        ->and(html_entity_decode($html))->toContain('<p>Café</p>');
});

it('uses a live library block, and skips an inactive one', function () {
    $block = NewsletterBlock::create(['key' => 'ad', 'name' => 'Ad', 'type' => NewsletterBlockType::Text, 'content' => ['heading' => 'First wording']]);
    $newsletter = campaign([['type' => 'reusable', 'newsletter_block_id' => $block->id]]);

    expect(renderer()->renderHtml($newsletter))->toContain('First wording');

    $block->update(['content' => ['heading' => 'Edited wording']]);
    expect(renderer()->renderHtml($newsletter))->toContain('Edited wording')->not->toContain('First wording');

    $block->update(['is_active' => false]);
    expect(renderer()->renderHtml($newsletter))->not->toContain('Edited wording');
});

it('wraps a button in a signed tracked url, and leaves it alone with no saved newsletter', function () {
    $newsletter = buttonCampaign();

    expect(renderer()->renderHtml($newsletter))->not->toContain('https://example.test/sale')->toContain('/newsletter/click/')
        ->and(renderer()->htmlFromBlocks([['type' => 'button', 'button_label' => 'Go', 'button_url' => 'https://example.test/draft']]))->toContain('https://example.test/draft');
});

it('adds the recipient to the tracked url so a click belongs to that send', function () {
    $newsletter = buttonCampaign();

    $html = renderer()->renderHtml($newsletter, ['newsletter_send_id' => 77]);

    expect(html_entity_decode($html))->toContain('send=77');
});

it('redirects a signed click to the live destination and records it', function () {
    $newsletter = buttonCampaign('https://example.test/sale', 'Shop now');
    $blockKey = $newsletter->content_blocks[1]['block_key'];

    $this->get(app(LinkTracker::class)->trackedUrl($newsletter, $blockKey, null))->assertRedirect('https://example.test/sale');

    $click = NewsletterLinkClick::firstOrFail();

    expect($click->block_key)->toBe($blockKey)
        ->and($click->label)->toBe('Shop now')
        ->and($click->destination_url)->toBe('https://example.test/sale')
        ->and($click->newsletter_send_id)->toBeNull()
        ->and($click->ip_hash)->not->toBeNull()
        ->and($click->clicked_at)->not->toBeNull();
});

it('follows a corrected link for mail that was already sent', function () {
    $newsletter = buttonCampaign('https://example.test/old');
    $blockKey = $newsletter->content_blocks[1]['block_key'];
    $url = app(LinkTracker::class)->trackedUrl($newsletter, $blockKey, null);

    $blocks = $newsletter->content_blocks;
    $blocks[1]['button_url'] = 'https://example.test/new';
    $newsletter->update(['content_blocks' => $blocks]);

    $this->get($url)->assertRedirect('https://example.test/new');
});

it('attributes a click to the recipient send when given', function () {
    $newsletter = buttonCampaign();
    $send = NewsletterSend::create(['newsletter_id' => $newsletter->id, 'email' => 'a@example.test', 'normalized_email' => 'a@example.test']);

    $this->get(app(LinkTracker::class)->trackedUrl($newsletter, $newsletter->content_blocks[1]['block_key'], $send->id));

    expect(NewsletterLinkClick::firstOrFail()->send->is($send))->toBeTrue();
});

it('rejects unsigned and tampered click urls and unknown blocks, and records nothing', function () {
    $newsletter = buttonCampaign();
    $blockKey = $newsletter->content_blocks[1]['block_key'];
    $tracker = app(LinkTracker::class);

    $this->get(route('newsletter.link-click', ['newsletter' => $newsletter, 'blockKey' => $blockKey]))->assertForbidden();
    $this->get($tracker->trackedUrl($newsletter, $blockKey, null).'&tamper=1')->assertForbidden();
    $this->get($tracker->trackedUrl($newsletter, 'not-a-real-block-key', null))->assertNotFound();

    expect(NewsletterLinkClick::count())->toBe(0);
});

it('will not redirect to anything but http or https', function () {
    $newsletter = buttonCampaign('javascript:alert(1)');

    $this->get(app(LinkTracker::class)->trackedUrl($newsletter, $newsletter->content_blocks[1]['block_key'], null))->assertNotFound();
});

it('counts clicks on one library block across different newsletters', function () {
    $block = NewsletterBlock::create(['key' => 'evergreen', 'name' => 'Evergreen', 'type' => NewsletterBlockType::Button, 'content' => ['button_label' => 'See', 'button_url' => 'https://example.test/evergreen']]);
    $one = campaign([['type' => 'reusable', 'newsletter_block_id' => $block->id]]);
    $two = campaign([['type' => 'reusable', 'newsletter_block_id' => $block->id]], ['title' => 'B']);

    $this->get(app(LinkTracker::class)->trackedUrl($one, $one->content_blocks[1]['block_key'], null));
    $this->get(app(LinkTracker::class)->trackedUrl($two, $two->content_blocks[1]['block_key'], null));

    expect(NewsletterLinkClick::where('newsletter_block_id', $block->id)->count())->toBe(2);
});

it('tracks a link marked in rich text, with button or plain styling, and resolves it on click', function () {
    [$newsletter, $id] = trackedLinkCampaign('https://example.test/rich', 'Read the full story', 'button');

    $html = renderer()->renderHtml($newsletter);

    expect($html)->not->toContain('https://example.test/rich')->toContain('/newsletter/click/')->toContain('background:#8a4b19');

    $this->get(app(LinkTracker::class)->trackedUrl($newsletter, $id, null))->assertRedirect('https://example.test/rich');

    $click = NewsletterLinkClick::firstOrFail();

    expect($click->block_key)->toBe($id)->and($click->label)->toBe('Read the full story')->and($click->newsletter_block_id)->toBeNull();

    [$plain] = trackedLinkCampaign('https://example.test/plain', 'Learn more', 'link');
    expect(renderer()->renderHtml($plain))->toContain('text-decoration:underline');
});

it('finds an image through the media contract, and renders none when there is no library', function () {
    $newsletter = campaign([['type' => 'image', 'asset_id' => 5, 'caption' => 'A photo']]);

    expect(renderer()->renderHtml($newsletter))->not->toContain('<img');

    app()->instance(NewsletterMedia::class, new class implements NewsletterMedia
    {
        public function find(int $id): ?array
        {
            return ['url' => "https://cdn.example.test/{$id}.jpg", 'alt' => 'From the library'];
        }

        public function imageOptions(): array
        {
            return [5 => 'photo.jpg'];
        }
    });
    app()->forgetInstance(ContentRenderer::class);

    $html = renderer()->renderHtml($newsletter);

    expect($html)->toContain('https://cdn.example.test/5.jpg')->toContain('alt="From the library"')->toContain('A photo');
});

it('reads branding from saved settings over the configured defaults', function () {
    config(['filagora-newsletters.branding.brand_name' => 'Acme']);
    $branding = app(BrandingSettings::class);

    expect($branding->settings()['brand_name'])->toBe('Acme')->and($branding->settings()['logo_url'])->toBeNull();

    $branding->save(['brand_name' => 'Edited', 'header_eyebrow' => 'Weekly', 'logo_alt' => '', 'footer_text' => 'Bye', 'manage_link_label' => 'Leave']);

    expect(app(BrandingSettings::class)->settings())->toMatchArray(['brand_name' => 'Edited', 'header_eyebrow' => 'Weekly', 'footer_text' => 'Bye', 'manage_link_label' => 'Leave']);
});

it('renders the campaign mail with a preview banner and the send id in the tracked links', function () {
    $newsletter = buttonCampaign();

    $mail = new CampaignMail($newsletter, 'https://example.test/unsub', preview: true, newsletterSendId: 9);

    expect($mail->envelope()->subject)->toBe('[Preview] Spring news')
        ->and($mail->render())->toContain('Preview test send.')->toContain('https://example.test/unsub')
        ->and(html_entity_decode($mail->render()))->toContain('send=9');
});

it('tags outgoing mail with the send id for the provider that will report events', function () {
    $newsletter = buttonCampaign();

    $headers = function (?string $provider) use ($newsletter): array {
        $mail = (new CampaignMail($newsletter, 'https://example.test/unsub', newsletterSendId: 4, deliveryProvider: $provider))->build();
        $message = new Email;

        foreach ($mail->callbacks as $callback) {
            $callback($message);
        }

        return array_map(fn ($header) => $header->getName(), iterator_to_array($message->getHeaders()->all()));
    };

    expect($headers('mailgun'))->toContain('X-Mailgun-Variables')
        ->and($headers('mailtrap'))->toContain('X-Mailtrap-Variables')
        ->and($headers(null))->not->toContain('X-Mailgun-Variables')->not->toContain('X-Mailtrap-Variables');
});

it('sends through the mailer named by the delivery provider, defaulting to the application mailer', function () {
    $transport = app(DeliveryTransport::class);

    config(['mail.default' => 'array']);
    expect($transport->provider())->toBe('array');

    config(['filagora-newsletters.delivery.provider' => 'Mailgun']);
    expect($transport->provider())->toBe('mailgun')->and($transport->isProvider('MAILGUN'))->toBeTrue();

    config(['filagora-newsletters.delivery.provider' => 'array']);
    Mail::mailer('array');
    $transport->send('a@example.test', new CampaignMail(buttonCampaign(), 'https://example.test/unsub'));

    expect(app('mailer')->getSymfonyTransport())->not->toBeNull();
});
