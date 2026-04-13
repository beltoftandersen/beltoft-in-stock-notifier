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
	 * Keys that hold user-facing translatable text.
	 *
	 * These are never persisted to the database unless the admin has customised
	 * them. When absent from the DB, get_all() falls through to defaults()
	 * which returns the translated value for the current locale.
	 *
	 * @var string[]
	 */
	const TEXT_KEYS = array(
		'gdpr_text',
		'success_message',
		'already_subscribed_msg',
		'heading_text',
		'button_text',
	);

	/**
	 * Default option values.
	 *
	 * Text fields use literal __() calls so the i18n scanner can extract them
	 * and .po files can provide localised defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'                => '1',
			'form_position_enabled'  => '1',
			'quantity_field_enabled' => '0',
			'gdpr_enabled'          => '0',
			'gdpr_text'             => __( 'I agree to be notified when this product is back in stock.', 'beltoft-in-stock-notifier' ),
			'batch_size'            => '50',
			'throttle_seconds'      => '0',
			'cleanup_days'          => '90',
			'success_message'       => __( 'You will be notified when this product is back in stock.', 'beltoft-in-stock-notifier' ),
			'already_subscribed_msg' => __( 'You are already subscribed for this product.', 'beltoft-in-stock-notifier' ),
			'heading_text'          => __( 'Want to know when it\'s back? Leave your email below.', 'beltoft-in-stock-notifier' ),
			'button_text'           => __( 'Notify Me', 'beltoft-in-stock-notifier' ),
			'rate_limit_per_ip'     => '10',
			'enable_logging'        => '0',
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
			'enable_logging',
			'cleanup_on_uninstall',
		);

		foreach ( $booleans as $key ) {
			$clean[ $key ] = ! empty( $input[ $key ] ) ? '1' : '0';
		}

		foreach ( self::TEXT_KEYS as $key ) {
			if ( ! isset( $input[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( wp_unslash( $input[ $key ] ) );

			/*
			 * Only persist text fields that differ from the translated default.
			 * Omitting unchanged defaults lets get_all() fall through to __(),
			 * so translations stay current without stale DB values.
			 */
			if ( $value !== $defaults[ $key ] ) {
				$clean[ $key ] = $value;
			}
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
