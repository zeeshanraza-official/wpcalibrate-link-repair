<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Discovery\Providers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for pluggable content discovery providers.
 */
interface ContentProviderInterface
{
    /**
     * Unique identifier for the provider (e.g. 'posts', 'nav_menus').
     */
    public function getId(): string;

    /**
     * Human-readable title for admin settings.
     */
    public function getTitle(): string;

    /**
     * Check if this provider is currently active/available on the site.
     */
    public function isAvailable(): bool;

    /**
     * Count total items available for scanning.
     *
     * @param array<string, mixed> $options
     */
    public function countItems(array $options = []): int;

    /**
     * Retrieve a batch of items.
     *
     * @param int $offset
     * @param int $limit
     * @param array<string, mixed> $options
     * @return array<int, mixed>
     */
    public function getItems(int $offset, int $limit, array $options = []): array;

    /**
     * Extract URLs and occurrence metadata from an item.
     *
     * @param mixed $item
     * @return array<int, array{
     *     raw_url: string,
     *     link_type: string,
     *     source_type: string,
     *     source_id: int,
     *     field_key: string,
     *     context_path: string,
     *     anchor_text: ?string,
     *     tag_attributes: array<string, string>,
     *     raw_fragment: ?string
     * }>
     */
    public function extractUrls(mixed $item): array;
}
