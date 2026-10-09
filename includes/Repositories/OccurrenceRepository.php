<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Database\Schema;

/**
 * Repository for managing URL occurrences in custom table.
 */
final class OccurrenceRepository
{
    private string $table;

    public function __construct()
    {
        $tables = Schema::getTableNames();
        $this->table = $tables['occurrences'];
    }

    /**
     * Save an occurrence linking a URL to a content item.
     *
     * @param int $urlId
     * @param array<string, mixed> $data
     * @return int Occurrence ID
     */
    public function saveOccurrence(int $urlId, array $data): int
    {
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');

        $sourceType  = (string) ($data['source_type'] ?? 'post');
        $sourceId    = (int) ($data['source_id'] ?? 0);
        $fieldKey    = (string) ($data['field_key'] ?? 'post_content');
        $contextPath = (string) ($data['context_path'] ?? '');
        $anchorText  = isset($data['anchor_text']) ? (string) $data['anchor_text'] : null;
        $tagAttrs    = !empty($data['tag_attributes']) ? wp_json_encode($data['tag_attributes']) : null;
        $rawFragment = isset($data['raw_fragment']) ? (string) $data['raw_fragment'] : null;

        // Check if exact occurrence already exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $existingId = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT id FROM {$this->table} 
                 WHERE url_id = %d AND source_type = %s AND source_id = %d AND field_key = %s AND context_path = %s",
                $urlId,
                $sourceType,
                $sourceId,
                $fieldKey,
                $contextPath
            )
        );

        if ($existingId !== null) {
            $id = (int) $existingId;
            // Update timestamp
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->update(
                $this->table,
                ['last_scanned_at' => $now, 'anchor_text' => $anchorText],
                ['id' => $id],
                ['%s', '%s'],
                ['%d']
            );
            return $id;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $this->table,
            [
                'url_id'          => $urlId,
                'source_type'     => $sourceType,
                'source_id'       => $sourceId,
                'field_key'       => $fieldKey,
                'context_path'    => $contextPath,
                'anchor_text'     => $anchorText,
                'tag_attributes'  => $tagAttrs,
                'raw_fragment'    => $rawFragment,
                'last_scanned_at' => $now,
                'created_at'      => $now,
            ],
            ['%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Get all occurrences for a specific URL with rich content metadata.
     *
     * @param int $urlId
     * @return array<int, array<string, mixed>>
     */
    public function getByUrlId(int $urlId): array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT * FROM {$this->table} WHERE url_id = %d ORDER BY id ASC",
                $urlId
            )
        );

        if (!is_array($rows)) {
            return [];
        }

        $results = [];
        foreach ($rows as $row) {
            $item = (array) $row;
            $item['title']     = __('Unknown Item', 'wpcalibrate-link-repair');
            $item['edit_url']  = '';
            $item['view_url']  = '';
            $item['can_edit']  = false;

            $sourceId = (int) $row->source_id;
            $sourceType = $row->source_type;

            if (in_array($sourceType, ['post', 'page', 'attachment', 'product'], true) || post_type_exists($sourceType)) {
                $post = get_post($sourceId);
                if ($post instanceof \WP_Post) {
                    $item['title']    = $post->post_title !== '' ? $post->post_title : sprintf(__('Post #%d', 'wpcalibrate-link-repair'), $sourceId);
                    $item['edit_url'] = (string) get_edit_post_link($sourceId, '');
                    $item['view_url'] = (string) get_permalink($sourceId);
                    $item['can_edit'] = current_user_can('edit_post', $sourceId);
                }
            } elseif ($sourceType === 'nav_menu_item') {
                $item['title']    = sprintf(__('Navigation Menu Item #%d', 'wpcalibrate-link-repair'), $sourceId);
                $item['edit_url'] = admin_url('nav-menus.php');
                $item['can_edit'] = current_user_can('edit_theme_options');
            } elseif ($sourceType === 'widget') {
                $item['title']    = sprintf(__('Widget Block #%d', 'wpcalibrate-link-repair'), $sourceId);
                $item['edit_url'] = admin_url('widgets.php');
                $item['can_edit'] = current_user_can('edit_theme_options');
            }

            $results[] = $item;
        }

        return $results;
    }

    /**
     * Delete occurrences for a specific content source.
     */
    public function deleteForSource(string $sourceType, int $sourceId): int
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return (int) $wpdb->delete(
            $this->table,
            ['source_type' => $sourceType, 'source_id' => $sourceId],
            ['%s', '%d']
        );
    }

    /**
     * Reassign occurrences from old URL ID to new URL ID.
     */
    public function replaceUrlId(int $oldUrlId, int $newUrlId): int
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        return (int) $wpdb->update(
            $this->table,
            ['url_id' => $newUrlId],
            ['url_id' => $oldUrlId],
            ['%d'],
            ['%d']
        );
    }
}
