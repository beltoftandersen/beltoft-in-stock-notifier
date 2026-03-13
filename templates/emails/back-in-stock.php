<?php
/**
 * Back in stock notification email (HTML).
 *
 * This template can be overridden by copying it to
 * yourtheme/woocommerce/emails/back-in-stock.php
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
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header.
 */
do_action( 'woocommerce_email_header', $email_heading, $email );

$isn_image_id    = $product->get_image_id();
$isn_image_url   = $isn_image_id ? wp_get_attachment_url( $isn_image_id ) : '';
$isn_stock_qty   = $product->get_stock_quantity();
if ( null === $isn_stock_qty ) {
	$isn_stock_qty = __( 'Available', 'beltoft-in-stock-notifier' );
}
$isn_product_url = apply_filters( 'bisn_email_product_url', $product->get_permalink(), $product, $email );
$isn_site_name   = get_bloginfo( 'name' );
$isn_site_url    = home_url( '/' );

if ( $isn_image_url ) :
	?>
<p style="text-align: center;">
	<a href="<?php echo esc_url( $isn_product_url ); ?>">
		<img src="<?php echo esc_url( $isn_image_url ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" style="max-width: 200px; height: auto; border: none;" />
	</a>
</p>
<?php endif; ?>

<p>
	<?php
	/* translators: 1: product name, 2: site URL, 3: site name */
	$isn_intro = sprintf( __( 'Good news! <strong>%1$s</strong> is back in stock at <a href="%2$s">%3$s</a>.', 'beltoft-in-stock-notifier' ), esc_html( $product->get_name() ), esc_url( $isn_site_url ), esc_html( $isn_site_name ) );
	echo wp_kses_post( $isn_intro );
	?>
</p>

<p style="text-align: center; margin: 1.5em 0;">
	<a href="<?php echo esc_url( $isn_product_url ); ?>" style="display: inline-block; padding: 12px 24px; background-color: <?php echo esc_attr( get_option( 'woocommerce_email_base_color', '#7f54b3' ) ); ?>; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold;">
		<?php echo esc_html__( 'Shop Now', 'beltoft-in-stock-notifier' ); ?>
	</a>
</p>

<p>
	<?php
	/* translators: %s: stock quantity or "Available" */
	echo esc_html( sprintf( __( 'Current stock: %s', 'beltoft-in-stock-notifier' ), $isn_stock_qty ) );
	?>
</p>

<p style="font-size: 12px; color: #888;">
	<?php
	/* translators: 1: site name, 2: unsubscribe URL */
	$isn_footer = sprintf( __( 'You received this email because you subscribed to a back-in-stock notification on %1$s. <a href="%2$s">Unsubscribe</a>', 'beltoft-in-stock-notifier' ), esc_html( $isn_site_name ), esc_url( $unsubscribe_url ) );
	echo wp_kses_post( $isn_footer );
	?>
</p>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer.
 */
do_action( 'woocommerce_email_footer', $email );
