<?php
/**
 * Plugin Name:       Beltoft In-Stock Notifier for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/beltoft-in-stock-notifier/
 * Description:       Let customers subscribe to out-of-stock product notifications and automatically email them when items are back in stock.
 * Version:           1.2.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            beltoft.net
 * Author URI:        https://beltoft.net
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       beltoft-in-stock-notifier
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 *
 * WC requires at least: 6.0
 * WC tested up to:      9.6
 *
 * @package BeltoftInStockNotifier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── PSR-4 autoloader ──────────────────────────────────────────── */
spl_autoload_register(
	function ( $class ) {
		if ( strpos( $class, 'BeltoftInStockNotifier\\' ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( 'BeltoftInStockNotifier\\' ) );
		$relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';
		$file     = plugin_dir_path( __FILE__ ) . 'src/' . $relative;
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/* ── Constants ─────────────────────────────────────────────────── */
define( 'BISN_VERSION', '1.2.1' );
define( 'BISN_PATH', plugin_dir_path( __FILE__ ) );
define( 'BISN_URL', plugin_dir_url( __FILE__ ) );
define( 'BISN_BASENAME', plugin_basename( __FILE__ ) );
define( 'BISN_DB_VERSION', '1.1.4' );

/* ── Activation / deactivation ─────────────────────────────────── */
register_activation_hook(
	__FILE__,
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			/* translators: %s: WooCommerce plugin name */
			wp_die( esc_html__( 'In-Stock Notifier requires WooCommerce to be installed and active.', 'beltoft-in-stock-notifier' ) );
		}
		BeltoftInStockNotifier\Support\Installer::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		wp_clear_scheduled_hook( 'bisn_daily_cleanup' );
		/* Unschedule all Action Scheduler actions. */
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'bisn_send_notification' );
		}
		/* Clean up legacy cron hook from pre-1.0.8. */
		wp_clear_scheduled_hook( 'bisn_send_notifications' );
		delete_transient( 'bisn_processing_lock' );
	}
);

/* ── Bootstrap ─────────────────────────────────────────────────── */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		BeltoftInStockNotifier\Plugin::init();
	}
);

/* ── Settings link on Plugins page ─────────────────────────────── */
add_filter(
	'plugin_action_links_' . BISN_BASENAME,
	function ( $links ) {
		$url           = admin_url( 'admin.php?page=bisn-notifier' );
		$settings_link = '<a href="' . esc_url( $url ) . '">'
			. esc_html__( 'Settings', 'beltoft-in-stock-notifier' )
			. '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
);

/* ── HPOS compatibility ────────────────────────────────────────── */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);
