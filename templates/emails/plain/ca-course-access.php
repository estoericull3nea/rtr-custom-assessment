<?php
/**
 * Course access email (plain text).
 *
 * @var WC_Order $order
 * @var string   $email_heading
 * @var string   $course_name
 * @var string   $course_url
 * @var string   $course_password
 * @var int      $expiry_hours
 * @var bool     $sent_to_admin
 * @var bool     $plain_text
 * @var WC_Email $email
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo esc_html( wp_strip_all_tags( $email_heading ) ) . "\n\n";

printf(
	/* translators: %s: customer first name */
	esc_html__( 'Hi %s,', 'rtr-custom-assessment' ) . "\n\n",
	esc_html( $order->get_billing_first_name() ?: __( 'there', 'rtr-custom-assessment' ) )
);

echo esc_html__( 'Thank you for your purchase. Your course access is ready — use the link and password below to get started.', 'rtr-custom-assessment' ) . "\n\n";

echo "----------------------------------------\n";
echo esc_html( $course_name ) . "\n";
if ( '' !== $course_password ) {
	echo esc_html__( 'Access password:', 'rtr-custom-assessment' ) . ' ' . esc_html( $course_password ) . "\n";
}
if ( $expiry_hours > 0 ) {
	printf(
		/* translators: %d: number of hours */
		esc_html__( 'This access link expires %d hours after it was issued.', 'rtr-custom-assessment' ) . "\n",
		(int) $expiry_hours
	);
}
echo esc_html__( 'Access your course:', 'rtr-custom-assessment' ) . "\n";
echo esc_url( $course_url ) . "\n";
echo "----------------------------------------\n\n";

echo esc_html__( 'If you don’t see this email right away, please check your spam folder.', 'rtr-custom-assessment' ) . "\n";
