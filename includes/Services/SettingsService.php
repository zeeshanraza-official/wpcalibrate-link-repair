<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service for plugin settings management and validation.
 */
final class SettingsService
{
    public const OPTION_KEY = 'wpcalibrate_lr_settings';

    /**
     * Get default settings values.
     *
     * @return array<string, mixed>
     */
    public static function getDefaults(): array
    {
        return [
            'scan_post_types'         => ['post', 'page'],
            'scan_internal_links'     => true,
            'scan_external_links'     => true,
            'scan_media'              => true,
            'scan_custom_fields'      => false,
            'custom_field_keys'       => '',
            'schedule_frequency'      => 'weekly', // 'disabled', 'daily', 'weekly', 'monthly'
            'schedule_time'           => '03:00',
            'batch_size'              => 25,
            'request_timeout'         => 10,
            'retry_count'             => 2,
            'max_redirects'           => 5,
            'request_delay_ms'        => 100,
            'per_host_delay_ms'       => 200,
            'email_reports_enabled'   => false,
            'email_recipient'         => get_option('admin_email', ''),
            'email_only_broken'       => true,
            'email_all_clear'         => false,
            'excluded_urls'           => "#\nmailto:\ntel:\njavascript:",
            'excluded_domains'        => '',
            'ignored_http_statuses'   => '',
            'retention_days'          => 90,
            'delete_data_on_uninstall'=> false,
            'enable_debug_log'        => false,
            'user_agent'              => 'WPCalibrate Link Repair/' . WPCALIBRATE_LINK_REPAIR_VERSION . ' (+' . home_url('/') . ')',
        ];
    }

    /**
     * Get all settings merged with defaults.
     *
     * @return array<string, mixed>
     */
    public static function getAll(): array
    {
        $saved = get_option(self::OPTION_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }
        return array_merge(self::getDefaults(), $saved);
    }

    /**
     * Get a specific setting value.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = self::getAll();
        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }
        return $default;
    }

    /**
     * Set a specific setting value.
     */
    public static function set(string $key, mixed $value): void
    {
        $settings = self::getAll();
        $settings[$key] = $value;
        update_option(self::OPTION_KEY, $settings);
    }

    /**
     * Validate and sanitize settings array before saving.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function sanitize(array $input): array
    {
        $clean = self::getDefaults();

        // Post types
        if (isset($input['scan_post_types']) && is_array($input['scan_post_types'])) {
            $available = get_post_types(['public' => true]);
            $clean['scan_post_types'] = array_values(array_intersect($input['scan_post_types'], array_keys($available)));
        }

        // Toggles
        $clean['scan_internal_links']      = !empty($input['scan_internal_links']);
        $clean['scan_external_links']      = !empty($input['scan_external_links']);
        $clean['scan_media']               = !empty($input['scan_media']);
        $clean['scan_custom_fields']       = !empty($input['scan_custom_fields']);
        $clean['email_reports_enabled']    = !empty($input['email_reports_enabled']);
        $clean['email_only_broken']        = !empty($input['email_only_broken']);
        $clean['email_all_clear']          = !empty($input['email_all_clear']);
        $clean['delete_data_on_uninstall'] = !empty($input['delete_data_on_uninstall']);
        $clean['enable_debug_log']         = !empty($input['enable_debug_log']);

        // Custom field keys
        if (isset($input['custom_field_keys'])) {
            $clean['custom_field_keys'] = sanitize_textarea_field((string) $input['custom_field_keys']);
        }

        // Schedule
        $allowedFrequencies = ['disabled', 'daily', 'weekly', 'monthly'];
        if (isset($input['schedule_frequency']) && in_array($input['schedule_frequency'], $allowedFrequencies, true)) {
            $clean['schedule_frequency'] = $input['schedule_frequency'];
        }

        if (isset($input['schedule_time']) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $input['schedule_time'])) {
            $clean['schedule_time'] = sanitize_text_field((string) $input['schedule_time']);
        }

        // Batch size (5 - 100)
        if (isset($input['batch_size'])) {
            $batch = (int) $input['batch_size'];
            $clean['batch_size'] = max(5, min(100, $batch));
        }

        // Timeout (3 - 30 seconds)
        if (isset($input['request_timeout'])) {
            $timeout = (int) $input['request_timeout'];
            $clean['request_timeout'] = max(3, min(30, $timeout));
        }

        // Retry count (0 - 3)
        if (isset($input['retry_count'])) {
            $retries = (int) $input['retry_count'];
            $clean['retry_count'] = max(0, min(3, $retries));
        }

        // Max redirects (1 - 10)
        if (isset($input['max_redirects'])) {
            $redirects = (int) $input['max_redirects'];
            $clean['max_redirects'] = max(1, min(10, $redirects));
        }

        // Request delays (0 - 2000 ms)
        if (isset($input['request_delay_ms'])) {
            $delay = (int) $input['request_delay_ms'];
            $clean['request_delay_ms'] = max(0, min(2000, $delay));
        }

        if (isset($input['per_host_delay_ms'])) {
            $hostDelay = (int) $input['per_host_delay_ms'];
            $clean['per_host_delay_ms'] = max(0, min(3000, $hostDelay));
        }

        // Email recipient
        if (isset($input['email_recipient'])) {
            $email = sanitize_email((string) $input['email_recipient']);
            $clean['email_recipient'] = is_email($email) ? $email : (string) get_option('admin_email', '');
        }

        // Exclusions
        if (isset($input['excluded_urls'])) {
            $clean['excluded_urls'] = sanitize_textarea_field((string) $input['excluded_urls']);
        }

        if (isset($input['excluded_domains'])) {
            $clean['excluded_domains'] = sanitize_textarea_field((string) $input['excluded_domains']);
        }

        if (isset($input['ignored_http_statuses'])) {
            $clean['ignored_http_statuses'] = sanitize_text_field((string) $input['ignored_http_statuses']);
        }

        // Retention days (7 - 365)
        if (isset($input['retention_days'])) {
            $days = (int) $input['retention_days'];
            $clean['retention_days'] = max(7, min(365, $days));
        }

        // User Agent
        if (isset($input['user_agent']) && !empty(trim((string) $input['user_agent']))) {
            $clean['user_agent'] = sanitize_text_field(trim((string) $input['user_agent']));
        }

        return $clean;
    }

    /**
     * Save settings.
     *
     * @param array<string, mixed> $input
     * @return bool
     */
    public static function update(array $input): bool
    {
        $sanitized = self::sanitize($input);
        return update_option(self::OPTION_KEY, $sanitized, false);
    }
}
