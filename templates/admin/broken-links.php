<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Broken Links & URL Management Table Template.
 */
?>
<div class="wpcalibrate-lr-table-view">
    <div class="table-toolbar">
        <div class="toolbar-search">
            <input type="search" id="url-search-input" class="regular-text" placeholder="<?php esc_attr_e('Search URL or anchor text...', 'wpcalibrate-link-repair'); ?>" />
            <button type="button" class="button" id="btn-search-urls"><?php esc_html_e('Search', 'wpcalibrate-link-repair'); ?></button>
        </div>

        <div class="toolbar-filters">
            <label for="url-status-filter" class="screen-reader-text"><?php esc_html_e('Filter Status', 'wpcalibrate-link-repair'); ?></label>
            <select id="url-status-filter">
                <option value="broken" selected><?php esc_html_e('Broken Links (404/410/Errors)', 'wpcalibrate-link-repair'); ?></option>
                <option value="warning"><?php esc_html_e('Warnings & Timeouts', 'wpcalibrate-link-repair'); ?></option>
                <option value="redirect"><?php esc_html_e('Redirects', 'wpcalibrate-link-repair'); ?></option>
                <option value="working"><?php esc_html_e('Working', 'wpcalibrate-link-repair'); ?></option>
                <option value="internal"><?php esc_html_e('Internal Links Only', 'wpcalibrate-link-repair'); ?></option>
                <option value="external"><?php esc_html_e('External Links Only', 'wpcalibrate-link-repair'); ?></option>
                <option value="media"><?php esc_html_e('Images & Media Only', 'wpcalibrate-link-repair'); ?></option>
                <option value="ignored"><?php esc_html_e('Ignored Links', 'wpcalibrate-link-repair'); ?></option>
                <option value="all"><?php esc_html_e('All Links', 'wpcalibrate-link-repair'); ?></option>
            </select>

            <button type="button" class="button" id="btn-refresh-table">
                <span class="dashicons dashicons-image-rotate"></span> <?php esc_html_e('Refresh', 'wpcalibrate-link-repair'); ?>
            </button>
        </div>
    </div>

    <!-- Results Table Container (Populated via JS / REST) -->
    <div class="table-responsive">
        <table class="wp-list-table widefat fixed striped wpcalibrate-lr-data-table" id="urls-table">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-url"><?php esc_html_e('Target URL', 'wpcalibrate-link-repair'); ?></th>
                    <th scope="col" class="manage-column column-status" style="width: 140px;"><?php esc_html_e('Status', 'wpcalibrate-link-repair'); ?></th>
                    <th scope="col" class="manage-column column-http" style="width: 100px;"><?php esc_html_e('HTTP Code', 'wpcalibrate-link-repair'); ?></th>
                    <th scope="col" class="manage-column column-occurrences" style="width: 120px;"><?php esc_html_e('Occurrences', 'wpcalibrate-link-repair'); ?></th>
                    <th scope="col" class="manage-column column-checked" style="width: 150px;"><?php esc_html_e('Last Checked', 'wpcalibrate-link-repair'); ?></th>
                    <th scope="col" class="manage-column column-actions" style="width: 220px;"><?php esc_html_e('Actions', 'wpcalibrate-link-repair'); ?></th>
                </tr>
            </thead>
            <tbody id="urls-table-body">
                <tr>
                    <td colspan="6" class="loading-cell">
                        <span class="spinner is-active" style="float:none;"></span> <?php esc_html_e('Loading link records...', 'wpcalibrate-link-repair'); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Server-Side Pagination Controls -->
    <div class="tablenav bottom">
        <div class="tablenav-pages" id="table-pagination">
            <span class="displaying-num" id="pagination-info">0 items</span>
            <span class="pagination-links" id="pagination-buttons"></span>
        </div>
    </div>

    <!-- Bulk Replace Modal -->
    <div class="wpcalibrate-lr-modal" id="modal-bulk-replace" role="dialog" aria-modal="true" aria-labelledby="modal-replace-title" style="display:none;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modal-replace-title"><?php esc_html_e('Replace URL', 'wpcalibrate-link-repair'); ?></h3>
                    <button type="button" class="modal-close-btn" aria-label="<?php esc_attr_e('Close dialog', 'wpcalibrate-link-repair'); ?>">&times;</button>
                </div>
                <div class="modal-body">
                    <p><strong><?php esc_html_e('Current URL:', 'wpcalibrate-link-repair'); ?></strong> <code id="modal-old-url" class="code-url"></code></p>
                    <label for="modal-new-url-input"><strong><?php esc_html_e('Replacement URL:', 'wpcalibrate-link-repair'); ?></strong></label>
                    <input type="url" id="modal-new-url-input" class="large-text" placeholder="https://example.com/new-path" required />

                    <div class="modal-scope-options">
                        <label>
                            <input type="radio" name="replace_scope" value="bulk" checked />
                            <?php esc_html_e('Replace across all confirmed occurrences on the website', 'wpcalibrate-link-repair'); ?>
                        </label>
                    </div>

                    <div id="modal-preview-loading" style="display:none; margin-top:10px;">
                        <span class="spinner is-active" style="float:none;"></span> <?php esc_html_e('Analyzing affected content locations...', 'wpcalibrate-link-repair'); ?>
                    </div>
                    <div id="modal-preview-results" style="margin-top: 15px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="button" id="modal-cancel-btn"><?php esc_html_e('Cancel', 'wpcalibrate-link-repair'); ?></button>
                    <button type="button" class="button button-primary" id="modal-confirm-replace-btn"><?php esc_html_e('Confirm & Replace', 'wpcalibrate-link-repair'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
