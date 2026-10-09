<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Repair;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Http\UrlNormalizer;
use WPCalibrate\LinkRepair\Repair\Writers\ContentWriterInterface;
use WPCalibrate\LinkRepair\Repair\Writers\PostContentWriter;
use WPCalibrate\LinkRepair\Repair\Writers\NavMenuWriter;
use WPCalibrate\LinkRepair\Repair\Writers\ElementorContentWriter;
use WPCalibrate\LinkRepair\Repositories\UrlRepository;
use WPCalibrate\LinkRepair\Repositories\OccurrenceRepository;
use WPCalibrate\LinkRepair\Scanner\ScanEngine;
use WPCalibrate\LinkRepair\Services\CapabilityService;
use WPCalibrate\LinkRepair\Services\Logger;
use WPCalibrate\LinkRepair\Services\SettingsService;

/**
 * Coordinates safe content repair operations (single edit, bulk replace, unlink, whitelist, ignore).
 */
final class RepairEngine
{
    /**
     * @var array<int, ContentWriterInterface>
     */
    private array $writers = [];
    private UrlRepository $urlRepo;
    private OccurrenceRepository $occRepo;
    private ?ScanEngine $scanEngine;

    public function __construct(
        ?UrlRepository $urlRepo = null,
        ?OccurrenceRepository $occRepo = null,
        ?ScanEngine $scanEngine = null
    ) {
        $this->urlRepo    = $urlRepo ?? new UrlRepository();
        $this->occRepo    = $occRepo ?? new OccurrenceRepository();
        $this->scanEngine = $scanEngine;

        $this->registerDefaultWriters();
    }

    private function registerDefaultWriters(): void
    {
        $this->writers[] = new PostContentWriter();
        $this->writers[] = new NavMenuWriter();
        $this->writers[] = new ElementorContentWriter();
    }

    /**
     * Preview which occurrences will be affected by a bulk replace operation.
     *
     * @param int $urlId
     * @param string $newUrl
     * @return array{
     *     url_id: int,
     *     old_url: string,
     *     new_url: string,
     *     occurrences: array<int, array<string, mixed>>,
     *     total_count: int
     * }
     */
    public function previewBulkReplacement(int $urlId, string $newUrl): array
    {
        CapabilityService::check();

        $urlRow = $this->urlRepo->findById($urlId);
        if (!$urlRow) {
            throw new \InvalidArgumentException(__('URL record not found.', 'wpcalibrate-link-repair'));
        }

        $normalizedNew = UrlNormalizer::normalize($newUrl);
        if ($normalizedNew === null) {
            throw new \InvalidArgumentException(__('The replacement URL is invalid.', 'wpcalibrate-link-repair'));
        }

        $occurrences = $this->occRepo->getByUrlId($urlId);

        return [
            'url_id'      => $urlId,
            'old_url'     => (string) $urlRow->url,
            'new_url'     => $normalizedNew,
            'occurrences' => $occurrences,
            'total_count' => count($occurrences),
        ];
    }

