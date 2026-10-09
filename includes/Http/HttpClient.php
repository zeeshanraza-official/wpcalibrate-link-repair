<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Http;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Scanner\StatusClassifier;
use WPCalibrate\LinkRepair\Services\SettingsService;
use WPCalibrate\LinkRepair\Services\Logger;

/**
 * Robust HTTP client using WordPress HTTP API with SSRF prevention,
 * HEAD->GET fallback, bounded response size, redirect tracking, and retries.
 */
final class HttpClient
{
    /**
     * @var array<string, float> Last request timestamp per host for throttling
     */
    private static array $lastHostRequestTime = [];

    /**
     * Validate health of a URL.
     *
     * @param string $url
     * @return array{
     *     http_code: ?int,
     *     status: string,
     *     redirect_url: ?string,
     *     error_message: ?string,
     *     redirect_chain: array<int, string>
     * }
     */
    public function checkUrl(string $url): array
    {
        // 1. Check if URL can be verified via local WordPress APIs without HTTP request
        $localCheck = UrlNormalizer::verifyLocally($url);
        if ($localCheck !== null) {
            if ($localCheck['exists']) {
                return [
                    'http_code'      => 200,
                    'status'         => StatusClassifier::STATUS_WORKING,
                    'redirect_url'   => null,
                    'error_message'  => null,
                    'redirect_chain' => [],
                ];
            } else {
                return [
                    'http_code'      => 404,
                    'status'         => StatusClassifier::STATUS_BROKEN,
                    'redirect_url'   => null,
                    'error_message'  => 'Local internal file/resource not found',
                    'redirect_chain' => [],
                ];
            }
        }

        // 2. SSRF Protection
        $isInternal = UrlNormalizer::isInternal($url);
        $ssrfCheck  = SsrfProtector::isSafe($url, $isInternal);
        if (!$ssrfCheck['safe']) {
            return [
                'http_code'      => null,
                'status'         => StatusClassifier::STATUS_WARNING,
                'redirect_url'   => null,
                'error_message'  => 'Blocked by SSRF protection: ' . $ssrfCheck['reason'],
                'redirect_chain' => [],
            ];
        }

        // 3. Throttling
        $this->throttle($url);

        // 4. Execution with retry policy
        $retryLimit = (int) SettingsService::get('retry_count', 2);
        $attempt = 0;
        $result = null;

        while ($attempt <= $retryLimit) {
            $result = $this->executeRequestCycle($url);

            // If result is not retryable, break immediately
            if (!StatusClassifier::isRetryable($result['status'])) {
                break;
            }

            $attempt++;
            if ($attempt <= $retryLimit) {
                // Short exponential backoff (e.g. 200ms, 400ms)
                usleep($attempt * 200000);
            }
        }

        return $result ?? [
            'http_code'      => null,
            'status'         => StatusClassifier::STATUS_CONNECTION_ERROR,
            'redirect_url'   => null,
            'error_message'  => 'No response received',
            'redirect_chain' => [],
        ];
    }

