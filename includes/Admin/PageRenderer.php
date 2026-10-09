<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Repositories\UrlRepository;
use WPCalibrate\LinkRepair\Repositories\ScanRepository;
use WPCalibrate\LinkRepair\License\LicenseManager;
use WPCalibrate\LinkRepair\Services\SettingsService;
use WPCalibrate\LinkRepair\Services\CapabilityService;

/**
 * Handles rendering of Link Repair admin views and tabs.
 */
final class PageRenderer
{
    private UrlRepository $urlRepo;
    private ScanRepository $scanRepo;

    public function __construct(
        ?UrlRepository $urlRepo = null,
        ?ScanRepository $scanRepo = null
    ) {
        $this->urlRepo  = $urlRepo ?? new UrlRepository();
        $this->scanRepo = $scanRepo ?? new ScanRepository();
    }

    /**
     * Render the admin page shell and active tab.
     */
    public function render(): void
    {
        CapabilityService::check();

        // Handle settings save if POST
        if (isset($_POST['wpcalibrate_lr_save_settings'])) {
            check_admin_referer('wpcalibrate_lr_save_settings_nonce');
            $settingsData = $_POST['wpcalibrate_lr_settings'] ?? []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            if (is_array($settingsData)) {
                SettingsService::update($settingsData);
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'wpcalibrate-link-repair') . '</p></div>';
            }
        }

        // Handle license key save if POST
        if (isset($_POST['wpcalibrate_lr_save_license'])) {
            check_admin_referer('wpcalibrate_lr_save_license_nonce');
            $key = sanitize_text_field((string) ($_POST['wpcalibrate_lr_license_key'] ?? ''));
            LicenseManager::saveKey($key);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('License key updated.', 'wpcalibrate-link-repair') . '</p></div>';
        }

