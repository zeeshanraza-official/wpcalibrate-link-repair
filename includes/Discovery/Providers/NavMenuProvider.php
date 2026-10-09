<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Discovery\Providers;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Http\UrlNormalizer;

/**
 * Discovers links in WordPress Navigation Menus.
 */
final class NavMenuProvider implements ContentProviderInterface
{
    public function getId(): string
    {
        return 'nav_menus';
    }

    public function getTitle(): string
    {
        return __('Navigation Menus', 'wpcalibrate-link-repair');
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function countItems(array $options = []): int
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $count = $wpdb->get_var(
            "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'nav_menu_item' AND post_status = 'publish'"
        );
        return (int) $count;
    }

    public function getItems(int $offset, int $limit, array $options = []): array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'nav_menu_item' AND post_status = 'publish' 
                 ORDER BY ID ASC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
        return is_array($rows) ? $rows : [];
    }

    public function extractUrls(mixed $item): array
    {
        if (!is_object($item) || !isset($item->ID)) {
            return [];
        }

        $url = (string) get_post_meta((int) $item->ID, '_menu_item_url', true);
        if (trim($url) === '') {
            return [];
        }

        $cleanUrl = trim($url);
        $fragment = UrlNormalizer::extractFragment($cleanUrl);
        $title = !empty($item->post_title) ? (string) $item->post_title : (string) get_post_meta((int) $item->ID, '_menu_item_title', true);

        return [
            [
                'raw_url'        => $cleanUrl,
                'link_type'      => 'link',
                'source_type'    => 'nav_menu_item',
                'source_id'      => (int) $item->ID,
                'field_key'      => '_menu_item_url',
                'context_path'   => 'meta:_menu_item_url',
                'anchor_text'    => !empty($title) ? $title : __('Menu Item', 'wpcalibrate-link-repair'),
                'tag_attributes' => [],
                'raw_fragment'   => $fragment,
            ],
        ];
    }
}
