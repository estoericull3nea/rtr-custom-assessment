<?php
/**
 * AJAX handler — registers all wp_ajax / wp_ajax_nopriv hooks.
 */

if (!defined('ABSPATH')) {
	exit;
}

class CA_Ajax
{
	private const FULL_RESULTS_TEMPLATE_VERSION = 'v7';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self|null Set after the plugin boots {@see custom-assessment.php}.
	 */
	public static function get_instance()
	{
		return self::$instance;
	}

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
		self::$instance = $this;

		$actions = array(
			'ca_save_user_info',
			'ca_save_answer',
			'ca_get_question',
			'ca_get_progress',
			'ca_find_in_progress_by_email',
			'ca_submit_assessment',
			'ca_get_results_preview',
			'ca_prepare_inner_dimensions_checkout',
			'ca_prepare_paid_full_results_checkout',
		);

		foreach ($actions as $action) {
			add_action('wp_ajax_' . $action, array($this, $action));
			add_action('wp_ajax_nopriv_' . $action, array($this, $action));
		}

		add_action('woocommerce_before_thankyou', array($this, 'render_inner_dimensions_download_on_thankyou'), 20);
		add_action('woocommerce_thankyou', array($this, 'render_inner_dimensions_download_on_thankyou'), 30);
		add_action('woocommerce_order_details_after_order_table', array($this, 'render_inner_dimensions_download_after_order_table'), 20);
		add_action('woocommerce_checkout_create_order', array($this, 'attach_inner_dimensions_meta_to_checkout_order'), 20, 2);
		add_filter('woocommerce_checkout_get_value', array($this, 'checkout_prefill_billing_from_pay_order'), 20, 2);
		add_filter('woocommerce_payment_complete_order_status', array($this, 'inner_dimensions_payment_complete_order_status'), 10, 3);
		add_action('woocommerce_payment_complete', array($this, 'mark_inner_dimensions_product_out_of_stock_on_payment'), 10, 1);
		add_action('woocommerce_payment_complete', array($this, 'maybe_send_customer_paid_pdf_email'), 15, 1);
		add_action('template_redirect', array($this, 'maybe_nac_order_pay_404_or_expired'), 5);
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

		$payload = CA_Assessment_Registry::get_question_display_payload($assessment_type, $index);

		if (!$payload) {
			$this->send_error('ca_get_question', __('Question not found.', 'rtr-custom-assessment'), array('question_index' => $index));
		}

		$scale_max = isset($payload['scale_max']) ? (int) $payload['scale_max'] : CA_Assessment_Types::get_scale_max($assessment_type);

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

		// Send results email as soon as assessment is completed (all assessment types).
		CA_Mailer::send_results_email($submission_id);

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
	 * Price for downloadable full-results PDF (per assessment type).
	 *
	 * @param int    $submission_id Submission ID.
	 * @param string $assessment_type Normalized type.
	 * @return float
	 */
	private function get_paid_full_results_price($submission_id, $assessment_type)
	{
		$submission_id = (int) $submission_id;
		$t = CA_Assessment_Types::normalize($assessment_type);
		if (CA_Assessment_Types::INNER_DIMENSIONS === $t) {
			return (float) apply_filters('ca_inner_dimensions_full_results_price', 9.99, $submission_id);
		}
		if (CA_Assessment_Types::SOCIAL_FLUENCY === $t) {
			return (float) apply_filters('ca_social_fluency_full_results_price', 9.99, $submission_id);
		}
		return 0;
	}

	/**
	 * Create/reuse WooCommerce order for Natural Attributes Cataloging payment (backward compatible alias).
	 */
	public function ca_prepare_inner_dimensions_checkout()
	{
		$this->run_ca_prepare_paid_full_results_checkout('ca_prepare_inner_dimensions_checkout', CA_Assessment_Types::INNER_DIMENSIONS);
	}

	/**
	 * Create/reuse WooCommerce order for Social Fluency or Natural Attributes paid full-results flow.
	 */
	public function ca_prepare_paid_full_results_checkout()
	{
		$this->run_ca_prepare_paid_full_results_checkout('ca_prepare_paid_full_results_checkout', $this->get_assessment_type_from_request());
	}

