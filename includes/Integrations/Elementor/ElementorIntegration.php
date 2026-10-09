<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Integrations\Elementor;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optional Elementor integration manager.
 */
final class ElementorIntegration
{
    /**
     * Check if Elementor is active and loaded.
     */
    public static function isElementorActive(): bool
    {
        return did_action('elementor/loaded') > 0 || class_exists('\\Elementor\\Plugin');
    }

    /**
     * Regenerate / clear Elementor CSS and files cache safely.
     */
    public static function clearCache(int $postId = 0): void
    {
        if (!self::isElementorActive()) {
            return;
        }

        try {
            if (isset(\Elementor\Plugin::$instance->files_manager)) {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            }
            if ($postId > 0 && isset(\Elementor\Plugin::$instance->posts_css_manager)) {
                $postCss = \Elementor\Plugin::$instance->posts_css_manager->get($postId);
                if ($postCss && method_exists($postCss, 'delete')) {
                    $postCss->delete();
                }
            }
        } catch (\Throwable) {
            // Ignore cache flush failures silently
        }
    }
}
