<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$iconDark = WPCALIBRATE_LINK_REPAIR_URL . 'branding/icon-dark.png';
$iconWhite = WPCALIBRATE_LINK_REPAIR_URL . 'branding/icon-white.png';
?>
<div class="wpcalibrate-lr-support-view">
    <div class="wpcalibrate-lr-card" style="max-width: 720px;">
        <div class="support-brand-banner" style="display:flex; align-items:center; gap:16px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #dcdcde;">
            <img src="<?php echo esc_url($iconDark); ?>" alt="WPCalibrate" style="max-width: 64px; height: auto;" />
            <div>
                <h2 style="margin:0 0 5px 0;"><?php esc_html_e('WPCalibrate Support & Services', 'wpcalibrate-link-repair'); ?></h2>
                <p style="margin:0; color:#646970;"><?php esc_html_e('Professional WordPress, WooCommerce & Plugin Maintenance Services', 'wpcalibrate-link-repair'); ?></p>
            </div>
        </div>

        <p>
            <?php esc_html_e('Need assistance with your website, complex link repairs, WooCommerce optimization, or bespoke plugin customizations? The WPCalibrate engineering team is available through the following verified direct channels:', 'wpcalibrate-link-repair'); ?>
        </p>

        <div class="support-channels-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 15px; margin: 25px 0;">
            <div class="channel-card" style="padding:15px; border:1px solid #c3c4c7; border-radius:6px; background:#fff;">
                <div style="font-size:20px; margin-bottom:6px;"><span class="dashicons dashicons-email"></span> <strong><?php esc_html_e('Email Support', 'wpcalibrate-link-repair'); ?></strong></div>
                <p style="margin: 4px 0;">
                    <a href="<?php echo esc_url('mailto:support@wpcalibrate.com'); ?>" class="support-link">
                        <?php echo esc_html('support@wpcalibrate.com'); ?>
                    </a>
                </p>
                <small style="color:#646970;"><?php esc_html_e('Direct ticket support & inquiries', 'wpcalibrate-link-repair'); ?></small>
            </div>

            <div class="channel-card" style="padding:15px; border:1px solid #c3c4c7; border-radius:6px; background:#fff;">
                <div style="font-size:20px; margin-bottom:6px;"><span class="dashicons dashicons-admin-site-alt3"></span> <strong><?php esc_html_e('Official Website', 'wpcalibrate-link-repair'); ?></strong></div>
                <p style="margin: 4px 0;">
                    <a href="<?php echo esc_url('https://wpcalibrate.com'); ?>" target="_blank" rel="noopener noreferrer" class="support-link">
                        <?php echo esc_html('https://wpcalibrate.com'); ?>
                    </a>
                </p>
                <small style="color:#646970;"><?php esc_html_e('Maintenance plans, services & guides', 'wpcalibrate-link-repair'); ?></small>
            </div>

            <div class="channel-card" style="padding:15px; border:1px solid #c3c4c7; border-radius:6px; background:#fff;">
                <div style="font-size:20px; margin-bottom:6px;"><span class="dashicons dashicons-cart"></span> <strong><?php esc_html_e('Marketplace', 'wpcalibrate-link-repair'); ?></strong></div>
                <p style="margin: 4px 0;">
                    <a href="<?php echo esc_url('https://marketplace.wpcalibrate.com/'); ?>" target="_blank" rel="noopener noreferrer" class="support-link">
                        <?php echo esc_html('https://marketplace.wpcalibrate.com/'); ?>
                    </a>
                </p>
                <small style="color:#646970;"><?php esc_html_e('Premium extensions & tools', 'wpcalibrate-link-repair'); ?></small>
            </div>

            <div class="channel-card" style="padding:15px; border:1px solid #c3c4c7; border-radius:6px; background:#fff;">
                <div style="font-size:20px; margin-bottom:6px;"><span class="dashicons dashicons-phone"></span> <strong><?php esc_html_e('WhatsApp & Phone', 'wpcalibrate-link-repair'); ?></strong></div>
                <p style="margin: 4px 0;">
                    <a href="<?php echo esc_url('https://wa.me/447474795976'); ?>" target="_blank" rel="noopener noreferrer" class="support-link">
                        <?php echo esc_html('+447474795976'); ?>
                    </a>
                </p>
                <small style="color:#646970;"><?php esc_html_e('Direct messaging & urgent support', 'wpcalibrate-link-repair'); ?></small>
            </div>
        </div>

        <div class="support-privacy-notice" style="padding:12px 16px; background:#f0f6fc; border-left:4px solid #72aee6; border-radius:3px;">
            <p style="margin:0; font-size:13px; color:#2c3338;">
                <strong><?php esc_html_e('Privacy Guarantee:', 'wpcalibrate-link-repair'); ?></strong>
                <?php esc_html_e('WPCalibrate Link Repair performs scanning and repairs strictly from your local server. No website content, visitor information, or scan results are ever transmitted to WPCalibrate or external telemetry servers.', 'wpcalibrate-link-repair'); ?>
            </p>
        </div>
    </div>
</div>
