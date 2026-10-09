# WPCalibrate Link Repair

[![WordPress](https://img.shields.io/badge/WordPress-6.2%20to%207.1%2B-blue.svg)](https://wordpress.org)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%20to%208.3%2B-purple.svg)](https://php.net)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Release](https://img.shields.io/badge/Release-v1.0.0-brightgreen.svg)](https://github.com/zeeshanraza-official/wpcalibrate-link-repair/releases)

> **WPCalibrate Link Repair** is a production-ready WordPress plugin that scans your site for broken internal links, dead external links, redirect loops, and missing media, providing safe administrator tools to review and repair them without manual editing.

---

## Table of Contents
- [About](#about)
- [Key Features](#key-features)
- [Documentation & How to Use](#documentation--how-to-use)
  - [Installation](#installation)
  - [Updating from WordPress Dashboard](#updating-from-wordpress-dashboard)
  - [4-Step Repair Workflow](#4-step-repair-workflow)
  - [Settings & Customization](#settings--customization)
  - [Supported Content Sources](#supported-content-sources)
  - [Safety & Data Integrity Guarantees](#safety--data-integrity-guarantees)
- [Developer Hooks & Filters](#developer-hooks--filters)
- [Changelog](#changelog)
- [Support & Contributing](#support--contributing)
- [License](#license)

---

## About

Broken links harm search engine crawl efficiency, lower domain rankings, and frustrate site visitors with 404 errors. Finding and fixing dead links across hundreds or thousands of blog posts, pages, widgets, and page builders usually takes dozens of manual hours.

**WPCalibrate Link Repair** automates this entire lifecycle:
1. It crawls and catalogs all URLs across standard posts, Gutenberg blocks, classic editor content, navigation menus, widget blocks, custom fields, Elementor page builder data, and WooCommerce products.
2. It tests every URL via lightweight, streaming HTTP checks with rate-limiting, SSRF protection, and retry policies.
3. It displays broken links, timeouts, and redirect chains in a unified, searchable admin dashboard.
4. It provides non-destructive, revision-friendly tools to repair, bulk replace, or unlink dead links in one click.

---

## Key Features

### 🔍 Deep Content Discovery
- **Comprehensive Scanning:** Discovers links in posts, pages, custom post types, classic HTML, Gutenberg blocks, nav menus, widget blocks, and post meta.
- **Elementor Deep Integration:** Recursively parses Elementor's `_elementor_data` JSON schema to safely inspect button links, headings, icon boxes, text editors, and custom widgets without raw SQL corruption.
- **WooCommerce Ready:** Declares native High-Performance Order Storage (HPOS) compatibility and inspects product long/short descriptions.
- **Smart URL Normalization:** Canonicalizes scheme/host, resolves relative paths, handles protocol-relative links, strips tracking fragments, and ignores `mailto:`, `tel:`, and `javascript:` URIs.

### ⚡ Resilient HTTP Validation Engine
- **Streaming & Lightweight:** Issues rapid `HEAD` requests first, falling back to body-capped `GET` requests (max 2KB stream) if a server rejects `HEAD`.
- **SSRF Attack Protection:** Actively blocks private IPv4 subnets (`127.0.0.0/8`, `10.0.0.0/8`, `192.168.0.0/16`, `172.16.0.0/12`), cloud metadata endpoints (`169.254.169.254`), and IPv6 loopbacks, while safely exempting local internal multisite URLs.
- **Redirect Chain & Loop Detection:** Follows redirects up to 5 hops, detecting circular 301/302 redirect loops and logging destination endpoints.
- **Strict Monotonic Cursor Traversal:** Employs primary-key cursor pagination (`WHERE id > $lastId`) ensuring that each unique link is validated exactly once per scan with zero infinite loops.

### 🛠️ Safe & Non-Destructive Repair
- **Single Occurrence Edit:** Update a broken URL for a specific post without touching others.
- **Bulk Exact Replacement:** Replace dead URLs site-wide with instant preview and occurrence count verification.
- **Safe HTML Unlink:** Remove broken `<a>` tags while keeping anchor text and inner layout completely intact.
- **Recheck Queue:** Instantly re-test individual URLs on demand to verify server fixes.
- **Ignore & Whitelist Lists:** Silence false positives or whitelist trusted external hosts.

### 📊 Modern Admin Experience
- **Real-Time Scan Monitor:** Live polling interface showing discovery count, checked count, broken counts, and dynamic progress bar.
- **Native WordPress Palette:** Menu icon matches WordPress dashboard Dashicons in idle state (`#a7aaad`) and brightens to pure white (`#ffffff`) on hover/active.
- **Interactive Overview Tab:** Built-in guided onboarding with diagrams, workflow timeline, and quick-start actions.
- **Scheduled Background Audits:** Run automated daily, weekly, or monthly scans via Action Scheduler or WP-Cron with automated email digest summaries.
- **Seamless GitHub Updates:** Automatically alerts administrators when a new GitHub release is available and allows 1-click updates directly inside the WordPress dashboard.

---

## Documentation & How to Use

### Installation

#### Method 1: WordPress Admin Upload (Recommended)
1. Download the latest `wpcalibrate-link-repair-1.0.0.zip` from the [Releases](https://github.com/zeeshanraza-official/wpcalibrate-link-repair/releases) page.
2. In your WordPress admin dashboard, navigate to **Plugins → Add New Plugin → Upload Plugin**.
3. Select the downloaded ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

#### Method 2: Manual FTP / SFTP Installation
1. Extract the `wpcalibrate-link-repair` folder from the ZIP archive.
2. Upload the `wpcalibrate-link-repair` folder to `/wp-content/plugins/` on your web server.
3. In WordPress admin, go to **Plugins → Installed Plugins** and activate **WPCalibrate Link Repair**.

---

### Updating from WordPress Dashboard

This repository includes native GitHub Release update integration:
- Whenever a new release tag (e.g. `v1.0.1`) is published on this repository, WordPress checks GitHub's Releases API in the background.
- An update notification badge will automatically appear in your WordPress admin menu under **Dashboard → Updates** and **Plugins**.
- You can click **"Update Now"** or view the changelog details popup directly in WordPress without manual FTP uploads.
- Compatible with **Git Updater** / **GitHub Updater** plugins via native `Update URI` and `GitHub Plugin URI` headers.

---

### 4-Step Repair Workflow

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  1. Discover    │ ──> │   2. Validate    │ ──> │   3. Review     │ ──> │ 4. Safe Repair  │
│ Scans all posts │     │ Fast HTTP checks │     │ Categorized 404 │     │ 1-Click update, │
│ pages & widgets │     │ SSRF-protected   │     │ & redirect tabs │     │ bulk or unlink  │
└─────────────────┘     └──────────────────┘     └─────────────────┘     └─────────────────┘
```

1. **Step 1: Start Scan**
   Click **"Scan Now"** on the plugin dashboard. The scanner explores your database in memory-safe batches of 25 items.
2. **Step 2: Monitor Real-Time Progress**
   Watch live progress metrics as URLs transition from discovery into health validation.
3. **Step 3: Review Results**
   Inspect identified broken URLs under the **Broken Links** tab or view 3xx hops under the **Redirects** tab. Click **Occurrences** to see exactly which posts contain the link.
4. **Step 4: Execute Safe Repair**
   - Click **Edit URL** to replace the broken link with a working destination.
   - Click **Bulk Replace** to update every instance across your entire site.
   - Click **Unlink** to strip the anchor tag while preserving the original reading text.

---

### Settings & Customization

Navigate to **WPCalibrate → Settings** to configure:
- **Scan Sources:** Enable/disable Posts, Pages, Custom Post Types, Internal Links, External Links, and Media URLs.
- **Request Limits:** Configure batch size (default 25), HTTP timeout (default 10s), retry count (up to 3 retries), and per-host delay.
- **Exclusion Filters:** Add URL patterns or entire domains to bypass during scans.
- **Automated Scheduling:** Select Daily, Weekly, or Monthly background audits.
- **Email Notifications:** Send email digests upon scan completion (optionally only when broken links are found).
- **Uninstall Data Retention:** Choose whether to keep database tables or perform a complete purge upon plugin deletion.

---

### Supported Content Sources
- ✅ Standard WordPress Posts & Pages (`post_content`)
- ✅ Custom Post Types (CPTs)
- ✅ Gutenberg Block Editor content
- ✅ Classic Editor HTML anchors and media
- ✅ WordPress Navigation Menus (`nav_menu_item`)
- ✅ Widget Blocks (`widget_block`)
- ✅ Custom Fields & Postmeta
- ✅ Elementor Page Builder (`_elementor_data` structured JSON)
- ✅ WooCommerce Products & Descriptions (HPOS Compatible)

---

### Safety & Data Integrity Guarantees
- **Revision Preservation:** When repairing post content, WordPress core revisions are safely preserved so changes can be reverted if necessary.
- **No Raw SQL Regex Corruption:** Page builder JSON data (Elementor) is parsed into PHP trees, mutated strictly on matched URL nodes, and safely reserialized with cache clearing.
- **Transaction Safety:** Occurrences are tracked in dedicated custom database tables (`{$wpdb->prefix}wpcalibrate_lr_*`), preventing database bloat in `wp_posts`.
- **SSRF Defense:** Strict socket verification rejects loopback and private subnets from probing internal microservices.

---

## Developer Hooks & Filters

Extend or customize scanning behavior with WordPress filters:

```php
// Add custom post types to scan
add_filter('wpcalibrate_lr_scannable_post_types', function(array $post_types) {
    $post_types[] = 'portfolio';
    $post_types[] = 'docs';
    return $post_types;
});

// Exclude custom URL domains dynamically
add_filter('wpcalibrate_lr_excluded_domains', function(array $domains) {
    $domains[] = 'cdn.legacy-domain.com';
    return $domains;
});

// Adjust HTTP request arguments for external validator
add_filter('wpcalibrate_lr_http_request_args', function(array $args, string $url) {
    $args['timeout'] = 15;
    return $args;
}, 10, 2);
```

---

## Changelog

### [1.0.3] - 2026-10-09
- **Fixed:** Resolved infinite scanning loop where validation stage re-checked URLs indefinitely (9000+ loops).
- **Added:** Monotonic primary-key cursor pagination (`WHERE id > $lastId`) guaranteeing strictly forward, zero-duplicate scans.
- **Added:** Defensive runaway scan recovery that automatically completes and calibrates stuck scans.
- **Added:** Native GitHub release update integration (`GitHubUpdater.php`) enabling 1-click dashboard updates from GitHub.

### [1.0.2] - 2026-10-09
- **Fixed:** Admin sidebar menu logo color precision. Converted source artwork to pure white (`#ffffff`) SVG vector with `opacity: 0.65` in idle state (matching `#a7aaad` Dashicons) and full opacity (`1.0`) on hover/active.

### [1.0.1] - 2026-10-09
- **Added:** Dedicated Overview tab with step-by-step documentation, architecture highlights, and quick action buttons.
- **Fixed:** Admin menu icon dimension blowout. Constrained to standard 20x20px with crisp vector assets.

### [1.0.0] - 2026-10-09
- **Initial Release:** Complete link repair engine, streaming HTTP validator, Elementor JSON writer, WooCommerce HPOS integration, REST API, Action Scheduler support, and automated test suite.

---

## Support & Contributing

- **Support Email:** [support@wpcalibrate.com](mailto:support@wpcalibrate.com)
- **Website:** [https://wpcalibrate.com](https://wpcalibrate.com)
- **Marketplace:** [https://marketplace.wpcalibrate.com/](https://marketplace.wpcalibrate.com/)
- **Bug Reports & Feature Requests:** [GitHub Issues](https://github.com/zeeshanraza-official/wpcalibrate-link-repair/issues)

---

## License

This plugin is licensed under the GNU General Public License v2.0 or later ([GPL-2.0-or-later](LICENSE)).
Commercial distribution and branding assets are copyright © 2026 WPCalibrate.
