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
 * @var string      $unsubscribe_url Unsubscribe URL.
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

/* translators: 1: product name, 2: site name */
echo esc_html( sprintf( __( 'Good news! %1$s is back in stock at %2$s.', 'beltoft-in-stock-notifier' ), $product->get_name(), get_bloginfo( 'name' ) ) ) . "\n\n";

$isn_product_url = apply_filters( 'bisn_email_product_url', $product->get_permalink(), $product, $email );
echo esc_html__( 'Shop Now:', 'beltoft-in-stock-notifier' ) . ' ' . esc_url( $isn_product_url ) . "\n\n";

/* translators: %s: stock quantity or "Available" */
echo esc_html( sprintf( __( 'Current stock: %s', 'beltoft-in-stock-notifier' ), $isn_stock_qty ) ) . "\n\n";

echo "────────────────────────────────────────────\n\n";

/* translators: %s: site name */
echo esc_html( sprintf( __( 'You received this email because you subscribed to a back-in-stock notification on %s.', 'beltoft-in-stock-notifier' ), get_bloginfo( 'name' ) ) ) . "\n";
echo esc_html__( 'Unsubscribe:', 'beltoft-in-stock-notifier' ) . ' ' . esc_url( $unsubscribe_url ) . "\n";
