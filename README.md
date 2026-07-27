# KW Performance

Automatically crawl your WordPress site's frontend, detect broken links (404s, 410s, broken/looping redirects, and 5xx server errors), log exactly where each broken link lives on the page, and get notified by email — all from one clean admin screen.

- **Plugin Slug:** `kw-performance`
- **Author:** KW Developers ([kilowott.com](https://kilowott.com))
- **Requires:** WordPress 5.8+, PHP 8.0+

## Features

- Crawls published pages, posts, custom post types, and post type archives.
- Extracts every `<a href>` on the rendered page (ignoring `mailto:`, `tel:`, `javascript:`, `#anchor`, and empty links).
- Validates each unique URL once per scan (deduplicated) using the WordPress HTTP API, manually following redirects to report the full chain (301/302/307/308), the final URL, and the final HTTP status.
- Flags 404, 410, 5xx responses, connection errors/timeouts, and redirect loops as broken.
- Detects the DOM context of every broken link: nearest ancestor CSS class/ID, all ancestor classes, and Gutenberg (`wp-block-*`) / Elementor (`elementor-widget-*`) markers when present.
- Stores results in a dedicated database table (`{prefix}kwperf_logs`) with detection counts, first/last seen timestamps, and auto-removes rows for links that are no longer broken on the next scan.
- Scheduled scanning via WP-Cron (hourly / twice daily / daily / weekly), with a manual "Run Scan Now" AJAX button and a progress indicator.
- HTML email report sent to one or more configurable admin addresses (comma-separated) after each scheduled scan when broken links are found (optionally even when none are found).
- Optional Slack notifications via an Incoming Webhook — same trigger as email, with a "Send Test Notification" button that checks the webhook before you save.
- Searchable, sortable, paginated 404 Log admin screen built on `WP_List_Table`, with filtering by status type, CSV export, single/bulk delete, and single/bulk/"recheck all" re-validation.
- Scan History screen showing duration, pages/links scanned, broken/working counts, and any errors for every past run (manual or scheduled).
- Nonce-protected AJAX endpoints, `manage_options` capability checks, sanitized input, escaped output, and prepared SQL throughout.

## Installation

1. Copy the `kw-performance` folder into `wp-content/plugins/`.
2. Activate **KW Performance** from the Plugins screen.
3. Go to **KW Performance → Settings** to configure the scan interval, notification email, and which post types are scanned.

Activation automatically creates the plugin's database tables and schedules the recurring scan; deactivation removes the scheduled cron event without touching your logged data.

### Upgrading From KW 404 Detector

This plugin was previously named "KW 404 Detector". If you have that version installed:

1. Deactivate **KW 404 Detector**, then remove its files directly (FTP/file manager/SSH) — **don't** click "Delete" on the Plugins screen, since that runs its uninstall routine and wipes its settings.
2. Install and activate **KW Performance** as above.

On activation, it automatically migrates the old plugin's logs, scan history, and settings into its own tables/options, drops the old tables, and cleans up the old cron event — no data is lost, and no manual DB work is needed. If you do end up deleting the old plugin through wp-admin first, the migration simply has nothing left to find; you'll just start fresh.

## Usage

### Settings

**KW Performance → Settings** lets you:

- Enable/disable scheduled scanning and choose the interval.
- Set the notification email address(es) — comma-separate multiple addresses (defaults to the site admin email).
- Choose whether to be notified even when a scan finds nothing broken (applies to both email and Slack).
- Choose which public post types are crawled.
- Enable Slack notifications and paste an [Incoming Webhook](https://api.slack.com/apps) URL, then confirm it works with "Send Test Notification" before saving.
- Run an on-demand scan ("Run Scan Now") with a live progress bar — a second scan cannot be started while one is already running.
- Export all logged broken links as CSV, or clear the log table entirely.
- View last scan date/time, pages scanned, total broken links, and total working links from the last run.

### 404 Log

**KW Performance → 404 Log** lists every currently-broken link with:

| Column | Description |
|---|---|
| Broken Link | The original `href`, with "Open URL", "Recheck", and "Delete" row actions |
| HTTP Status | Final HTTP status code (color-coded badge) |
| Redirect Status | Redirect chain summary and count, or a "Redirect Loop" badge |
| Page Title / Permalink | The page the link was found on (opens in a new tab) |
| Section/Class | Nearest container class/ID, plus Gutenberg/Elementor markers when detected |
| Last Checked | When this link was last validated |
| Times Detected | How many consecutive scans have found this link broken |

Use the search box and status filter (404 / 410 / 5xx / broken redirects) to narrow the list, click column headers to sort, and use the bulk action dropdown to delete or recheck multiple rows at once. "Recheck All" re-validates every logged link immediately and removes any that are now resolved.

### Scan History

**KW Performance → Scan History** shows every completed scan (manual or scheduled) with its date, duration, pages/links scanned, broken/working counts, and any fetch errors encountered.

## Database Schema

```
{$wpdb->prefix}kwperf_logs
  id, source_page_id, source_permalink, source_title,
  broken_url, final_url, http_status, redirect_status, redirect_count,
  section, css_class, link_text, status,
  first_detected, last_checked, detection_count

{$wpdb->prefix}kwperf_scan_history
  id, scan_date, duration, pages_scanned, links_scanned,
  broken_links_found, working_links, errors_encountered, scan_type
```

## Uninstalling

Deactivating the plugin only removes the scheduled cron event — your logs and settings are preserved. To permanently delete all plugin data (logs, scan history, settings, and database tables) when the plugin is deleted, enable **Delete Data On Uninstall** on the Settings screen first, then delete the plugin from the Plugins screen.

## Performance Notes

- Every unique URL is checked at most once per scan run, even if it appears on many pages.
- Redirects are followed manually (HEAD first, falling back to GET) up to the configured maximum, so redirect chains and loops are detected without extra requests.
- Large sites: scans run with no PHP execution time limit where the host allows it, but very large catalogs may still benefit from a longer host-level timeout or a less frequent cron interval.

## Testing With test-404-template.php

The plugin ships two extra files for exercising every scanner case at once on a real site, without waiting to stumble across real broken links:

- **`test-404-template.php`** — a WordPress Page Template. It renders one page containing every link type the scanner has to handle: ignored links (`#`, empty, `mailto:`, `tel:`, `javascript:`, and a bare email typo missing `mailto:`), a working link, a direct 404, direct 410/500/503, single- and double-hop redirects to both a working and a broken destination, a redirect loop, a working and a broken external link, a duplicate broken link across two sections, and a link with no visible text — each wrapped in markup meant to exercise section/class detection (a `<section class="hero-banner">`, a `wp-block-*` Gutenberg-style wrapper, an `elementor-widget-*`/`elementor-element` wrapper, an ancestor with multiple classes, and an ancestor identified only by `id`). The page itself renders a table of every case and its expected result, so you can eyeball it before even running a scan.
- **`test-404-mu-plugin.php`** — a required companion "must-use" plugin. KW Performance checks most links with an HTTP `HEAD` request, but WordPress only runs a page template's own PHP for a full `GET` page load — a `HEAD` request to an existing page short-circuits before the template file is ever included. Simulating status codes and redirects directly in the template would therefore work if you open the links in a browser (`GET`) but silently no-op against the scanner's `HEAD` checks. Hooking `template_redirect` from a must-use plugin fixes that, since must-use plugins load unconditionally on every request, `HEAD` included.

### Setup

1. Copy `test-404-template.php` into the root of your active theme (or child theme).
2. Copy `test-404-mu-plugin.php` into `wp-content/mu-plugins/` (create that folder if it doesn't exist — no activation needed, WordPress loads everything in it automatically).
3. In wp-admin, create a new Page, choose **KW Performance Test Template** under Page Attributes, and publish it.
4. Run a scan (**Run Scan Now**, or wait for the cron) and compare the **404 Log** against the expected-result table on the test page itself.

### Notes

- If your host uses full-page or CDN caching (Kinsta, Servebolt, Cloudflare, etc.), purge the cache before scanning — a cached `200` for one of the simulated status/redirect URLs will read as a false negative that has nothing to do with the plugin.
- The `?kwperf_status=...` query-string variants are all technically the same page/permalink, so they show up as separate rows in the 404 Log (one per simulated case) with the same source page — that's expected, not a duplicate-detection bug.
- Both files are meant to be temporary. Once you're done, delete the test page, remove the template from your theme, and remove the mu-plugin file — it deliberately serves broken responses.

## File Structure

```
kw-performance/
├── kw-performance.php      Plugin bootstrap, autoloader, activation hooks
├── uninstall.php           Uninstall cleanup
├── test-404-template.php   Theme page template — one-page test of every scanner case
├── test-404-mu-plugin.php  Companion mu-plugin — makes the test page's simulated cases work under HEAD checks
├── assets/
│   ├── css/admin.css
│   ├── js/admin.js
│   └── images/icon.svg, logo-horizontal.svg
├── includes/
│   ├── class-plugin.php       Core singleton / lifecycle
│   ├── class-admin.php        Admin menus, screens, asset enqueue
│   ├── class-scanner.php      Crawler, link extraction, URL validation
│   ├── class-cron.php         WP-Cron scheduling
│   ├── class-logger.php       Log/scan-history persistence (custom tables)
│   ├── class-database.php     Table creation/teardown
│   ├── class-settings.php     Settings API registration
│   ├── class-email.php        HTML notification email
│   ├── class-slack.php        Slack Incoming Webhook notification
│   ├── class-ajax.php         AJAX + admin-post (CSV export) handlers
│   ├── class-logs-list-table.php
│   └── class-history-list-table.php
└── templates/
    ├── settings-page.php
    ├── logs-page.php
    ├── scan-history-page.php
    └── email-scan-report.php
```

## Security

- All state-changing AJAX requests are protected by a nonce (`kwperf_admin_nonce`) and require the `manage_options` capability.
- CSV export uses `check_admin_referer()` on an `admin-post.php` action.
- All database access uses `$wpdb->prepare()` / `$wpdb->insert()` / `$wpdb->update()` with typed placeholders.
- All output is escaped with the appropriate `esc_html()`, `esc_url()`, or `esc_attr()` calls.

## License

GPL v2 or later.