    /**
     * Execute HEAD request with GET fallback, tracking redirects manually.
     *
     * @param string $initialUrl
     * @return array{
     *     http_code: ?int,
     *     status: string,
     *     redirect_url: ?string,
     *     error_message: ?string,
     *     redirect_chain: array<int, string>
     * }
     */
    private function executeRequestCycle(string $initialUrl): array
    {
        $timeout      = (int) SettingsService::get('request_timeout', 10);
        $maxRedirects = (int) SettingsService::get('max_redirects', 5);
        $userAgent    = (string) SettingsService::get('user_agent', 'WPCalibrate Link Repair');

        $currentUrl = $initialUrl;
        $redirectChain = [];
        $redirectCount = 0;

        while ($redirectCount <= $maxRedirects) {
            // First attempt: HEAD
            $response = $this->doRequest($currentUrl, 'HEAD', $timeout, $userAgent);

            // Fall back to GET if HEAD failed, timed out, or returned method not allowed / ambiguous
            if ($this->shouldFallbackToGet($response)) {
                $response = $this->doRequest($currentUrl, 'GET', $timeout, $userAgent);
            }

            if (is_wp_error($response)) {
                $errMsg = $response->get_error_message();
                return [
                    'http_code'      => null,
                    'status'         => StatusClassifier::classify(null, $errMsg),
                    'redirect_url'   => null,
                    'error_message'  => $errMsg,
                    'redirect_chain' => $redirectChain,
                ];
            }

            $code = (int) wp_remote_retrieve_response_code($response);

            // Check for redirect response
            if ($code >= 300 && $code < 400) {
                $location = wp_remote_retrieve_header($response, 'location');
                if (empty($location)) {
                    return [
                        'http_code'      => $code,
                        'status'         => StatusClassifier::STATUS_REDIRECT,
                        'redirect_url'   => null,
                        'error_message'  => 'Redirect status received without Location header',
                        'redirect_chain' => $redirectChain,
                    ];
                }

                $nextUrl = UrlNormalizer::resolveRelative($location, $currentUrl);
                $redirectChain[] = $nextUrl;

                // Detect redirect loop (returns to initial URL or any previous URL in chain)
                if ($nextUrl === $initialUrl || in_array($nextUrl, $redirectChain, true)) {
                    return [
                        'http_code'      => $code,
                        'status'         => StatusClassifier::STATUS_WARNING,
                        'redirect_url'   => $nextUrl,
                        'error_message'  => 'Redirect loop detected',
                        'redirect_chain' => $redirectChain,
                    ];
                }

                // Verify SSRF for redirect target
                $targetSafe = SsrfProtector::isSafe($nextUrl, UrlNormalizer::isInternal($nextUrl));
                if (!$targetSafe['safe']) {
                    return [
                        'http_code'      => $code,
                        'status'         => StatusClassifier::STATUS_WARNING,
                        'redirect_url'   => $nextUrl,
                        'error_message'  => 'Redirect destination blocked by SSRF protection',
                        'redirect_chain' => $redirectChain,
                    ];
                }

                $currentUrl = $nextUrl;
                $redirectCount++;

                if ($redirectCount > $maxRedirects) {
                    return [
                        'http_code'      => $code,
                        'status'         => StatusClassifier::STATUS_WARNING,
                        'redirect_url'   => $currentUrl,
                        'error_message'  => sprintf('Exceeded maximum redirect depth (%d)', $maxRedirects),
                        'redirect_chain' => $redirectChain,
                    ];
                }

                continue;
            }

            // Non-redirect response reached
            $finalStatus = StatusClassifier::classify($code);
            $finalRedirect = !empty($redirectChain) ? end($redirectChain) : null;

            return [
                'http_code'      => $code,
                'status'         => !empty($redirectChain) ? StatusClassifier::STATUS_REDIRECT : $finalStatus,
                'redirect_url'   => $finalRedirect,
                'error_message'  => null,
                'redirect_chain' => $redirectChain,
            ];
        }

        return [
            'http_code'      => null,
            'status'         => StatusClassifier::STATUS_WARNING,
            'redirect_url'   => $currentUrl,
            'error_message'  => 'Too many redirects',
            'redirect_chain' => $redirectChain,
        ];
    }

    /**
     * Check if a HEAD response warrants GET fallback.
     */
    private function shouldFallbackToGet(mixed $response): bool
    {
        if (is_wp_error($response)) {
            return true;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        // 405 Method Not Allowed, 501 Not Implemented, 400 Bad Request, 403 Forbidden (many anti-bots block HEAD)
        return in_array($code, [400, 403, 405, 500, 501], true);
    }

    /**
     * Issue HTTP request using WordPress HTTP API.
     */
    private function doRequest(string $url, string $method, int $timeout, string $userAgent): mixed
    {
        $args = [
            'method'              => $method,
            'timeout'             => $timeout,
            'redirection'         => 0, // Manual redirect following for accurate chains
            'user-agent'          => $userAgent,
            'headers'             => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            ],
            'sslverify'           => true,
            'limit_response_size' => 102400, // 100 KB max body
            'stream'              => false,
            'decompress'          => false,
        ];

        // Never forward cookies or authorization headers
        return wp_safe_remote_request($url, $args);
    }

    /**
     * Apply global and per-host throttling delays.
     */
    private function throttle(string $url): void
    {
        $globalDelayMs = (int) SettingsService::get('request_delay_ms', 100);
        $perHostDelayMs = (int) SettingsService::get('per_host_delay_ms', 200);

        if ($globalDelayMs > 0) {
            usleep($globalDelayMs * 1000);
        }

        $host = (string) wp_parse_url($url, PHP_URL_HOST);
        if ($host !== '' && $perHostDelayMs > 0) {
            $now = microtime(true);
            $lastTime = self::$lastHostRequestTime[$host] ?? 0.0;
            $elapsedMs = ($now - $lastTime) * 1000;

            if ($elapsedMs < $perHostDelayMs) {
                $sleepMs = $perHostDelayMs - $elapsedMs;
                usleep((int) ($sleepMs * 1000));
            }

            self::$lastHostRequestTime[$host] = microtime(true);
        }
    }
}
