<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use WPCalibrate\LinkRepair\Admin\AdminMenu;

$dashboardUrl   = admin_url('admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=dashboard');
$brokenLinksUrl = admin_url('admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=broken-links');
$redirectsUrl   = admin_url('admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=redirects');
$settingsUrl    = admin_url('admin.php?page=' . AdminMenu::PAGE_SLUG . '&tab=settings');
$iconDark       = WPCALIBRATE_LINK_REPAIR_URL . 'branding/icon-dark.png';
?>

<div class="wpcalibrate-lr-overview-view">
    <!-- Hero / Welcome Banner -->
    <div class="wpcal-hero-banner">
        <div class="wpcal-hero-content">
            <div class="wpcal-hero-badge">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e('Intelligent Link Health & Automation', 'wpcalibrate-link-repair'); ?>
            </div>
            <h2 class="wpcal-hero-title">
                <?php esc_html_e('Welcome to WPCalibrate Link Repair', 'wpcalibrate-link-repair'); ?>
            </h2>
            <p class="wpcal-hero-desc">
                <?php esc_html_e('WPCalibrate Link Repair actively audits your entire WordPress website for broken links, missing media, and redirect chains. It provides robust, revision-safe tools to repair URLs without having to edit individual posts or corrupt page builder layouts.', 'wpcalibrate-link-repair'); ?>
            </p>
            <div class="wpcal-hero-actions">
                <a href="<?php echo esc_url($dashboardUrl); ?>" class="button button-primary button-hero">
                    <span class="dashicons dashicons-dashboard"></span>
                    <?php esc_html_e('Go to Dashboard', 'wpcalibrate-link-repair'); ?>
                </a>
                <a href="<?php echo esc_url($settingsUrl); ?>" class="button button-secondary button-hero">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e('Configure Settings', 'wpcalibrate-link-repair'); ?>
                </a>
            </div>
        </div>
        <div class="wpcal-hero-media">
            <?php if (file_exists(WPCALIBRATE_LINK_REPAIR_DIR . 'branding/icon-dark.png')): ?>
                <img src="<?php echo esc_url($iconDark); ?>" alt="WPCalibrate" class="wpcal-hero-logo" />
            <?php endif; ?>
        </div>
    </div>

    <!-- Purpose & Value Proposition Grid -->
    <div class="wpcal-section-header">
        <span class="wpcal-section-pill"><?php esc_html_e('Core Purpose', 'wpcalibrate-link-repair'); ?></span>
        <h3><?php esc_html_e('Why Link Health Matters for Your Website', 'wpcalibrate-link-repair'); ?></h3>
        <p class="description"><?php esc_html_e('Broken links erode visitor confidence, waste search engine crawl budgets, and hurt organic rankings.', 'wpcalibrate-link-repair'); ?></p>
    </div>

    <div class="wpcal-card-grid wpcal-grid-3">
        <div class="wpcal-card wpcal-feature-card">
            <div class="wpcal-feature-icon wpcal-icon-seo">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <h4><?php esc_html_e('SEO & Crawl Budget Protection', 'wpcalibrate-link-repair'); ?></h4>
            <p><?php esc_html_e('Search engines like Google rank websites with healthy internal architecture higher. Eliminating 404 errors prevents search engine bots from wasting crawl budget on dead ends.', 'wpcalibrate-link-repair'); ?></p>
        </div>

        <div class="wpcal-card wpcal-feature-card">
            <div class="wpcal-feature-icon wpcal-icon-ux">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <h4><?php esc_html_e('Frictionless User Experience', 'wpcalibrate-link-repair'); ?></h4>
            <p><?php esc_html_e('Visitors who encounter broken links or missing download files bounce immediately. Keeping links verified ensures smooth customer journeys and maximizes conversion rates.', 'wpcalibrate-link-repair'); ?></p>
        </div>

        <div class="wpcal-card wpcal-feature-card">
            <div class="wpcal-feature-icon wpcal-icon-perf">
                <span class="dashicons dashicons-performance"></span>
            </div>
            <h4><?php esc_html_e('Redirect Chain Elimination', 'wpcalibrate-link-repair'); ?></h4>
            <p><?php esc_html_e('Redirect chains (301/302 hops) slow down page load times and dilute link equity. WPCalibrate detects chains and lets you resolve them directly to their final destination.', 'wpcalibrate-link-repair'); ?></p>
        </div>
    </div>

    <!-- The 4-Step Method / How to Use -->
    <div class="wpcal-section-header" style="margin-top: 40px;">
        <span class="wpcal-section-pill"><?php esc_html_e('How It Works', 'wpcalibrate-link-repair'); ?></span>
        <h3><?php esc_html_e('The 4-Step Link Repair Method', 'wpcalibrate-link-repair'); ?></h3>
        <p class="description"><?php esc_html_e('An end-to-end automated workflow designed for speed, safety, and zero downtime.', 'wpcalibrate-link-repair'); ?></p>
    </div>

    <div class="wpcal-method-timeline">
        <!-- Step 1 -->
        <div class="wpcal-timeline-item">
            <div class="wpcal-timeline-marker">1</div>
            <div class="wpcal-timeline-card wpcal-card">
                <div class="wpcal-timeline-header">
                    <h4><?php esc_html_e('Step 1: Discover & Crawl', 'wpcalibrate-link-repair'); ?></h4>
                    <span class="wpcal-tag wpcal-tag-blue"><?php esc_html_e('Automated Engine', 'wpcalibrate-link-repair'); ?></span>
                </div>
                <p><?php esc_html_e('Click "Scan Now" or let scheduled scans run in the background. The crawler extracts all unique internal and external URLs, anchor texts, images, and embedded media across posts, pages, Gutenberg blocks, Elementor sections, and WooCommerce products.', 'wpcalibrate-link-repair'); ?></p>
                <div class="wpcal-tip-box">
                    <span class="dashicons dashicons-lightbulb"></span>
                    <span><?php esc_html_e('Scans run in asynchronous micro-batches via Action Scheduler / WP-Cron without exhausting PHP memory or timing out.', 'wpcalibrate-link-repair'); ?></span>
                </div>
            </div>
        </div>

        <!-- Step 2 -->
        <div class="wpcal-timeline-item">
            <div class="wpcal-timeline-marker">2</div>
            <div class="wpcal-timeline-card wpcal-card">
                <div class="wpcal-timeline-header">
                    <h4><?php esc_html_e('Step 2: Validate & Classify', 'wpcalibrate-link-repair'); ?></h4>
                    <span class="wpcal-tag wpcal-tag-purple"><?php esc_html_e('Smart HTTP Engine', 'wpcalibrate-link-repair'); ?></span>
                </div>
                <p><?php esc_html_e('The validator tests each unique URL using fast, lightweight HTTP HEAD requests (falling back to streaming GET if HEAD is forbidden). Statuses are categorized precisely:', 'wpcalibrate-link-repair'); ?></p>
                <ul class="wpcal-status-list">
                    <li><strong class="text-danger">404 / 410:</strong> <?php esc_html_e('Broken URLs and permanently gone resources requiring repair.', 'wpcalibrate-link-repair'); ?></li>
                    <li><strong class="text-warning">301 / 302:</strong> <?php esc_html_e('Permanent and temporary redirects pointing to new addresses.', 'wpcalibrate-link-repair'); ?></li>
                    <li><strong class="text-warning">500 / 503 / Timeout:</strong> <?php esc_html_e('Server warnings rechecked with exponential backoff.', 'wpcalibrate-link-repair'); ?></li>
                    <li><strong class="text-success">200 OK:</strong> <?php esc_html_e('Healthy URLs validated and marked operational.', 'wpcalibrate-link-repair'); ?></li>
                </ul>
            </div>
        </div>

        <!-- Step 3 -->
        <div class="wpcal-timeline-item">
            <div class="wpcal-timeline-marker">3</div>
            <div class="wpcal-timeline-card wpcal-card">
                <div class="wpcal-timeline-header">
                    <h4><?php esc_html_e('Step 3: Review Findings in Dashboard', 'wpcalibrate-link-repair'); ?></h4>
                    <span class="wpcal-tag wpcal-tag-teal"><?php esc_html_e('Centralized Review', 'wpcalibrate-link-repair'); ?></span>
                </div>
                <p><?php esc_html_e('Review findings organized into dedicated tabs with search, internal/external filters, and status filters. Expand any URL to see exactly where it appears on your website (post title, post ID, content type, and anchor text).', 'wpcalibrate-link-repair'); ?></p>
                <p>
                    <a href="<?php echo esc_url($brokenLinksUrl); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-warning"></span> <?php esc_html_e('View Broken Links Table', 'wpcalibrate-link-repair'); ?>
                    </a>
                    <a href="<?php echo esc_url($redirectsUrl); ?>" class="button button-secondary" style="margin-left: 8px;">
                        <span class="dashicons dashicons-redo"></span> <?php esc_html_e('View Redirects Table', 'wpcalibrate-link-repair'); ?>
                    </a>
                </p>
            </div>
        </div>

        <!-- Step 4 -->
        <div class="wpcal-timeline-item">
            <div class="wpcal-timeline-marker">4</div>
            <div class="wpcal-timeline-card wpcal-card">
                <div class="wpcal-timeline-header">
                    <h4><?php esc_html_e('Step 4: Non-Destructive Repair', 'wpcalibrate-link-repair'); ?></h4>
                    <span class="wpcal-tag wpcal-tag-green"><?php esc_html_e('Safe Execution', 'wpcalibrate-link-repair'); ?></span>
                </div>
                <p><?php esc_html_e('Select the repair tool best suited for each situation:', 'wpcalibrate-link-repair'); ?></p>
                <div class="wpcal-tools-subgrid">
                    <div class="wpcal-tool-box">
                        <span class="dashicons dashicons-edit"></span>
                        <strong><?php esc_html_e('Inline Edit', 'wpcalibrate-link-repair'); ?></strong>
                        <small><?php esc_html_e('Replace an individual broken link with a new URL without opening the post editor.', 'wpcalibrate-link-repair'); ?></small>
                    </div>
                    <div class="wpcal-tool-box">
                        <span class="dashicons dashicons-admin-site-alt3"></span>
                        <strong><?php esc_html_e('Bulk Replacement', 'wpcalibrate-link-repair'); ?></strong>
                        <small><?php esc_html_e('Replace all instances of an old or renamed URL across the entire database with preview.', 'wpcalibrate-link-repair'); ?></small>
                    </div>
                    <div class="wpcal-tool-box">
                        <span class="dashicons dashicons-editor-unlink"></span>
                        <strong><?php esc_html_e('Clean Unlink', 'wpcalibrate-link-repair'); ?></strong>
                        <small><?php esc_html_e('Remove the broken hyperlink tag while preserving the anchor text cleanly.', 'wpcalibrate-link-repair'); ?></small>
                    </div>
                    <div class="wpcal-tool-box">
                        <span class="dashicons dashicons-update"></span>
                        <strong><?php esc_html_e('Auto-Resolve Redirects', 'wpcalibrate-link-repair'); ?></strong>
                        <small><?php esc_html_e('Update links directly to their final 301 destination to bypass redirect overhead.', 'wpcalibrate-link-repair'); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Supported Content Providers Grid -->
    <div class="wpcal-section-header" style="margin-top: 40px;">
        <span class="wpcal-section-pill"><?php esc_html_e('Compatibility', 'wpcalibrate-link-repair'); ?></span>
        <h3><?php esc_html_e('Supported Content Sources & Builders', 'wpcalibrate-link-repair'); ?></h3>
        <p class="description"><?php esc_html_e('Comprehensive scanning and surgical repair across all WordPress content formats.', 'wpcalibrate-link-repair'); ?></p>
    </div>

    <div class="wpcal-card-grid wpcal-grid-4">
        <div class="wpcal-card wpcal-source-card">
            <span class="dashicons dashicons-admin-post"></span>
            <h5><?php esc_html_e('Posts, Pages & CPTs', 'wpcalibrate-link-repair'); ?></h5>
            <p><?php esc_html_e('Standard Classic & Block editor content, excerpts, and custom post types.', 'wpcalibrate-link-repair'); ?></p>
        </div>

        <div class="wpcal-card wpcal-source-card">
            <span class="dashicons dashicons-block-default"></span>
            <h5><?php esc_html_e('Gutenberg Blocks', 'wpcalibrate-link-repair'); ?></h5>
            <p><?php esc_html_e('Core buttons, navigation blocks, HTML blocks, paragraphs, and media embeds.', 'wpcalibrate-link-repair'); ?></p>
        </div>

        <div class="wpcal-card wpcal-source-card">
            <span class="dashicons dashicons-layout"></span>
            <h5><?php esc_html_e('Elementor Page Builder', 'wpcalibrate-link-repair'); ?></h5>
            <p><?php esc_html_e('Deep JSON traversal of button widgets, text widgets, icon boxes, and cache purging.', 'wpcalibrate-link-repair'); ?></p>
        </div>

        <div class="wpcal-card wpcal-source-card">
            <span class="dashicons dashicons-cart"></span>
            <h5><?php esc_html_e('WooCommerce', 'wpcalibrate-link-repair'); ?></h5>
            <p><?php esc_html_e('Product descriptions, short descriptions, image links, and full HPOS compatibility.', 'wpcalibrate-link-repair'); ?></p>
        </div>
    </div>

    <!-- Safety & Integrity Guarantees Card -->
    <div class="wpcal-card wpcal-safety-card" style="margin-top: 30px;">
        <div class="wpcal-safety-header">
            <span class="dashicons dashicons-shield"></span>
            <div>
                <h4 style="margin: 0 0 4px 0;"><?php esc_html_e('Safety & Data Integrity Guarantees', 'wpcalibrate-link-repair'); ?></h4>
                <p style="margin: 0; color: #646970;"><?php esc_html_e('Built from the ground up to guarantee zero corruption of your website content.', 'wpcalibrate-link-repair'); ?></p>
            </div>
        </div>
        <div class="wpcal-safety-grid">
            <div class="wpcal-safety-item">
                <strong><span class="dashicons dashicons-backup"></span> <?php esc_html_e('Revision Preserving', 'wpcalibrate-link-repair'); ?></strong>
                <p><?php esc_html_e('All content repairs utilize standard WordPress revision hooks, allowing you to rollback changes at any time via the post revision history.', 'wpcalibrate-link-repair'); ?></p>
            </div>
            <div class="wpcal-safety-item">
                <strong><span class="dashicons dashicons-lock"></span> <?php esc_html_e('SSRF Intranet Protection', 'wpcalibrate-link-repair'); ?></strong>
                <p><?php esc_html_e('Strictly blocks scanning of private subnets, cloud metadata IPs (169.254.x.x), loopbacks, and local services to safeguard internal security.', 'wpcalibrate-link-repair'); ?></p>
            </div>
            <div class="wpcal-safety-item">
                <strong><span class="dashicons dashicons-database"></span> <?php esc_html_e('No Blind SQL String Replaces', 'wpcalibrate-link-repair'); ?></strong>
                <p><?php esc_html_e('Structured data (such as Elementor postmeta) is safely parsed as structured objects, never mangling PHP serialized string lengths.', 'wpcalibrate-link-repair'); ?></p>
            </div>
            <div class="wpcal-safety-item">
                <strong><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('Strict Access Control', 'wpcalibrate-link-repair'); ?></strong>
                <p><?php esc_html_e('All endpoints and operations require manage_options capabilities and rigorous cryptographic nonce verification.', 'wpcalibrate-link-repair'); ?></p>
            </div>
        </div>
    </div>

    <!-- Recommended Best Practices -->
    <div class="wpcal-card" style="margin-top: 24px; padding: 24px;">
        <h4 style="margin-top: 0;"><?php esc_html_e('Recommended Best Practices', 'wpcalibrate-link-repair'); ?></h4>
        <ol style="margin-bottom: 0; padding-left: 20px; line-height: 1.8;">
            <li><strong><?php esc_html_e('Schedule Weekly Scans:', 'wpcalibrate-link-repair'); ?></strong> <?php esc_html_e('External websites frequently change URLs or shut down. Enable weekly scans in Settings to catch newly broken links promptly.', 'wpcalibrate-link-repair'); ?></li>
            <li><strong><?php esc_html_e('Resolve Redirects to Final URLs:', 'wpcalibrate-link-repair'); ?></strong> <?php esc_html_e('Updating internal links directly to canonical destinations reduces server roundtrips and optimizes mobile page load times.', 'wpcalibrate-link-repair'); ?></li>
            <li><strong><?php esc_html_e('Configure Domain Whitelists:', 'wpcalibrate-link-repair'); ?></strong> <?php esc_html_e('If you link to external sites that enforce strict anti-bot firewalls, add their domains to the Whitelist in Settings to prevent false alerts.', 'wpcalibrate-link-repair'); ?></li>
        </ol>
    </div>
</div>
