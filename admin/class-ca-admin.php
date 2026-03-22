<?php
/**
 * Admin page: list and detail view for all submissions.
 */

if (!defined('ABSPATH')) {
	exit;
}

class CA_Admin
{

	public function __construct()
	{
		add_action('admin_init', array($this, 'handle_delete_action'));
		add_action('admin_init', array($this, 'handle_export_action'));
		add_action('admin_init', array($this, 'handle_send_email_action'));
		add_action('admin_init', array($this, 'handle_categories_action'));
		add_action('admin_init', array($this, 'handle_edit_category_action'));
		add_action('admin_init', array($this, 'handle_questions_action'));
		add_action('wp_ajax_ca_edit_question_ajax', array($this, 'handle_edit_question_ajax'));
		add_action('admin_menu', array($this, 'register_menu'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
	}

	public function register_menu()
	{
		add_menu_page(
			__('Assessment Dashboard', 'custom-assessment'),
			__('Assessment', 'custom-assessment'),
			'manage_options',
			'custom-assessment-dashboard',
			array($this, 'render_dashboard_page'),
			'dashicons-chart-bar',
			56
		);

		add_submenu_page(
			'custom-assessment-dashboard',
			__('Dashboard', 'custom-assessment'),
			__('Dashboard', 'custom-assessment'),
			'manage_options',
			'custom-assessment-dashboard',
			array($this, 'render_dashboard_page')
		);

		add_submenu_page(
			'custom-assessment-dashboard',
			__('Submissions', 'custom-assessment'),
			__('Submissions', 'custom-assessment'),
			'manage_options',
			'custom-assessment-submissions',
			array($this, 'render_list_page')
		);

		add_submenu_page(
			'custom-assessment-dashboard',
			__('Questions', 'custom-assessment'),
			__('Questions', 'custom-assessment'),
			'manage_options',
			'custom-assessment-questions',
			array($this, 'render_questions_page')
		);

		add_submenu_page(
			'custom-assessment-dashboard',
			__('Categories', 'custom-assessment'),
			__('Categories', 'custom-assessment'),
			'manage_options',
			'custom-assessment-categories',
			array($this, 'render_categories_page')
		);
	}

	public function enqueue_admin_assets($hook)
	{
		if (strpos($hook, 'custom-assessment') === false) {
			return;
		}
		wp_enqueue_style(
			'ca-admin-styles',
			CA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			CA_VERSION
		);
		wp_enqueue_script(
			'ca-admin-scripts',
			CA_PLUGIN_URL . 'assets/js/admin.js',
			array('jquery'),
			CA_VERSION,
			true
		);
	}

	/**
	 * Handle delete action early on admin_init before any output.
	 */
	public function handle_delete_action()
	{
		if (!isset($_GET['page']) || !in_array($_GET['page'], array('custom-assessment-dashboard', 'custom-assessment-submissions'), true)) {
			return;
		}

		if (!current_user_can('manage_options')) {
			return;
		}

		if (isset($_GET['action']) && 'delete' === $_GET['action'] && !empty($_GET['id'])) {
			if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'ca_delete_submission_' . absint($_GET['id']))) {
				wp_die(esc_html__('Security check failed.', 'custom-assessment'));
			}

			CA_Database::delete_submission(absint($_GET['id']));
			$redirect_url = remove_query_arg(array('action', 'id', '_wpnonce'), wp_unslash($_SERVER['REQUEST_URI']));
			$redirect_url = add_query_arg('message', 'deleted', $redirect_url);
			wp_safe_redirect(esc_url_raw($redirect_url));
			exit;
		}
	}

	/**
	 * Handle export action early on admin_init before any output.
	 */
	public function handle_export_action()
	{
		if (!isset($_GET['page']) || !in_array($_GET['page'], array('custom-assessment-dashboard', 'custom-assessment-submissions'), true)) {
			return;
		}

		if (!current_user_can('manage_options')) {
			return;
		}

		if (isset($_GET['action']) && 'export' === $_GET['action'] && !empty($_GET['id']) && !empty($_GET['format'])) {
			if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'ca_export_submission_' . absint($_GET['id']))) {
				wp_die(esc_html__('Security check failed.', 'custom-assessment'));
			}

			$submission_id = absint($_GET['id']);
			$format = sanitize_text_field(wp_unslash($_GET['format']));
			$submission = CA_Database::get_submission($submission_id);

			if (!$submission || 'completed' !== $submission->status) {
				wp_die(esc_html__('Only completed submissions can be exported.', 'custom-assessment'));
			}

			if ('csv' === $format) {
				$this->export_as_csv($submission_id, $submission);
			} elseif ('pdf' === $format) {
				$this->export_as_pdf($submission_id, $submission);
			}