	/**
	 * Shared paid checkout processor for assessments that unlock PDF after WooCommerce payment.
	 *
	 * @param string $action_key AJAX action label for logs / errors.
	 * @param string $assessment_type Normalized assessment type (must match submission).
	 */
	private function run_ca_prepare_paid_full_results_checkout($action_key, $assessment_type)
	{
		try {
			$this->verify_nonce($action_key);

			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified via $this->verify_nonce().
			$submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			if (!$submission_id) {
				$this->send_error($action_key, __('Invalid session.', 'rtr-custom-assessment'));
			}

			$want = CA_Assessment_Types::normalize($assessment_type);
			if (!CA_Assessment_Types::requires_paid_full_results($want)) {
				$this->send_error(
					$action_key,
					__('This checkout is only available for assessments that use paid full results.', 'rtr-custom-assessment')
				);
			}

			if (!$this->is_woocommerce_ready()) {
				$this->send_error($action_key, __('WooCommerce is required for checkout, but it is not active.', 'rtr-custom-assessment'));
			}

			$submission = $this->require_submission_for_type($submission_id, $want);
			if ('completed' !== $submission->status) {
				$this->send_error($action_key, __('Please complete all questions before proceeding to checkout.', 'rtr-custom-assessment'));
			}

			$price = $this->get_paid_full_results_price($submission_id, $want);
			if ($price <= 0) {
				$this->send_error($action_key, __('The full results price is not configured correctly.', 'rtr-custom-assessment'));
			}

			$results_file_path = $this->generate_paid_full_results_pdf_file($submission_id, $submission);
			if (!$results_file_path) {
				$this->send_error($action_key, __('Could not generate your results file. Please try again.', 'rtr-custom-assessment'));
			}

			$product_id = $this->upsert_paid_full_results_product($submission, $submission_id, $price, $results_file_path, $want);
			if ($product_id <= 0) {
				$this->send_error($action_key, __('Could not prepare your downloadable product. Please try again.', 'rtr-custom-assessment'));
			}

			$this->set_inner_dimensions_checkout_prefill_session($submission);

			$checkout_url = $this->build_paid_full_results_order_pay_checkout_url($submission, $submission_id, $product_id, $want);
			if ('' === $checkout_url) {
				$this->send_error($action_key, __('Could not build a checkout link. Please try again.', 'rtr-custom-assessment'));
			}
			$this->send_success(
				$action_key,
				array(
					'checkout_url' => $checkout_url,
					'product_id' => (int) $product_id,
				),
				'Checkout cart prepared.',
				array('submission_id' => $submission_id, 'product_id' => (int) $product_id)
			);
		} catch (\Throwable $e) {
			$error_message = (string) $e->getMessage();
			if ('' === $error_message) {
				$error_message = 'Unknown error';
			}
			CA_Logger::log(
				$action_key,
				'error',
				'Unhandled checkout preparation error.',
				array(
					'error' => $error_message,
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				)
			);
			$this->send_error(
				$action_key,
				sprintf(
					/* translators: %s: backend error message */
					__('We could not start checkout right now. %s', 'rtr-custom-assessment'),
					sanitize_text_field($error_message)
				)
			);
		}
	}

	/**
	 * Build the same order-pay URL as paid checkout (no nonce). For emails and deep links.
	 *
	 * @param int $submission_id Submission ID.
	 * @return string Pay URL or empty string on failure.
	 */
	public function get_paid_full_results_order_pay_url_for_submission($submission_id)
	{
		$submission_id = (int) $submission_id;
		if ($submission_id <= 0 || !$this->is_woocommerce_ready()) {
			return '';
		}

		$submission = CA_Database::get_submission($submission_id);
		if (!$submission || 'completed' !== $submission->status) {
			return '';
		}

		$want = CA_Assessment_Types::from_submission($submission);
		if (!CA_Assessment_Types::requires_paid_full_results($want)) {
			return '';
		}

		$price = $this->get_paid_full_results_price($submission_id, $want);
		if ($price <= 0) {
			return '';
		}

		$results_file_path = $this->generate_paid_full_results_pdf_file($submission_id, $submission);
		if (!$results_file_path) {
			return '';
		}

		$product_id = $this->upsert_paid_full_results_product($submission, $submission_id, $price, $results_file_path, $want);
		if ($product_id <= 0) {
			return '';
		}

		$url = $this->build_paid_full_results_order_pay_checkout_url($submission, $submission_id, $product_id, $want);
		return is_string($url) ? $url : '';
	}

	/**
	 * @deprecated Use {@see get_paid_full_results_order_pay_url_for_submission()}; kept for call sites expecting this name.
	 * @param int $submission_id Submission ID.
	 * @return string
	 */
	public function get_inner_dimensions_order_pay_url_for_submission($submission_id)
	{
		return $this->get_paid_full_results_order_pay_url_for_submission($submission_id);
	}

	/**
	 * Latest unpaid Woo order for submission + assessment (paid-results flow).
	 *
	 * @param int    $submission_id Submission ID.
	 * @param string $assessment_type Normalized type.
	 * @return int
	 */
	private function find_existing_paid_full_results_order_id($submission_id, $assessment_type)
	{
		if (!function_exists('wc_get_orders')) {
			return 0;
		}

		$assessment_type = CA_Assessment_Types::normalize($assessment_type);

		$orders = wc_get_orders(array(
			'limit' => 1,
			'orderby' => 'date',
			'order' => 'DESC',
			'status' => array('pending', 'failed'),
			'meta_query' => array(
				array(
					'key' => '_ca_submission_id',
					'value' => (int) $submission_id,
				),
				array(
					'key' => '_ca_assessment_type',
					'value' => $assessment_type,
				),
			),
			'return' => 'ids',
		));

		if (empty($orders)) {
			return 0;
		}

		return (int) $orders[0];
	}

