<?php
/**
 * WooCommerce email class for back-in-stock notifications.
 *
 * @package BeltoftInStockNotifier
 */

namespace BeltoftInStockNotifier\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Back In Stock notification email.
 *
 * Extends WC_Email so it inherits WooCommerce email template styling,
 * appears in WooCommerce > Settings > Emails, and can be customized
 * by copying the template to the active theme.
 */
class BackInStockEmail extends \WC_Email {

	/**
	 * Product object.
	 *
	 * @var \WC_Product|null
	 */
	public $product_obj = null;

	/**
	 * Unsubscribe URL.
	 *
	 * @var string
	 */
	public $unsubscribe_url = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'bisn_back_in_stock';
		$this->customer_email = true;
		$this->title          = __( 'Back In Stock', 'beltoft-in-stock-notifier' );
		$this->description    = __( 'Sent to customers who subscribed for a back-in-stock notification when the product returns to stock.', 'beltoft-in-stock-notifier' );

		$this->template_html  = 'emails/back-in-stock.php';
		$this->template_plain = 'emails/plain/back-in-stock.php';
		$this->template_base  = BISN_PATH . 'templates/';

		$this->placeholders = array(
			'{product_name}' => '',
			'{site_title}'   => $this->get_blogname(),
		);

		parent::__construct();
	}

	/**
	 * Get default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( '{product_name} is back in stock!', 'beltoft-in-stock-notifier' );
	}

	/**
	 * Get default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'It\'s back in stock!', 'beltoft-in-stock-notifier' );
	}

	/**
	 * Trigger the email.
	 *
	 * @param \WC_Product $product         Product object.
	 * @param string      $recipient_email Recipient email address.
	 * @param string      $unsubscribe_url Unsubscribe URL.
	 * @return bool Whether the email was sent.
	 */
	public function trigger( $product, $recipient_email, $unsubscribe_url ) {
		$this->setup_locale();

		$this->product_obj    = $product;
		$this->unsubscribe_url = $unsubscribe_url;
		$this->recipient      = $recipient_email;

		$this->placeholders['{product_name}'] = $product->get_name();

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			$this->restore_locale();
			return false;
		}

		$result = $this->send(
			$this->get_recipient(),
			$this->get_subject(),
			$this->get_content(),
			$this->get_headers(),
			$this->get_attachments()
		);

		$this->restore_locale();

		return $result;
	}

	/**
	 * Get content HTML.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return $this->render_template( $this->template_html, false );
	}

	/**
	 * Get content plain text.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return $this->render_template( $this->template_plain, true );
	}

	/**
	 * Render a template with shared variables.
	 *
	 * @param string $template   Template file path.
	 * @param bool   $plain_text Whether this is a plain-text render.
	 * @return string
	 */
	private function render_template( $template, $plain_text ) {
		$product = $this->get_product_for_render();

		$this->placeholders['{product_name}'] = $product->get_name();

		return wc_get_template_html(
			$template,
			array(
				'product'         => $product,
				'email_heading'   => $this->get_heading(),
				'unsubscribe_url' => $this->unsubscribe_url ? $this->unsubscribe_url : '#',
				'sent_to_admin'   => false,
				'plain_text'      => $plain_text,
				'email'           => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Get product for rendering. Falls back to a sample product for previews.
	 *
	 * @return \WC_Product
	 */
	private function get_product_for_render() {
		if ( $this->product_obj ) {
			return $this->product_obj;
		}

		/* Preview mode: find any published product for sample data. */
		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 1,
			)
		);

		if ( ! empty( $products ) ) {
			return $products[0];
		}

		/* Absolute fallback: create a dummy product object. */
		$dummy = new \WC_Product();
		$dummy->set_name( __( 'Sample Product', 'beltoft-in-stock-notifier' ) );
		return $dummy;
	}

	/**
	 * Initialise settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		/* translators: %s: list of available placeholders */
		$placeholder_text = sprintf( __( 'Available placeholders: %s', 'beltoft-in-stock-notifier' ), '<code>{product_name}, {site_title}</code>' );

		$this->form_fields = array(
			'enabled'    => array(
				'title'   => __( 'Enable/Disable', 'beltoft-in-stock-notifier' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'beltoft-in-stock-notifier' ),
				'default' => 'yes',
			),
			'subject'    => array(
				'title'       => __( 'Subject', 'beltoft-in-stock-notifier' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			),
			'heading'    => array(
				'title'       => __( 'Email heading', 'beltoft-in-stock-notifier' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			),
			'email_type' => array(
				'title'       => __( 'Email type', 'beltoft-in-stock-notifier' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'beltoft-in-stock-notifier' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			),
		);
	}
}