			exit;
		}
	}

	/**
	 * Handle send email action early on admin_init before any output.
	 */
	public function handle_send_email_action()
	{
		if (!isset($_GET['page']) || !in_array($_GET['page'], array('custom-assessment-dashboard', 'custom-assessment-submissions'), true)) {
			return;
		}

		if (!current_user_can('manage_options')) {
			return;
		}

		if (isset($_GET['action']) && 'send_email' === $_GET['action'] && !empty($_GET['id'])) {
			if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'ca_send_email_' . absint($_GET['id']))) {
				wp_die(esc_html__('Security check failed.', 'custom-assessment'));
			}

			$submission_id = absint($_GET['id']);
			$submission = CA_Database::get_submission($submission_id);

			if (!$submission) {
				wp_die(esc_html__('Submission not found.', 'custom-assessment'));
			}

			if ('completed' !== $submission->status) {
				wp_die(esc_html__('Only completed submissions can have emails sent.', 'custom-assessment'));
			}

			// Send the email using the existing mailer
			$sent = CA_Mailer::send_results_email($submission_id);

			$redirect_url = remove_query_arg(array('action', 'id', '_wpnonce'), wp_unslash($_SERVER['REQUEST_URI']));
			if ($sent) {
				$redirect_url = add_query_arg('message', 'email_sent', $redirect_url);
			} else {
				$redirect_url = add_query_arg('message', 'email_failed', $redirect_url);
			}
			wp_safe_redirect(esc_url_raw($redirect_url));
			exit;
		}
	}

	/**
	 * Handle categories form submissions early on admin_init before any output.
	 */
	public function handle_categories_action()
	{
		if (!isset($_GET['page']) || 'custom-assessment-categories' !== $_GET['page']) {
			return;
		}

		if (!current_user_can('manage_options')) {
			return;
		}

		if (isset($_POST['ca_action']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_categories_action')) {
			if ('add_category' === $_POST['ca_action'] && !empty($_POST['new_category'])) {
				$new_category = sanitize_text_field(wp_unslash($_POST['new_category']));
				if (!empty($new_category)) {
					// Check if category already exists
					$existing_categories = CA_Questions::get_categories();
					if (in_array($new_category, $existing_categories)) {
						$message = 'duplicate';
					} else {
						$this->add_category($new_category);
						$message = 'added';
					}
				}
			} elseif ('delete_category' === $_POST['ca_action'] && !empty($_POST['category_name'])) {
				$category_name = sanitize_text_field(wp_unslash($_POST['category_name']));
				if (!empty($category_name)) {
					$this->delete_category($category_name);
					$message = 'deleted';
				}
			}

			if (isset($message)) {
				$redirect_url = add_query_arg('message', $message, admin_url('admin.php?page=custom-assessment-categories'));
				wp_safe_redirect(esc_url_raw($redirect_url));
				exit;
			}
		}
	}

	/**
	 * Handle edit category action early on admin_init before any output.
	 */
	public function handle_edit_category_action()
	{
		if (!isset($_GET['page']) || 'custom-assessment-categories' !== $_GET['page']) {
			return;
		}

		if (!current_user_can('manage_options')) {
			return;
		}

		if (isset($_POST['ca_action']) && 'edit_category' === $_POST['ca_action'] && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_edit_category_action')) {
			$old_category = sanitize_text_field(wp_unslash($_POST['old_category_name']));
			$new_category = sanitize_text_field(wp_unslash($_POST['new_category_name']));

			if (!empty($old_category) && !empty($new_category) && $old_category !== $new_category) {
				$this->edit_category($old_category, $new_category);
				$message = 'edited';

				$redirect_url = add_query_arg('message', $message, admin_url('admin.php?page=custom-assessment-categories'));
				wp_safe_redirect(esc_url_raw($redirect_url));
				exit;
			}
		}
	}

	/**
	 * Handle questions form submissions early on admin_init before any output.
	 */
	public function handle_questions_action()
	{
		if (!isset($_GET['page']) || 'custom-assessment-questions' !== $_GET['page']) {
			return;
		}

		if (!current_user_can('manage_options')) {
			return;
		}

		if (isset($_POST['ca_action']) && 'delete_question' === $_POST['ca_action'] && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_delete_question_action')) {
			$question_index = absint($_POST['question_index']);
			if ($question_index >= 0) {
				$this->delete_question($question_index);
				$message = 'question_deleted';

				$redirect_url = add_query_arg('message', $message, admin_url('admin.php?page=custom-assessment-questions'));
				wp_safe_redirect(esc_url_raw($redirect_url));
				exit;
			}
		}

		if (isset($_POST['ca_action']) && 'edit_question' === $_POST['ca_action'] && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_edit_question_action')) {
			$question_index = absint($_POST['question_index']);
			$new_category = isset($_POST['new_category']) ? sanitize_text_field(wp_unslash($_POST['new_category'])) : '';
			$new_question_text = isset($_POST['new_question_text']) ? sanitize_text_field(wp_unslash($_POST['new_question_text'])) : '';
			$new_priority = isset($_POST['new_priority']) ? absint($_POST['new_priority']) : 0;

			if ($question_index >= 0 && '' !== $new_category && '' !== $new_question_text && $new_priority > 0) {
				// Enforce unique priority within the same category (except the current question).
				$flat_questions = CA_Questions::get_flat();
				$priority_exists = false;
				foreach ($flat_questions as $q) {
					if (!isset($q['index'], $q['category'], $q['priority'])) {
						continue;
					}
					$idx = (int) $q['index'];
					if ($idx === (int) $question_index) {
						continue;
					}
					if ((string) $q['category'] === (string) $new_category && (int) $q['priority'] === (int) $new_priority) {
						$priority_exists = true;
						break;
					}
				}

				if ($priority_exists) {
					$message = 'priority_exists';
					$redirect_url = add_query_arg('message', $message, admin_url('admin.php?page=custom-assessment-questions'));
					wp_safe_redirect(esc_url_raw($redirect_url));
					exit;
				}

				$edited = $this->edit_question($question_index, $new_category, $new_question_text, $new_priority);
				$message = $edited ? 'question_edited' : 'question_edit_failed';

				$redirect_url = add_query_arg('message', $message, admin_url('admin.php?page=custom-assessment-questions'));
				wp_safe_redirect(esc_url_raw($redirect_url));
				exit;
			}
		}

		if (isset($_POST['ca_action']) && 'bulk_edit_questions' === $_POST['ca_action'] && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_bulk_edit_question_action')) {
			$indexes = isset($_POST['question_indexes']) ? (array) $_POST['question_indexes'] : array();
			$indexes = array_map('absint', $indexes);
			$indexes = array_values(array_filter($indexes, fn($i) => $i >= 0));

			$bulk_category = isset($_POST['bulk_category']) ? sanitize_text_field(wp_unslash($_POST['bulk_category'])) : '';
			$bulk_question_text = isset($_POST['bulk_question_text']) ? sanitize_text_field(wp_unslash($_POST['bulk_question_text'])) : '';
			$bulk_priority = isset($_POST['bulk_priority']) ? absint($_POST['bulk_priority']) : 0;

			$override_category = '' !== $bulk_category;
			$override_text = '' !== $bulk_question_text;
			$override_priority = $bulk_priority > 0;

			if (empty($indexes)) {
				$message = 'bulk_edit_failed';
				$redirect_url = add_query_arg('message', $message, admin_url('admin.php?page=custom-assessment-questions'));
				wp_safe_redirect(esc_url_raw($redirect_url));
				exit;
			}

			$flat_questions = CA_Questions::get_flat();
			$selected_set = array_flip($indexes);

			// Build the target category/text/priority for each selected question.
			$targets = array();
			foreach ($indexes as $idx) {
				if (!isset($flat_questions[$idx])) {
					continue;
				}

				$original = $flat_questions[$idx];
				$set_category = $override_category ? $bulk_category : $original['category'];
				$set_text = $override_text ? $bulk_question_text : $original['text'];
				$set_priority = $override_priority ? $bulk_priority : (int) $original['priority'];

				if (!empty($set_category) && !empty($set_text) && $set_priority > 0) {
					$targets[$idx] = array(
						'category' => $set_category,
						'text' => $set_text,
						'priority' => (int) $set_priority,
					);
				}
			}

			if (empty($targets)) {
				$message = 'bulk_edit_failed';
				$redirect_url = add_query_arg('message', $message, admin_url('admin.php?page=custom-assessment-questions'));
				wp_safe_redirect(esc_url_raw($redirect_url));
				exit;
			}

			// Enforce unique priority within the same category, including collisions inside the selected batch.
			$existing_keys = array();
			foreach ($flat_questions as $q) {
				if (!isset($q['index'], $q['category'], $q['priority'])) {
					continue;
				}
				$q_idx = (int) $q['index'];
				if (isset($selected_set[$q_idx])) {
					continue;
				}
				$key = (string) $q['category'] . '|' . (int) $q['priority'];
				$existing_keys[$key] = true;
			}

			$target_keys = array();
			$priority_collision = false;
			foreach ($targets as $idx => $t) {
				$key = (string) $t['category'] . '|' . (int) $t['priority'];

				if (isset($target_keys[$key])) {
					$priority_collision = true;
					break;
				}
				$target_keys[$key] = true;

				if (isset($existing_keys[$key])) {
					$priority_collision = true;
					break;
				}
			}

			if ($priority_collision) {
				$message = 'priority_exists';
				$redirect_url = add_query_arg('message', $message, admin_url('admin.php?page=custom-assessment-questions'));
				wp_safe_redirect(esc_url_raw($redirect_url));
				exit;
			}

			$updated_any = false;
			foreach ($targets as $idx => $t) {
				$ok = $this->edit_question($idx, $t['category'], $t['text'], $t['priority']);
				if ($ok) {
					$updated_any = true;
				}
			}

			$message = $updated_any ? 'bulk_edit_success' : 'bulk_edit_failed';
			$redirect_url = add_query_arg('message', $message, admin_url('admin.php?page=custom-assessment-questions'));
			wp_safe_redirect(esc_url_raw($redirect_url));
			exit;
		}

		if (isset($_POST['ca_action']) && 'add_question' === $_POST['ca_action'] && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_add_question_action')) {
			$question_text = sanitize_text_field(wp_unslash($_POST['question_text']));
			$question_category = sanitize_text_field(wp_unslash($_POST['question_category']));
			$question_priority = absint($_POST['question_priority']);

			if (!empty($question_text) && !empty($question_category) && $question_priority > 0) {
				// Enforce unique priority within the same category.
				$flat_questions = CA_Questions::get_flat();
				$priority_exists = false;
				foreach ($flat_questions as $q) {
					if (
						isset($q['category'], $q['priority']) &&
						(string) $q['category'] === (string) $question_category &&
						(int) $q['priority'] === (int) $question_priority
					) {
						$priority_exists = true;
						break;
					}
				}

				if ($priority_exists) {
					$message = 'priority_exists';
					$redirect_url = add_query_arg('message', $message, admin_url('admin.php?page=custom-assessment-questions'));
					wp_safe_redirect(esc_url_raw($redirect_url));
					exit;
				}

				// Debug: Log the question being added
				error_log("Adding question: $question_text | Category: $question_category | Priority: $question_priority");

				$this->add_question($question_text, $question_category, $question_priority);
				$message = 'question_added';

				$redirect_url = add_query_arg('message', $message, admin_url('admin.php?page=custom-assessment-questions'));
				wp_safe_redirect(esc_url_raw($redirect_url));
				exit;
			}
		}
	}

	/**
	 * AJAX handler for inline edits on the Assessment Questions table.
	 */
	public function handle_edit_question_ajax()
	{
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => 'You do not have permission to edit questions.'), 403);
		}

		$nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
		if (empty($nonce) || !wp_verify_nonce($nonce, 'ca_edit_question_action')) {
			wp_send_json_error(array('message' => 'Security check failed.'), 403);
		}

		$question_index = isset($_POST['question_index']) ? absint($_POST['question_index']) : -1;
		$new_category = isset($_POST['new_category']) ? sanitize_text_field(wp_unslash($_POST['new_category'])) : '';
		$new_question_text = isset($_POST['new_question_text']) ? sanitize_text_field(wp_unslash($_POST['new_question_text'])) : '';
		$new_priority = isset($_POST['new_priority']) ? absint($_POST['new_priority']) : 0;

		if ($question_index < 0 || '' === $new_category || '' === $new_question_text || $new_priority <= 0) {
			wp_send_json_error(array('message' => 'Invalid input.'), 400);
		}

		// Enforce unique priority within the same category (except the current question).
		$flat_questions = CA_Questions::get_flat();
		$priority_exists = false;
		foreach ($flat_questions as $q) {
			if (!isset($q['index'], $q['category'], $q['priority'])) {
				continue;
			}
			$idx = (int) $q['index'];
			if ($idx === (int) $question_index) {
				continue;
			}
			if ((string) $q['category'] === (string) $new_category && (int) $q['priority'] === (int) $new_priority) {
				$priority_exists = true;
				break;
			}
		}
		if ($priority_exists) {
			wp_send_json_error(
				array('message' => esc_html__('Priority already exists in this category. Please choose another number.', 'custom-assessment')),
				409
			);
		}

		$edited = $this->edit_question($question_index, $new_category, $new_question_text, $new_priority);
		if (!$edited) {
			wp_send_json_error(array('message' => 'Unable to update this question.'), 404);
		}

		$updated = CA_Questions::get_question($question_index);
		wp_send_json_success(array(
			'question_index' => $question_index,
			'category' => isset($updated['category']) ? (string) $updated['category'] : $new_category,
			'text' => isset($updated['text']) ? (string) $updated['text'] : $new_question_text,
			'priority' => isset($updated['priority']) ? (int) $updated['priority'] : (int) $new_priority,
		));
	}

	/**
	 * Export submission as CSV.
	 */
	private function export_as_csv($submission_id, $submission)
	{
		$answers = CA_Database::get_answers($submission_id);
		$cat_scores = CA_Database::get_category_scores($submission_id);
		$flat_q = CA_Questions::get_flat();

		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="submission_' . $submission_id . '_' . sanitize_file_name($submission->first_name . '_' . $submission->last_name) . '.csv"');

		$output = fopen('php://output', 'w');
		fputcsv($output, array('Respondent Information'));
		fputcsv($output, array('Field', 'Value'));
		fputcsv($output, array('Name', $submission->first_name . ' ' . $submission->last_name));
		fputcsv($output, array('Email', $submission->email));
		fputcsv($output, array('Phone', $submission->phone));
		fputcsv($output, array('Job Title', $submission->job_title));
		fputcsv($output, array('Total Score', $submission->total_score . ' / ' . (CA_Questions::get_total_count() * 5)));
		fputcsv($output, array('Average Score', number_format($submission->average_score, 2) . ' / 5.00'));
		fputcsv($output, array('Status', ucwords(str_replace('_', ' ', $submission->status))));
		fputcsv($output, array('Submitted', date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->created_at))));

		fputcsv($output, array());
		fputcsv($output, array('Category Scores'));
		fputcsv($output, array('Category', 'Subtotal', 'Average', 'Summary'));
		foreach ($cat_scores as $cat) {
			fputcsv($output, array(
				$cat->category_name,
				$cat->subtotal,
				number_format($cat->average, 2),
				CA_Scoring::get_category_summary($cat->category_name, (float) $cat->average)
			));
		}

		fputcsv($output, array());
		fputcsv($output, array('Question Responses'));
		fputcsv($output, array('Question', 'Response'));
		foreach ($flat_q as $idx => $q) {
			$answer = isset($answers[$idx]) ? $answers[$idx] : null;
			fputcsv($output, array($q['text'], $answer ? $answer : 'No answer'));
		}

		fclose($output);
	}

	/**
	 * Export submission as PDF.
	 */
	private function export_as_pdf($submission_id, $submission)
	{
		$answers = CA_Database::get_answers($submission_id);
		$cat_scores = CA_Database::get_category_scores($submission_id);
		$flat_q = CA_Questions::get_flat();

		$html = '<html>
			<head>
				<meta charset="UTF-8">
				<style>
					body { font-family: Arial, sans-serif; margin: 20px; }
					h1 { color: #333; border-bottom: 2px solid #0073aa; padding-bottom: 10px; }
					h2 { color: #555; margin-top: 20px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
					table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
					th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
					th { background-color: #f5f5f5; font-weight: bold; }
					.info-block { margin-bottom: 15px; }
					.info-label { font-weight: bold; display: inline-block; width: 120px; }
					.page-break { page-break-after: always; }
				</style>
			</head>
			<body>
				<h1>Assessment Submission Report</h1>
				<div class="info-block">
					<div><span class="info-label">Name:</span> ' . esc_html($submission->first_name . ' ' . $submission->last_name) . '</div>
					<div><span class="info-label">Email:</span> ' . esc_html($submission->email) . '</div>
					<div><span class="info-label">Phone:</span> ' . esc_html($submission->phone) . '</div>
					<div><span class="info-label">Job Title:</span> ' . esc_html($submission->job_title) . '</div>
					<div><span class="info-label">Total Score:</span> ' . esc_html($submission->total_score . ' / ' . (CA_Questions::get_total_count() * 5)) . '</div>
					<div><span class="info-label">Average Score:</span> ' . esc_html(number_format($submission->average_score, 2) . ' / 5.00') . '</div>
					<div><span class="info-label">Submitted:</span> ' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->created_at))) . '</div>
				</div>

				<h2>Category Scores</h2>
				<table>
					<thead>
						<tr>
							<th>Category</th>
							<th>Subtotal</th>
							<th>Average</th>
							<th>Summary</th>
						</tr>
					</thead>
					<tbody>';

		foreach ($cat_scores as $cat) {
			$html .= '<tr>
				<td>' . esc_html($cat->category_name) . '</td>
				<td>' . esc_html($cat->subtotal) . '</td>
				<td>' . esc_html(number_format($cat->average, 2)) . '</td>
				<td>' . esc_html(CA_Scoring::get_category_summary($cat->category_name, (float) $cat->average)) . '</td>
			</tr>';
		}

		$html .= '</tbody>
				</table>

				<h2>Question Responses</h2>
				<table>
					<thead>
						<tr>
							<th>Question</th>
							<th>Response</th>
						</tr>
					</thead>
					<tbody>';

		foreach ($flat_q as $idx => $q) {
			$answer = isset($answers[$idx]) ? $answers[$idx] : null;
			$html .= '<tr>
				<td>' . esc_html($q['text']) . '</td>
				<td>' . esc_html($answer ? $answer : 'No answer') . '</td>
			</tr>';
		}

		$html .= '</tbody>
				</table>
			</body>
		</html>';

		$filename = 'submission_' . $submission_id . '_' . sanitize_file_name($submission->first_name . '_' . $submission->last_name) . '.pdf';
		require_once CA_PLUGIN_DIR . 'includes/class-ca-pdf.php';
		$pdf = new CA_PDF();
		$pdf->export_pdf($html, $filename);
	}

	/**
	 * Check if SMTP is configured for email sending.
	 * 
	 * @return bool True if SMTP is configured, false otherwise
	 */
	private function is_smtp_configured()
	{
		// Check for common SMTP plugin settings/options that indicate SMTP is configured
		$smtp_indicators = array(
			'wp_mail_smtp',           // WP Mail SMTP plugin
			'swpsmtp_options',        // Easy WP SMTP plugin
			'postman_options',        // Post SMTP plugin
			'pepipost_options',       // Pepipost plugin
			'sendgrid_options',       // SendGrid plugin
			'mailgun_options',        // Mailgun plugin
			'wp_ses_options',         // AWS SES plugin
			'gmail_smtp_options',     // Gmail SMTP plugin
		);

		$has_smtp_config = false;

		// Check if any SMTP plugin has active configuration
		foreach ($smtp_indicators as $option_name) {
			$option = get_option($option_name);
			if ($option && is_array($option) && !empty($option)) {
				// Check if the configuration looks valid (has host, username, etc.)
				if (isset($option['mail']['host']) && !empty($option['mail']['host'])) {
					$has_smtp_config = true;
					break;
				}
				if (isset($option['host']) && !empty($option['host'])) {
					$has_smtp_config = true;
					break;
				}
				if (isset($option['smtp_host']) && !empty($option['smtp_host'])) {
					$has_smtp_config = true;
					break;
				}
			}
		}

		// Check for specific plugin constants that indicate SMTP is active
		$smtp_constants = array(
			'WPMailSMTP',
			'Easy_Wp_SMTP',
			'Postman_SMTP',
			'PEPIPOST_PLUGIN_VERSION',
			'SENDGRID_PLUGIN_VERSION',
		);

		foreach ($smtp_constants as $constant) {
			if (defined($constant) || class_exists($constant)) {
				$has_smtp_config = true;
				break;
			}
		}

		// Check if wp_mail is being filtered (indicates SMTP plugin is active)
		if (has_filter('wp_mail_from') || has_filter('wp_mail_from_name')) {
			$has_smtp_config = true;
		}

		// Additional check: test if we can detect SMTP settings in common locations
		// This helps catch cases where plugins are installed but not yet configured
		$test_configs = array(
			'wp_mail_smtp',
			'swpsmtp_options',
			'postman_options'
		);

		foreach ($test_configs as $config_key) {
			$config = get_option($config_key);
			if ($config) {
				// Look for SMTP-specific settings
				$smtp_keys = array('host', 'smtp_host', 'mail_host', 'server', 'smtp_server');
				foreach ($smtp_keys as $key) {
					if (isset($config[$key]) && !empty($config[$key])) {
						$has_smtp_config = true;
						break 2; // break both loops
					}
					// Check nested mail array
					if (isset($config['mail']) && isset($config['mail'][$key]) && !empty($config['mail'][$key])) {
						$has_smtp_config = true;
						break 2; // break both loops
					}
				}
			}
		}

		// Return true only if we found clear evidence of SMTP configuration
		// Otherwise return false to show the warning message
		return $has_smtp_config;
	}

	/**
	 * Render assessment dashboard.
	 */
	public function render_dashboard_page()
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'custom-assessment'));
		}

		// Check SMTP configuration - show error if no SMTP detected
		// This ensures users are aware they need SMTP for email functionality
		$smtp_configured = $this->is_smtp_configured();

		$submissions = CA_Database::get_all_submissions();
		$completed = array_filter($submissions, fn($s) => $s->status === 'completed');
		$in_progress = array_filter($submissions, fn($s) => $s->status === 'in_progress');

		// Calculate statistics
		$total_submissions = count($submissions);
		$completed_count = count($completed);
		$in_progress_count = count($in_progress);
		$completion_rate = $total_submissions > 0 ? round(($completed_count / $total_submissions) * 100) : 0;

		// Calculate average scores from completed submissions
		$avg_total_score = 0;
		$avg_average_score = 0;
		if ($completed_count > 0) {
			$sum_total = array_sum(array_map(fn($s) => (float) $s->total_score, $completed));
			$sum_avg = array_sum(array_map(fn($s) => (float) $s->average_score, $completed));
			$avg_total_score = $sum_total / $completed_count;
			$avg_average_score = $sum_avg / $completed_count;
		}

		// Get recent submissions
		$recent_submissions = array_slice($submissions, 0, 5);
		?>
		<div class="wrap ca-admin-wrap">
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-chart-bar"></span>
				<?php esc_html_e('Assessment Dashboard', 'custom-assessment'); ?>
			</h1>

			<?php if (!$smtp_configured): ?>
				<div class="notice notice-error is-dismissible">
					<p><strong><?php esc_html_e('Warning: No SMTP configuration detected.', 'custom-assessment'); ?></strong></p>
					<p><?php esc_html_e('Email notifications for completed assessments may not work properly. Please configure an SMTP plugin to ensure emails are delivered successfully.', 'custom-assessment'); ?>
					</p>
					<p><em><?php esc_html_e('Recommended plugins: WP Mail SMTP, Easy WP SMTP, Post SMTP Mailer, or similar.', 'custom-assessment'); ?></em>
					</p>
				</div>
			<?php endif; ?>

			<div class="ca-dashboard-grid">
				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html($total_submissions); ?></div>
					<div class="ca-dashboard-card-label"><?php esc_html_e('Total Submissions', 'custom-assessment'); ?></div>
				</div>

				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html($completed_count); ?></div>
					<div class="ca-dashboard-card-label"><?php esc_html_e('Completed', 'custom-assessment'); ?></div>
				</div>

				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html($in_progress_count); ?></div>
					<div class="ca-dashboard-card-label"><?php esc_html_e('In Progress', 'custom-assessment'); ?></div>
				</div>

				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html($completion_rate); ?>%</div>
					<div class="ca-dashboard-card-label"><?php esc_html_e('Completion Rate', 'custom-assessment'); ?></div>
				</div>

				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html(number_format($avg_total_score, 1)); ?></div>
					<div class="ca-dashboard-card-label"><?php esc_html_e('Avg Total Score', 'custom-assessment'); ?></div>
				</div>

				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html(number_format($avg_average_score, 2)); ?>/5
					</div>
					<div class="ca-dashboard-card-label"><?php esc_html_e('Avg Score Per Q', 'custom-assessment'); ?></div>
				</div>
			</div>

			<div class="ca-dashboard-section">
				<h2><?php esc_html_e('Recent Submissions', 'custom-assessment'); ?></h2>

				<?php if (empty($recent_submissions)): ?>
					<p><?php esc_html_e('No submissions yet.', 'custom-assessment'); ?></p>
				<?php else: ?>
					<table class="wp-list-table widefat fixed striped ca-admin-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e('Name', 'custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Email', 'custom-assessment'); ?></th>
								<th scope="col" class="ca-col-score"><?php esc_html_e('Score', 'custom-assessment'); ?></th>
								<th scope="col" class="ca-col-status"><?php esc_html_e('Status', 'custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Date', 'custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Action', 'custom-assessment'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($recent_submissions as $sub): ?>
								<tr>
									<td><strong><?php echo esc_html($sub->first_name . ' ' . $sub->last_name); ?></strong></td>
									<td><?php echo esc_html($sub->email); ?></td>
									<td class="ca-col-score">
										<?php echo 'completed' === $sub->status ? esc_html(number_format($sub->average_score, 2)) : '—'; ?>
									</td>
									<td class="ca-col-status">
										<span class="ca-status-badge ca-status--<?php echo esc_attr($sub->status); ?>">
											<?php echo esc_html(ucwords(str_replace('_', ' ', $sub->status))); ?>
										</span>
									</td>
									<td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($sub->created_at))); ?>
									</td>
									<td>
										<a href="<?php echo esc_url(add_query_arg(array('page' => 'custom-assessment-submissions', 'view' => 'detail', 'id' => $sub->id), admin_url('admin.php'))); ?>"
											class="button button-small">
											<?php esc_html_e('View', 'custom-assessment'); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p>
						<a href="<?php echo esc_url(add_query_arg(array('page' => 'custom-assessment-submissions'), admin_url('admin.php'))); ?>"
							class="button button-primary">
							<?php esc_html_e('View All Submissions', 'custom-assessment'); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}


	/**
	 * Render list page or detail view.
	 */
	public function render_list_page()
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'custom-assessment'));
		}

		// Check SMTP configuration - show error if no SMTP detected
		// This ensures users are aware they need SMTP for email functionality
		$smtp_configured = $this->is_smtp_configured();

		// Detail view
		if (isset($_GET['view']) && 'detail' === $_GET['view'] && !empty($_GET['id'])) {
			$id = absint($_GET['id']);
			$this->render_detail_page($id);
			return;
		}

		// List view
		$all_submissions = CA_Database::get_all_submissions();

		// Pagination setup
		$per_page = 10;
		$current_page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
		$total_submissions_count = count($all_submissions);
		$total_pages = max(1, (int) ceil($total_submissions_count / $per_page));
		$offset = ($current_page - 1) * $per_page;
		$paged_submissions = array_slice($all_submissions, $offset, $per_page);

		$submissions = $paged_submissions;

		// Statistics (calculated over all submissions, not paged subset).
		$completed_count = 0;
		$active_count = 0; // started + in_progress
		$latest_created_at = '';
		$completed_avg_sum = 0.0;

		foreach ($all_submissions as $sub) {
			if (!isset($sub->status, $sub->created_at, $sub->average_score)) {
				continue;
			}

			$status = (string) $sub->status;
			if ('completed' === $status) {
				$completed_count++;
				$completed_avg_sum += (float) $sub->average_score;
			} elseif ('started' === $status || 'in_progress' === $status) {
				$active_count++;
			}

			$created_ts = strtotime($sub->created_at);
			if (false !== $created_ts) {
				if ('' === $latest_created_at || $created_ts > strtotime($latest_created_at)) {
					$latest_created_at = $sub->created_at;
				}
			}
		}

		$completed_avg = $completed_count > 0 ? $completed_avg_sum / $completed_count : 0.0;
		$latest_created_display = $latest_created_at ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($latest_created_at)) : '—';
		?>
		<div class="wrap ca-admin-wrap">
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-chart-bar"></span>
				<?php esc_html_e('Assessment Submissions', 'custom-assessment'); ?>
			</h1>

			<?php if (!$smtp_configured): ?>
				<div class="notice notice-error is-dismissible">
					<p><strong><?php esc_html_e('Warning: No SMTP configuration detected.', 'custom-assessment'); ?></strong></p>
					<p><?php esc_html_e('Email notifications for completed assessments may not work properly. Please configure an SMTP plugin to ensure emails are delivered successfully.', 'custom-assessment'); ?>
					</p>
					<p><em><?php esc_html_e('Recommended plugins: WP Mail SMTP, Easy WP SMTP, Post SMTP Mailer, or similar.', 'custom-assessment'); ?></em>
					</p>
				</div>
			<?php endif; ?>

			<?php if (isset($_GET['message']) && 'deleted' === $_GET['message']): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Submission deleted successfully.', 'custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if (isset($_GET['message']) && 'email_sent' === $_GET['message']): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Assessment results email sent successfully to the customer.', 'custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if (isset($_GET['message']) && 'email_failed' === $_GET['message']): ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e('Failed to send assessment results email. Please check your SMTP configuration.', 'custom-assessment'); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if (empty($all_submissions)): ?>
				<div class="ca-admin-empty">
					<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					<p><?php esc_html_e('No submissions yet. Share the assessment shortcode [custom_assessment] on any page.', 'custom-assessment'); ?>
					</p>
				</div>
			<?php else: ?>

				<!-- Basic Statistics -->
				<div class="ca-questions-stats-grid">
					<div class="ca-stat-card">
						<div class="ca-stat-value"><?php echo esc_html($total_submissions_count); ?></div>
						<div class="ca-stat-label"><?php esc_html_e('Total Submissions', 'custom-assessment'); ?></div>
					</div>

					<div class="ca-stat-card">
						<div class="ca-stat-value"><?php echo esc_html($completed_count); ?></div>
						<div class="ca-stat-label"><?php esc_html_e('Completed', 'custom-assessment'); ?></div>
					</div>

					<div class="ca-stat-card">
						<div class="ca-stat-value"><?php echo esc_html($active_count); ?></div>
						<div class="ca-stat-label"><?php esc_html_e('In Progress', 'custom-assessment'); ?></div>
					</div>

					<div class="ca-stat-card">
						<div class="ca-stat-value"><?php echo esc_html(number_format($completed_avg, 2)); ?></div>
						<div class="ca-stat-label"><?php esc_html_e('Avg Score (Completed)', 'custom-assessment'); ?></div>
						<div class="ca-stat-sublabel">
							<?php esc_html_e('Latest submission: ', 'custom-assessment'); ?>
							<?php echo esc_html($latest_created_display); ?>
						</div>
					</div>
				</div>

				<div class="ca-questions-search" style="text-align: end;">
					<div class="ca-search-field">
						<label for="ca-search-submissions"><?php esc_html_e('Search Submissions', 'custom-assessment'); ?></label>
						<input type="text" id="ca-search-submissions"
							placeholder="<?php esc_attr_e('Search by ID, name, email, phone, job title, score, or status (minimum 3 characters)...', 'custom-assessment'); ?>"
							autocomplete="off">
						<div class="ca-search-count" style="display: none;">
							<span id="ca-search-results-count"></span>
						</div>
					</div>
				</div>

				<br />

				<table class="wp-list-table widefat fixed striped ca-admin-table">
					<thead>
						<tr>
							<th scope="col" class="ca-col-id"><?php esc_html_e('#', 'custom-assessment'); ?></th>
							<th scope="col"><?php esc_html_e('Name', 'custom-assessment'); ?></th>
							<th scope="col"><?php esc_html_e('Email', 'custom-assessment'); ?></th>
							<th scope="col"><?php esc_html_e('Phone', 'custom-assessment'); ?></th>
							<th scope="col"><?php esc_html_e('Job Title', 'custom-assessment'); ?></th>
							<th scope="col" class="ca-col-score"><?php esc_html_e('Total Score', 'custom-assessment'); ?></th>
							<th scope="col" class="ca-col-score"><?php esc_html_e('Average', 'custom-assessment'); ?></th>
							<th scope="col" class="ca-col-status"><?php esc_html_e('Status', 'custom-assessment'); ?></th>
							<th scope="col"><?php esc_html_e('Date', 'custom-assessment'); ?></th>
							<th scope="col"><?php esc_html_e('Actions', 'custom-assessment'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($submissions as $sub): ?>
							<tr>
								<td class="ca-col-id"><?php echo esc_html($sub->id); ?></td>
								<td>
									<strong><?php echo esc_html($sub->first_name . ' ' . $sub->last_name); ?></strong>
								</td>
								<td><?php echo esc_html($sub->email); ?></td>
								<td><?php echo esc_html($sub->phone); ?></td>
								<td><?php echo esc_html($sub->job_title); ?></td>
								<td class="ca-col-score">
									<?php echo 'completed' === $sub->status ? esc_html($sub->total_score) : '—'; ?>
								</td>
								<td class="ca-col-score">
									<?php echo 'completed' === $sub->status ? esc_html(number_format($sub->average_score, 2)) : '—'; ?>
								</td>
								<td class="ca-col-status">
									<span class="ca-status-badge ca-status--<?php echo esc_attr($sub->status); ?>">
										<?php echo esc_html(ucwords(str_replace('_', ' ', $sub->status))); ?>
									</span>
								</td>
								<td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($sub->created_at))); ?>
								</td>
								<td>
									<a href="<?php echo esc_url(add_query_arg(array('page' => 'custom-assessment-submissions', 'view' => 'detail', 'id' => $sub->id), admin_url('admin.php'))); ?>"
										class="button button-small">
										<?php esc_html_e('View', 'custom-assessment'); ?>
									</a>
									<?php if ('completed' === $sub->status): ?>
										<div class="ca-export-dropdown-wrapper">
											<div class="ca-export-menu ca-export-dropdown" id="export-<?php echo esc_attr($sub->id); ?>">
												<?php $csv_url = add_query_arg(array('page' => 'custom-assessment-submissions', 'action' => 'export', 'format' => 'csv', 'id' => $sub->id, '_wpnonce' => wp_create_nonce('ca_export_submission_' . $sub->id)), admin_url('admin.php')); ?>
												<a href="<?php echo esc_url($csv_url); ?>" class="ca-export-option">
													CSV
												</a>
												<?php $pdf_url = add_query_arg(array('page' => 'custom-assessment-submissions', 'action' => 'export', 'format' => 'pdf', 'id' => $sub->id, '_wpnonce' => wp_create_nonce('ca_export_submission_' . $sub->id)), admin_url('admin.php')); ?>
												<a href="<?php echo esc_url($pdf_url); ?>" class="ca-export-option">
													PDF
												</a>
											</div>
											<button type="button" class="button button-small ca-export-dropdown-btn"
												data-id="<?php echo esc_attr($sub->id); ?>">
												<?php esc_html_e('Export', 'custom-assessment'); ?> ▼
											</button>
										</div>

										<?php $email_url = add_query_arg(array('page' => 'custom-assessment-submissions', 'action' => 'send_email', 'id' => $sub->id, '_wpnonce' => wp_create_nonce('ca_send_email_' . $sub->id)), admin_url('admin.php')); ?>
										<a href="<?php echo esc_url($email_url); ?>" class="button button-small"
											onclick="return confirm('<?php echo esc_js(__('Are you sure you want to resend the assessment results email to this customer?', 'custom-assessment')); ?>');">
											<?php esc_html_e('Resend Email', 'custom-assessment'); ?>
										</a>
									<?php endif; ?>
									<?php $delete_url = add_query_arg(array('page' => 'custom-assessment-submissions', 'action' => 'delete', 'id' => $sub->id, '_wpnonce' => wp_create_nonce('ca_delete_submission_' . $sub->id)), admin_url('admin.php')); ?>
									<a href="<?php echo esc_url($delete_url); ?>" class="button button-small"
										onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this submission? This action cannot be undone.', 'custom-assessment')); ?>');">
										<?php esc_html_e('Delete', 'custom-assessment'); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php echo esc_html($total_submissions_count); ?> 			<?php esc_html_e('submissions', 'custom-assessment'); ?>
						</span>

						<?php if ($total_pages > 1): ?>
							<span class="pagination-links">
								<?php
								$base_url = admin_url('admin.php?page=custom-assessment-submissions');
								$prev_disabled = $current_page <= 1 ? 'disabled' : '';
								$next_disabled = $current_page >= $total_pages ? 'disabled' : '';

								echo '<a class="prev-page button ' . esc_attr($prev_disabled) . '" href="' . esc_url(add_query_arg('paged', max(1, $current_page - 1), $base_url)) . '">&laquo;</a>';

								$start_page = max(1, $current_page - 2);
								$end_page = min($total_pages, $start_page + 4);

								if ($start_page > 1) {
									echo '<a class="page-numbers" href="' . esc_url(add_query_arg('paged', 1, $base_url)) . '">1</a>';
									if ($start_page > 2) {
										echo '<span class="dots">…</span>';
									}
								}

								for ($i = $start_page; $i <= $end_page; $i++) {
									$active_class = ($i === $current_page) ? 'current' : '';
									echo '<a class="page-numbers ' . esc_attr($active_class) . '" href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '">' . esc_html($i) . '</a>';
								}

								if ($end_page < $total_pages) {
									if ($end_page < $total_pages - 1) {
										echo '<span class="dots">…</span>';
									}
									echo '<a class="page-numbers" href="' . esc_url(add_query_arg('paged', $total_pages, $base_url)) . '">' . esc_html($total_pages) . '</a>';
								}

								echo '<a class="next-page button ' . esc_attr($next_disabled) . '" href="' . esc_url(add_query_arg('paged', min($total_pages, $current_page + 1), $base_url)) . '">&raquo;</a>';
								?>
							</span>
						<?php endif; ?>
					</div>
					<br class="clear">
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the detail view for a single submission.
	 *
	 * @param int $submission_id
	 */
	private function render_detail_page($submission_id)
	{
		$submission = CA_Database::get_submission($submission_id);
		$answers = CA_Database::get_answers($submission_id);
		$cat_scores = CA_Database::get_category_scores($submission_id);
		$flat_q = CA_Questions::get_flat();

		if (!$submission) {
			echo '<div class="wrap"><p>' . esc_html__('Submission not found.', 'custom-assessment') . '</p></div>';
			return;
		}
		?>
		<div class="wrap ca-admin-wrap">
			<h1 class="ca-admin-title">
				<a href="<?php echo esc_url(admin_url('admin.php?page=custom-assessment-submissions')); ?>"
					class="ca-admin-back">
					<span class="dashicons dashicons-arrow-left-alt"></span>
				</a>
				<?php esc_html_e('Submission Detail', 'custom-assessment'); ?>
			</h1>

			<!-- User Info -->
			<div class="ca-admin-card">
				<h2 class="ca-admin-card-title"><?php esc_html_e('Respondent Information', 'custom-assessment'); ?></h2>
				<div class="ca-admin-info-grid">
					<div>
						<label><?php esc_html_e('Name', 'custom-assessment'); ?></label><span><?php echo esc_html($submission->first_name . ' ' . $submission->last_name); ?></span>
					</div>
					<div>
						<label><?php esc_html_e('Email', 'custom-assessment'); ?></label><span><?php echo esc_html($submission->email); ?></span>
					</div>
					<div>
						<label><?php esc_html_e('Phone', 'custom-assessment'); ?></label><span><?php echo esc_html($submission->phone); ?></span>
					</div>
					<div>
						<label><?php esc_html_e('Job Title', 'custom-assessment'); ?></label><span><?php echo esc_html($submission->job_title); ?></span>
					</div>
					<div>
						<label><?php esc_html_e('Submitted', 'custom-assessment'); ?></label><span><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->created_at))); ?></span>
					</div>
					<div><label><?php esc_html_e('Status', 'custom-assessment'); ?></label>
						<span
							class="ca-status-badge ca-status--<?php echo esc_attr($submission->status); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $submission->status))); ?></span>
					</div>
				</div>
			</div>

			<?php if ('completed' === $submission->status): ?>

				<!-- Overall Scores -->
				<div class="ca-admin-card">
					<h2 class="ca-admin-card-title"><?php esc_html_e('Overall Scores', 'custom-assessment'); ?></h2>
					<div class="ca-admin-score-row">
						<div class="ca-admin-score-box">
							<div class="ca-admin-score-value"><?php echo esc_html($submission->total_score); ?></div>
							<div class="ca-admin-score-label"><?php esc_html_e('Total Score', 'custom-assessment'); ?></div>
							<div class="ca-admin-score-max">
								<?php echo esc_html('/ ' . (CA_Questions::get_total_count() * 5)); ?>
							</div>
						</div>
						<div class="ca-admin-score-box">
							<div class="ca-admin-score-value">
								<?php echo esc_html(number_format($submission->average_score, 2)); ?>
							</div>
							<div class="ca-admin-score-label"><?php esc_html_e('Average Score', 'custom-assessment'); ?></div>
							<div class="ca-admin-score-max"><?php esc_html_e('/ 5.00', 'custom-assessment'); ?></div>
						</div>
						<div class="ca-admin-score-box">
							<div class="ca-admin-score-value ca-admin-score-profile">
								<?php echo esc_html(CA_Scoring::get_overall_profile((float) $submission->average_score)); ?>
							</div>
							<div class="ca-admin-score-label"><?php esc_html_e('Profile', 'custom-assessment'); ?></div>
						</div>
					</div>
				</div>

				<!-- Category Scores -->
				<div class="ca-admin-card">
					<h2 class="ca-admin-card-title"><?php esc_html_e('Category Scores', 'custom-assessment'); ?></h2>
					<table class="wp-list-table widefat fixed ca-admin-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Category', 'custom-assessment'); ?></th>
								<th><?php esc_html_e('Subtotal', 'custom-assessment'); ?></th>
								<th><?php esc_html_e('Average', 'custom-assessment'); ?></th>
								<th><?php esc_html_e('Summary', 'custom-assessment'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($cat_scores as $cat): ?>
								<tr>
									<td><strong><?php echo esc_html($cat->category_name); ?></strong></td>
									<td><?php echo esc_html($cat->subtotal); ?></td>
									<td><?php echo esc_html(number_format($cat->average, 2)); ?></td>
									<td class="ca-admin-summary">
										<?php echo esc_html(CA_Scoring::get_category_summary($cat->category_name, (float) $cat->average)); ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

			<?php endif; ?>

			<!-- All Answers -->
			<div class="ca-admin-card">
				<h2 class="ca-admin-card-title"><?php esc_html_e('Question Responses', 'custom-assessment'); ?></h2>
				<?php if (empty($answers)): ?>
					<p><?php esc_html_e('No answers recorded yet.', 'custom-assessment'); ?></p>
				<?php else: ?>
					<table class="wp-list-table widefat fixed ca-admin-table">
						<thead>
							<tr>
								<th class="ca-col-id"><?php esc_html_e('#', 'custom-assessment'); ?></th>
								<th><?php esc_html_e('Category', 'custom-assessment'); ?></th>
								<th><?php esc_html_e('Question', 'custom-assessment'); ?></th>
								<th class="ca-col-score"><?php esc_html_e('Answer', 'custom-assessment'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($flat_q as $q): ?>
								<tr>
									<td class="ca-col-id"><?php echo esc_html($q['index'] + 1); ?></td>
									<td><?php echo esc_html($q['category']); ?></td>
									<td><?php echo esc_html($q['text']); ?></td>
									<td class="ca-col-score">
										<?php if (isset($answers[$q['index']])): ?>
											<span class="ca-answer-pill ca-answer-pill--<?php echo esc_attr($answers[$q['index']]); ?>">
												<?php echo esc_html($answers[$q['index']]); ?>
											</span>
										<?php else: ?>
											<span class="ca-no-answer">—</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

		</div>
		<?php
	}

	/**
	 * Render questions page - displays all assessment questions.
	 */
	public function render_questions_page()
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'custom-assessment'));
		}

		$questions = CA_Questions::get_flat();
		$total_questions = CA_Questions::get_total_count();
		$categories = CA_Questions::get_categories();

		// Priority range for edit dropdown: 1..max (at least 5 for consistency with Add New Question).
		$priority_max = 0;
		foreach ($questions as $q) {
			if (isset($q['priority'])) {
				$priority_max = max($priority_max, (int) $q['priority']);
			}
		}
		$priority_end = max(5, (int) $priority_max);

		// Provide the full questions list to the admin search script.
		// This guarantees global searching across pagination.
		$all_questions_js = array();
		foreach ($questions as $q) {
			$question_index = isset($q['index']) ? (int) $q['index'] : null;
			$all_questions_js[] = array(
				'question_index' => (null === $question_index) ? 0 : $question_index,
				'number' => (null === $question_index) ? '0' : (string) ($question_index + 1),
				'category' => isset($q['category']) ? (string) $q['category'] : '',
				'priority' => isset($q['priority']) ? (string) $q['priority'] : '',
				'question' => isset($q['text']) ? (string) $q['text'] : '',
			);
		}

		$delete_question_nonce = wp_create_nonce('ca_delete_question_action');
		$delete_question_confirm = esc_js(
			__('Are you sure you want to delete this question? This action cannot be undone.', 'custom-assessment')
		);

		$edit_question_nonce = wp_create_nonce('ca_edit_question_action');

		// Calculate question statistics
		$priority_counts = array_count_values(array_column($questions, 'priority'));
		$category_counts = array_count_values(array_column($questions, 'category'));

		// Find most and least used categories
		$most_used_category = '';
		$most_used_count = 0;
		$least_used_category = '';
		$least_used_count = PHP_INT_MAX;

		foreach ($category_counts as $category => $count) {
			if ($count > $most_used_count) {
				$most_used_count = $count;
				$most_used_category = $category;
			}
			if ($count < $least_used_count) {
				$least_used_count = $count;
				$least_used_category = $category;
			}
		}

		// Find most and least used priorities
		$most_used_priority = '';
		$most_used_priority_count = 0;
		$least_used_priority = '';
		$least_used_priority_count = PHP_INT_MAX;

		foreach ($priority_counts as $priority => $count) {
			if ($count > $most_used_priority_count) {
				$most_used_priority_count = $count;
				$most_used_priority = $priority;
			}
			if ($count < $least_used_priority_count) {
				$least_used_priority_count = $count;
				$least_used_priority = $priority;
			}
		}

		// Pagination setup
		$per_page = 10;
		$current_page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
		$total_questions_count = count($questions);
		$total_pages = ceil($total_questions_count / $per_page);
		$offset = ($current_page - 1) * $per_page;
		$paged_questions = array_slice($questions, $offset, $per_page);

		?>
		<div class="wrap ca-admin-wrap">
			<script type="text/javascript">
				window.CA_ADMIN_QUESTIONS_ALL = <?php echo wp_json_encode($all_questions_js); ?>;
				window.CA_ADMIN_AJAX_URL = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
				window.CA_ADMIN_QUESTIONS_DELETE_NONCE = <?php echo wp_json_encode($delete_question_nonce); ?>;
				window.CA_ADMIN_QUESTIONS_DELETE_CONFIRM = <?php echo wp_json_encode($delete_question_confirm); ?>;
				window.CA_ADMIN_QUESTIONS_EDIT_NONCE = <?php echo wp_json_encode($edit_question_nonce); ?>;
				window.CA_ADMIN_QUESTIONS_CATEGORIES = <?php echo wp_json_encode($categories); ?>;
				window.CA_ADMIN_QUESTIONS_PRIORITY_MAX = <?php echo (int) $priority_end; ?>;
			</script>
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-format-chat"></span>
				<?php esc_html_e('Assessment Questions', 'custom-assessment'); ?>
			</h1>

			<!-- Basic Statistics -->
			<div class="ca-questions-stats-grid">
				<div class="ca-stat-card">
					<div class="ca-stat-value"><?php echo esc_html($total_questions); ?></div>
					<div class="ca-stat-label"><?php esc_html_e('Total Questions', 'custom-assessment'); ?></div>
				</div>

				<div class="ca-stat-card">
					<div class="ca-stat-value"><?php echo esc_html(count($categories)); ?></div>
					<div class="ca-stat-label"><?php esc_html_e('Categories', 'custom-assessment'); ?></div>
				</div>

				<div class="ca-stat-card">
					<div class="ca-stat-value"><?php echo esc_html($most_used_category); ?></div>
					<div class="ca-stat-label"><?php esc_html_e('Most Used Category', 'custom-assessment'); ?></div>
					<div class="ca-stat-sublabel"><?php echo esc_html($most_used_count . ' questions'); ?></div>
				</div>
			</div>

			<!-- Add Question Form -->
			<div class="ca-questions-actions">
				<div class="ca-question-form">
					<h3><?php esc_html_e('Add New Question', 'custom-assessment'); ?></h3>
					<form method="post" action="">
						<?php wp_nonce_field('ca_add_question_action', '_wpnonce'); ?>
						<input type="hidden" name="ca_action" value="add_question">
						<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px;">
							<div class="ca-form-field">
								<label for="question_category"><?php esc_html_e('Category', 'custom-assessment'); ?></label>
								<select id="question_category" name="question_category" required>
									<option value=""><?php esc_html_e('Select a category', 'custom-assessment'); ?></option>
									<?php foreach ($categories as $category): ?>
										<option value="<?php echo esc_attr($category); ?>"><?php echo esc_html($category); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="ca-form-field">
								<label for="question_priority"><?php esc_html_e('Priority', 'custom-assessment'); ?></label>
								<input type="number" id="question_priority" name="question_priority" required min="1" step="1"
									autocomplete="off" placeholder="" />
							</div>
							<div class="ca-form-field">
								<label for="question_text"><?php esc_html_e('Question Text', 'custom-assessment'); ?></label>
								<input type="text" id="question_text" name="question_text" class="ca-question-text-input"
									placeholder="<?php esc_attr_e('Enter the question text', 'custom-assessment'); ?>" required
									maxlength="500" autocomplete="off">
								<div class="ca-question-text-counter" aria-live="polite">
									<span id="ca-question-text-counter">0</span> / 500
								</div>
							</div>
						</div>
						<div class="ca-form-actions">
							<button type="submit" class="button button-primary ca-question-submit">
								<?php esc_html_e('Add Question', 'custom-assessment'); ?>
							</button>
						</div>
					</form>
				</div>
			</div>

			<br />

			<div class="ca-questions-search" style="text-align: end;">
				<div class="ca-search-field">
					<label for="ca-search-questions"><?php esc_html_e('Search Questions', 'custom-assessment'); ?></label>
					<input type="text" id="ca-search-questions"
						placeholder="<?php esc_attr_e('Search by number, category, or question text (minimum 3 characters)...', 'custom-assessment'); ?>"
						autocomplete="off">
					<div class="ca-search-count" style="display: none;">
						<span id="ca-search-results-count"></span>
					</div>
				</div>
			</div>

			<div class="ca-bulk-actions-bar" style="margin-top: 10px;">
				<button type="button" class="button button-secondary ca-bulk-edit-open" disabled>
					<?php esc_html_e('Bulk Edit', 'custom-assessment'); ?>
				</button>
				<span class="ca-bulk-selected-count">0 selected</span>
			</div>

			<div class="ca-bulk-edit-modal-overlay" id="ca-bulk-edit-modal-overlay" style="display:none;">
				<div class="ca-bulk-edit-modal">
					<h3><?php esc_html_e('Bulk Edit Questions', 'custom-assessment'); ?></h3>
					<form method="post" action="" id="ca-bulk-edit-form">
						<?php wp_nonce_field('ca_bulk_edit_question_action', '_wpnonce'); ?>
						<input type="hidden" name="ca_action" value="bulk_edit_questions">
						<input type="hidden" name="question_indexes_count" id="ca-bulk-question-indexes-count" value="0">

						<div class="ca-bulk-edit-fields">
							<div class="ca-bulk-field">
								<label for="ca-bulk-category"><?php esc_html_e('Category', 'custom-assessment'); ?></label>
								<select id="ca-bulk-category" name="bulk_category">
									<option value=""><?php esc_html_e('Keep current', 'custom-assessment'); ?></option>
									<?php foreach ($categories as $cat): ?>
										<option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="ca-bulk-field">
								<label for="ca-bulk-priority"><?php esc_html_e('Priority', 'custom-assessment'); ?></label>
								<input type="number" id="ca-bulk-priority" name="bulk_priority" min="1" step="1" placeholder="">
							</div>

							<div class="ca-bulk-field">
								<label for="ca-bulk-question-text"><?php esc_html_e('Question Text', 'custom-assessment'); ?></label>
								<textarea id="ca-bulk-question-text" name="bulk_question_text" rows="3" maxlength="500"
									placeholder="<?php esc_attr_e('Leave empty to keep current', 'custom-assessment'); ?>"></textarea>
							</div>
						</div>

						<div id="ca-bulk-selected-indexes"></div>

						<div class="ca-bulk-edit-actions">
							<button type="button" class="button ca-bulk-edit-cancel">
								<?php esc_html_e('Cancel', 'custom-assessment'); ?>
							</button>
							<button type="submit" class="button button-primary">
								<?php esc_html_e('Save Bulk Changes', 'custom-assessment'); ?>
							</button>
						</div>
					</form>
				</div>
			</div>

			<br />

			<?php if (isset($_GET['message']) && 'question_deleted' === $_GET['message']): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Question deleted successfully.', 'custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if (isset($_GET['message']) && 'question_added' === $_GET['message']): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Question added successfully.', 'custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if (isset($_GET['message']) && 'question_edited' === $_GET['message']): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Question updated successfully.', 'custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if (isset($_GET['message']) && 'question_edit_failed' === $_GET['message']): ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e('Unable to update this question.', 'custom-assessment'); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if (isset($_GET['message']) && 'priority_exists' === $_GET['message']): ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e('Priority already exists in this category. Please choose another number.', 'custom-assessment'); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if (isset($_GET['message']) && 'bulk_edit_success' === $_GET['message']): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Bulk edit applied successfully.', 'custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if (isset($_GET['message']) && 'bulk_edit_failed' === $_GET['message']): ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e('Bulk edit failed. Please select questions and try again.', 'custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if (empty($questions)): ?>
				<div class="ca-admin-empty">
					<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
					<p><?php esc_html_e('No questions found. Please check your assessment configuration.', 'custom-assessment'); ?>
					</p>
				</div>
			<?php else: ?>
				<table class="wp-list-table widefat fixed striped ca-admin-table">
					<thead>
						<tr>
							<th class="ca-col-id">
								<input type="checkbox" id="ca-bulk-select-all" class="ca-bulk-select-all">
								<?php esc_html_e('#', 'custom-assessment'); ?>
							</th>
							<th><?php esc_html_e('Category', 'custom-assessment'); ?></th>
							<th><?php esc_html_e('Priority', 'custom-assessment'); ?></th>
							<th><?php esc_html_e('Question', 'custom-assessment'); ?></th>
							<th><?php esc_html_e('Actions', 'custom-assessment'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($paged_questions as $q): ?>
							<tr>
								<td class="ca-col-id">
									<input type="checkbox" class="ca-question-select" value="<?php echo esc_attr($q['index']); ?>">
									<?php echo esc_html($q['index'] + 1); ?>
								</td>
								<td class="ca-col-category">
									<span class="ca-question-category-text" data-original="<?php echo esc_attr($q['category']); ?>">
										<?php echo esc_html($q['category']); ?>
									</span>
									<select class="ca-question-category-select" style="display: none;"
										form="ca-edit-question-form-<?php echo esc_attr($q['index']); ?>" name="new_category"
										data-original="<?php echo esc_attr($q['category']); ?>">
										<?php foreach ($categories as $cat): ?>
											<option value="<?php echo esc_attr($cat); ?>" <?php echo $cat === $q['category'] ? 'selected' : ''; ?>>
												<?php echo esc_html($cat); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td class="ca-col-priority">
									<span class="ca-question-priority-text" data-original="<?php echo esc_attr($q['priority']); ?>">
										<?php echo esc_html($q['priority']); ?>
									</span>
									<input type="number" class="ca-question-priority-input" style="display: none;"
										form="ca-edit-question-form-<?php echo esc_attr($q['index']); ?>" name="new_priority"
										value="<?php echo esc_attr($q['priority']); ?>" min="1" step="1" autocomplete="off"
										data-original="<?php echo esc_attr($q['priority']); ?>">
								</td>
								<td class="ca-col-question">
									<span class="ca-question-text-display" data-original="<?php echo esc_attr($q['text']); ?>">
										<?php echo esc_html($q['text']); ?>
									</span>
									<input type="text" class="ca-question-text-input" style="display: none;"
										form="ca-edit-question-form-<?php echo esc_attr($q['index']); ?>" name="new_question_text"
										value="<?php echo esc_attr($q['text']); ?>" maxlength="500" autocomplete="off"
										data-original="<?php echo esc_attr($q['text']); ?>">
								</td>
								<td class="ca-col-actions">
									<form method="post" action="" id="ca-edit-question-form-<?php echo esc_attr($q['index']); ?>"
										class="ca-question-edit-form" style="display: inline;">
										<?php wp_nonce_field('ca_edit_question_action', '_wpnonce'); ?>
										<input type="hidden" name="ca_action" value="edit_question">
										<input type="hidden" name="question_index" value="<?php echo esc_attr($q['index']); ?>">
										<button type="button" class="button button-small button-secondary ca-question-edit-btn"
											data-index="<?php echo esc_attr($q['index']); ?>">
											<?php esc_html_e('Edit', 'custom-assessment'); ?>
										</button>
										<button type="button" class="button button-small button-secondary ca-question-cancel-btn"
											style="display: none;">
											<?php esc_html_e('Cancel', 'custom-assessment'); ?>
										</button>
										<button type="submit" class="button button-small button-primary ca-question-save-btn"
											style="display: none;">
											<?php esc_html_e('Save', 'custom-assessment'); ?>
										</button>
									</form>
									<form method="post" style="display: inline;"
										onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to delete this question? This action cannot be undone.', 'custom-assessment')); ?>');">
										<?php wp_nonce_field('ca_delete_question_action', '_wpnonce'); ?>
										<input type="hidden" name="ca_action" value="delete_question">
										<input type="hidden" name="question_index" value="<?php echo esc_attr($q['index']); ?>">
										<button type="submit" class="button button-small button-secondary">
											<?php esc_html_e('Delete', 'custom-assessment'); ?>
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php echo esc_html($total_questions_count); ?> 			<?php esc_html_e('items', 'custom-assessment'); ?>
						</span>
						<?php if ($total_pages > 1): ?>
							<span class="pagination-links">
								<?php
								$base_url = admin_url('admin.php?page=custom-assessment-questions');
								$prev_disabled = $current_page <= 1 ? 'disabled' : '';
								$next_disabled = $current_page >= $total_pages ? 'disabled' : '';

								// Previous button
								echo '<a class="prev-page button ' . esc_attr($prev_disabled) . '" href="' . esc_url(add_query_arg('paged', max(1, $current_page - 1), $base_url)) . '">&laquo;</a>';

								// Page numbers (show up to 5 page numbers)
								$start_page = max(1, $current_page - 2);
								$end_page = min($total_pages, $start_page + 4);

								if ($start_page > 1) {
									echo '<a class="page-numbers" href="' . esc_url(add_query_arg('paged', 1, $base_url)) . '">1</a>';
									if ($start_page > 2) {
										echo '<span class="dots">…</span>';
									}
								}

								for ($i = $start_page; $i <= $end_page; $i++) {
									$active_class = ($i === $current_page) ? 'current' : '';
									echo '<a class="page-numbers ' . esc_attr($active_class) . '" href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '">' . esc_html($i) . '</a>';
								}

								if ($end_page < $total_pages) {
									if ($end_page < $total_pages - 1) {
										echo '<span class="dots">…</span>';
									}
									echo '<a class="page-numbers" href="' . esc_url(add_query_arg('paged', $total_pages, $base_url)) . '">' . esc_html($total_pages) . '</a>';
								}

								// Next button
								echo '<a class="next-page button ' . esc_attr($next_disabled) . '" href="' . esc_url(add_query_arg('paged', min($total_pages, $current_page + 1), $base_url)) . '">&raquo;</a>';
								?>
							</span>
						<?php endif; ?>
					</div>
					<br class="clear">
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render categories page - displays all assessment categories with CRUD operations.
	 */
	public function render_categories_page()
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'custom-assessment'));
		}

		// Handle form submissions
		if (isset($_POST['ca_action']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_categories_action')) {
			if ('add_category' === $_POST['ca_action'] && !empty($_POST['new_category'])) {
				$new_category = sanitize_text_field(wp_unslash($_POST['new_category']));
				if (!empty($new_category)) {
					// Check if category already exists
					$existing_categories = CA_Questions::get_categories();
					if (in_array($new_category, $existing_categories)) {
						$message = 'duplicate';
					} else {
						$this->add_category($new_category);
						$message = 'added';
					}
				}
			} elseif ('delete_category' === $_POST['ca_action'] && !empty($_POST['category_name'])) {
				$category_name = sanitize_text_field(wp_unslash($_POST['category_name']));
				if (!empty($category_name)) {
					$this->delete_category($category_name);
					$message = 'deleted';
				}
			}

			if (isset($message)) {
				$redirect_url = add_query_arg('message', $message, admin_url('admin.php?page=custom-assessment-categories'));
				wp_safe_redirect(esc_url_raw($redirect_url));
				exit;
			}
		}

		$categories = CA_Questions::get_categories();

		// Pagination setup
		$per_page = 10;
		$current_page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
		$total_categories = count($categories);
		$total_pages = ceil($total_categories / $per_page);
		$offset = ($current_page - 1) * $per_page;
		$paged_categories = array_slice($categories, $offset, $per_page);

		?>
		<div class="wrap ca-admin-wrap">
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-category"></span>
				<?php esc_html_e('Assessment Categories', 'custom-assessment'); ?>
			</h1>

			<script type="text/javascript">
				var ca_admin_data = {
					nonce: '<?php echo esc_js(wp_create_nonce("ca_edit_category_action")); ?>'
				};
			</script>

			<?php if (isset($_GET['message'])): ?>
				<?php if ('duplicate' === $_GET['message']): ?>
					<div class="notice notice-error is-dismissible">
						<p><?php esc_html_e('Error: Category already exists. Please choose a different name.', 'custom-assessment'); ?>
						</p>
					</div>
				<?php else: ?>
					<div class="notice notice-success is-dismissible">
						<p>
							<?php
							if ('added' === $_GET['message']) {
								esc_html_e('Category added successfully.', 'custom-assessment');
							} elseif ('deleted' === $_GET['message']) {
								esc_html_e('Category deleted successfully.', 'custom-assessment');
							} elseif ('edited' === $_GET['message']) {
								esc_html_e('Category updated successfully.', 'custom-assessment');
							}
							?>
						</p>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<!-- Basic Statistics -->
			<div class="ca-categories-stats-grid">
				<div class="ca-stat-card">
					<div class="ca-stat-value"><?php echo esc_html(count($categories)); ?></div>
					<div class="ca-stat-label"><?php esc_html_e('Total Categories', 'custom-assessment'); ?></div>
				</div>

				<?php
				// Calculate question counts for each category
				$questions = CA_Questions::get_flat();
				$category_counts = array_count_values(array_column($questions, 'category'));

				// Find most used category
				$most_used_category = '';
				$most_used_count = 0;
				$least_used_category = '';
				$least_used_count = PHP_INT_MAX;

				foreach ($category_counts as $category => $count) {
					if ($count > $most_used_count) {
						$most_used_count = $count;
						$most_used_category = $category;
					}
					if ($count < $least_used_count) {
						$least_used_count = $count;
						$least_used_category = $category;
					}
				}
				?>

				<div class="ca-stat-card">
					<div class="ca-stat-value"><?php echo esc_html($most_used_category); ?></div>
					<div class="ca-stat-label"><?php esc_html_e('Most Used Category', 'custom-assessment'); ?></div>
					<div class="ca-stat-sublabel"><?php echo esc_html($most_used_count . ' questions'); ?></div>
				</div>

				<div class="ca-stat-card">
					<div class="ca-stat-value"><?php echo esc_html($least_used_category); ?></div>
					<div class="ca-stat-label"><?php esc_html_e('Least Used Category', 'custom-assessment'); ?></div>
					<div class="ca-stat-sublabel"><?php echo esc_html($least_used_count . ' questions'); ?></div>
				</div>
			</div>

			<div class="ca-categories-header">
				<div class="ca-categories-stats">
					<span class="ca-stat-item">
						<strong><?php echo esc_html(count($categories)); ?></strong>
						<?php esc_html_e('Total Categories', 'custom-assessment'); ?>
					</span>
				</div>
			</div>

			<div class="ca-categories-actions">
				<div class="ca-category-form">
					<h3><?php esc_html_e('Add New Category', 'custom-assessment'); ?></h3>
					<form method="post" action="">
						<?php wp_nonce_field('ca_categories_action', '_wpnonce'); ?>
						<input type="hidden" name="ca_action" value="add_category">
						<div style="display: flex; gap: 10px; align-items: center; margin-bottom: 20px;">
							<div class="ca-form-field">
								<input type="text" id="new_category" name="new_category"
									placeholder="<?php esc_attr_e('Enter category name', 'custom-assessment'); ?>" required>
							</div>
							<div class="ca-form-actions">
								<button type="submit" class="button button-primary">
									<?php esc_html_e('Add Category', 'custom-assessment'); ?>
								</button>
							</div>
						</div>
					</form>
				</div>
			</div>

			<?php if (empty($categories)): ?>
				<div class="ca-admin-empty">
					<span class="dashicons dashicons-category" aria-hidden="true"></span>
					<p><?php esc_html_e('No categories found. Add your first category above.', 'custom-assessment'); ?></p>
				</div>
			<?php else: ?>
				<div class="tablenav top">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php echo esc_html($total_categories); ?> 			<?php esc_html_e('items', 'custom-assessment'); ?>
						</span>
						<?php if ($total_pages > 1): ?>
							<span class="pagination-links">
								<?php
								$base_url = admin_url('admin.php?page=custom-assessment-categories');
								$prev_disabled = $current_page <= 1 ? 'disabled' : '';
								$next_disabled = $current_page >= $total_pages ? 'disabled' : '';

								// Previous button
								echo '<a class="prev-page button ' . esc_attr($prev_disabled) . '" href="' . esc_url(add_query_arg('paged', max(1, $current_page - 1), $base_url)) . '">&laquo;</a>';

								// Page numbers (show up to 5 page numbers)
								$start_page = max(1, $current_page - 2);
								$end_page = min($total_pages, $start_page + 4);

								if ($start_page > 1) {
									echo '<a class="page-numbers" href="' . esc_url(add_query_arg('paged', 1, $base_url)) . '">1</a>';
									if ($start_page > 2) {
										echo '<span class="dots">…</span>';
									}
								}

								for ($i = $start_page; $i <= $end_page; $i++) {
									$active_class = ($i === $current_page) ? 'current' : '';
									echo '<a class="page-numbers ' . esc_attr($active_class) . '" href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '">' . esc_html($i) . '</a>';
								}

								if ($end_page < $total_pages) {
									if ($end_page < $total_pages - 1) {
										echo '<span class="dots">…</span>';
									}
									echo '<a class="page-numbers" href="' . esc_url(add_query_arg('paged', $total_pages, $base_url)) . '">' . esc_html($total_pages) . '</a>';
								}

								// Next button
								echo '<a class="next-page button ' . esc_attr($next_disabled) . '" href="' . esc_url(add_query_arg('paged', min($total_pages, $current_page + 1), $base_url)) . '">&raquo;</a>';
								?>
							</span>
						<?php endif; ?>
					</div>
					<br class="clear">
				</div>

				<table class="wp-list-table widefat fixed striped ca-admin-table">
					<thead>
						<tr>
							<th class="ca-col-id"><?php esc_html_e('#', 'custom-assessment'); ?></th>
							<th><?php esc_html_e('Category Name', 'custom-assessment'); ?></th>
							<th><?php esc_html_e('Questions Count', 'custom-assessment'); ?></th>
							<th><?php esc_html_e('Actions', 'custom-assessment'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$questions = CA_Questions::get_flat();
						$category_counts = array_count_values(array_column($questions, 'category'));

						foreach ($paged_categories as $index => $category):
							$global_index = $offset + $index;
							$count = isset($category_counts[$category]) ? $category_counts[$category] : 0;
							?>
							<tr>
								<td class="ca-col-id"><?php echo esc_html($global_index + 1); ?></td>
								<td>
									<strong class="ca-category-name" id="category-name-<?php echo esc_attr($global_index); ?>">
										<?php echo esc_html($category); ?>
									</strong>
									<input type="text" class="ca-category-input"
										id="category-input-<?php echo esc_attr($global_index); ?>"
										value="<?php echo esc_attr($category); ?>" style="display: none; width: 100%;"
										data-original="<?php echo esc_attr($category); ?>">
								</td>
								<td><?php echo esc_html($count); ?></td>
								<td>
									<button type="button" class="button button-small ca-edit-btn"
										data-index="<?php echo esc_attr($global_index); ?>"
										data-category="<?php echo esc_attr($category); ?>">
										<?php esc_html_e('Edit', 'custom-assessment'); ?>
									</button>
									<button type="button" class="button button-small ca-save-btn" style="display: none;"
										data-index="<?php echo esc_attr($global_index); ?>"
										data-category="<?php echo esc_attr($category); ?>">
										<?php esc_html_e('Save', 'custom-assessment'); ?>
									</button>
									<button type="button" class="button button-small button-secondary ca-category-cancel-btn"
										style="display: none;" data-index="<?php echo esc_attr($global_index); ?>"
										data-category="<?php echo esc_attr($category); ?>">
										<?php esc_html_e('Cancel', 'custom-assessment'); ?>
									</button>
									<form method="post" style="display: inline;"
										onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to delete this category? This will also delete all questions in this category.', 'custom-assessment')); ?>');">
										<?php wp_nonce_field('ca_categories_action', '_wpnonce'); ?>
										<input type="hidden" name="ca_action" value="delete_category">
										<input type="hidden" name="category_name" value="<?php echo esc_attr($category); ?>">
										<button type="submit" class="button button-small button-secondary">
											<?php esc_html_e('Delete', 'custom-assessment'); ?>
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php echo esc_html($total_categories); ?> 			<?php esc_html_e('items', 'custom-assessment'); ?>
						</span>
						<?php if ($total_pages > 1): ?>
							<span class="pagination-links">
								<?php
								// Previous button
								echo '<a class="prev-page button ' . esc_attr($prev_disabled) . '" href="' . esc_url(add_query_arg('paged', max(1, $current_page - 1), $base_url)) . '">&laquo;</a>';

								// Page numbers (show up to 5 page numbers)
								$start_page = max(1, $current_page - 2);
								$end_page = min($total_pages, $start_page + 4);

								if ($start_page > 1) {
									echo '<a class="page-numbers" href="' . esc_url(add_query_arg('paged', 1, $base_url)) . '">1</a>';
									if ($start_page > 2) {
										echo '<span class="dots">…</span>';
									}
								}

								for ($i = $start_page; $i <= $end_page; $i++) {
									$active_class = ($i === $current_page) ? 'current' : '';
									echo '<a class="page-numbers ' . esc_attr($active_class) . '" href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '">' . esc_html($i) . '</a>';
								}

								if ($end_page < $total_pages) {
									if ($end_page < $total_pages - 1) {
										echo '<span class="dots">…</span>';
									}
									echo '<a class="page-numbers" href="' . esc_url(add_query_arg('paged', $total_pages, $base_url)) . '">' . esc_html($total_pages) . '</a>';
								}

								// Next button
								echo '<a class="next-page button ' . esc_attr($next_disabled) . '" href="' . esc_url(add_query_arg('paged', min($total_pages, $current_page + 1), $base_url)) . '">&raquo;</a>';
								?>
							</span>
						<?php endif; ?>
					</div>
					<br class="clear">
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Add a new category to the questions configuration.
	 * 
	 * @param string $category_name
	 */
	private function add_category($category_name)
	{
		// Get existing custom categories
		$custom_categories = get_option('ca_custom_categories', array());

		// Add the new category if it doesn't already exist
		if (!in_array($category_name, $custom_categories)) {
			$custom_categories[] = $category_name;
			update_option('ca_custom_categories', $custom_categories);
		}
	}

	/**
	 * Delete a category from the questions configuration.
	 * 
	 * @param string $category_name
	 */
	private function delete_category($category_name)
	{
		// Get existing custom categories
		$custom_categories = get_option('ca_custom_categories', array());

		// Remove the category if it exists
		$key = array_search($category_name, $custom_categories);
		if ($key !== false) {
			unset($custom_categories[$key]);
			$custom_categories = array_values($custom_categories); // Re-index array
			update_option('ca_custom_categories', $custom_categories);
		}
	}

	/**
	 * Edit a category name in the questions configuration.
	 * 
	 * @param string $old_category
	 * @param string $new_category
	 */
	private function edit_category($old_category, $new_category)
	{
		// Get existing custom categories
		$custom_categories = get_option('ca_custom_categories', array());

		// Find and replace the category name
		$key = array_search($old_category, $custom_categories);
		if ($key !== false) {
			$custom_categories[$key] = $new_category;
			update_option('ca_custom_categories', $custom_categories);
		}
	}

	/**
	 * Delete a question from the questions configuration.
	 * 
	 * @param int $question_index
	 */
	private function delete_question($question_index)
	{
		// Get all questions to find which category and priority this question belongs to
		$all_questions = CA_Questions::get_all();
		$flat_questions = CA_Questions::get_flat();

		// Find the question details from the flat array
		if (!isset($flat_questions[$question_index])) {
			return; // Question not found
		}

		$question_to_delete = $flat_questions[$question_index];
		$category_to_delete = $question_to_delete['category'];
		$priority_to_delete = $question_to_delete['priority'];

		// Get existing custom questions
		$custom_questions = get_option('ca_custom_questions', array());

		// Find and remove the matching question from custom questions
		$found = false;
		foreach ($custom_questions as $key => $custom_question) {
			if (
				$custom_question['category'] === $category_to_delete &&
				$custom_question['priority'] === $priority_to_delete &&
				$custom_question['text'] === $question_to_delete['text']
			) {
				unset($custom_questions[$key]);
				$found = true;
				break;
			}
		}

		// Only update if we found and removed a custom question
		if ($found) {
			$custom_questions = array_values($custom_questions); // Re-index array
			update_option('ca_custom_questions', $custom_questions);
		}
	}

	/**
	 * Edit a question's category and text (updates custom questions only).
	 *
	 * @return bool True if edited, false if question is not found in custom questions.
	 */
	private function edit_question($question_index, $new_category, $new_question_text, $new_priority)
	{
		$flat_questions = CA_Questions::get_flat();
		if (!isset($flat_questions[$question_index])) {
			return false;
		}

		$question_to_edit = $flat_questions[$question_index];
		$original_category = isset($question_to_edit['category']) ? (string) $question_to_edit['category'] : '';
		$original_priority = isset($question_to_edit['priority']) ? (int) $question_to_edit['priority'] : 0;
		$original_text = isset($question_to_edit['text']) ? (string) $question_to_edit['text'] : '';

		$custom_questions = get_option('ca_custom_questions', array());

		$found = false;
		foreach ($custom_questions as $key => $custom_question) {
			$custom_category = isset($custom_question['category']) ? (string) $custom_question['category'] : '';
			$custom_priority = isset($custom_question['priority']) ? (int) $custom_question['priority'] : 0;
			$custom_text = isset($custom_question['text']) ? (string) $custom_question['text'] : '';

			if (
				$custom_category === $original_category &&
				$custom_priority === $original_priority &&
				$custom_text === $original_text
			) {
				$custom_questions[$key]['category'] = $new_category;
				$custom_questions[$key]['text'] = $new_question_text;
				$custom_questions[$key]['priority'] = (int) $new_priority;
				$found = true;
				break;
			}
		}

		if ($found) {
			update_option('ca_custom_questions', array_values($custom_questions));
			return true;
		}

		// If not found in custom questions, allow editing base (hardcoded) questions
		// by storing an override entry.
		$overrides = get_option('ca_question_overrides', array());
		if (!is_array($overrides)) {
			$overrides = array();
		}

		$overrides[(int) $question_index] = array(
			'category' => $new_category,
			'text' => $new_question_text,
			'priority' => (int) $new_priority,
		);

		update_option('ca_question_overrides', $overrides);

		return true;
	}

	/**
	 * Add a new question to the questions configuration.
	 * 
	 * @param string $question_text
	 * @param string $question_category
	 * @param int $question_priority
	 */
	private function add_question($question_text, $question_category, $question_priority)
	{
		// Get existing questions configuration
		$questions = get_option('ca_custom_questions', array());

		// Add the new question
		$new_question = array(
			'text' => $question_text,
			'category' => $question_category,
			'priority' => $question_priority
		);

		$questions[] = $new_question;
		update_option('ca_custom_questions', $questions);
	}
}

