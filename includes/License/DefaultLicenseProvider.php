<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\License;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Default licensing provider reporting 'Not configured'.
 */
final class DefaultLicenseProvider implements LicenseProviderInterface
{
    private const OPTION_KEY = 'wpcalibrate_lr_license_data';

    public function getId(): string
    {
        return 'default';
    }

    public function getTitle(): string
    {
        return __('Default Licensing Service', 'wpcalibrate-link-repair');
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function getStatus(): string
    {
        return 'not_configured';
    }

    public function getStatusLabel(): string
    {
        return __('Not Configured', 'wpcalibrate-link-repair');
    }

    public function getMaskedKey(): string
    {
        $data = (array) get_option(self::OPTION_KEY, []);
        $key = (string) ($data['key'] ?? '');
        if ($key === '') {
            return '';
        }

        $length = strlen($key);
        if ($length <= 4) {
            return str_repeat('•', $length);
        }

        $last4 = substr($key, -4);
        return str_repeat('•', min(16, $length - 4)) . $last4;
    }

    public function setKey(string $key): void
    {
        $clean = trim($key);
        if ($clean === '') {
            delete_option(self::OPTION_KEY);
            return;
        }

        update_option(self::OPTION_KEY, [
            'key'        => $clean,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], false);
    }

    public function activate(): array
    {
        return [
            'success' => false,
            'message' => __('Licensing backend is not configured. Activation requests are disabled until provider details are supplied.', 'wpcalibrate-link-repair'),
        ];
    }

    public function deactivate(): array
    {
        return [
            'success' => false,
            'message' => __('Licensing backend is not configured.', 'wpcalibrate-link-repair'),
        ];
    }

    public function getRenewalUrl(): string
    {
        return 'https://marketplace.wpcalibrate.com/';
    }
}
