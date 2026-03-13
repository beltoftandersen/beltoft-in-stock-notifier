<?php
/**
 * Plugin options management.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralized settings storage and retrieval.
 */
class Options {

	const OPTION = 'bisn_options';

	/**
	 * Static cache for options.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $cache = null;

	/**
	 * Register the setting.
	 *
	 * @return void
	 */
	public static function init() {
		add_action(
			'admin_init',
			function () {
				register_setting(
					'bisn_settings_group',
					self::OPTION,
					array(
						'type'              => 'array',
						'sanitize_callback' => array( __CLASS__, 'sanitize' ),
						'default'           => self::defaults(),
						'show_in_rest'      => false,
					)
				);
			}
		);
	}

	/**
	 * Default option values.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'                => '1',
			'form_position_enabled'  => '1',
			'quantity_field_enabled' => '0',
			'gdpr_enabled'          => '0',
			'gdpr_text'             => 'I agree to be notified when this product is back in stock.',
			'batch_size'            => '50',
			'throttle_seconds'      => '0',
			'cleanup_days'          => '90',
			'success_message'       => 'You will be notified when this product is back in stock.',
			'already_subscribed_msg' => 'You are already subscribed for this product.',
			'button_text'           => 'Notify Me',
			'rate_limit_per_ip'     => '10',
			'disable_logging'       => '0',
			'cleanup_on_uninstall'  => '0',
		);
	}

	/**
	 * Get all options merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		self::$cache = array_merge( self::defaults(), $saved );
		return self::$cache;
	}

	/**
	 * Get a single option value.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::get_all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	/**
	 * Sanitize settings on save.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			return self::defaults();
		}

		$clean    = array();
		$defaults = self::defaults();

		$booleans = array(
			'enabled',
			'form_position_enabled',
			'quantity_field_enabled',
			'gdpr_enabled',
			'disable_logging',
			'cleanup_on_uninstall',
		);

		foreach ( $booleans as $key ) {
			$clean[ $key ] = ! empty( $input[ $key ] ) ? '1' : '0';
		}

		$text_fields = array(
			'gdpr_text',
			'success_message',
			'already_subscribed_msg',
			'button_text',
		);

		foreach ( $text_fields as $key ) {
			$clean[ $key ] = isset( $input[ $key ] )
				? sanitize_text_field( wp_unslash( $input[ $key ] ) )
				: $defaults[ $key ];
		}

		$int_fields = array(
			'batch_size'        => array( 1, 500 ),
			'throttle_seconds'  => array( 0, 3600 ),
			'cleanup_days'      => array( 0, 365 ),
			'rate_limit_per_ip' => array( 1, 1000 ),
		);

		foreach ( $int_fields as $key => $range ) {
			$val = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : absint( $defaults[ $key ] );
			if ( $val < $range[0] ) {
				$val = $range[0];
			}
			if ( $val > $range[1] ) {
				$val = $range[1];
			}
			$clean[ $key ] = (string) $val;
		}

		self::$cache = null;

		return $clean;
	}
}
