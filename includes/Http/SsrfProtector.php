<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Http;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SSRF protector preventing scanning requests to local/private network addresses or dangerous schemes.
 */
final class SsrfProtector
{
    /**
     * Check if a URL is safe to request externally.
     *
     * @param string $url The URL to validate.
     * @param bool $allowLocal If true, allows internal site host (when verified as site domain).
     * @return array{safe: bool, reason: string}
     */
    public static function isSafe(string $url, bool $allowLocal = false): array
    {
        $parsed = wp_parse_url($url);
        if (!$parsed || empty($parsed['scheme']) || empty($parsed['host'])) {
            return ['safe' => false, 'reason' => 'invalid_url'];
        }

        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return ['safe' => false, 'reason' => 'unsafe_scheme'];
        }

        $host = strtolower($parsed['host']);

        // Check for obvious localhost / internal hostnames
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true) || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            if ($allowLocal && UrlNormalizer::isInternal($url)) {
                return ['safe' => true, 'reason' => 'local_internal_site'];
            }
            return ['safe' => false, 'reason' => 'localhost_blocked'];
        }

        // Cloud metadata addresses (AWS, GCP, Azure, DigitalOcean)
        if ($host === '169.254.169.254' || $host === 'metadata.google.internal' || $host === 'instance-data') {
            return ['safe' => false, 'reason' => 'cloud_metadata_blocked'];
        }

        // If host is an IP address, validate IP range
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!self::isPublicIp($host)) {
                if ($allowLocal && UrlNormalizer::isInternal($url)) {
                    return ['safe' => true, 'reason' => 'local_internal_site'];
                }
                return ['safe' => false, 'reason' => 'private_ip_blocked'];
            }
            return ['safe' => true, 'reason' => 'public_ip'];
        }

        // Host is a domain name. Resolve IP to check for DNS rebinding / private targets
        $ips = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (is_array($ips) && !empty($ips)) {
            foreach ($ips as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                if ($ip && !self::isPublicIp($ip)) {
                    if ($allowLocal && UrlNormalizer::isInternal($url)) {
                        return ['safe' => true, 'reason' => 'local_internal_site'];
                    }
                    return ['safe' => false, 'reason' => 'dns_resolved_to_private_ip'];
                }
            }
        }

        return ['safe' => true, 'reason' => 'ok'];
    }

    /**
     * Determine if an IP is a publicly routable IP address (not loopback, private, or link-local).
     */
    public static function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
