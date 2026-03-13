<?php
/**
 * Database repository for subscriptions.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All $wpdb CRUD operations for the bisn_subscriptions table.
 */
class Repository {

	/**
	 * Insert or reactivate a subscription.
	 *
	 * @param array<string, mixed> $data Subscription data.
	 * @return int|false The subscription ID or false on failure.
	 */
	public static function upsert( $data ) {
		global $wpdb;

		$product_id   = absint( $data['product_id'] );
		$variation_id = isset( $data['variation_id'] ) ? absint( $data['variation_id'] ) : 0;
		$email        = sanitize_email( $data['email'] );
		$quantity     = isset( $data['quantity'] ) ? absint( $data['quantity'] ) : 1;
		$ip           = isset( $data['ip_address'] ) ? sanitize_text_field( $data['ip_address'] ) : '';
		$gdpr         = ! empty( $data['gdpr_consent'] ) ? 1 : 0;
		$token        = isset( $data['unsubscribe_token'] ) ? sanitize_text_field( $data['unsubscribe_token'] ) : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, status FROM {$wpdb->prefix}bisn_subscriptions WHERE product_id = %d AND variation_id = %d AND email = %s",
				$product_id,
				$variation_id,
				$email
			)
		);

		if ( $existing ) {
			if ( 'active' === $existing->status ) {
				return 0; // Already subscribed.
			}
			// Reactivate: use raw SQL so notified_at is set to real NULL, not empty string.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}bisn_subscriptions SET status = %s, quantity_requested = %d, ip_address = %s, gdpr_consent = %d, unsubscribe_token = %s, created_at = %s, notified_at = NULL WHERE id = %d",
					'active',
					$quantity,
					$ip,
					$gdpr,
					$token,
					current_time( 'mysql', true ),
					$existing->id
				)
			);
			return (int) $existing->id;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'bisn_subscriptions',
			array(
				'product_id'         => $product_id,
				'variation_id'       => $variation_id,
				'email'              => $email,
				'quantity_requested' => $quantity,
				'status'             => 'active',
				'ip_address'         => $ip,
				'gdpr_consent'       => $gdpr,
				'unsubscribe_token'  => $token,
				'created_at'         => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get active subscriptions for a product.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID (0 for simple).
	 * @param int $limit        Batch limit.
	 * @param int $offset       Offset.
	 * @return array<int, object>
	 */
	public static function get_active_for_product( $product_id, $variation_id = 0, $limit = 50, $offset = 0 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bisn_subscriptions WHERE product_id = %d AND variation_id = %d AND status = %s ORDER BY created_at ASC LIMIT %d OFFSET %d",
				absint( $product_id ),
				absint( $variation_id ),
				'active',
				absint( $limit ),
				absint( $offset )
			)
		);
	}

	/**
	 * Check if active subscriptions exist for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function has_active_subscriptions( $product_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bisn_subscriptions WHERE product_id = %d AND status = %s",
				absint( $product_id ),
				'active'
			)
		);

		return $count > 0;
	}

	/**
	 * Mark a subscription as notified.
	 *
	 * @param int $id Subscription ID.
	 * @return bool
	 */
	public static function mark_notified( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->update(
			$wpdb->prefix . 'bisn_subscriptions',
			array(
				'status'      => 'notified',
				'notified_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Unsubscribe by token.
	 *
	 * @param string $token Unsubscribe token.
	 * @return bool
	 */
	public static function unsubscribe_by_token( $token ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->update(
			$wpdb->prefix . 'bisn_subscriptions',
			array( 'status' => 'unsubscribed' ),
			array(
				'unsubscribe_token' => sanitize_text_field( $token ),
				'status'            => 'active',
			),
			array( '%s' ),
			array( '%s', '%s' )
		);
	}

	/**
	 * Get subscription by token.
	 *
	 * @param string $token Unsubscribe token.
	 * @return object|null
	 */
	public static function get_by_token( $token ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bisn_subscriptions WHERE unsubscribe_token = %s",
				sanitize_text_field( $token )
			)
		);
	}

	/**
	 * Delete old notified subscriptions.
	 *
	 * @param int $days Days to keep. 0 = never cleanup.
	 * @return int Number of deleted rows.
	 */
	public static function cleanup_old( $days ) {
		$days = absint( $days );
		if ( 0 === $days ) {
			return 0;
		}

		global $wpdb;

		$total = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted = (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->prefix}bisn_subscriptions WHERE status = %s AND notified_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY) LIMIT 1000",
					'notified',
					$days
				)
			);
			$total += $deleted;
		} while ( $deleted >= 1000 );

		return $total;
	}

	/**
	 * Count subscriptions by status.
	 *
	 * @return array<string, int>
	 */
	public static function count_by_status() {
		global $wpdb;

		// No user input in this query; table name is from trusted $wpdb->prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT status, COUNT(*) as cnt FROM {$wpdb->prefix}bisn_subscriptions GROUP BY status"
		);

		$counts = array(
			'active'       => 0,
			'notified'     => 0,
			'unsubscribed' => 0,
		);
		foreach ( $rows as $row ) {
			$counts[ $row->status ] = (int) $row->cnt;
		}
		return $counts;
	}

	/**
	 * Get top products by active subscription count.
	 *
	 * @param int $limit  Number of products per page.
	 * @param int $offset Offset for pagination.
	 * @return array{items: array<int, object>, total: int}
	 */
	public static function top_products( $limit = 20, $offset = 0 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM (SELECT product_id, variation_id FROM {$wpdb->prefix}bisn_subscriptions WHERE status = %s GROUP BY product_id, variation_id) AS t",
				'active'
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, variation_id, COUNT(*) as sub_count FROM {$wpdb->prefix}bisn_subscriptions WHERE status = %s GROUP BY product_id, variation_id ORDER BY sub_count DESC LIMIT %d OFFSET %d",
				'active',
				absint( $limit ),
				absint( $offset )
			)
		);

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Get paginated subscriptions for admin list.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array{items: array<int, object>, total: int}
	 */
	public static function get_admin_list( $args ) {
		global $wpdb;

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_text_field( $args['status'] );
		}

		if ( ! empty( $args['product_id'] ) ) {
			$where[]  = 'product_id = %d';
			$values[] = absint( $args['product_id'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'email LIKE %s';
			$values[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		}

		$where_sql = implode( ' AND ', $where );

		$orderby_map = array(
			'id'         => 'id',
			'email'      => 'email',
			'product_id' => 'product_id',
			'status'     => 'status',
			'created_at' => 'created_at',
		);
		$orderby = isset( $args['orderby'], $orderby_map[ $args['orderby'] ] )
			? $orderby_map[ $args['orderby'] ]
			: 'created_at';
		$order   = isset( $args['order'] ) && 'asc' === strtolower( $args['order'] ) ? 'ASC' : 'DESC';

		$per_page = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 20;
		$offset   = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}bisn_subscriptions WHERE {$where_sql}";
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_query, ...$values ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			$total = (int) $wpdb->get_var( $count_query );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$select_query = "SELECT * FROM {$wpdb->prefix}bisn_subscriptions WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$select_vals  = array_merge( $values, array( $per_page, $offset ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$items = $wpdb->get_results( $wpdb->prepare( $select_query, ...$select_vals ) );

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Bulk delete subscriptions.
	 *
	 * @param array<int, int> $ids Subscription IDs.
	 * @return int Number of deleted rows.
	 */
	public static function bulk_delete( $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}bisn_subscriptions WHERE id IN ($placeholders)", ...$ids ) );
	}

	/**
	 * Delete all subscriptions.
	 *
	 * Requires explicit confirmation to prevent accidental data loss.
	 *
	 * @param bool $confirm Must be true to execute.
	 * @return int Number of deleted rows.
	 */
	public static function delete_all( $confirm = false ) {
		if ( ! $confirm ) {
			return 0;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query( "DELETE FROM {$wpdb->prefix}bisn_subscriptions" );
	}

	/**
	 * Bulk mark as notified.
	 *
	 * @param array<int, int> $ids Subscription IDs.
	 * @return int Number of updated rows.
	 */
	public static function bulk_mark_notified( $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$now          = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}bisn_subscriptions SET status = 'notified', notified_at = %s WHERE id IN ($placeholders)", $now, ...$ids ) );
	}

	/**
	 * Count rate-limited IPs.
	 *
	 * @param string $ip IP address.
	 * @return int
	 */
	public static function count_recent_by_ip( $ip ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bisn_subscriptions WHERE ip_address = %s AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)",
				sanitize_text_field( $ip )
			)
		);
	}
}
