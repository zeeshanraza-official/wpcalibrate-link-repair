<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Scanner;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Discovery\ContentDiscoveryEngine;
use WPCalibrate\LinkRepair\Http\HttpClient;
use WPCalibrate\LinkRepair\Jobs\SchedulerFactory;
use WPCalibrate\LinkRepair\Notifications\EmailNotifier;
use WPCalibrate\LinkRepair\Repositories\ScanRepository;
use WPCalibrate\LinkRepair\Repositories\UrlRepository;
use WPCalibrate\LinkRepair\Services\Logger;
use WPCalibrate\LinkRepair\Services\SettingsService;

/**
 * Core scanning orchestrator managing discovery, validation, locking, and progress.
 */
final class ScanEngine
{
    public const BATCH_HOOK = 'wpcalibrate_lr_run_batch';
    public const SCHEDULED_HOOK = 'wpcalibrate_lr_scheduled_scan';
    private const LOCK_TRANSIENT = 'wpcalibrate_lr_scan_lock';
    private const LOCK_TTL = 300; // 5 minutes

    private ScanRepository $scanRepo;
    private UrlRepository $urlRepo;
    private ContentDiscoveryEngine $discovery;
    private HttpClient $http;

    public function __construct(
        ?ScanRepository $scanRepo = null,
        ?UrlRepository $urlRepo = null,
        ?ContentDiscoveryEngine $discovery = null,
        ?HttpClient $http = null
    ) {
        $this->scanRepo  = $scanRepo ?? new ScanRepository();
        $this->urlRepo   = $urlRepo ?? new UrlRepository();
        $this->discovery = $discovery ?? new ContentDiscoveryEngine($this->urlRepo);
        $this->http      = $http ?? new HttpClient();
    }

    /**
     * Start a new scan run.
     *
     * @param string $triggerType 'manual' or 'scheduled'
     * @return int Scan ID
     * @throws \RuntimeException If a scan is already running.
     */
    public function startScan(string $triggerType = 'manual'): int
    {
        $activeScan = $this->scanRepo->getActiveScan();
        if ($activeScan !== null && $activeScan->status === 'running') {
            // Verify if lock is still alive
            if ($this->isLockActive()) {
                // If the active scan is runaway or stuck, gracefully close it
                if ((int) $activeScan->total_checked > 100) {
                    $this->releaseLock();
                    $this->completeScan((int) $activeScan->id);
                } else {
                    throw new \RuntimeException(__('A scan is already actively running.', 'wpcalibrate-link-repair'));
                }
            } else {
                // Recover orphaned scan
                $this->releaseLock();
                $this->scanRepo->updateScan((int) $activeScan->id, [
                    'status'        => 'failed',
                    'error_summary' => __('Previous scan timed out or crashed. Recovering.', 'wpcalibrate-link-repair'),
                ]);
            }
        }

        $scanId = $this->scanRepo->createScan($triggerType);
        $this->acquireLock($scanId);

        $this->scanRepo->updateScan($scanId, [
            'status'       => 'running',
            'stage'        => 'discovery',
            'current_step' => __('Starting content discovery...', 'wpcalibrate-link-repair'),
        ]);

        Logger::info("Scan #{$scanId} started ({$triggerType}).");

        // Dispatch initial batch
        $scheduler = SchedulerFactory::getScheduler();
        $scheduler->dispatchBatch(self::BATCH_HOOK, ['scan_id' => $scanId]);

        return $scanId;
    }

    /**
     * Pause an in-progress scan.
     */
    public function pauseScan(int $scanId): bool
    {
        $scan = $this->scanRepo->getScan($scanId);
        if (!$scan || $scan->status !== 'running') {
            return false;
        }

        $this->scanRepo->updateScan($scanId, [
            'status'       => 'paused',
            'current_step' => __('Scan paused by administrator.', 'wpcalibrate-link-repair'),
        ]);

        $this->releaseLock();
        Logger::info("Scan #{$scanId} paused.");
        return true;
    }

    /**
     * Resume a paused scan.
     */
    public function resumeScan(int $scanId): bool
    {
        $scan = $this->scanRepo->getScan($scanId);
        if (!$scan || $scan->status !== 'paused') {
            return false;
        }

        $this->acquireLock($scanId);
        $this->scanRepo->updateScan($scanId, [
            'status'       => 'running',
            'current_step' => __('Resuming scan...', 'wpcalibrate-link-repair'),
        ]);

        Logger::info("Scan #{$scanId} resumed.");
        $scheduler = SchedulerFactory::getScheduler();
        $scheduler->dispatchBatch(self::BATCH_HOOK, ['scan_id' => $scanId]);

        return true;
    }

