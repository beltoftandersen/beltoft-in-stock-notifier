<?php
/**
 * AJAX handler for subscription form submissions.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeltoftInStockNotifier\Support\Options;
use BeltoftInStockNotifier\Subscription\Repository;
use BeltoftInStockNotifier\Subscription\Validator;
use BeltoftInStockNotifier\Unsubscribe\TokenManager;
use BeltoftInStockNotifier\Logging\LogViewer;

/**
 * Processes subscription AJAX requests.
 */
class AjaxHandler {

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_bisn_subscribe', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_bisn_subscribe', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Handle the subscription AJAX request.
	 *
	 * @return void
	 */
	public static function handle() {
		// Verify nonce before accessing any other $_POST data.
		if ( ! isset( $_POST['bisn_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bisn_nonce'] ) ), 'bisn_subscribe_nonce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed. Please refresh the page.', 'beltoft-in-stock-notifier' ) ) );
		}

		$data = array(
			'bisn_nonce'        => sanitize_text_field( wp_unslash( $_POST['bisn_nonce'] ) ),
			'bisn_email'        => isset( $_POST['bisn_email'] ) ? sanitize_email( wp_unslash( $_POST['bisn_email'] ) ) : '',
			'bisn_product_id'   => isset( $_POST['bisn_product_id'] ) ? absint( $_POST['bisn_product_id'] ) : 0,
			'bisn_variation_id' => isset( $_POST['bisn_variation_id'] ) ? absint( $_POST['bisn_variation_id'] ) : 0,
			'bisn_quantity'     => isset( $_POST['bisn_quantity'] ) ? absint( $_POST['bisn_quantity'] ) : 1,
			'bisn_gdpr'         => isset( $_POST['bisn_gdpr'] ) ? sanitize_text_field( wp_unslash( $_POST['bisn_gdpr'] ) ) : '',
			'bisn_website'      => isset( $_POST['bisn_website'] ) ? sanitize_text_field( wp_unslash( $_POST['bisn_website'] ) ) : '',
		);

		$validation = Validator::validate( $data );
		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( array( 'message' => $validation->get_error_message() ) );
		}

		$opts  = Options::get_all();
		$email = $data['bisn_email']; // Already sanitized above.
		$token = TokenManager::generate( $email );

		/**
		 * Fires before a subscription is created.
		 *
		 * @param array $data Validated form data.
		 */
		do_action( 'bisn_before_subscription', $data );

		$result = Repository::upsert(
			array(
				'product_id'        => $data['bisn_product_id'],
				'variation_id'      => $data['bisn_variation_id'],
				'email'             => $email,
				'quantity'          => $data['bisn_quantity'],
				'ip_address'        => Validator::get_client_ip(),
				'gdpr_consent'      => ! empty( $data['bisn_gdpr'] ),
				'unsubscribe_token' => $token,
			)
		);

		if ( 0 === $result ) {
			wp_send_json_success( array( 'message' => esc_html( $opts['already_subscribed_msg'] ) ) );
		}

		if ( false === $result ) {
			LogViewer::log( 'FAIL subscription email=' . $email . ' product=' . $data['bisn_product_id'] . ' db_error' );
			wp_send_json_error( array( 'message' => esc_html__( 'Something went wrong. Please try again.', 'beltoft-in-stock-notifier' ) ) );
		}

		/**
		 * Fires after a subscription is created.
		 *
		 * @param int   $result Subscription ID.
		 * @param array $data   Validated form data.
		 */
		do_action( 'bisn_after_subscription', $result, $data );

		LogViewer::log( 'SUBSCRIBE email=' . $email . ' product=' . $data['bisn_product_id'] . ' variation=' . $data['bisn_variation_id'] );

		wp_send_json_success( array( 'message' => esc_html( $opts['success_message'] ) ) );
	}
}
