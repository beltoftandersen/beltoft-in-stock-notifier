<?php
/**
 * HMAC token generation for unsubscribe links.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Unsubscribe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates and validates HMAC-SHA256 unsubscribe tokens.
 */
class TokenManager {

	/**
	 * Generate a unique token for a subscription.
	 *
	 * @param string $email Subscriber email.
	 * @return string 64-character hex token.
	 */
	public static function generate( $email ) {
		return wp_generate_password( 64, false );
	}

	/**
	 * Build the unsubscribe URL for a token.
	 *
	 * @param string $token Unsubscribe token.
	 * @return string
	 */
	public static function get_url( $token ) {
		return add_query_arg(
			array(
				'bisn_action' => 'unsubscribe',
				'token'       => rawurlencode( $token ),
			),
			home_url( '/' )
		);
	}
}
