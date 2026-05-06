<?php
/**
 * Email handler for sending assessment results to users.
 */

if (!defined('ABSPATH')) {
	exit;
}

class CA_Mailer
{

	/**
	 * Send assessment results email to user.
	 *
	 * @param int $submission_id
	 * @return bool
	 */
	public static function send_results_email($submission_id)
	{
		$submission_id = (int) $submission_id;
		$submission = CA_Database::get_submission($submission_id);

		if (!$submission || 'completed' !== $submission->status) {
			return false;
		}

		$cat_scores = CA_Database::get_category_scores($submission_id);

		// Build email subject and body
		$subject = sprintf(
			/* translators: %s: Site name. */
			__('Your Assessment Results - %s', 'rtr-custom-assessment'),
			get_bloginfo('name')
		);

		$body = self::build_email_body($submission, $cat_scores);

		// Setup email headers
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
		);

		// Send email
		$sent = wp_mail($submission->email, $subject, $body, $headers);
		if ($sent) {
			self::send_admin_results_notification($submission);
		}

		return $sent;
	}

	/**
	 * Send one combined email when a bundle (NAC + Social Fluency) is fully completed.
	 *
	 * @param int $inner_submission_id
	 * @param int $social_submission_id
	 * @return bool
	 */
	public static function send_bundle_completion_email($inner_submission_id, $social_submission_id)
	{
		$inner_submission_id = (int) $inner_submission_id;
		$social_submission_id = (int) $social_submission_id;
		if ($inner_submission_id <= 0 || $social_submission_id <= 0) {
			return false;
		}

		$inner = CA_Database::get_submission($inner_submission_id);
		$social = CA_Database::get_submission($social_submission_id);
		if (
			!$inner || !$social
			|| 'completed' !== (string) $inner->status
			|| 'completed' !== (string) $social->status
		) {
			return false;
		}

		$to = sanitize_email((string) $inner->email);
		if (!is_email($to)) {
			return false;
		}

		$inner_cats = CA_Database::get_category_scores($inner_submission_id);
		$social_cats = CA_Database::get_category_scores($social_submission_id);

		$top_inner = null;
		foreach ($inner_cats as $cat) {
			if (!$top_inner || (float) $cat->average > (float) $top_inner->average) {
				$top_inner = $cat;
			}
		}

		$top_social = null;
		foreach ($social_cats as $cat) {
			if (!$top_social || (float) $cat->average > (float) $top_social->average) {
				$top_social = $cat;
			}
		}

		$inner_name = $top_inner ? (string) $top_inner->category_name : __('Your strongest attribute', 'rtr-custom-assessment');
		$inner_summary = $top_inner
			? CA_Scoring::get_category_summary((string) $top_inner->category_name, (float) $top_inner->average, CA_Assessment_Types::INNER_DIMENSIONS)
			: __('You respond most strongly where your natural strengths are already active.', 'rtr-custom-assessment');

		$social_profile = CA_Scoring::get_overall_profile((float) $social->average_score, CA_Assessment_Types::SOCIAL_FLUENCY);
		$social_domain = $top_social ? (string) $top_social->category_name : __('your strongest domain', 'rtr-custom-assessment');
		$social_summary = $top_social
			? CA_Scoring::get_category_summary((string) $top_social->category_name, (float) $top_social->average, CA_Assessment_Types::SOCIAL_FLUENCY)
			: __('This is where your social strengths show up most clearly.', 'rtr-custom-assessment');

		$blog_name = get_bloginfo('name');
		$subject = sprintf(
			/* translators: %s: site name */
			__('Your Bundle Assessment Results - %s', 'rtr-custom-assessment'),
			$blog_name
		);

		$full_name = trim((string) $inner->first_name . ' ' . (string) $inner->last_name);
		if ('' === $full_name) {
			$full_name = __('there', 'rtr-custom-assessment');
		}

		$body = '
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>' . esc_html__('Bundle Results', 'rtr-custom-assessment') . '</title>
			<style>
				body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; color:#333; background:#f5f5f5; margin:0; }
				.wrap { max-width:640px; margin:20px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.1); }
				.head { background:linear-gradient(135deg,#aa3130 0%,#8b2823 100%); color:#fff; padding:26px; text-align:center; }
				.content { padding:26px; }
				.card { border:1px solid #e9e2d9; border-radius:8px; padding:14px 16px; margin:0 0 14px; background:#fff; }
				.kicker { font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#8d2b28; font-weight:700; margin:0 0 8px; }
				.row { font-size:14px; line-height:1.6; margin:6px 0; }
				.note { font-size:13px; color:#666; margin:10px 0 0; }
			</style>
		</head>
		<body>
			<div class="wrap">
				<div class="head">
					<h1 style="margin:0 0 8px; font-size:26px;">' . esc_html__('Bundle Complete!', 'rtr-custom-assessment') . '</h1>
					<p style="margin:0; opacity:.92;">' . esc_html__('Here is your combined preview from both assessments.', 'rtr-custom-assessment') . '</p>
				</div>
				<div class="content">
					<p>' . sprintf(esc_html__('Hi %s,', 'rtr-custom-assessment'), esc_html($full_name)) . '</p>

					<div class="card">
						<p class="kicker">' . esc_html__('Natural Attributes Cataloging', 'rtr-custom-assessment') . '</p>
						<p class="row"><strong>' . esc_html__('Top attribute surfaced:', 'rtr-custom-assessment') . '</strong> ' . esc_html($inner_name) . '</p>
						<p class="row"><strong>' . esc_html__('Pattern revealed:', 'rtr-custom-assessment') . '</strong> ' . esc_html($inner_summary) . '</p>
					</div>

					<div class="card">
						<p class="kicker">' . esc_html__('Social Fluency Assessment', 'rtr-custom-assessment') . '</p>
						<p class="row"><strong>' . esc_html__('Overall Social Fluency tier:', 'rtr-custom-assessment') . '</strong> ' . esc_html($social_profile) . '</p>
						<p class="row"><strong>' . esc_html__('Domain to notice:', 'rtr-custom-assessment') . '</strong> ' . esc_html($social_domain) . '</p>
						<p class="row">' . esc_html($social_summary) . '</p>
					</div>

					<p class="note">' . esc_html__('You can unlock full downloadable reports from your checkout flow.', 'rtr-custom-assessment') . '</p>
				</div>
			</div>
		</body>
		</html>';

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $blog_name . ' <' . get_option('admin_email') . '>',
		);

		$sent = (bool) wp_mail($to, $subject, $body, $headers);
		if ($sent) {
			self::send_admin_bundle_results_notification($inner, $social);
		}
		return $sent;
	}

	/**
	 * Email customer after paid NAC order with the same PDF URL as the checkout thank-you page.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @param string    $download_url Public PDF URL.
	 * @return bool
	 */
	public static function send_customer_paid_pdf_download_email($order, $download_url)
	{
		if (!$order instanceof \WC_Order) {
			return false;
		}

		$download_url = trim((string) $download_url);
		if ('' === $download_url) {
			return false;
		}

		$to = trim((string) $order->get_billing_email());
		if (!is_email($to)) {
			return false;
		}

		$blog_name = get_bloginfo('name');
		$order_no = (string) $order->get_order_number();
		$subject = sprintf(
			/* translators: %s: order number */
			__('Your order #%s is complete', 'rtr-custom-assessment'),
			$order_no
		);
		$subject = apply_filters('ca_customer_paid_pdf_email_subject', $subject, $order, $download_url);

		$first = trim((string) $order->get_billing_first_name());
		$greeting = '' !== $first
			? '<p style="margin-bottom:12px;">' . sprintf(
				/* translators: %s: first name */
				esc_html__('Hi %s,', 'rtr-custom-assessment'),
				esc_html($first)
			) . '</p>'
			: '<p style="margin-bottom:12px;">' . esc_html__('Hello,', 'rtr-custom-assessment') . '</p>';

		$body_inner = $greeting;
		$body_inner .= '<p style="margin-bottom:14px;">' . esc_html__('Your payment was received and your order is complete. Use the link below to download your PDF — the same link shown on the checkout confirmation page.', 'rtr-custom-assessment') . '</p>';
		$body_inner .= '<div class="cta-wrap" style="margin: 20px 0; text-align:center;">';
		$body_inner .= '<a href="' . esc_url($download_url) . '" class="cta-btn" style="display:inline-block;background:#aa3130;color:#fff !important;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:600;font-size:15px;">';
		$body_inner .= esc_html__('Download PDF', 'rtr-custom-assessment');
		$body_inner .= '</a></div>';
		$body_inner .= '<p style="margin-top:14px;font-size:13px;color:#666;line-height:1.5;">';
		$body_inner .= esc_html__('If the button does not work, copy this address into your browser:', 'rtr-custom-assessment');
		$body_inner .= '</p>';
		$body_inner .= '<p style="word-break:break-all;font-size:12px;color:#999;margin-top:6px;"><a href="' . esc_url($download_url) . '" style="color:#aa3130;">' . esc_html($download_url) . '</a></p>';

		$body = '
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>' . esc_html__('Your order is complete', 'rtr-custom-assessment') . '</title>
			<style>
				* { margin: 0; padding: 0; }
				body {
					font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
					line-height: 1.6;
					color: #333;
					background-color: #f5f5f5;
				}
				.email-container {
					max-width: 600px;
					margin: 20px auto;
					background-color: #fff;
					border-radius: 8px;
					box-shadow: 0 2px 8px rgba(0,0,0,0.1);
					overflow: hidden;
				}
				.email-header {
					background: linear-gradient(135deg, #aa3130 0%, #8b2823 100%);
					color: #fff;
					padding: 30px;
					text-align: center;
				}
				.email-header h1 {
					font-size: 26px;
					margin-bottom: 8px;
				}
				.email-header p {
					font-size: 14px;
					opacity: 0.92;
				}
				.email-content { padding: 30px; }
				.cta-wrap { margin: 20px 0; text-align: center; }
				.cta-btn {
					display: inline-block;
					background: #aa3130;
					color: #fff !important;
					text-decoration: none;
					padding: 12px 22px;
					border-radius: 6px;
					font-weight: 600;
					font-size: 15px;
				}
				.footer-section {
					background-color: #f5f5f5;
					padding: 20px 30px;
					border-top: 1px solid #eee;
					font-size: 13px;
					color: #666;
					text-align: center;
				}
			</style>
		</head>
		<body>
			<div class="email-container">
				<div class="email-header">
					<h1>' . esc_html__('Thank you for your purchase', 'rtr-custom-assessment') . '</h1>
					<p>' . esc_html__('Your order is complete', 'rtr-custom-assessment') . '</p>
				</div>
				<div class="email-content">
					' . $body_inner . '
				</div>
				<div class="footer-section">
					<p>&copy; ' . esc_html($blog_name) . ' ' . gmdate('Y') . '. ' . esc_html__('All rights reserved.', 'rtr-custom-assessment') . '</p>
					<p>' . esc_html__('This is an automated message. Please do not reply.', 'rtr-custom-assessment') . '</p>
				</div>
			</div>
		</body>
		</html>';

		$body = apply_filters('ca_customer_paid_pdf_email_body', $body, $order, $download_url);

		$from_email = (string) get_option('admin_email');
		if (!is_email($from_email)) {
			$from_email = $to;
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $blog_name . ' <' . $from_email . '>',
		);

		return (bool) wp_mail($to, $subject, $body, $headers);
	}

	/**
	 * Email customer download links for a bundle order (two PDFs).
	 *
	 * @param \WC_Order $order WooCommerce order.
	 * @param string    $inner_url Natural Attributes (PDF) public URL.
	 * @param string    $social_url Social Fluency (PDF) public URL.
	 * @return bool
	 */
	public static function send_customer_paid_bundle_pdf_download_email($order, $inner_url, $social_url)
	{
		if (!$order instanceof \WC_Order) {
			return false;
		}

		$inner_url = trim((string) $inner_url);
		$social_url = trim((string) $social_url);

		if ('' === $inner_url || '' === $social_url) {
			return false;
		}

		$to = trim((string) $order->get_billing_email());
		if (!is_email($to)) {
			return false;
		}

		$blog_name = get_bloginfo('name');
		$order_no = (string) $order->get_order_number();
		$subject = sprintf(
			/* translators: %s: order number */
			__('Your order #%s is complete', 'rtr-custom-assessment'),
			$order_no
		);

		$first = trim((string) $order->get_billing_first_name());
		$greeting = '' !== $first
			? '<p style="margin-bottom:12px;">' . sprintf(
				/* translators: %s: first name */
				esc_html__('Hi %s,', 'rtr-custom-assessment'),
				esc_html($first)
			) . '</p>'
			: '<p style="margin-bottom:12px;">' . esc_html__('Hello,', 'rtr-custom-assessment') . '</p>';

		$body_inner = $greeting;
		$body_inner .= '<p style="margin-bottom:14px;">' . esc_html__('Your payment was received and your bundle order is complete. Download both reports below.', 'rtr-custom-assessment') . '</p>';

		$body_inner .= '<div class="cta-wrap" style="margin: 18px 0; text-align:center;">';
		$body_inner .= '<p style="margin: 0 0 10px; font-size: 13px; color: #666;">' . esc_html__('Natural Attributes &amp; Social Fluency', 'rtr-custom-assessment') . '</p>';

		$body_inner .= '<div style="margin: 14px 0;">';
		$body_inner .= '<a href="' . esc_url($inner_url) . '" class="cta-btn" style="display:inline-block;background:#aa3130;color:#fff !important;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:600;font-size:15px;margin: 6px;">' . esc_html__('Download Natural Attributes', 'rtr-custom-assessment') . '</a>';
		$body_inner .= '</div>';
		$body_inner .= '<div style="margin: 6px 0 0;">';
		$body_inner .= '<a href="' . esc_url($social_url) . '" class="cta-btn" style="display:inline-block;background:#aa3130;color:#fff !important;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:600;font-size:15px;margin: 6px;">' . esc_html__('Download Social Fluency', 'rtr-custom-assessment') . '</a>';
		$body_inner .= '</div>';
		$body_inner .= '</div>';

		$body = '
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>' . esc_html__('Your order is complete', 'rtr-custom-assessment') . '</title>
			<style>
				* { margin: 0; padding: 0; }
				body {
					font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
					line-height: 1.6;
					color: #333;
					background-color: #f5f5f5;
				}
				.email-container {
					max-width: 600px;
					margin: 20px auto;
					background-color: #fff;
					border-radius: 8px;
					box-shadow: 0 2px 8px rgba(0,0,0,0.1);
					overflow: hidden;
				}
				.email-header {
					background: linear-gradient(135deg, #aa3130 0%, #8b2823 100%);
					color: #fff;
					padding: 30px;
					text-align: center;
				}
				.email-header h1 {
					font-size: 26px;
					margin-bottom: 8px;
				}
				.email-header p {
					font-size: 14px;
					opacity: 0.92;
				}
				.email-content { padding: 30px; }
				.cta-wrap { margin: 20px 0; text-align: center; }
				.footer-section {
					background-color: #f5f5f5;
					padding: 20px 30px;
					border-top: 1px solid #eee;
					font-size: 13px;
					color: #666;
					text-align: center;
				}
			</style>
		</head>
		<body>
			<div class="email-container">
				<div class="email-header">
					<h1>' . esc_html__('Thank you for your purchase', 'rtr-custom-assessment') . '</h1>
					<p>' . esc_html__('Your order is complete', 'rtr-custom-assessment') . '</p>
				</div>
				<div class="email-content">
					' . $body_inner . '
				</div>
				<div class="footer-section">
					<p>&copy; ' . esc_html($blog_name) . ' ' . gmdate('Y') . '. ' . esc_html__('All rights reserved.', 'rtr-custom-assessment') . '</p>
					<p>' . esc_html__('This is an automated message. Please do not reply.', 'rtr-custom-assessment') . '</p>
				</div>
			</div>
		</body>
		</html>';

		$body = apply_filters('ca_customer_paid_bundle_pdf_email_body', $body, $order, $inner_url, $social_url);

		$from_email = (string) get_option('admin_email');
		if (!is_email($from_email)) {
			$from_email = $to;
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $blog_name . ' <' . $from_email . '>',
		);

		return (bool) wp_mail($to, $subject, $body, $headers);
	}

	/**
	 * Notify admin that a customer results email was sent.
	 *
	 * @param object $submission Submission row.
	 * @return void
	 */
	private static function send_admin_results_notification($submission)
	{
		if (!$submission || empty($submission->id)) {
			return;
		}

		$admin_email = (string) get_option('admin_email');
		if (!is_email($admin_email)) {
			return;
		}

		$subject = sprintf(
			/* translators: %d: submission id */
			__('Customer Results Email Sent (Submission #%d)', 'rtr-custom-assessment'),
			(int) $submission->id
		);

		$detail_url = self::get_admin_submission_detail_url($submission);
		$assessment_label = self::assessment_type_label(CA_Assessment_Types::from_submission($submission));
		$name = trim((string) $submission->first_name . ' ' . (string) $submission->last_name);
		if ('' === $name) {
			$name = __('Unknown', 'rtr-custom-assessment');
		}

		$blog_name = get_bloginfo('name');
		$submitted_at = isset($submission->created_at) && '' !== (string) $submission->created_at
			? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime((string) $submission->created_at))
			: '—';

		$body = '
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>' . esc_html__('Customer Results Email Sent', 'rtr-custom-assessment') . '</title>
			<style>
				* { margin: 0; padding: 0; }
				body {
					font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
					line-height: 1.6;
					color: #333;
					background-color: #f5f5f5;
				}
				.email-container {
					max-width: 600px;
					margin: 20px auto;
					background-color: #fff;
					border-radius: 8px;
					box-shadow: 0 2px 8px rgba(0,0,0,0.1);
					overflow: hidden;
				}
				.email-header {
					background: linear-gradient(135deg, #aa3130 0%, #8b2823 100%);
					color: #fff;
					padding: 30px;
					text-align: center;
				}
				.email-header h1 {
					font-size: 26px;
					margin-bottom: 8px;
				}
				.email-header p {
					font-size: 14px;
					opacity: 0.92;
				}
				.email-content {
					padding: 30px;
				}
				.info-table {
					width: 100%;
					font-size: 14px;
					border-collapse: collapse;
					background: #fafafa;
					border: 1px solid #eee;
					border-radius: 6px;
					overflow: hidden;
				}
				.info-table td {
					padding: 10px 12px;
					vertical-align: top;
					border-bottom: 1px solid #eee;
				}
				.info-table tr:last-child td {
					border-bottom: none;
				}
				.info-key {
					width: 34%;
					color: #666;
					font-weight: 600;
					background: #f7f7f7;
				}
				.cta-wrap {
					margin-top: 20px;
					text-align: center;
				}
				.cta-btn {
					display: inline-block;
					background: #aa3130;
					color: #fff !important;
					text-decoration: none;
					padding: 10px 18px;
					border-radius: 6px;
					font-weight: 600;
					font-size: 14px;
				}
				.cta-btn:hover {
					background: #8b2823;
					text-decoration: none;
				}
				.footer-section {
					background-color: #f5f5f5;
					padding: 20px 30px;
					border-top: 1px solid #eee;
					font-size: 13px;
					color: #666;
					text-align: center;
				}
			</style>
		</head>
		<body>
			<div class="email-container">
				<div class="email-header">
					<h1>' . esc_html__('Customer Results Email Sent', 'rtr-custom-assessment') . '</h1>
					<p>' . esc_html__('A customer notification was delivered successfully.', 'rtr-custom-assessment') . '</p>
				</div>

				<div class="email-content">
					<p style="margin-bottom: 14px;">' . esc_html__('A customer results email has just been sent. Submission details are below:', 'rtr-custom-assessment') . '</p>

					<table class="info-table" role="presentation">
						<tr>
							<td class="info-key">' . esc_html__('Submission ID', 'rtr-custom-assessment') . '</td>
							<td>' . esc_html((string) ((int) $submission->id)) . '</td>
						</tr>
						<tr>
							<td class="info-key">' . esc_html__('Assessment', 'rtr-custom-assessment') . '</td>
							<td>' . esc_html($assessment_label) . '</td>
						</tr>
						<tr>
							<td class="info-key">' . esc_html__('Name', 'rtr-custom-assessment') . '</td>
							<td>' . esc_html($name) . '</td>
						</tr>
						<tr>
							<td class="info-key">' . esc_html__('Email', 'rtr-custom-assessment') . '</td>
							<td>' . esc_html((string) $submission->email) . '</td>
						</tr>
						<tr>
							<td class="info-key">' . esc_html__('Submitted', 'rtr-custom-assessment') . '</td>
							<td>' . esc_html($submitted_at) . '</td>
						</tr>
					</table>

					<div class="cta-wrap">
						<a href="' . esc_url($detail_url) . '" class="cta-btn">' . esc_html__('Open Submission Detail', 'rtr-custom-assessment') . '</a>
					</div>
				</div>

				<div class="footer-section">
					<p>&copy; ' . esc_html($blog_name) . ' ' . gmdate('Y') . '. ' . esc_html__('All rights reserved.', 'rtr-custom-assessment') . '</p>
					<p>' . esc_html__('This is an automated admin notification.', 'rtr-custom-assessment') . '</p>
				</div>
			</div>
		</body>
		</html>';

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo('name') . ' <' . $admin_email . '>',
		);

		wp_mail($admin_email, $subject, $body, $headers);
	}

	/**
	 * Notify admin that a bundle completion email was sent.
	 *
	 * @param object $inner_submission  Natural Attributes submission.
	 * @param object $social_submission Social Fluency submission.
	 * @return void
	 */
	private static function send_admin_bundle_results_notification($inner_submission, $social_submission)
	{
		if (!$inner_submission || !$social_submission) {
			return;
		}

		$admin_email = (string) get_option('admin_email');
		if (!is_email($admin_email)) {
			return;
		}

		$name = trim((string) $inner_submission->first_name . ' ' . (string) $inner_submission->last_name);
		if ('' === $name) {
			$name = __('Unknown', 'rtr-custom-assessment');
		}

		$subject = sprintf(
			/* translators: %s: customer name */
			__('Bundle Results Email Sent (%s)', 'rtr-custom-assessment'),
			$name
		);

		$inner_url = self::get_admin_submission_detail_url($inner_submission);
		$social_url = self::get_admin_submission_detail_url($social_submission);

		$body = '
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>' . esc_html__('Bundle Results Email Sent', 'rtr-custom-assessment') . '</title>
		</head>
		<body style="font-family:Segoe UI,Tahoma,Geneva,Verdana,sans-serif;line-height:1.6;color:#333;background:#f5f5f5;margin:0;padding:20px;">
			<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;">
				<div style="background:linear-gradient(135deg,#aa3130 0%,#8b2823 100%);color:#fff;padding:24px 22px;">
					<h1 style="margin:0 0 6px;font-size:24px;">' . esc_html__('Bundle Results Email Sent', 'rtr-custom-assessment') . '</h1>
					<p style="margin:0;opacity:.9;">' . esc_html__('Customer bundle completion notification delivered.', 'rtr-custom-assessment') . '</p>
				</div>
				<div style="padding:22px;">
					<p><strong>' . esc_html($name) . '</strong> (' . esc_html((string) $inner_submission->email) . ')</p>
					<p style="margin:8px 0;">' . esc_html__('Natural Attributes submission:', 'rtr-custom-assessment') . ' #' . esc_html((string) ((int) $inner_submission->id)) . '</p>
					<p style="margin:8px 0;">' . esc_html__('Social Fluency submission:', 'rtr-custom-assessment') . ' #' . esc_html((string) ((int) $social_submission->id)) . '</p>
					<p style="margin:14px 0 0;"><a href="' . esc_url($inner_url) . '">' . esc_html__('Open Natural Attributes submission', 'rtr-custom-assessment') . '</a></p>
					<p style="margin:8px 0 0;"><a href="' . esc_url($social_url) . '">' . esc_html__('Open Social Fluency submission', 'rtr-custom-assessment') . '</a></p>
				</div>
			</div>
		</body>
		</html>';

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo('name') . ' <' . $admin_email . '>',
		);
		wp_mail($admin_email, $subject, $body, $headers);
	}

	/**
	 * Build admin detail URL for the submission.
	 *
	 * @param object $submission Submission row.
	 * @return string
	 */
	private static function get_admin_submission_detail_url($submission)
	{
		$submission_id = isset($submission->id) ? (int) $submission->id : 0;
		$page = 'custom-assessment-mindset';
		$type = CA_Assessment_Types::from_submission($submission);

		if (CA_Assessment_Types::SOCIAL_FLUENCY === $type) {
			$page = 'custom-assessment-social';
		} elseif (CA_Assessment_Types::INNER_DIMENSIONS === $type) {
			$page = 'custom-assessment-inner';
		}

		return add_query_arg(
			array(
				'page' => $page,
				'ca_tab' => 'submissions',
				'view' => 'detail',
				'id' => $submission_id,
			),
			admin_url('admin.php')
		);
	}

	/**
	 * Human label for assessment type.
	 *
	 * @param string $type Normalized assessment type.
	 * @return string
	 */
	private static function assessment_type_label($type)
	{
		$type = CA_Assessment_Types::normalize($type);
		if (CA_Assessment_Types::SOCIAL_FLUENCY === $type) {
			return __('Social Fluency', 'rtr-custom-assessment');
		}
		if (CA_Assessment_Types::INNER_DIMENSIONS === $type) {
			return __('Natural Attributes Cataloging', 'rtr-custom-assessment');
		}
		return __('Mindset', 'rtr-custom-assessment');
	}

	/**
	 * Build detailed HTML email body with assessment results.
	 *
	 * @param object $submission Submission record
	 * @param array  $cat_scores Category scores
	 * @return string HTML email body
	 */
	private static function build_email_body($submission, $cat_scores)
	{
		$blog_name = get_bloginfo('name');
		$assessment_type = CA_Assessment_Types::from_submission($submission);
		$needs_paywall = CA_Assessment_Types::requires_paid_full_results($assessment_type);
		$scale_max = CA_Assessment_Types::get_scale_max($assessment_type);
		$total_questions = CA_Assessment_Registry::get_total_count($assessment_type);
		$max_score = $total_questions * $scale_max;
		$overall_profile = CA_Scoring::get_overall_profile((float) $submission->average_score, $assessment_type);

		$paid_unlocked = $needs_paywall && self::submission_has_paid_full_results_order($submission);
		$paywall_url = '';
		if ($needs_paywall && !$paid_unlocked) {
			$ajax = CA_Ajax::get_instance();
			if ($ajax) {
				$paywall_url = $ajax->get_paid_full_results_order_pay_url_for_submission((int) $submission->id);
			}
			if ('' === $paywall_url && function_exists('wc_get_checkout_url')) {
				$paywall_url = wc_get_checkout_url();
			}
		}

		$header_tagline = $needs_paywall
			? esc_html__('Unlock your full report', 'rtr-custom-assessment')
			: esc_html__('Your Results Summary', 'rtr-custom-assessment');
		$intro_follow = $needs_paywall
			? esc_html__('Thank you for completing the assessment. Use the secure link below to open your personal checkout page and unlock your full results—the same link you get after finishing the assessment.', 'rtr-custom-assessment')
			: esc_html__('Thank you for completing the assessment. Below is your detailed results summary.', 'rtr-custom-assessment');

		$body = '
		<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>Assessment Results</title>
			<style>
				* { margin: 0; padding: 0; }
				body {
					font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
					line-height: 1.6;
					color: #333;
					background-color: #f5f5f5;
				}
				.email-container {
					max-width: 600px;
					margin: 20px auto;
					background-color: #fff;
					border-radius: 8px;
					box-shadow: 0 2px 8px rgba(0,0,0,0.1);
					overflow: hidden;
				}
				.email-header {
					background: linear-gradient(135deg, #aa3130 0%, #8b2823 100%);
					color: white;
					padding: 30px;
					text-align: center;
				}
				.email-header h1 {
					font-size: 28px;
					margin-bottom: 10px;
				}
				.email-header p {
					font-size: 14px;
					opacity: 0.9;
				}
				.email-content {
					padding: 30px;
				}
				.intro-text {
					margin-bottom: 30px;
					font-size: 16px;
					color: #555;
				}
				.intro-text strong {
					color: #333;
				}
				.section {
					margin-bottom: 30px;
					padding-left: 20px;
				}
				.section-title {
					font-size: 18px;
					font-weight: 600;
					color: #333;
					margin-bottom: 15px;
				}
				.score-box {
					background-color: #f9f9f9;
					border-radius: 6px;
					padding: 15px;
					margin-bottom: 12px;
				}
				.score-header {
					display: flex;
					justify-content: space-between;
					align-items: center;
					margin-bottom: 8px;
				}
				.score-name {
					font-weight: 600;
					color: #333;
				}
				.score-value {
					font-size: 18px;
					font-weight: 700;
					color: #aa3130;
				}
				.score-summary {
					font-size: 14px;
					color: #666;
					margin-top: 8px;
				}
				.profile-box {
					background: linear-gradient(135deg, #f8e8e8 0%, #faf5f5 100%);
					border-radius: 6px;
					padding: 15px;
					text-align: center;
				}
				.profile-label {
					font-size: 12px;
					color: #666;
					margin-bottom: 8px;
				}
				.profile-value {
					font-size: 22px;
					font-weight: 700;
					color: #aa3130;
				}
				.overall-stats {
					display: grid;
					grid-template-columns: 1fr 1fr;
					gap: 15px;
					margin-bottom: 25px;
				}
				.stat-box {
					background-color: #f0f0f0;
					padding: 15px;
					border-radius: 6px;
					text-align: center;
				}
				.stat-value {
					font-size: 24px;
					font-weight: 700;
					color: #aa3130;
					margin-bottom: 5px;
				}
				.stat-label {
					font-size: 13px;
					color: #666;
				}
				.categories-list {
					margin-bottom: 25px;
				}
				.category-item {
					background-color: #f9f9f9;
					border-radius: 6px;
					padding: 15px;
					margin-bottom: 12px;
				}
				.category-header {
					display: flex;
					justify-content: space-between;
					align-items: center;
					margin-bottom: 8px;
				}
				.category-name {
					font-weight: 600;
					color: #333;
				}
				.category-score {
					font-size: 16px;
					font-weight: 700;
					color: #aa3130;
				}
				.category-summary {
					font-size: 13px;
					color: #666;
					margin-top: 8px;
				}
				.footer-section {
					background-color: #f5f5f5;
					padding: 20px 30px;
					border-top: 1px solid #eee;
					font-size: 13px;
					color: #666;
					text-align: center;
				}
				.footer-section p {
					margin-bottom: 10px;
				}
				.footer-section a {
					color: #aa3130;
					text-decoration: none;
				}
				.footer-section a:hover {
					text-decoration: underline;
				}
				.highlight {
					background-color: #fff3cd;
					padding: 2px 4px;
					border-radius: 2px;
				}
				.paywall-btn-wrap {
					margin-top: 14px;
				}
				.paywall-btn {
					display: inline-block;
					background: #aa3130;
					color: #ffffff !important;
					text-decoration: none;
					padding: 10px 18px;
					border-radius: 6px;
					font-weight: 600;
					font-size: 14px;
				}
				.paywall-btn:hover {
					background: #8b2823;
					text-decoration: none;
				}
				.preview-card {
					background: #fff;
					border: 1px solid #e4d7ca;
					border-radius: 8px;
					box-shadow: 0 8px 22px rgba(0, 0, 0, 0.06);
					padding: 16px 18px;
				}
				.preview-kicker {
					margin: 0 0 12px;
					font-size: 12px;
					font-weight: 700;
					letter-spacing: 0.08em;
					text-transform: uppercase;
					color: #8d2b28;
				}
				.preview-row {
					padding: 10px 0;
					border-top: 1px solid #efe3d6;
					font-size: 14px;
					color: #333;
				}
				.preview-row:last-of-type {
					border-bottom: 1px solid #efe3d6;
				}
				.preview-note {
					margin: 12px 0 0;
					font-size: 13px;
					color: #666;
				}
			</style>
		</head>
		<body>
			<div class="email-container">
				<!-- Header -->
				<div class="email-header">
					<h1>Assessment Complete!</h1>
					<p>' . $header_tagline . '</p>
				</div>

				<!-- Content -->
				<div class="email-content">
					<div class="intro-text">
						<p>Dear <strong>' . esc_html($submission->first_name . ' ' . $submission->last_name) . '</strong>,</p>
						<p style="margin-top: 10px;">' . $intro_follow . '</p>
					</div>';

		if (!$needs_paywall) {
			$body .= '
					<!-- Overall Scores -->
					<div class="section">
						<div class="section-title">📊 Overall Performance</div>
						
						<div class="overall-stats">
							<div class="stat-box">
								<div class="stat-value">' . esc_html($submission->total_score) . ' / ' . esc_html($max_score) . '</div>
								<div class="stat-label">Total Score</div>
							</div>
							<div class="stat-box">
								<div class="stat-value">' . esc_html(number_format($submission->average_score, 2)) . ' / ' . esc_html(number_format((float) $scale_max, 2)) . '</div>
								<div class="stat-label">Average Score</div>
							</div>
						</div>

						<div class="profile-box">
							<div class="profile-label">Your Assessment Profile</div>
							<div class="profile-value">' . esc_html($overall_profile) . '</div>
						</div>
					</div>

					<!-- Category Breakdown -->
					<div class="section">
						<div class="section-title">📈 Category Breakdown</div>
						<div class="categories-list">';

			foreach ($cat_scores as $cat) {
				$q_count = ($cat->average > 0) ? (int) round((float) $cat->subtotal / (float) $cat->average) : 0;
				$cat_max = $q_count * $scale_max;
				$body .= '
						<div class="category-item">
							<div class="category-header">
								<span class="category-name">' . esc_html($cat->category_name) . '</span>
								<span class="category-score">' . esc_html(number_format((float) $cat->average, 2)) . ' / ' . esc_html(number_format((float) $scale_max, 2)) . ' &nbsp;·&nbsp; ' . esc_html($cat->subtotal) . ' / ' . esc_html($cat_max) . '</span>
							</div>
							<div class="score-summary">' . esc_html(CA_Scoring::get_category_summary($cat->category_name, (float) $cat->average, $assessment_type)) . '</div>
						</div>';
			}

			$body .= '
						</div>
					</div>';
		}

		$nac_preview_html = '';
		if (CA_Assessment_Types::INNER_DIMENSIONS === $assessment_type) {
			$top_cat = null;
			foreach ($cat_scores as $cat) {
				if (!$top_cat || (float) $cat->average > (float) $top_cat->average) {
					$top_cat = $cat;
				}
			}

			$top_attr_label = $top_cat
				? (string) $top_cat->category_name
				: __('Your strongest natural attribute', 'rtr-custom-assessment');
			$pattern_summary = $top_cat
				? CA_Scoring::get_category_summary((string) $top_cat->category_name, (float) $top_cat->average, $assessment_type)
				: __('You respond most strongly where your natural strengths are already active.', 'rtr-custom-assessment');

			$nac_preview_html = '
					<div class="section">
						<div class="preview-card">
							<p class="preview-kicker">' . esc_html__('See your preview. A snapshot of what the assessment surfaced.', 'rtr-custom-assessment') . '</p>
							<div class="preview-row"><strong>' . esc_html__('Top attribute surfaced:', 'rtr-custom-assessment') . '</strong> ' . esc_html($top_attr_label) . '</div>
							<div class="preview-row"><strong>' . esc_html__('Pattern revealed:', 'rtr-custom-assessment') . '</strong> ' . esc_html($pattern_summary) . '</div>
							<p class="preview-note">' . esc_html__('This is meaningful but incomplete. Unlock the full report for your full breakdown and all responses.', 'rtr-custom-assessment') . '</p>
						</div>
					</div>';
		}

		$social_preview_html = '';
		if (CA_Assessment_Types::SOCIAL_FLUENCY === $assessment_type && $needs_paywall && ! $paid_unlocked) {
			$top_cat = null;
			foreach ($cat_scores as $cat) {
				if (!$top_cat || (float) $cat->average > (float) $top_cat->average) {
					$top_cat = $cat;
				}
			}

			$domain_name = $top_cat ? (string) $top_cat->category_name : __('your strongest domain', 'rtr-custom-assessment');
			$domain_summary = $top_cat
				? CA_Scoring::get_category_summary((string) $top_cat->category_name, (float) $top_cat->average, $assessment_type)
				: __('This is where your social strengths show up most clearly.', 'rtr-custom-assessment');

			$social_preview_html = '
					<div class="section">
						<div class="preview-card">
							<p class="preview-kicker">' . esc_html__('Your overall Social Fluency tier and one domain to notice. Free.', 'rtr-custom-assessment') . '</p>
							<div class="preview-row"><strong>' . esc_html__('Overall tier:', 'rtr-custom-assessment') . '</strong> ' . esc_html((string) $overall_profile) . '</div>
							<div class="preview-row"><strong>' . esc_html__('Domain to notice:', 'rtr-custom-assessment') . '</strong> ' . esc_html($domain_name) . '</div>
							<p class="preview-note">' . esc_html($domain_summary) . '</p>
							<p class="preview-note">' . esc_html__('This is meaningful but incomplete. Unlock the full report to explore your complete breakdown and every response.', 'rtr-custom-assessment') . '</p>
						</div>
					</div>';
		}

		$paywall_email_cta = '';
		if ($needs_paywall && $paid_unlocked) {
			$paywall_email_cta = '
						<p style="margin: 12px 0 0; color: #666; font-size: 14px;">' . esc_html__('Your payment is complete. Your full results are available from your order confirmation and in your account order details.', 'rtr-custom-assessment') . '</p>';
		} elseif ($needs_paywall && !$paid_unlocked && '' !== trim($paywall_url)) {
			$paywall_email_cta = '
						<div class="paywall-btn-wrap">
							<a href="' . esc_url($paywall_url) . '" class="paywall-btn">&#128722; Get the Full Result</a>
						</div>';
		}

		$body .= $nac_preview_html . $social_preview_html . '
					<!-- Submission Details -->
					<div class="section">
						<div class="section-title" style="color: #666;">Submission Details</div>
						<table style="width: 100%; font-size: 14px;">
							<tr>
								<td style="padding: 8px; color: #666;"><strong>Submission Date:</strong></td>
								<td style="padding: 8px; color: #333;">' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->created_at))) . '</td>
							</tr>
							<tr style="background-color: #f9f9f9;">
								<td style="padding: 8px; color: #666;"><strong>Email:</strong></td>
								<td style="padding: 8px; color: #333;">' . esc_html($submission->email) . '</td>
							</tr>
							<tr>
								<td style="padding: 8px; color: #666;"><strong>Job Title:</strong></td>
								<td style="padding: 8px; color: #333;">' . esc_html($submission->job_title) . '</td>
							</tr>
						</table>
					</div>

					<!-- Call to Action -->
					<div style="background-color: #f0f0f0; border-radius: 6px; padding: 20px; text-align: center; margin-top: 25px;">
						<p style="margin: 0; color: #666; font-size: 14px;">
							Thank you for taking the time to complete this assessment. If you have any questions, please don\'t hesitate to reach out.
						</p>
						' . $paywall_email_cta . '
					</div>
				</div>

				<!-- Footer -->
				<div class="footer-section">
					<p>&copy; ' . esc_html($blog_name) . ' ' . gmdate('Y') . '. All rights reserved.</p>
					<p>This is an automated email. Please do not reply to this message.</p>
				</div>
			</div>
		</body>
		</html>';

		return $body;
	}

	/**
	 * Whether this submission already has a paid WooCommerce order for the same paid-results assessment type.
	 *
	 * @param object $submission Submission row.
	 * @return bool
	 */
	private static function submission_has_paid_full_results_order($submission)
	{
		if (!$submission || empty($submission->id) || !function_exists('wc_get_orders')) {
			return false;
		}

		$submission_id = (int) $submission->id;
		$atype = CA_Assessment_Types::from_submission($submission);
		if (!CA_Assessment_Types::requires_paid_full_results($atype)) {
			return false;
		}

		$order_ids = wc_get_orders(array(
			'limit' => 1,
			'orderby' => 'date',
			'order' => 'DESC',
			'status' => array('completed', 'processing'),
			'meta_query' => array(
				array(
					'key' => '_ca_submission_id',
					'value' => $submission_id,
				),
				array(
					'key' => '_ca_assessment_type',
					'value' => $atype,
				),
			),
			'return' => 'ids',
		));

		if (empty($order_ids)) {
			return false;
		}

		$order = wc_get_order((int) $order_ids[0]);
		if (!$order instanceof \WC_Order) {
			return false;
		}

		return $order->is_paid();
	}
}
