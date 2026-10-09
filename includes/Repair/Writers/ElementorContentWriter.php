<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Repair\Writers;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Integrations\Elementor\ElementorIntegration;
use WPCalibrate\LinkRepair\Services\Logger;

/**
 * Safely modifies URLs inside Elementor structured JSON metadata without string corruption.
 */
final class ElementorContentWriter implements ContentWriterInterface
{
    public function canHandle(string $sourceType, string $fieldKey): bool
    {
        return $sourceType === 'elementor' || $fieldKey === '_elementor_data';
    }

    public function replaceUrl(
        int $sourceId,
        string $fieldKey,
        string $oldUrl,
        string $newUrl,
        ?string $contextPath = null
    ): array {
        if (!current_user_can('edit_post', $sourceId)) {
            return [
                'success'        => false,
                'message'        => sprintf(__('Unauthorized: cannot edit post #%d.', 'wpcalibrate-link-repair'), $sourceId),
                'modified_count' => 0,
            ];
        }

        $rawJson = get_post_meta($sourceId, '_elementor_data', true);
        if (!is_string($rawJson) || trim($rawJson) === '') {
            return [
                'success'        => false,
                'message'        => __('Elementor data empty or not found.', 'wpcalibrate-link-repair'),
                'modified_count' => 0,
            ];
        }

        $elements = json_decode($rawJson, true);
        if (!is_array($elements)) {
            return [
                'success'        => false,
                'message'        => __('Invalid Elementor JSON structure. Cannot safely modify without risking corruption. Please edit via Elementor editor.', 'wpcalibrate-link-repair'),
                'modified_count' => 0,
            ];
        }

        $modifiedCount = 0;
        $this->updateElementTree($elements, $oldUrl, $newUrl, $modifiedCount);

        if ($modifiedCount === 0) {
            return [
                'success'        => false,
                'message'        => __('Target URL was not found in Elementor settings.', 'wpcalibrate-link-repair'),
                'modified_count' => 0,
            ];
        }

        // Encode back to clean JSON
        $newJson = wp_json_encode($elements);
        if ($newJson === false) {
            return [
                'success'        => false,
                'message'        => __('Failed to encode modified Elementor structure.', 'wpcalibrate-link-repair'),
                'modified_count' => 0,
            ];
        }

        // Update post meta safely using wp_slash to prevent WordPress stripslashes mangling JSON
        update_post_meta($sourceId, '_elementor_data', wp_slash($newJson));

        // Invalidate Elementor caches
        ElementorIntegration::clearCache($sourceId);

        Logger::info("Safely replaced {$modifiedCount} occurrence(s) in Elementor post #{$sourceId}.");
        return [
            'success'        => true,
            'message'        => sprintf(__('Safely updated %d occurrence(s) in Elementor data for post #%d.', 'wpcalibrate-link-repair'), $modifiedCount, $sourceId),
            'modified_count' => $modifiedCount,
        ];
    }

    public function unlinkUrl(
        int $sourceId,
        string $fieldKey,
        string $targetUrl,
        ?string $contextPath = null
    ): array {
        // In Elementor, a link setting cannot be converted to bare text (it represents widget link settings or editor text)
        // For editor text, we can update the HTML snippet
        $rawJson = get_post_meta($sourceId, '_elementor_data', true);
        if (!is_string($rawJson)) {
            return ['success' => false, 'message' => __('Elementor data not found.', 'wpcalibrate-link-repair'), 'modified_count' => 0];
        }

        $elements = json_decode($rawJson, true);
        if (!is_array($elements)) {
            return ['success' => false, 'message' => __('Invalid Elementor structure.', 'wpcalibrate-link-repair'), 'modified_count' => 0];
        }

        $modifiedCount = 0;
        $this->unlinkInElementTree($elements, $targetUrl, $modifiedCount);

        if ($modifiedCount === 0) {
            return ['success' => false, 'message' => __('Anchor tag not found in Elementor text editor blocks.', 'wpcalibrate-link-repair'), 'modified_count' => 0];
        }

        $newJson = wp_json_encode($elements);
        if ($newJson === false) {
            return ['success' => false, 'message' => __('JSON encode error.', 'wpcalibrate-link-repair'), 'modified_count' => 0];
        }

        update_post_meta($sourceId, '_elementor_data', wp_slash($newJson));
        ElementorIntegration::clearCache($sourceId);

        return [
            'success'        => true,
            'message'        => sprintf(__('Unlinked %d anchor(s) in Elementor post #%d.', 'wpcalibrate-link-repair'), $modifiedCount, $sourceId),
            'modified_count' => $modifiedCount,
        ];
    }

    /**
     * Recursively update matching exact URLs in Elementor element settings.
     */
    private function updateElementTree(array &$elements, string $oldUrl, string $newUrl, int &$count): void
    {
        foreach ($elements as &$node) {
            if (!is_array($node)) {
                continue;
            }

            if (isset($node['settings']) && is_array($node['settings'])) {
                // Link setting
                if (isset($node['settings']['link']['url']) && $node['settings']['link']['url'] === $oldUrl) {
                    $node['settings']['link']['url'] = $newUrl;
                    $count++;
                }

                // Image setting
                if (isset($node['settings']['image']['url']) && $node['settings']['image']['url'] === $oldUrl) {
                    $node['settings']['image']['url'] = $newUrl;
                    $count++;
                }

                // Editor HTML setting
                if (isset($node['settings']['editor']) && is_string($node['settings']['editor'])) {
                    $pattern = PostContentWriter::buildUrlAttributeRegex($oldUrl);
                    $subCount = 0;
                    $newEditor = preg_replace_callback(
                        $pattern,
                        static function (array $m) use ($newUrl, &$subCount): string {
                            $subCount++;
                            return "{$m[1]}={$m[2]}{$newUrl}{$m[2]}";
                        },
                        $node['settings']['editor']
                    );
                    if ($subCount > 0 && $newEditor !== null) {
                        $node['settings']['editor'] = $newEditor;
                        $count += $subCount;
                    }
                }
            }

            if (isset($node['elements']) && is_array($node['elements'])) {
                $this->updateElementTree($node['elements'], $oldUrl, $newUrl, $count);
            }
        }
    }

    /**
     * Recursively unlink anchor tags inside Elementor text editor settings.
     */
    private function unlinkInElementTree(array &$elements, string $targetUrl, int &$count): void
    {
        foreach ($elements as &$node) {
            if (!is_array($node)) {
                continue;
            }

            if (isset($node['settings']['editor']) && is_string($node['settings']['editor'])) {
                $pattern = PostContentWriter::buildAnchorTagRegex($targetUrl);
                $subCount = 0;
                $newEditor = preg_replace_callback(
                    $pattern,
                    static function (array $m) use (&$subCount): string {
                        $subCount++;
                        return $m[3];
                    },
                    $node['settings']['editor']
                );
                if ($subCount > 0 && $newEditor !== null) {
                    $node['settings']['editor'] = $newEditor;
                    $count += $subCount;
                }
            }

            if (isset($node['elements']) && is_array($node['elements'])) {
                $this->unlinkInElementTree($node['elements'], $targetUrl, $count);
            }
        }
    }
}