	/**
	 * Create/update hidden downloadable Woo product for paid full-results flow.
	 *
	 * @param object $submission
	 * @param int    $submission_id
	 * @param float  $price
	 * @param string $results_file_path
	 * @param string $assessment_type Normalized type.
	 * @return int
	 */
	private function upsert_paid_full_results_product($submission, $submission_id, $price, $results_file_path, $assessment_type)
	{
		$assessment_type = CA_Assessment_Types::normalize($assessment_type);
		$product_id = $this->find_existing_paid_full_results_product_id($submission_id, $assessment_type);
		$product = $product_id > 0 ? wc_get_product($product_id) : new WC_Product_Simple();
		if (!$product) {
			$product = new WC_Product_Simple();
		}

		if (CA_Assessment_Types::SOCIAL_FLUENCY === $assessment_type) {
			$name_tpl = __('Social Fluency Full Results #%1$d - %2$s %3$s', 'rtr-custom-assessment');
		} else {
			$name_tpl = __('Natural Attributes Cataloging Full Results #%1$d - %2$s %3$s', 'rtr-custom-assessment');
		}

		$product->set_name(
			sprintf(
				$name_tpl,
				(int) $submission_id,
				(string) $submission->first_name,
				(string) $submission->last_name
			)
		);
		$product->set_status('publish');
		$product->set_catalog_visibility('hidden');
		$product->set_virtual(true);
		$product->set_downloadable(false);
		$product->set_regular_price(wc_format_decimal($price, 2));
		$product->set_sold_individually(true);
		$product->set_downloads(array());

		$product_id = $product->save();
		if ($product_id > 0) {
			update_post_meta($product_id, '_ca_submission_id', (int) $submission_id);
			update_post_meta($product_id, '_ca_assessment_type', $assessment_type);
			update_post_meta($product_id, '_ca_full_results_file_path', (string) $results_file_path);
			update_post_meta($product_id, '_ca_full_results_template_version', self::FULL_RESULTS_TEMPLATE_VERSION);
		}

		return (int) $product_id;
	}

