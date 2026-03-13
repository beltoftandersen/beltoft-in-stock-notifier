<?php
/**
 * Uninstall handler - cleans up plugin data on deletion.
 *
 * @package BeltoftInStockNotifier
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$bisn_options = get_option( 'bisn_options', array() );

if ( ! is_array( $bisn_options ) || empty( $bisn_options['cleanup_on_uninstall'] ) || '1' !== $bisn_options['cleanup_on_uninstall'] ) {
	return;
}

/* Drop the subscriptions table. */
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bisn_subscriptions" );

/* Delete options. */
delete_option( 'bisn_options' );
delete_option( 'bisn_db_version' );
delete_option( 'bisn_pending_queue' ); /* Legacy option from pre-1.0.8 queue. */

/* Remove cron events. */
wp_clear_scheduled_hook( 'bisn_daily_cleanup' );

/* Unschedule all Action Scheduler actions for this plugin. */
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'bisn_send_notification' );
}

/* Remove legacy log files if they exist (from pre-1.0.8 installs). */
if ( ! function_exists( 'WP_Filesystem' ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
}
WP_Filesystem();
global $wp_filesystem;
if ( $wp_filesystem ) {
	$bisn_upload  = wp_upload_dir();
	$bisn_log_dir = trailingslashit( $bisn_upload['basedir'] ) . 'bisn-logs/';
	if ( $wp_filesystem->is_dir( $bisn_log_dir ) ) {
		$wp_filesystem->delete( $bisn_log_dir, true );
	}
}
