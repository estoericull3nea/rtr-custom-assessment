<?php
/**
 * AJAX handler — registers all wp_ajax / wp_ajax_nopriv hooks.
 */

if (!defined('ABSPATH')) {
	exit;
}

class CA_Ajax
{

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
		);

		foreach ($actions as $action) {
			add_action('wp_ajax_' . $action, array($this, $action));
			add_action('wp_ajax_nopriv_' . $action, array($this, $action));
		}
	}

	// -------------------------------------------------------------------------
	// Nonce helper
	// -------------------------------------------------------------------------

	private function verify_nonce()
	{
		if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ca_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed.', 'custom-assessment')));
		}
	}

	// -------------------------------------------------------------------------
	// Action: save user info (Step 1)
	// -------------------------------------------------------------------------

	public function ca_save_user_info()
	{
		$this->verify_nonce();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified via $this->verify_nonce().
		$first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
		$last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
		$email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
		$phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
		$job_title = isset($_POST['job_title']) ? sanitize_text_field(wp_unslash($_POST['job_title'])) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Validate
		$errors = array();
		if (empty($first_name))
			$errors[] = __('First name is required.', 'custom-assessment');
		if (empty($last_name))
			$errors[] = __('Last name is required.', 'custom-assessment');
		if (empty($email) || !is_email($email))
			$errors[] = __('A valid email is required.', 'custom-assessment');
		if (empty($phone))
			$errors[] = __('Phone number is required.', 'custom-assessment');
		if (empty($job_title))
			$errors[] = __('Job title is required.', 'custom-assessment');

		if (!empty($errors)) {
			wp_send_json_error(array('message' => implode(' ', $errors)));
		}

		$submission_id = CA_Database::insert_submission(array(
			'first_name' => $first_name,
			'last_name' => $last_name,
			'email' => $email,
			'phone' => $phone,
			'job_title' => $job_title,
		));

		if (!$submission_id) {
			wp_send_json_error(array('message' => __('Could not save your information. Please try again.', 'custom-assessment')));
		}

		wp_send_json_success(array(
			'submission_id' => $submission_id,
			'message' => __('Information saved.', 'custom-assessment'),
		));
	}

	// -------------------------------------------------------------------------
	// Action: get question by index
	// -------------------------------------------------------------------------

	public function ca_get_question()
	{
		$this->verify_nonce();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified via $this->verify_nonce().
		$index = isset($_POST['question_index']) ? absint($_POST['question_index']) : 0;
		$submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$question = CA_Questions::get_question($index);

		if (!$question) {
			wp_send_json_error(array('message' => __('Question not found.', 'custom-assessment')));
		}

		$saved_answer = $submission_id ? CA_Database::get_answer($submission_id, $index) : null;
		$total = CA_Questions::get_total_count();
		$progress = $total > 0 ? round(($index / $total) * 100) : 0;

		wp_send_json_success(array(
			'question' => $question,
			'saved_answer' => $saved_answer,
			'total' => $total,
			'progress' => $progress,
			'is_last' => ($index === $total - 1),
		));
	}

	// -------------------------------------------------------------------------
	// Action: save answer
	// -------------------------------------------------------------------------

	public function ca_save_answer()
	{
		$this->verify_nonce();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified via $this->verify_nonce().
		$submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
		$question_index = isset($_POST['question_index']) ? absint($_POST['question_index']) : 0;
		$answer = isset($_POST['answer']) ? absint($_POST['answer']) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Validate
		if (!$submission_id) {
			wp_send_json_error(array('message' => __('Invalid session. Please refresh and try again.', 'custom-assessment')));
		}
		if ($answer < 1 || $answer > 5) {
			wp_send_json_error(array('message' => __('Invalid answer. Please select a value between 1 and 5.', 'custom-assessment')));
		}

		CA_Database::save_answer($submission_id, $question_index, $answer);
		CA_Database::set_in_progress($submission_id);

		$total = CA_Questions::get_total_count();
		$next = $question_index + 1;
		$progress = $total > 0 ? round(($next / $total) * 100) : 0;

		wp_send_json_success(array(
			'next_index' => $next,
			'progress' => $progress,
			'is_last' => ($next >= $total),
		));
	}

	// -------------------------------------------------------------------------
	// Action: get progress
	// -------------------------------------------------------------------------

	public function ca_get_progress()
	{
		$this->verify_nonce();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified via $this->verify_nonce().
		$submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if (!$submission_id) {
			wp_send_json_error(array('message' => __('Invalid session.', 'custom-assessment')));
		}

		$submission = CA_Database::get_submission($submission_id);
		if (!$submission) {
			wp_send_json_error(array('message' => __('Submission not found.', 'custom-assessment')));
		}

		$answers = CA_Database::get_answers($submission_id);
		$total = CA_Questions::get_total_count();
		$answered = count($answers);
		$progress = $total > 0 ? round(($answered / $total) * 100) : 0;

		wp_send_json_success(array(
			'answered' => $answered,
			'total' => $total,
			'progress' => $progress,
			'status' => $submission->status,
			'email' => $submission->email,
		));
	}

	// -------------------------------------------------------------------------
	// Action: find in-progress submission by email
	// -------------------------------------------------------------------------

	public function ca_find_in_progress_by_email()
	{
		$this->verify_nonce();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified via $this->verify_nonce().
		$email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if (empty($email) || !is_email($email)) {
			wp_send_json_error(array('message' => __('A valid email is required.', 'custom-assessment')));
		}

		$submission = CA_Database::get_in_progress_submission_by_email($email);

		if (!$submission) {
			wp_send_json_success(array('found' => false));
		}

		$answers = CA_Database::get_answers($submission->id);
		$total = CA_Questions::get_total_count();
		$answered = count($answers);
		$progress = $total > 0 ? round(($answered / $total) * 100) : 0;

		wp_send_json_success(array(
			'found' => true,
			'submission_id' => $submission->id,
			'email' => $submission->email,
			'answered' => $answered,
			'total' => $total,
			'progress' => $progress,
			'status' => $submission->status,
			// Used by the frontend to continue in the correct priority-based order.
			'answers_map' => $answers,
		));
	}

	// -------------------------------------------------------------------------
	// Action: submit assessment (calculate scores)
	// -------------------------------------------------------------------------

	public function ca_submit_assessment()
	{
		$this->verify_nonce();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified via $this->verify_nonce().
		$submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if (!$submission_id) {
			wp_send_json_error(array('message' => __('Invalid session.', 'custom-assessment')));
		}

		$answers = CA_Database::get_answers($submission_id);
		$total_q = CA_Questions::get_total_count();

		if (count($answers) < $total_q) {
			wp_send_json_error(array('message' => __('Please answer all questions before submitting.', 'custom-assessment')));
		}

		$scoring = CA_Scoring::calculate($answers);

		CA_Database::update_submission_scores(
			$submission_id,
			$scoring['total_score'],
			$scoring['average_score']
		);

		CA_Database::save_category_scores($submission_id, $scoring['category_scores']);

		// Send completion email to user
		CA_Mailer::send_results_email($submission_id);

		wp_send_json_success(array(
			'message' => __('Assessment submitted.', 'custom-assessment'),
		));
	}

	// -------------------------------------------------------------------------
	// Action: get results preview
	// -------------------------------------------------------------------------

	public function ca_get_results_preview()
	{
		$this->verify_nonce();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce already verified via $this->verify_nonce().
		$submission_id = isset($_POST['submission_id']) ? absint($_POST['submission_id']) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if (!$submission_id) {
			wp_send_json_error(array('message' => __('Invalid session.', 'custom-assessment')));
		}

		$submission = CA_Database::get_submission($submission_id);
		$cat_scores_raw = CA_Database::get_category_scores($submission_id);

		if (!$submission) {
			wp_send_json_error(array('message' => __('Submission not found.', 'custom-assessment')));
		}

		// Build category data with summaries
		$category_scores = array();
		foreach ($cat_scores_raw as $cat) {
			$category_scores[] = array(
				'name' => $cat->category_name,
				'subtotal' => (int) $cat->subtotal,
				'average' => (float) $cat->average,
				'summary' => CA_Scoring::get_category_summary($cat->category_name, (float) $cat->average),
			);
		}

		$overall_profile = CA_Scoring::get_overall_profile((float) $submission->average_score);

		wp_send_json_success(array(
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
			'max_score' => CA_Questions::get_total_count() * 5,
		));
	}
}