	/**
	 * Find hidden paid-results product for submission + assessment.
	 *
	 * @param int    $submission_id
	 * @param string $assessment_type Normalized type.
	 * @return int
	 */
	private function find_existing_paid_full_results_product_id($submission_id, $assessment_type)
	{
		$assessment_type = CA_Assessment_Types::normalize($assessment_type);

		$ids = get_posts(array(
			'post_type' => 'product',
			'post_status' => array('publish', 'private', 'draft'),
			'posts_per_page' => 1,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => '_ca_submission_id',
					'value' => (int) $submission_id,
				),
				array(
					'key' => '_ca_assessment_type',
					'value' => $assessment_type,
				),
			),
		));

		if (empty($ids)) {
			return 0;
		}
		return (int) $ids[0];
	}

	/**
	 * WooCommerce order payment URL (order-pay) — reliable for guests; avoids hidden product single URLs.
	 *
	 * @param \WC_Order $order Order instance.
	 * @return string
	 */
	private function get_inner_dimensions_order_payment_url($order)
	{
		if (!$order instanceof \WC_Order) {
			return '';
		}
		if (!$order->needs_payment()) {
			return '';
		}
		// false: include pay_for_order=true + key so the full payment form loads (classic + blocks).
		$url = $order->get_checkout_payment_url(false);
		$url = is_string($url) ? trim($url) : '';
		return '' !== $url ? $this->ensure_www_url($url) : '';
	}

	/**
	 * Clear cart before sending the customer to order payment (avoids stray line items).
	 */
	private function clear_wc_cart_for_guest_checkout()
	{
		if (!function_exists('WC')) {
			return;
		}
		$wc = WC();
		if (!$wc || !isset($wc->cart) || !is_object($wc->cart)) {
			return;
		}
		$wc->cart->empty_cart();
	}

	/**
	 * Create or refresh a pending order and return the WooCommerce checkout payment URL.
	 *
	 * Does not rely on cart session — works for logged-out visitors (order key is in the URL).
	 *
	 * @param object $submission    Submission row.
	 * @param int    $submission_id Submission ID.
	 * @param int    $product_id    Hidden product ID.
	 * @param string $assessment_type Normalized assessment type.
	 * @return string Checkout order-pay URL or empty string.
	 */
	private function build_paid_full_results_order_pay_checkout_url($submission, $submission_id, $product_id, $assessment_type)
	{
		$submission_id = (int) $submission_id;
		$product_id = (int) $product_id;
		$assessment_type = CA_Assessment_Types::normalize($assessment_type);

		if (!$submission || $submission_id <= 0 || $product_id <= 0) {
			return '';
		}

		$product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
		if (!$product || !is_object($product)) {
			return '';
		}

		$customer_id = is_user_logged_in() ? (int) get_current_user_id() : 0;

		$order = null;
		$existing_id = $this->find_existing_paid_full_results_order_id($submission_id, $assessment_type);
		if ($existing_id > 0) {
			$candidate = wc_get_order($existing_id);
			if ($candidate instanceof \WC_Order && $candidate->needs_payment()) {
				$order = $candidate;
				foreach ($order->get_items('line_item') as $item_id => $item) {
					$order->remove_item($item_id);
				}
			}
		}

		if (!$order instanceof \WC_Order) {
			$created_via = CA_Assessment_Types::SOCIAL_FLUENCY === $assessment_type ? 'ca_social_fluency_full_results' : 'ca_inner_dimensions_full_results';
			$created = wc_create_order(
				array(
					'status' => 'pending',
					'customer_id' => $customer_id,
					'created_via' => $created_via,
				)
			);
			if (is_wp_error($created) || !($created instanceof \WC_Order)) {
				return '';
			}
			$order = $created;
		} elseif ($customer_id > 0 && (int) $order->get_customer_id() !== $customer_id) {
			$order->set_customer_id($customer_id);
		}

		$order->add_product($product, 1);
		$this->apply_submission_billing_to_order($order, $submission);

		$file_path = (string) get_post_meta($product_id, '_ca_full_results_file_path', true);
		$template_version = (string) get_post_meta($product_id, '_ca_full_results_template_version', true);

		$order->update_meta_data('_ca_submission_id', $submission_id);
		$order->update_meta_data('_ca_assessment_type', $assessment_type);
		$order->update_meta_data('_ca_full_results_unlock', 'yes');
		$order->update_meta_data('_ca_full_results_product_id', $product_id);
		if ('' !== $file_path) {
			$order->update_meta_data('_ca_full_results_file_path', $file_path);
		}
		if ('' !== $template_version) {
			$order->update_meta_data('_ca_full_results_template_version', $template_version);
		}

		$expiry_seconds = (int) apply_filters(
			'ca_paid_full_results_order_pay_link_expiry_seconds',
			apply_filters('ca_nac_order_pay_link_expiry_seconds', 2 * HOUR_IN_SECONDS),
			$assessment_type,
			$submission_id
		);
		$expiry_seconds = max(60, $expiry_seconds);
		$order->update_meta_data('_ca_order_pay_expires_at', time() + $expiry_seconds);

		$order->calculate_totals();
		$order->save();

		$this->clear_wc_cart_for_guest_checkout();

		return $this->get_inner_dimensions_order_payment_url($order);
	}

	/**
	 * On order-pay: 404 if the NAC link expired (unpaid past window) or the order no longer needs payment (e.g. completed).
	 *
	 * Requires a valid `key` query arg matching the order (same as WooCommerce’s pay flow).
	 *
	 * @return void
	 */
	public function maybe_nac_order_pay_404_or_expired()
	{
		if (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('order-pay')) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WC front-end order key in URL.
		if (empty($_GET['key'])) {
			return;
		}

		global $wp;
		$order_id = isset($wp->query_vars['order-pay']) ? absint($wp->query_vars['order-pay']) : 0;
		if ($order_id <= 0 && function_exists('get_query_var')) {
			$order_id = absint(get_query_var('order-pay'));
		}
		if ($order_id <= 0) {
			return;
		}

		$order = wc_get_order($order_id);
		if (!$order instanceof \WC_Order) {
			return;
		}

		if (!CA_Assessment_Types::requires_paid_full_results((string) $order->get_meta('_ca_assessment_type'))) {
			return;
		}

		$key = isset($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : '';
		if ('' === $key || !hash_equals($order->get_order_key(), $key)) {
			return;
		}

		if (!$order->needs_payment()) {
			$this->nac_order_pay_send_404();
		}

		$expiry_seconds = (int) apply_filters(
			'ca_paid_full_results_order_pay_link_expiry_seconds',
			apply_filters('ca_nac_order_pay_link_expiry_seconds', 2 * HOUR_IN_SECONDS),
			(string) $order->get_meta('_ca_assessment_type'),
			(int) $order->get_meta('_ca_submission_id')
		);
		$expiry_seconds = max(60, $expiry_seconds);

		$expires_at = (int) $order->get_meta('_ca_order_pay_expires_at');
		if ($expires_at <= 0 && $order->get_date_created()) {
			$expires_at = $order->get_date_created()->getTimestamp() + $expiry_seconds;
		}

		if ($expires_at > 0 && time() > $expires_at) {
			$this->nac_order_pay_send_404();
		}
	}

	/**
	 * Render the theme 404 template and halt.
	 *
	 * @return void
	 */
	private function nac_order_pay_send_404()
	{
		global $wp_query;
		$wp_query->set_404();
		status_header(404);
		nocache_headers();

		$template = get_query_template('404');
		if ($template) {
			include $template;
		} else {
			wp_die(esc_html__('Not found.', 'rtr-custom-assessment'), esc_html__('Not found.', 'rtr-custom-assessment'), array('response' => 404));
		}
		exit;
	}

	/**
	 * Copy assessment step-1 fields (name, email, phone) onto the order billing address.
	 * Does not set billing_company (job title is not copied to checkout).
	 * Fills required WC billing placeholders using store base location and filterable defaults.
	 *
	 * @param \WC_Order $order      Order instance.
	 * @param object    $submission Row from ca_submissions.
	 * @return void
	 */
	private function apply_submission_billing_to_order($order, $submission)
	{
		if (!$order instanceof \WC_Order || !$submission) {
			return;
		}

		$order->set_billing_first_name((string) $submission->first_name);
		$order->set_billing_last_name((string) $submission->last_name);
		$order->set_billing_email((string) $submission->email);
		$order->set_billing_phone((string) $submission->phone);
		$order->set_billing_company('');

		$country = '';
		$state = '';
		if (function_exists('wc_get_base_location')) {
			$loc = wc_get_base_location();
			$country = isset($loc['country']) ? (string) $loc['country'] : '';
			$state = isset($loc['state']) ? (string) $loc['state'] : '';
		}

		if ('' === $country && function_exists('WC') && WC()->countries) {
			$country = (string) WC()->countries->get_base_country();
			$base_state = WC()->countries->get_base_state();
			$state = null !== $base_state ? (string) $base_state : '';
		}

		if ('' !== $country) {
			$order->set_billing_country($country);
		}
		if ('' !== $state) {
			$order->set_billing_state($state);
		}

		$line1 = (string) apply_filters('ca_paid_full_results_default_billing_address_1', '', $submission, $order);
		if ('' === $line1) {
			$line1 = (string) apply_filters('ca_inner_dimensions_default_billing_address_1', '', $submission, $order);
		}
		if ('' === $line1) {
			$line1 = __('Digital delivery — paid full results', 'rtr-custom-assessment');
		}
		$order->set_billing_address_1($line1);

		$city = (string) apply_filters('ca_inner_dimensions_default_billing_city', '', $submission, $order);
		if ('' === $city) {
			$city = __('Online', 'rtr-custom-assessment');
		}
		$order->set_billing_city($city);

		$postcode = (string) apply_filters('ca_inner_dimensions_default_billing_postcode', '', $submission, $order);
		if ('' === $postcode) {
			$store_postcode = (string) get_option('woocommerce_store_postcode', '');
			if ('' !== $store_postcode) {
				$postcode = $store_postcode;
			} else {
				$postcode = (string) apply_filters('ca_inner_dimensions_default_billing_postcode_fallback', '00000', $submission, $order);
			}
		}
		$order->set_billing_postcode($postcode);
	}

	/**
	 * On order-pay checkout, default billing fields from the order when the value is still empty.
	 *
	 * @param mixed  $value Checkout default.
	 * @param string $input Field key e.g. billing_first_name.
	 * @return mixed
	 */
	public function checkout_prefill_billing_from_pay_order($value, $input)
	{
		if (!is_string($input) || 0 !== strpos($input, 'billing_')) {
			return $value;
		}
		if (null !== $value && false !== $value && '' !== (string) $value) {
			return $value;
		}

		// First, prefill from session when we are on checkout with NAC cart flow.
		if (function_exists('is_checkout') && is_checkout()) {
			$session_value = $this->get_inner_dimensions_checkout_prefill_value($input);
			if ('' !== $session_value) {
				return $session_value;
			}
		}

		// Fallback for order-pay flow (legacy path).
		if (!function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('order-pay')) {
			return $value;
		}

		global $wp;
		$order_id = 0;
		if (isset($wp->query_vars['order-pay'])) {
			$order_id = absint($wp->query_vars['order-pay']);
		}
		if ($order_id <= 0 && function_exists('get_query_var')) {
			$order_id = absint(get_query_var('order-pay'));
		}
		if ($order_id <= 0) {
			return $value;
		}

		$order = wc_get_order($order_id);
		if (!$order instanceof \WC_Order || !$order->needs_payment()) {
			return $value;
		}

		if ((int) $order->get_meta('_ca_submission_id', true) <= 0) {
			return $value;
		}

		$suffix = substr($input, strlen('billing_'));
		$getter = 'get_billing_' . $suffix;
		if (!is_callable(array($order, $getter))) {
			return $value;
		}

		$from_order = call_user_func(array($order, $getter));
		if (is_string($from_order) && '' !== $from_order) {
			return $from_order;
		}
		if (is_numeric($from_order)) {
			return (string) $from_order;
		}

		return $value;
	}

	/**
	 * Store billing fields in Woo session for NAC checkout prefill.
	 *
	 * @param object $submission Submission row.
	 * @return void
	 */
	private function set_inner_dimensions_checkout_prefill_session($submission)
	{
		if (!$submission || !function_exists('WC')) {
			return;
		}
		$wc = WC();
		if (!$wc || !isset($wc->session) || !is_object($wc->session)) {
			return;
		}

		$prefill = array(
			'billing_first_name' => isset($submission->first_name) ? (string) $submission->first_name : '',
			'billing_last_name' => isset($submission->last_name) ? (string) $submission->last_name : '',
			'billing_email' => isset($submission->email) ? (string) $submission->email : '',
			'billing_phone' => isset($submission->phone) ? (string) $submission->phone : '',
		);
		$wc->session->set('ca_inner_dimensions_checkout_prefill', $prefill);
	}

	/**
	 * Read a single billing field from NAC checkout prefill session payload.
	 *
	 * @param string $input Billing field key.
	 * @return string
	 */
	private function get_inner_dimensions_checkout_prefill_value($input)
	{
		if (!function_exists('WC')) {
			return '';
		}
		$wc = WC();
		if (!$wc || !isset($wc->session) || !is_object($wc->session)) {
			return '';
		}
		$prefill = $wc->session->get('ca_inner_dimensions_checkout_prefill');
		if (!is_array($prefill) || !isset($prefill[$input])) {
			return '';
		}
		return trim((string) $prefill[$input]);
	}

	/**
	 * Generate a PDF results file in uploads for this submission.
	 *
	 * @param int    $submission_id
	 * @param object $submission
	 * @return string|false Absolute server path to generated file.
	 */
	private function generate_paid_full_results_pdf_file($submission_id, $submission)
	{
		$data   = $this->build_submission_pdf_data($submission_id, $submission);
		$upload = wp_upload_dir();
		if (!empty($upload['error'])) {
			return false;
		}

		$sub_type = CA_Assessment_Types::from_submission($submission);
		$prefix = CA_Assessment_Types::SOCIAL_FLUENCY === $sub_type ? 'sf-results-' : 'nac-results-';

		$dir_path  = trailingslashit($upload['basedir']) . 'ca-results';
		$timestamp = gmdate('YmdHis');
		$file_name = $prefix . (int) $submission_id . '-' . $timestamp . '.pdf';
		$file_path = trailingslashit($dir_path) . $file_name;

		if (!is_dir($dir_path)) {
			wp_mkdir_p($dir_path);
		}

		$pdf = new Rtr_Custom_Assessment_Pdf();
		if (!$pdf->save_pdf_from_data($data, $file_path)) {
			return false;
		}
		return $file_path;
	}

	/**
	 * Build structured data array for the graphical PDF report.
	 *
	 * @param int    $submission_id
	 * @param object $submission
	 * @return array
	 */
	private function build_submission_pdf_data($submission_id, $submission)
	{
		$answers    = CA_Database::get_answers($submission_id);
		$cat_scores = CA_Database::get_category_scores($submission_id);
		$sub_type   = CA_Assessment_Types::from_submission($submission);
		$scale_max  = CA_Assessment_Types::get_scale_max($sub_type);
		$flat_q     = CA_Assessment_Registry::get_flat($sub_type);
		$total_q    = CA_Assessment_Registry::get_total_count($sub_type);

		$overall_percent = (int) round(((float) $submission->average_score / max(1, (int) $scale_max)) * 100);
		$overall_percent = max(0, min(100, $overall_percent));

		$categories = array();
		foreach ($cat_scores as $cat) {
			$percent      = (int) round(((float) $cat->average / max(1, (int) $scale_max)) * 100);
			$percent      = max(0, min(100, $percent));
			$categories[] = array(
				'name'    => (string) $cat->category_name,
				'percent' => $percent,
				'level'   => $this->get_results_level_label($percent),
				'summary' => CA_Scoring::get_category_summary($cat->category_name, (float) $cat->average, $sub_type),
			);
		}

		$responses = array();
		foreach ($flat_q as $q) {
			$idx    = isset($q['index']) ? (int) $q['index'] : 0;
			$answer = isset($answers[$idx]) ? (int) $answers[$idx] : 0;
			if (CA_Assessment_Types::INNER_DIMENSIONS === $sub_type) {
				$answer_text = (1 === $answer) ? 'Yes' : ((2 === $answer) ? 'No' : '-');
			} else {
				$answer_text = $answer > 0 ? (string) $answer : '-';
			}
			$responses[] = array(
				'question' => isset($q['text']) ? (string) $q['text'] : '',
				'answer'   => $answer_text,
			);
		}

		$logo_path = '/wp-content/uploads/2026/02/cropped-cropped-Logo_Red@2x-1-e1771817601241-1.png';
		$logo_url  = home_url($logo_path);

		return array(
			'name'            => trim((string) $submission->first_name . ' ' . (string) $submission->last_name),
			'email'           => (string) $submission->email,
			'total_score'     => $submission->total_score . ' / ' . ($total_q * $scale_max),
			'overall_percent' => $overall_percent,
			'categories'      => $categories,
			'responses'       => $responses,
			'logo_url'        => $logo_url,
			'assessment_type' => $sub_type,
		);
	}

	/**
	 * Human-friendly score level for result badges.
	 *
	 * @param int $percent Score as percentage.
	 * @return string
	 */
	private function get_results_level_label($percent)
	{
		$percent = (int) $percent;
		if ($percent >= 80) {
			return 'high';
		}
		if ($percent >= 50) {
			return 'medium';
		}
		return 'low';
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
			|| !CA_Assessment_Types::requires_paid_full_results($assessment_type)
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

	/**
	 * Render download CTA on WooCommerce thank-you page for NAC full results orders.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function render_inner_dimensions_download_on_thankyou($order_id)
	{
		static $rendered_order_ids = array();
		$order_id = (int) $order_id;
		if ($order_id > 0 && in_array($order_id, $rendered_order_ids, true)) {
			return;
		}

		if (!$this->is_woocommerce_ready()) {
			return;
		}

		$order = wc_get_order($order_id);
		if (!$order) {
			return;
		}
		if (!$order->is_paid()) {
			return;
		}

		$assessment_type = (string) $order->get_meta('_ca_assessment_type');
		if (!CA_Assessment_Types::requires_paid_full_results($assessment_type)) {
			return;
		}

		$download_url = $this->get_inner_dimensions_pdf_download_url_for_order($order);
		if ('' === $download_url) {
			return;
		}
		if ($order_id > 0) {
			$rendered_order_ids[] = $order_id;
		}
		?>
		<section class="woocommerce-order ca-order-download" style="margin-top:24px;">
			<h2><?php esc_html_e('Your Full Results', 'rtr-custom-assessment'); ?></h2>
			<p><?php esc_html_e('Your payment was received. Download your full results below.', 'rtr-custom-assessment'); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url($download_url); ?>" download>
					<?php esc_html_e('Download PDF', 'rtr-custom-assessment'); ?>
				</a>
			</p>
		</section>
		<?php
	}

	/**
	 * After successful payment, email the customer the same PDF download URL as the thank-you page.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function maybe_send_customer_paid_pdf_email($order_id)
	{
		if (!$this->is_woocommerce_ready()) {
			return;
		}

		$order = wc_get_order((int) $order_id);
		if (!$order instanceof \WC_Order) {
			return;
		}

		if (!CA_Assessment_Types::requires_paid_full_results((string) $order->get_meta('_ca_assessment_type'))) {
			return;
		}

		if (!$order->is_paid()) {
			return;
		}

		if ('yes' === (string) $order->get_meta('_ca_paid_pdf_email_sent')) {
			return;
		}

		$download_url = $this->get_inner_dimensions_pdf_download_url_for_order($order);
		if ('' === $download_url) {
			return;
		}

		$sent = CA_Mailer::send_customer_paid_pdf_download_email($order, $download_url);
		if ($sent) {
			$order->update_meta_data('_ca_paid_pdf_email_sent', 'yes');
			$order->save();
		}
	}

	/**
	 * Public PDF URL for NAC orders (matches thank-you download link after ensure + normalize).
	 *
	 * @param \WC_Order $order Order instance.
	 * @return string
	 */
	public function get_inner_dimensions_pdf_download_url_for_order($order)
	{
		if (!$this->is_woocommerce_ready()) {
			return '';
		}
		if (!$order instanceof \WC_Order) {
			return '';
		}
		if (!CA_Assessment_Types::requires_paid_full_results((string) $order->get_meta('_ca_assessment_type'))) {
			return '';
		}
		if (!$order->is_paid()) {
			return '';
		}

		$this->ensure_inner_dimensions_order_results_file($order);

		$download_url = (string) $order->get_meta('_ca_full_results_file_path');
		if ('' === $download_url) {
			$product_id = (int) $order->get_meta('_ca_full_results_product_id');
			if ($product_id > 0) {
				$download_url = (string) get_post_meta($product_id, '_ca_full_results_file_path', true);
			}
		}

		return $this->normalize_results_download_url($download_url);
	}

	/**
	 * Render download CTA on order details blocks (order-received / view-order).
	 *
	 * @param WC_Order|int $order Order instance or ID (hook dependent).
	 * @return void
	 */
	public function render_inner_dimensions_download_after_order_table($order)
	{
		if (is_object($order) && method_exists($order, 'get_id')) {
			$this->render_inner_dimensions_download_on_thankyou((int) $order->get_id());
			return;
		}
		$this->render_inner_dimensions_download_on_thankyou((int) $order);
	}

	/**
	 * Attach NAC metadata to orders created from checkout/cart flow.
	 *
	 * @param WC_Order $order
	 * @param array    $data
	 * @return void
	 */
	public function attach_inner_dimensions_meta_to_checkout_order($order, $data)
	{
		if (!$order || !is_object($order) || !method_exists($order, 'get_items')) {
			return;
		}

		$existing_type = (string) $order->get_meta('_ca_assessment_type');
		if ('' !== $existing_type && CA_Assessment_Types::requires_paid_full_results($existing_type)) {
			return;
		}

		foreach ($order->get_items() as $item) {
			if (!is_object($item) || !method_exists($item, 'get_product_id')) {
				continue;
			}
			$product_id = (int) $item->get_product_id();
			if ($product_id <= 0) {
				continue;
			}

			$product_type = (string) get_post_meta($product_id, '_ca_assessment_type', true);
			if (!CA_Assessment_Types::requires_paid_full_results($product_type)) {
				continue;
			}

			$submission_id = (int) get_post_meta($product_id, '_ca_submission_id', true);
			$file_path = (string) get_post_meta($product_id, '_ca_full_results_file_path', true);
			$template_version = (string) get_post_meta($product_id, '_ca_full_results_template_version', true);

			$order->update_meta_data('_ca_submission_id', $submission_id);
			$order->update_meta_data('_ca_assessment_type', CA_Assessment_Types::normalize($product_type));
			$order->update_meta_data('_ca_full_results_unlock', 'yes');
			$order->update_meta_data('_ca_full_results_product_id', $product_id);
			if ('' !== $file_path) {
				$order->update_meta_data('_ca_full_results_file_path', $file_path);
			}
			if ('' !== $template_version) {
				$order->update_meta_data('_ca_full_results_template_version', $template_version);
			}
			break;
		}
	}

	/**
	 * After successful payment, mark Natural Attributes Cataloging orders completed (not processing).
	 *
	 * @param string   $status    Status WooCommerce would apply (typically processing or completed).
	 * @param int      $order_id  Order ID.
	 * @param \WC_Order|false $order Order object when available.
	 * @return string
	 */
	public function inner_dimensions_payment_complete_order_status($status, $order_id, $order)
	{
		if (!$order instanceof \WC_Order) {
			$order = wc_get_order((int) $order_id);
		}
		if (!$order instanceof \WC_Order) {
			return $status;
		}

		if (!CA_Assessment_Types::requires_paid_full_results((string) $order->get_meta('_ca_assessment_type'))) {
			return $status;
		}

		return 'completed';
	}

	/**
	 * After successful payment, draft the NAC full-results product and mark it out of stock (one purchase per hidden product).
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function mark_inner_dimensions_product_out_of_stock_on_payment($order_id)
	{
		if (!$this->is_woocommerce_ready()) {
			return;
		}

		$order = wc_get_order((int) $order_id);
		if (!$order instanceof \WC_Order) {
			return;
		}

		if (!CA_Assessment_Types::requires_paid_full_results((string) $order->get_meta('_ca_assessment_type'))) {
			return;
		}

		if ('yes' === (string) $order->get_meta('_ca_nac_product_marked_outofstock')) {
			return;
		}

		$candidate_ids = array();
		$meta_pid = (int) $order->get_meta('_ca_full_results_product_id');
		if ($meta_pid > 0) {
			$candidate_ids[] = $meta_pid;
		}

		foreach ($order->get_items('line_item') as $item) {
			if (!is_object($item) || !method_exists($item, 'get_product_id')) {
				continue;
			}
			$pid = (int) $item->get_product_id();
			if ($pid > 0) {
				$candidate_ids[] = $pid;
			}
		}

		$candidate_ids = array_unique(array_filter($candidate_ids));
		foreach ($candidate_ids as $product_id) {
			$this->set_inner_dimensions_product_out_of_stock((int) $product_id);
		}

		$order->update_meta_data('_ca_nac_product_marked_outofstock', 'yes');
		$order->save();
	}

	/**
	 * After payment: set NAC hidden product to draft and out of stock.
	 *
	 * @param int $product_id Product post ID.
	 * @return void
	 */
	private function set_inner_dimensions_product_out_of_stock($product_id)
	{
		$product_id = (int) $product_id;
		if ($product_id <= 0) {
			return;
		}

		$product = wc_get_product($product_id);
		if (!$product instanceof \WC_Product) {
			return;
		}

		if (!CA_Assessment_Types::requires_paid_full_results((string) $product->get_meta('_ca_assessment_type'))) {
			return;
		}

		$product->set_status('draft');
		$product->set_manage_stock(true);
		$product->set_stock_quantity(0);
		$product->set_backorders('no');
		$product->set_stock_status('outofstock');
		$product->save();
	}

	/**
	 * Ensure order/product points to latest styled PDF file.
	 *
	 * @param WC_Order $order
	 * @return void
	 */
	private function ensure_inner_dimensions_order_results_file($order)
	{
		if (!$order || !is_object($order) || !method_exists($order, 'get_meta')) {
			return;
		}

		$assessment_type = (string) $order->get_meta('_ca_assessment_type');
		if (!CA_Assessment_Types::requires_paid_full_results($assessment_type)) {
			return;
		}

		$current_path = (string) $order->get_meta('_ca_full_results_file_path');
		$current_version = (string) $order->get_meta('_ca_full_results_template_version');
		$needs_new_file = (
			'' === $current_path
			|| !preg_match('/\.pdf$/i', $current_path)
			|| self::FULL_RESULTS_TEMPLATE_VERSION !== $current_version
		);
		if (!$needs_new_file) {
			return;
		}

		$submission_id = (int) $order->get_meta('_ca_submission_id');
		if ($submission_id <= 0) {
			return;
		}

		$submission = CA_Database::get_submission($submission_id);
		if (!$submission) {
			return;
		}

		$new_path = $this->generate_paid_full_results_pdf_file($submission_id, $submission);
		if (!$new_path) {
			return;
		}

		$order->update_meta_data('_ca_full_results_file_path', (string) $new_path);
		$order->update_meta_data('_ca_full_results_template_version', self::FULL_RESULTS_TEMPLATE_VERSION);
		$order->save();

		$product_id = (int) $order->get_meta('_ca_full_results_product_id');
		if ($product_id > 0) {
			update_post_meta($product_id, '_ca_full_results_file_path', (string) $new_path);
			update_post_meta($product_id, '_ca_full_results_template_version', self::FULL_RESULTS_TEMPLATE_VERSION);
		}
	}

	/**
	 * Normalize Woo download file reference to a public URL for frontend rendering.
	 *
	 * @param string $file
	 * @return string
	 */
	private function normalize_results_download_url($file)
	{
		$file = trim((string) $file);
		if ('' === $file) {
			return '';
		}

		// Already URL.
		if (false !== strpos($file, '://')) {
			return $file;
		}

		$upload = wp_upload_dir();
		if (empty($upload['basedir']) || empty($upload['baseurl'])) {
			return '';
		}

		$basedir = wp_normalize_path((string) $upload['basedir']);
		$file_normalized = wp_normalize_path($file);
		if (0 === strpos($file_normalized, $basedir)) {
			$relative = ltrim(substr($file_normalized, strlen($basedir)), '/');
			return trailingslashit((string) $upload['baseurl']) . $relative;
		}

		return '';
	}

	/**
	 * Normalize checkout redirect URLs (do not alter host — forcing "www." breaks many sites and causes 404s).
	 *
	 * @param string $url
	 * @return string
	 */
	private function ensure_www_url($url)
	{
		$url = trim((string) $url);
		return '' === $url ? '' : $url;
	}

}