    /**
     * Cancel a running or paused scan.
     */
    public function cancelScan(int $scanId): bool
    {
        $scan = $this->scanRepo->getScan($scanId);
        if (!$scan) {
            return false;
        }

        $this->scanRepo->updateScan($scanId, [
            'status'       => 'cancelled',
            'completed_at' => gmdate('Y-m-d H:i:s'),
            'current_step' => __('Scan cancelled by administrator.', 'wpcalibrate-link-repair'),
        ]);

        $this->releaseLock();
        Logger::info("Scan #{$scanId} cancelled.");
        return true;
    }

    /**
     * Execute one batch cycle of scanning (discovery or validation).
     *
     * @param int $scanId
     * @return array<string, mixed> Progress details
     */
    public function processBatch(int $scanId): array
    {
        $scan = $this->scanRepo->getScan($scanId);
        if (!$scan || $scan->status !== 'running') {
            return ['status' => $scan->status ?? 'invalid', 'finished' => true];
        }

        // Refresh lock lease
        $this->acquireLock($scanId);

        $batchSize = (int) SettingsService::get('batch_size', 25);
        $stage = $scan->stage;

        if ($stage === 'discovery') {
            $cursor = json_decode((string) $scan->cursor_data, true);
            if (!is_array($cursor)) {
                $cursor = ['provider_index' => 0, 'offset' => 0];
            }

            $discoveryResult = $this->discovery->processBatch($cursor, $batchSize);
            $newTotal = (int) $scan->total_discovered + $discoveryResult['urls_discovered'];

            if ($discoveryResult['completed']) {
                // Discovery finished -> transition to validation stage with true unique count
                $totalUnique = $this->urlRepo->countNonIgnored();
                $this->scanRepo->updateScan($scanId, [
                    'stage'            => 'validation',
                    'current_step'     => __('Validating URLs...', 'wpcalibrate-link-repair'),
                    'total_discovered' => $totalUnique,
                    'cursor_data'      => wp_json_encode(['last_id' => 0]),
                ]);
            } else {
                $this->scanRepo->updateScan($scanId, [
                    'total_discovered' => $newTotal,
                    'cursor_data'      => wp_json_encode($discoveryResult['next_cursor']),
                    'current_step'     => sprintf(
                        /* translators: 1: items processed, 2: total discovered */
                        __('Discovering URLs: processed %1$d items (%2$d URLs found)', 'wpcalibrate-link-repair'),
                        $discoveryResult['items_processed'],
                        $newTotal
                    ),
                ]);
            }

            // Schedule next batch
            SchedulerFactory::getScheduler()->dispatchBatch(self::BATCH_HOOK, ['scan_id' => $scanId]);

            return [
                'stage'            => 'discovery',
                'status'           => 'running',
                'total_discovered' => $newTotal,
                'finished'         => false,
            ];
        }

        // Stage: Validation
        if ($stage === 'validation') {
            $cursor = json_decode((string) $scan->cursor_data, true);
            $lastId = is_array($cursor) ? (int) ($cursor['last_id'] ?? 0) : 0;

            $totalUrls = $this->urlRepo->countNonIgnored();

            // Defensive recovery for existing stuck runaway scan from previous bug
            if ((int) $scan->total_checked > max(50, $totalUrls * 2)) {
                $this->completeScan($scanId);
                return [
                    'stage'    => 'validation',
                    'status'   => 'completed',
                    'finished' => true,
                ];
            }

            // If no URLs exist, complete immediately
            if ($totalUrls === 0) {
                $this->completeScan($scanId);
                return [
                    'stage'    => 'validation',
                    'status'   => 'completed',
                    'finished' => true,
                ];
            }

            // Monotonic cursor fetch by primary key ID strictly greater than $lastId
            $pendingUrls = $this->urlRepo->getUrlsAfterId($lastId, $batchSize);

            if (empty($pendingUrls)) {
                // Validation complete!
                $this->completeScan($scanId);
                return [
                    'stage'    => 'validation',
                    'status'   => 'completed',
                    'finished' => true,
                ];
            }

            $counts = [
                'working'   => (int) $scan->count_working,
                'broken'    => (int) $scan->count_broken,
                'redirect'  => (int) $scan->count_redirects,
                'warning'   => (int) $scan->count_warnings,
                'checked'   => (int) $scan->total_checked,
            ];

            $maxId = $lastId;
            foreach ($pendingUrls as $urlRow) {
                // Verify scan wasn't cancelled in between
                $refreshedScan = $this->scanRepo->getScan($scanId);
                if (!$refreshedScan || $refreshedScan->status !== 'running') {
                    $this->releaseLock();
                    return ['status' => $refreshedScan->status ?? 'cancelled', 'finished' => true];
                }

                $check = $this->http->checkUrl($urlRow->url);
                $this->urlRepo->updateCheckResult((int) $urlRow->id, $check);

                $counts['checked']++;
                $maxId = max($maxId, (int) $urlRow->id);
                $status = $check['status'];

                if ($status === StatusClassifier::STATUS_WORKING) {
                    $counts['working']++;
                } elseif ($status === StatusClassifier::STATUS_BROKEN) {
                    $counts['broken']++;
                } elseif ($status === StatusClassifier::STATUS_REDIRECT) {
                    $counts['redirect']++;
                } else {
                    $counts['warning']++;
                }
            }

            $isFinished = (count($pendingUrls) < $batchSize);

            if ($isFinished) {
                $this->scanRepo->updateScan($scanId, [
                    'total_checked'   => $counts['checked'],
                    'count_working'   => $counts['working'],
                    'count_broken'    => $counts['broken'],
                    'count_redirects' => $counts['redirect'],
                    'count_warnings'  => $counts['warning'],
                    'cursor_data'     => wp_json_encode(['last_id' => $maxId]),
                ]);

                $this->completeScan($scanId);
                return [
                    'stage'         => 'validation',
                    'status'        => 'completed',
                    'total_checked' => $counts['checked'],
                    'counts'        => $counts,
                    'finished'      => true,
                ];
            }

            $this->scanRepo->updateScan($scanId, [
                'total_checked'   => $counts['checked'],
                'count_working'   => $counts['working'],
                'count_broken'    => $counts['broken'],
                'count_redirects' => $counts['redirect'],
                'count_warnings'  => $counts['warning'],
                'cursor_data'     => wp_json_encode(['last_id' => $maxId]),
                'current_step'    => sprintf(
                    /* translators: 1: checked count, 2: broken count */
                    __('Validated %1$d URLs (%2$d broken found)', 'wpcalibrate-link-repair'),
                    $counts['checked'],
                    $counts['broken']
                ),
            ]);

            // Dispatch next batch
            SchedulerFactory::getScheduler()->dispatchBatch(self::BATCH_HOOK, ['scan_id' => $scanId]);

            return [
                'stage'         => 'validation',
                'status'        => 'running',
                'total_checked' => $counts['checked'],
                'counts'        => $counts,
                'finished'      => false,
            ];
        }

        return ['status' => 'unknown', 'finished' => true];
    }

