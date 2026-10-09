<?php
/**
 * Plugin Name:       WPCalibrate Link Repair
 * Plugin URI:        https://marketplace.wpcalibrate.com/
 * Description:       Scans WordPress content for broken internal and external links, redirects, and missing media, providing safe administrator tools to review and repair them.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.2
 * Author:            WPCalibrate
 * Author URI:        https://wpcalibrate.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpcalibrate-link-repair
 * Domain Path:       /languages
 * Update URI:        https://github.com/zeeshanraza-official/wpcalibrate-link-repair
 * GitHub Plugin URI: https://github.com/zeeshanraza-official/wpcalibrate-link-repair
 * Primary Branch:    main
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WPCALIBRATE_LINK_REPAIR_VERSION', '1.0.0');
define('WPCALIBRATE_LINK_REPAIR_SCHEMA_VERSION', '1.0.0');
define('WPCALIBRATE_LINK_REPAIR_MIN_PHP', '8.2.0');
define('WPCALIBRATE_LINK_REPAIR_MIN_WP', '6.2.0');
define('WPCALIBRATE_LINK_REPAIR_FILE', __FILE__);
define('WPCALIBRATE_LINK_REPAIR_DIR', plugin_dir_path(__FILE__));
define('WPCALIBRATE_LINK_REPAIR_URL', plugin_dir_url(__FILE__));
define('WPCALIBRATE_LINK_REPAIR_BASENAME', plugin_basename(__FILE__));

// PHP and WordPress version compatibility checks
if (version_compare(PHP_VERSION, WPCALIBRATE_LINK_REPAIR_MIN_PHP, '<')) {
    add_action('admin_notices', static function (): void {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: 1: Required PHP version, 2: Current PHP version */
                        __('WPCalibrate Link Repair requires PHP version %1$s or higher. Your server is currently running PHP version %2$s.', 'wpcalibrate-link-repair'),
                        WPCALIBRATE_LINK_REPAIR_MIN_PHP,
                        PHP_VERSION
                    )
                );
                ?>
            </p>
        </div>
        <?php
    });
    return;
}

// Require autoloader
require_once WPCALIBRATE_LINK_REPAIR_DIR . 'includes/Autoloader.php';
\WPCalibrate\LinkRepair\Autoloader::register(WPCALIBRATE_LINK_REPAIR_DIR . 'includes');

// Bootstrap the plugin
add_action('plugins_loaded', static function (): void {
    global $wp_version;
    if (isset($wp_version) && version_compare($wp_version, WPCALIBRATE_LINK_REPAIR_MIN_WP, '<')) {
        add_action('admin_notices', static function () use ($wp_version): void {
            ?>
            <div class="notice notice-error">
                <p>
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: 1: Required WordPress version, 2: Current WordPress version */
                            __('WPCalibrate Link Repair requires WordPress version %1$s or higher. Your site is currently running WordPress version %2$s.', 'wpcalibrate-link-repair'),
                            WPCALIBRATE_LINK_REPAIR_MIN_WP,
                            $wp_version
                        )
                    );
                    ?>
                </p>
            </div>
            <?php
        });
        return;
    }

    \WPCalibrate\LinkRepair\Plugin::getInstance()->boot();
});

// Register activation and deactivation hooks
register_activation_hook(__FILE__, static function (): void {
    \WPCalibrate\LinkRepair\Plugin::activate();
});

register_deactivation_hook(__FILE__, static function (): void {
    \WPCalibrate\LinkRepair\Plugin::deactivate();
});
