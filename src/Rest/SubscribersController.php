<?php
/**
 * Read-only REST endpoint exposing aggregate subscriber counts per product.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeltoftInStockNotifier\Subscription\Repository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /wp-json/beltoft-in-stock-notifier/v1/subscribers
 *
 * Returns counts and dates only — never e-mail addresses, names, user IDs
 * or IP addresses. Intended for purchasing / demand-planning integrations.
 *
 * Authentication: WordPress cookie/application-password auth, or the
 * WooCommerce REST API consumer key/secret scheme (the same one used by
 * wc/v3). Callers must have the `manage_woocommerce` capability.
 */
class SubscribersController {

	const REST_NAMESPACE = 'beltoft-in-stock-notifier/v1';

	const DEFAULT_PER_PAGE = 200;
	const MAX_PER_PAGE     = 500;

	const NOTIFIED_WINDOW_DAYS = 30;
	const SIGNUPS_WINDOW_DAYS  = 365;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'woocommerce_rest_is_request_to_rest_api', array( __CLASS__, 'allow_wc_authentication' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/subscribers',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_subscribers' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'args'                => array(
					'page'     => array(
						'description'       => __( 'Page of results (1-based).', 'beltoft-in-stock-notifier' ),
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
					'per_page' => array(
						'description'       => __( 'Number of products per page.', 'beltoft-in-stock-notifier' ),
						'type'              => 'integer',
						'default'           => self::DEFAULT_PER_PAGE,
						'minimum'           => 1,
						'maximum'           => self::MAX_PER_PAGE,
						'sanitize_callback' => 'absint',
						'validate_callback' => 'rest_validate_request_arg',
					),
				),
			)
		);
	}

	/**
	 * Let WooCommerce apply its consumer key/secret authentication to our namespace.
	 *
	 * WooCommerce only authenticates requests whose path contains `wc/` or `wc-`.
	 * This filter extends that to `beltoft-in-stock-notifier/`.
	 *
	 * @param bool $is_wc_request Whether WooCommerce already treats this as its own request.
	 * @return bool
	 */
	public static function allow_wc_authentication( $is_wc_request ) {
		if ( $is_wc_request ) {
			return true;
		}

		$needle = self::REST_NAMESPACE . '/';

		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$rest_prefix = trailingslashit( rest_get_url_prefix() );
			if ( false !== strpos( $request_uri, $rest_prefix . $needle ) ) {
				return true;
			}
		}

		// Sites without pretty permalinks use ?rest_route=/namespace/route.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only route detection, no state change.
		if ( isset( $_GET['rest_route'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$rest_route = sanitize_text_field( wp_unslash( $_GET['rest_route'] ) );
			if ( false !== strpos( $rest_route, '/' . $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Permission check: authenticated user with manage_woocommerce.
	 *
	 * @return true|WP_Error
	 */
	public static function check_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'bisn_rest_not_authenticated',
				__( 'Authentication required.', 'beltoft-in-stock-notifier' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'bisn_rest_forbidden',
				__( 'You are not allowed to view subscriber statistics.', 'beltoft-in-stock-notifier' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Build the paginated aggregate response.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_subscribers( WP_REST_Request $request ) {
		$page     = max( 1, absint( $request->get_param( 'page' ) ) );
		$per_page = absint( $request->get_param( 'per_page' ) );
		$per_page = min( self::MAX_PER_PAGE, max( 1, $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

		$now            = time();
		$notified_since = gmdate( 'Y-m-d H:i:s', $now - self::NOTIFIED_WINDOW_DAYS * DAY_IN_SECONDS );
		// Whole UTC calendar days: start at midnight of the day 365 days ago.
		$signups_since = gmdate( 'Y-m-d 00:00:00', $now - self::SIGNUPS_WINDOW_DAYS * DAY_IN_SECONDS );

		$result = Repository::aggregate_by_product( $per_page, $offset, $notified_since, $signups_since );

		$pairs = array();
		$ids   = array();
		foreach ( $result['items'] as $row ) {
			$product_id   = (int) $row->product_id;
			$variation_id = (int) $row->variation_id;
			$pairs[]      = array( $product_id, $variation_id );
			$ids[]        = $product_id;
			if ( $variation_id ) {
				$ids[] = $variation_id;
			}
		}

		$signups = self::index_signups( Repository::signups_per_day( $pairs, $signups_since ) );
		$skus    = self::lookup_skus( array_unique( $ids ) );

		$items = array();
		foreach ( $result['items'] as $row ) {
			$product_id   = (int) $row->product_id;
			$variation_id = (int) $row->variation_id;
			$key          = $product_id . ':' . $variation_id;

			$sku = '';
			if ( $variation_id && isset( $skus[ $variation_id ] ) && '' !== $skus[ $variation_id ] ) {
				$sku = $skus[ $variation_id ];
			} elseif ( isset( $skus[ $product_id ] ) ) {
				$sku = $skus[ $product_id ];
			}

			$items[] = array(
				'sku'          => $sku,
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'waiting'      => (int) $row->waiting,
				'notified_30d' => (int) $row->notified_recent,
				'signups'      => isset( $signups[ $key ] ) ? $signups[ $key ] : array(),
			);
		}

		$total = (int) $result['total'];

		$response = new WP_REST_Response(
			array(
				'generated_at' => gmdate( 'c', $now ),
				'page'         => $page,
				'per_page'     => $per_page,
				'total_pages'  => (int) ceil( $total / $per_page ),
				'total_items'  => $total,
				'items'        => $items,
			)
		);
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/**
	 * Index per-day signup rows by "product_id:variation_id".
	 *
	 * @param array<int, object> $rows Rows from Repository::signups_per_day().
	 * @return array<string, array<string, int>>
	 */
	private static function index_signups( array $rows ) {
		$indexed = array();
		foreach ( $rows as $row ) {
			$key = (int) $row->product_id . ':' . (int) $row->variation_id;
			if ( ! isset( $indexed[ $key ] ) ) {
				$indexed[ $key ] = array();
			}
			$indexed[ $key ][ (string) $row->day ] = (int) $row->cnt;
		}
		return $indexed;
	}

	/**
	 * Fetch SKUs for a set of product/variation IDs in one query.
	 *
	 * @param array<int, int> $ids Post IDs.
	 * @return array<int, string> Map of post ID => SKU ('' when none).
	 */
	private static function lookup_skus( array $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( empty( $ids ) ) {
			return array();
		}

		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id IN ( $placeholders )", '_sku', ...$ids ) );

		$skus = array();
		foreach ( (array) $rows as $row ) {
			$skus[ (int) $row->post_id ] = (string) $row->meta_value;
		}
		return $skus;
	}
}
