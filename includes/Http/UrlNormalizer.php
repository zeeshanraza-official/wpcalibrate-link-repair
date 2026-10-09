<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Http;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalizes URLs, resolves relatives, classifies internal/external, and checks exclusions.
 */
final class UrlNormalizer
{
    /**
     * Compute SHA-256 hash of normalized URL for database deduplication.
     */
    public static function hash(string $url): string
    {
        return hash('sha256', $url);
    }

    /**
     * Normalize a URL:
     * - Trims whitespace
     * - Resolves relative or protocol-relative against base site URL
     * - Lowercases scheme and host
     * - Resolves path dot segments (. and ..)
     * - Removes fragment for network checking (fragment should be stored in occurrence)
     */
    public static function normalize(string $url, string $baseUrl = ''): ?string
    {
        $raw = trim($url);
        if ($raw === '' || str_starts_with($raw, '#')) {
            return null;
        }

        // Ignore common non-HTTP schemes
        if (preg_match('/^(?:mailto:|tel:|javascript:|sms:|data:|file:|php:|blob:)/i', $raw)) {
            return null;
        }

        if ($baseUrl === '') {
            $baseUrl = home_url('/');
        }

        // Protocol-relative (//example.com/path)
        if (str_starts_with($raw, '//')) {
            $siteScheme = wp_parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
            $raw = $siteScheme . ':' . $raw;
        }

        $parsed = wp_parse_url($raw);
        if ($parsed === false) {
            return null;
        }

        // Relative path resolution
        if (empty($parsed['scheme'])) {
            $raw = self::resolveRelative($raw, $baseUrl);
            $parsed = wp_parse_url($raw);
            if ($parsed === false || empty($parsed['scheme'])) {
                return null;
            }
        }

        $scheme = strtolower($parsed['scheme'] ?? 'http');
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parsed['host'] ?? '');
        if ($host === '') {
            return null;
        }

        // Filter out default ports
        $port = isset($parsed['port']) ? (int) $parsed['port'] : null;
        $portStr = '';
        if ($port !== null && !(($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443))) {
            $portStr = ':' . $port;
        }

        // Normalize path dot segments
        $path = $parsed['path'] ?? '/';
        $path = self::normalizePathSegments($path);

        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';

