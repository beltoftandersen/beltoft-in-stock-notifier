# Beltoft In-Stock Notifier for WooCommerce

Let customers subscribe to out-of-stock product notifications and automatically email them when items are back in stock.

- Stable version: 1.1.1
- Requires: WordPress 5.8+, PHP 7.4+, WooCommerce 6.0+
- Author: beltoft.net
- Text domain: beltoft-in-stock-notifier

## Overview

This plugin adds a subscription form to out-of-stock WooCommerce products. When a product comes back in stock, subscribers get an email notification using WooCommerce's email template — same design as your order emails.

Stock changes are detected regardless of source: admin UI, REST API, CLI, or ERP systems.

## Features

- Subscription form on out-of-stock products (simple, variable, grouped, external)
- Variable products: form shows/hides automatically per variation
- Notifications use WooCommerce email templates
- Email configurable under WooCommerce > Settings > Emails > Back In Stock
- Batch sending via Action Scheduler with configurable batch size and throttle
- Detects stock changes from admin, REST API, CLI, and ERP systems
- Admin dashboard with stats, SKU and Brand columns, and manual "Send Notifications" button
- Brand column appears automatically when WooCommerce Brands is active (WooCommerce 9.4+)
- Subscription list with search, filters, pagination, and bulk actions
- One-click unsubscribe link in every email
- Optional GDPR checkbox, honeypot spam protection, and rate limiting
- Shortcode `[bisn_form]` for custom placement
- Activity logging via WooCommerce logger (WooCommerce > Status > Logs)
- Clean uninstall with opt-in data removal
- PSR-4 codebase, no Composer dependency

## How It Works

1. Customer visits an out-of-stock product and enters their email.
2. When the product comes back in stock, WooCommerce hooks trigger the plugin.
3. Emails are queued and sent in batches — product image, "Shop Now" button, and unsubscribe link included.
4. Custom hook available for CDN/Varnish cache purging if needed.

## Installation

1. Upload the plugin to `wp-content/plugins/` or install from a ZIP.
2. Activate the plugin.
3. Go to **WooCommerce > In-Stock Notifier** to configure.
4. Optionally adjust the email under **WooCommerce > Settings > Emails > Back In Stock**.

## Configuration

### Form Placement

The form shows automatically on out-of-stock product pages. To place it manually, disable auto-placement in settings and use the shortcode:

```
[bisn_form]
[bisn_form product_id="123"]
```

### Email Template

Notifications use WooCommerce's email system — same header, footer, and colours as your other store emails. Customise the subject and heading under WooCommerce > Settings > Emails > Back In Stock.

Override the template by copying `templates/emails/back-in-stock.php` to your theme's `woocommerce/emails/` folder.

### Batch Sending

- Configurable batch size (default 50) and throttle between emails.
- If more subscribers remain, the next batch runs 60 seconds later.
- For low-traffic sites, set up a real system cron for reliable scheduling.

### Logging

- Logs are written via WooCommerce's built-in logger.
- View logs under WooCommerce > Status > Logs (source: `bisn`).
- Disable logging in the plugin settings for production.

## Hooks & Filters

Developers can extend the plugin:

- `bisn_form_html` / `bisn_form_fields` / `bisn_form_heading_text`
- `bisn_before_subscription` / `bisn_after_subscription`
- `bisn_validate_subscription`
- `bisn_before_notification_sent` / `bisn_after_notification_sent` / `bisn_after_batch_sent`
- `bisn_stock_status_triggers` — customise which statuses trigger notifications (default: instock, onbackorder)
- `bisn_cache_purge_product` — fire custom cache purge logic (e.g. Varnish, CDN)

## Translations

- Text domain: `beltoft-in-stock-notifier`
- Translation template: `languages/beltoft-in-stock-notifier.pot`

## Changelog

### 1.1.1

- Added Czech (cs_CZ) translation.

### 1.1.0

- Renamed internal prefix from `isn_` to `bisn_` for WordPress.org compliance.
- Moved inline JavaScript to `wp_add_inline_script()` for Content Security Policy compatibility.
- Added `wp_kses()` sanitization to the subscription form output.

### 1.0.29

- Simplified token generation to use `wp_generate_password()`.
- Rate limiter now uses REMOTE_ADDR only; X-Forwarded-For requires explicit opt-in via filter.
- `delete_all()` requires explicit confirmation parameter to prevent accidental data loss.
- Product subscriber lookup now shows truncation notice when results exceed limit.
- Notification throttle now uses Action Scheduler delay instead of blocking `sleep()`.
- Skip redundant subscription check for variations (query parent directly).
- Added Brand column to Dashboard top products table (conditional on WooCommerce Brands 9.4+).

### 1.0.28

- Added database index on `unsubscribe_token` column for faster token lookups.
- Added `bisn_email_product_url` filter to email templates for URL customization.
- Added `bisn_dashboard_after_stats` action hook for extending the dashboard.
- Added SKU column to the Top Products table on the Dashboard tab.

### 1.0.8

- Logging switched to WooCommerce logger — no more exposed log files on Nginx/IIS.
- "Enable Notifications" setting now fully stops queueing and sending when disabled.
- Notification queue uses Action Scheduler (bundled with WooCommerce) instead of options.
- Added database index for faster rate-limit queries at scale.
- Removed legacy file-based logging and custom log viewer.

### 1.0.7

- Notifications now use WooCommerce email templates.
- Back In Stock email configurable under WooCommerce > Settings > Emails.
- Email preview in WooCommerce email settings.
- Manual notification trigger from the Dashboard tab.
- Variation stock status shown correctly in admin dashboard.
- Improved form design (joined email input and button).
- Pre-fill email for logged-in users.

### 1.0.0

- Initial release.

## About the Author

Beltoft In-Stock Notifier for WooCommerce is built and maintained by [beltoft.net](https://beltoft.net).

## License

GPLv2 or later. See https://www.gnu.org/licenses/gpl-2.0.html.
