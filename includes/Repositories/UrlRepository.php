<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Database\Schema;
use WPCalibrate\LinkRepair\Http\UrlNormalizer;

/**
 * Repository for managing unique URLs in custom table.
 */
final class UrlRepository
{
    private string $table;

    public function __construct()
    {
        $tables = Schema::getTableNames();
        $this->table = $tables['urls'];
    }

    /**
     * Find existing URL record by normalized URL or insert new record.
     *
     * @param string $normalizedUrl
     * @param array<string, mixed> $extra
     * @return int URL ID
     */
    public function findOrCreate(string $normalizedUrl, array $extra = []): int
    {
        global $wpdb;
        $hash = UrlNormalizer::hash($normalizedUrl);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $existingId = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT id FROM {$this->table} WHERE url_hash = %s",
                $hash
            )
        );

        $now = gmdate('Y-m-d H:i:s');

        if ($existingId !== null) {
            $id = (int) $existingId;
            // Update last discovered timestamp
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->update(
                $this->table,
                ['last_discovered_at' => $now, 'updated_at' => $now],
                ['id' => $id],
                ['%s', '%s'],
                ['%d']
            );
            return $id;
        }

        $parsed = wp_parse_url($normalizedUrl);
        $scheme = $parsed['scheme'] ?? 'http';
        $host   = $parsed['host'] ?? '';
        $isInternal = UrlNormalizer::isInternal($normalizedUrl) ? 1 : 0;
        $linkType = $extra['link_type'] ?? UrlNormalizer::classifyLinkType($normalizedUrl);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $this->table,
            [
                'url_hash'             => $hash,
                'url'                  => $normalizedUrl,
                'scheme'               => $scheme,
                'host'                 => $host,
                'link_type'            => $linkType,
                'is_internal'          => $isInternal,
                'status'               => 'pending',
                'http_code'            => null,
                'redirect_url'         => null,
                'error_message'        => null,
                'check_count'          => 0,
                'consecutive_failures' => 0,
                'is_ignored'           => 0,
                'is_whitelisted'       => 0,
                'first_discovered_at'  => $now,
                'last_discovered_at'   => $now,
                'last_checked_at'      => null,
                'created_at'           => $now,
                'updated_at'           => $now,
            ],
            [
                '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s',
                '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s'
            ]
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Find URL record by ID.
     */
    public function findById(int $id): ?object
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
     * Find URL record by hash.
     */
    public function findByHash(string $hash): ?object
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $row = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE url_hash = %s",
                $hash
            )
        );
        return is_object($row) ? $row : null;
    }

    /**
     * Update health check results for a URL.
     *
     * @param int $id
     * @param array<string, mixed> $data
     * @return bool
     */
    public function updateCheckResult(int $id, array $data): bool
    {
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');

        $updateData = [
            'status'          => $data['status'] ?? 'pending',
            'http_code'       => isset($data['http_code']) ? (int) $data['http_code'] : null,
            'redirect_url'    => $data['redirect_url'] ?? null,
            'error_message'   => $data['error_message'] ?? null,
            'last_checked_at' => $now,
            'updated_at'      => $now,
        ];

        // Increment check_count and handle consecutive failures
        if (($data['status'] ?? '') === 'broken' || ($data['status'] ?? '') === 'server_error') {
            $updateData['consecutive_failures'] = ($data['consecutive_failures'] ?? 1);
        } else {
            $updateData['consecutive_failures'] = 0;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->query(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "UPDATE {$this->table} SET 
                    status = %s, 
                    http_code = %d, 
                    redirect_url = %s, 
                    error_message = %s, 
                    consecutive_failures = %d, 
                    check_count = check_count + 1, 
                    last_checked_at = %s, 
                    updated_at = %s 
                 WHERE id = %d",
                $updateData['status'],
                $updateData['http_code'],
                $updateData['redirect_url'],
                $updateData['error_message'],
                $updateData['consecutive_failures'],
                $updateData['last_checked_at'],
                $updateData['updated_at'],
                $id
            )
        );

        return true;
    }

    /**
     * Get batch of non-ignored URLs with ID strictly greater than $lastId.
     * Guarantees monotonic, primary-key-indexed cursor iteration and prevents infinite loops.
     *
     * @param int $lastId
     * @param int $limit
     * @return array<int, object>
     */
    public function getUrlsAfterId(int $lastId = 0, int $limit = 25): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE is_ignored = 0 AND id > %d ORDER BY id ASC LIMIT %d",
                $lastId,
                $limit
            )
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Count all non-ignored URLs eligible for validation.
     *
     * @return int
     */
    public function countNonIgnored(): int
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return (int) $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT COUNT(id) FROM {$this->table} WHERE is_ignored = 0"
        );
    }

    /**
     * Get batch of URLs needing validation.
     *
     * @param int $limit
     * @param array<int, int> $excludeIds
     * @return array<int, object>
     */
    public function getPendingValidation(int $limit = 25, array $excludeIds = []): array
    {
        global $wpdb;
        $excludeSql = '';
        if (!empty($excludeIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeIds), '%d'));
            $excludeSql = $wpdb->prepare(" AND id NOT IN ({$placeholders})", ...$excludeIds);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE is_ignored = 0 {$excludeSql} ORDER BY last_checked_at ASC, id ASC LIMIT %d",
                $limit
            )
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Retrieve aggregate counters for the dashboard.
     *
     * @return array<string, int>
     */
    public function getDashboardCounts(): array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $counts = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT status, COUNT(id) as cnt FROM {$this->table} GROUP BY status"
        );

        $summary = [
            'total'       => 0,
            'checked'     => 0,
            'working'     => 0,
            'broken'      => 0,
            'redirect'    => 0,
            'warning'     => 0,
            'ignored'     => 0,
            'pending'     => 0,
        ];

        if (is_array($counts)) {
            foreach ($counts as $row) {
                $status = $row->status;
                $cnt = (int) $row->cnt;
                $summary['total'] += $cnt;

                if (in_array($status, ['working', 'broken', 'redirect', 'warning', 'ignored', 'pending'], true)) {
                    $summary[$status] = ($summary[$status] ?? 0) + $cnt;
                } elseif (in_array($status, ['server_error', 'timeout', 'dns_error', 'ssl_error', 'connection_error', 'auth_restricted', 'rate_limited'], true)) {
                    $summary['warning'] += $cnt;
                }

                if ($status !== 'pending') {
                    $summary['checked'] += $cnt;
                }
            }
        }

        // Add ignored count
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $summary['ignored'] = (int) $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "SELECT COUNT(id) FROM {$this->table} WHERE is_ignored = 1"
        );

        return $summary;
    }

    /**
     * Query URLs with search, filter, pagination and strict allowlist sorting.
     *
     * @param array<string, mixed> $filters
     * @param int $limit
     * @param int $offset
     * @param string $orderBy
     * @param string $orderDir
     * @return array<int, object>
     */
    public function queryUrls(
        array $filters = [],
        int $limit = 20,
        int $offset = 0,
        string $orderBy = 'last_checked_at',
        string $orderDir = 'DESC'
    ): array {
        global $wpdb;

        // Allowlist sort columns
        $allowedColumns = [
            'id'              => 'u.id',
            'url'             => 'u.url',
            'status'          => 'u.status',
            'http_code'       => 'u.http_code',
            'link_type'       => 'u.link_type',
            'last_checked_at' => 'u.last_checked_at',
            'first_discovered_at' => 'u.first_discovered_at',
        ];
        $sortCol = $allowedColumns[$orderBy] ?? 'u.last_checked_at';
        $dir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        [$whereSql, $args] = $this->buildWhereClause($filters);

        $tables = Schema::getTableNames();
        $occTable = $tables['occurrences'];

        $sql = "SELECT u.*, COUNT(o.id) as occurrence_count 
                FROM {$this->table} u 
                LEFT JOIN {$occTable} o ON u.id = o.url_id 
                {$whereSql} 
                GROUP BY u.id 
                ORDER BY {$sortCol} {$dir} 
                LIMIT %d OFFSET %d";

        $args[] = $limit;
        $args[] = $offset;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$args));
        return is_array($rows) ? $rows : [];
    }

    /**
     * Count total URLs matching filters.
     *
     * @param array<string, mixed> $filters
     * @return int
     */
    public function countUrls(array $filters = []): int
    {
        global $wpdb;
        [$whereSql, $args] = $this->buildWhereClause($filters);
        $sql = "SELECT COUNT(u.id) FROM {$this->table} u {$whereSql}";

        if (!empty($args)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            return (int) $wpdb->get_var($wpdb->prepare($sql, ...$args));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return (int) $wpdb->get_var($sql);
    }

    /**
     * Build parameterized WHERE clause for filtering.
     *
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildWhereClause(array $filters): array
    {
        global $wpdb;
        $clauses = [];
        $args = [];

        // Search by URL
        if (!empty($filters['search'])) {
            $like = '%' . $wpdb->esc_like(trim((string) $filters['search'])) . '%';
            $clauses[] = 'u.url LIKE %s';
            $args[] = $like;
        }

        // Status filter
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $st = (string) $filters['status'];
            if ($st === 'broken') {
                $clauses[] = "u.status IN ('broken', 'server_error', 'connection_error')";
            } elseif ($st === 'redirect') {
                $clauses[] = "u.status = 'redirect'";
            } elseif ($st === 'warning') {
                $clauses[] = "u.status IN ('warning', 'timeout', 'dns_error', 'ssl_error', 'auth_restricted', 'rate_limited')";
            } elseif ($st === 'working') {
                $clauses[] = "u.status = 'working'";
            } elseif ($st === 'ignored') {
                $clauses[] = "u.is_ignored = 1";
            } elseif ($st === 'internal') {
                $clauses[] = "u.is_internal = 1";
            } elseif ($st === 'external') {
                $clauses[] = "u.is_internal = 0";
            } elseif ($st === 'media' || $st === 'image') {
                $clauses[] = "u.link_type IN ('image', 'media')";
            } else {
                $clauses[] = 'u.status = %s';
                $args[] = $st;
            }
        }

        // If not explicitly filtering by ignored, hide ignored by default unless requested
        if (empty($filters['status']) || $filters['status'] !== 'ignored') {
            if (isset($filters['show_ignored']) && !$filters['show_ignored']) {
                $clauses[] = 'u.is_ignored = 0';
            }
        }

        // HTTP code filter
        if (!empty($filters['http_code'])) {
            $clauses[] = 'u.http_code = %d';
            $args[] = (int) $filters['http_code'];
        }

        $where = empty($clauses) ? '' : 'WHERE ' . implode(' AND ', $clauses);
        return [$where, $args];
    }

    /**
     * Toggle ignored state for a URL.
     */
    public function setIgnored(int $id, bool $ignored): bool
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return (bool) $wpdb->update(
            $this->table,
            [
                'is_ignored' => $ignored ? 1 : 0,
                'status'     => $ignored ? 'ignored' : 'pending',
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ],
            ['id' => $id],
            ['%d', '%s', '%s'],
            ['%d']
        );
    }
}