        // Stripped of fragment
        return $scheme . '://' . $host . $portStr . $path . $query;
    }

    /**
     * Resolve relative URL against base URL.
     */
    public static function resolveRelative(string $relative, string $baseUrl): string
    {
        $baseParts = wp_parse_url($baseUrl);
        $scheme = $baseParts['scheme'] ?? 'https';
        $host   = $baseParts['host'] ?? '';
        $port   = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';
        $baseHost = $scheme . '://' . $host . $port;

        // Root-relative (/path)
        if (str_starts_with($relative, '/')) {
            return $baseHost . $relative;
        }

        // Directory-relative
        $basePath = $baseParts['path'] ?? '/';
        if (!str_ends_with($basePath, '/')) {
            $basePath = dirname($basePath) . '/';
        }
        if ($basePath === '\\/' || $basePath === '.') {
            $basePath = '/';
        }

        return $baseHost . self::normalizePathSegments($basePath . $relative);
    }

    /**
     * Resolve '.' and '..' dot segments in a path.
     */
    public static function normalizePathSegments(string $path): string
    {
        $hasTrailingSlash = str_ends_with($path, '/') && strlen($path) > 1;
        $segments = explode('/', str_replace('\\', '/', $path));
        $resolved = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if (!empty($resolved)) {
                    array_pop($resolved);
                }
            } else {
                $resolved[] = $segment;
            }
        }

        $out = '/' . implode('/', $resolved);
        if ($hasTrailingSlash && $out !== '/' && !str_ends_with($out, '/')) {
            $out .= '/';
        }

        return $out;
    }

    /**
     * Determine if a URL is internal to the current WordPress site.
     */
    public static function isInternal(string $url): bool
    {
        $siteHost = wp_parse_url(home_url(), PHP_URL_HOST);
        $urlHost  = wp_parse_url($url, PHP_URL_HOST);

        if (!$urlHost || !$siteHost) {
            return true; // Relative URLs are internal
        }

        $siteHost = strtolower((string) $siteHost);
        $urlHost  = strtolower((string) $urlHost);

        // Normalize www prefix for host comparison
        $normSiteHost = preg_replace('/^www\./i', '', $siteHost);
        $normUrlHost  = preg_replace('/^www\./i', '', $urlHost);

        return $normSiteHost === $normUrlHost;
    }

    /**
     * Extract fragment identifier (#anchor) if present.
     */
    public static function extractFragment(string $url): ?string
    {
        $fragment = wp_parse_url($url, PHP_URL_FRAGMENT);
        return is_string($fragment) && $fragment !== '' ? '#' . $fragment : null;
    }

    /**
     * Strip fragment identifier from URL.
     */
    public static function stripFragment(string $url): string
    {
        $hashPos = strpos($url, '#');
        return $hashPos !== false ? substr($url, 0, $hashPos) : $url;
    }

    /**
     * Check if a URL matches exclusion patterns or excluded domains.
     *
     * @param string $url
     * @param array<int, string>|string $excludedPatterns
     * @param array<int, string>|string $excludedDomains
     * @return bool
     */
    public static function isExcluded(string $url, array|string $excludedPatterns = [], array|string $excludedDomains = []): bool
    {
        if (is_string($excludedPatterns)) {
            $excludedPatterns = array_filter(array_map('trim', explode("\n", $excludedPatterns)));
        }
        if (is_string($excludedDomains)) {
            $excludedDomains = array_filter(array_map('trim', explode("\n", $excludedDomains)));
        }

        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));

        // Domain exclusions
        foreach ($excludedDomains as $domain) {
            $domain = strtolower(trim($domain));
            if ($domain === '') {
                continue;
            }
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        // Pattern exclusions (substring or simple wildcard match)
        foreach ($excludedPatterns as $pattern) {
            $pattern = trim($pattern);
            if ($pattern === '') {
                continue;
            }
            if (str_contains($pattern, '*')) {
                $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '#i';
                if (preg_match($regex, $url)) {
                    return true;
                }
            } elseif (str_contains($url, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Classify link type (image, media, link).
     */
    public static function classifyLinkType(string $url, string $tagName = 'a'): string
    {
        $tagName = strtolower($tagName);
        if ($tagName === 'img') {
            return 'image';
        }
        if (in_array($tagName, ['video', 'audio', 'source', 'track'], true)) {
            return 'media';
        }

        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'avif'];
        if (in_array($extension, $imageExtensions, true)) {
            return 'image';
        }

        $mediaExtensions = ['mp4', 'mp3', 'webm', 'ogg', 'wav', 'mov', 'avi', 'flac', 'm4a', 'pdf', 'zip'];
        if (in_array($extension, $mediaExtensions, true)) {
            return 'media';
        }

        return 'link';
    }

    /**
     * Perform local WordPress lookup for internal URLs to avoid HTTP network loops.
     *
     * @return array{verified: bool, exists: bool, reason: string}|null
     */
    public static function verifyLocally(string $url): ?array
    {
        if (!self::isInternal($url)) {
            return null;
        }

        // 1. Check if URL points to uploads directory
        $uploadDir = wp_upload_dir();
        $uploadBaseUrl = $uploadDir['baseurl'];
        if (str_starts_with($url, $uploadBaseUrl)) {
            $relativePath = substr($url, strlen($uploadBaseUrl));
            $filePath = $uploadDir['basedir'] . $relativePath;
            // Remove any query args from file path
            $filePath = preg_replace('/\?.*$/', '', $filePath) ?? $filePath;
            if (file_exists($filePath)) {
                return ['verified' => true, 'exists' => true, 'reason' => 'upload_file_exists'];
            }
            return ['verified' => true, 'exists' => false, 'reason' => 'upload_file_missing'];
        }

        // 2. Check if URL points to home root
        $homeUrl = rtrim(home_url('/'), '/');
        $cleanUrl = rtrim($url, '/');
        if ($cleanUrl === $homeUrl) {
            return ['verified' => true, 'exists' => true, 'reason' => 'home_url'];
        }

        // 3. Check post/page existence via url_to_postid
        if (function_exists('url_to_postid')) {
            $postId = url_to_postid($url);
            if ($postId > 0) {
                $status = get_post_status($postId);
                if (in_array($status, ['publish', 'inherit'], true)) {
                    return ['verified' => true, 'exists' => true, 'reason' => 'post_published'];
                }
            }
        }

        return null;
    }
}
