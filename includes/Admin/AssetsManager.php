<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Services\CapabilityService;

/**
 * Enqueues CSS and JavaScript assets strictly on Link Repair admin pages.
 */
final class AssetsManager
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(string $hook): void
    {
        // Only load on our plugin's admin page
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($page !== AdminMenu::PAGE_SLUG && $page !== AdminMenu::PARENT_MENU_SLUG) {
            return;
        }

        if (!CapabilityService::currentUserCan()) {
            return;
        }

        $cssUrl = WPCALIBRATE_LINK_REPAIR_URL . 'assets/css/admin.css';
        $jsUrl  = WPCALIBRATE_LINK_REPAIR_URL . 'assets/js/admin.js';

        wp_enqueue_style(
            'wpcalibrate-lr-admin',
            $cssUrl,
            [],
            WPCALIBRATE_LINK_REPAIR_VERSION
        );

        if (is_rtl()) {
            wp_enqueue_style(
                'wpcalibrate-lr-admin-rtl',
                WPCALIBRATE_LINK_REPAIR_URL . 'assets/css/admin-rtl.css',
                ['wpcalibrate-lr-admin'],
                WPCALIBRATE_LINK_REPAIR_VERSION
            );
        }

        wp_enqueue_script(
            'wpcalibrate-lr-admin',
            $jsUrl,
            ['jquery'],
            WPCALIBRATE_LINK_REPAIR_VERSION,
            true
        );

        wp_localize_script('wpcalibrate-lr-admin', 'WPCalibrateLR', [
            'restUrl' => esc_url_raw(rest_url('wpcalibrate-link-repair/v1/')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'adminNonce' => wp_create_nonce('wpcalibrate_lr_admin_action'),
            'strings' => [
                'confirmCancel'    => __('Are you sure you want to cancel the active scan?', 'wpcalibrate-link-repair'),
                'confirmBulk'      => __('Are you sure you want to replace this URL across all confirmed occurrences? This action will modify your content.', 'wpcalibrate-link-repair'),
                'confirmUnlink'    => __('Are you sure you want to unlink this URL? Visible anchor text will be preserved.', 'wpcalibrate-link-repair'),
                'scanStarted'      => __('Scan started successfully.', 'wpcalibrate-link-repair'),
                'scanPaused'       => __('Scan paused.', 'wpcalibrate-link-repair'),
                'scanResumed'      => __('Scan resumed.', 'wpcalibrate-link-repair'),
                'scanCancelled'    => __('Scan cancelled.', 'wpcalibrate-link-repair'),
                'errorOccurred'    => __('An unexpected error occurred. Please try again.', 'wpcalibrate-link-repair'),
                'rechecking'       => __('Rechecking URL health...', 'wpcalibrate-link-repair'),
            ],
        ]);
    }
}
