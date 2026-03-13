<?php
/**
 * Dashboard tab with stats cards and top products.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeltoftInStockNotifier\Subscription\Repository;
use BeltoftInStockNotifier\Stock\NotificationQueue;
use BeltoftInStockNotifier\Logging\LogViewer;

/**
 * Renders the Dashboard admin tab.
 */
class DashboardTab {

	/**
	 * Render the dashboard.
	 *
	 * @return void
	 */
	public static function render() {
		self::handle_manual_send();

		if ( ! empty( $_GET['bisn_sent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Notifications queued. They will be sent shortly via cron.', 'beltoft-in-stock-notifier' );
			echo '</p></div>';
		}

		$counts = Repository::count_by_status();

		echo '<div class="bisn-stats-cards">';
		$c = AdminPage::STATUS_COLORS;
		self::stat_card( __( 'Active Subscribers', 'beltoft-in-stock-notifier' ), $counts['active'], $c['active'] );
		echo '</div>';

		/**
		 * Fires after the dashboard stats cards.
		 *
		 * Used by Pro to inject conversion tracking stats.
		 */
		do_action( 'bisn_dashboard_after_stats' );

		/* ── Product search ────────────────────────────────── */
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );

		$search_product_id = isset( $_GET['bisn_product'] ) ? absint( $_GET['bisn_product'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<h2>' . esc_html__( 'Look Up Product Subscribers', 'beltoft-in-stock-notifier' ) . '</h2>';
		echo '<form method="get" style="margin-bottom:20px;">';
		echo '<input type="hidden" name="page" value="' . esc_attr( AdminPage::PAGE_SLUG ) . '" />';
		echo '<input type="hidden" name="tab" value="dashboard" />';

		$search_product_name = '';
		if ( $search_product_id ) {
			$p = wc_get_product( $search_product_id );
			if ( $p ) {
				$sku = $p->get_sku();
				$search_product_name = $p->get_name() . ( $sku ? ' (SKU: ' . $sku . ')' : '' ) . ' (#' . $search_product_id . ')';
			}
		}
		echo '<select class="wc-product-search" name="bisn_product" data-placeholder="' . esc_attr__( 'Search by product name or SKU...', 'beltoft-in-stock-notifier' ) . '" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true" style="min-width:350px;">';
		if ( $search_product_id && $search_product_name ) {
			echo '<option value="' . absint( $search_product_id ) . '" selected>' . esc_html( $search_product_name ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Look Up', 'beltoft-in-stock-notifier' ), 'secondary', 'bisn_lookup', false );
		echo '</form>';

		if ( $search_product_id ) {
			self::render_product_subscribers( $search_product_id );
		}

		/* ── Top products ─────────────────────────────────── */
		echo '<h2>' . esc_html__( 'Top Products by Active Subscriptions', 'beltoft-in-stock-notifier' ) . '</h2>';

		$per_page  = 20;
		$top_paged = isset( $_GET['top_paged'] ) ? max( 1, absint( $_GET['top_paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offset    = ( $top_paged - 1 ) * $per_page;

		$result      = Repository::top_products( $per_page, $offset );
		$top         = $result['items'];
		$total       = $result['total'];
		$total_pages = (int) ceil( $total / $per_page );

		if ( empty( $top ) && 1 === $top_paged ) {
			echo '<p>' . esc_html__( 'No active subscriptions yet.', 'beltoft-in-stock-notifier' ) . '</p>';
			return;
		}

		// Batch-load parent products to avoid N+1 queries.
		$product_ids = array_map( function ( $row ) { return absint( $row->product_id ); }, $top );
		$products_list = wc_get_products( array( 'include' => $product_ids, 'limit' => -1 ) );
		$product_map = array();
		foreach ( $products_list as $isn_product ) {
			$product_map[ $isn_product->get_id() ] = $isn_product;
		}

		// Batch-load brands if the product_brand taxonomy exists (WooCommerce 9.4+).
		$show_brands      = taxonomy_exists( 'product_brand' );
		$brands_by_product = array();
		if ( $show_brands && ! empty( $product_ids ) ) {
			$brand_terms = wp_get_object_terms( $product_ids, 'product_brand', array( 'fields' => 'all_with_object_id' ) );
			if ( ! is_wp_error( $brand_terms ) ) {
				foreach ( $brand_terms as $term ) {
					$brands_by_product[ $term->object_id ][] = $term->name;
				}
			}
		}

		// Batch-load variations where needed.
		$variation_ids = array();
		foreach ( $top as $row ) {
			if ( absint( $row->variation_id ) > 0 ) {
				$variation_ids[] = absint( $row->variation_id );
			}
		}
		$variation_map = array();
		if ( ! empty( $variation_ids ) ) {
			$variations_list = wc_get_products( array( 'include' => $variation_ids, 'type' => 'variation', 'limit' => -1 ) );
			foreach ( $variations_list as $isn_variation ) {
				$variation_map[ $isn_variation->get_id() ] = $isn_variation;
			}
		}

		echo '<table class="widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Product', 'beltoft-in-stock-notifier' ) . '</th>';
		echo '<th>' . esc_html__( 'SKU', 'beltoft-in-stock-notifier' ) . '</th>';
		if ( $show_brands ) {
			echo '<th>' . esc_html__( 'Brand', 'beltoft-in-stock-notifier' ) . '</th>';
		}
		echo '<th>' . esc_html__( 'Variation', 'beltoft-in-stock-notifier' ) . '</th>';
		echo '<th>' . esc_html__( 'Subscribers', 'beltoft-in-stock-notifier' ) . '</th>';
		echo '<th>' . esc_html__( 'Stock Status', 'beltoft-in-stock-notifier' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'beltoft-in-stock-notifier' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $top as $row ) {
			$product      = isset( $product_map[ $row->product_id ] ) ? $product_map[ $row->product_id ] : null;
			$name         = $product ? $product->get_name() : ( '#' . $row->product_id );
			$edit         = $product ? get_edit_post_link( $row->product_id ) : '';
			$variation_id = absint( $row->variation_id );

			// Use variation stock status when a specific variation is tracked.
			if ( $variation_id > 0 && isset( $variation_map[ $variation_id ] ) ) {
				$stock_raw = $variation_map[ $variation_id ]->get_stock_status();
			} elseif ( $product ) {
				$stock_raw = $product->get_stock_status();
			} else {
				$stock_raw = '';
			}
			$stock = self::stock_label( $stock_raw );

			echo '<tr>';
			echo '<td>';
			if ( $edit ) {
				echo '<a href="' . esc_url( $edit ) . '">' . esc_html( $name ) . '</a>';
			} else {
				echo esc_html( $name );
			}
			echo '</td>';
			echo '<td>';
			$sku = '';
			if ( $variation_id > 0 && isset( $variation_map[ $variation_id ] ) ) {
				$sku = $variation_map[ $variation_id ]->get_sku();
			}
			if ( ! $sku && $product ) {
				$sku = $product->get_sku();
			}
			echo $sku ? esc_html( $sku ) : '—';
			echo '</td>';
			if ( $show_brands ) {
				$brand_names = isset( $brands_by_product[ $row->product_id ] ) ? $brands_by_product[ $row->product_id ] : array();
				echo '<td>' . ( ! empty( $brand_names ) ? esc_html( implode( ', ', $brand_names ) ) : '—' ) . '</td>';
			}
			echo '<td>';
			if ( $variation_id > 0 && isset( $variation_map[ $variation_id ] ) ) {
				$attrs = $variation_map[ $variation_id ]->get_attributes();
				echo esc_html( implode( ', ', array_filter( $attrs ) ) );
				echo ' <small>(#' . absint( $variation_id ) . ')</small>';
			} elseif ( $variation_id > 0 ) {
				echo '#' . absint( $variation_id );
			} else {
				echo '—';
			}
			echo '</td>';
			echo '<td><strong>' . absint( $row->sub_count ) . '</strong></td>';
			echo '<td>' . esc_html( $stock ) . '</td>';
			echo '<td>';
			$send_url = wp_nonce_url(
				add_query_arg(
					array(
						'page'         => AdminPage::PAGE_SLUG,
						'tab'          => 'dashboard',
						'bisn_action'  => 'manual_send',
						'product_id'   => $row->product_id,
						'variation_id' => $row->variation_id,
					),
					admin_url( 'admin.php' )
				),
				'bisn_manual_send'
			);
			echo '<a href="' . esc_url( $send_url ) . '" class="button button-small" onclick="return confirm(\'' . esc_js( __( 'Send notifications to all active subscribers for this product?', 'beltoft-in-stock-notifier' ) ) . '\');">';
			echo esc_html__( 'Send Notifications', 'beltoft-in-stock-notifier' );
			echo '</a>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		/* ── Pagination ──────────────────────────────────── */
		if ( $total_pages > 1 ) {
			$base_url = add_query_arg(
				array(
					'page' => AdminPage::PAGE_SLUG,
					'tab'  => 'dashboard',
				),
				admin_url( 'admin.php' )
			);

			echo '<div class="tablenav bottom"><div class="tablenav-pages">';
			/* translators: %s: total number of products */
			echo '<span class="displaying-num">' . esc_html( sprintf( _n( '%s product', '%s products', $total, 'beltoft-in-stock-notifier' ), number_format_i18n( $total ) ) ) . '</span>';
			echo '<span class="pagination-links">';

			if ( $top_paged > 1 ) {
				echo '<a class="prev-page button" href="' . esc_url( add_query_arg( 'top_paged', $top_paged - 1, $base_url ) ) . '">&lsaquo;</a> ';
			} else {
				echo '<span class="tablenav-pages-navspan button disabled">&lsaquo;</span> ';
			}

			/* translators: 1: current page, 2: total pages */
			echo '<span class="paging-input">' . esc_html( sprintf( __( '%1$d of %2$d', 'beltoft-in-stock-notifier' ), $top_paged, $total_pages ) ) . '</span>';

			if ( $top_paged < $total_pages ) {
				echo ' <a class="next-page button" href="' . esc_url( add_query_arg( 'top_paged', $top_paged + 1, $base_url ) ) . '">&rsaquo;</a>';
			} else {
				echo ' <span class="tablenav-pages-navspan button disabled">&rsaquo;</span>';
			}

			echo '</span></div></div>';
		}
	}

	/**
	 * Handle manual send action.
	 *
	 * @return void
	 */
	private static function handle_manual_send() {
		if ( ! isset( $_GET['bisn_action'] ) || 'manual_send' !== $_GET['bisn_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		check_admin_referer( 'bisn_manual_send' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'beltoft-in-stock-notifier' ) );
		}

		$product_id   = isset( $_GET['product_id'] ) ? absint( $_GET['product_id'] ) : 0;
		$variation_id = isset( $_GET['variation_id'] ) ? absint( $_GET['variation_id'] ) : 0;

		if ( ! $product_id ) {
			return;
		}

		NotificationQueue::enqueue( $product_id, $variation_id );
		LogViewer::log( 'MANUAL_SEND queued product_id=' . $product_id . ' variation_id=' . $variation_id );

		$redirect = add_query_arg(
			array(
				'page'         => AdminPage::PAGE_SLUG,
				'tab'          => 'dashboard',
				'bisn_sent'    => '1',
				'product_id'   => $product_id,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render subscribers for a specific product.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return void
	 */
	private static function render_product_subscribers( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			echo '<p>' . esc_html__( 'Product not found.', 'beltoft-in-stock-notifier' ) . '</p>';
			return;
		}

		$sku  = $product->get_sku();
		$name = $product->get_name();

		// Determine if this is a variation or a parent product.
		$is_variation = $product->is_type( 'variation' );
		if ( $is_variation ) {
			$query_product_id   = $product->get_parent_id();
			$query_variation_id = $product_id;
		} else {
			$query_product_id   = $product_id;
			$query_variation_id = 0;
		}

		// Get subscriptions for this product (any status).
		$per_page = 100;
		$result   = Repository::get_admin_list( array(
			'product_id' => $query_product_id,
			'per_page'   => $per_page,
			'offset'     => 0,
		) );

		// If variation, filter to that specific variation.
		$items = $result['items'];
		if ( $is_variation ) {
			$items = array_filter( $items, function ( $item ) use ( $query_variation_id ) {
				return absint( $item->variation_id ) === $query_variation_id;
			} );
		}

		echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:15px 20px;margin-bottom:20px;">';
		echo '<h3 style="margin-top:0;">' . esc_html( $name );
		if ( $sku ) {
			echo ' <span style="color:#666;font-weight:normal;">(SKU: ' . esc_html( $sku ) . ')</span>';
		}
		echo '</h3>';

		if ( $result['total'] > $per_page ) {
			echo '<p style="color:#d63638;"><strong>';
			/* translators: 1: displayed count, 2: total count */
			echo esc_html( sprintf( __( 'Showing first %1$d of %2$d subscribers. Use the Subscriptions tab for full results.', 'beltoft-in-stock-notifier' ), $per_page, $result['total'] ) );
			echo '</strong></p>';
		}

		if ( empty( $items ) ) {
			echo '<p>' . esc_html__( 'No subscribers for this product.', 'beltoft-in-stock-notifier' ) . '</p>';
		} else {
			echo '<table class="widefat fixed striped">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Email', 'beltoft-in-stock-notifier' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'beltoft-in-stock-notifier' ) . '</th>';
			echo '<th>' . esc_html__( 'Subscribed', 'beltoft-in-stock-notifier' ) . '</th>';
			echo '<th>' . esc_html__( 'Notified', 'beltoft-in-stock-notifier' ) . '</th>';
			echo '</tr></thead><tbody>';

			$colors = AdminPage::STATUS_COLORS;
			foreach ( $items as $item ) {
				$color = isset( $colors[ $item->status ] ) ? $colors[ $item->status ] : '#666';
				echo '<tr>';
				echo '<td>' . esc_html( $item->email ) . '</td>';
				echo '<td><span style="color:' . esc_attr( $color ) . ';font-weight:600;">' . esc_html( ucfirst( $item->status ) ) . '</span></td>';
				echo '<td>' . esc_html( $item->created_at ) . '</td>';
				echo '<td>' . ( $item->notified_at ? esc_html( $item->notified_at ) : '—' ) . '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		echo '</div>';
	}

	/**
	 * Render a stat card.
	 *
	 * @param string $label Card label.
	 * @param int    $value Card value.
	 * @param string $color Border color.
	 * @return void
	 */
	private static function stat_card( $label, $value, $color ) {
		echo '<div class="bisn-stat-card" style="border-left-color:' . esc_attr( $color ) . ';">';
		echo '<div class="bisn-stat-value">' . absint( $value ) . '</div>';
		echo '<div class="bisn-stat-label">' . esc_html( $label ) . '</div>';
		echo '</div>';
	}

	/**
	 * Convert WooCommerce stock status slug to readable label.
	 *
	 * @param string $status Raw stock status.
	 * @return string
	 */
	private static function stock_label( $status ) {
		$labels = array(
			'instock'     => __( 'In stock', 'beltoft-in-stock-notifier' ),
			'outofstock'  => __( 'Out of stock', 'beltoft-in-stock-notifier' ),
			'onbackorder' => __( 'On backorder', 'beltoft-in-stock-notifier' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}
