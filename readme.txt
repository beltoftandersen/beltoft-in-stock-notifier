=== Beltoft In-Stock Notifier for WooCommerce ===
Contributors: beltoftnet
Tags: woocommerce, back in stock, restock, notification, waitlist
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let customers subscribe to out-of-stock product notifications and automatically email them when items are back in stock.

== Description ==

Beltoft In-Stock Notifier for WooCommerce adds a subscription form to out-of-stock products so customers can leave their email and get notified when the product is restocked. Emails are sent using WooCommerce's email template so they match your store's design.

=== Key Features ===
1. Subscription form on out-of-stock products — works with simple, variable, grouped, and external products.
2. Variable products: form shows/hides automatically when selecting out-of-stock variations.
3. Notifications sent via WooCommerce email templates — same look as your order emails.
4. Email settings (subject, heading, on/off) under WooCommerce > Settings > Emails.
5. Batch sending via Action Scheduler — handles thousands of subscribers without slowing down.
6. Detects stock changes from admin, REST API, CLI, and ERP systems.
7. Admin dashboard with stats and a manual "Send Notifications" button per product.
8. Subscription list with search, filters, pagination, and bulk actions.
9. One-click unsubscribe link in every email.
10. Optional GDPR checkbox, honeypot spam protection, and rate limiting.
11. Shortcode `[bisn_form]` for custom placement.
12. Activity logging via WooCommerce logger.

=== How It Works ===
1. Customer visits an out-of-stock product and enters their email.
2. When the product comes back in stock, the plugin picks it up via WooCommerce hooks.
3. Emails are queued and sent in batches — each one includes the product image, a "Shop Now" button, and an unsubscribe link.
4. Custom hook available for CDN/Varnish cache purging if needed.

== Installation ==
1. Upload the `beltoft-in-stock-notifier` folder to `/wp-content/plugins/` or install via the Plugins screen.
2. Activate the plugin.
3. Go to **WooCommerce > In-Stock Notifier** to configure.
4. Optionally adjust the email text under **WooCommerce > Settings > Emails > Back In Stock** (body text, button text, footer text).

== Configuration ==

=== Form Placement ===
- The form shows automatically on out-of-stock product pages.
- To place it yourself, disable auto-placement in settings and use `[bisn_form]` or `[bisn_form product_id="123"]`.

=== Email Template ===
- Uses WooCommerce's email system — same header, footer, and colours as your other store emails.
- Customise subject, heading, body text, button text, and footer text under WooCommerce > Settings > Emails > Back In Stock.
- Use `{product_name}` and `{site_title}` placeholders in any of these fields.
- Override the template by copying `templates/emails/back-in-stock.php` to your theme's `woocommerce/emails/` folder for more complex customisations.

=== Batch Sending ===
- Configurable batch size (default 50) and throttle between emails.
- If there are more subscribers than one batch, the next batch runs 60 seconds later.
- For low-traffic sites, set up a real system cron for reliable scheduling.

=== Hooks & Filters ===
Developers can extend the plugin:

* `bisn_form_html` / `bisn_form_fields` / `bisn_form_heading_text`
* `bisn_before_subscription` / `bisn_after_subscription`
* `bisn_validate_subscription`
* `bisn_before_notification_sent` / `bisn_after_notification_sent` / `bisn_after_batch_sent`
* `bisn_stock_status_triggers` — customise which statuses trigger notifications (default: instock, onbackorder)
* `bisn_cache_purge_product` — fire custom cache purge logic (e.g. Varnish, CDN)

== Frequently Asked Questions ==

= Does it work with variable products? =
Yes. The form appears when a customer selects an out-of-stock variation. The specific variation is tracked so the notification only sends when that variation is back.

= Does it pick up stock changes from the REST API or ERPs? =
Yes. It hooks into WooCommerce's core stock events which fire regardless of how stock is updated.

= Which email providers does it work with? =
Any. It sends through WooCommerce's email system which uses `wp_mail()` — works with SMTP plugins, Amazon SES, SendGrid, etc.

= Do emails match my store's design? =
Yes. They use WooCommerce's email template — same header, footer, and styling as order emails.

= Can I send notifications manually? =
Yes. The Dashboard tab has a "Send Notifications" button next to each product.

= How do customers unsubscribe? =
Every email has a one-click unsubscribe link. No login needed.

= What if the product sells out again before all emails go out? =
The sender checks stock before each batch. If the product is out of stock again, remaining emails are skipped.

= Does it handle cached pages? =
WooCommerce and popular caching plugins already purge product pages when stock changes. The plugin also fires an `bisn_cache_purge_product` hook if you need custom purge logic (e.g. Varnish or a CDN).

== Screenshots ==
1. Subscription form on an out-of-stock product page.
2. Admin dashboard with stats and top products.
3. Subscription management with filters and bulk actions.
4. Settings page.
5. Back In Stock email in WooCommerce email settings.

== Changelog ==

= 1.2.0 =
* Added editable email body text, button text, footer text, and unsubscribe link text under WooCommerce > Settings > Emails > Back In Stock.
* Added editable form heading text under WooCommerce > In-Stock Notifier > Settings.
* All editable email fields support `{product_name}` and `{site_title}` placeholders.
* Translations still apply when fields are left at their defaults.
* Changed "Disable Logging" to "Enable Logging" (off by default).

= 1.1.1 =
* Added Czech (cs_CZ) translation.

= 1.1.0 =
* Renamed internal prefix from `isn_` to `bisn_` for WordPress.org compliance.
* Moved inline JavaScript to `wp_add_inline_script()` for Content Security Policy compatibility.
* Added `wp_kses()` sanitization to the subscription form output.

= 1.0.29 =
* Simplified token generation to use `wp_generate_password()` (no dependency on wp_salt).
* Rate limiter now uses REMOTE_ADDR only; X-Forwarded-For requires explicit opt-in via filter.
* `delete_all()` requires explicit confirmation parameter to prevent accidental data loss.
* Product subscriber lookup now shows truncation notice when results exceed limit.
* Notification throttle now uses Action Scheduler delay instead of blocking `sleep()`.
* Skip redundant subscription check for variations (query parent directly).

= 1.0.28 =
* Added database index on `unsubscribe_token` column for faster token lookups.
* Added `bisn_email_product_url` filter to email templates for URL customization.
* Added `bisn_dashboard_after_stats` action hook for extending the dashboard.
* Added SKU column to the Top Products table on the Dashboard tab.

= 1.0.8 =
* Logging now uses WooCommerce logger (WooCommerce > Status > Logs) — fixes log file exposure on Nginx/IIS.
* "Enable Notifications" setting now fully stops queueing and sending when disabled.
* Notification queue replaced with Action Scheduler (bundled with WooCommerce) for atomic, concurrent-safe scheduling.
* Added composite index on ip_address + created_at for faster rate-limit queries at scale.
* Removed legacy file-based logging and custom log viewer.

= 1.0.7 =
* Notifications now use WooCommerce email templates.
* Back In Stock email configurable under WooCommerce > Settings > Emails.
* Email preview in WooCommerce email settings.
* Manual notification trigger from the Dashboard tab.
* Variation stock status shown correctly in admin dashboard.
* Improved form design (joined email input and button).
* Pre-fill email for logged-in users.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.8 =
Security and reliability improvements: logging moved to WooCommerce logger, notification queue now uses Action Scheduler, rate-limit index added.

= 1.0.7 =
Notifications now use WooCommerce email templates. New manual send button and admin improvements.

= 1.0.0 =
Initial release.
