<?php
declare(strict_types=1);

namespace WPCalibrate\LinkRepair\Notifications;

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Services\SettingsService;
use WPCalibrate\LinkRepair\Services\Logger;

/**
 * Sends summary email notifications after scan completion.
 */
final class EmailNotifier
{
    /**
     * Send email report for a completed scan.
     *
     * @param object $scan
     * @return bool
     */
    public static function sendReport(object $scan): bool
    {
        $enabled = (bool) SettingsService::get('email_reports_enabled', false);
        if (!$enabled) {
            return false;
        }

        $recipient = (string) SettingsService::get('email_recipient', get_option('admin_email'));
        if (!is_email($recipient)) {
            return false;
        }

        $onlyBroken = (bool) SettingsService::get('email_only_broken', true);
        $brokenCount = (int) ($scan->count_broken ?? 0);

        // If only_broken is set and there are no broken links, check all_clear
        if ($onlyBroken && $brokenCount === 0) {
            $allClear = (bool) SettingsService::get('email_all_clear', false);
            if (!$allClear) {
                return false;
            }
        }

        $siteName = get_bloginfo('name');
        $dashboardUrl = admin_url('admin.php?page=wpcalibrate-link-repair');

        $subject = sprintf(
            /* translators: 1: site name, 2: broken count */
            __('[%1$s] Link Repair Scan Complete: %2$d Broken Links Found', 'wpcalibrate-link-repair'),
            $siteName,
            $brokenCount
        );

        $body  = sprintf(__("Hello,\n\nA link scan has completed on %s.\n\n", 'wpcalibrate-link-repair'), $siteName);
        $body .= sprintf(__("Scan Summary:\n", 'wpcalibrate-link-repair'));
        $body .= sprintf(__("- Total Checked: %d\n", 'wpcalibrate-link-repair'), (int) ($scan->total_checked ?? 0));
        $body .= sprintf(__("- Broken Links: %d\n", 'wpcalibrate-link-repair'), $brokenCount);
        $body .= sprintf(__("- Redirects: %d\n", 'wpcalibrate-link-repair'), (int) ($scan->count_redirects ?? 0));
        $body .= sprintf(__("- Warnings / Temporary: %d\n", 'wpcalibrate-link-repair'), (int) ($scan->count_warnings ?? 0));
        $body .= sprintf(__("- Working Links: %d\n\n", 'wpcalibrate-link-repair'), (int) ($scan->count_working ?? 0));
        $body .= sprintf(__("Review and repair links in your dashboard:\n%s\n\n", 'wpcalibrate-link-repair'), $dashboardUrl);
        $body .= __("Regards,\nWPCalibrate Link Repair", 'wpcalibrate-link-repair');

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        try {
            $sent = wp_mail($recipient, $subject, $body, $headers);
            if ($sent) {
                Logger::info("Scan report email dispatched to {$recipient}.");
            } else {
                Logger::warning("wp_mail failed to send report to {$recipient}.");
            }
            return $sent;
        } catch (\Throwable $e) {
            Logger::error('Exception sending scan email: ' . $e->getMessage());
            return false;
        }
    }
}
