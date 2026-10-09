<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Integrations\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Discovery\Providers\ContentProviderInterface;
use WPCalibrate\LinkRepair\Discovery\HtmlLinkExtractor;
use WPCalibrate\LinkRepair\Services\SettingsService;

/**
 * Discovers links inside WooCommerce product descriptions and short descriptions using public APIs.
 */
final class WooCommerceProductProvider implements ContentProviderInterface
{
    public function getId(): string
    {
        return 'woocommerce_products';
    }

    public function getTitle(): string
    {
        return __('WooCommerce Products', 'wpcalibrate-link-repair');
    }

    public function isAvailable(): bool
    {
        return WooCommerceIntegration::isWooCommerceActive();
    }

    public function countItems(array $options = []): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $count = $wpdb->get_var(
            "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('publish', 'draft', 'private', 'pending')"
        );
        return (int) $count;
    }

    public function getItems(int $offset, int $limit, array $options = []): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status IN ('publish', 'draft', 'private', 'pending') 
                 ORDER BY ID ASC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );

        $products = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (function_exists('wc_get_product')) {
                    $prod = wc_get_product((int) $row->ID);
                    if ($prod) {
                        $products[] = $prod;
                    }
                }
            }
        }

        return $products;
    }

    public function extractUrls(mixed $item): array
    {
        if (!is_object($item) || !method_exists($item, 'get_id')) {
            return [];
        }

        $scanMedia = (bool) SettingsService::get('scan_media', true);
        $productId = (int) $item->get_id();
        $occurrences = [];

        // 1. Product Description
        $desc = (string) $item->get_description();
        if ($desc !== '') {
            $occurrences = array_merge(
                $occurrences,
                HtmlLinkExtractor::extract(
                    $desc,
                    'product',
                    $productId,
                    'post_content',
                    $scanMedia
                )
            );
        }

        // 2. Product Short Description
        $shortDesc = (string) $item->get_short_description();
        if ($shortDesc !== '') {
            $occurrences = array_merge(
                $occurrences,
                HtmlLinkExtractor::extract(
                    $shortDesc,
                    'product',
                    $productId,
                    'post_excerpt',
                    $scanMedia
                )
            );
        }

        return $occurrences;
    }
}
