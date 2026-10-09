=== WPCalibrate Link Repair ===
Contributors: wpcalibrate
Tags: broken links, link checker, 404, redirect, broken link repair, missing images, elementor, woocommerce
Requires at least: 6.2
Tested up to: 7.1.3
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional, safe broken link scanner and repair utility for WordPress websites, WooCommerce stores, and Elementor pages.

== Description ==

**WPCalibrate Link Repair** scans your WordPress website for broken internal links, dead external URLs, redirects, and missing media (images, videos, audio), giving authorized administrators safe, non-destructive tools to review and repair them without manual editing.

Built specifically for high accuracy and server stability:
* **Unique URL Validation:** Avoids redundant external network checks by verifying each unique URL once per scan cycle, regardless of how many times it appears.
* **Safe Content Repair:** Perform exact-URL replacements, update redirects to verified destinations, or unlink broken anchor elements while preserving human-readable anchor text.
* **Elementor Compatible:** Safely reads and updates Elementor JSON structures without corrupting page builder layouts, and flushes Elementor CSS caches automatically.
* **WooCommerce Compatible:** Checks product descriptions and short descriptions without interfering with cart sessions, customer records, or checkout blocks. High-Performance Order Storage (HPOS) compatible.
* **SSRF Protected:** Proactively blocks private network ranges and unsafe protocols (file://, php://, data://).
* **Controlled Background Processing:** Runs in bounded batches using Action Scheduler or WP-Cron with pause, resume, and cancel capabilities.
* **Privacy by Design:** All scanning and repairs execute locally on your WordPress server. No page contents, credentials, or scan results are ever transmitted to external services.

== Installation ==

1. Upload the `wpcalibrate-link-repair` folder to the `/wp-content/plugins/` directory, or install the `.zip` file via **Plugins > Add New > Upload Plugin**.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Access **WPCalibrate > Link Repair** from your WordPress admin menu.
4. Click **Scan Now** to start your initial scan or configure automated schedules under **Settings**.

== Frequently Asked Questions ==

= Does scanning slow down my website for visitors? =
No. Scans execute exclusively in bounded background batches during administrator triggers or scheduled background cron jobs. Frontend visitor requests never execute scanning or link validation workloads.

= Does WPCalibrate Link Repair send my data to external servers? =
No. The plugin runs entirely on your own WordPress hosting server. External HTTP requests occur only to check whether outbound links in your posts are active. No site content, logs, or visitor data are transmitted to WPCalibrate or third parties.

= Does this modify files or database tables automatically? =
Never without explicit administrator review and confirmation. All bulk replacements, unlinks, and redirect updates require administrator approval.

== Changelog ==

= 1.0.0 =
* Initial production release.
* Discovery engine for standard posts, pages, CPTs, navigation menus, block widgets, and custom fields.
* HEAD with GET fallback HTTP validator with redirect chain analysis and SSRF protection.
* Safe content repair engine with bulk replacement preview, unlinking, and Elementor structured JSON support.
* Scheduled scanning (daily, weekly, monthly) and email reporting.
* Support view with direct contact channels.
