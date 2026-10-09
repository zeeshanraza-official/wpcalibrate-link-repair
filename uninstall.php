<?php
/**
 * WPCalibrate Link Repair - Uninstall Handler
 *
 * Triggered only when the plugin is deleted from the WordPress admin plugins screen.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if administrator intentionally enabled data deletion on uninstall
$settings = get_option('wpcalibrate_lr_settings', []);
$deleteData = is_array($settings) && !empty($settings['delete_data_on_uninstall']);

if (!$deleteData) {
    // Preserve tables and options by default to protect user data from accidental loss
    return;
}

global $wpdb;

// 1. Drop custom plugin tables
$tableUrls        = $wpdb->prefix . 'wpcalibrate_lr_urls';
$tableOccurrences = $wpdb->prefix . 'wpcalibrate_lr_occurrences';
$tableScans       = $wpdb->prefix . 'wpcalibrate_lr_scans';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query("DROP TABLE IF EXISTS `{$tableOccurrences}`");
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query("DROP TABLE IF EXISTS `{$tableUrls}`");
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query("DROP TABLE IF EXISTS `{$tableScans}`");

// 2. Delete plugin options
delete_option('wpcalibrate_lr_settings');
delete_option('wpcalibrate_lr_schema_version');
delete_option('wpcalibrate_lr_license_data');
delete_option('wpcalibrate_lr_recent_logs');

// 3. Clear transient locks
delete_transient('wpcalibrate_lr_scan_lock');

// 4. Clear scheduled events
if (function_exists('wp_unschedule_hook')) {
    wp_unschedule_hook('wpcalibrate_lr_run_batch');
    wp_unschedule_hook('wpcalibrate_lr_scheduled_scan');
}

// 5. Clear Action Scheduler jobs if Action Scheduler is available
if (function_exists('as_unschedule_all_actions')) {
    try {
        as_unschedule_all_actions('wpcalibrate_lr_run_batch', null, 'wpcalibrate-link-repair');
        as_unschedule_all_actions('wpcalibrate_lr_scheduled_scan', null, 'wpcalibrate-link-repair');
    } catch (\Throwable) {
        // Silently continue
    }
}
