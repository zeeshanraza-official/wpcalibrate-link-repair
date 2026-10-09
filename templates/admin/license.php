<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\License\LicenseManager;

$provider   = LicenseManager::getProvider();
$maskedKey  = $provider->getMaskedKey();
$status     = $provider->getStatus();
$statusLbl  = $provider->getStatusLabel();
$renewalUrl = $provider->getRenewalUrl();
?>
<div class="wpcalibrate-lr-license-view">
    <div class="wpcalibrate-lr-card" style="max-width: 680px;">
        <h2><?php esc_html_e('License Management', 'wpcalibrate-link-repair'); ?></h2>
        <p class="description">
            <?php esc_html_e('Manage your WPCalibrate product license key for automatic updates and priority support.', 'wpcalibrate-link-repair'); ?>
        </p>

        <div class="license-status-banner status-<?php echo esc_attr($status); ?>" style="margin: 18px 0; padding: 12px 16px; border-left: 4px solid #dba617; background: #fdfaf0;">
            <p style="margin: 0;">
                <strong><?php esc_html_e('Status:', 'wpcalibrate-link-repair'); ?></strong>
                <span class="badge" style="background:#646970; color:#fff; padding:3px 8px; border-radius:3px; font-weight:600; margin-left: 5px;">
                    <?php echo esc_html($statusLbl); ?>
                </span>
            </p>
            <p style="margin: 8px 0 0 0; font-size: 13px; color: #50575e;">
                <?php esc_html_e('Licensing service provider is currently not configured. Full core link scanning and safe repair features remain available in this release. Remote license activation requests will become active once provider credentials are provided.', 'wpcalibrate-link-repair'); ?>
            </p>
        </div>

        <form method="post" action="">
            <?php wp_nonce_field('wpcalibrate_lr_save_license_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="wpcalibrate_lr_license_key"><?php esc_html_e('License Key', 'wpcalibrate-link-repair'); ?></label></th>
                    <td>
                        <input type="text" 
                               name="wpcalibrate_lr_license_key" 
                               id="wpcalibrate_lr_license_key" 
                               value="<?php echo esc_attr($maskedKey); ?>" 
                               class="regular-text code" 
                               placeholder="<?php esc_attr_e('Enter your license key...', 'wpcalibrate-link-repair'); ?>" />
                        <p class="description"><?php esc_html_e('Saved keys are always masked and never displayed in plain text.', 'wpcalibrate-link-repair'); ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit" style="display:flex; gap:10px; align-items:center;">
                <button type="submit" name="wpcalibrate_lr_save_license" class="button button-primary">
                    <?php esc_html_e('Save License Key', 'wpcalibrate-link-repair'); ?>
                </button>
                <button type="button" class="button" disabled title="<?php esc_attr_e('Licensing backend not configured', 'wpcalibrate-link-repair'); ?>">
                    <?php esc_html_e('Activate License', 'wpcalibrate-link-repair'); ?>
                </button>
                <a href="<?php echo esc_url($renewalUrl); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
                    <?php esc_html_e('Visit Marketplace & Store', 'wpcalibrate-link-repair'); ?> &rarr;
                </a>
            </p>
        </form>
    </div>
</div>
