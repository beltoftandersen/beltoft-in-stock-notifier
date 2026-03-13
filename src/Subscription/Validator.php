<?php
/**
 * Subscription input validation.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeltoftInStockNotifier\Support\Options;

/**
 * Validates subscription form submissions.
 */
class Validator {

	/**
	 * Validate a subscription request.
	 *
	 * Nonce verification is handled by AjaxHandler before calling this method.
	 * Returns a WP_Error on failure or true on success.
	 *
	 * @param array<string, mixed> $data Form data.
	 * @return true|\WP_Error
	 */
	public static function validate( $data ) {
		/* Honeypot check. */
		if ( ! empty( $data['bisn_website'] ) ) {
			return new \WP_Error( 'bisn_spam', __( 'Spam detected.', 'beltoft-in-stock-notifier' ) );
		}

		/* Email. */
		$email = isset( $data['bisn_email'] ) ? sanitize_email( wp_unslash( $data['bisn_email'] ) ) : '';
		if ( empty( $email ) || ! is_email( $email ) ) {
			return new \WP_Error( 'bisn_email', __( 'Please enter a valid email address.', 'beltoft-in-stock-notifier' ) );
		}

		/* Product ID. */
		$product_id = isset( $data['bisn_product_id'] ) ? absint( $data['bisn_product_id'] ) : 0;
		if ( ! $product_id ) {
			return new \WP_Error( 'bisn_product', __( 'Invalid product.', 'beltoft-in-stock-notifier' ) );
		}

		/* GDPR consent. */
		$opts = Options::get_all();
		if ( '1' === $opts['gdpr_enabled'] && empty( $data['bisn_gdpr'] ) ) {
			return new \WP_Error( 'bisn_gdpr', __( 'Please accept the consent checkbox.', 'beltoft-in-stock-notifier' ) );
		}

		/* Rate limiting. */
		$ip    = self::get_client_ip();
		$limit = absint( $opts['rate_limit_per_ip'] );
		if ( $limit > 0 && Repository::count_recent_by_ip( $ip ) >= $limit ) {
			return new \WP_Error( 'bisn_rate_limit', __( 'Too many requests. Please try again later.', 'beltoft-in-stock-notifier' ) );
		}

		/**
		 * Allow third-party validation.
		 *
		 * @param true|\WP_Error $valid Current validation result.
		 * @param array          $data  Form data.
		 */
		$result = apply_filters( 'bisn_validate_subscription', true, $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Get the client IP address.
	 *
	 * Uses REMOTE_ADDR only, as it cannot be spoofed by the client.
	 * X-Forwarded-For is only trusted when explicitly opted in via filter
	 * (for sites behind a trusted reverse proxy).
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		/**
		 * Whether to trust X-Forwarded-For for IP detection.
		 *
		 * Only enable this if the site is behind a trusted reverse proxy.
		 *
		 * @param bool $trust Default false.
		 */
		if ( apply_filters( 'bisn_trust_forwarded_for', false )
			&& ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			// Use the rightmost IP (closest to the trusted proxy), not the leftmost (client-provided).
			return trim( end( $ips ) );
		}

		return '';
	}
}
