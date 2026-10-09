<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Api;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Repositories\UrlRepository;
use WPCalibrate\LinkRepair\Repositories\OccurrenceRepository;
use WPCalibrate\LinkRepair\Repositories\ScanRepository;
use WPCalibrate\LinkRepair\Scanner\ScanEngine;
use WPCalibrate\LinkRepair\Repair\RepairEngine;
use WPCalibrate\LinkRepair\Services\CapabilityService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API routes for Link Repair dashboard, scans, and repair actions.
 */
final class RestServer
{
    public const NAMESPACE = 'wpcalibrate-link-repair/v1';

    private UrlRepository $urlRepo;
    private OccurrenceRepository $occRepo;
    private ScanRepository $scanRepo;
    private ScanEngine $scanEngine;
    private RepairEngine $repairEngine;

    public function __construct(
        ?UrlRepository $urlRepo = null,
        ?OccurrenceRepository $occRepo = null,
        ?ScanRepository $scanRepo = null,
        ?ScanEngine $scanEngine = null,
        ?RepairEngine $repairEngine = null
    ) {
        $this->urlRepo      = $urlRepo ?? new UrlRepository();
        $this->occRepo      = $occRepo ?? new OccurrenceRepository();
        $this->scanRepo     = $scanRepo ?? new ScanRepository();
        $this->scanEngine   = $scanEngine ?? new ScanEngine($this->scanRepo, $this->urlRepo);
        $this->repairEngine = $repairEngine ?? new RepairEngine($this->urlRepo, $this->occRepo, $this->scanEngine);

        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Check current user authorization for Link Repair REST endpoints.
     */
    public function checkPermission(WP_REST_Request $request): bool|WP_Error
    {
        if (!CapabilityService::currentUserCan()) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access Link Repair endpoints.', 'wpcalibrate-link-repair'),
                ['status' => 403]
            );
        }
        return true;
    }

    public function registerRoutes(): void
    {
        // 1. Scan Status & Dashboard Counts
        register_rest_route(self::NAMESPACE, '/scans/status', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getScanStatus'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // 2. Start Scan
        register_rest_route(self::NAMESPACE, '/scans/start', [
            'methods'             => 'POST',
            'callback'            => [$this, 'startScan'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // 3. Pause Scan
        register_rest_route(self::NAMESPACE, '/scans/pause', [
            'methods'             => 'POST',
            'callback'            => [$this, 'pauseScan'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // 4. Resume Scan
        register_rest_route(self::NAMESPACE, '/scans/resume', [
            'methods'             => 'POST',
            'callback'            => [$this, 'resumeScan'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // 5. Cancel Scan
        register_rest_route(self::NAMESPACE, '/scans/cancel', [
            'methods'             => 'POST',
            'callback'            => [$this, 'cancelScan'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // 6. Process Batch Step (Client-assisted worker fallback)
        register_rest_route(self::NAMESPACE, '/scans/step', [
            'methods'             => 'POST',
            'callback'            => [$this, 'stepScan'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // 7. Query URLs (server-side pagination, search, filter, sort)
        register_rest_route(self::NAMESPACE, '/urls', [
            'methods'             => 'GET',
            'callback'            => [$this, 'queryUrls'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // 8. Get Occurrences for URL
        register_rest_route(self::NAMESPACE, '/urls/(?P<id>\d+)/occurrences', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getOccurrences'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // 9. Recheck URL
        register_rest_route(self::NAMESPACE, '/urls/(?P<id>\d+)/recheck', [
            'methods'             => 'POST',
            'callback'            => [$this, 'recheckUrl'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // 10. Toggle Ignore
        register_rest_route(self::NAMESPACE, '/urls/(?P<id>\d+)/ignore', [
            'methods'             => 'POST',
            'callback'            => [$this, 'toggleIgnore'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // 11. Whitelist URL
        register_rest_route(self::NAMESPACE, '/urls/(?P<id>\d+)/whitelist', [
            'methods'             => 'POST',
            'callback'            => [$this, 'whitelistUrl'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // 12. Preview Bulk Replacement
        register_rest_route(self::NAMESPACE, '/repair/preview', [
            'methods'             => 'POST',
            'callback'            => [$this, 'previewReplacement'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // 13. Replace URL (single occurrence or bulk)
        register_rest_route(self::NAMESPACE, '/repair/replace', [
            'methods'             => 'POST',
            'callback'            => [$this, 'replaceUrl'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // 14. Unlink Occurrence
        register_rest_route(self::NAMESPACE, '/repair/unlink', [
            'methods'             => 'POST',
            'callback'            => [$this, 'unlinkOccurrence'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        // 15. Replace Redirect with Destination
        register_rest_route(self::NAMESPACE, '/repair/redirect', [
            'methods'             => 'POST',
            'callback'            => [$this, 'replaceRedirect'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function getScanStatus(WP_REST_Request $request): WP_REST_Response
    {
        $activeScan = $this->scanRepo->getActiveScan();
        $latestScan = $this->scanRepo->getLatestScan();
        $counts     = $this->urlRepo->getDashboardCounts();

        return new WP_REST_Response([
            'success'     => true,
            'active_scan' => $activeScan,
            'latest_scan' => $latestScan,
            'counts'      => $counts,
        ]);
    }

    public function startScan(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $scanId = $this->scanEngine->startScan('manual');
            return new WP_REST_Response([
                'success' => true,
                'scan_id' => $scanId,
                'message' => __('Scan started successfully.', 'wpcalibrate-link-repair'),
            ]);
        } catch (\Throwable $e) {
            return new WP_Error('scan_start_error', $e->getMessage(), ['status' => 400]);
        }
    }

    public function pauseScan(WP_REST_Request $request): WP_REST_Response
    {
        $activeScan = $this->scanRepo->getActiveScan();
        if (!$activeScan) {
            return new WP_REST_Response(['success' => false, 'message' => __('No active scan.', 'wpcalibrate-link-repair')], 404);
        }

        $paused = $this->scanEngine->pauseScan((int) $activeScan->id);
        return new WP_REST_Response(['success' => $paused]);
    }

    public function resumeScan(WP_REST_Request $request): WP_REST_Response
    {
        $activeScan = $this->scanRepo->getActiveScan();
        if (!$activeScan || $activeScan->status !== 'paused') {
            return new WP_REST_Response(['success' => false, 'message' => __('No paused scan to resume.', 'wpcalibrate-link-repair')], 404);
        }

        $resumed = $this->scanEngine->resumeScan((int) $activeScan->id);
        return new WP_REST_Response(['success' => $resumed]);
    }

    public function cancelScan(WP_REST_Request $request): WP_REST_Response
    {
        $activeScan = $this->scanRepo->getActiveScan();
        if (!$activeScan) {
            return new WP_REST_Response(['success' => false, 'message' => __('No active scan to cancel.', 'wpcalibrate-link-repair')], 404);
        }

        $cancelled = $this->scanEngine->cancelScan((int) $activeScan->id);
        return new WP_REST_Response(['success' => $cancelled]);
    }

    public function stepScan(WP_REST_Request $request): WP_REST_Response
    {
        $activeScan = $this->scanRepo->getActiveScan();
        if (!$activeScan || $activeScan->status !== 'running') {
            return new WP_REST_Response(['success' => false, 'finished' => true]);
        }

        $stepResult = $this->scanEngine->processBatch((int) $activeScan->id);
        $updatedScan = $this->scanRepo->getScan((int) $activeScan->id);
        $counts = $this->urlRepo->getDashboardCounts();

        return new WP_REST_Response([
            'success'     => true,
            'step_result' => $stepResult,
            'scan'        => $updatedScan,
            'counts'      => $counts,
        ]);
    }

    public function queryUrls(WP_REST_Request $request): WP_REST_Response
    {
        $page    = max(1, (int) $request->get_param('page'));
        $perPage = max(5, min(100, (int) ($request->get_param('per_page') ?? 20)));
        $offset  = ($page - 1) * $perPage;

        $filters = [
            'status'    => sanitize_text_field((string) ($request->get_param('status') ?? 'all')),
            'search'    => sanitize_text_field((string) ($request->get_param('search') ?? '')),
            'http_code' => $request->get_param('http_code') ? (int) $request->get_param('http_code') : null,
        ];

        $orderBy  = sanitize_key((string) ($request->get_param('order_by') ?? 'last_checked_at'));
        $orderDir = strtoupper((string) ($request->get_param('order_dir') ?? 'DESC'));

        $items = $this->urlRepo->queryUrls($filters, $perPage, $offset, $orderBy, $orderDir);
        $total = $this->urlRepo->countUrls($filters);

        return new WP_REST_Response([
            'success'    => true,
            'items'      => $items,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'total_pages'=> (int) ceil($total / $perPage),
        ]);
    }

    public function getOccurrences(WP_REST_Request $request): WP_REST_Response
    {
        $urlId = (int) $request->get_param('id');
        $occurrences = $this->occRepo->getByUrlId($urlId);

        return new WP_REST_Response([
            'success'     => true,
            'url_id'      => $urlId,
            'occurrences' => $occurrences,
        ]);
    }

    public function recheckUrl(WP_REST_Request $request): WP_REST_Response
    {
        $urlId = (int) $request->get_param('id');
        $res = $this->scanEngine->recheckUrl($urlId);

        return new WP_REST_Response($res);
    }

    public function toggleIgnore(WP_REST_Request $request): WP_REST_Response
    {
        $urlId = (int) $request->get_param('id');
        $ignored = (bool) $request->get_param('ignored');
        $ok = $this->urlRepo->setIgnored($urlId, $ignored);

        return new WP_REST_Response(['success' => $ok]);
    }

    public function whitelistUrl(WP_REST_Request $request): WP_REST_Response
    {
        $urlId = (int) $request->get_param('id');
        $ok = $this->repairEngine->whitelistUrl($urlId);

        return new WP_REST_Response(['success' => $ok]);
    }

    public function previewReplacement(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $urlId  = (int) $request->get_param('url_id');
        $newUrl = (string) $request->get_param('new_url');

        try {
            $preview = $this->repairEngine->previewBulkReplacement($urlId, $newUrl);
            return new WP_REST_Response(['success' => true, 'preview' => $preview]);
        } catch (\Throwable $e) {
            return new WP_Error('preview_error', $e->getMessage(), ['status' => 400]);
        }
    }

    public function replaceUrl(WP_REST_Request $request): WP_REST_Response
    {
        $scope = (string) ($request->get_param('scope') ?? 'bulk');
        $newUrl = (string) $request->get_param('new_url');

        if ($scope === 'single') {
            $occurrenceId = (int) $request->get_param('occurrence_id');
            $res = $this->repairEngine->replaceOccurrence($occurrenceId, $newUrl);
        } else {
            $urlId = (int) $request->get_param('url_id');
            $res = $this->repairEngine->replaceUrlGlobally($urlId, $newUrl);
        }

        return new WP_REST_Response($res);
    }

    public function unlinkOccurrence(WP_REST_Request $request): WP_REST_Response
    {
        $occurrenceId = (int) $request->get_param('occurrence_id');
        $res = $this->repairEngine->unlinkOccurrence($occurrenceId);

        return new WP_REST_Response($res);
    }

    public function replaceRedirect(WP_REST_Request $request): WP_REST_Response
    {
        $urlId = (int) $request->get_param('url_id');
        $res = $this->repairEngine->replaceRedirect($urlId);

        return new WP_REST_Response($res);
    }
}
