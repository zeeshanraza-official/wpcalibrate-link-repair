<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Repositories\ScanRepository;

$scanRepo = new ScanRepository();
$scans = $scanRepo->getRecentScans(30);
?>
<div class="wpcalibrate-lr-history-view">
    <h2><?php esc_html_e('Historical Scan Runs', 'wpcalibrate-link-repair'); ?></h2>
    <p class="description">
        <?php esc_html_e('Chronological log of recent manual and scheduled scan cycles. Scans older than your configured retention policy are automatically purged.', 'wpcalibrate-link-repair'); ?>
    </p>

    <div class="table-responsive" style="margin-top: 15px;">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" style="width: 80px;"><?php esc_html_e('Scan #', 'wpcalibrate-link-repair'); ?></th>
                    <th scope="col" style="width: 100px;"><?php esc_html_e('Trigger', 'wpcalibrate-link-repair'); ?></th>
                    <th scope="col" style="width: 120px;"><?php esc_html_e('Status', 'wpcalibrate-link-repair'); ?></th>
                    <th scope="col" style="width: 160px;"><?php esc_html_e('Started At', 'wpcalibrate-link-repair'); ?></th>
                    <th scope="col" style="width: 160px;"><?php esc_html_e('Completed At', 'wpcalibrate-link-repair'); ?></th>
                    <th scope="col" style="width: 100px;"><?php esc_html_e('Checked', 'wpcalibrate-link-repair'); ?></th>
                    <th scope="col" style="width: 90px;"><?php esc_html_e('Broken', 'wpcalibrate-link-repair'); ?></th>
                    <th scope="col" style="width: 90px;"><?php esc_html_e('Redirects', 'wpcalibrate-link-repair'); ?></th>
                    <th scope="col"><?php esc_html_e('Summary', 'wpcalibrate-link-repair'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($scans)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 25px; color: #646970;">
                            <?php esc_html_e('No scan history recorded yet. Run your first scan from the Dashboard.', 'wpcalibrate-link-repair'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($scans as $scan): ?>
                        <tr>
                            <td><strong>#<?php echo (int) $scan->id; ?></strong></td>
                            <td><span class="badge badge-trigger"><?php echo esc_html(ucfirst((string) $scan->trigger_type)); ?></span></td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr((string) $scan->status); ?>">
                                    <?php echo esc_html(ucfirst((string) $scan->status)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html((string) $scan->started_at); ?></td>
                            <td><?php echo esc_html($scan->completed_at ? (string) $scan->completed_at : '—'); ?></td>
                            <td><?php echo (int) $scan->total_checked; ?></td>
                            <td>
                                <?php if ((int) $scan->count_broken > 0): ?>
                                    <span class="count-badge count-broken"><?php echo (int) $scan->count_broken; ?></span>
                                <?php else: ?>
                                    0
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int) $scan->count_redirects; ?></td>
                            <td><?php echo esc_html($scan->error_summary ?: ($scan->current_step ?: __('Completed without errors.', 'wpcalibrate-link-repair'))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
