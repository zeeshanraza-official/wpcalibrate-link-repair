<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Repair\Writers;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Services\Logger;

/**
 * Handles link replacement for WordPress navigation menu items.
 */
final class NavMenuWriter implements ContentWriterInterface
{
    public function canHandle(string $sourceType, string $fieldKey): bool
    {
        return $sourceType === 'nav_menu_item' && $fieldKey === '_menu_item_url';
    }

    public function replaceUrl(
        int $sourceId,
        string $fieldKey,
        string $oldUrl,
        string $newUrl,
        ?string $contextPath = null
    ): array {
        if (!current_user_can('edit_theme_options')) {
            return [
                'success'        => false,
                'message'        => __('Unauthorized: cannot edit menu items.', 'wpcalibrate-link-repair'),
                'modified_count' => 0,
            ];
        }

        $currentUrl = (string) get_post_meta($sourceId, '_menu_item_url', true);
        if ($currentUrl !== $oldUrl) {
            return [
                'success'        => false,
                'message'        => __('Menu item URL does not match target URL.', 'wpcalibrate-link-repair'),
                'modified_count' => 0,
            ];
        }

        update_post_meta($sourceId, '_menu_item_url', esc_url_raw($newUrl));
        Logger::info("Updated menu item #{$sourceId} URL from {$oldUrl} to {$newUrl}.");

        return [
            'success'        => true,
            'message'        => sprintf(__('Updated menu item #%d URL.', 'wpcalibrate-link-repair'), $sourceId),
            'modified_count' => 1,
        ];
    }

    public function unlinkUrl(
        int $sourceId,
        string $fieldKey,
        string $targetUrl,
        ?string $contextPath = null
    ): array {
        // Navigation menu item cannot be unlinked into bare text; it can be pointed to '#' or left as is
        return [
            'success'        => false,
            'message'        => __('Navigation menu items cannot be unlinked into text. Please edit or delete the menu item.', 'wpcalibrate-link-repair'),
            'modified_count' => 0,
        ];
    }
}
