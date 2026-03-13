<?php
/**
 * Subscriptions admin tab with WP_List_Table.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeltoftInStockNotifier\Subscription\Repository;

/**
 * Renders and handles the Subscriptions list table.
 */
class SubscriptionsTab {

	/**
	 * Render the subscriptions tab.
	 *
	 * @return void
	 */
	public static function render() {
		self::handle_bulk_actions();

		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		$table = new SubscriptionsListTable();
		$table->prepare_items();

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( AdminPage::PAGE_SLUG ) . '" />';
		echo '<input type="hidden" name="tab" value="subscriptions" />';
		$table->search_box( esc_html__( 'Search Email', 'beltoft-in-stock-notifier' ), 'bisn-search' );
		$table->display();
		echo '</form>';
	}

	/**
	 * Process bulk actions.
	 *
	 * Uses $_REQUEST so it works with both GET (form) and POST submissions.
	 *
	 * @return void
	 */
	private static function handle_bulk_actions() {
		// Handle single-row delete via row action link.
		if ( isset( $_GET['bisn_action'], $_GET['bisn_id'] ) && 'delete' === $_GET['bisn_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			check_admin_referer( 'bisn_delete_' . absint( $_GET['bisn_id'] ) );

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'You do not have permission to do this.', 'beltoft-in-stock-notifier' ) );
			}

			$count = Repository::bulk_delete( array( absint( $_GET['bisn_id'] ) ) );
			if ( $count ) {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Subscription deleted.', 'beltoft-in-stock-notifier' ) . '</p></div>';
			}
			return;
		}

		// Handle "Delete All" action.
		if ( isset( $_REQUEST['bisn_bulk_action'] ) && 'delete_all' === $_REQUEST['bisn_bulk_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			check_admin_referer( 'bisn_bulk_action_nonce' );

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'You do not have permission to do this.', 'beltoft-in-stock-notifier' ) );
			}

			$count = Repository::delete_all( true );
			echo '<div class="notice notice-success"><p>';
			/* translators: %d: number of deleted subscriptions */
			echo esc_html( sprintf( _n( '%d subscription deleted.', '%d subscriptions deleted.', $count, 'beltoft-in-stock-notifier' ), $count ) );
			echo '</p></div>';
			return;
		}

		if ( ! isset( $_REQUEST['bisn_bulk_action'] ) || empty( $_REQUEST['bisn_ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		check_admin_referer( 'bisn_bulk_action_nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'beltoft-in-stock-notifier' ) );
		}

		$action = sanitize_text_field( wp_unslash( $_REQUEST['bisn_bulk_action'] ) );
		$ids    = array_map( 'absint', (array) $_REQUEST['bisn_ids'] );

		switch ( $action ) {
			case 'delete':
				$count = Repository::bulk_delete( $ids );
				echo '<div class="notice notice-success"><p>';
				/* translators: %d: number of deleted subscriptions */
				echo esc_html( sprintf( _n( '%d subscription deleted.', '%d subscriptions deleted.', $count, 'beltoft-in-stock-notifier' ), $count ) );
				echo '</p></div>';
				break;

			case 'mark_notified':
				$count = Repository::bulk_mark_notified( $ids );
				echo '<div class="notice notice-success"><p>';
				/* translators: %d: number of updated subscriptions */
				echo esc_html( sprintf( _n( '%d subscription marked as notified.', '%d subscriptions marked as notified.', $count, 'beltoft-in-stock-notifier' ), $count ) );
				echo '</p></div>';
				break;
		}
	}
}

/**
 * WP_List_Table implementation for subscriptions.
 */
class SubscriptionsListTable extends \WP_List_Table {

	/**
	 * Product lookup map to avoid N+1 queries.
	 *
	 * @var array<int, \WC_Product>
	 */
	private $product_map = array();