        $activeTab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $allowedTabs = ['dashboard', 'overview', 'broken-links', 'redirects', 'scan-history', 'settings', 'license', 'support'];
        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'dashboard';
        }

        $counts     = $this->urlRepo->getDashboardCounts();
        $activeScan = $this->scanRepo->getActiveScan();
        $latestScan = $this->scanRepo->getLatestScan();

        $logoPath = WPCALIBRATE_LINK_REPAIR_DIR . 'branding/icon-dark.png';
        $logoUrl  = file_exists($logoPath) ? WPCALIBRATE_LINK_REPAIR_URL . 'branding/icon-dark.png' : '';

        ?>
        <div class="wrap wpcalibrate-lr-wrap">
            <header class="wpcalibrate-lr-header">
                <div class="wpcalibrate-lr-brand">
                    <?php if ($logoUrl !== ''): ?>
                        <img src="<?php echo esc_url($logoUrl); ?>" alt="WPCalibrate" class="wpcalibrate-lr-logo" />
                    <?php endif; ?>
                    <div class="wpcalibrate-lr-title-wrap">
                        <h1 class="wpcalibrate-lr-title"><?php esc_html_e('WPCalibrate Link Repair', 'wpcalibrate-link-repair'); ?></h1>
                        <span class="wpcalibrate-lr-version">v<?php echo esc_html(WPCALIBRATE_LINK_REPAIR_VERSION); ?></span>
                    </div>
                </div>

                <div class="wpcalibrate-lr-header-actions">
                    <button type="button" class="button button-primary wpcalibrate-lr-scan-btn" id="wpcalibrate-lr-scan-now-btn">
                        <span class="dashicons dashicons-search"></span> <?php esc_html_e('Scan Now', 'wpcalibrate-link-repair'); ?>
                    </button>
                </div>
            </header>

            <nav class="nav-tab-wrapper wpcalibrate-lr-tabs" aria-label="<?php esc_attr_e('Plugin Tabs', 'wpcalibrate-link-repair'); ?>">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=dashboard')); ?>" 
                   class="nav-tab <?php echo $activeTab === 'dashboard' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-dashboard"></span> <?php esc_html_e('Dashboard', 'wpcalibrate-link-repair'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=overview')); ?>" 
                   class="nav-tab <?php echo $activeTab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-info"></span> <?php esc_html_e('Overview', 'wpcalibrate-link-repair'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=broken-links')); ?>" 
                   class="nav-tab <?php echo $activeTab === 'broken-links' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-warning"></span> <?php esc_html_e('Broken Links', 'wpcalibrate-link-repair'); ?>
                    <?php if (!empty($counts['broken'])): ?>
                        <span class="count-badge count-broken"><?php echo (int) $counts['broken']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=redirects')); ?>" 
                   class="nav-tab <?php echo $activeTab === 'redirects' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-redo"></span> <?php esc_html_e('Redirects', 'wpcalibrate-link-repair'); ?>
                    <?php if (!empty($counts['redirect'])): ?>
                        <span class="count-badge count-redirect"><?php echo (int) $counts['redirect']; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=scan-history')); ?>" 
                   class="nav-tab <?php echo $activeTab === 'scan-history' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-backup"></span> <?php esc_html_e('Scan History', 'wpcalibrate-link-repair'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=settings')); ?>" 
                   class="nav-tab <?php echo $activeTab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e('Settings', 'wpcalibrate-link-repair'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=license')); ?>" 
                   class="nav-tab <?php echo $activeTab === 'license' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-admin-network"></span> <?php esc_html_e('License', 'wpcalibrate-link-repair'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=support')); ?>" 
                   class="nav-tab <?php echo $activeTab === 'support' ? 'nav-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-sos"></span> <?php esc_html_e('Support', 'wpcalibrate-link-repair'); ?>
                </a>
            </nav>

            <main class="wpcalibrate-lr-content">
                <?php
                switch ($activeTab) {
                    case 'overview':
                        $this->renderOverviewTab();
                        break;
                    case 'broken-links':
                        $this->renderBrokenLinksTab();
                        break;
                    case 'redirects':
                        $this->renderRedirectsTab();
                        break;
                    case 'scan-history':
                        $this->renderScanHistoryTab();
                        break;
                    case 'settings':
                        $this->renderSettingsTab();
                        break;
                    case 'license':
                        $this->renderLicenseTab();
                        break;
                    case 'support':
                        $this->renderSupportTab();
                        break;
                    case 'dashboard':
                    default:
                        $this->renderDashboardTab($counts, $activeScan, $latestScan);
                        break;
                }
                ?>
            </main>
        </div>
        <?php
    }

    private function renderOverviewTab(): void
    {
        $template = WPCALIBRATE_LINK_REPAIR_DIR . 'templates/admin/overview.php';
        if (file_exists($template)) {
            include $template;
        }
    }

    private function renderDashboardTab(array $counts, ?object $activeScan, ?object $latestScan): void
    {
        $template = WPCALIBRATE_LINK_REPAIR_DIR . 'templates/admin/dashboard.php';
        if (file_exists($template)) {
            include $template;
        }
    }

    private function renderBrokenLinksTab(): void
    {
        $template = WPCALIBRATE_LINK_REPAIR_DIR . 'templates/admin/broken-links.php';
        if (file_exists($template)) {
            include $template;
        }
    }

    private function renderRedirectsTab(): void
    {
        $template = WPCALIBRATE_LINK_REPAIR_DIR . 'templates/admin/redirects.php';
        if (file_exists($template)) {
            include $template;
        }
    }

    private function renderScanHistoryTab(): void
    {
        $template = WPCALIBRATE_LINK_REPAIR_DIR . 'templates/admin/scan-history.php';
        if (file_exists($template)) {
            include $template;
        }
    }

    private function renderSettingsTab(): void
    {
        $template = WPCALIBRATE_LINK_REPAIR_DIR . 'templates/admin/settings.php';
        if (file_exists($template)) {
            include $template;
        }
    }

    private function renderLicenseTab(): void
    {
        $template = WPCALIBRATE_LINK_REPAIR_DIR . 'templates/admin/license.php';
        if (file_exists($template)) {
            include $template;
        }
    }

    private function renderSupportTab(): void
    {
        $template = WPCALIBRATE_LINK_REPAIR_DIR . 'templates/admin/support.php';
        if (file_exists($template)) {
            include $template;
        }
    }
}
