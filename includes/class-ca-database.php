<?php
/**
 * Database handler: creates tables and provides data access methods.
 */

if (!defined('ABSPATH')) {
	exit;
}

class CA_Database
{
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This class is the plugin's data-access layer and intentionally performs direct database operations.

	/**
	 * Create plugin database tables on activation.
	 */
	public static function create_tables()
	{
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Submissions table
		$submissions_table = $wpdb->prefix . 'ca_submissions';
		$sql_submissions = "CREATE TABLE IF NOT EXISTS {$submissions_table} (
			id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			first_name      VARCHAR(100) NOT NULL DEFAULT '',
			last_name       VARCHAR(100) NOT NULL DEFAULT '',
			email           VARCHAR(200) NOT NULL DEFAULT '',
			phone           VARCHAR(50)  NOT NULL DEFAULT '',
			job_title       VARCHAR(200) NOT NULL DEFAULT '',
			assessment_type VARCHAR(32)  NOT NULL DEFAULT 'mindset',
			total_score     SMALLINT     NOT NULL DEFAULT 0,
			average_score   DECIMAL(4,2) NOT NULL DEFAULT 0.00,
			status          VARCHAR(20)  NOT NULL DEFAULT 'started',
			created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY assessment_type (assessment_type)
		) {$charset_collate};";

