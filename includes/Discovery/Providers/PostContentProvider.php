<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Discovery\Providers;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Discovery\HtmlLinkExtractor;
use WPCalibrate\LinkRepair\Services\SettingsService;

/**
 * Discovers links and media in standard posts, pages, and public custom post types.
 */
final class PostContentProvider implements ContentProviderInterface
{
    public function getId(): string
    {
        return 'posts';
    }

    public function getTitle(): string
    {
        return __('Posts, Pages & Custom Post Types', 'wpcalibrate-link-repair');
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Get configured post types to scan.
     *
     * @return array<int, string>
     */
    private function getPostTypes(): array
    {
        $types = (array) SettingsService::get('scan_post_types', ['post', 'page']);
        return empty($types) ? ['post', 'page'] : $types;
    }

    public function countItems(array $options = []): int
    {
        global $wpdb;
        $postTypes = $this->getPostTypes();
        if (empty($postTypes)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($postTypes), '%s'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $count = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type IN ({$placeholders}) AND post_status IN ('publish', 'future', 'draft', 'private', 'pending')",
                ...$postTypes
            )
        );

        return (int) $count;
    }

    public function getItems(int $offset, int $limit, array $options = []): array
    {
        global $wpdb;
        $postTypes = $this->getPostTypes();
        if (empty($postTypes)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($postTypes), '%s'));
        $args = array_merge($postTypes, [$limit, $offset]);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT ID, post_type, post_title, post_content, post_excerpt FROM {$wpdb->posts} 
                 WHERE post_type IN ({$placeholders}) AND post_status IN ('publish', 'future', 'draft', 'private', 'pending')
                 ORDER BY ID ASC LIMIT %d OFFSET %d",
                ...$args
            )
        );

        return is_array($rows) ? $rows : [];
    }

    public function extractUrls(mixed $item): array
    {
        if (!is_object($item) || !isset($item->ID)) {
            return [];
        }

        $scanMedia = (bool) SettingsService::get('scan_media', true);
        $occurrences = [];

        // 1. Scan post_content
        if (!empty($item->post_content)) {
            $occurrences = array_merge(
                $occurrences,
                HtmlLinkExtractor::extract(
                    (string) $item->post_content,
                    $item->post_type,
                    (int) $item->ID,
                    'post_content',
                    $scanMedia
                )
            );
        }

        // 2. Scan post_excerpt
        if (!empty($item->post_excerpt)) {
            $occurrences = array_merge(
                $occurrences,
                HtmlLinkExtractor::extract(
                    (string) $item->post_excerpt,
                    $item->post_type,
                    (int) $item->ID,
                    'post_excerpt',
                    $scanMedia
                )
            );
        }

        return $occurrences;
    }
}
