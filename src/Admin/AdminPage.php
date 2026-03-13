<?php
/**
 * Admin page controller with tab routing.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the WooCommerce submenu page and routes to tab renderers.
 */
class AdminPage {

	const PAGE_SLUG = 'bisn-notifier';

	const STATUS_COLORS = array(
		'active'       => '#0073aa',
		'notified'     => '#46b450',
		'unsubscribed' => '#dc3232',
	);

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
	}

	/**
	 * Register the submenu page under WooCommerce.
	 *
	 * @return void
	 */
	public static function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'In-Stock Notifier', 'beltoft-in-stock-notifier' ),
			__( 'In-Stock Notifier', 'beltoft-in-stock-notifier' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render the admin page with tab navigation.
	 *
	 * @return void
	 */
	public static function render_page() {
		$tabs = array(
			'dashboard'     => __( 'Dashboard', 'beltoft-in-stock-notifier' ),
			'subscriptions' => __( 'Subscriptions', 'beltoft-in-stock-notifier' ),
			'settings'      => __( 'Settings', 'beltoft-in-stock-notifier' ),
		);

		/**
		 * Filter admin page tabs so add-ons can register their own.
		 *
		 * @param array<string, string> $tabs Slug => label pairs.
		 */
		$tabs = apply_filters( 'bisn_admin_tabs', $tabs );

		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $current_tab ] ) ) {
			$current_tab = 'dashboard';
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'In-Stock Notifier', 'beltoft-in-stock-notifier' ) . '</h1>';

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url   = add_query_arg(
				array(
					'page' => self::PAGE_SLUG,
					'tab'  => $slug,
				),
				admin_url( 'admin.php' )
			);
			$class = ( $slug === $current_tab ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">';
			echo esc_html( $label );
			echo '</a>';
		}
		echo '</nav>';

		echo '<div class="bisn-tab-content" style="margin-top:20px;">';

		switch ( $current_tab ) {
			case 'dashboard':
				DashboardTab::render();
				break;
			case 'subscriptions':
				SubscriptionsTab::render();
				break;
			case 'settings':
				SettingsTab::render();
				break;
			default:
				/**
				 * Render a custom admin tab registered by an add-on.
				 *
				 * @param string $current_tab The active tab slug.
				 */
				do_action( "bisn_admin_tab_{$current_tab}" );
				break;
		}

		echo '</div>';
		echo '</div>';
	}
}
