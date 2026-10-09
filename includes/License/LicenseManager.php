<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\License;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Services\CapabilityService;

/**
 * Manages licensing operations and provider delegation.
 */
final class LicenseManager
{
    private static ?LicenseProviderInterface $provider = null;

    public static function getProvider(): LicenseProviderInterface
    {
        if (self::$provider !== null) {
            return self::$provider;
        }

        $default = new DefaultLicenseProvider();
        /**
         * Filter license provider instance.
         *
         * @param LicenseProviderInterface $provider
         */
        $filtered = apply_filters('wpcalibrate_link_repair_license_provider', $default);

        self::$provider = ($filtered instanceof LicenseProviderInterface) ? $filtered : $default;
        return self::$provider;
    }

    public static function setProvider(?LicenseProviderInterface $provider): void
    {
        self::$provider = $provider;
    }

    /**
     * Handle saving license key.
     */
    public static function saveKey(string $key): void
    {
        CapabilityService::check();
        self::getProvider()->setKey($key);
    }

    /**
     * Handle activation attempt.
     *
     * @return array{success: bool, message: string}
     */
    public static function activate(): array
    {
        CapabilityService::check();
        return self::getProvider()->activate();
    }

    /**
     * Handle deactivation attempt.
     *
     * @return array{success: bool, message: string}
     */
    public static function deactivate(): array
    {
        CapabilityService::check();
        return self::getProvider()->deactivate();
    }
}
