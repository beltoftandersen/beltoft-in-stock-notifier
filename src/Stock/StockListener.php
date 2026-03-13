<?php
/**
 * Listens for WooCommerce stock changes.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Stock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeltoftInStockNotifier\Support\Options;
use BeltoftInStockNotifier\Subscription\Repository;
use BeltoftInStockNotifier\Logging\LogViewer;

/**
 * Hooks into WooCommerce stock events and triggers notification queue.
 */
class StockListener {

	/**
	 * Track products already queued in this request to prevent duplicate notifications.
	 *
	 * @var array<int, true>
	 */
	private static $queued_this_request = array();

	/**
	 * Register stock change hooks.
	 *
	 * @return void
	 */
	public static function init() {
		/* Simple product stock status change. */
		add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'on_stock_status_change' ), 10, 3 );

		/* Variation stock status change. */
		add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'on_stock_status_change' ), 10, 3 );

		/* Safety net: catches any product save where stock_status prop changed. */
		add_action( 'woocommerce_product_object_updated_props', array( __CLASS__, 'on_product_props_updated' ), 10, 2 );
	}

	/**
	 * Handle stock status change.
	 *
	 * @param int        $product_id Product ID.
	 * @param string     $status     New stock status.
	 * @param WC_Product $product    Product object.
	 * @return void
	 */
	public static function on_stock_status_change( $product_id, $status, $product = null ) {
		if ( self::is_trigger_status( $status ) ) {
			self::maybe_queue_product( $product_id );
		}
	}

	/**
	 * Handle product property updates (belt-and-suspenders for ERP/API updates).
	 *
	 * @param \WC_Product $product       Product object.
	 * @param array       $updated_props Array of changed property names.
	 * @return void
	 */
	public static function on_product_props_updated( $product, $updated_props ) {
		if ( ! is_array( $updated_props ) || ! in_array( 'stock_status', $updated_props, true ) ) {
			return;
		}

		if ( self::is_trigger_status( $product->get_stock_status() ) ) {
			self::maybe_queue_product( $product->get_id() );
		}
	}

	/**
	 * Check if the plugin is enabled and the status triggers notifications.
	 *
	 * @param string $status Stock status value.
	 * @return bool
	 */
	private static function is_trigger_status( $status ) {
		if ( Options::get( 'enabled' ) !== '1' ) {
			return false;
		}

		/**
		 * Filter the stock statuses that trigger notifications.
		 *
		 * @param array $statuses Stock status values.
		 */
		$trigger_statuses = apply_filters( 'bisn_stock_status_triggers', array( 'instock', 'onbackorder' ) );

		return in_array( $status, $trigger_statuses, true );
	}

	/**
	 * Check for subscriptions and queue notifications if any exist.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return void
	 */
	private static function maybe_queue_product( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return;
		}

		// Prevent duplicate queueing from multiple hooks firing in the same request.
		if ( isset( self::$queued_this_request[ $product_id ] ) ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$parent_id  = $product->get_parent_id();
		$enqueue_id = $product_id;

		// For variations, check the parent directly since subscriptions are stored
		// against the parent product_id. Checking the variation_id would always miss.
		if ( $parent_id ) {
			$has_subs = Repository::has_active_subscriptions( $parent_id );
			if ( $has_subs ) {
				$enqueue_id = $parent_id;
			}
		} else {
			$has_subs = Repository::has_active_subscriptions( $product_id );
		}

		if ( ! $has_subs ) {
			return;
		}

		self::$queued_this_request[ $product_id ] = true;
		LogViewer::log( 'STOCK_CHANGE product=' . $product_id . ' status=instock queuing_notifications' );

		$variation = ( $parent_id && $parent_id !== $product_id ) ? $product_id : 0;
		NotificationQueue::enqueue( $enqueue_id, $variation );

		/**
		 * Fires after a product comes back in stock, for custom cache purge logic (CDN, Varnish, etc.).
		 *
		 * @param int    $product_id Product ID.
		 * @param string $url        Product permalink.
		 */
		do_action( 'bisn_cache_purge_product', $product_id, get_permalink( $product_id ) );

		if ( $parent_id && $parent_id !== $product_id ) {
			/** This action is documented above. */
			do_action( 'bisn_cache_purge_product', $parent_id, get_permalink( $parent_id ) );
		}
	}
}
