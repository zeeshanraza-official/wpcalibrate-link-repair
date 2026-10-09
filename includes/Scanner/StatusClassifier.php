<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Scanner;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classifies HTTP status codes and transport errors into standardized categories.
 */
final class StatusClassifier
{
    public const STATUS_WORKING          = 'working';
    public const STATUS_REDIRECT         = 'redirect';
    public const STATUS_BROKEN           = 'broken';
    public const STATUS_SERVER_ERROR     = 'server_error';
    public const STATUS_TIMEOUT          = 'timeout';
    public const STATUS_DNS_ERROR        = 'dns_error';
    public const STATUS_SSL_ERROR        = 'ssl_error';
    public const STATUS_CONNECTION_ERROR = 'connection_error';
    public const STATUS_AUTH_RESTRICTED  = 'auth_restricted';
    public const STATUS_RATE_LIMITED     = 'rate_limited';
    public const STATUS_WARNING          = 'warning';
    public const STATUS_IGNORED          = 'ignored';

    /**
     * Classify an HTTP response code or WP_Error.
     *
     * @param int|null $httpCode
     * @param string|null $errorMessage
     * @return string Status classification category
     */
    public static function classify(?int $httpCode, ?string $errorMessage = null): string
    {
        if ($errorMessage !== null && $errorMessage !== '') {
            $errLower = strtolower($errorMessage);

            if (str_contains($errLower, 'timed out') || str_contains($errLower, 'operation timed out') || str_contains($errLower, 'curl error 28')) {
                return self::STATUS_TIMEOUT;
            }

            if (str_contains($errLower, 'could not resolve host') || str_contains($errLower, 'name resolution') || str_contains($errLower, 'curl error 6')) {
                return self::STATUS_DNS_ERROR;
            }

            if (str_contains($errLower, 'ssl') || str_contains($errLower, 'certificate') || str_contains($errLower, 'curl error 35') || str_contains($errLower, 'curl error 60')) {
                return self::STATUS_SSL_ERROR;
            }

            if (str_contains($errLower, 'failed to connect') || str_contains($errLower, 'connection refused') || str_contains($errLower, 'curl error 7')) {
                return self::STATUS_CONNECTION_ERROR;
            }

            if (str_contains($errLower, 'redirect loop') || str_contains($errLower, 'too many redirects') || str_contains($errLower, 'curl error 47')) {
                return self::STATUS_WARNING;
            }
        }

        if ($httpCode === null || $httpCode <= 0) {
            return self::STATUS_CONNECTION_ERROR;
        }

        // 2xx Success
        if ($httpCode >= 200 && $httpCode < 300) {
            return self::STATUS_WORKING;
        }

        // 3xx Redirects
        if ($httpCode >= 300 && $httpCode < 400) {
            return self::STATUS_REDIRECT;
        }

        // Definite broken
        if ($httpCode === 404 || $httpCode === 410) {
            return self::STATUS_BROKEN;
        }

        // Authentication restricted (not classified as broken)
        if ($httpCode === 401 || $httpCode === 403) {
            return self::STATUS_AUTH_RESTRICTED;
        }

        // Rate limited
        if ($httpCode === 429) {
            return self::STATUS_RATE_LIMITED;
        }

        // Server errors (5xx)
        if ($httpCode >= 500 && $httpCode < 600) {
            return self::STATUS_SERVER_ERROR;
        }

        // Other 4xx or ambiguous
        return self::STATUS_WARNING;
    }

    /**
     * Determine if a status category warrants a retry before final recording.
     */
    public static function isRetryable(string $status): bool
    {
        return in_array(
            $status,
            [
                self::STATUS_TIMEOUT,
                self::STATUS_SERVER_ERROR,
                self::STATUS_RATE_LIMITED,
                self::STATUS_CONNECTION_ERROR,
            ],
            true
        );
    }

    /**
     * Get accessible human-readable label for a status category.
     */
    public static function getLabel(string $status): string
    {
        $labels = [
            self::STATUS_WORKING          => __('Working (200 OK)', 'wpcalibrate-link-repair'),
            self::STATUS_REDIRECT         => __('Redirect (3xx)', 'wpcalibrate-link-repair'),
            self::STATUS_BROKEN           => __('Broken (404/410)', 'wpcalibrate-link-repair'),
            self::STATUS_SERVER_ERROR     => __('Server Error (5xx)', 'wpcalibrate-link-repair'),
            self::STATUS_TIMEOUT          => __('Timeout', 'wpcalibrate-link-repair'),
            self::STATUS_DNS_ERROR        => __('DNS Error', 'wpcalibrate-link-repair'),
            self::STATUS_SSL_ERROR        => __('SSL/TLS Error', 'wpcalibrate-link-repair'),
            self::STATUS_CONNECTION_ERROR => __('Connection Failed', 'wpcalibrate-link-repair'),
            self::STATUS_AUTH_RESTRICTED  => __('Restricted (401/403)', 'wpcalibrate-link-repair'),
            self::STATUS_RATE_LIMITED     => __('Rate Limited (429)', 'wpcalibrate-link-repair'),
            self::STATUS_WARNING          => __('Warning / Ambiguous', 'wpcalibrate-link-repair'),
            self::STATUS_IGNORED          => __('Ignored', 'wpcalibrate-link-repair'),
        ];

        return $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }
}
