<?php
/**
 * Action Scheduler worker that sends notification emails.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Stock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeltoftInStockNotifier\Support\Options;
use BeltoftInStockNotifier\Subscription\Repository;
use BeltoftInStockNotifier\Unsubscribe\TokenManager;
use BeltoftInStockNotifier\Email\BackInStockEmail;
use BeltoftInStockNotifier\Logging\LogViewer;

/**
 * Processes notification actions scheduled by NotificationQueue.
 */
class NotificationSender {

	/**
	 * Register action hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( NotificationQueue::ACTION_HOOK, array( __CLASS__, 'process_batch' ), 10, 2 );
		add_action( 'bisn_daily_cleanup', array( __CLASS__, 'cleanup' ) );
	}

	/**
	 * Process one batch of notifications for a product.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID (0 for simple).
	 * @return void
	 */
	public static function process_batch( $product_id = 0, $variation_id = 0 ) {
		/* Bail if plugin is disabled. */
		if ( Options::get( 'enabled' ) !== '1' ) {
			return;
		}

		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );

		if ( ! $product_id ) {
			return;
		}

		$opts       = Options::get_all();
		$batch_size = absint( $opts['batch_size'] );
		$throttle   = absint( $opts['throttle_seconds'] );

		/* Re-verify stock status. */
		$check_id = $variation_id ? $variation_id : $product_id;
		$product  = wc_get_product( $check_id );
		if ( ! $product || ! $product->is_in_stock() ) {
			LogViewer::log( 'SKIP product=' . $check_id . ' no_longer_in_stock' );
			return;
		}

		/* Get active subscriptions. */
		$subs = Repository::get_active_for_product( $product_id, $variation_id, $batch_size );
		if ( empty( $subs ) && $variation_id ) {
			/* Try with variation_id=0 (generic "any variation" subscriptions). */
			$subs = Repository::get_active_for_product( $product_id, 0, $batch_size );
		}

		if ( empty( $subs ) ) {
			LogViewer::log( 'SKIP product=' . $product_id . ' no_active_subscriptions' );
			return;
		}

		$sent_count = 0;
		$start_time = microtime( true );

		foreach ( $subs as $sub ) {
			/**
			 * Fires before a notification email is sent.
			 *
			 * @param object      $sub     Subscription record.
			 * @param \WC_Product $product Product object.
			 */
			do_action( 'bisn_before_notification_sent', $sub, $product );

			$unsub_url = TokenManager::get_url( $sub->unsubscribe_token );

			/* Send via WooCommerce email class for consistent template styling. */
			$email_instance = self::get_email_instance();
			$sent           = $email_instance->trigger( $product, $sub->email, $unsub_url );

			if ( $sent ) {
				Repository::mark_notified( $sub->id );
				++$sent_count;
				LogViewer::log( 'SENT email=' . $sub->email . ' product=' . $product_id );

				/**
				 * Fires after a notification email is sent.
				 *
				 * @param object      $sub     Subscription record.
				 * @param \WC_Product $product Product object.
				 */
				do_action( 'bisn_after_notification_sent', $sub, $product );
			} else {
				LogViewer::log( 'FAIL email=' . $sub->email . ' product=' . $product_id, 'error' );
			}

			if ( $throttle > 0 ) {
				// Schedule next batch with throttle delay instead of blocking with sleep().
				NotificationQueue::enqueue_next_batch( $product_id, $variation_id, $throttle );
				break;
			}

			/* Break if approaching PHP timeout to avoid abrupt termination. */
			$max_time = (int) ini_get( 'max_execution_time' );
			if ( $max_time > 0 ) {
				$elapsed = microtime( true ) - $start_time;
				if ( $elapsed > ( $max_time * 0.8 ) ) {
					LogViewer::log( 'BATCH_TIMEOUT product=' . $product_id . ' sent=' . $sent_count . ' approaching_max_execution_time', 'warning' );
					break;
				}
			}
		}

		/**
		 * Fires after a batch of notifications is sent.
		 *
		 * @param int         $sent_count Number of emails sent.
		 * @param int         $product_id Product ID.
		 * @param \WC_Product $product    Product object.
		 */
		do_action( 'bisn_after_batch_sent', $sent_count, $product_id, $product );

		LogViewer::log( 'BATCH_DONE product=' . $product_id . ' sent=' . $sent_count );

		/* If more subscribers remain, schedule a follow-up batch. */
		$remaining = Repository::get_active_for_product( $product_id, $variation_id, 1 );
		if ( empty( $remaining ) && $variation_id ) {
			$remaining = Repository::get_active_for_product( $product_id, 0, 1 );
		}
		if ( ! empty( $remaining ) ) {
			NotificationQueue::enqueue_next_batch( $product_id, $variation_id );
		}
	}

	/**
	 * Get the WooCommerce BackInStockEmail instance.
	 *
	 * @return BackInStockEmail
	 */
	private static function get_email_instance() {
		$emails = WC()->mailer()->get_emails();
		if ( isset( $emails['BISN_Back_In_Stock'] ) ) {
			return $emails['BISN_Back_In_Stock'];
		}
		return new BackInStockEmail();
	}

	/**
	 * Daily cleanup of old notified subscriptions.
	 *
	 * @return void
	 */
	public static function cleanup() {
		$days    = absint( Options::get( 'cleanup_days' ) );
		$deleted = Repository::cleanup_old( $days );
		if ( $deleted > 0 ) {
			LogViewer::log( 'CLEANUP deleted=' . $deleted . ' older_than=' . $days . '_days' );
		}
	}
}
