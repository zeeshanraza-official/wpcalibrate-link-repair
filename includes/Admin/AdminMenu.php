<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Services\CapabilityService;

/**
 * Handles WordPress admin menu registration, shared parent menu coexistence, and tab routing.
 */
final class AdminMenu
{
    public const PARENT_MENU_SLUG = 'wpcalibrate';
    public const PAGE_SLUG        = 'wpcalibrate-link-repair';

    private PageRenderer $renderer;

    public function __construct(?PageRenderer $renderer = null)
    {
        $this->renderer = $renderer ?? new PageRenderer();
        add_action('admin_menu', [$this, 'registerSharedParentMenu'], 9);
        add_action('admin_menu', [$this, 'registerPluginMenu'], 10);
        add_action('admin_head', [$this, 'outputAdminMenuStyles']);
    }

    /**
     * Defensively register shared top-level WPCalibrate menu at priority 9 if not already registered.
     */
    public function registerSharedParentMenu(): void
    {
        global $menu;
        $capability = CapabilityService::getRequiredCapability();

        // Check if top-level menu 'wpcalibrate' is already registered by another WPCalibrate plugin
        $alreadyRegistered = false;
        if (is_array($menu)) {
            foreach ($menu as $item) {
                if (isset($item[2]) && $item[2] === self::PARENT_MENU_SLUG) {
                    $alreadyRegistered = true;
                    break;
                }
            }
        }

        if (!$alreadyRegistered) {
            $iconPath = WPCALIBRATE_LINK_REPAIR_DIR . 'branding/icon-menu.svg';
            $iconUrl  = file_exists($iconPath)
                ? 'data:image/svg+xml;base64,' . base64_encode((string) file_get_contents($iconPath))
                : (file_exists(WPCALIBRATE_LINK_REPAIR_DIR . 'branding/icon-menu.png')
                    ? WPCALIBRATE_LINK_REPAIR_URL . 'branding/icon-menu.png'
                    : 'dashicons-admin-links');

            add_menu_page(
                'WPCalibrate',
                'WPCalibrate',
                $capability,
                self::PARENT_MENU_SLUG,
                [$this, 'renderPage'],
                $iconUrl,
                58
            );
        }
    }

    /**
     * Register Link Repair submenu at priority 10.
     */
    public function registerPluginMenu(): void
    {
        $capability = CapabilityService::getRequiredCapability();

        add_submenu_page(
            self::PARENT_MENU_SLUG,
            __('Link Repair - WPCalibrate', 'wpcalibrate-link-repair'),
            __('Link Repair', 'wpcalibrate-link-repair'),
            $capability,
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    /**
     * Render main plugin admin page and tabs.
     */
    public function renderPage(): void
    {
        CapabilityService::check();
        $this->renderer->render();
    }

    /**
     * Inject admin menu icon dimensions and color transitions across all admin screens.
     */
    public function outputAdminMenuStyles(): void
    {
        $slug = esc_attr(self::PARENT_MENU_SLUG);
        ?>
        <style id="wpcalibrate-menu-icon-css">
            #adminmenu .toplevel_page_<?php echo $slug; ?> .wp-menu-image {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            #adminmenu .toplevel_page_<?php echo $slug; ?> .wp-menu-image img {
                width: 20px !important;
                height: 20px !important;
                max-width: 20px !important;
                max-height: 20px !important;
                padding: 0 !important;
                margin: 0 !important;
                box-sizing: border-box;
                object-fit: contain;
                opacity: 0.65;
                transition: opacity 0.15s ease-in-out;
            }
            #adminmenu .toplevel_page_<?php echo $slug; ?> .wp-menu-image.svg {
                background-size: 20px 20px !important;
                background-position: center !important;
                background-repeat: no-repeat !important;
                opacity: 0.65;
                transition: opacity 0.15s ease-in-out;
            }
            #adminmenu .toplevel_page_<?php echo $slug; ?>:hover .wp-menu-image img,
            #adminmenu .toplevel_page_<?php echo $slug; ?>:hover .wp-menu-image.svg,
            #adminmenu .toplevel_page_<?php echo $slug; ?>.wp-has-current-submenu .wp-menu-image img,
            #adminmenu .toplevel_page_<?php echo $slug; ?>.wp-has-current-submenu .wp-menu-image.svg,
            #adminmenu .toplevel_page_<?php echo $slug; ?>.current .wp-menu-image img,
            #adminmenu .toplevel_page_<?php echo $slug; ?>.current .wp-menu-image.svg {
                opacity: 1 !important;
            }
        </style>
        <?php
    }
}

