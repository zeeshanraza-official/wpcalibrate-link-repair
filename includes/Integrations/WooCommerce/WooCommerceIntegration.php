<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Integrations\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Optional WooCommerce integration manager with HPOS compatibility declaration.
 */
final class WooCommerceIntegration
{
    /**
     * Check if WooCommerce is active.
     */
    public static function isWooCommerceActive(): bool
    {
        return class_exists('\\WooCommerce');
    }

    /**
     * Declare High-Performance Order Storage (HPOS) compatibility.
     */
    public static function declareHposCompatibility(): void
    {
        if (class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                WPCALIBRATE_LINK_REPAIR_FILE,
                true
            );
        }
    }
}
