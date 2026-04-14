<?php
/**
 * AJAX handler — registers all wp_ajax / wp_ajax_nopriv hooks.
 */

if (!defined('ABSPATH')) {
	exit;
}

class CA_Ajax
{
	private function send_error($action, $message, $context = array())
	{
		CA_Logger::log($action, 'error', $message, $context);
		wp_send_json_error(array('message' => $message));
	}

	private function send_success($action, $data = array(), $message = '', $context = array())
	{
		CA_Logger::log($action, 'success', $message, $context);
		wp_send_json_success($data);
	}

	public function __construct()
	{
		$actions = array(
			'ca_save_user_info',
			'ca_save_answer',
			'ca_get_question',
			'ca_get_progress',
			'ca_find_in_progress_by_email',
			'ca_submit_assessment',
			'ca_get_results_preview',
			'ca_prepare_inner_dimensions_checkout',
		);

		foreach ($actions as $action) {
			add_action('wp_ajax_' . $action, array($this, $action));
			add_action('wp_ajax_nopriv_' . $action, array($this, $action));
		}

		add_action('woocommerce_order_status_processing', array($this, 'maybe_send_inner_dimensions_results_after_payment'));
		add_action('woocommerce_order_status_completed', array($this, 'maybe_send_inner_dimensions_results_after_payment'));
	}

	/**
	 * Check whether WooCommerce is available.
	 *
	 * @return bool
	 */
	private function is_woocommerce_ready()
	{
		return function_exists('wc_create_order') && function_exists('wc_get_order') && class_exists('WC_Product_Simple');
	}

	// -------------------------------------------------------------------------
	// Nonce helper
	// -------------------------------------------------------------------------

