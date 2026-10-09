<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Services\SettingsService;

$settings = SettingsService::getAll();
$publicPostTypes = get_post_types(['public' => true], 'objects');
?>
<div class="wpcalibrate-lr-settings-view">
    <form method="post" action="">
        <?php wp_nonce_field('wpcalibrate_lr_save_settings_nonce'); ?>

        <div class="settings-section">
            <h2><?php esc_html_e('Content Discovery Sources', 'wpcalibrate-link-repair'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Post Types to Scan', 'wpcalibrate-link-repair'); ?></th>
                    <td>
                        <fieldset>
                            <?php foreach ($publicPostTypes as $ptKey => $ptObj): ?>
                                <?php if ($ptKey === 'attachment') continue; ?>
                                <label style="margin-right: 15px; display: inline-block;">
                                    <input type="checkbox" name="wpcalibrate_lr_settings[scan_post_types][]" value="<?php echo esc_attr($ptKey); ?>" 
                                        <?php checked(in_array($ptKey, (array) ($settings['scan_post_types'] ?? []), true)); ?> />
                                    <?php echo esc_html($ptObj->labels->name); ?> (<code><?php echo esc_html($ptKey); ?></code>)
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="description"><?php esc_html_e('Select which post types are analyzed during scanning.', 'wpcalibrate-link-repair'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Link Categories', 'wpcalibrate-link-repair'); ?></th>
                    <td>
                        <fieldset>
                            <label style="display:block; margin-bottom: 6px;">
                                <input type="checkbox" name="wpcalibrate_lr_settings[scan_internal_links]" value="1" <?php checked(!empty($settings['scan_internal_links'])); ?> />
                                <?php esc_html_e('Scan internal links pointing within this website', 'wpcalibrate-link-repair'); ?>
                            </label>
                            <label style="display:block; margin-bottom: 6px;">
                                <input type="checkbox" name="wpcalibrate_lr_settings[scan_external_links]" value="1" <?php checked(!empty($settings['scan_external_links'])); ?> />
                                <?php esc_html_e('Scan outbound links to external domains', 'wpcalibrate-link-repair'); ?>
                            </label>
                            <label style="display:block;">
                                <input type="checkbox" name="wpcalibrate_lr_settings[scan_media]" value="1" <?php checked(!empty($settings['scan_media'])); ?> />
                                <?php esc_html_e('Scan images and embedded media (<img src>, <video>, <audio>)', 'wpcalibrate-link-repair'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Custom Fields (Postmeta)', 'wpcalibrate-link-repair'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpcalibrate_lr_settings[scan_custom_fields]" value="1" id="toggle-custom-fields" <?php checked(!empty($settings['scan_custom_fields'])); ?> />
                            <?php esc_html_e('Enable scanning of administrator-specified custom postmeta fields', 'wpcalibrate-link-repair'); ?>
                        </label>
                        <p class="description" style="color: #b32d2e;">
                            <strong><?php esc_html_e('Warning:', 'wpcalibrate-link-repair'); ?></strong> <?php esc_html_e('Scanning large numbers of unstructured meta fields can increase scanning time. Only specify keys that contain URLs or HTML content.', 'wpcalibrate-link-repair'); ?>
                        </p>
                        <div id="custom-fields-keys-wrap" style="margin-top: 10px;">
                            <label for="custom_field_keys"><strong><?php esc_html_e('Custom Field Keys (one per line):', 'wpcalibrate-link-repair'); ?></strong></label><br />
                            <textarea name="wpcalibrate_lr_settings[custom_field_keys]" id="custom_field_keys" rows="3" class="large-text code"><?php echo esc_textarea((string) ($settings['custom_field_keys'] ?? '')); ?></textarea>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="settings-section">
            <h2><?php esc_html_e('Scheduled Scanning', 'wpcalibrate-link-repair'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="schedule_frequency"><?php esc_html_e('Scan Frequency', 'wpcalibrate-link-repair'); ?></label></th>
                    <td>
                        <select name="wpcalibrate_lr_settings[schedule_frequency]" id="schedule_frequency">
                            <option value="disabled" <?php selected($settings['schedule_frequency'], 'disabled'); ?>><?php esc_html_e('Disabled (Manual Scans Only)', 'wpcalibrate-link-repair'); ?></option>
                            <option value="daily" <?php selected($settings['schedule_frequency'], 'daily'); ?>><?php esc_html_e('Daily', 'wpcalibrate-link-repair'); ?></option>
                            <option value="weekly" <?php selected($settings['schedule_frequency'], 'weekly'); ?>><?php esc_html_e('Weekly', 'wpcalibrate-link-repair'); ?></option>
                            <option value="monthly" <?php selected($settings['schedule_frequency'], 'monthly'); ?>><?php esc_html_e('Monthly', 'wpcalibrate-link-repair'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Recurring background scans run using your server background scheduler.', 'wpcalibrate-link-repair'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="schedule_time"><?php esc_html_e('Preferred Time (UTC)', 'wpcalibrate-link-repair'); ?></label></th>
                    <td>
                        <input type="text" name="wpcalibrate_lr_settings[schedule_time]" id="schedule_time" value="<?php echo esc_attr((string) ($settings['schedule_time'] ?? '03:00')); ?>" class="small-text" pattern="^(?:[01]\d|2[0-3]):[0-5]\d$" placeholder="03:00" />
                        <p class="description"><?php esc_html_e('24-hour UTC format (e.g. 03:00 for off-peak hours).', 'wpcalibrate-link-repair'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="settings-section">
            <h2><?php esc_html_e('Performance & Rate Limiting', 'wpcalibrate-link-repair'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="batch_size"><?php esc_html_e('Batch Size', 'wpcalibrate-link-repair'); ?></label></th>
                    <td>
                        <input type="number" name="wpcalibrate_lr_settings[batch_size]" id="batch_size" min="5" max="100" value="<?php echo (int) ($settings['batch_size'] ?? 25); ?>" class="small-text" />
                        <p class="description"><?php esc_html_e('Number of items processed per batch cycle (5 to 100). Default is 25.', 'wpcalibrate-link-repair'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="request_timeout"><?php esc_html_e('Request Timeout (seconds)', 'wpcalibrate-link-repair'); ?></label></th>
                    <td>
                        <input type="number" name="wpcalibrate_lr_settings[request_timeout]" id="request_timeout" min="3" max="30" value="<?php echo (int) ($settings['request_timeout'] ?? 10); ?>" class="small-text" />
                        <p class="description"><?php esc_html_e('Seconds to wait before classifying an unreachable URL as timed out (3 to 30s).', 'wpcalibrate-link-repair'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="retry_count"><?php esc_html_e('Maximum Retries', 'wpcalibrate-link-repair'); ?></label></th>
                    <td>
                        <input type="number" name="wpcalibrate_lr_settings[retry_count]" id="retry_count" min="0" max="3" value="<?php echo (int) ($settings['retry_count'] ?? 2); ?>" class="small-text" />
                        <p class="description"><?php esc_html_e('Number of times to retry ambiguous timeouts or 5xx server errors before recording a warning (0 to 3).', 'wpcalibrate-link-repair'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="max_redirects"><?php esc_html_e('Maximum Redirect Depth', 'wpcalibrate-link-repair'); ?></label></th>
                    <td>
                        <input type="number" name="wpcalibrate_lr_settings[max_redirects]" id="max_redirects" min="1" max="10" value="<?php echo (int) ($settings['max_redirects'] ?? 5); ?>" class="small-text" />
                        <p class="description"><?php esc_html_e('Maximum number of redirect hops to follow (1 to 10). Default is 5.', 'wpcalibrate-link-repair'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="request_delay_ms"><?php esc_html_e('Global Request Delay (ms)', 'wpcalibrate-link-repair'); ?></label></th>
                    <td>
                        <input type="number" name="wpcalibrate_lr_settings[request_delay_ms]" id="request_delay_ms" min="0" max="2000" step="50" value="<?php echo (int) ($settings['request_delay_ms'] ?? 100); ?>" class="small-text" />
                        <p class="description"><?php esc_html_e('Delay in milliseconds between outbound checks (0 to 2000ms). Prevents server overloading.', 'wpcalibrate-link-repair'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="per_host_delay_ms"><?php esc_html_e('Per-Host Throttling (ms)', 'wpcalibrate-link-repair'); ?></label></th>
                    <td>
                        <input type="number" name="wpcalibrate_lr_settings[per_host_delay_ms]" id="per_host_delay_ms" min="0" max="3000" step="50" value="<?php echo (int) ($settings['per_host_delay_ms'] ?? 200); ?>" class="small-text" />
                        <p class="description"><?php esc_html_e('Minimum spacing between requests to the identical external host (0 to 3000ms). Avoids triggering external rate limits.', 'wpcalibrate-link-repair'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="settings-section">
            <h2><?php esc_html_e('Exclusions & Ignore Rules', 'wpcalibrate-link-repair'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="excluded_urls"><?php esc_html_e('Excluded URL Patterns', 'wpcalibrate-link-repair'); ?></label></th>
                    <td>
                        <textarea name="wpcalibrate_lr_settings[excluded_urls]" id="excluded_urls" rows="4" class="large-text code"><?php echo esc_textarea((string) ($settings['excluded_urls'] ?? '')); ?></textarea>
                        <p class="description"><?php esc_html_e('One pattern per line. URLs containing these strings or matching wildcards (e.g. *facebook.com/sharer*) will be excluded from scanning.', 'wpcalibrate-link-repair'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="excluded_domains"><?php esc_html_e('Excluded Domains', 'wpcalibrate-link-repair'); ?></label></th>
                    <td>
                        <textarea name="wpcalibrate_lr_settings[excluded_domains]" id="excluded_domains" rows="3" class="large-text code"><?php echo esc_textarea((string) ($settings['excluded_domains'] ?? '')); ?></textarea>
                        <p class="description"><?php esc_html_e('One domain per line (e.g. google.com, example.org). Outbound checks to these domains will be skipped.', 'wpcalibrate-link-repair'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="settings-section">
            <h2><?php esc_html_e('Email Notifications', 'wpcalibrate-link-repair'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable Email Reports', 'wpcalibrate-link-repair'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wpcalibrate_lr_settings[email_reports_enabled]" value="1" <?php checked(!empty($settings['email_reports_enabled'])); ?> />
                            <?php esc_html_e('Send an email summary after scheduled scan completion', 'wpcalibrate-link-repair'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="email_recipient"><?php esc_html_e('Recipient Email', 'wpcalibrate-link-repair'); ?></label></th>
                    <td>
                        <input type="email" name="wpcalibrate_lr_settings[email_recipient]" id="email_recipient" value="<?php echo esc_attr((string) ($settings['email_recipient'] ?? get_option('admin_email'))); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Notification Rules', 'wpcalibrate-link-repair'); ?></th>
                    <td>
                        <label style="display:block; margin-bottom: 6px;">
                            <input type="checkbox" name="wpcalibrate_lr_settings[email_only_broken]" value="1" <?php checked(!empty($settings['email_only_broken'])); ?> />
                            <?php esc_html_e('Only notify if broken links are detected', 'wpcalibrate-link-repair'); ?>
                        </label>
                        <label style="display:block;">
                            <input type="checkbox" name="wpcalibrate_lr_settings[email_all_clear]" value="1" <?php checked(!empty($settings['email_all_clear'])); ?> />
                            <?php esc_html_e('Send "All Clear" confirmation report when no broken links are found', 'wpcalibrate-link-repair'); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <div class="settings-section">
            <h2><?php esc_html_e('Data Retention & Clean Uninstall', 'wpcalibrate-link-repair'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="retention_days"><?php esc_html_e('Retention Period (Days)', 'wpcalibrate-link-repair'); ?></label></th>
                    <td>
                        <input type="number" name="wpcalibrate_lr_settings[retention_days]" id="retention_days" min="7" max="365" value="<?php echo (int) ($settings['retention_days'] ?? 90); ?>" class="small-text" />
                        <p class="description"><?php esc_html_e('Number of days to preserve historical scan run records (7 to 365 days). Default is 90.', 'wpcalibrate-link-repair'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Uninstall Clean-up', 'wpcalibrate-link-repair'); ?></th>
                    <td>
                        <label style="color: #b32d2e;">
                            <input type="checkbox" name="wpcalibrate_lr_settings[delete_data_on_uninstall]" value="1" <?php checked(!empty($settings['delete_data_on_uninstall'])); ?> />
                            <strong><?php esc_html_e('Delete all plugin tables, settings, and scan records on plugin uninstall', 'wpcalibrate-link-repair'); ?></strong>
                        </label>
                        <p class="description"><?php esc_html_e('Disabled by default to prevent accidental data loss. When checked, deleting the plugin completely removes all WPCalibrate Link Repair custom database tables and options.', 'wpcalibrate-link-repair'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" name="wpcalibrate_lr_save_settings" class="button button-primary button-hero">
                <?php esc_html_e('Save Changes', 'wpcalibrate-link-repair'); ?>
            </button>
        </p>
    </form>
</div>
