<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin-specific logger with sensitive data scrubbing.
 */
final class Logger
{
    public const LEVEL_DEBUG = 'debug';
    public const LEVEL_INFO  = 'info';
    public const LEVEL_WARN  = 'warning';
    public const LEVEL_ERROR = 'error';

    private const SENSITIVE_KEYS = [
        'password',
        'pass',
        'key',
        'license',
        'license_key',
        'authorization',
        'cookie',
        'nonce',
        'secret',
        'token',
        'api_key',
    ];

    /**
     * Log a message with context.
     *
     * @param string $level Log severity level.
     * @param string $message Log message.
     * @param array<string, mixed> $context Safe contextual data.
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $settings = SettingsService::getAll();
        $debugEnabled = !empty($settings['enable_debug_log']) || (defined('WP_DEBUG') && WP_DEBUG);

        if ($level === self::LEVEL_DEBUG && !$debugEnabled) {
            return;
        }

        $sanitizedContext = self::scrubContext($context);
        $contextString = !empty($sanitizedContext) ? ' | Context: ' . wp_json_encode($sanitizedContext) : '';
        $entry = sprintf(
            '[%s] [WPCalibrate Link Repair] [%s] %s%s',
            gmdate('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $contextString
        );

        // Store bounded log in an option for admin diagnostic viewing (max 200 entries)
        self::appendDiagnosticLog($entry);

        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($entry); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log(self::LEVEL_DEBUG, $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log(self::LEVEL_INFO, $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log(self::LEVEL_WARN, $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Remove sensitive data from context array recursively.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function scrubContext(array $context): array
    {
        $clean = [];
        foreach ($context as $key => $val) {
            $keyLower = strtolower((string) $key);
            $isSensitive = false;
            foreach (self::SENSITIVE_KEYS as $sensitive) {
                if (str_contains($keyLower, $sensitive)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $clean[$key] = '***REDACTED***';
            } elseif (is_array($val)) {
                $clean[$key] = self::scrubContext($val);
            } else {
                $clean[$key] = $val;
            }
        }
        return $clean;
    }

    /**
     * Store recent logs in a bounded circular buffer (max 200 items).
     */
    private static function appendDiagnosticLog(string $entry): void
    {
        $logs = (array) get_option('wpcalibrate_lr_recent_logs', []);
        $logs[] = $entry;
        if (count($logs) > 200) {
            $logs = array_slice($logs, -200);
        }
        update_option('wpcalibrate_lr_recent_logs', $logs, false);
    }

    /**
     * Retrieve recent logs.
     *
     * @return array<int, string>
     */
    public static function getRecentLogs(): array
    {
        return (array) get_option('wpcalibrate_lr_recent_logs', []);
    }

    /**
     * Clear diagnostic logs.
     */
    public static function clearLogs(): void
    {
        delete_option('wpcalibrate_lr_recent_logs');
    }
}
