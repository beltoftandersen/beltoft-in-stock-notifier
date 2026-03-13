<?php
/**
 * Handles unsubscribe requests.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Unsubscribe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeltoftInStockNotifier\Subscription\Repository;
use BeltoftInStockNotifier\Logging\LogViewer;

/**
 * Listens for unsubscribe requests and processes them.
 *
 * Uses a two-step flow: GET shows a confirmation page with a POST form,
 * POST performs the actual unsubscribe. This prevents link prefetchers
 * and email scanners from triggering unsubscribes.
 */
class Handler {

	/**
	 * Register the init hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'listen' ) );
	}

	/**
	 * Listen for unsubscribe action in query string.
	 *
	 * @return void
	 */
	public static function listen() {
		if ( ! isset( $_GET['bisn_action'] ) || 'unsubscribe' !== $_GET['bisn_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $token ) ) {
			wp_die(
				esc_html__( 'Invalid unsubscribe link.', 'beltoft-in-stock-notifier' ),
				esc_html__( 'Unsubscribe', 'beltoft-in-stock-notifier' ),
				array( 'response' => 400 )
			);
		}

		$sub = Repository::get_by_token( $token );
		if ( ! $sub ) {
			wp_die(
				esc_html__( 'Invalid unsubscribe link.', 'beltoft-in-stock-notifier' ),
				esc_html__( 'Unsubscribe', 'beltoft-in-stock-notifier' ),
				array( 'response' => 400 )
			);
		}

		if ( 'active' !== $sub->status ) {
			wp_die(
				esc_html__( 'You have already been unsubscribed.', 'beltoft-in-stock-notifier' ),
				esc_html__( 'Unsubscribe', 'beltoft-in-stock-notifier' ),
				array( 'response' => 200 )
			);
		}

		/* POST = perform the unsubscribe. */
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			if ( ! isset( $_POST['bisn_unsub_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bisn_unsub_nonce'] ) ), 'bisn_unsubscribe_' . $token ) ) {
				wp_die(
					esc_html__( 'Security check failed. Please try the link again.', 'beltoft-in-stock-notifier' ),
					esc_html__( 'Unsubscribe', 'beltoft-in-stock-notifier' ),
					array( 'response' => 403 )
				);
			}

			Repository::unsubscribe_by_token( $token );
			LogViewer::log( 'UNSUBSCRIBE email=' . $sub->email . ' product=' . $sub->product_id );

			wp_die(
				esc_html__( 'You have been successfully unsubscribed. You will no longer receive back-in-stock notifications for this product.', 'beltoft-in-stock-notifier' ),
				esc_html__( 'Unsubscribed', 'beltoft-in-stock-notifier' ),
				array( 'response' => 200 )
			);
		}

		/* GET = show confirmation page with POST form. */
		$action_url = esc_url( add_query_arg(
			array(
				'bisn_action' => 'unsubscribe',
				'token'       => $token,
			),
			home_url( '/' )
		) );

		$html  = '<p>' . esc_html__( 'Click the button below to confirm you want to unsubscribe from back-in-stock notifications for this product.', 'beltoft-in-stock-notifier' ) . '</p>';
		$html .= '<form method="post" action="' . $action_url . '">';
		$html .= '<input type="hidden" name="bisn_unsub_nonce" value="' . esc_attr( wp_create_nonce( 'bisn_unsubscribe_' . $token ) ) . '" />';
		$html .= '<p><button type="submit" style="padding:10px 24px;background:#0073aa;color:#fff;border:none;border-radius:3px;cursor:pointer;font-size:14px;">';
		$html .= esc_html__( 'Confirm Unsubscribe', 'beltoft-in-stock-notifier' );
		$html .= '</button></p>';
		$html .= '</form>';

		wp_die(
			$html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All parts escaped above.
			esc_html__( 'Unsubscribe', 'beltoft-in-stock-notifier' ),
			array( 'response' => 200 )
		);
	}
}
