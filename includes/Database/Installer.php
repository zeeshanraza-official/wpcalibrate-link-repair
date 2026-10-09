<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Database;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Services\Logger;

/**
 * Handles database installation, verification, and schema version updates.
 */
final class Installer
{
    public const SCHEMA_VERSION_OPTION = 'wpcalibrate_lr_schema_version';

    /**
     * Install or update database tables.
     */
    public static function install(): bool
    {
        if (file_exists(ABSPATH . 'wp-admin/includes/upgrade.php')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $queries = Schema::getTableSql();
        foreach ($queries as $sql) {
            if (function_exists('dbDelta')) {
                dbDelta($sql);
            }
        }

        // Verify tables exist
        if (!self::verifyTables()) {
            Logger::error('Failed to create or verify WPCalibrate Link Repair database tables.');
            return false;
        }

        // Run migrations if needed
        Migrations::run();

        update_option(self::SCHEMA_VERSION_OPTION, WPCALIBRATE_LINK_REPAIR_SCHEMA_VERSION, false);
        Logger::info('Database installation/update completed successfully.', [
            'schema_version' => WPCALIBRATE_LINK_REPAIR_SCHEMA_VERSION,
        ]);

        return true;
    }

    /**
     * Check if all required custom tables exist in the database.
     */
    public static function verifyTables(): bool
    {
        global $wpdb;
        $tables = Schema::getTableNames();

        foreach ($tables as $key => $tableName) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableName));
            if ($found !== $tableName) {
                return false;
            }
        }

        return true;
    }

    /**
     * Drop plugin tables on uninstall if explicitly requested.
     */
    public static function dropTables(): void
    {
        global $wpdb;
        $tables = Schema::getTableNames();

        foreach ($tables as $tableName) {
            // Table names are hardcoded with $wpdb->prefix in Schema::getTableNames()
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("DROP TABLE IF EXISTS `{$tableName}`");
        }

        delete_option(self::SCHEMA_VERSION_OPTION);
    }
}
