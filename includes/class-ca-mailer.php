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

		return $sent;
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
		$is_nac = ($assessment_type === CA_Assessment_Types::INNER_DIMENSIONS);
		$results_mask_style = $is_nac ? ' style="filter: blur(8px); opacity: 0.22; user-select: none; pointer-events: none;"' : '';
		$scale_max = CA_Assessment_Types::get_scale_max($assessment_type);
		$total_questions = CA_Assessment_Registry::get_total_count($assessment_type);
		$max_score = $total_questions * $scale_max;
		$overall_profile = CA_Scoring::get_overall_profile((float) $submission->average_score, $assessment_type);

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
			</style>
		</head>
		<body>
			<div class="email-container">
				<!-- Header -->
				<div class="email-header">
					<h1>Assessment Complete!</h1>
					<p>Your Results Summary</p>
				</div>

				<!-- Content -->
				<div class="email-content">
					<div class="intro-text">
						<p>Dear <strong>' . esc_html($submission->first_name . ' ' . $submission->last_name) . '</strong>,</p>
						<p style="margin-top: 10px;">Thank you for completing the assessment. Below is your detailed results summary.</p>
					</div>

					<!-- Overall Scores -->
					<div class="section"' . $results_mask_style . '>
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
					<div class="section"' . $results_mask_style . '>
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

		$paywall_email_cta = '';
		if ($is_nac) {
			$paywall_email_cta = '
						<div class="paywall-btn-wrap">
							<a href="' . esc_url(home_url('/')) . '" class="paywall-btn">&#128722; Get the Full Result</a>
						</div>';
		}

		$body .= '
						</div>
					</div>

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
}
