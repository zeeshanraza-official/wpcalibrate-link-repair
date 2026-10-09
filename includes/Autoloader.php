<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PSR-4 compliant autoloader for WPCalibrate Link Repair.
 */
final class Autoloader
{
    private const PREFIX = 'WPCalibrate\\LinkRepair\\';
    private static ?string $baseDir = null;

    /**
     * Register autoloader with SPL stack.
     */
    public static function register(string $baseDir): void
    {
        self::$baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
        spl_autoload_register([self::class, 'load']);
    }

    /**
     * Autoload callback.
     */
    public static function load(string $class): void
    {
        if (!str_starts_with($class, self::PREFIX)) {
            return;
        }

        $relativeClass = substr($class, strlen(self::PREFIX));
        $segments = explode('\\', $relativeClass);
        $className = array_pop($segments);

        // Normalize path: check exact case first, then check case-insensitive match for portability
        $subPath = empty($segments) ? '' : implode(DIRECTORY_SEPARATOR, $segments) . DIRECTORY_SEPARATOR;
        $file = self::$baseDir . $subPath . $className . '.php';

        if (file_exists($file)) {
            require_once $file;
            return;
        }

        // Fallback for lowercase directory structure (e.g. includes/admin/ vs includes/Admin/)
        $lowerSubPath = empty($segments) ? '' : strtolower(implode(DIRECTORY_SEPARATOR, $segments)) . DIRECTORY_SEPARATOR;
        $fallbackFile = self::$baseDir . $lowerSubPath . $className . '.php';

        if (file_exists($fallbackFile)) {
            require_once $fallbackFile;
        }
    }
}
