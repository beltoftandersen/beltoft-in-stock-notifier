<?php
/**
 * Logging via WooCommerce logger.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Logging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeltoftInStockNotifier\Support\Options;

/**
 * Writes log entries via wc_get_logger(). View logs under WooCommerce > Status > Logs.
 */
class LogViewer {

	const SOURCE = 'bisn';

	/**
	 * Write a log entry via WooCommerce logger.
	 *
	 * @param string $message Log message.
	 * @param string $level   Log level (debug, info, notice, warning, error, critical, alert, emergency).
	 * @return void
	 */
	public static function log( $message, $level = 'info' ) {
		if ( Options::get( 'disable_logging' ) === '1' ) {
			return;
		}

		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$logger = wc_get_logger();
		$logger->log( $level, $message, array( 'source' => self::SOURCE ) );
	}
}
