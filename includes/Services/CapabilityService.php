<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for managing authorization capabilities.
 */
final class CapabilityService
{
    public const DEFAULT_CAPABILITY = 'manage_options';

    /**
     * Get the required capability for plugin management.
     */
    public static function getRequiredCapability(): string
    {
        /**
         * Filter the required capability to manage WPCalibrate Link Repair.
         *
         * @param string $capability Default 'manage_options'.
         */
        return (string) apply_filters('wpcalibrate_link_repair_capability', self::DEFAULT_CAPABILITY);
    }

    /**
     * Check if the current user has the required capability.
     */
    public static function currentUserCan(): bool
    {
        return current_user_can(self::getRequiredCapability());
    }

    /**
     * Verify capability or throw/return false.
     */
    public static function check(): void
    {
        if (!self::currentUserCan()) {
            wp_die(
                esc_html__('You do not have sufficient permissions to access this page.', 'wpcalibrate-link-repair'),
                esc_html__('Access Denied', 'wpcalibrate-link-repair'),
                ['response' => 403]
            );
        }
    }
}
