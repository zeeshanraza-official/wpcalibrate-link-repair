<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Integrations\Elementor;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Discovery\Providers\ContentProviderInterface;
use WPCalibrate\LinkRepair\Discovery\HtmlLinkExtractor;
use WPCalibrate\LinkRepair\Http\UrlNormalizer;
use WPCalibrate\LinkRepair\Services\SettingsService;

/**
 * Discovers links safely within structured Elementor page builder data.
 */
final class ElementorProvider implements ContentProviderInterface
{
    public function getId(): string
    {
        return 'elementor';
    }

    public function getTitle(): string
    {
        return __('Elementor Builder Content', 'wpcalibrate-link-repair');
    }

    public function isAvailable(): bool
    {
        return ElementorIntegration::isElementorActive();
    }

    public function countItems(array $options = []): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $count = $wpdb->get_var(
            "SELECT COUNT(post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_edit_mode' AND meta_value = 'builder'"
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
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_edit_mode' AND meta_value = 'builder' 
                 ORDER BY post_id ASC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );

        $posts = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $post = get_post((int) $row->post_id);
                if ($post instanceof \WP_Post) {
                    $posts[] = $post;
                }
            }
        }

        return $posts;
    }

    public function extractUrls(mixed $item): array
    {
        if (!is_object($item) || !isset($item->ID)) {
            return [];
        }

        $postId = (int) $item->ID;
        $rawJson = get_post_meta($postId, '_elementor_data', true);
        if (!is_string($rawJson) || trim($rawJson) === '') {
            return [];
        }

        $elements = json_decode($rawJson, true);
        if (!is_array($elements)) {
            return [];
        }

        $scanMedia = (bool) SettingsService::get('scan_media', true);
        $occurrences = [];
        $this->walkElements($elements, $postId, '', $occurrences, $scanMedia);

        return $occurrences;
    }

    /**
     * Recursively walk Elementor elements tree to extract links and media.
     *
     * @param array<int, mixed>|mixed $elements
     * @param int $postId
     * @param string $path
     * @param array<int, mixed> $results
     * @param bool $scanMedia
     */
    private function walkElements(
        mixed $elements,
        int $postId,
        string $path,
        array &$results,
        bool $scanMedia
    ): void {
        if (!is_array($elements)) {
            return;
        }

        foreach ($elements as $key => $node) {
            $currentPath = $path === '' ? (string) $key : "{$path}.{$key}";

            if (!is_array($node)) {
                continue;
            }

            // Inspect settings array
            if (isset($node['settings']) && is_array($node['settings'])) {
                $settings = $node['settings'];
                $widgetType = $node['widgetType'] ?? ($node['elType'] ?? 'element');

                // 1. Link setting (e.g. ['link']['url'] or ['url'])
                if (isset($settings['link']['url']) && is_string($settings['link']['url']) && trim($settings['link']['url']) !== '') {
                    $url = trim($settings['link']['url']);
                    $results[] = [
                        'raw_url'        => $url,
                        'link_type'      => 'link',
                        'source_type'    => 'elementor',
                        'source_id'      => $postId,
                        'field_key'      => '_elementor_data',
                        'context_path'   => "{$currentPath}.settings.link.url",
                        'anchor_text'    => sprintf(__('Elementor %s Link', 'wpcalibrate-link-repair'), $widgetType),
                        'tag_attributes' => [],
                        'raw_fragment'   => UrlNormalizer::extractFragment($url),
                    ];
                }

                // 2. Editor HTML setting
                if (isset($settings['editor']) && is_string($settings['editor'])) {
                    $htmlLinks = HtmlLinkExtractor::extract(
                        $settings['editor'],
                        'elementor',
                        $postId,
                        '_elementor_data',
                        $scanMedia
                    );
                    foreach ($htmlLinks as $hl) {
                        $hl['context_path'] = "{$currentPath}.settings.editor:" . $hl['context_path'];
                        $results[] = $hl;
                    }
                }

                // 3. Image setting
                if ($scanMedia && isset($settings['image']['url']) && is_string($settings['image']['url']) && trim($settings['image']['url']) !== '') {
                    $imgUrl = trim($settings['image']['url']);
                    $results[] = [
                        'raw_url'        => $imgUrl,
                        'link_type'      => 'image',
                        'source_type'    => 'elementor',
                        'source_id'      => $postId,
                        'field_key'      => '_elementor_data',
                        'context_path'   => "{$currentPath}.settings.image.url",
                        'anchor_text'    => sprintf(__('Elementor %s Image', 'wpcalibrate-link-repair'), $widgetType),
                        'tag_attributes' => [],
                        'raw_fragment'   => null,
                    ];
                }
            }

            // Recurse into child elements
            if (isset($node['elements']) && is_array($node['elements'])) {
                $this->walkElements($node['elements'], $postId, "{$currentPath}.elements", $results, $scanMedia);
            }
        }
    }
}
