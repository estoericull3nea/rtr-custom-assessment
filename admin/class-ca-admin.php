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
		add_action('admin_init', array($this, 'handle_logs_action'));
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
			__('Assessment Dashboard', 'rtr-custom-assessment'),
			__('Assessment', 'rtr-custom-assessment'),
			'manage_options',
			'custom-assessment-dashboard',
			array($this, 'render_dashboard_page'),
			'dashicons-chart-bar',
			56
		);

		add_submenu_page(
			'custom-assessment-dashboard',
			__('Dashboard', 'rtr-custom-assessment'),
			__('Dashboard', 'rtr-custom-assessment'),
			'manage_options',
			'custom-assessment-dashboard',
			array($this, 'render_dashboard_page')
		);

		add_submenu_page(
			'custom-assessment-dashboard',
			__('Submissions', 'rtr-custom-assessment'),
			__('Submissions', 'rtr-custom-assessment'),
			'manage_options',
			'custom-assessment-submissions',
			array($this, 'render_list_page')
		);

		add_submenu_page(
			'custom-assessment-dashboard',
			__('Questions', 'rtr-custom-assessment'),
			__('Questions', 'rtr-custom-assessment'),
			'manage_options',
			'custom-assessment-questions',
			array($this, 'render_questions_page')
		);

		add_submenu_page(
			'custom-assessment-dashboard',
			__('Categories', 'rtr-custom-assessment'),
			__('Categories', 'rtr-custom-assessment'),
			'manage_options',
			'custom-assessment-categories',
			array($this, 'render_categories_page')
		);

		add_submenu_page(
			'custom-assessment-dashboard',
			__('Logs', 'rtr-custom-assessment'),
			__('Logs', 'rtr-custom-assessment'),
			'manage_options',
			'custom-assessment-logs',
			array($this, 'render_logs_page')
		);
	}

	/**
	 * Handle logs actions.
	 */
	public function handle_logs_action()
	{
		if (!isset($_GET['page']) || 'custom-assessment-logs' !== $_GET['page']) {
			return;
		}

		if (!current_user_can('manage_options')) {
			return;
		}

		if (
			isset($_POST['ca_action'], $_POST['_wpnonce']) &&
			'clear_logs' === sanitize_text_field(wp_unslash($_POST['ca_action'])) &&
			wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_clear_logs_action')
		) {
			CA_Logger::clear_logs();
			CA_Logger::log('admin_clear_logs', 'success', 'Logs cleared by admin.');
			$redirect_url = add_query_arg('message', 'logs_cleared', admin_url('admin.php?page=custom-assessment-logs'));
			wp_safe_redirect(esc_url_raw($redirect_url));
			exit;
		}
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
			$delete_id = absint($_GET['id']);
			if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'ca_delete_submission_' . absint($_GET['id']))) {
				CA_Logger::log('admin_delete_submission', 'error', 'Security check failed.', array('submission_id' => $delete_id));
				wp_die(esc_html__('Security check failed.', 'rtr-custom-assessment'));
			}

			$deleted = CA_Database::delete_submission($delete_id);
			CA_Logger::log(
				'admin_delete_submission',
				$deleted ? 'success' : 'error',
				$deleted ? 'Submission deleted.' : 'Failed to delete submission.',
				array('submission_id' => $delete_id)
			);
			$current_request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
			$redirect_url = remove_query_arg(array('action', 'id', '_wpnonce'), $current_request_uri);
			$redirect_url = add_query_arg('message', 'deleted', $redirect_url);
			wp_safe_redirect(esc_url_raw($redirect_url));
			exit;
		}

		if (
			isset($_POST['ca_action'], $_POST['_wpnonce']) &&
			'bulk_delete_submissions' === sanitize_text_field(wp_unslash($_POST['ca_action'])) &&
			wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_bulk_delete_submissions_action')
		) {
			$bulk_action_top = isset($_POST['bulk_action']) ? sanitize_text_field(wp_unslash($_POST['bulk_action'])) : '';
			$bulk_action_bottom = isset($_POST['bulk_action_bottom']) ? sanitize_text_field(wp_unslash($_POST['bulk_action_bottom'])) : '';
			$delete_selected = ('delete' === $bulk_action_top || 'delete' === $bulk_action_bottom);

			if (!$delete_selected) {
				CA_Logger::log('admin_bulk_delete_submissions', 'error', 'No bulk delete action selected.');
				$current_request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
				$redirect_url = remove_query_arg(array('action', 'id', '_wpnonce'), $current_request_uri);
				$redirect_url = add_query_arg('message', 'bulk_delete_none_selected', $redirect_url);
				wp_safe_redirect(esc_url_raw($redirect_url));
				exit;
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below.
			$ids_raw = isset($_POST['submission_ids']) ? wp_unslash($_POST['submission_ids']) : array();
			$ids_raw = is_array($ids_raw) ? $ids_raw : array($ids_raw);
			$ids_raw = array_map('sanitize_text_field', $ids_raw);
			$ids = array_map('absint', $ids_raw);
			$ids = array_values(array_filter($ids, fn($id) => $id > 0));

			if (empty($ids)) {
				CA_Logger::log('admin_bulk_delete_submissions', 'error', 'No submissions selected.');
				$current_request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
				$redirect_url = remove_query_arg(array('action', 'id', '_wpnonce'), $current_request_uri);
				$redirect_url = add_query_arg('message', 'bulk_delete_none_selected', $redirect_url);
				wp_safe_redirect(esc_url_raw($redirect_url));
				exit;
			}

			$deleted_count = 0;
			foreach ($ids as $submission_id) {
				$deleted = CA_Database::delete_submission($submission_id);
				if ($deleted) {
					$deleted_count++;
				}
			}
			CA_Logger::log(
				'admin_bulk_delete_submissions',
				$deleted_count > 0 ? 'success' : 'error',
				$deleted_count > 0 ? 'Bulk delete completed.' : 'Bulk delete removed 0 submissions.',
				array('selected_count' => count($ids), 'deleted_count' => $deleted_count)
			);

			$current_request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
			$redirect_url = remove_query_arg(array('action', 'id', '_wpnonce'), $current_request_uri);
			$redirect_url = add_query_arg(array(
				'message' => 'bulk_deleted',
				'deleted_count' => $deleted_count,
			), $redirect_url);
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

		if (isset($_GET['action']) && 'export_all' === $_GET['action'] && !empty($_GET['format'])) {
			if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'ca_export_all_submissions')) {
				CA_Logger::log('admin_export_all', 'error', 'Security check failed.');
				wp_die(esc_html__('Security check failed.', 'rtr-custom-assessment'));
			}

			$format = sanitize_text_field(wp_unslash($_GET['format']));
			$all_submissions = CA_Database::get_all_submissions();

			if ('csv' === $format) {
				CA_Logger::log('admin_export_all', 'success', 'Export all CSV requested.');
				$this->export_all_as_csv($all_submissions);
			} elseif ('json' === $format) {
				CA_Logger::log('admin_export_all', 'success', 'Export all JSON requested.');
				$this->export_all_as_json($all_submissions);
			} else {
				CA_Logger::log('admin_export_all', 'error', 'Invalid export format.', array('format' => $format));
				wp_die(esc_html__('Invalid export format.', 'rtr-custom-assessment'));
			}

			exit;
		}

		if (isset($_GET['action']) && 'export' === $_GET['action'] && !empty($_GET['id']) && !empty($_GET['format'])) {
			if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'ca_export_submission_' . absint($_GET['id']))) {
				CA_Logger::log('admin_export_submission', 'error', 'Security check failed.', array('submission_id' => absint($_GET['id'])));
				wp_die(esc_html__('Security check failed.', 'rtr-custom-assessment'));
			}

			$submission_id = absint($_GET['id']);
			$format = sanitize_text_field(wp_unslash($_GET['format']));
			$submission = CA_Database::get_submission($submission_id);

			if (!$submission || 'completed' !== $submission->status) {
				CA_Logger::log('admin_export_submission', 'error', 'Submission not exportable.', array('submission_id' => $submission_id));
				wp_die(esc_html__('Only completed submissions can be exported.', 'rtr-custom-assessment'));
			}

			if ('csv' === $format) {
				CA_Logger::log('admin_export_submission', 'success', 'CSV export requested.', array('submission_id' => $submission_id));
				$this->export_as_csv($submission_id, $submission);
			} elseif ('pdf' === $format) {
				CA_Logger::log('admin_export_submission', 'success', 'PDF export requested.', array('submission_id' => $submission_id));
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
				CA_Logger::log('admin_send_email', 'error', 'Security check failed.', array('submission_id' => absint($_GET['id'])));
				wp_die(esc_html__('Security check failed.', 'rtr-custom-assessment'));
			}

			$submission_id = absint($_GET['id']);
			$submission = CA_Database::get_submission($submission_id);

			if (!$submission) {
				CA_Logger::log('admin_send_email', 'error', 'Submission not found.', array('submission_id' => $submission_id));
				wp_die(esc_html__('Submission not found.', 'rtr-custom-assessment'));
			}

			if ('completed' !== $submission->status) {
				CA_Logger::log('admin_send_email', 'error', 'Submission is not completed.', array('submission_id' => $submission_id));
				wp_die(esc_html__('Only completed submissions can have emails sent.', 'rtr-custom-assessment'));
			}

			// Send the email using the existing mailer
			$sent = CA_Mailer::send_results_email($submission_id);

			$current_request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
			$redirect_url = remove_query_arg(array('action', 'id', '_wpnonce'), $current_request_uri);
			if ($sent) {
				CA_Logger::log('admin_send_email', 'success', 'Email sent.', array('submission_id' => $submission_id));
				$redirect_url = add_query_arg('message', 'email_sent', $redirect_url);
			} else {
				CA_Logger::log('admin_send_email', 'error', 'Failed to send email.', array('submission_id' => $submission_id));
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

		if (isset($_POST['ca_action'], $_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_categories_action')) {
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

		if (
			isset($_POST['ca_action'], $_POST['_wpnonce'], $_POST['old_category_name'], $_POST['new_category_name']) &&
			'edit_category' === $_POST['ca_action'] &&
			wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_edit_category_action')
		) {
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

		if (
			isset($_POST['ca_action'], $_POST['_wpnonce'], $_POST['question_index']) &&
			'delete_question' === $_POST['ca_action'] &&
			wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_delete_question_action')
		) {
			$question_index = absint($_POST['question_index']);
			if ($question_index >= 0) {
				$this->delete_question($question_index);
				$message = 'question_deleted';

				$redirect_url = add_query_arg('message', $message, admin_url('admin.php?page=custom-assessment-questions'));
				wp_safe_redirect(esc_url_raw($redirect_url));
				exit;
			}
		}

		if (
			isset($_POST['ca_action'], $_POST['_wpnonce'], $_POST['question_index']) &&
			'edit_question' === $_POST['ca_action'] &&
			wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_edit_question_action')
		) {
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

		if (
			isset($_POST['ca_action'], $_POST['_wpnonce']) &&
			'bulk_edit_questions' === $_POST['ca_action'] &&
			wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_bulk_edit_question_action')
		) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized immediately below via sanitize_text_field + absint.
			$indexes_raw = isset($_POST['question_indexes']) ? wp_unslash($_POST['question_indexes']) : array();
			$indexes_raw = is_array($indexes_raw) ? $indexes_raw : array($indexes_raw);
			$indexes_raw = array_map('sanitize_text_field', $indexes_raw);
			$indexes = array_map('absint', $indexes_raw);
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

		if (
			isset($_POST['ca_action'], $_POST['_wpnonce'], $_POST['question_text'], $_POST['question_category'], $_POST['question_priority']) &&
			'add_question' === $_POST['ca_action'] &&
			wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_add_question_action')
		) {
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
				array('message' => esc_html__('Priority already exists in this category. Please choose another number.', 'rtr-custom-assessment')),
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

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing a stream opened on php://output.
		fclose($output);
	}

	/**
	 * Export all submissions as CSV.
	 *
	 * @param array $submissions List of submission objects.
	 */
	private function export_all_as_csv($submissions)
	{
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="all_submissions_' . gmdate('Ymd_His') . '.csv"');

		$flat_questions = CA_Questions::get_flat();
		$output = fopen('php://output', 'w');
		$header = array(
			'ID',
			'First Name',
			'Last Name',
			'Email',
			'Phone',
			'Job Title',
			'Status',
			'Total Score',
			'Average Score',
			'Created At',
			'Updated At',
		);

		// Add per-question answer columns so one CSV includes complete response details.
		foreach ($flat_questions as $idx => $question) {
			$header[] = sprintf(
				'Q%d: %s',
				$idx + 1,
				isset($question['text']) ? (string) $question['text'] : ''
			);
		}

		// Add category score summary columns.
		$header[] = 'Category Scores (name:subtotal:average)';
		$header[] = 'Category Summaries (name:summary)';

		fputcsv($output, $header);

		foreach ($submissions as $submission) {
			$row = array(
				isset($submission->id) ? $submission->id : '',
				isset($submission->first_name) ? $submission->first_name : '',
				isset($submission->last_name) ? $submission->last_name : '',
				isset($submission->email) ? $submission->email : '',
				isset($submission->phone) ? $submission->phone : '',
				isset($submission->job_title) ? $submission->job_title : '',
				isset($submission->status) ? $submission->status : '',
				isset($submission->total_score) ? $submission->total_score : '',
				isset($submission->average_score) ? $submission->average_score : '',
				isset($submission->created_at) ? $submission->created_at : '',
				isset($submission->updated_at) ? $submission->updated_at : '',
			);

			$submission_id = isset($submission->id) ? absint($submission->id) : 0;
			$answers_map = $submission_id > 0 ? CA_Database::get_answers($submission_id) : array();
			foreach ($flat_questions as $idx => $question) {
				$answer = isset($answers_map[$idx]) ? $answers_map[$idx] : '';
				$row[] = '' !== $answer ? $answer : 'No answer';
			}

			$category_scores = $submission_id > 0 ? CA_Database::get_category_scores($submission_id) : array();
			$scores_summary = array();
			$text_summary = array();
			foreach ($category_scores as $cat) {
				$cat_name = isset($cat->category_name) ? (string) $cat->category_name : '';
				$cat_subtotal = isset($cat->subtotal) ? (int) $cat->subtotal : 0;
				$cat_average = isset($cat->average) ? (float) $cat->average : 0.0;
				$scores_summary[] = $cat_name . ':' . $cat_subtotal . ':' . number_format($cat_average, 2);
				$text_summary[] = $cat_name . ':' . CA_Scoring::get_category_summary($cat_name, $cat_average);
			}
			$row[] = implode(' | ', $scores_summary);
			$row[] = implode(' | ', $text_summary);

			fputcsv($output, $row);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing a stream opened on php://output.
		fclose($output);
	}

	/**
	 * Export all submissions as JSON, including answers and category scores.
	 *
	 * @param array $submissions List of submission objects.
	 */
	private function export_all_as_json($submissions)
	{
		$payload = array(
			'exported_at' => gmdate('c'),
			'total_submissions' => is_array($submissions) ? count($submissions) : 0,
			'submissions' => array(),
		);

		if (is_array($submissions)) {
			foreach ($submissions as $submission) {
				$submission_id = isset($submission->id) ? absint($submission->id) : 0;
				$payload['submissions'][] = array(
					'id' => $submission_id,
					'first_name' => isset($submission->first_name) ? (string) $submission->first_name : '',
					'last_name' => isset($submission->last_name) ? (string) $submission->last_name : '',
					'email' => isset($submission->email) ? (string) $submission->email : '',
					'phone' => isset($submission->phone) ? (string) $submission->phone : '',
					'job_title' => isset($submission->job_title) ? (string) $submission->job_title : '',
					'status' => isset($submission->status) ? (string) $submission->status : '',
					'total_score' => isset($submission->total_score) ? (int) $submission->total_score : 0,
					'average_score' => isset($submission->average_score) ? (float) $submission->average_score : 0,
					'created_at' => isset($submission->created_at) ? (string) $submission->created_at : '',
					'updated_at' => isset($submission->updated_at) ? (string) $submission->updated_at : '',
					'answers' => $submission_id > 0 ? CA_Database::get_answers($submission_id) : array(),
					'category_scores' => $submission_id > 0 ? CA_Database::get_category_scores($submission_id) : array(),
				);
			}
		}

		header('Content-Type: application/json; charset=utf-8');
		header('Content-Disposition: attachment; filename="all_submissions_' . gmdate('Ymd_His') . '.json"');
		echo wp_json_encode($payload, JSON_PRETTY_PRINT);
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
		$pdf = new Rtr_Custom_Assessment_Pdf();
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
			wp_die(esc_html__('You do not have permission to view this page.', 'rtr-custom-assessment'));
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
				<?php esc_html_e('Assessment Dashboard', 'rtr-custom-assessment'); ?>
			</h1>

			<?php if (!$smtp_configured): ?>
				<div class="notice notice-error is-dismissible">
					<p><strong><?php esc_html_e('Warning: No SMTP configuration detected.', 'rtr-custom-assessment'); ?></strong>
					</p>
					<p><?php esc_html_e('Email notifications for completed assessments may not work properly. Please configure an SMTP plugin to ensure emails are delivered successfully.', 'rtr-custom-assessment'); ?>
					</p>
					<p><em><?php esc_html_e('Recommended plugins: WP Mail SMTP, Easy WP SMTP, Post SMTP Mailer, or similar.', 'rtr-custom-assessment'); ?></em>
					</p>
				</div>
			<?php endif; ?>

			<div class="ca-dashboard-grid">
				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html($total_submissions); ?></div>
					<div class="ca-dashboard-card-label"><?php esc_html_e('Total Submissions', 'rtr-custom-assessment'); ?>
					</div>
				</div>

				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html($completed_count); ?></div>
					<div class="ca-dashboard-card-label"><?php esc_html_e('Completed', 'rtr-custom-assessment'); ?></div>
				</div>

				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html($in_progress_count); ?></div>
					<div class="ca-dashboard-card-label"><?php esc_html_e('In Progress', 'rtr-custom-assessment'); ?></div>
				</div>

				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html($completion_rate); ?>%</div>
					<div class="ca-dashboard-card-label"><?php esc_html_e('Completion Rate', 'rtr-custom-assessment'); ?></div>
				</div>

				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html(number_format($avg_total_score, 1)); ?></div>
					<div class="ca-dashboard-card-label"><?php esc_html_e('Avg Total Score', 'rtr-custom-assessment'); ?></div>
				</div>

				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html(number_format($avg_average_score, 2)); ?>/5
					</div>
					<div class="ca-dashboard-card-label"><?php esc_html_e('Avg Score Per Q', 'rtr-custom-assessment'); ?></div>
				</div>
			</div>

			<div class="ca-dashboard-section">
				<h2><?php esc_html_e('Recent Submissions', 'rtr-custom-assessment'); ?></h2>

				<?php if (empty($recent_submissions)): ?>
					<p><?php esc_html_e('No submissions yet.', 'rtr-custom-assessment'); ?></p>
				<?php else: ?>
					<table class="wp-list-table widefat fixed striped ca-admin-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e('Name', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Email', 'rtr-custom-assessment'); ?></th>
								<th scope="col" class="ca-col-score"><?php esc_html_e('Score', 'rtr-custom-assessment'); ?></th>
								<th scope="col" class="ca-col-status"><?php esc_html_e('Status', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Date', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Action', 'rtr-custom-assessment'); ?></th>
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
											<?php esc_html_e('View', 'rtr-custom-assessment'); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p>
						<a href="<?php echo esc_url(add_query_arg(array('page' => 'custom-assessment-submissions'), admin_url('admin.php'))); ?>"
							class="button button-primary">
							<?php esc_html_e('View All Submissions', 'rtr-custom-assessment'); ?>
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
			wp_die(esc_html__('You do not have permission to view this page.', 'rtr-custom-assessment'));
		}

		// Check SMTP configuration - show error if no SMTP detected
		// This ensures users are aware they need SMTP for email functionality
		$smtp_configured = $this->is_smtp_configured();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only use of sanitized query params for UI state.
		$list_view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
		$list_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
		$list_message = isset($_GET['message']) ? sanitize_key(wp_unslash($_GET['message'])) : '';
		$current_page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Detail view
		if ('detail' === $list_view && $list_id > 0) {
			$this->render_detail_page($list_id);
			return;
		}

		// List view
		$all_submissions = CA_Database::get_all_submissions();

		// Pagination setup
		$per_page = 10;
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
				<?php esc_html_e('Assessment Submissions', 'rtr-custom-assessment'); ?>
			</h1>

			<?php if (!$smtp_configured): ?>
				<div class="notice notice-error is-dismissible">
					<p><strong><?php esc_html_e('Warning: No SMTP configuration detected.', 'rtr-custom-assessment'); ?></strong>
					</p>
					<p><?php esc_html_e('Email notifications for completed assessments may not work properly. Please configure an SMTP plugin to ensure emails are delivered successfully.', 'rtr-custom-assessment'); ?>
					</p>
					<p><em><?php esc_html_e('Recommended plugins: WP Mail SMTP, Easy WP SMTP, Post SMTP Mailer, or similar.', 'rtr-custom-assessment'); ?></em>
					</p>
				</div>
			<?php endif; ?>

			<?php if ('deleted' === $list_message): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Submission deleted successfully.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if ('bulk_delete_none_selected' === $list_message): ?>
				<div class="notice notice-warning is-dismissible">
					<p><?php esc_html_e('No submissions selected for bulk delete.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if ('bulk_deleted' === $list_message): ?>
				<?php
				// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only use of sanitized query arg for notice display.
				$deleted_count_notice = isset($_GET['deleted_count']) ? absint($_GET['deleted_count']) : 0;
				// phpcs:enable WordPress.Security.NonceVerification.Recommended
				?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php
						printf(
							esc_html(
								_n(
									'%d submission deleted successfully.',
									'%d submissions deleted successfully.',
									$deleted_count_notice,
									'rtr-custom-assessment'
								)
							),
							esc_html($deleted_count_notice)
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ('email_sent' === $list_message): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Assessment results email sent successfully to the customer.', 'rtr-custom-assessment'); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ('email_failed' === $list_message): ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e('Failed to send assessment results email. Please check your SMTP configuration.', 'rtr-custom-assessment'); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if (empty($all_submissions)): ?>
				<div class="ca-admin-empty">
					<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					<p><?php esc_html_e('No submissions yet. Share the assessment shortcode [custom_assessment] on any page.', 'rtr-custom-assessment'); ?>
					</p>
				</div>
			<?php else: ?>

				<!-- Basic Statistics -->
				<div class="ca-questions-stats-grid">
					<div class="ca-stat-card">
						<div class="ca-stat-value"><?php echo esc_html($total_submissions_count); ?></div>
						<div class="ca-stat-label"><?php esc_html_e('Total Submissions', 'rtr-custom-assessment'); ?></div>
					</div>

					<div class="ca-stat-card">
						<div class="ca-stat-value"><?php echo esc_html($completed_count); ?></div>
						<div class="ca-stat-label"><?php esc_html_e('Completed', 'rtr-custom-assessment'); ?></div>
					</div>

					<div class="ca-stat-card">
						<div class="ca-stat-value"><?php echo esc_html($active_count); ?></div>
						<div class="ca-stat-label"><?php esc_html_e('In Progress', 'rtr-custom-assessment'); ?></div>
					</div>

					<div class="ca-stat-card">
						<div class="ca-stat-value"><?php echo esc_html(number_format($completed_avg, 2)); ?></div>
						<div class="ca-stat-label"><?php esc_html_e('Avg Score (Completed)', 'rtr-custom-assessment'); ?></div>
						<div class="ca-stat-sublabel">
							<?php esc_html_e('Latest submission: ', 'rtr-custom-assessment'); ?>
							<?php echo esc_html($latest_created_display); ?>
						</div>
					</div>
				</div>

				<div class="ca-questions-search" style="text-align: end;">
					<div style="margin-bottom: 10px;">
						<?php $export_all_csv_url = add_query_arg(array('page' => 'custom-assessment-submissions', 'action' => 'export_all', 'format' => 'csv', '_wpnonce' => wp_create_nonce('ca_export_all_submissions')), admin_url('admin.php')); ?>
						<a href="<?php echo esc_url($export_all_csv_url); ?>" class="button button-secondary">
							<?php esc_html_e('Export All CSV', 'rtr-custom-assessment'); ?>
						</a>
						<?php $export_all_json_url = add_query_arg(array('page' => 'custom-assessment-submissions', 'action' => 'export_all', 'format' => 'json', '_wpnonce' => wp_create_nonce('ca_export_all_submissions')), admin_url('admin.php')); ?>
						<a href="<?php echo esc_url($export_all_json_url); ?>" class="button button-secondary">
							<?php esc_html_e('Export All JSON', 'rtr-custom-assessment'); ?>
						</a>
					</div>

					<div class="ca-search-field">
						<label
							for="ca-search-submissions"><?php esc_html_e('Search Submissions', 'rtr-custom-assessment'); ?></label>
						<input type="text" id="ca-search-submissions"
							placeholder="<?php esc_attr_e('Search by ID, name, email, phone, job title, score, or status (minimum 3 characters)...', 'rtr-custom-assessment'); ?>"
							autocomplete="off">
						<div class="ca-search-count" style="display: none;">
							<span id="ca-search-results-count"></span>
						</div>
					</div>
				</div>

				<br />

				<form method="post" action="">
					<?php wp_nonce_field('ca_bulk_delete_submissions_action', '_wpnonce'); ?>
					<input type="hidden" name="ca_action" value="bulk_delete_submissions">

					<div class="tablenav top">
						<div class="alignleft actions bulkactions">
							<label for="bulk-action-selector-top"
								class="screen-reader-text"><?php esc_html_e('Select bulk action', 'rtr-custom-assessment'); ?></label>
							<select name="bulk_action" id="bulk-action-selector-top">
								<option value="-1"><?php esc_html_e('Bulk actions', 'rtr-custom-assessment'); ?></option>
								<option value="delete"><?php esc_html_e('Delete', 'rtr-custom-assessment'); ?></option>
							</select>
							<input type="submit" class="button action"
								value="<?php esc_attr_e('Apply', 'rtr-custom-assessment'); ?>"
								onclick="if(document.getElementById('bulk-action-selector-top').value !== 'delete'){return false;} return confirm('<?php echo esc_js(__('Are you sure you want to delete the selected submissions? This action cannot be undone.', 'rtr-custom-assessment')); ?>');">
						</div>
						<br class="clear">
					</div>

					<table class="wp-list-table widefat fixed striped ca-admin-table">
						<thead>
							<tr>
								<td scope="col" class="manage-column column-cb check-column">
									<label class="screen-reader-text"
										for="ca-submissions-select-all"><?php esc_html_e('Select all submissions', 'rtr-custom-assessment'); ?></label>
									<input type="checkbox" id="ca-submissions-select-all">
								</td>
								<th scope="col" class="ca-col-id"><?php esc_html_e('#', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Name', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Email', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Phone', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Job Title', 'rtr-custom-assessment'); ?></th>
								<th scope="col" class="ca-col-score"><?php esc_html_e('Total Score', 'rtr-custom-assessment'); ?>
								</th>
								<th scope="col" class="ca-col-score"><?php esc_html_e('Average', 'rtr-custom-assessment'); ?></th>
								<th scope="col" class="ca-col-status"><?php esc_html_e('Status', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Date', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Actions', 'rtr-custom-assessment'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($submissions as $sub): ?>
								<tr>
									<th scope="row" class="check-column">
										<input type="checkbox" class="ca-submission-checkbox" name="submission_ids[]"
											value="<?php echo esc_attr($sub->id); ?>">
									</th>
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
											<?php esc_html_e('View', 'rtr-custom-assessment'); ?>
										</a>
										<?php if ('completed' === $sub->status): ?>
											<div class="ca-export-dropdown-wrapper">
												<div class="ca-export-menu ca-export-dropdown"
													id="export-<?php echo esc_attr($sub->id); ?>">
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
													<?php esc_html_e('Export', 'rtr-custom-assessment'); ?> ▼
												</button>
											</div>

											<?php $email_url = add_query_arg(array('page' => 'custom-assessment-submissions', 'action' => 'send_email', 'id' => $sub->id, '_wpnonce' => wp_create_nonce('ca_send_email_' . $sub->id)), admin_url('admin.php')); ?>
											<a href="<?php echo esc_url($email_url); ?>" class="button button-small"
												onclick="return confirm('<?php echo esc_js(__('Are you sure you want to resend the assessment results email to this customer?', 'rtr-custom-assessment')); ?>');">
												<?php esc_html_e('Resend Email', 'rtr-custom-assessment'); ?>
											</a>
										<?php endif; ?>
										<?php $delete_url = add_query_arg(array('page' => 'custom-assessment-submissions', 'action' => 'delete', 'id' => $sub->id, '_wpnonce' => wp_create_nonce('ca_delete_submission_' . $sub->id)), admin_url('admin.php')); ?>
										<a href="<?php echo esc_url($delete_url); ?>" class="button button-small"
											onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this submission? This action cannot be undone.', 'rtr-custom-assessment')); ?>');">
											<?php esc_html_e('Delete', 'rtr-custom-assessment'); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<div class="tablenav bottom">
						<div class="alignleft actions bulkactions">
							<label for="bulk-action-selector-bottom"
								class="screen-reader-text"><?php esc_html_e('Select bulk action', 'rtr-custom-assessment'); ?></label>
							<select name="bulk_action_bottom" id="bulk-action-selector-bottom">
								<option value="-1"><?php esc_html_e('Bulk actions', 'rtr-custom-assessment'); ?></option>
								<option value="delete"><?php esc_html_e('Delete', 'rtr-custom-assessment'); ?></option>
							</select>
							<input type="submit" class="button action"
								value="<?php esc_attr_e('Apply', 'rtr-custom-assessment'); ?>"
								onclick="var top=document.getElementById('bulk-action-selector-top'); var bottom=document.getElementById('bulk-action-selector-bottom'); var selected=(top&&top.value==='delete')||(bottom&&bottom.value==='delete'); if(!selected){return false;} return confirm('<?php echo esc_js(__('Are you sure you want to delete the selected submissions? This action cannot be undone.', 'rtr-custom-assessment')); ?>');">
						</div>
						<br class="clear">
					</div>
				</form>

				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php echo esc_html($total_submissions_count); ?>
							<?php esc_html_e('submissions', 'rtr-custom-assessment'); ?>
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

				<script>
					(function () {
						var selectAll = document.getElementById('ca-submissions-select-all');
						if (!selectAll) return;
						selectAll.addEventListener('change', function () {
							var items = document.querySelectorAll('.ca-submission-checkbox');
							for (var i = 0; i < items.length; i++) {
								items[i].checked = !!selectAll.checked;
							}
						});
					})();
				</script>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render logs page.
	 */
	public function render_logs_page()
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'rtr-custom-assessment'));
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only query params for UI state.
		$message = isset($_GET['message']) ? sanitize_key(wp_unslash($_GET['message'])) : '';
		$current_page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
		$search_q = isset($_GET['log_search']) ? sanitize_text_field(wp_unslash($_GET['log_search'])) : '';
		$filter_status = isset($_GET['log_status']) ? sanitize_key(wp_unslash($_GET['log_status'])) : '';
		$filter_action = isset($_GET['log_action']) ? sanitize_text_field(wp_unslash($_GET['log_action'])) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$all_logs = array_reverse(CA_Logger::get_logs());

		// Build unique actions list for filter dropdown.
		$actions_list = array();
		foreach ($all_logs as $entry) {
			if (isset($entry['action']) && $entry['action'] !== '') {
				$actions_list[$entry['action']] = true;
			}
		}
		$actions_list = array_keys($actions_list);
		sort($actions_list);

		// Filter logs by status/action/search.
		$logs = array_filter($all_logs, function ($entry) use ($filter_status, $filter_action, $search_q) {
			if ($filter_status && isset($entry['status']) && (string) $entry['status'] !== (string) $filter_status) {
				return false;
			}
			if ($filter_action && isset($entry['action']) && (string) $entry['action'] !== (string) $filter_action) {
				return false;
			}
			if ($search_q !== '') {
				$haystack = wp_json_encode($entry);
				if (false === stripos((string) $haystack, (string) $search_q)) {
					return false;
				}
			}
			return true;
		});

		// Pagination
		$per_page = 10;
		$total_logs = count($logs);
		$total_pages = max(1, (int) ceil($total_logs / $per_page));
		$current_page = min($current_page, $total_pages);
		$offset = ($current_page - 1) * $per_page;
		$logs = array_slice(array_values($logs), $offset, $per_page);
		?>
		<div class="wrap ca-admin-wrap">
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-list-view"></span>
				<?php esc_html_e('Assessment Logs', 'rtr-custom-assessment'); ?>
			</h1>

			<?php if ('logs_cleared' === $message): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Logs cleared successfully.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<form method="get" action="" style="margin-bottom: 12px;">
				<input type="hidden" name="page" value="custom-assessment-logs">
				<div class="ca-questions-stats-grid">
					<div class="ca-search-field">
						<label for="ca-log-search"><?php esc_html_e('Search Logs', 'rtr-custom-assessment'); ?></label>
						<input type="text" id="ca-log-search" name="log_search" value="<?php echo esc_attr($search_q); ?>"
							placeholder="<?php esc_attr_e('Search message, action, status, context…', 'rtr-custom-assessment'); ?>" autocomplete="off" />
					</div>
					<div class="ca-search-field">
						<label for="ca-log-status"><?php esc_html_e('Status', 'rtr-custom-assessment'); ?></label>
						<select id="ca-log-status" name="log_status">
							<option value=""><?php esc_html_e('All', 'rtr-custom-assessment'); ?></option>
							<option value="success" <?php selected($filter_status, 'success'); ?>><?php esc_html_e('Success', 'rtr-custom-assessment'); ?></option>
							<option value="error" <?php selected($filter_status, 'error'); ?>><?php esc_html_e('Error', 'rtr-custom-assessment'); ?></option>
						</select>
					</div>
					<div class="ca-search-field">
						<label for="ca-log-action"><?php esc_html_e('Action', 'rtr-custom-assessment'); ?></label>
						<select id="ca-log-action" name="log_action">
							<option value=""><?php esc_html_e('All', 'rtr-custom-assessment'); ?></option>
							<?php foreach ($actions_list as $act): ?>
								<option value="<?php echo esc_attr($act); ?>" <?php selected($filter_action, $act); ?>>
									<?php echo esc_html($act); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="ca-search-field" style="align-self: end;">
						<button type="submit" class="button button-primary"><?php esc_html_e('Apply', 'rtr-custom-assessment'); ?></button>
						<a href="<?php echo esc_url(admin_url('admin.php?page=custom-assessment-logs')); ?>" class="button"><?php esc_html_e('Reset', 'rtr-custom-assessment'); ?></a>
					</div>
				</div>
			</form>

			<form method="post" action="" style="margin-bottom: 12px;">
				<?php wp_nonce_field('ca_clear_logs_action', '_wpnonce'); ?>
				<input type="hidden" name="ca_action" value="clear_logs">
				<button type="submit" class="button button-secondary"
					onclick="return confirm('<?php echo esc_js(__('Clear all logs? This cannot be undone.', 'rtr-custom-assessment')); ?>');">
					<?php esc_html_e('Clear Logs', 'rtr-custom-assessment'); ?>
				</button>
			</form>

			<?php if (empty($logs)): ?>
				<div class="ca-admin-empty">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
					<p><?php esc_html_e('No logs yet.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php else: ?>
				<table class="wp-list-table widefat fixed striped ca-admin-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e('Time', 'rtr-custom-assessment'); ?></th>
							<th scope="col"><?php esc_html_e('Action', 'rtr-custom-assessment'); ?></th>
							<th scope="col"><?php esc_html_e('Status', 'rtr-custom-assessment'); ?></th>
							<th scope="col"><?php esc_html_e('Message', 'rtr-custom-assessment'); ?></th>
							<th scope="col"><?php esc_html_e('Context', 'rtr-custom-assessment'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($logs as $entry): ?>
							<tr>
								<td><?php echo isset($entry['time']) ? esc_html($entry['time']) : ''; ?></td>
								<td><?php echo isset($entry['action']) ? esc_html($entry['action']) : ''; ?></td>
								<td>
									<?php
									$status = isset($entry['status']) ? (string) $entry['status'] : '';
									$status_class = 'success' === $status ? 'ca-status--completed' : 'ca-status--failed';
									?>
									<span class="ca-status-badge <?php echo esc_attr($status_class); ?>">
										<?php echo esc_html(strtoupper($status)); ?>
									</span>
								</td>
								<td><?php echo isset($entry['message']) ? esc_html($entry['message']) : ''; ?></td>
								<td>
									<code><?php echo esc_html(wp_json_encode(isset($entry['context']) ? $entry['context'] : array())); ?></code>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php echo esc_html($total_logs); ?> <?php esc_html_e('entries', 'rtr-custom-assessment'); ?>
						</span>
						<?php if ($total_pages > 1): ?>
							<span class="pagination-links">
								<?php
								$base_url = admin_url('admin.php?page=custom-assessment-logs');
								$query_base = array(
									'page' => 'custom-assessment-logs',
									'log_search' => $search_q,
									'log_status' => $filter_status,
									'log_action' => $filter_action,
								);
								$prev_disabled = $current_page <= 1 ? 'disabled' : '';
								$next_disabled = $current_page >= $total_pages ? 'disabled' : '';
								echo '<a class="prev-page button ' . esc_attr($prev_disabled) . '" href="' . esc_url(add_query_arg(array_merge($query_base, array('paged' => max(1, $current_page - 1))), $base_url)) . '">&laquo;</a>';
								$start_page = max(1, $current_page - 2);
								$end_page = min($total_pages, $start_page + 4);
								if ($start_page > 1) {
									echo '<a class="page-numbers" href="' . esc_url(add_query_arg(array_merge($query_base, array('paged' => 1)), $base_url)) . '">1</a>';
									if ($start_page > 2) {
										echo '<span class="dots">…</span>';
									}
								}
								for ($i = $start_page; $i <= $end_page; $i++) {
									$active_class = ($i === $current_page) ? 'current' : '';
									echo '<a class="page-numbers ' . esc_attr($active_class) . '" href="' . esc_url(add_query_arg(array_merge($query_base, array('paged' => $i)), $base_url)) . '">' . esc_html($i) . '</a>';
								}
								if ($end_page < $total_pages) {
									if ($end_page < $total_pages - 1) {
										echo '<span class="dots">…</span>';
									}
									echo '<a class="page-numbers" href="' . esc_url(add_query_arg(array_merge($query_base, array('paged' => $total_pages)), $base_url)) . '">' . esc_html($total_pages) . '</a>';
								}
								echo '<a class="next-page button ' . esc_attr($next_disabled) . '" href="' . esc_url(add_query_arg(array_merge($query_base, array('paged' => min($total_pages, $current_page + 1))), $base_url)) . '">&raquo;</a>';
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
			echo '<div class="wrap"><p>' . esc_html__('Submission not found.', 'rtr-custom-assessment') . '</p></div>';
			return;
		}
		?>
		<div class="wrap ca-admin-wrap">
			<h1 class="ca-admin-title">
				<a href="<?php echo esc_url(admin_url('admin.php?page=custom-assessment-submissions')); ?>"
					class="ca-admin-back">
					<span class="dashicons dashicons-arrow-left-alt"></span>
				</a>
				<?php esc_html_e('Submission Detail', 'rtr-custom-assessment'); ?>
			</h1>

			<!-- User Info -->
			<div class="ca-admin-card">
				<h2 class="ca-admin-card-title"><?php esc_html_e('Respondent Information', 'rtr-custom-assessment'); ?></h2>
				<div class="ca-admin-info-grid">
					<div>
						<label><?php esc_html_e('Name', 'rtr-custom-assessment'); ?></label><span><?php echo esc_html($submission->first_name . ' ' . $submission->last_name); ?></span>
					</div>
					<div>
						<label><?php esc_html_e('Email', 'rtr-custom-assessment'); ?></label><span><?php echo esc_html($submission->email); ?></span>
					</div>
					<div>
						<label><?php esc_html_e('Phone', 'rtr-custom-assessment'); ?></label><span><?php echo esc_html($submission->phone); ?></span>
					</div>
					<div>
						<label><?php esc_html_e('Job Title', 'rtr-custom-assessment'); ?></label><span><?php echo esc_html($submission->job_title); ?></span>
					</div>
					<div>
						<label><?php esc_html_e('Submitted', 'rtr-custom-assessment'); ?></label><span><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($submission->created_at))); ?></span>
					</div>
					<div><label><?php esc_html_e('Status', 'rtr-custom-assessment'); ?></label>
						<span
							class="ca-status-badge ca-status--<?php echo esc_attr($submission->status); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $submission->status))); ?></span>
					</div>
				</div>
			</div>

			<?php if ('completed' === $submission->status): ?>

				<!-- Overall Scores -->
				<div class="ca-admin-card">
					<h2 class="ca-admin-card-title"><?php esc_html_e('Overall Scores', 'rtr-custom-assessment'); ?></h2>
					<div class="ca-admin-score-row">
						<div class="ca-admin-score-box">
							<div class="ca-admin-score-value"><?php echo esc_html($submission->total_score); ?></div>
							<div class="ca-admin-score-label"><?php esc_html_e('Total Score', 'rtr-custom-assessment'); ?></div>
							<div class="ca-admin-score-max">
								<?php echo esc_html('/ ' . (CA_Questions::get_total_count() * 5)); ?>
							</div>
						</div>
						<div class="ca-admin-score-box">
							<div class="ca-admin-score-value">
								<?php echo esc_html(number_format($submission->average_score, 2)); ?>
							</div>
							<div class="ca-admin-score-label"><?php esc_html_e('Average Score', 'rtr-custom-assessment'); ?></div>
							<div class="ca-admin-score-max"><?php esc_html_e('/ 5.00', 'rtr-custom-assessment'); ?></div>
						</div>
						<div class="ca-admin-score-box">
							<div class="ca-admin-score-value ca-admin-score-profile">
								<?php echo esc_html(CA_Scoring::get_overall_profile((float) $submission->average_score)); ?>
							</div>
							<div class="ca-admin-score-label"><?php esc_html_e('Profile', 'rtr-custom-assessment'); ?></div>
						</div>
					</div>
				</div>

				<!-- Category Scores -->
				<div class="ca-admin-card">
					<h2 class="ca-admin-card-title"><?php esc_html_e('Category Scores', 'rtr-custom-assessment'); ?></h2>
					<table class="wp-list-table widefat fixed ca-admin-table">
						<thead>
							<tr>
								<th><?php esc_html_e('Category', 'rtr-custom-assessment'); ?></th>
								<th><?php esc_html_e('Subtotal', 'rtr-custom-assessment'); ?></th>
								<th><?php esc_html_e('Average', 'rtr-custom-assessment'); ?></th>
								<th><?php esc_html_e('Summary', 'rtr-custom-assessment'); ?></th>
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
				<h2 class="ca-admin-card-title"><?php esc_html_e('Question Responses', 'rtr-custom-assessment'); ?></h2>
				<?php if (empty($answers)): ?>
					<p><?php esc_html_e('No answers recorded yet.', 'rtr-custom-assessment'); ?></p>
				<?php else: ?>
					<table class="wp-list-table widefat fixed ca-admin-table">
						<thead>
							<tr>
								<th class="ca-col-id"><?php esc_html_e('#', 'rtr-custom-assessment'); ?></th>
								<th><?php esc_html_e('Category', 'rtr-custom-assessment'); ?></th>
								<th><?php esc_html_e('Question', 'rtr-custom-assessment'); ?></th>
								<th class="ca-col-score"><?php esc_html_e('Answer', 'rtr-custom-assessment'); ?></th>
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
			wp_die(esc_html__('You do not have permission to view this page.', 'rtr-custom-assessment'));
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
			__('Are you sure you want to delete this question? This action cannot be undone.', 'rtr-custom-assessment')
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
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only use of sanitized query params for pagination/notices.
		$current_page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
		$questions_message = isset($_GET['message']) ? sanitize_key(wp_unslash($_GET['message'])) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
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
				<?php esc_html_e('Assessment Questions', 'rtr-custom-assessment'); ?>
			</h1>

			<!-- Basic Statistics -->
			<div class="ca-questions-stats-grid">
				<div class="ca-stat-card">
					<div class="ca-stat-value"><?php echo esc_html($total_questions); ?></div>
					<div class="ca-stat-label"><?php esc_html_e('Total Questions', 'rtr-custom-assessment'); ?></div>
				</div>

				<div class="ca-stat-card">
					<div class="ca-stat-value"><?php echo esc_html(count($categories)); ?></div>
					<div class="ca-stat-label"><?php esc_html_e('Categories', 'rtr-custom-assessment'); ?></div>
				</div>

				<div class="ca-stat-card">
					<div class="ca-stat-value"><?php echo esc_html($most_used_category); ?></div>
					<div class="ca-stat-label"><?php esc_html_e('Most Used Category', 'rtr-custom-assessment'); ?></div>
					<div class="ca-stat-sublabel"><?php echo esc_html($most_used_count . ' questions'); ?></div>
				</div>
			</div>

			<!-- Add Question Form -->
			<div class="ca-questions-actions">
				<div class="ca-question-form">
					<h3><?php esc_html_e('Add New Question', 'rtr-custom-assessment'); ?></h3>
					<form method="post" action="">
						<?php wp_nonce_field('ca_add_question_action', '_wpnonce'); ?>
						<input type="hidden" name="ca_action" value="add_question">
						<div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px;">
							<div class="ca-form-field">
								<label for="question_category"><?php esc_html_e('Category', 'rtr-custom-assessment'); ?></label>
								<select id="question_category" name="question_category" required>
									<option value=""><?php esc_html_e('Select a category', 'rtr-custom-assessment'); ?></option>
									<?php foreach ($categories as $category): ?>
										<option value="<?php echo esc_attr($category); ?>"><?php echo esc_html($category); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="ca-form-field">
								<label for="question_priority"><?php esc_html_e('Priority', 'rtr-custom-assessment'); ?></label>
								<input type="number" id="question_priority" name="question_priority" required min="1" step="1"
									autocomplete="off" placeholder="" />
							</div>
							<div class="ca-form-field">
								<label
									for="question_text"><?php esc_html_e('Question Text', 'rtr-custom-assessment'); ?></label>
								<input type="text" id="question_text" name="question_text" class="ca-question-text-input"
									placeholder="<?php esc_attr_e('Enter the question text', 'rtr-custom-assessment'); ?>"
									required maxlength="500" autocomplete="off">
								<div class="ca-question-text-counter" aria-live="polite">
									<span id="ca-question-text-counter">0</span> / 500
								</div>
							</div>
						</div>
						<div class="ca-form-actions">
							<button type="submit" class="button button-primary ca-question-submit">
								<?php esc_html_e('Add Question', 'rtr-custom-assessment'); ?>
							</button>
						</div>
					</form>
				</div>
			</div>

			<br />

			<div class="ca-questions-search" style="text-align: end;">
				<div class="ca-search-field">
					<label for="ca-search-questions"><?php esc_html_e('Search Questions', 'rtr-custom-assessment'); ?></label>
					<input type="text" id="ca-search-questions"
						placeholder="<?php esc_attr_e('Search by number, category, or question text (minimum 3 characters)...', 'rtr-custom-assessment'); ?>"
						autocomplete="off">
					<div class="ca-search-count" style="display: none;">
						<span id="ca-search-results-count"></span>
					</div>
				</div>
			</div>

			<div class="ca-bulk-actions-bar" style="margin-top: 10px;">
				<button type="button" class="button button-secondary ca-bulk-edit-open" disabled>
					<?php esc_html_e('Bulk Edit', 'rtr-custom-assessment'); ?>
				</button>
				<span class="ca-bulk-selected-count">0 selected</span>
			</div>

			<div class="ca-bulk-edit-modal-overlay" id="ca-bulk-edit-modal-overlay" style="display:none;">
				<div class="ca-bulk-edit-modal">
					<h3><?php esc_html_e('Bulk Edit Questions', 'rtr-custom-assessment'); ?></h3>
					<form method="post" action="" id="ca-bulk-edit-form">
						<?php wp_nonce_field('ca_bulk_edit_question_action', '_wpnonce'); ?>
						<input type="hidden" name="ca_action" value="bulk_edit_questions">
						<input type="hidden" name="question_indexes_count" id="ca-bulk-question-indexes-count" value="0">

						<div class="ca-bulk-edit-fields">
							<div class="ca-bulk-field">
								<label for="ca-bulk-category"><?php esc_html_e('Category', 'rtr-custom-assessment'); ?></label>
								<select id="ca-bulk-category" name="bulk_category">
									<option value=""><?php esc_html_e('Keep current', 'rtr-custom-assessment'); ?></option>
									<?php foreach ($categories as $cat): ?>
										<option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="ca-bulk-field">
								<label for="ca-bulk-priority"><?php esc_html_e('Priority', 'rtr-custom-assessment'); ?></label>
								<input type="number" id="ca-bulk-priority" name="bulk_priority" min="1" step="1" placeholder="">
							</div>

							<div class="ca-bulk-field">
								<label
									for="ca-bulk-question-text"><?php esc_html_e('Question Text', 'rtr-custom-assessment'); ?></label>
								<textarea id="ca-bulk-question-text" name="bulk_question_text" rows="3" maxlength="500"
									placeholder="<?php esc_attr_e('Leave empty to keep current', 'rtr-custom-assessment'); ?>"></textarea>
							</div>
						</div>

						<div id="ca-bulk-selected-indexes"></div>

						<div class="ca-bulk-edit-actions">
							<button type="button" class="button ca-bulk-edit-cancel">
								<?php esc_html_e('Cancel', 'rtr-custom-assessment'); ?>
							</button>
							<button type="submit" class="button button-primary">
								<?php esc_html_e('Save Bulk Changes', 'rtr-custom-assessment'); ?>
							</button>
						</div>
					</form>
				</div>
			</div>

			<br />

			<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display of sanitized status message from query string. ?>
			<?php if ('question_deleted' === $questions_message): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Question deleted successfully.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if ('question_added' === $questions_message): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Question added successfully.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if ('question_edited' === $questions_message): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Question updated successfully.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if ('question_edit_failed' === $questions_message): ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e('Unable to update this question.', 'rtr-custom-assessment'); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ('priority_exists' === $questions_message): ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e('Priority already exists in this category. Please choose another number.', 'rtr-custom-assessment'); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ('bulk_edit_success' === $questions_message): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Bulk edit applied successfully.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if ('bulk_edit_failed' === $questions_message): ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e('Bulk edit failed. Please select questions and try again.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php endif; ?>
			<?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>

			<?php if (empty($questions)): ?>
				<div class="ca-admin-empty">
					<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
					<p><?php esc_html_e('No questions found. Please check your assessment configuration.', 'rtr-custom-assessment'); ?>
					</p>
				</div>
			<?php else: ?>
				<table class="wp-list-table widefat fixed striped ca-admin-table">
					<thead>
						<tr>
							<th class="ca-col-id">
								<input type="checkbox" id="ca-bulk-select-all" class="ca-bulk-select-all">
								<?php esc_html_e('#', 'rtr-custom-assessment'); ?>
							</th>
							<th><?php esc_html_e('Category', 'rtr-custom-assessment'); ?></th>
							<th><?php esc_html_e('Priority', 'rtr-custom-assessment'); ?></th>
							<th><?php esc_html_e('Question', 'rtr-custom-assessment'); ?></th>
							<th><?php esc_html_e('Actions', 'rtr-custom-assessment'); ?></th>
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
											<?php esc_html_e('Edit', 'rtr-custom-assessment'); ?>
										</button>
										<button type="button" class="button button-small button-secondary ca-question-cancel-btn"
											style="display: none;">
											<?php esc_html_e('Cancel', 'rtr-custom-assessment'); ?>
										</button>
										<button type="submit" class="button button-small button-primary ca-question-save-btn"
											style="display: none;">
											<?php esc_html_e('Save', 'rtr-custom-assessment'); ?>
										</button>
									</form>
									<form method="post" style="display: inline;"
										onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to delete this question? This action cannot be undone.', 'rtr-custom-assessment')); ?>');">
										<?php wp_nonce_field('ca_delete_question_action', '_wpnonce'); ?>
										<input type="hidden" name="ca_action" value="delete_question">
										<input type="hidden" name="question_index" value="<?php echo esc_attr($q['index']); ?>">
										<button type="submit" class="button button-small button-secondary">
											<?php esc_html_e('Delete', 'rtr-custom-assessment'); ?>
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
							<?php echo esc_html($total_questions_count); ?> 			<?php esc_html_e('items', 'rtr-custom-assessment'); ?>
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
			wp_die(esc_html__('You do not have permission to view this page.', 'rtr-custom-assessment'));
		}

		// Handle form submissions
		if (isset($_POST['ca_action'], $_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_categories_action')) {
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
				<?php esc_html_e('Assessment Categories', 'rtr-custom-assessment'); ?>
			</h1>

			<script type="text/javascript">
				var ca_admin_data = {
					nonce: '<?php echo esc_js(wp_create_nonce("ca_edit_category_action")); ?>'
				};
			</script>

			<?php if (isset($_GET['message'])): ?>
				<?php if ('duplicate' === $_GET['message']): ?>
					<div class="notice notice-error is-dismissible">
						<p><?php esc_html_e('Error: Category already exists. Please choose a different name.', 'rtr-custom-assessment'); ?>
						</p>
					</div>
				<?php else: ?>
					<div class="notice notice-success is-dismissible">
						<p>
							<?php
							if ('added' === $_GET['message']) {
								esc_html_e('Category added successfully.', 'rtr-custom-assessment');
							} elseif ('deleted' === $_GET['message']) {
								esc_html_e('Category deleted successfully.', 'rtr-custom-assessment');
							} elseif ('edited' === $_GET['message']) {
								esc_html_e('Category updated successfully.', 'rtr-custom-assessment');
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
					<div class="ca-stat-label"><?php esc_html_e('Total Categories', 'rtr-custom-assessment'); ?></div>
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
					<div class="ca-stat-label"><?php esc_html_e('Most Used Category', 'rtr-custom-assessment'); ?></div>
					<div class="ca-stat-sublabel"><?php echo esc_html($most_used_count . ' questions'); ?></div>
				</div>

				<div class="ca-stat-card">
					<div class="ca-stat-value"><?php echo esc_html($least_used_category); ?></div>
					<div class="ca-stat-label"><?php esc_html_e('Least Used Category', 'rtr-custom-assessment'); ?></div>
					<div class="ca-stat-sublabel"><?php echo esc_html($least_used_count . ' questions'); ?></div>
				</div>
			</div>

			<div class="ca-categories-header">
				<div class="ca-categories-stats">
					<span class="ca-stat-item">
						<strong><?php echo esc_html(count($categories)); ?></strong>
						<?php esc_html_e('Total Categories', 'rtr-custom-assessment'); ?>
					</span>
				</div>
			</div>

			<div class="ca-categories-actions">
				<div class="ca-category-form">
					<h3><?php esc_html_e('Add New Category', 'rtr-custom-assessment'); ?></h3>
					<form method="post" action="">
						<?php wp_nonce_field('ca_categories_action', '_wpnonce'); ?>
						<input type="hidden" name="ca_action" value="add_category">
						<div style="display: flex; gap: 10px; align-items: center; margin-bottom: 20px;">
							<div class="ca-form-field">
								<input type="text" id="new_category" name="new_category"
									placeholder="<?php esc_attr_e('Enter category name', 'rtr-custom-assessment'); ?>" required>
							</div>
							<div class="ca-form-actions">
								<button type="submit" class="button button-primary">
									<?php esc_html_e('Add Category', 'rtr-custom-assessment'); ?>
								</button>
							</div>
						</div>
					</form>
				</div>
			</div>

			<?php if (empty($categories)): ?>
				<div class="ca-admin-empty">
					<span class="dashicons dashicons-category" aria-hidden="true"></span>
					<p><?php esc_html_e('No categories found. Add your first category above.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php else: ?>
				<div class="tablenav top">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php echo esc_html($total_categories); ?> 			<?php esc_html_e('items', 'rtr-custom-assessment'); ?>
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
							<th class="ca-col-id"><?php esc_html_e('#', 'rtr-custom-assessment'); ?></th>
							<th><?php esc_html_e('Category Name', 'rtr-custom-assessment'); ?></th>
							<th><?php esc_html_e('Questions Count', 'rtr-custom-assessment'); ?></th>
							<th><?php esc_html_e('Actions', 'rtr-custom-assessment'); ?></th>
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
										<?php esc_html_e('Edit', 'rtr-custom-assessment'); ?>
									</button>
									<button type="button" class="button button-small ca-save-btn" style="display: none;"
										data-index="<?php echo esc_attr($global_index); ?>"
										data-category="<?php echo esc_attr($category); ?>">
										<?php esc_html_e('Save', 'rtr-custom-assessment'); ?>
									</button>
									<button type="button" class="button button-small button-secondary ca-category-cancel-btn"
										style="display: none;" data-index="<?php echo esc_attr($global_index); ?>"
										data-category="<?php echo esc_attr($category); ?>">
										<?php esc_html_e('Cancel', 'rtr-custom-assessment'); ?>
									</button>
									<form method="post" style="display: inline;"
										onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to delete this category? This will also delete all questions in this category.', 'rtr-custom-assessment')); ?>');">
										<?php wp_nonce_field('ca_categories_action', '_wpnonce'); ?>
										<input type="hidden" name="ca_action" value="delete_category">
										<input type="hidden" name="category_name" value="<?php echo esc_attr($category); ?>">
										<button type="submit" class="button button-small button-secondary">
											<?php esc_html_e('Delete', 'rtr-custom-assessment'); ?>
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
							<?php echo esc_html($total_categories); ?> 			<?php esc_html_e('items', 'rtr-custom-assessment'); ?>
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


