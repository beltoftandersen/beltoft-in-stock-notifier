<?php
/**
 * Back in stock notification email (plain text).
 *
 * This template can be overridden by copying it to
 * yourtheme/woocommerce/emails/plain/back-in-stock.php
 *
 * @package BeltoftInStockNotifier
 * @var \WC_Product $product         Product object.
 * @var string      $email_heading   Email heading.
 * @var string      $body_text       Body text (with placeholders replaced).
 * @var string      $button_text     Button text (with placeholders replaced).
 * @var string      $footer_text      Footer text (with placeholders replaced).
 * @var string      $unsubscribe_text Unsubscribe link text (with placeholders replaced).
 * @var string      $unsubscribe_url  Unsubscribe URL.
 * @var bool        $sent_to_admin   Whether sent to admin.
 * @var bool        $plain_text      Whether plain text.
 * @var \WC_Email   $email           Email object.
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo "════════════════════════════════════════════\n\n";
echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n";
echo "════════════════════════════════════════════\n\n";

$isn_stock_qty = $product->get_stock_quantity();
if ( null === $isn_stock_qty ) {
	$isn_stock_qty = __( 'Available', 'beltoft-in-stock-notifier' );
}

echo esc_html( $body_text ) . "\n\n";

$isn_product_url = apply_filters( 'bisn_email_product_url', $product->get_permalink(), $product, $email );
echo esc_html( $button_text ) . ': ' . esc_url( $isn_product_url ) . "\n\n";

/* translators: %s: stock quantity or "Available" */
echo esc_html( sprintf( __( 'Current stock: %s', 'beltoft-in-stock-notifier' ), $isn_stock_qty ) ) . "\n\n";

echo "────────────────────────────────────────────\n\n";

echo esc_html( $footer_text ) . "\n";
echo esc_html( $unsubscribe_text ) . ': ' . esc_url( $unsubscribe_url ) . "\n";
