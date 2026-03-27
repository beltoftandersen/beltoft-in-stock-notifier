<?php
/**
 * Renders the subscription form on product pages.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeltoftInStockNotifier\Support\Options;

/**
 * Hooks into WooCommerce to display the notify form.
 */
class FormRenderer {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_simple' ), 31 );
		add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'render_variable' ) );
	}

	/**
	 * Render form for simple / grouped / external products.
	 *
	 * @return void
	 */
	public static function render_simple() {
		$opts = Options::get_all();
		if ( '1' !== $opts['enabled'] || '1' !== $opts['form_position_enabled'] ) {
			return;
		}

		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		if ( $product->is_type( 'variable' ) ) {
			return; // Handled by render_variable.
		}

		if ( $product->is_in_stock() ) {
			return;
		}

		// get_form_html() already applies wp_kses() with allowed_form_html().
		echo self::get_form_html( $product->get_id(), 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized in get_form_html().
	}

	/**
	 * Render hidden form container for variable products.
	 * JS will show/hide based on selected variation stock.
	 *
	 * @return void
	 */
	public static function render_variable() {
		$opts = Options::get_all();
		if ( '1' !== $opts['enabled'] || '1' !== $opts['form_position_enabled'] ) {
			return;
		}

		global $product;
		if ( ! $product instanceof \WC_Product || ! $product->is_type( 'variable' ) ) {
			return;
		}

		// get_form_html() already applies wp_kses() with allowed_form_html().
		echo self::get_form_html( $product->get_id(), 0, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized in get_form_html().
	}

	/**
	 * Enqueue frontend CSS, JS, and localized data.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {
		if ( wp_script_is( 'bisn-frontend', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style(
			'bisn-frontend',
			BISN_URL . 'assets/css/frontend.css',
			array(),
			BISN_VERSION
		);

		wp_enqueue_script(
			'bisn-frontend',
			BISN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BISN_VERSION,
			true
		);

		wp_localize_script(
			'bisn-frontend',
			'bisn_vars',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'bisn_subscribe_nonce' ),
				'error_generic' => esc_html__( 'An error occurred.', 'beltoft-in-stock-notifier' ),
				'error_network' => esc_html__( 'An error occurred. Please try again.', 'beltoft-in-stock-notifier' ),
			)
		);
	}

	/**
	 * Custom kses allowlist for the subscription form HTML.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_form_html() {
		return array(
			'div'    => array(
				'class'       => true,
				'id'          => true,
				'style'       => true,
				'role'        => true,
				'aria-live'   => true,
				'aria-hidden' => true,
				'data-*'      => true,
			),
			'p'      => array(
				'class' => true,
				'id'    => true,
				'style' => true,
			),
			'form'   => array(
				'class'      => true,
				'id'         => true,
				'style'      => true,
				'data-*'     => true,
			),
			'input'  => array(
				'class'        => true,
				'id'           => true,
				'style'        => true,
				'name'         => true,
				'type'         => true,
				'value'        => true,
				'placeholder'  => true,
				'required'     => true,
				'min'          => true,
				'checked'      => true,
				'tabindex'     => true,
				'autocomplete' => true,
				'data-*'       => true,
			),
			'button' => array(
				'class' => true,
				'id'    => true,
				'style' => true,
				'type'  => true,
				'name'  => true,
				'value' => true,
				'data-*' => true,
			),
			'label'  => array(
				'class' => true,
				'id'    => true,
				'style' => true,
				'for'   => true,
			),
			'span'   => array(
				'class'       => true,
				'id'          => true,
				'style'       => true,
				'role'        => true,
				'aria-live'   => true,
				'aria-hidden' => true,
				'data-*'      => true,
			),
		);
	}

	/**
	 * Build the form HTML.
	 *
	 * @param int  $product_id   Product ID.
	 * @param int  $variation_id Variation ID (0 for simple products, set by JS for variable).
	 * @param bool $hidden       Whether to render hidden (for variable products).
	 * @return string
	 */
	public static function get_form_html( $product_id, $variation_id, $hidden = false ) {
		$opts = Options::get_all();

		$heading_default = $opts['heading_text'];

		/**
		 * Filter the heading text.
		 *
		 * @param string $heading Heading text.
		 * @param int    $product_id Product ID.
		 */
		$heading = apply_filters(
			'bisn_form_heading_text',
			$heading_default,
			$product_id
		);

		$hide_attr = $hidden ? ' style="display:none;"' : '';
		$html  = '<div class="bisn-notify-form"' . $hide_attr . '>';
		$html .= '<p class="bisn-form-heading">' . esc_html( $heading ) . '</p>';
		$html .= '<form class="bisn-form" data-bisn-form="1">';

		/* Quantity field (optional) — above the inline row. */
		if ( '1' === $opts['quantity_field_enabled'] ) {
			$html .= '<div class="bisn-field bisn-field-quantity">';
			$html .= '<label for="bisn-quantity-' . absint( $product_id ) . '">' . esc_html__( 'Desired quantity', 'beltoft-in-stock-notifier' ) . '</label>';
			$html .= '<input type="number" id="bisn-quantity-' . absint( $product_id ) . '" name="bisn_quantity" min="1" value="1" class="bisn-quantity-input" />';
			$html .= '</div>';
		}

		/* GDPR checkbox (optional) — above the inline row. */
		if ( '1' === $opts['gdpr_enabled'] ) {
			$html .= '<div class="bisn-field bisn-field-gdpr">';
			$html .= '<label><input type="checkbox" name="bisn_gdpr" value="1" required /> ';
			$html .= esc_html( $opts['gdpr_text'] );
			$html .= '</label>';
			$html .= '</div>';
		}

		/* Inline row: email + submit button side by side. */
		$html .= '<div class="bisn-fields-row">';

		/* Email field — prefill for logged-in users. */
		$user_email = is_user_logged_in() ? wp_get_current_user()->user_email : '';
		$html .= '<label for="bisn-email-' . absint( $product_id ) . '" class="screen-reader-text">' . esc_html__( 'Email address', 'beltoft-in-stock-notifier' ) . '</label>';
		$html .= '<input type="email" id="bisn-email-' . absint( $product_id ) . '" name="bisn_email" placeholder="' . esc_attr__( 'Your email address', 'beltoft-in-stock-notifier' ) . '" value="' . esc_attr( $user_email ) . '" required class="bisn-email-input" />';

		/* Submit button. */
		$html .= '<button type="submit" class="bisn-submit">';
		$html .= esc_html( $opts['button_text'] );
		$html .= '</button>';

		$html .= '</div>'; /* /.bisn-fields-row */

		/* Honeypot. */
		$html .= '<div style="display:none !important;" aria-hidden="true">';
		$html .= '<input type="text" name="bisn_website" tabindex="-1" autocomplete="off" />';
		$html .= '</div>';

		/* Hidden fields. */
		$html .= '<input type="hidden" name="bisn_nonce" value="" />';
		$html .= '<input type="hidden" name="bisn_product_id" value="' . absint( $product_id ) . '" />';
		$html .= '<input type="hidden" name="bisn_variation_id" value="' . absint( $variation_id ) . '" />';

		/**
		 * Allow adding extra form fields.
		 *
		 * @param string $fields     Extra HTML.
		 * @param int    $product_id Product ID.
		 */
		$extra = apply_filters( 'bisn_form_fields', '', $product_id );
		if ( $extra ) {
			$html .= wp_kses_post( $extra );
		}

		$html .= '</form>';
		$html .= '<div class="bisn-form-message" role="status" aria-live="polite"></div>';

		$html .= '</div>';

		/**
		 * Filter the complete form HTML.
		 *
		 * @param string $html       Full form HTML.
		 * @param int    $product_id Product ID.
		 */
		$html = apply_filters( 'bisn_form_html', $html, $product_id );

		return wp_kses( $html, self::allowed_form_html() );
	}
}
