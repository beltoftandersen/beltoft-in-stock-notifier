<?php
/**
 * Plugin activation handler.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the database table, seeds options, and schedules cron.
 */
class Installer {

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_table();
		self::seed_options();
		self::schedule_cron();
	}

	/**
	 * Create the subscriptions table using dbDelta.
	 *
	 * @return void
	 */
	private static function create_table() {
		global $wpdb;
		$table   = $wpdb->prefix . 'bisn_subscriptions';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			product_id bigint(20) unsigned NOT NULL,
			variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			email varchar(200) NOT NULL,
			quantity_requested int unsigned NOT NULL DEFAULT 1,
			status varchar(20) NOT NULL DEFAULT 'active',
			ip_address varchar(45) NOT NULL DEFAULT '',
			gdpr_consent tinyint(1) NOT NULL DEFAULT 0,
			unsubscribe_token varchar(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			notified_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY product_id (product_id),
			KEY variation_id (variation_id),
			KEY email (email(100)),
			KEY status (status),
			KEY product_status (product_id, status),
			KEY batch_lookup (product_id, variation_id, status, created_at),
			UNIQUE KEY unique_sub (product_id, variation_id, email(100)),
			KEY ip_created (ip_address, created_at),
			KEY cleanup_lookup (status, notified_at),
			KEY unsubscribe_token (unsubscribe_token)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'bisn_db_version', BISN_DB_VERSION );
	}

	/**
	 * Seed default options if not already set.
	 *
	 * Text fields (TEXT_KEYS) are intentionally omitted so that get_all()
	 * falls through to defaults(), which returns the __()-translated value
	 * for the current locale. Only non-text settings are persisted.
	 *
	 * @return void
	 */
	private static function seed_options() {
		if ( false === get_option( Options::OPTION ) ) {
			$defaults = Options::defaults();
			foreach ( Options::TEXT_KEYS as $key ) {
				unset( $defaults[ $key ] );
			}
			add_option( Options::OPTION, $defaults );
		}
	}

	/**
	 * Schedule the daily cleanup cron.
	 *
	 * @return void
	 */
	private static function schedule_cron() {
		if ( ! wp_next_scheduled( 'bisn_daily_cleanup' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'bisn_daily_cleanup' );
		}
	}
}