	/**
	 * Variation lookup map to avoid N+1 queries.
	 *
	 * @var array<int, \WC_Product_Variation>
	 */
	private $variation_map = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'subscription',
				'plural'   => 'subscriptions',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'email'      => __( 'Email', 'beltoft-in-stock-notifier' ),
			'product_id' => __( 'Product', 'beltoft-in-stock-notifier' ),
			'status'     => __( 'Status', 'beltoft-in-stock-notifier' ),
			'created_at' => __( 'Subscribed', 'beltoft-in-stock-notifier' ),
			'notified_at' => __( 'Notified', 'beltoft-in-stock-notifier' ),
		);
	}

	/**
	 * Get sortable columns.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	public function get_sortable_columns() {
		return array(
			'email'      => array( 'email', false ),
			'product_id' => array( 'product_id', false ),
			'status'     => array( 'status', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Prepare items for display.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page = 20;
		$paged    = $this->get_pagenum();

		$args = array(
			'per_page' => $per_page,
			'offset'   => ( $paged - 1 ) * $per_page,
			'orderby'  => isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'created_at', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'order'    => isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		if ( ! empty( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['search'] = sanitize_text_field( wp_unslash( $_GET['s'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! empty( $_GET['status_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['status'] = sanitize_text_field( wp_unslash( $_GET['status_filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( ! empty( $_GET['product_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['product_id'] = absint( $_GET['product_filter'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$result = Repository::get_admin_list( $args );

		$this->items = $result['items'];

		// Batch-load all products to avoid N+1 queries.
		$product_ids = array_unique( array_map( function ( $item ) { return absint( $item->product_id ); }, $this->items ) );
		if ( ! empty( $product_ids ) ) {
			$products = wc_get_products( array( 'include' => $product_ids, 'limit' => -1 ) );
			foreach ( $products as $isn_product ) {
				$this->product_map[ $isn_product->get_id() ] = $isn_product;
			}
		}

		// Batch-load all variations.
		$variation_ids = array_unique( array_filter( array_map( function ( $item ) { return absint( $item->variation_id ); }, $this->items ) ) );
		if ( ! empty( $variation_ids ) ) {
			$variations_list = wc_get_products( array( 'include' => $variation_ids, 'type' => 'variation', 'limit' => -1 ) );
			foreach ( $variations_list as $isn_variation ) {
				$this->variation_map[ $isn_variation->get_id() ] = $isn_variation;
			}
		}

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $result['total'] / $per_page ),
			)
		);

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}

	/**
	 * Extra controls before the table.
	 *
	 * @param string $which Top or bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$current_status = isset( $_GET['status_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['status_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="alignleft actions">';
		echo '<select name="status_filter">';
		echo '<option value="">' . esc_html__( 'All statuses', 'beltoft-in-stock-notifier' ) . '</option>';
		foreach ( array( 'active', 'notified', 'unsubscribed' ) as $status ) {
			echo '<option value="' . esc_attr( $status ) . '"' . selected( $current_status, $status, false ) . '>';
			echo esc_html( ucfirst( $status ) );
			echo '</option>';
		}
		echo '</select>';
		submit_button( __( 'Filter', 'beltoft-in-stock-notifier' ), '', 'filter_action', false );
		echo '</div>';

		echo '<div class="alignleft actions" style="margin-left:8px;">';
		echo '<select name="bisn_bulk_action">';
		echo '<option value="">' . esc_html__( 'Bulk Actions', 'beltoft-in-stock-notifier' ) . '</option>';
		echo '<option value="delete">' . esc_html__( 'Delete Selected', 'beltoft-in-stock-notifier' ) . '</option>';
		echo '<option value="delete_all">' . esc_html__( 'Delete All Subscriptions', 'beltoft-in-stock-notifier' ) . '</option>';
		echo '<option value="mark_notified">' . esc_html__( 'Mark as Notified', 'beltoft-in-stock-notifier' ) . '</option>';
		echo '</select>';
		wp_nonce_field( 'bisn_bulk_action_nonce' );
		submit_button( __( 'Apply', 'beltoft-in-stock-notifier' ), 'action', 'bisn_apply_bulk', false );
		echo '</div>';
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Row item.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return '<input type="checkbox" name="bisn_ids[]" value="' . absint( $item->id ) . '" />';
	}

	/**
	 * Email column with row actions.
	 *
	 * @param object $item Row item.
	 * @return string
	 */
	protected function column_email( $item ) {
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'page'        => AdminPage::PAGE_SLUG,
					'tab'         => 'subscriptions',
					'bisn_action' => 'delete',
					'bisn_id'     => absint( $item->id ),
				),
				admin_url( 'admin.php' )
			),
			'bisn_delete_' . absint( $item->id )
		);

		$actions = array(
			'delete' => '<a href="' . esc_url( $delete_url ) . '" style="color:#b32d2e;" onclick="return confirm(\'' . esc_js( __( 'Delete this subscription?', 'beltoft-in-stock-notifier' ) ) . '\');">' . esc_html__( 'Delete', 'beltoft-in-stock-notifier' ) . '</a>',
		);

		return esc_html( $item->email ) . $this->row_actions( $actions );
	}

	/**
	 * Product column — shows name and SKU.
	 *
	 * @param object $item Row item.
	 * @return string
	 */
	protected function column_product_id( $item ) {
		$product   = isset( $this->product_map[ $item->product_id ] ) ? $this->product_map[ $item->product_id ] : null;
		$variation = ( absint( $item->variation_id ) > 0 && isset( $this->variation_map[ $item->variation_id ] ) ) ? $this->variation_map[ $item->variation_id ] : null;

		$name = $product ? $product->get_name() : ( '#' . $item->product_id );
		$output = esc_html( $name );

		// Show variation SKU if available, otherwise product SKU.
		$sku = $variation ? $variation->get_sku() : ( $product ? $product->get_sku() : '' );
		if ( $sku ) {
			$output .= '<br><small>' . esc_html__( 'SKU:', 'beltoft-in-stock-notifier' ) . ' ' . esc_html( $sku ) . '</small>';
		}

		return $output;
	}

	/**
	 * Status column.
	 *
	 * @param object $item Row item.
	 * @return string
	 */
	protected function column_status( $item ) {
		$colors = AdminPage::STATUS_COLORS;
		$color  = isset( $colors[ $item->status ] ) ? $colors[ $item->status ] : '#666';
		return '<span style="color:' . esc_attr( $color ) . ';font-weight:600;">' . esc_html( ucfirst( $item->status ) ) . '</span>';
	}

	/**
	 * Created at column.
	 *
	 * @param object $item Row item.
	 * @return string
	 */
	protected function column_created_at( $item ) {
		return esc_html( $item->created_at );
	}

	/**
	 * Notified at column.
	 *
	 * @param object $item Row item.
	 * @return string
	 */
	protected function column_notified_at( $item ) {
		return $item->notified_at ? esc_html( $item->notified_at ) : '—';
	}

	/**
	 * Default column handler.
	 *
	 * @param object $item        Row item.
	 * @param string $column_name Column name.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}

	/**
	 * Message when no items found.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No subscriptions found.', 'beltoft-in-stock-notifier' );
	}
}
