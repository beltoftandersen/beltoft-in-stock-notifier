<?php
/**
 * Settings tab using WordPress Settings API.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BeltoftInStockNotifier\Support\Options;

/**
 * Renders and registers the settings form.
 */
class SettingsTab {

	/**
	 * Render the settings form.
	 *
	 * @return void
	 */
	public static function render() {
		$opts = Options::get_all();

		if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'beltoft-in-stock-notifier' ) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'options.php' ) ) . '">';
		settings_fields( 'bisn_settings_group' );

		/* ── General ────────────────────────────────────────── */
		echo '<h2>' . esc_html__( 'General', 'beltoft-in-stock-notifier' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		self::checkbox_row( 'enabled', __( 'Enable Notifications', 'beltoft-in-stock-notifier' ), $opts );
		self::checkbox_row( 'form_position_enabled', __( 'Auto-place Form on Product Pages', 'beltoft-in-stock-notifier' ), $opts, __( 'Uncheck to use the [bisn_form] shortcode only.', 'beltoft-in-stock-notifier' ) );
		self::checkbox_row( 'quantity_field_enabled', __( 'Show Quantity Field', 'beltoft-in-stock-notifier' ), $opts );
		self::text_row( 'heading_text', __( 'Form Heading', 'beltoft-in-stock-notifier' ), $opts, __( 'Text shown above the subscription form.', 'beltoft-in-stock-notifier' ) );
		self::text_row( 'button_text', __( 'Button Text', 'beltoft-in-stock-notifier' ), $opts );

		echo '</tbody></table>';

		/* ── GDPR ───────────────────────────────────────────── */
		echo '<h2>' . esc_html__( 'GDPR / Consent', 'beltoft-in-stock-notifier' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		self::checkbox_row( 'gdpr_enabled', __( 'Require GDPR Consent', 'beltoft-in-stock-notifier' ), $opts );
		self::textarea_row( 'gdpr_text', __( 'Consent Text', 'beltoft-in-stock-notifier' ), $opts );

		echo '</tbody></table>';

		/* ── Messages ───────────────────────────────────────── */
		echo '<h2>' . esc_html__( 'Messages', 'beltoft-in-stock-notifier' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		self::text_row( 'success_message', __( 'Success Message', 'beltoft-in-stock-notifier' ), $opts );
		self::text_row( 'already_subscribed_msg', __( 'Already Subscribed Message', 'beltoft-in-stock-notifier' ), $opts );

		echo '</tbody></table>';

		/* ── Performance ────────────────────────────────────── */
		echo '<h2>' . esc_html__( 'Performance', 'beltoft-in-stock-notifier' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		self::number_row( 'batch_size', __( 'Batch Size', 'beltoft-in-stock-notifier' ), $opts, __( 'Emails per cron run (1-500).', 'beltoft-in-stock-notifier' ) );
		self::number_row( 'throttle_seconds', __( 'Throttle (seconds)', 'beltoft-in-stock-notifier' ), $opts, __( 'Delay between each email (0 = no delay).', 'beltoft-in-stock-notifier' ) );
		self::number_row( 'rate_limit_per_ip', __( 'Rate Limit per IP', 'beltoft-in-stock-notifier' ), $opts, __( 'Max subscriptions per IP per hour.', 'beltoft-in-stock-notifier' ) );

		echo '</tbody></table>';

		/* ── Advanced ───────────────────────────────────────── */
		echo '<h2>' . esc_html__( 'Advanced', 'beltoft-in-stock-notifier' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		self::number_row( 'cleanup_days', __( 'Cleanup After (days)', 'beltoft-in-stock-notifier' ), $opts, __( 'Delete notified subscriptions older than this. 0 = never delete.', 'beltoft-in-stock-notifier' ) );
		self::checkbox_row( 'enable_logging', __( 'Enable Logging', 'beltoft-in-stock-notifier' ), $opts );
		self::checkbox_row( 'cleanup_on_uninstall', __( 'Remove Data on Uninstall', 'beltoft-in-stock-notifier' ), $opts, __( 'Delete all plugin data when the plugin is deleted.', 'beltoft-in-stock-notifier' ) );

		echo '</tbody></table>';

		submit_button();
		echo '</form>';
	}

	/**
	 * Render a checkbox row.
	 *
	 * @param string               $key  Setting key.
	 * @param string               $label Label text.
	 * @param array<string, mixed> $opts Current options.
	 * @param string               $desc Description text.
	 * @return void
	 */
	private static function checkbox_row( $key, $label, $opts, $desc = '' ) {
		$name  = Options::OPTION . '[' . $key . ']';
		$value = isset( $opts[ $key ] ) ? $opts[ $key ] : '0';

		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" ' . checked( '1', $value, false ) . ' /> ';
		if ( $desc ) {
			echo esc_html( $desc );
		}
		echo '</label>';
		echo '</td></tr>';
	}

	/**
	 * Render a text input row.
	 *
	 * @param string               $key   Setting key.
	 * @param string               $label Label text.
	 * @param array<string, mixed> $opts  Current options.
	 * @param string               $desc  Description text.
	 * @return void
	 */
	private static function text_row( $key, $label, $opts, $desc = '' ) {
		$name  = Options::OPTION . '[' . $key . ']';
		$value = isset( $opts[ $key ] ) ? $opts[ $key ] : '';

		echo '<tr><th scope="row"><label for="bisn_' . esc_attr( $key ) . '">';
		echo esc_html( $label );
		echo '</label></th><td>';
		echo '<input type="text" id="bisn_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Render a number input row.
	 *
	 * @param string               $key   Setting key.
	 * @param string               $label Label text.
	 * @param array<string, mixed> $opts  Current options.
	 * @param string               $desc  Description text.
	 * @return void
	 */
	private static function number_row( $key, $label, $opts, $desc = '' ) {
		$name  = Options::OPTION . '[' . $key . ']';
		$value = isset( $opts[ $key ] ) ? $opts[ $key ] : '0';

		echo '<tr><th scope="row"><label for="bisn_' . esc_attr( $key ) . '">';
		echo esc_html( $label );
		echo '</label></th><td>';
		echo '<input type="number" id="bisn_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="small-text" min="0" />';
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Render a textarea row.
	 *
	 * @param string               $key   Setting key.
	 * @param string               $label Label text.
	 * @param array<string, mixed> $opts  Current options.
	 * @param string               $desc  Description text.
	 * @return void
	 */
	private static function textarea_row( $key, $label, $opts, $desc = '' ) {
		$name  = Options::OPTION . '[' . $key . ']';
		$value = isset( $opts[ $key ] ) ? $opts[ $key ] : '';

		echo '<tr><th scope="row"><label for="bisn_' . esc_attr( $key ) . '">';
		echo esc_html( $label );
		echo '</label></th><td>';
		echo '<textarea id="bisn_' . esc_attr( $key ) . '" name="' . esc_attr( $name ) . '" rows="4" class="large-text">';
		echo esc_textarea( $value );
		echo '</textarea>';
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
		echo '</td></tr>';
	}
}
