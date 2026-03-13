<?php
/**
 * Notification queue using Action Scheduler (bundled with WooCommerce).
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Stock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the notification queue via Action Scheduler for atomic, concurrent-safe scheduling.
 */
class NotificationQueue {

	const ACTION_HOOK = 'bisn_send_notification';

	/**
	 * Enqueue a product for notification sending.
	 *
	 * Each product+variation pair gets its own scheduled action, avoiding
	 * the race condition of a shared option-based queue.
	 *
	 * @param int $product_id   Product ID (parent for variations).
	 * @param int $variation_id Variation ID (0 for simple products).
	 * @return void
	 */
	public static function enqueue( $product_id, $variation_id = 0 ) {
		$args = array(
			'product_id'   => absint( $product_id ),
			'variation_id' => absint( $variation_id ),
		);

		/* Don't schedule a duplicate if one is already pending for this product+variation. */
		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::ACTION_HOOK, $args ) ) {
			return;
		}

		if ( function_exists( 'as_schedule_single_action' ) ) {
			$action_id = as_schedule_single_action( time() + 30, self::ACTION_HOOK, $args, 'bisn' );
			if ( ! $action_id ) {
				\BeltoftInStockNotifier\Logging\LogViewer::log( 'SCHEDULE_FAIL product=' . $args['product_id'] . ' variation=' . $args['variation_id'], 'error' );
			}
		}
	}

	/**
	 * Schedule a follow-up batch (called when more subscribers remain).
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @param int $delay        Seconds to wait before executing (default 60).
	 * @return void
	 */
	public static function enqueue_next_batch( $product_id, $variation_id = 0, $delay = 60 ) {
		$args = array(
			'product_id'   => absint( $product_id ),
			'variation_id' => absint( $variation_id ),
		);

		/* Don't schedule a duplicate if one is already pending for this product+variation. */
		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( self::ACTION_HOOK, $args ) ) {
			return;
		}

		$delay = max( 1, absint( $delay ) );

		if ( function_exists( 'as_schedule_single_action' ) ) {
			$action_id = as_schedule_single_action( time() + $delay, self::ACTION_HOOK, $args, 'bisn' );
			if ( ! $action_id ) {
				\BeltoftInStockNotifier\Logging\LogViewer::log( 'SCHEDULE_FAIL product=' . $args['product_id'] . ' variation=' . $args['variation_id'], 'error' );
			}
		}
	}
}
