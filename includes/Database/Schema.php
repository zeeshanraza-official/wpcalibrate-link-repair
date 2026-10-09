<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database schema definitions for WPCalibrate Link Repair.
 */
final class Schema
{
    /**
     * Get table names with WordPress table prefix.
     *
     * @return array<string, string>
     */
    public static function getTableNames(): array
    {
        global $wpdb;
        return [
            'urls'        => $wpdb->prefix . 'wpcalibrate_lr_urls',
            'occurrences' => $wpdb->prefix . 'wpcalibrate_lr_occurrences',
            'scans'       => $wpdb->prefix . 'wpcalibrate_lr_scans',
        ];
    }

    /**
     * Get SQL definitions for dbDelta().
     *
     * @return array<int, string>
     */
    public static function getTableSql(): array
    {
        global $wpdb;
        $collate = $wpdb->get_charset_collate();
        $tables  = self::getTableNames();

        return [
            // URLs table
            "CREATE TABLE {$tables['urls']} (
  id bigint(20) unsigned NOT NULL auto_increment,
  url_hash char(64) NOT NULL,
  url text NOT NULL,
  scheme varchar(16) NOT NULL default 'http',
  host varchar(255) NOT NULL default '',
  link_type varchar(32) NOT NULL default 'link',
  is_internal tinyint(1) NOT NULL default 0,
  status varchar(32) NOT NULL default 'pending',
  http_code smallint(5) unsigned default NULL,
  redirect_url text default NULL,
  error_message varchar(255) default NULL,
  check_count int(10) unsigned NOT NULL default 0,
  consecutive_failures tinyint(3) unsigned NOT NULL default 0,
  is_ignored tinyint(1) NOT NULL default 0,
  is_whitelisted tinyint(1) NOT NULL default 0,
  first_discovered_at datetime NOT NULL,
  last_discovered_at datetime NOT NULL,
  last_checked_at datetime default NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY idx_url_hash (url_hash),
  KEY idx_status (status),
  KEY idx_is_internal (is_internal),
  KEY idx_link_type (link_type),
  KEY idx_is_ignored (is_ignored),
  KEY idx_last_checked_at (last_checked_at)
) {$collate};",

            // Occurrences table
            "CREATE TABLE {$tables['occurrences']} (
  id bigint(20) unsigned NOT NULL auto_increment,
  url_id bigint(20) unsigned NOT NULL,
  source_type varchar(64) NOT NULL,
  source_id bigint(20) unsigned NOT NULL default 0,
  field_key varchar(128) NOT NULL default 'post_content',
  context_path varchar(255) NOT NULL default '',
  anchor_text varchar(500) default NULL,
  tag_attributes text default NULL,
  raw_fragment varchar(255) default NULL,
  last_scanned_at datetime NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_url_id (url_id),
  KEY idx_source (source_type, source_id),
  UNIQUE KEY idx_occurrence_unique (url_id, source_type, source_id, field_key, context_path(100))
) {$collate};",

            // Scans table
            "CREATE TABLE {$tables['scans']} (
  id bigint(20) unsigned NOT NULL auto_increment,
  trigger_type varchar(32) NOT NULL default 'manual',
  status varchar(32) NOT NULL default 'pending',
  stage varchar(32) NOT NULL default 'discovery',
  current_step varchar(64) NOT NULL default '',
  cursor_data text default NULL,
  total_discovered int(10) unsigned NOT NULL default 0,
  total_checked int(10) unsigned NOT NULL default 0,
  count_working int(10) unsigned NOT NULL default 0,
  count_broken int(10) unsigned NOT NULL default 0,
  count_redirects int(10) unsigned NOT NULL default 0,
  count_warnings int(10) unsigned NOT NULL default 0,
  count_ignored int(10) unsigned NOT NULL default 0,
  started_at datetime default NULL,
  completed_at datetime default NULL,
  error_summary varchar(500) default NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_status (status),
  KEY idx_created_at (created_at)
) {$collate};",
        ];
    }
}
