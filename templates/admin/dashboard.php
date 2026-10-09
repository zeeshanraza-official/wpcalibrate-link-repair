<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Dashboard Tab Template.
 *
 * @var array<string, int> $counts
 * @var object|null $activeScan
 * @var object|null $latestScan
 */

$isRunning = $activeScan && $activeScan->status === 'running';
$isPaused  = $activeScan && $activeScan->status === 'paused';
$scanStatus = $activeScan ? $activeScan->status : ($latestScan ? $latestScan->status : 'idle');
?>
<div class="wpcalibrate-lr-dashboard">
    <!-- Active Scan Monitor -->
    <div class="wpcalibrate-lr-card wpcalibrate-lr-monitor-card" id="wpcalibrate-lr-monitor">
        <div class="monitor-header">
            <div>
                <h2><?php esc_html_e('Scan Monitor', 'wpcalibrate-link-repair'); ?></h2>
                <p class="monitor-status-text">
                    <span class="status-indicator status-<?php echo esc_attr($scanStatus); ?>" id="monitor-indicator"></span>
                    <strong id="monitor-status-label"><?php echo esc_html(ucfirst($scanStatus)); ?></strong>
                    <span id="monitor-step-text" class="monitor-step">
                        <?php echo esc_html($activeScan->current_step ?? ($latestScan ? __('Last scan completed.', 'wpcalibrate-link-repair') : __('No scan in progress.', 'wpcalibrate-link-repair'))); ?>
                    </span>
                </p>
            </div>
            <div class="monitor-controls">
                <button type="button" class="button button-primary" id="btn-start-scan" <?php echo ($isRunning || $isPaused) ? 'style="display:none;"' : ''; ?>>
                    <span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Start Scan', 'wpcalibrate-link-repair'); ?>
                </button>
                <button type="button" class="button" id="btn-pause-scan" <?php echo !$isRunning ? 'style="display:none;"' : ''; ?>>
                    <span class="dashicons dashicons-controls-pause"></span> <?php esc_html_e('Pause', 'wpcalibrate-link-repair'); ?>
                </button>
                <button type="button" class="button button-primary" id="btn-resume-scan" <?php echo !$isPaused ? 'style="display:none;"' : ''; ?>>
                    <span class="dashicons dashicons-controls-play"></span> <?php esc_html_e('Resume', 'wpcalibrate-link-repair'); ?>
                </button>
                <button type="button" class="button button-link-delete" id="btn-cancel-scan" <?php echo (!$isRunning && !$isPaused) ? 'style="display:none;"' : ''; ?>>
                    <span class="dashicons dashicons-no"></span> <?php esc_html_e('Cancel', 'wpcalibrate-link-repair'); ?>
                </button>
            </div>
        </div>

        <div class="progress-bar-wrap" id="monitor-progress-wrap" <?php echo (!$isRunning && !$isPaused) ? 'style="display:none;"' : ''; ?>>
            <div class="progress-bar-fill" id="monitor-progress-fill" style="width: 0%;"></div>
        </div>

        <div class="monitor-meta">
            <span><strong><?php esc_html_e('Last Scan Started:', 'wpcalibrate-link-repair'); ?></strong> 
                <span id="monitor-last-started"><?php echo esc_html($latestScan->started_at ?? __('Never', 'wpcalibrate-link-repair')); ?></span>
            </span>
            <span><strong><?php esc_html_e('Last Scan Completed:', 'wpcalibrate-link-repair'); ?></strong> 
                <span id="monitor-last-completed"><?php echo esc_html($latestScan->completed_at ?? __('N/A', 'wpcalibrate-link-repair')); ?></span>
            </span>
            <span><strong><?php esc_html_e('Next Scheduled Scan:', 'wpcalibrate-link-repair'); ?></strong> 
                <?php
                $next = wp_next_scheduled(\WPCalibrate\LinkRepair\Scanner\ScanEngine::SCHEDULED_HOOK);
                echo esc_html($next ? get_date_from_gmt(gmdate('Y-m-d H:i:s', $next), 'Y-m-d H:i:s') : __('Disabled', 'wpcalibrate-link-repair'));
                ?>
            </span>
        </div>
    </div>

    <!-- Metric Cards Grid -->
    <div class="wpcalibrate-lr-stats-grid">
        <div class="stat-card stat-broken">
            <div class="stat-icon"><span class="dashicons dashicons-warning"></span></div>
            <div class="stat-content">
                <span class="stat-number" id="count-broken"><?php echo (int) ($counts['broken'] ?? 0); ?></span>
                <span class="stat-label"><?php esc_html_e('Broken Links (404/410)', 'wpcalibrate-link-repair'); ?></span>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpcalibrate-link-repair&tab=broken-links')); ?>" class="stat-link">
                <?php esc_html_e('View & Repair', 'wpcalibrate-link-repair'); ?> &rarr;
            </a>
        </div>

        <div class="stat-card stat-redirect">
            <div class="stat-icon"><span class="dashicons dashicons-redo"></span></div>
            <div class="stat-content">
                <span class="stat-number" id="count-redirect"><?php echo (int) ($counts['redirect'] ?? 0); ?></span>
                <span class="stat-label"><?php esc_html_e('Redirects (3xx)', 'wpcalibrate-link-repair'); ?></span>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpcalibrate-link-repair&tab=redirects')); ?>" class="stat-link">
                <?php esc_html_e('Review Redirects', 'wpcalibrate-link-repair'); ?> &rarr;
            </a>
        </div>

        <div class="stat-card stat-warning">
            <div class="stat-icon"><span class="dashicons dashicons-flag"></span></div>
            <div class="stat-content">
                <span class="stat-number" id="count-warning"><?php echo (int) ($counts['warning'] ?? 0); ?></span>
                <span class="stat-label"><?php esc_html_e('Warnings / Retries', 'wpcalibrate-link-repair'); ?></span>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpcalibrate-link-repair&tab=broken-links&filter=warning')); ?>" class="stat-link">
                <?php esc_html_e('Investigate', 'wpcalibrate-link-repair'); ?> &rarr;
            </a>
        </div>

        <div class="stat-card stat-working">
            <div class="stat-icon"><span class="dashicons dashicons-yes-alt"></span></div>
            <div class="stat-content">
                <span class="stat-number" id="count-working"><?php echo (int) ($counts['working'] ?? 0); ?></span>
                <span class="stat-label"><?php esc_html_e('Working Links', 'wpcalibrate-link-repair'); ?></span>
            </div>
            <span class="stat-meta"><?php esc_html_e('Healthy URLs verified', 'wpcalibrate-link-repair'); ?></span>
        </div>

        <div class="stat-card stat-total">
            <div class="stat-icon"><span class="dashicons dashicons-admin-links"></span></div>
            <div class="stat-content">
                <span class="stat-number" id="count-total"><?php echo (int) ($counts['total'] ?? 0); ?></span>
                <span class="stat-label"><?php esc_html_e('Total URLs Tracked', 'wpcalibrate-link-repair'); ?></span>
            </div>
            <span class="stat-meta"><?php echo sprintf(esc_html__('%d ignored', 'wpcalibrate-link-repair'), (int) ($counts['ignored'] ?? 0)); ?></span>
        </div>
    </div>
</div>
