<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Redirects View Template.
 */
?>
<div class="wpcalibrate-lr-redirects-view">
    <div class="notice notice-info inline">
        <p>
            <?php esc_html_e('Redirects are not broken links, but updating them to their final destination improves site performance and prevents unnecessary HTTP redirect hops. Verify the final destination before applying updates.', 'wpcalibrate-link-repair'); ?>
        </p>
    </div>

    <div class="table-toolbar">
        <div class="toolbar-search">
            <input type="search" id="redirect-search-input" class="regular-text" placeholder="<?php esc_attr_e('Search redirects...', 'wpcalibrate-link-repair'); ?>" />
            <button type="button" class="button" id="btn-search-redirects"><?php esc_html_e('Search', 'wpcalibrate-link-repair'); ?></button>
        </div>
        <div class="toolbar-filters">
            <button type="button" class="button" id="btn-refresh-redirects">
                <span class="dashicons dashicons-image-rotate"></span> <?php esc_html_e('Refresh', 'wpcalibrate-link-repair'); ?>
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="wp-list-table widefat fixed striped" id="redirects-table">
            <thead>
                <tr>
                    <th scope="col" style="width: 35%;"><?php esc_html_e('Original URL', 'wpcalibrate-link-repair'); ?></th>
                    <th scope="col" style="width: 100px;"><?php esc_html_e('HTTP Code', 'wpcalibrate-link-repair'); ?></th>
                    <th scope="col" style="width: 35%;"><?php esc_html_e('Verified Destination', 'wpcalibrate-link-repair'); ?></th>
                    <th scope="col" style="width: 90px;"><?php esc_html_e('Sources', 'wpcalibrate-link-repair'); ?></th>
                    <th scope="col" style="width: 180px;"><?php esc_html_e('Action', 'wpcalibrate-link-repair'); ?></th>
                </tr>
            </thead>
            <tbody id="redirects-table-body">
                <tr>
                    <td colspan="5" class="loading-cell">
                        <span class="spinner is-active" style="float:none;"></span> <?php esc_html_e('Loading redirects...', 'wpcalibrate-link-repair'); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="tablenav bottom">
        <div class="tablenav-pages" id="redirects-pagination">
            <span class="displaying-num" id="redirects-pagination-info">0 items</span>
            <span class="pagination-links" id="redirects-pagination-buttons"></span>
        </div>
    </div>
</div>