    /**
     * Mark a scan completed and trigger notification.
     */
    private function completeScan(int $scanId): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $scan = $this->scanRepo->getScan($scanId);
        $counts = $this->urlRepo->getDashboardCounts();

        $actualTotal = ($counts['total'] ?? 0) > 0 ? (int) $counts['total'] : (int) ($scan->total_discovered ?? 0);
        $totalChecked = (int) ($scan->total_checked ?? 0);

        if ($totalChecked > $actualTotal || $totalChecked === 0) {
            $totalChecked = ($counts['checked'] ?? 0) > 0 ? (int) $counts['checked'] : $actualTotal;
        }

        $brokenFound = $counts['broken'] ?? (int) ($scan->count_broken ?? 0);

        $this->scanRepo->updateScan($scanId, [
            'status'          => 'completed',
            'completed_at'    => $now,
            'current_step'    => sprintf(
                /* translators: 1: total verified, 2: broken count */
                __('Scan completed: %1$d URLs verified (%2$d broken found).', 'wpcalibrate-link-repair'),
                $totalChecked,
                $brokenFound
            ),
            'total_checked'   => $totalChecked,
            'count_working'   => $counts['working'] ?? (int) ($scan->count_working ?? 0),
            'count_broken'    => $brokenFound,
            'count_redirects' => $counts['redirect'] ?? (int) ($scan->count_redirects ?? 0),
            'count_warnings'  => $counts['warning'] ?? (int) ($scan->count_warnings ?? 0),
        ]);

        $this->releaseLock();
        Logger::info("Scan #{$scanId} completed.");

        // Send email report if configured
        $scan = $this->scanRepo->getScan($scanId);
        if ($scan) {
            EmailNotifier::sendReport($scan);
        }
    }

    /**
     * Recheck a single specific URL by ID.
     *
     * @param int $urlId
     * @return array<string, mixed>
     */
    public function recheckUrl(int $urlId): array
    {
        $urlRow = $this->urlRepo->findById($urlId);
        if (!$urlRow) {
            return ['success' => false, 'message' => __('URL not found.', 'wpcalibrate-link-repair')];
        }

        $check = $this->http->checkUrl($urlRow->url);
        $this->urlRepo->updateCheckResult($urlId, $check);

        return [
            'success'   => true,
            'url_id'    => $urlId,
            'status'    => $check['status'],
            'http_code' => $check['http_code'],
            'label'     => StatusClassifier::getLabel($check['status']),
        ];
    }

    // Locking methods
    private function isLockActive(): bool
    {
        return (bool) get_transient(self::LOCK_TRANSIENT);
    }

    private function acquireLock(int $scanId): void
    {
        set_transient(self::LOCK_TRANSIENT, $scanId, self::LOCK_TTL);
    }

    private function releaseLock(): void
    {
        delete_transient(self::LOCK_TRANSIENT);
    }
}
