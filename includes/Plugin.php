<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Admin\AdminMenu;
use WPCalibrate\LinkRepair\Admin\AssetsManager;
use WPCalibrate\LinkRepair\Api\RestServer;
use WPCalibrate\LinkRepair\Database\Installer;
use WPCalibrate\LinkRepair\Discovery\ContentDiscoveryEngine;
use WPCalibrate\LinkRepair\Integrations\Elementor\ElementorIntegration;
use WPCalibrate\LinkRepair\Integrations\Elementor\ElementorProvider;
use WPCalibrate\LinkRepair\Integrations\WooCommerce\WooCommerceIntegration;
use WPCalibrate\LinkRepair\Integrations\WooCommerce\WooCommerceProductProvider;
use WPCalibrate\LinkRepair\Jobs\SchedulerFactory;
use WPCalibrate\LinkRepair\Repositories\ScanRepository;
use WPCalibrate\LinkRepair\Repositories\UrlRepository;
use WPCalibrate\LinkRepair\Scanner\ScanEngine;
use WPCalibrate\LinkRepair\Services\Logger;
use WPCalibrate\LinkRepair\Services\SettingsService;

/**
 * Main application coordinator and lifecycle orchestrator.
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    private UrlRepository $urlRepo;
    private ScanRepository $scanRepo;
    private ContentDiscoveryEngine $discovery;
    private ScanEngine $scanEngine;
    private ?AdminMenu $adminMenu = null;
    private ?AssetsManager $assetsManager = null;
    private ?RestServer $restServer = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->urlRepo    = new UrlRepository();
        $this->scanRepo   = new ScanRepository();
        $this->discovery  = new ContentDiscoveryEngine($this->urlRepo);
        $this->scanEngine = new ScanEngine($this->scanRepo, $this->urlRepo, $this->discovery);
    }

    /**
     * Bootstrap the plugin services and hooks.
     */
    public function boot(): void
    {
        // 1. Text domain
        load_plugin_textdomain(
            'wpcalibrate-link-repair',
            false,
            dirname(WPCALIBRATE_LINK_REPAIR_BASENAME) . '/languages'
        );

        // 2. HPOS Compatibility declaration for WooCommerce
        add_action('before_woocommerce_init', static function (): void {
            WooCommerceIntegration::declareHposCompatibility();
        });

        // 3. Register optional builders/providers
        if (ElementorIntegration::isElementorActive()) {
            $this->discovery->registerProvider(new ElementorProvider());
        }

        if (WooCommerceIntegration::isWooCommerceActive()) {
            $this->discovery->registerProvider(new WooCommerceProductProvider());
        }

        // 4. Admin Menu & Assets
        if (is_admin()) {
            $this->adminMenu = new AdminMenu();
            $this->assetsManager = new AssetsManager();
        }

        // 5. REST API
        $this->restServer = new RestServer(
            $this->urlRepo,
            null,
            $this->scanRepo,
            $this->scanEngine
        );

        // 6. Background Batch execution hook
        add_action(ScanEngine::BATCH_HOOK, [$this, 'handleBatchAction']);

        // 7. Scheduled recurring scan hook
        add_action(ScanEngine::SCHEDULED_HOOK, [$this, 'handleScheduledScanAction']);

        // 8. Daily maintenance cleanup hook
        add_action('wp_scheduled_delete', [$this, 'handleMaintenanceCleanup']);

        // 9. GitHub Release Updates in WordPress Dashboard
        if (is_admin()) {
            (new \WPCalibrate\LinkRepair\Services\GitHubUpdater(
                'zeeshanraza-official',
                'wpcalibrate-link-repair',
                WPCALIBRATE_LINK_REPAIR_FILE,
                WPCALIBRATE_LINK_REPAIR_VERSION
            ))->init();
        }
    }

    /**
     * Background batch runner callback.
     *
     * @param array<string, mixed>|int|null $args
     */
    public function handleBatchAction(mixed $args = null): void
    {
        $scanId = null;
        if (is_array($args) && isset($args['scan_id'])) {
            $scanId = (int) $args['scan_id'];
        } elseif (is_numeric($args)) {
            $scanId = (int) $args;
        }

        if ($scanId === null) {
            $active = $this->scanRepo->getActiveScan();
            if ($active && $active->status === 'running') {
                $scanId = (int) $active->id;
            }
        }

        if ($scanId !== null) {
            $this->scanEngine->processBatch($scanId);
        }
    }

    /**
     * Scheduled scan trigger callback.
     */
    public function handleScheduledScanAction(): void
    {
        $frequency = (string) SettingsService::get('schedule_frequency', 'weekly');
        if ($frequency === 'disabled') {
            return;
        }

        try {
            Logger::info('Executing scheduled link repair scan.');
            $this->scanEngine->startScan('scheduled');
        } catch (\Throwable $e) {
            Logger::warning('Scheduled scan could not be started: ' . $e->getMessage());
        }
    }

    /**
     * Incremental maintenance cleanup (purges expired scan history).
     */
    public function handleMaintenanceCleanup(): void
    {
        $retentionDays = (int) SettingsService::get('retention_days', 90);
        $deleted = $this->scanRepo->cleanupOldScans($retentionDays);
        if ($deleted > 0) {
            Logger::info("Cleaned up {$deleted} expired scan records.");
        }
    }

    /**
     * Activation routine.
     */
    public static function activate(): void
    {
        Installer::install();

        // Register default recurring schedule if enabled
        $frequency = (string) SettingsService::get('schedule_frequency', 'weekly');
        if ($frequency !== 'disabled') {
            $scheduler = SchedulerFactory::getScheduler();
            $timeStr = (string) SettingsService::get('schedule_time', '03:00');
            [$hour, $minute] = explode(':', $timeStr);
            $start = strtotime("tomorrow {$hour}:{$minute}:00 UTC");
            if ($start !== false) {
                $scheduler->scheduleRecurring(ScanEngine::SCHEDULED_HOOK, $frequency, $start);
            }
        }
    }

    /**
     * Deactivation routine.
     */
    public static function deactivate(): void
    {
        // Unschedule recurring and batch jobs
        $scheduler = SchedulerFactory::getScheduler();
        $scheduler->clearScheduled(ScanEngine::SCHEDULED_HOOK);
        $scheduler->clearScheduled(ScanEngine::BATCH_HOOK);

        // Release locks
        delete_transient('wpcalibrate_lr_scan_lock');
    }

    public function getUrlRepo(): UrlRepository
    {
        return $this->urlRepo;
    }

    public function getScanRepo(): ScanRepository
    {
        return $this->scanRepo;
    }

    public function getScanEngine(): ScanEngine
    {
        return $this->scanEngine;
    }

    public function getDiscovery(): ContentDiscoveryEngine
    {
        return $this->discovery;
    }
}
