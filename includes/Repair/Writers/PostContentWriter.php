<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Repair\Writers;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Services\Logger;

/**
 * Safely replaces and unlinks URLs in standard WordPress post_content and post_excerpt.
 */
final class PostContentWriter implements ContentWriterInterface
{
    public function canHandle(string $sourceType, string $fieldKey): bool
    {
        return in_array($fieldKey, ['post_content', 'post_excerpt'], true)
            && ($sourceType === 'post' || $sourceType === 'page' || post_type_exists($sourceType));
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

        $post = get_post($sourceId);
        if (!is_object($post) || !isset($post->ID, $post->{$fieldKey})) {
            return [
                'success'        => false,
                'message'        => sprintf(__('Post #%d not found.', 'wpcalibrate-link-repair'), $sourceId),
                'modified_count' => 0,
            ];
        }

        $content = (string) $post->{$fieldKey};
        $pattern = self::buildUrlAttributeRegex($oldUrl);
        $count = 0;

        $newContent = preg_replace_callback(
            $pattern,
            static function (array $matches) use ($newUrl, &$count): string {
                $count++;
                $attr = $matches[1];
                $quote = $matches[2];
                return "{$attr}={$quote}{$newUrl}{$quote}";
            },
            $content
        );

        if ($count === 0 || $newContent === null || $newContent === $content) {
            return [
                'success'        => false,
                'message'        => __('Target URL was not found in content or already replaced.', 'wpcalibrate-link-repair'),
                'modified_count' => 0,
            ];
        }

        // Update post safely preserving post_status, post_date, etc.
        $updateArgs = [
            'ID'        => $sourceId,
            $fieldKey   => $newContent,
        ];

        // Disable unslash recursion issues by passing properly slashed data
        $result = wp_update_post(wp_slash($updateArgs), true);
        if (is_wp_error($result)) {
            Logger::error("Failed to update post #{$sourceId}: " . $result->get_error_message());
            return [
                'success'        => false,
                'message'        => $result->get_error_message(),
                'modified_count' => 0,
            ];
        }

        Logger::info("Replaced {$count} occurrences of URL in post #{$sourceId} ({$fieldKey}).");
        return [
            'success'        => true,
            'message'        => sprintf(__('Successfully updated %d occurrence(s) in post #%d.', 'wpcalibrate-link-repair'), $count, $sourceId),
            'modified_count' => $count,
        ];
    }

    public function unlinkUrl(
        int $sourceId,
        string $fieldKey,
        string $targetUrl,
        ?string $contextPath = null
    ): array {
        if (!current_user_can('edit_post', $sourceId)) {
            return [
                'success'        => false,
                'message'        => sprintf(__('Unauthorized: cannot edit post #%d.', 'wpcalibrate-link-repair'), $sourceId),
                'modified_count' => 0,
            ];
        }

        $post = get_post($sourceId);
        if (!is_object($post) || !isset($post->ID, $post->{$fieldKey})) {
            return [
                'success'        => false,
                'message'        => sprintf(__('Post #%d not found.', 'wpcalibrate-link-repair'), $sourceId),
                'modified_count' => 0,
            ];
        }

        $content = (string) $post->{$fieldKey};
        $pattern = self::buildAnchorTagRegex($targetUrl);
        $count = 0;

        $newContent = preg_replace_callback(
            $pattern,
            static function (array $matches) use (&$count): string {
                $count++;
                // $matches[3] is the inner anchor text / content
                return $matches[3];
            },
            $content
        );

        if ($count === 0 || $newContent === null || $newContent === $content) {
            return [
                'success'        => false,
                'message'        => __('Target anchor tag not found in content.', 'wpcalibrate-link-repair'),
                'modified_count' => 0,
            ];
        }

        $updateArgs = [
            'ID'      => $sourceId,
            $fieldKey => $newContent,
        ];

        $result = wp_update_post(wp_slash($updateArgs), true);
        if (is_wp_error($result)) {
            return [
                'success'        => false,
                'message'        => $result->get_error_message(),
                'modified_count' => 0,
            ];
        }

        Logger::info("Unlinked {$count} anchor(s) in post #{$sourceId} ({$fieldKey}).");
        return [
            'success'        => true,
            'message'        => sprintf(__('Successfully unlinked %d anchor(s) while preserving text in post #%d.', 'wpcalibrate-link-repair'), $count, $sourceId),
            'modified_count' => $count,
        ];
    }

    /**
     * Regex matching href="..." or src="..." with the exact target URL.
     */
    public static function buildUrlAttributeRegex(string $url): string
    {
        $quoted = preg_quote($url, '#');
        return '#\b(href|src)\s*=\s*(["\'])' . $quoted . '\2#is';
    }

    /**
     * Regex matching full <a> tag pointing to target URL.
     */
    public static function buildAnchorTagRegex(string $url): string
    {
        $quoted = preg_quote($url, '#');
        return '#<a\b([^>]*\bhref\s*=\s*(["\'])' . $quoted . '\2[^>]*)>(.*?)<\/a>#is';
    }
}
