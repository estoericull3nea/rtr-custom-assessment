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

if ( '' !== $course_password ) {
	echo esc_html__( 'Thank you for your purchase. Your course access is ready — follow the steps below to get started.', 'rtr-custom-assessment' ) . "\n\n";
} else {
	echo esc_html__( 'Thank you for your purchase. Your course access is ready — use the link below to get started.', 'rtr-custom-assessment' ) . "\n\n";
}

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
echo "\n";
if ( '' !== $course_password ) {
	echo esc_html__( 'How to access your course:', 'rtr-custom-assessment' ) . "\n";
	echo '1. ' . esc_html__( 'Open the course link below.', 'rtr-custom-assessment' ) . "\n";
	echo '2. ' . esc_html__( 'When prompted, enter the access password shown above.', 'rtr-custom-assessment' ) . "\n";
	echo '3. ' . esc_html__( 'Click Continue — the course will load in your browser.', 'rtr-custom-assessment' ) . "\n\n";
} else {
	echo esc_html__( 'Click the link below to open your course — it will load right away in your browser.', 'rtr-custom-assessment' ) . "\n\n";
}
echo esc_html__( 'Course link:', 'rtr-custom-assessment' ) . "\n";
echo esc_url( $course_url ) . "\n";
echo "----------------------------------------\n\n";

echo esc_html__( 'If you don’t see this email right away, please check your spam folder.', 'rtr-custom-assessment' ) . "\n";
