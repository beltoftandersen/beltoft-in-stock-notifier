<?php
/**
 * Central plugin initialization.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeltoftInStockNotifier\Admin\AdminPage;
use BeltoftInStockNotifier\Frontend\FormRenderer;
use BeltoftInStockNotifier\Frontend\AjaxHandler;
use BeltoftInStockNotifier\Frontend\Shortcode;
use BeltoftInStockNotifier\Stock\StockListener;
use BeltoftInStockNotifier\Stock\NotificationSender;
use BeltoftInStockNotifier\Unsubscribe\Handler as UnsubscribeHandler;
use BeltoftInStockNotifier\Email\BackInStockEmail;
use BeltoftInStockNotifier\Support\Installer;
use BeltoftInStockNotifier\Support\Options;

/**
 * Plugin bootstrap.
 */
class Plugin {

	/**
	 * Initialize all components.
	 *
	 * @return void
	 */
	public static function init() {
		Options::init();
		self::maybe_upgrade_db();

		if ( is_admin() ) {
			AdminPage::init();
		}

		FormRenderer::init();
		AjaxHandler::init();
		Shortcode::init();
		StockListener::init();
		NotificationSender::init();
		UnsubscribeHandler::init();

		/* Register back-in-stock email with WooCommerce. */
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register_email_class' ) );

		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Run database migrations if DB version is outdated.
	 *
	 * @return void
	 */
	private static function maybe_upgrade_db() {
		$current = get_option( 'bisn_db_version', '1.0.0' );
		if ( version_compare( $current, BISN_DB_VERSION, '>=' ) ) {
			return;
		}

		/*
		 * 1.1.4: Strip stale English text defaults from saved options so that
		 * get_all() falls through to the __()-translated defaults. Only text
		 * fields that still match the original English value are removed;
		 * admin-customised values are preserved.
		 */
		if ( version_compare( $current, '1.1.4', '<' ) ) {
			self::migrate_strip_text_defaults();
		}

		Installer::activate();
	}

	/**
	 * Remove text fields from saved options when they match the English default.
	 *
	 * @return void
	 */
	private static function migrate_strip_text_defaults() {
		$saved = get_option( Options::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			return;
		}

		$english = array(
			'gdpr_text'             => 'I agree to be notified when this product is back in stock.',
			'success_message'       => 'You will be notified when this product is back in stock.',
			'already_subscribed_msg' => 'You are already subscribed for this product.',
			'heading_text'          => 'Want to know when it\'s back? Leave your email below.',
			'button_text'           => 'Notify Me',
		);

		$changed = false;
		foreach ( $english as $key => $en_value ) {
			if ( isset( $saved[ $key ] ) && $saved[ $key ] === $en_value ) {
				unset( $saved[ $key ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( Options::OPTION, $saved );
		}
	}

	/**
	 * Register the back-in-stock email class with WooCommerce.
	 *
	 * @param array $email_classes Existing email classes.
	 * @return array
	 */
	public static function register_email_class( $email_classes ) {
		$email_classes['BISN_Back_In_Stock'] = new BackInStockEmail();
		return $email_classes;
	}

	/**
	 * Enqueue admin CSS and JS on the plugin page only.
	 *
	 * @param string $hook_suffix The admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( 'woocommerce_page_bisn-notifier' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'bisn-admin',
			BISN_URL . 'assets/css/admin.css',
			array(),
			BISN_VERSION
		);

		wp_enqueue_script(
			'bisn-admin',
			BISN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BISN_VERSION,
			true
		);

		$js = 'document.querySelector(\'[name="bisn_apply_bulk"]\')&&document.querySelector(\'[name="bisn_apply_bulk"]\').addEventListener("click",function(e){var s=document.querySelector(\'[name="bisn_bulk_action"]\');if(s&&s.value==="delete_all"&&!confirm("' . esc_js( __( 'Are you sure you want to delete ALL subscriptions? This cannot be undone.', 'beltoft-in-stock-notifier' ) ) . '"))e.preventDefault();});';
		wp_add_inline_script( 'bisn-admin', $js );
	}

	/**
	 * Enqueue frontend CSS and JS on product pages with OOS items.
	 *
	 * @return void
	 */
	public static function enqueue_frontend_assets() {
		if ( ! is_product() ) {
			return;
		}

		if ( empty( Options::get_all()['enabled'] ) ) {
			return;
		}

		$isn_product = wc_get_product( get_the_ID() );
		if ( ! $isn_product ) {
			return;
		}

		$needs_assets = false;
		if ( ! $isn_product->is_in_stock() ) {
			$needs_assets = true;
		} elseif ( $isn_product->is_type( 'variable' ) ) {
			$needs_assets = true;
		}

		if ( ! $needs_assets ) {
			return;
		}

		FormRenderer::enqueue_assets();
	}
}
