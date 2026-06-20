<?php
/**
 * Course access email (HTML).
 *
 * @var WC_Order $order
 * @var string   $email_heading
 * @var string   $course_name
 * @var string   $course_url
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var WC_Email $email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php
	printf(
		/* translators: %s: customer first name */
		esc_html__( 'Hi %s,', 'rtr-custom-assessment' ),
		esc_html( $order->get_billing_first_name() ?: __( 'there', 'rtr-custom-assessment' ) )
	);
	?>
</p>

<p><?php esc_html_e( 'Thank you for your purchase. Your course access is ready — use the button or link below to get started.', 'rtr-custom-assessment' ); ?></p>

<div style="margin:24px 0;padding:20px 24px;background:#f9f5f5;border:2px solid #aa3130;border-radius:4px;font-family:Helvetica,Arial,sans-serif;">
	<h2 style="margin:0 0 8px;font-size:18px;line-height:1.4;color:#1a1a2e;"><?php echo esc_html( $course_name ); ?></h2>
	<p style="margin:0 0 16px;font-size:15px;line-height:1.5;color:#444;">
		<?php esc_html_e( 'Click below to open your course. This link is personal to your order.', 'rtr-custom-assessment' ); ?>
	</p>
	<p style="margin:0 0 16px;">
		<a href="<?php echo esc_url( $course_url ); ?>" style="display:inline-block;font-size:18px;font-weight:700;color:#aa3130;text-decoration:none;border-bottom:2px solid #aa3130;padding-bottom:2px;" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Access Your Course', 'rtr-custom-assessment' ); ?>
		</a>
	</p>
	<p style="margin:0;font-size:13px;line-height:1.5;color:#666;">
		<?php esc_html_e( 'Or copy this link into your browser:', 'rtr-custom-assessment' ); ?>
		<br>
		<a href="<?php echo esc_url( $course_url ); ?>" style="color:#aa3130;word-break:break-all;" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $course_url ); ?></a>
	</p>
</div>

<p><?php esc_html_e( 'If you don’t see this email right away, please check your spam folder.', 'rtr-custom-assessment' ); ?></p>

<?php
do_action( 'woocommerce_email_footer', $email );
