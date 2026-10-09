<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\License;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for pluggable licensing providers.
 */
interface LicenseProviderInterface
{
    /**
     * Get unique provider identifier.
     */
    public function getId(): string;

    /**
     * Get display title.
     */
    public function getTitle(): string;

    /**
     * Check if a licensing backend is fully configured.
     */
    public function isConfigured(): bool;

    /**
     * Get current license status ('not_configured', 'inactive', 'valid', 'expired', 'disabled').
     */
    public function getStatus(): string;

    /**
     * Get human readable status label.
     */
    public function getStatusLabel(): string;

    /**
     * Get masked license key (e.g. '••••••••••••1234').
     */
    public function getMaskedKey(): string;

    /**
     * Save a new license key (stored securely/masked).
     */
    public function setKey(string $key): void;

    /**
     * Attempt license activation.
     *
     * @return array{success: bool, message: string}
     */
    public function activate(): array;

    /**
     * Attempt license deactivation.
     *
     * @return array{success: bool, message: string}
     */
    public function deactivate(): array;

    /**
     * Get renewal or purchase URL.
     */
    public function getRenewalUrl(): string;
}