    /**
     * Replace a single occurrence of a URL.
     *
     * @param int $occurrenceId
     * @param string $newUrl
     * @return array{success: bool, message: string}
     */
    public function replaceOccurrence(int $occurrenceId, string $newUrl): array
    {
        CapabilityService::check();

        global $wpdb;
        $tables = \WPCalibrate\LinkRepair\Database\Schema::getTableNames();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $occ = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT o.*, u.url as old_url FROM {$tables['occurrences']} o 
                 JOIN {$tables['urls']} u ON o.url_id = u.id 
                 WHERE o.id = %d",
                $occurrenceId
            )
        );

        if (!$occ) {
            return ['success' => false, 'message' => __('Occurrence record not found.', 'wpcalibrate-link-repair')];
        }

        $normalizedNew = UrlNormalizer::normalize($newUrl);
        if ($normalizedNew === null) {
            return ['success' => false, 'message' => __('Invalid replacement URL.', 'wpcalibrate-link-repair')];
        }

        $writer = $this->findWriter($occ->source_type, $occ->field_key);
        if (!$writer) {
            return [
                'success' => false,
                'message' => sprintf(__('No safe content writer available for source type %s.', 'wpcalibrate-link-repair'), $occ->source_type),
            ];
        }

        $result = $writer->replaceUrl(
            (int) $occ->source_id,
            (string) $occ->field_key,
            (string) $occ->old_url,
            $normalizedNew,
            $occ->context_path
        );

        if ($result['success']) {
            // Reassign or create URL record for the new URL
            $newUrlId = $this->urlRepo->findOrCreate($normalizedNew);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->update($tables['occurrences'], ['url_id' => $newUrlId], ['id' => $occurrenceId], ['%d'], ['%d']);

            // Queue recheck of new URL
            $this->queueRecheck($newUrlId);
        }

        return $result;
    }

    /**
     * Bulk replace all occurrences of an exact URL across supported locations.
     *
     * @param int $urlId
     * @param string $newUrl
     * @return array{success: bool, message: string, updated_count: int, failed_count: int}
     */
    public function replaceUrlGlobally(int $urlId, string $newUrl): array
    {
        CapabilityService::check();

        $urlRow = $this->urlRepo->findById($urlId);
        if (!$urlRow) {
            return ['success' => false, 'message' => __('URL record not found.', 'wpcalibrate-link-repair'), 'updated_count' => 0, 'failed_count' => 0];
        }

        $normalizedNew = UrlNormalizer::normalize($newUrl);
        if ($normalizedNew === null) {
            return ['success' => false, 'message' => __('Invalid replacement URL.', 'wpcalibrate-link-repair'), 'updated_count' => 0, 'failed_count' => 0];
        }

        $occurrences = $this->occRepo->getByUrlId($urlId);
        $updatedCount = 0;
        $failedCount = 0;
        $newUrlId = $this->urlRepo->findOrCreate($normalizedNew);

        foreach ($occurrences as $occ) {
            $writer = $this->findWriter((string) $occ['source_type'], (string) $occ['field_key']);
            if (!$writer) {
                $failedCount++;
                continue;
            }

            $res = $writer->replaceUrl(
                (int) $occ['source_id'],
                (string) $occ['field_key'],
                (string) $urlRow->url,
                $normalizedNew,
                (string) $occ['context_path']
            );

            if ($res['success']) {
                $updatedCount++;
            } else {
                $failedCount++;
            }
        }

        // Reassign occurrences table
        $this->occRepo->replaceUrlId($urlId, $newUrlId);
        $this->queueRecheck($newUrlId);

        return [
            'success'       => $updatedCount > 0,
            'message'       => sprintf(
                /* translators: 1: updated count, 2: failed count */
                __('Bulk replacement complete: %1$d updated, %2$d failed.', 'wpcalibrate-link-repair'),
                $updatedCount,
                $failedCount
            ),
            'updated_count' => $updatedCount,
            'failed_count'  => $failedCount,
        ];
    }

    /**
     * Unlink an occurrence (removes <a> while keeping text).
     *
     * @param int $occurrenceId
     * @return array{success: bool, message: string}
     */
    public function unlinkOccurrence(int $occurrenceId): array
    {
        CapabilityService::check();

        global $wpdb;
        $tables = \WPCalibrate\LinkRepair\Database\Schema::getTableNames();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $occ = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT o.*, u.url as target_url FROM {$tables['occurrences']} o 
                 JOIN {$tables['urls']} u ON o.url_id = u.id 
                 WHERE o.id = %d",
                $occurrenceId
            )
        );

        if (!$occ) {
            return ['success' => false, 'message' => __('Occurrence not found.', 'wpcalibrate-link-repair')];
        }

        $writer = $this->findWriter($occ->source_type, $occ->field_key);
        if (!$writer) {
            return ['success' => false, 'message' => __('No content writer capable of unlinking this format.', 'wpcalibrate-link-repair')];
        }

        $result = $writer->unlinkUrl(
            (int) $occ->source_id,
            (string) $occ->field_key,
            (string) $occ->target_url,
            $occ->context_path
        );

        if ($result['success']) {
            // Remove occurrence record
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->delete($tables['occurrences'], ['id' => $occurrenceId], ['%d']);
        }

        return $result;
    }

    /**
     * Replace redirected URL with its verified final destination.
     *
     * @param int $urlId
     * @return array{success: bool, message: string}
     */
    public function replaceRedirect(int $urlId): array
    {
        CapabilityService::check();

        $urlRow = $this->urlRepo->findById($urlId);
        if (!$urlRow || empty($urlRow->redirect_url)) {
            return ['success' => false, 'message' => __('Redirect destination not found for this URL.', 'wpcalibrate-link-repair')];
        }

        return $this->replaceUrlGlobally($urlId, (string) $urlRow->redirect_url);
    }

    /**
     * Add URL to whitelist exclusions.
     */
    public function whitelistUrl(int $urlId): bool
    {
        CapabilityService::check();

        $urlRow = $this->urlRepo->findById($urlId);
        if (!$urlRow) {
            return false;
        }

        $settings = SettingsService::getAll();
        $excluded = (string) ($settings['excluded_urls'] ?? '');
        $url = (string) $urlRow->url;

        if (!str_contains($excluded, $url)) {
            $settings['excluded_urls'] = trim($excluded . "\n" . $url);
            SettingsService::update($settings);
        }

        // Set as ignored in database
        $this->urlRepo->setIgnored($urlId, true);
        return true;
    }

    private function findWriter(string $sourceType, string $fieldKey): ?ContentWriterInterface
    {
        foreach ($this->writers as $writer) {
            if ($writer->canHandle($sourceType, $fieldKey)) {
                return $writer;
            }
        }
        return null;
    }

    private function queueRecheck(int $urlId): void
    {
        if ($this->scanEngine) {
            $this->scanEngine->recheckUrl($urlId);
        }
    }
}
