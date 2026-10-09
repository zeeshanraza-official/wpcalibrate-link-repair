<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Database;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Services\Logger;

/**
 * Versioned database migrations runner.
 */
final class Migrations
{
    /**
     * Run any pending database migrations.
     */
    public static function run(): void
    {
        $currentVersion = (string) get_option(Installer::SCHEMA_VERSION_OPTION, '0.0.0');

        if (version_compare($currentVersion, '1.0.0', '<')) {
            self::migrateTo100();
        }

        update_option(Installer::SCHEMA_VERSION_OPTION, WPCALIBRATE_LINK_REPAIR_SCHEMA_VERSION, false);
    }

    /**
     * Migration 1.0.0: Initial schema baseline.
     */
    private static function migrateTo100(): void
    {
        Logger::info('Executing database migration 1.0.0 baseline.');
        // Base schema is applied by Installer::install() via Schema::getTableSql()
    }
}
