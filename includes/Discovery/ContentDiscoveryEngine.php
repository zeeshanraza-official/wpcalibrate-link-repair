<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Discovery;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Discovery\Providers\ContentProviderInterface;
use WPCalibrate\LinkRepair\Discovery\Providers\PostContentProvider;
use WPCalibrate\LinkRepair\Discovery\Providers\NavMenuProvider;
use WPCalibrate\LinkRepair\Discovery\Providers\WidgetBlockProvider;
use WPCalibrate\LinkRepair\Discovery\Providers\CustomFieldProvider;
use WPCalibrate\LinkRepair\Http\UrlNormalizer;
use WPCalibrate\LinkRepair\Repositories\UrlRepository;
use WPCalibrate\LinkRepair\Repositories\OccurrenceRepository;
use WPCalibrate\LinkRepair\Services\SettingsService;
use WPCalibrate\LinkRepair\Services\Logger;

/**
 * Coordinates content providers to discover URLs across all supported WordPress content sources.
 */
final class ContentDiscoveryEngine
{
    /**
     * @var array<string, ContentProviderInterface>
     */
    private array $providers = [];
    private UrlRepository $urlRepo;
    private OccurrenceRepository $occRepo;

    public function __construct(
        ?UrlRepository $urlRepo = null,
        ?OccurrenceRepository $occRepo = null
    ) {
        $this->urlRepo = $urlRepo ?? new UrlRepository();
        $this->occRepo = $occRepo ?? new OccurrenceRepository();
        $this->registerDefaultProviders();
    }

    /**
     * Register core content providers.
     */
    private function registerDefaultProviders(): void
    {
        $this->registerProvider(new PostContentProvider());
        $this->registerProvider(new NavMenuProvider());
        $this->registerProvider(new WidgetBlockProvider());
        $this->registerProvider(new CustomFieldProvider());
    }

    /**
     * Register a content provider instance.
     */
    public function registerProvider(ContentProviderInterface $provider): void
    {
        $this->providers[$provider->getId()] = $provider;
    }

    /**
     * Retrieve all active and available providers.
     *
     * @return array<int, ContentProviderInterface>
     */
    public function getActiveProviders(): array
    {
        /**
         * Filter registered content providers.
         *
         * @param array<string, ContentProviderInterface> $providers
         */
        $filtered = (array) apply_filters('wpcalibrate_link_repair_content_providers', $this->providers);

        $active = [];
        foreach ($filtered as $provider) {
            if ($provider instanceof ContentProviderInterface && $provider->isAvailable()) {
                $active[] = $provider;
            }
        }
        return $active;
    }

    /**
     * Process one discovery batch using cursor state.
     *
     * @param array{provider_index: int, offset: int} $cursor
     * @param int $batchSize
     * @return array{
     *     completed: bool,
     *     next_cursor: array{provider_index: int, offset: int},
     *     items_processed: int,
     *     urls_discovered: int
     * }
     */
    public function processBatch(array $cursor, int $batchSize = 25): array
    {
        $providers = $this->getActiveProviders();
        $providerIndex = (int) ($cursor['provider_index'] ?? 0);
        $offset = (int) ($cursor['offset'] ?? 0);

        if ($providerIndex >= count($providers)) {
            return [
                'completed'       => true,
                'next_cursor'     => ['provider_index' => $providerIndex, 'offset' => 0],
                'items_processed' => 0,
                'urls_discovered' => 0,
            ];
        }

        $currentProvider = $providers[$providerIndex];
        $items = $currentProvider->getItems($offset, $batchSize);
        $itemsCount = count($items);
        $discoveredCount = 0;

        $excludedPatterns = (string) SettingsService::get('excluded_urls', '');
        $excludedDomains  = (string) SettingsService::get('excluded_domains', '');
        $scanInternal     = (bool) SettingsService::get('scan_internal_links', true);
        $scanExternal     = (bool) SettingsService::get('scan_external_links', true);

        foreach ($items as $item) {
            $occurrences = $currentProvider->extractUrls($item);

            foreach ($occurrences as $occ) {
                $rawUrl = $occ['raw_url'];
                $normalized = UrlNormalizer::normalize($rawUrl);
                if ($normalized === null) {
                    continue;
                }

                // Check exclusions
                if (UrlNormalizer::isExcluded($normalized, $excludedPatterns, $excludedDomains)) {
                    continue;
                }

                $isInternal = UrlNormalizer::isInternal($normalized);
                if ($isInternal && !$scanInternal) {
                    continue;
                }
                if (!$isInternal && !$scanExternal) {
                    continue;
                }

                // Persist URL (deduplicated)
                $urlId = $this->urlRepo->findOrCreate($normalized, [
                    'link_type' => $occ['link_type'],
                ]);

                // Persist Occurrence
                $this->occRepo->saveOccurrence($urlId, $occ);
                $discoveredCount++;
            }
        }

        // Advance cursor
        if ($itemsCount < $batchSize) {
            // Finished current provider, advance to next
            $nextProviderIndex = $providerIndex + 1;
            $nextOffset = 0;
            $isComplete = $nextProviderIndex >= count($providers);
        } else {
            $nextProviderIndex = $providerIndex;
            $nextOffset = $offset + $itemsCount;
            $isComplete = false;
        }

        return [
            'completed'       => $isComplete,
            'next_cursor'     => ['provider_index' => $nextProviderIndex, 'offset' => $nextOffset],
            'items_processed' => $itemsCount,
            'urls_discovered' => $discoveredCount,
        ];
    }
}
