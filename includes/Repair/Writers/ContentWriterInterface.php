<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Repair\Writers;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Contract for format-specific safe content modification.
 */
interface ContentWriterInterface
{
    /**
     * Determine if this writer can handle the specified source type and field.
     */
    public function canHandle(string $sourceType, string $fieldKey): bool;

    /**
     * Replace occurrences of an exact URL in the target content object.
     *
     * @param int $sourceId
     * @param string $fieldKey
     * @param string $oldUrl
     * @param string $newUrl
     * @param string|null $contextPath Optional specific occurrence selector
     * @return array{success: bool, message: string, modified_count: int}
     */
    public function replaceUrl(
        int $sourceId,
        string $fieldKey,
        string $oldUrl,
        string $newUrl,
        ?string $contextPath = null
    ): array;

    /**
     * Remove an anchor tag while preserving its visible inner anchor text.
     *
     * @param int $sourceId
     * @param string $fieldKey
     * @param string $targetUrl
     * @param string|null $contextPath
     * @return array{success: bool, message: string, modified_count: int}
     */
    public function unlinkUrl(
        int $sourceId,
        string $fieldKey,
        string $targetUrl,
        ?string $contextPath = null
    ): array;
}
