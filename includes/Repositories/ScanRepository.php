<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Database\Schema;

/**
 * Repository for managing scan execution runs and diagnostic history.
 */
final class ScanRepository
{
    private string $table;

    public function __construct()
    {
        $tables = Schema::getTableNames();
        $this->table = $tables['scans'];
    }

    /**
     * Create a new scan run record.
     */
    public function createScan(string $triggerType = 'manual'): int
    {
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $this->table,
            [
                'trigger_type'     => $triggerType,
                'status'           => 'pending',
                'stage'            => 'discovery',
                'current_step'     => 'initializing',
                'cursor_data'      => wp_json_encode(['provider_index' => 0, 'offset' => 0]),
                'total_discovered' => 0,
                'total_checked'    => 0,
                'count_working'    => 0,
                'count_broken'     => 0,
                'count_redirects'  => 0,
                'count_warnings'   => 0,
                'count_ignored'    => 0,
                'started_at'       => $now,
                'completed_at'     => null,
                'error_summary'    => null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Retrieve scan record by ID.
     */
    public function getScan(int $id): ?object
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $row = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE id = %d",
                $id
            )
        );
        return is_object($row) ? $row : null;
    }

    /**
     * Retrieve the currently active scan (pending, running, or paused) if any.
     */
    public function getActiveScan(): ?object
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $row = $wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT * FROM {$this->table} WHERE status IN ('pending', 'running', 'paused') ORDER BY id DESC LIMIT 1"
        );
        return is_object($row) ? $row : null;
    }

    /**
     * Retrieve the most recent scan (regardless of status).
     */
    public function getLatestScan(): ?object
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $row = $wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT * FROM {$this->table} ORDER BY id DESC LIMIT 1"
        );
        return is_object($row) ? $row : null;
    }

    /**
     * Update scan run record.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public function updateScan(int $id, array $data): bool
    {
        global $wpdb;
        $data['updated_at'] = gmdate('Y-m-d H:i:s');

        // Formats mapping
        $formats = [];
        foreach ($data as $key => $val) {
            if (is_int($val)) {
                $formats[] = '%d';
            } elseif (is_float($val)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return (bool) $wpdb->update($this->table, $data, ['id' => $id], $formats, ['%d']);
    }

    /**
     * Retrieve recent scans for history tab.
     *
     * @param int $limit
     * @param int $offset
     * @return array<int, object>
     */
    public function getRecentScans(int $limit = 20, int $offset = 0): array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} ORDER BY id DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Count total scan records.
     */
    public function countScans(): int
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return (int) $wpdb->get_var("SELECT COUNT(id) FROM {$this->table}");
    }

    /**
     * Delete scans older than retention threshold.
     */
    public function cleanupOldScans(int $retentionDays): int
    {
        global $wpdb;
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($retentionDays * 86400));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return (int) $wpdb->query(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "DELETE FROM {$this->table} WHERE created_at < %s AND status NOT IN ('pending', 'running', 'paused')",
                $cutoff
            )
        );
    }
}
