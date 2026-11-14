<?php

declare(strict_types=1);

namespace Filagora\Newsletters\Services;

use Filagora\Core\Models\Setting;
use Filagora\Core\Settings\SettingsRegistry;
use Filagora\Newsletters\Contracts\NewsletterMedia;

class BrandingSettings
{
    public function __construct(private readonly NewsletterMedia $media) {}

    public const BRAND_NAME_KEY = 'newsletter_brand_name';

    public const HEADER_EYEBROW_KEY = 'newsletter_header_eyebrow';

    public const LOGO_ASSET_ID_KEY = 'newsletter_logo_asset_id';

    public const LOGO_ALT_KEY = 'newsletter_logo_alt';

    public const FOOTER_TEXT_KEY = 'newsletter_footer_text';

    public const MANAGE_LINK_LABEL_KEY = 'newsletter_manage_link_label';

    /**
     * @return array<string, string|null>
     */
    public function defaults(): array
    {
        $branding = (array) config('filagora-newsletters.branding', []);

        return [
            self::BRAND_NAME_KEY => $branding['brand_name'] ?? null,
            self::HEADER_EYEBROW_KEY => $branding['header_eyebrow'] ?? null,
            self::LOGO_ASSET_ID_KEY => null,
            self::LOGO_ALT_KEY => $branding['logo_alt'] ?? null,
            self::FOOTER_TEXT_KEY => $branding['footer_text'] ?? null,
            self::MANAGE_LINK_LABEL_KEY => $branding['manage_link_label'] ?? null,
        ];
    }

    /**
     * @return array{
     *     brand_name: string,
     *     header_eyebrow: string,
     *     logo_asset_id: ?int,
     *     logo_alt: string,
     *     logo_url: ?string,
     *     footer_text: string,
     *     manage_link_label: string
     * }
     */
    public function settings(): array
    {
        $defaults = $this->defaults();
        $logoAssetId = $this->logoAssetId();
        $logoAsset = $logoAssetId !== null ? $this->media->find($logoAssetId) : null;

        return [
            'brand_name' => trim((string) app(SettingsRegistry::class)->get(self::BRAND_NAME_KEY, $defaults[self::BRAND_NAME_KEY])) ?: (string) $defaults[self::BRAND_NAME_KEY],
            'header_eyebrow' => trim((string) app(SettingsRegistry::class)->get(self::HEADER_EYEBROW_KEY, $defaults[self::HEADER_EYEBROW_KEY])) ?: (string) $defaults[self::HEADER_EYEBROW_KEY],
            'logo_asset_id' => $logoAssetId,
            'logo_alt' => trim((string) app(SettingsRegistry::class)->get(self::LOGO_ALT_KEY, $defaults[self::LOGO_ALT_KEY])) ?: (string) $defaults[self::LOGO_ALT_KEY],
            'logo_url' => $logoAsset['url'] ?? null,
            'footer_text' => trim((string) app(SettingsRegistry::class)->get(self::FOOTER_TEXT_KEY, $defaults[self::FOOTER_TEXT_KEY])) ?: (string) $defaults[self::FOOTER_TEXT_KEY],
            'manage_link_label' => trim((string) app(SettingsRegistry::class)->get(self::MANAGE_LINK_LABEL_KEY, $defaults[self::MANAGE_LINK_LABEL_KEY])) ?: (string) $defaults[self::MANAGE_LINK_LABEL_KEY],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formData(): array
    {
        $settings = $this->settings();

        return [
            'brand_name' => $settings['brand_name'],
            'header_eyebrow' => $settings['header_eyebrow'],
            'logo_asset_id' => $settings['logo_asset_id'],
            'logo_alt' => $settings['logo_alt'],
            'footer_text' => $settings['footer_text'],
            'manage_link_label' => $settings['manage_link_label'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        $this->upsert(self::BRAND_NAME_KEY, 'Newsletter Brand Name', (string) ($data['brand_name'] ?? ''), 'string', 'Shared newsletter brand name.');
        $this->upsert(self::HEADER_EYEBROW_KEY, 'Newsletter Header Eyebrow', (string) ($data['header_eyebrow'] ?? ''), 'string', 'Small shared text shown above each newsletter subject.');
        $this->upsert(self::LOGO_ASSET_ID_KEY, 'Newsletter Logo Asset ID', $this->normalizeNullableInt($data['logo_asset_id'] ?? null), 'integer', 'Optional CMS media asset used as the shared newsletter logo.');
        $this->upsert(self::LOGO_ALT_KEY, 'Newsletter Logo Alt Text', (string) ($data['logo_alt'] ?? ''), 'string', 'Alt text for the shared newsletter logo.');
        $this->upsert(self::FOOTER_TEXT_KEY, 'Newsletter Footer Text', (string) ($data['footer_text'] ?? ''), 'text', 'Shared footer text shown on all newsletters.');
        $this->upsert(self::MANAGE_LINK_LABEL_KEY, 'Newsletter Manage Link Label', (string) ($data['manage_link_label'] ?? ''), 'string', 'Shared unsubscribe/manage link label.');
    }

    /**
     * @return array<int, string>
     */
    public function imageAssetOptions(): array
    {
        return $this->media->imageOptions();
    }

    private function logoAssetId(): ?int
    {
        $value = app(SettingsRegistry::class)->get(self::LOGO_ASSET_ID_KEY);

        return is_numeric($value) ? (int) $value : null;
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function upsert(string $key, string $label, mixed $value, string $valueType, string $description): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'label' => $label,
                'value' => $value === null ? null : (string) $value,
                'value_type' => $valueType,
                'is_public' => false,
                'description' => $description,
            ]
        );
    }
}