	private function verify_nonce($action = 'unknown')
	{
		if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ca_nonce')) {
			$this->send_error($action, __('Security check failed.', 'rtr-custom-assessment'));
		}
	}

	/**
	 * Assessment type from POST (defaults to mindset).
	 *
	 * @return string Normalized type.
	 */
	private function get_assessment_type_from_request()
	{
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified per action before this runs.
		$raw = isset($_POST['assessment_type']) ? sanitize_key(wp_unslash($_POST['assessment_type'])) : CA_Assessment_Types::MINDSET;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return CA_Assessment_Types::normalize($raw);
	}

	/**
	 * Ensure submission exists and matches the assessment type in the request.
	 *
	 * @param int    $submission_id   Submission ID.
	 * @param string $assessment_type Requested type.
	 * @return object WP DB row.
	 */
	private function require_submission_for_type($submission_id, $assessment_type)
	{
		$submission = CA_Database::get_submission((int) $submission_id);
		if (!$submission) {
			$this->send_error('ca_session', __('Submission not found.', 'rtr-custom-assessment'), array('submission_id' => $submission_id));
		}
		$stored = CA_Assessment_Types::from_submission($submission);
		$want = CA_Assessment_Types::normalize($assessment_type);
		if ($stored !== $want) {
			$this->send_error('ca_session', __('This session does not match the selected assessment. Please start again.', 'rtr-custom-assessment'));
		}
		return $submission;
	}

	// -------------------------------------------------------------------------
	// Action: save user info (Step 1)
	// -------------------------------------------------------------------------

	public function ca_save_user_info()
	{
		$this->verify_nonce('ca_save_user_info');

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified via $this->verify_nonce().
		$first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
		$last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
		$email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
		$phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
		$job_title = isset($_POST['job_title']) ? sanitize_text_field(wp_unslash($_POST['job_title'])) : '';
		$assessment_type = $this->get_assessment_type_from_request();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Validate
		$errors = array();
		if (empty($first_name))
			$errors[] = __('First name is required.', 'rtr-custom-assessment');
		if (empty($last_name))
			$errors[] = __('Last name is required.', 'rtr-custom-assessment');
		if (empty($email) || !is_email($email))
			$errors[] = __('A valid email is required.', 'rtr-custom-assessment');
		if (empty($phone))
			$errors[] = __('Phone number is required.', 'rtr-custom-assessment');
		if (empty($job_title))
			$errors[] = __('Job title is required.', 'rtr-custom-assessment');

		if (!empty($errors)) {
			$this->send_error('ca_save_user_info', implode(' ', $errors));
		}

		$submission_id = CA_Database::insert_submission(array(
			'first_name' => $first_name,
			'last_name' => $last_name,
			'email' => $email,
			'phone' => $phone,
			'job_title' => $job_title,
			'assessment_type' => $assessment_type,
		));

		if (!$submission_id) {
			$this->send_error('ca_save_user_info', __('Could not save your information. Please try again.', 'rtr-custom-assessment'));
		}

		$this->send_success(
			'ca_save_user_info',
			array(
			'submission_id' => $submission_id,
			'message' => __('Information saved.', 'rtr-custom-assessment'),
			),
			'Information saved.',
			array('submission_id' => $submission_id)
		);
	}

	// -------------------------------------------------------------------------
	// Action: get question by index
	// -------------------------------------------------------------------------

	public function ca_get_question()
	{
		$this->verify_nonce('ca_get_question');

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified via $this->verify_nonce().
		$index = isset($_POST['question_index']) ? absint($_POST['question_index']) : 0;
		$submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
		$assessment_type = $this->get_assessment_type_from_request();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ($submission_id) {
			$this->require_submission_for_type($submission_id, $assessment_type);
		}

		$question = CA_Assessment_Registry::get_question($assessment_type, $index);

		if (!$question) {
			$this->send_error('ca_get_question', __('Question not found.', 'rtr-custom-assessment'), array('question_index' => $index));
		}

		$scale_max = CA_Assessment_Types::get_scale_max($assessment_type);
		$payload = $question;
		$payload['scale_max'] = $scale_max;

		if (CA_Assessment_Types::SOCIAL_FLUENCY === $assessment_type) {
			$eps = isset($question['endpoints']) && is_array($question['endpoints']) ? $question['endpoints'] : array();
			$has_eps = !empty($eps['left']) || !empty($eps['right']) || !empty($eps['mid']);
			if ($has_eps) {
				$payload['label_style'] = 'endpoints';
				$payload['endpoints'] = $eps;
			} else {
				$payload['label_style'] = 'per_number';
				$payload['endpoints'] = array();
			}
		} elseif (CA_Assessment_Types::INNER_DIMENSIONS === $assessment_type) {
			$payload['label_style'] = 'yes_no';
			$payload['scale_max']   = 2;
		} else {
			$payload['label_style'] = 'per_number';
		}

		$saved_answer = $submission_id ? CA_Database::get_answer($submission_id, $index) : null;
		$total = CA_Assessment_Registry::get_total_count($assessment_type);
		$progress = $total > 0 ? round(($index / $total) * 100) : 0;

		$this->send_success('ca_get_question', array(
			'question' => $payload,
			'saved_answer' => $saved_answer,
			'total' => $total,
			'progress' => $progress,
			'is_last' => ($index === $total - 1),
			'scale_max' => $scale_max,
			'assessment_type' => $assessment_type,
		), '', array('submission_id' => $submission_id, 'question_index' => $index));
	}

	// -------------------------------------------------------------------------
	// Action: save answer
	// -------------------------------------------------------------------------

	public function ca_save_answer()
	{
		$this->verify_nonce('ca_save_answer');

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified via $this->verify_nonce().
		$submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
		$question_index = isset($_POST['question_index']) ? absint($_POST['question_index']) : 0;
		$answer = isset($_POST['answer']) ? absint($_POST['answer']) : 0;
		$assessment_type = $this->get_assessment_type_from_request();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Validate
		if (!$submission_id) {
			$this->send_error('ca_save_answer', __('Invalid session. Please refresh and try again.', 'rtr-custom-assessment'));
		}

		$this->require_submission_for_type($submission_id, $assessment_type);

		if (CA_Assessment_Types::is_yes_no_assessment($assessment_type)) {
			if (1 !== $answer && 2 !== $answer) {
				$this->send_error('ca_save_answer', __('Please select Yes or No.', 'rtr-custom-assessment'));
			}
		} else {
			$scale_max = CA_Assessment_Types::get_scale_max($assessment_type);
			if ($answer < 1 || $answer > $scale_max) {
				$this->send_error(
					'ca_save_answer',
					sprintf(
						/* translators: %d: maximum scale value */
						__('Invalid answer. Please select a value between 1 and %d.', 'rtr-custom-assessment'),
						$scale_max
					)
				);
			}
		}

		CA_Database::save_answer($submission_id, $question_index, $answer);
		CA_Database::set_in_progress($submission_id);

		$total = CA_Assessment_Registry::get_total_count($assessment_type);
		$next = $question_index + 1;
		$progress = $total > 0 ? round(($next / $total) * 100) : 0;

		$this->send_success('ca_save_answer', array(
			'next_index' => $next,
			'progress' => $progress,
			'is_last' => ($next >= $total),
		), '', array('submission_id' => $submission_id, 'question_index' => $question_index, 'answer' => $answer));
	}

	// -------------------------------------------------------------------------
	// Action: get progress
	// -------------------------------------------------------------------------

	public function ca_get_progress()
	{
		$this->verify_nonce('ca_get_progress');

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified via $this->verify_nonce().
		$submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
		$assessment_type = $this->get_assessment_type_from_request();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if (!$submission_id) {
			$this->send_error('ca_get_progress', __('Invalid session.', 'rtr-custom-assessment'));
		}

		$submission = $this->require_submission_for_type($submission_id, $assessment_type);

		$answers = CA_Database::get_answers($submission_id);
		$total = CA_Assessment_Registry::get_total_count($assessment_type);
		$answered = count($answers);
		$progress = $total > 0 ? round(($answered / $total) * 100) : 0;

		$this->send_success('ca_get_progress', array(
			'answered' => $answered,
			'total' => $total,
			'progress' => $progress,
			'status' => $submission->status,
			'email' => $submission->email,
		), '', array('submission_id' => $submission_id));
	}

	// -------------------------------------------------------------------------
	// Action: find in-progress submission by email
	// -------------------------------------------------------------------------

	public function ca_find_in_progress_by_email()
	{
		$this->verify_nonce('ca_find_in_progress_by_email');

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified via $this->verify_nonce().
		$email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
		$assessment_type = $this->get_assessment_type_from_request();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if (empty($email) || !is_email($email)) {
			$this->send_error('ca_find_in_progress_by_email', __('A valid email is required.', 'rtr-custom-assessment'));
		}

		$submission = CA_Database::get_in_progress_submission_by_email($email, $assessment_type);

		if (!$submission) {
			$this->send_success('ca_find_in_progress_by_email', array('found' => false), '', array('email' => $email));
		}

		$answers = CA_Database::get_answers($submission->id);
		$total = CA_Assessment_Registry::get_total_count($assessment_type);
		$answered = count($answers);
		$progress = $total > 0 ? round(($answered / $total) * 100) : 0;

		$this->send_success('ca_find_in_progress_by_email', array(
			'found' => true,
			'submission_id' => $submission->id,
			'email' => $submission->email,
			'answered' => $answered,
			'total' => $total,
			'progress' => $progress,
			'status' => $submission->status,
			// Used by the frontend to continue in the correct priority-based order.
			'answers_map' => $answers,
		), '', array('submission_id' => $submission->id, 'email' => $submission->email));
	}

	// -------------------------------------------------------------------------
	// Action: submit assessment (calculate scores)
	// -------------------------------------------------------------------------

	public function ca_submit_assessment()
	{
		$this->verify_nonce('ca_submit_assessment');

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified via $this->verify_nonce().
		$submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
		$assessment_type = $this->get_assessment_type_from_request();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if (!$submission_id) {
			$this->send_error('ca_submit_assessment', __('Invalid session.', 'rtr-custom-assessment'));
		}

		$this->require_submission_for_type($submission_id, $assessment_type);

		$answers = CA_Database::get_answers($submission_id);
		$total_q = CA_Assessment_Registry::get_total_count($assessment_type);

		if (count($answers) < $total_q) {
			$this->send_error('ca_submit_assessment', __('Please answer all questions before submitting.', 'rtr-custom-assessment'), array('submission_id' => $submission_id));
		}

		$scoring = CA_Scoring::calculate_for_assessment($assessment_type, $answers);

		CA_Database::update_submission_scores(
			$submission_id,
			$scoring['total_score'],
			$scoring['average_score']
		);

		CA_Database::save_category_scores($submission_id, $scoring['category_scores']);

		// For Natural Attributes Cataloging, send results only after Woo payment.
		if (CA_Assessment_Types::INNER_DIMENSIONS !== $assessment_type) {
			CA_Mailer::send_results_email($submission_id);
		}

		$this->send_success('ca_submit_assessment', array(
			'message' => __('Assessment submitted.', 'rtr-custom-assessment'),
		), 'Assessment submitted.', array('submission_id' => $submission_id));
	}

	// -------------------------------------------------------------------------
	// Action: get results preview
	// -------------------------------------------------------------------------

	public function ca_get_results_preview()
	{
		$this->verify_nonce('ca_get_results_preview');

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified via $this->verify_nonce().
		$submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
		$assessment_type = $this->get_assessment_type_from_request();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if (!$submission_id) {
			$this->send_error('ca_get_results_preview', __('Invalid session.', 'rtr-custom-assessment'));
		}

		$submission = $this->require_submission_for_type($submission_id, $assessment_type);
		$cat_scores_raw = CA_Database::get_category_scores($submission_id);

		$stored_type = CA_Assessment_Types::from_submission($submission);
		$scale_max = CA_Assessment_Types::get_scale_max($stored_type);
		$total_q = CA_Assessment_Registry::get_total_count($stored_type);

		// Build category data with summaries
		$category_scores = array();
		foreach ($cat_scores_raw as $cat) {
			$category_scores[] = array(
				'name' => $cat->category_name,
				'subtotal' => (int) $cat->subtotal,
				'average' => (float) $cat->average,
				'summary' => CA_Scoring::get_category_summary($cat->category_name, (float) $cat->average, $stored_type),
			);
		}

		$overall_profile = CA_Scoring::get_overall_profile((float) $submission->average_score, $stored_type);

		$this->send_success('ca_get_results_preview', array(
			'user' => array(
				'first_name' => esc_html($submission->first_name),
				'last_name' => esc_html($submission->last_name),
				'email' => esc_html($submission->email),
				'phone' => esc_html($submission->phone),
				'job_title' => esc_html($submission->job_title),
			),
			'total_score' => (int) $submission->total_score,
			'average_score' => (float) $submission->average_score,
			'overall_profile' => $overall_profile,
			'category_scores' => $category_scores,
			'max_score' => $total_q * $scale_max,
			'scale_max' => $scale_max,
			'assessment_type' => $stored_type,
		), '', array('submission_id' => $submission_id));
	}

	/**
	 * Create/reuse WooCommerce order for Natural Attributes Cataloging payment.
	 */
	public function ca_prepare_inner_dimensions_checkout()
	{
		$this->verify_nonce('ca_prepare_inner_dimensions_checkout');

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified via $this->verify_nonce().
		$submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
		$assessment_type = $this->get_assessment_type_from_request();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if (!$submission_id) {
			$this->send_error('ca_prepare_inner_dimensions_checkout', __('Invalid session.', 'rtr-custom-assessment'));
		}

		if (CA_Assessment_Types::INNER_DIMENSIONS !== $assessment_type) {
			$this->send_error('ca_prepare_inner_dimensions_checkout', __('This payment flow is only available for Natural Attributes Cataloging.', 'rtr-custom-assessment'));
		}

		if (!$this->is_woocommerce_ready()) {
			$this->send_error('ca_prepare_inner_dimensions_checkout', __('WooCommerce is required for checkout, but it is not active.', 'rtr-custom-assessment'));
		}

		$submission = $this->require_submission_for_type($submission_id, $assessment_type);
		if ('completed' !== $submission->status) {
			$this->send_error('ca_prepare_inner_dimensions_checkout', __('Please complete all questions before proceeding to checkout.', 'rtr-custom-assessment'));
		}

		$existing_order_id = $this->find_existing_inner_dimensions_order_id($submission_id);
		if ($existing_order_id > 0) {
			$order = wc_get_order($existing_order_id);
			$existing_product_id = $order ? (int) $order->get_meta('_ca_full_results_product_id') : 0;
			if ($order && $order->needs_payment() && $existing_product_id > 0) {
				$this->send_success(
					'ca_prepare_inner_dimensions_checkout',
					array(
						'order_id' => $order->get_id(),
						'checkout_url' => $order->get_checkout_payment_url(),
					),
					'Existing unpaid order reused.',
					array('submission_id' => $submission_id, 'order_id' => $order->get_id())
				);
			}
		}

		$price = (float) apply_filters('ca_inner_dimensions_full_results_price', 499.00, $submission_id);
		if ($price <= 0) {
			$this->send_error('ca_prepare_inner_dimensions_checkout', __('The full results price is not configured correctly.', 'rtr-custom-assessment'));
		}

		$pdf_url = $this->generate_inner_dimensions_pdf_file($submission_id, $submission);
		if (!$pdf_url) {
			$this->send_error('ca_prepare_inner_dimensions_checkout', __('Could not generate your PDF results. Please try again.', 'rtr-custom-assessment'));
		}

		$product_id = $this->upsert_inner_dimensions_product($submission, $submission_id, $price, $pdf_url);
		if ($product_id <= 0) {
			$this->send_error('ca_prepare_inner_dimensions_checkout', __('Could not prepare your downloadable product. Please try again.', 'rtr-custom-assessment'));
		}

		$order = wc_create_order();
		if (!$order) {
			$this->send_error('ca_prepare_inner_dimensions_checkout', __('Could not create an order. Please try again.', 'rtr-custom-assessment'));
		}

		$product = wc_get_product($product_id);
		if (!$product) {
			$this->send_error('ca_prepare_inner_dimensions_checkout', __('Could not load the generated product for checkout.', 'rtr-custom-assessment'));
		}
		$order->add_product($product, 1);

		$order->set_billing_first_name((string) $submission->first_name);
		$order->set_billing_last_name((string) $submission->last_name);
		$order->set_billing_email((string) $submission->email);
		$order->set_billing_phone((string) $submission->phone);

		$order->update_meta_data('_ca_submission_id', (int) $submission_id);
		$order->update_meta_data('_ca_assessment_type', $assessment_type);
		$order->update_meta_data('_ca_full_results_unlock', 'yes');
		$order->update_meta_data('_ca_full_results_product_id', (int) $product_id);
		$order->calculate_totals(true);
		$order->save();

		$this->send_success(
			'ca_prepare_inner_dimensions_checkout',
			array(
				'order_id' => $order->get_id(),
				'checkout_url' => $order->get_checkout_payment_url(),
			),
			'Order created.',
			array('submission_id' => $submission_id, 'order_id' => $order->get_id())
		);
	}

	/**
	 * Find latest unpaid order for this submission.
	 *
	 * @param int $submission_id Submission ID.
	 * @return int
	 */
	private function find_existing_inner_dimensions_order_id($submission_id)
	{
		if (!function_exists('wc_get_orders')) {
			return 0;
		}

		$orders = wc_get_orders(array(
			'limit' => 1,
			'orderby' => 'date',
			'order' => 'DESC',
			'status' => array('pending', 'failed'),
			'meta_key' => '_ca_submission_id',
			'meta_value' => (int) $submission_id,
			'return' => 'ids',
		));

		if (empty($orders)) {
			return 0;
		}

		return (int) $orders[0];
	}

	/**
	 * Create/update hidden downloadable Woo product for this submission.
	 *
	 * @param object $submission
	 * @param int    $submission_id
	 * @param float  $price
	 * @param string $pdf_url
	 * @return int
	 */
	private function upsert_inner_dimensions_product($submission, $submission_id, $price, $pdf_url)
	{
		$product_id = $this->find_existing_inner_dimensions_product_id($submission_id);
		$product = $product_id > 0 ? wc_get_product($product_id) : new WC_Product_Simple();
		if (!$product) {
			$product = new WC_Product_Simple();
		}

		$product->set_name(
			sprintf(
				/* translators: 1: submission id, 2: first name, 3: last name */
				__('NAC Full Results PDF #%1$d - %2$s %3$s', 'rtr-custom-assessment'),
				(int) $submission_id,
				(string) $submission->first_name,
				(string) $submission->last_name
			)
		);
		$product->set_status('publish');
		$product->set_catalog_visibility('hidden');
		$product->set_virtual(true);
		$product->set_downloadable(true);
		$product->set_regular_price(wc_format_decimal($price, 2));
		$product->set_sold_individually(true);

		$download = new WC_Product_Download();
		$download->set_id('ca_pdf_' . (int) $submission_id);
		$download->set_name(__('Full Results PDF', 'rtr-custom-assessment'));
		$download->set_file($pdf_url);
		$product->set_downloads(array($download));
		$product->set_download_limit(-1);
		$product->set_download_expiry(-1);

		$product_id = $product->save();
		if ($product_id > 0) {
			update_post_meta($product_id, '_ca_submission_id', (int) $submission_id);
			update_post_meta($product_id, '_ca_assessment_type', CA_Assessment_Types::INNER_DIMENSIONS);
		}

		return (int) $product_id;
	}

	/**
	 * Find hidden product generated for submission.
	 *
	 * @param int $submission_id
	 * @return int
	 */
	private function find_existing_inner_dimensions_product_id($submission_id)
	{
		$ids = get_posts(array(
			'post_type' => 'product',
			'post_status' => array('publish', 'private', 'draft'),
			'posts_per_page' => 1,
			'fields' => 'ids',
			'meta_key' => '_ca_submission_id',
			'meta_value' => (int) $submission_id,
		));

		if (empty($ids)) {
			return 0;
		}
		return (int) $ids[0];
	}

	/**
	 * Generate a PDF file in uploads for this submission.
	 *
	 * @param int    $submission_id
	 * @param object $submission
	 * @return string|false Public URL to generated PDF.
	 */
	private function generate_inner_dimensions_pdf_file($submission_id, $submission)
	{
		require_once CA_PLUGIN_DIR . 'includes/class-ca-pdf.php';

		$pdf = new Rtr_Custom_Assessment_Pdf();
		$html = $this->build_submission_pdf_html($submission_id, $submission);
		$upload = wp_upload_dir();
		if (!empty($upload['error'])) {
			return false;
		}

		$dir_path = trailingslashit($upload['basedir']) . 'ca-results';
		$dir_url = trailingslashit($upload['baseurl']) . 'ca-results';
		$timestamp = gmdate('YmdHis');
		$pdf_filename = 'nac-results-' . (int) $submission_id . '-' . $timestamp . '.pdf';
		$pdf_path = trailingslashit($dir_path) . $pdf_filename;

		if (!is_dir($dir_path)) {
			wp_mkdir_p($dir_path);
		}

		if ($pdf->save_pdf($html, $pdf_path)) {
			return trailingslashit($dir_url) . $pdf_filename;
		}

		// Fallback for environments without a PDF library: provide a downloadable HTML report.
		$html_filename = 'nac-results-' . (int) $submission_id . '-' . $timestamp . '.html';
		$html_path = trailingslashit($dir_path) . $html_filename;
		$html_payload = "<!doctype html>\n" . $html;
		if (false === file_put_contents($html_path, $html_payload)) {
			return false;
		}

		return trailingslashit($dir_url) . $html_filename;
	}

	/**
	 * Build submission report HTML used for PDF generation.
	 *
	 * @param int    $submission_id
	 * @param object $submission
	 * @return string
	 */
	private function build_submission_pdf_html($submission_id, $submission)
	{
		$answers = CA_Database::get_answers($submission_id);
		$cat_scores = CA_Database::get_category_scores($submission_id);
		$sub_type = CA_Assessment_Types::from_submission($submission);
		$scale_max = CA_Assessment_Types::get_scale_max($sub_type);
		$flat_q = CA_Assessment_Registry::get_flat($sub_type);
		$total_q = CA_Assessment_Registry::get_total_count($sub_type);

		$html = '<html><head><meta charset="UTF-8"><style>
			body{font-family:Arial,sans-serif;margin:20px;color:#222;}
			h1{font-size:24px;margin-bottom:10px;}
			h2{font-size:18px;margin:20px 0 8px;}
			table{width:100%;border-collapse:collapse;margin-bottom:16px;}
			th,td{border:1px solid #ddd;padding:8px;vertical-align:top;}
			th{background:#f6f6f6;}
		</style></head><body>';

		$html .= '<h1>Natural Attributes Cataloging - Full Results</h1>';
		$html .= '<p><strong>Name:</strong> ' . esc_html($submission->first_name . ' ' . $submission->last_name) . '<br>';
		$html .= '<strong>Email:</strong> ' . esc_html($submission->email) . '<br>';
		$html .= '<strong>Total Score:</strong> ' . esc_html($submission->total_score . ' / ' . ($total_q * $scale_max)) . '<br>';
		$html .= '<strong>Average Score:</strong> ' . esc_html(number_format((float) $submission->average_score, 2)) . '</p>';

		$html .= '<h2>Category Scores</h2><table><thead><tr><th>Category</th><th>Subtotal</th><th>Average</th><th>Summary</th></tr></thead><tbody>';
		foreach ($cat_scores as $cat) {
			$html .= '<tr><td>' . esc_html($cat->category_name) . '</td><td>' . esc_html($cat->subtotal) . '</td><td>' . esc_html(number_format((float) $cat->average, 2)) . '</td><td>' . esc_html(CA_Scoring::get_category_summary($cat->category_name, (float) $cat->average, $sub_type)) . '</td></tr>';
		}
		$html .= '</tbody></table>';

		$html .= '<h2>Question Responses</h2><table><thead><tr><th>Question</th><th>Response</th></tr></thead><tbody>';
		foreach ($flat_q as $q) {
			$idx = isset($q['index']) ? (int) $q['index'] : 0;
			$answer = isset($answers[$idx]) ? (int) $answers[$idx] : 0;
			if (CA_Assessment_Types::INNER_DIMENSIONS === $sub_type) {
				$answer_text = (1 === $answer) ? __('Yes', 'rtr-custom-assessment') : ((2 === $answer) ? __('No', 'rtr-custom-assessment') : __('No answer', 'rtr-custom-assessment'));
			} else {
				$answer_text = $answer > 0 ? (string) $answer : __('No answer', 'rtr-custom-assessment');
			}
			$html .= '<tr><td>' . esc_html($q['text']) . '</td><td>' . esc_html($answer_text) . '</td></tr>';
		}
		$html .= '</tbody></table></body></html>';

		return $html;
	}

	/**
	 * Send NAC full results email once payment is confirmed.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function maybe_send_inner_dimensions_results_after_payment($order_id)
	{
		if (!$this->is_woocommerce_ready()) {
			return;
		}

		$order = wc_get_order((int) $order_id);
		if (!$order) {
			return;
		}

		$submission_id = (int) $order->get_meta('_ca_submission_id');
		$assessment_type = (string) $order->get_meta('_ca_assessment_type');
		$already_sent = (string) $order->get_meta('_ca_full_results_email_sent');

		if (
			$submission_id <= 0
			|| CA_Assessment_Types::INNER_DIMENSIONS !== $assessment_type
			|| 'yes' === $already_sent
		) {
			return;
		}

		$sent = CA_Mailer::send_results_email($submission_id);
		if ($sent) {
			$order->update_meta_data('_ca_full_results_email_sent', 'yes');
			$order->save();
		}
	}
}


