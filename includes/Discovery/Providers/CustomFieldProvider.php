<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Discovery\Providers;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Discovery\HtmlLinkExtractor;
use WPCalibrate\LinkRepair\Http\UrlNormalizer;
use WPCalibrate\LinkRepair\Services\SettingsService;

/**
 * Discovers links inside administrator-selected custom fields / postmeta.
 */
final class CustomFieldProvider implements ContentProviderInterface
{
    public function getId(): string
    {
        return 'custom_fields';
    }

    public function getTitle(): string
    {
        return __('Custom Fields (Post Meta)', 'wpcalibrate-link-repair');
    }

    public function isAvailable(): bool
    {
        $enabled = (bool) SettingsService::get('scan_custom_fields', false);
        $keys = $this->getConfiguredKeys();
        return $enabled && !empty($keys);
    }

    /**
     * Get configured custom field meta keys.
     *
     * @return array<int, string>
     */
    private function getConfiguredKeys(): array
    {
        $raw = (string) SettingsService::get('custom_field_keys', '');
        $lines = preg_split('/[\r\n,]+/', $raw);
        if (!is_array($lines)) {
            return [];
        }

        $clean = [];
        foreach ($lines as $line) {
            $k = trim($line);
            if ($k !== '') {
                $clean[] = $k;
            }
        }
        return array_unique($clean);
    }

    public function countItems(array $options = []): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        global $wpdb;
        $keys = $this->getConfiguredKeys();
        $placeholders = implode(',', array_fill(0, count($keys), '%s'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $count = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT COUNT(meta_id) FROM {$wpdb->postmeta} WHERE meta_key IN ({$placeholders})",
                ...$keys
            )
        );

        return (int) $count;
    }

    public function getItems(int $offset, int $limit, array $options = []): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        global $wpdb;
        $keys = $this->getConfiguredKeys();
        $placeholders = implode(',', array_fill(0, count($keys), '%s'));
        $args = array_merge($keys, [$limit, $offset]);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} 
                 WHERE meta_key IN ({$placeholders}) 
                 ORDER BY meta_id ASC LIMIT %d OFFSET %d",
                ...$args
            )
        );

        return is_array($rows) ? $rows : [];
    }

    public function extractUrls(mixed $item): array
    {
        if (!is_object($item) || !isset($item->meta_id, $item->meta_value)) {
            return [];
        }

        $value = (string) $item->meta_value;
        if (trim($value) === '') {
            return [];
        }

        // If the value contains HTML tags, extract via HtmlLinkExtractor
        if (str_contains($value, '<')) {
            $scanMedia = (bool) SettingsService::get('scan_media', true);
            return HtmlLinkExtractor::extract(
                $value,
                'custom_field',
                (int) $item->post_id,
                (string) $item->meta_key,
                $scanMedia
            );
        }

        // Check if value itself is a URL (plain URL stored in meta)
        $normalized = UrlNormalizer::normalize($value);
        if ($normalized !== null) {
            $fragment = UrlNormalizer::extractFragment($value);
            return [
                [
                    'raw_url'        => trim($value),
                    'link_type'      => UrlNormalizer::classifyLinkType($value),
                    'source_type'    => 'custom_field',
                    'source_id'      => (int) $item->post_id,
                    'field_key'      => (string) $item->meta_key,
                    'context_path'   => 'meta_id:' . $item->meta_id,
                    'anchor_text'    => (string) $item->meta_key,
                    'tag_attributes' => [],
                    'raw_fragment'   => $fragment,
                ],
            ];
        }

        return [];
    }
}