		// Answers table
		$answers_table = $wpdb->prefix . 'ca_answers';
		$sql_answers = "CREATE TABLE IF NOT EXISTS {$answers_table} (
			id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			submission_id   BIGINT(20) UNSIGNED NOT NULL,
			question_index  TINYINT    NOT NULL DEFAULT 0,
			answer          TINYINT    NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_submission_question (submission_id, question_index)
		) {$charset_collate};";

		// Category scores table
		$cat_scores_table = $wpdb->prefix . 'ca_category_scores';
		$sql_cat_scores = "CREATE TABLE IF NOT EXISTS {$cat_scores_table} (
			id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			submission_id   BIGINT(20) UNSIGNED NOT NULL,
			category_name   VARCHAR(100) NOT NULL DEFAULT '',
			subtotal        SMALLINT     NOT NULL DEFAULT 0,
			average         DECIMAL(4,2) NOT NULL DEFAULT 0.00,
			PRIMARY KEY (id),
			KEY submission_id (submission_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql_submissions);
		dbDelta($sql_answers);
		dbDelta($sql_cat_scores);
	}

	/**
	 * Add new columns on existing installs (dbDelta may not ALTER all cases).
	 */
	public static function maybe_upgrade()
	{
		global $wpdb;
		$table = $wpdb->prefix . 'ca_submissions';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from prefix only.
		$has_type = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'assessment_type'");
		if (empty($has_type)) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from prefix only.
			$wpdb->query("ALTER TABLE {$table} ADD COLUMN assessment_type VARCHAR(32) NOT NULL DEFAULT 'mindset' AFTER job_title, ADD KEY assessment_type (assessment_type)");
		}
	}

	// -------------------------------------------------------------------------
	// Submissions
	// -------------------------------------------------------------------------

	/**
	 * Insert a new submission (user info) and return submission ID.
	 *
	 * @param array $data Sanitized user info.
	 * @return int|false New submission ID or false on failure.
	 */
	public static function insert_submission($data)
	{
		global $wpdb;
		$table = esc_sql($wpdb->prefix . 'ca_submissions');

		$assessment_type = isset( $data['assessment_type'] ) ? CA_Assessment_Types::normalize( $data['assessment_type'] ) : CA_Assessment_Types::MINDSET;

		$inserted = $wpdb->insert(
			$table,
			array(
				'first_name' => $data['first_name'],
				'last_name' => $data['last_name'],
				'email' => $data['email'],
				'phone' => $data['phone'],
				'job_title' => $data['job_title'],
				'assessment_type' => $assessment_type,
				'status' => 'started',
				'created_at' => current_time('mysql'),
				'updated_at' => current_time('mysql'),
			),
			array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Update submission scores and status on final submit.
	 *
	 * @param int   $submission_id
	 * @param int   $total_score
	 * @param float $average_score
	 */
	public static function update_submission_scores($submission_id, $total_score, $average_score)
	{
		global $wpdb;
		$table = esc_sql($wpdb->prefix . 'ca_submissions');

		$wpdb->update(
			$table,
			array(
				'total_score' => (int) $total_score,
				'average_score' => (float) $average_score,
				'status' => 'completed',
				'updated_at' => current_time('mysql'),
			),
			array('id' => (int) $submission_id),
			array('%d', '%f', '%s', '%s'),
			array('%d')
		);
	}

	/**
	 * Update submission status to in_progress.
	 *
	 * @param int $submission_id
	 */
	public static function set_in_progress($submission_id)
	{
		global $wpdb;
		$table = esc_sql($wpdb->prefix . 'ca_submissions');

		$wpdb->update(
			$table,
			array(
				'status' => 'in_progress',
				'updated_at' => current_time('mysql'),
			),
			array('id' => (int) $submission_id),
			array('%s', '%s'),
			array('%d')
		);
	}

	/**
	 * Get a submission by ID.
	 *
	 * @param int $submission_id
	 * @return object|null
	 */
	public static function get_submission($submission_id)
	{
		global $wpdb;
		$table = esc_sql($wpdb->prefix . 'ca_submissions');

		return $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix and not user input.
			$wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $submission_id)
		);
	}

	/**
	 * Get the active in-progress submission for an email (latest).
	 *
	 * @param string $email
	 * @return object|null
	 */
	public static function get_in_progress_submission_by_email($email, $assessment_type = null)
	{
		global $wpdb;
		$table = esc_sql($wpdb->prefix . 'ca_submissions');

		$type = null !== $assessment_type ? CA_Assessment_Types::normalize($assessment_type) : null;

		if ($type) {
			return $wpdb->get_row(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix and not user input.
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE email = %s AND assessment_type = %s AND status IN ('in_progress','started') ORDER BY updated_at DESC LIMIT 1",
					$email,
					$type
				)
			);
		}

		return $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix and not user input.
			$wpdb->prepare("SELECT * FROM {$table} WHERE email = %s AND status IN ('in_progress','started') ORDER BY updated_at DESC LIMIT 1", $email)
		);
	}

	/**
	 * Get all submissions for admin listing.
	 *
	 * @param string|null $assessment_type Optional. When set, only rows for that assessment (normalized).
	 * @return array
	 */
	public static function get_all_submissions($assessment_type = null)
	{
		global $wpdb;
		$table = esc_sql($wpdb->prefix . 'ca_submissions');

		if (null !== $assessment_type && '' !== $assessment_type) {
			$type = CA_Assessment_Types::normalize($assessment_type);
			return $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is prefix-derived.
					"SELECT * FROM {$table} WHERE assessment_type = %s ORDER BY created_at DESC",
					$type
				)
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix; values are not interpolated.
		return $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC");
	}

	// -------------------------------------------------------------------------
	// Answers
	// -------------------------------------------------------------------------

	/**
	 * Upsert a single answer.
	 *
	 * @param int $submission_id
	 * @param int $question_index 0-based.
	 * @param int $answer         1-5.
	 */
	public static function save_answer($submission_id, $question_index, $answer)
	{
		global $wpdb;
		$table = esc_sql($wpdb->prefix . 'ca_answers');

		$wpdb->replace(
			$table,
			array(
				'submission_id' => (int) $submission_id,
				'question_index' => (int) $question_index,
				'answer' => (int) $answer,
			),
			array('%d', '%d', '%d')
		);
	}

	/**
	 * Get all answers for a submission.
	 *
	 * @param int $submission_id
	 * @return array Keyed by question_index.
	 */
	public static function get_answers($submission_id)
	{
		global $wpdb;
		$table = esc_sql($wpdb->prefix . 'ca_answers');

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Escaped table name from $wpdb->prefix.
				"SELECT question_index, answer FROM {$table} WHERE submission_id = %d ORDER BY question_index ASC",
				(int) $submission_id
			)
		);
		$answers = array();
		foreach ($rows as $row) {
			$answers[(int) $row->question_index] = (int) $row->answer;
		}
		return $answers;
	}

	/**
	 * Get a single saved answer.
	 *
	 * @param int $submission_id
	 * @param int $question_index
	 * @return int|null
	 */
	public static function get_answer($submission_id, $question_index)
	{
		global $wpdb;
		$table = esc_sql($wpdb->prefix . 'ca_answers');

		$answer = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Escaped table name from $wpdb->prefix.
				"SELECT answer FROM {$table} WHERE submission_id = %d AND question_index = %d",
				(int) $submission_id,
				(int) $question_index
			)
		);
		return !is_null($answer) ? (int) $answer : null;
	}

	// -------------------------------------------------------------------------
	// Category scores
	// -------------------------------------------------------------------------

	/**
	 * Save category scores (called on final submit).
	 *
	 * @param int   $submission_id
	 * @param array $category_scores Array of [ 'name' => ..., 'subtotal' => ..., 'average' => ... ]
	 */
	public static function save_category_scores($submission_id, $category_scores)
	{
		global $wpdb;
		$table = esc_sql($wpdb->prefix . 'ca_category_scores');

		// Delete existing first (in case of re-submit)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional write in data-access layer.
		$wpdb->delete($table, array('submission_id' => (int) $submission_id), array('%d'));

		foreach ($category_scores as $cat) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional write in data-access layer.
			$wpdb->insert(
				$table,
				array(
					'submission_id' => (int) $submission_id,
					'category_name' => $cat['name'],
					'subtotal' => (int) $cat['subtotal'],
					'average' => (float) $cat['average'],
				),
				array('%d', '%s', '%d', '%f')
			);
		}
	}

	/**
	 * Get category scores for a submission.
	 *
	 * @param int $submission_id
	 * @return array
	 */
	public static function get_category_scores($submission_id)
	{
		global $wpdb;
		$table = esc_sql($wpdb->prefix . 'ca_category_scores');

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional read in data-access layer.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is derived from $wpdb->prefix and not user input.
		return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE submission_id = %d", (int) $submission_id));
	}

	/**
	 * Delete a submission and all related answers and category scores.
	 *
	 * @param int $submission_id
	 * @return bool True if deletion was successful.
	 */
	public static function delete_submission($submission_id)
	{
		global $wpdb;
		$submission_id = (int) $submission_id;

		$table_submissions = $wpdb->prefix . 'ca_submissions';
		$table_answers = $wpdb->prefix . 'ca_answers';
		$table_cat_scores = $wpdb->prefix . 'ca_category_scores';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional writes in data-access layer.
		$deleted = $wpdb->delete($table_submissions, array('id' => $submission_id), array('%d'));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional writes in data-access layer.
		$wpdb->delete($table_answers, array('submission_id' => $submission_id), array('%d'));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional writes in data-access layer.
		$wpdb->delete($table_cat_scores, array('submission_id' => $submission_id), array('%d'));

		return (bool) $deleted;
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
}
