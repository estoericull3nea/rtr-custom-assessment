<?php
/**
 * Admin page: list and detail view for all submissions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CA_Admin {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'handle_delete_action' ) );
		add_action( 'admin_init', array( $this, 'handle_export_action' ) );
		add_action( 'admin_init', array( $this, 'handle_send_email_action' ) );
		add_action( 'admin_init', array( $this, 'handle_categories_action' ) );
		add_action( 'admin_init', array( $this, 'handle_edit_category_action' ) );
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'Assessment Dashboard', CA_TEXT_DOMAIN ),
			__( 'Assessment', CA_TEXT_DOMAIN ),
			'manage_options',
			'custom-assessment-dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-chart-bar',
			56
		);

		add_submenu_page(
			'custom-assessment-dashboard',
			__( 'Dashboard', CA_TEXT_DOMAIN ),
			__( 'Dashboard', CA_TEXT_DOMAIN ),
			'manage_options',
			'custom-assessment-dashboard',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'custom-assessment-dashboard',
			__( 'Submissions', CA_TEXT_DOMAIN ),
			__( 'Submissions', CA_TEXT_DOMAIN ),
			'manage_options',
			'custom-assessment-submissions',
			array( $this, 'render_list_page' )
		);

		add_submenu_page(
			'custom-assessment-dashboard',
			__( 'Questions', CA_TEXT_DOMAIN ),
			__( 'Questions', CA_TEXT_DOMAIN ),
			'manage_options',
			'custom-assessment-questions',
			array( $this, 'render_questions_page' )
		);

		add_submenu_page(
			'custom-assessment-dashboard',
			__( 'Categories', CA_TEXT_DOMAIN ),
			__( 'Categories', CA_TEXT_DOMAIN ),
			'manage_options',
			'custom-assessment-categories',
			array( $this, 'render_categories_page' )
		);
	}

	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'custom-assessment' ) === false ) {
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
			array( 'jquery' ),
			CA_VERSION,
			true
		);
	}

	/**
	 * Handle delete action early on admin_init before any output.
	 */
	public function handle_delete_action() {
		if ( ! isset( $_GET['page'] ) || ! in_array( $_GET['page'], array( 'custom-assessment-dashboard', 'custom-assessment-submissions' ), true ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && ! empty( $_GET['id'] ) ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ca_delete_submission_' . absint( $_GET['id'] ) ) ) {
				wp_die( esc_html__( 'Security check failed.', CA_TEXT_DOMAIN ) );
			}

			CA_Database::delete_submission( absint( $_GET['id'] ) );
			$redirect_url = remove_query_arg( array( 'action', 'id', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) );
			$redirect_url = add_query_arg( 'message', 'deleted', $redirect_url );
			wp_safe_redirect( esc_url_raw( $redirect_url ) );
			exit;
		}
	}

	/**
	 * Handle export action early on admin_init before any output.
	 */
	public function handle_export_action() {
		if ( ! isset( $_GET['page'] ) || ! in_array( $_GET['page'], array( 'custom-assessment-dashboard', 'custom-assessment-submissions' ), true ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['action'] ) && 'export' === $_GET['action'] && ! empty( $_GET['id'] ) && ! empty( $_GET['format'] ) ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ca_export_submission_' . absint( $_GET['id'] ) ) ) {
				wp_die( esc_html__( 'Security check failed.', CA_TEXT_DOMAIN ) );
			}

			$submission_id = absint( $_GET['id'] );
			$format = sanitize_text_field( wp_unslash( $_GET['format'] ) );
			$submission = CA_Database::get_submission( $submission_id );

			if ( ! $submission || 'completed' !== $submission->status ) {
				wp_die( esc_html__( 'Only completed submissions can be exported.', CA_TEXT_DOMAIN ) );
			}

			if ( 'csv' === $format ) {
				$this->export_as_csv( $submission_id, $submission );
			} elseif ( 'pdf' === $format ) {
				$this->export_as_pdf( $submission_id, $submission );
			}

			exit;
		}
	}

	/**
	 * Handle send email action early on admin_init before any output.
	 */
	public function handle_send_email_action() {
		if ( ! isset( $_GET['page'] ) || ! in_array( $_GET['page'], array( 'custom-assessment-dashboard', 'custom-assessment-submissions' ), true ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['action'] ) && 'send_email' === $_GET['action'] && ! empty( $_GET['id'] ) ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ca_send_email_' . absint( $_GET['id'] ) ) ) {
				wp_die( esc_html__( 'Security check failed.', CA_TEXT_DOMAIN ) );
			}

			$submission_id = absint( $_GET['id'] );
			$submission = CA_Database::get_submission( $submission_id );

			if ( ! $submission ) {
				wp_die( esc_html__( 'Submission not found.', CA_TEXT_DOMAIN ) );
			}

			if ( 'completed' !== $submission->status ) {
				wp_die( esc_html__( 'Only completed submissions can have emails sent.', CA_TEXT_DOMAIN ) );
			}

			// Send the email using the existing mailer
			$sent = CA_Mailer::send_results_email( $submission_id );

			$redirect_url = remove_query_arg( array( 'action', 'id', '_wpnonce' ), wp_unslash( $_SERVER['REQUEST_URI'] ) );
			if ( $sent ) {
				$redirect_url = add_query_arg( 'message', 'email_sent', $redirect_url );
			} else {
				$redirect_url = add_query_arg( 'message', 'email_failed', $redirect_url );
			}
			wp_safe_redirect( esc_url_raw( $redirect_url ) );
			exit;
		}
	}

	/**
	 * Handle categories form submissions early on admin_init before any output.
	 */
	public function handle_categories_action() {
		if ( ! isset( $_GET['page'] ) || 'custom-assessment-categories' !== $_GET['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['ca_action'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ca_categories_action' ) ) {
			if ( 'add_category' === $_POST['ca_action'] && ! empty( $_POST['new_category'] ) ) {
				$new_category = sanitize_text_field( wp_unslash( $_POST['new_category'] ) );
				if ( ! empty( $new_category ) ) {
					// Check if category already exists
					$existing_categories = CA_Questions::get_categories();
					if ( in_array( $new_category, $existing_categories ) ) {
						$message = 'duplicate';
					} else {
						$this->add_category( $new_category );
						$message = 'added';
					}
				}
			} elseif ( 'delete_category' === $_POST['ca_action'] && ! empty( $_POST['category_name'] ) ) {
				$category_name = sanitize_text_field( wp_unslash( $_POST['category_name'] ) );
				if ( ! empty( $category_name ) ) {
					$this->delete_category( $category_name );
					$message = 'deleted';
				}
			}
			
			if ( isset( $message ) ) {
				$redirect_url = add_query_arg( 'message', $message, admin_url( 'admin.php?page=custom-assessment-categories' ) );
				wp_safe_redirect( esc_url_raw( $redirect_url ) );
				exit;
			}
		}
	}

	/**
	 * Handle edit category action early on admin_init before any output.
	 */
	public function handle_edit_category_action() {
		if ( ! isset( $_GET['page'] ) || 'custom-assessment-categories' !== $_GET['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['ca_action'] ) && 'edit_category' === $_POST['ca_action'] && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ca_edit_category_action' ) ) {
			$old_category = sanitize_text_field( wp_unslash( $_POST['old_category_name'] ) );
			$new_category = sanitize_text_field( wp_unslash( $_POST['new_category_name'] ) );
			
			if ( ! empty( $old_category ) && ! empty( $new_category ) && $old_category !== $new_category ) {
				$this->edit_category( $old_category, $new_category );
				$message = 'edited';
				
				$redirect_url = add_query_arg( 'message', $message, admin_url( 'admin.php?page=custom-assessment-categories' ) );
				wp_safe_redirect( esc_url_raw( $redirect_url ) );
				exit;
			}
		}
	}

	/**
	 * Export submission as CSV.
	 */
	private function export_as_csv( $submission_id, $submission ) {
		$answers = CA_Database::get_answers( $submission_id );
		$cat_scores = CA_Database::get_category_scores( $submission_id );
		$flat_q = CA_Questions::get_flat();

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="submission_' . $submission_id . '_' . sanitize_file_name( $submission->first_name . '_' . $submission->last_name ) . '.csv"' );

		$output = fopen( 'php://output', 'w' );
		fputcsv( $output, array( 'Respondent Information' ) );
		fputcsv( $output, array( 'Field', 'Value' ) );
		fputcsv( $output, array( 'Name', $submission->first_name . ' ' . $submission->last_name ) );
		fputcsv( $output, array( 'Email', $submission->email ) );
		fputcsv( $output, array( 'Phone', $submission->phone ) );
		fputcsv( $output, array( 'Job Title', $submission->job_title ) );
		fputcsv( $output, array( 'Total Score', $submission->total_score . ' / ' . ( CA_Questions::get_total_count() * 5 ) ) );
		fputcsv( $output, array( 'Average Score', number_format( $submission->average_score, 2 ) . ' / 5.00' ) );
		fputcsv( $output, array( 'Status', ucwords( str_replace( '_', ' ', $submission->status ) ) ) );
		fputcsv( $output, array( 'Submitted', date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $submission->created_at ) ) ) );

		fputcsv( $output, array() );
		fputcsv( $output, array( 'Category Scores' ) );
		fputcsv( $output, array( 'Category', 'Subtotal', 'Average', 'Summary' ) );
		foreach ( $cat_scores as $cat ) {
			fputcsv( $output, array(
				$cat->category_name,
				$cat->subtotal,
				number_format( $cat->average, 2 ),
				CA_Scoring::get_category_summary( $cat->category_name, (float) $cat->average )
			) );
		}

		fputcsv( $output, array() );
		fputcsv( $output, array( 'Question Responses' ) );
		fputcsv( $output, array( 'Question', 'Response' ) );
		foreach ( $flat_q as $idx => $q ) {
			$answer = isset( $answers[ $idx ] ) ? $answers[ $idx ] : null;
			fputcsv( $output, array( $q['text'], $answer ? $answer : 'No answer' ) );
		}

		fclose( $output );
	}

	/**
	 * Export submission as PDF.
	 */
	private function export_as_pdf( $submission_id, $submission ) {
		$answers = CA_Database::get_answers( $submission_id );
		$cat_scores = CA_Database::get_category_scores( $submission_id );
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
					<div><span class="info-label">Name:</span> ' . esc_html( $submission->first_name . ' ' . $submission->last_name ) . '</div>
					<div><span class="info-label">Email:</span> ' . esc_html( $submission->email ) . '</div>
					<div><span class="info-label">Phone:</span> ' . esc_html( $submission->phone ) . '</div>
					<div><span class="info-label">Job Title:</span> ' . esc_html( $submission->job_title ) . '</div>
					<div><span class="info-label">Total Score:</span> ' . esc_html( $submission->total_score . ' / ' . ( CA_Questions::get_total_count() * 5 ) ) . '</div>
					<div><span class="info-label">Average Score:</span> ' . esc_html( number_format( $submission->average_score, 2 ) . ' / 5.00' ) . '</div>
					<div><span class="info-label">Submitted:</span> ' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $submission->created_at ) ) ) . '</div>
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

		foreach ( $cat_scores as $cat ) {
			$html .= '<tr>
				<td>' . esc_html( $cat->category_name ) . '</td>
				<td>' . esc_html( $cat->subtotal ) . '</td>
				<td>' . esc_html( number_format( $cat->average, 2 ) ) . '</td>
				<td>' . esc_html( CA_Scoring::get_category_summary( $cat->category_name, (float) $cat->average ) ) . '</td>
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

		foreach ( $flat_q as $idx => $q ) {
			$answer = isset( $answers[ $idx ] ) ? $answers[ $idx ] : null;
			$html .= '<tr>
				<td>' . esc_html( $q['text'] ) . '</td>
				<td>' . esc_html( $answer ? $answer : 'No answer' ) . '</td>
			</tr>';
		}

		$html .= '</tbody>
				</table>
			</body>
		</html>';

		$filename = 'submission_' . $submission_id . '_' . sanitize_file_name( $submission->first_name . '_' . $submission->last_name ) . '.pdf';
		require_once CA_PLUGIN_DIR . 'includes/class-ca-pdf.php';
		$pdf = new CA_PDF();
		$pdf->export_pdf( $html, $filename );
	}

	/**
	 * Check if SMTP is configured for email sending.
	 * 
	 * @return bool True if SMTP is configured, false otherwise
	 */
	private function is_smtp_configured() {
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
	public function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', CA_TEXT_DOMAIN ) );
		}

		// Check SMTP configuration - show error if no SMTP detected
		// This ensures users are aware they need SMTP for email functionality
		$smtp_configured = $this->is_smtp_configured();

		$submissions = CA_Database::get_all_submissions();
		$completed = array_filter( $submissions, fn( $s ) => $s->status === 'completed' );
		$in_progress = array_filter( $submissions, fn( $s ) => $s->status === 'in_progress' );

		// Calculate statistics
		$total_submissions = count( $submissions );
		$completed_count = count( $completed );
		$in_progress_count = count( $in_progress );
		$completion_rate = $total_submissions > 0 ? round( ( $completed_count / $total_submissions ) * 100 ) : 0;

		// Calculate average scores from completed submissions
		$avg_total_score = 0;
		$avg_average_score = 0;
		if ( $completed_count > 0 ) {
			$sum_total = array_sum( array_map( fn( $s ) => (float) $s->total_score, $completed ) );
			$sum_avg = array_sum( array_map( fn( $s ) => (float) $s->average_score, $completed ) );
			$avg_total_score = $sum_total / $completed_count;
			$avg_average_score = $sum_avg / $completed_count;
		}

		// Get recent submissions
		$recent_submissions = array_slice( $submissions, 0, 5 );
		?>
		<div class="wrap ca-admin-wrap">
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-chart-bar"></span>
				<?php esc_html_e( 'Assessment Dashboard', CA_TEXT_DOMAIN ); ?>
			</h1>

			<?php if ( ! $smtp_configured ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><strong><?php esc_html_e( 'Warning: No SMTP configuration detected.', CA_TEXT_DOMAIN ); ?></strong></p>
					<p><?php esc_html_e( 'Email notifications for completed assessments may not work properly. Please configure an SMTP plugin to ensure emails are delivered successfully.', CA_TEXT_DOMAIN ); ?></p>
					<p><em><?php esc_html_e( 'Recommended plugins: WP Mail SMTP, Easy WP SMTP, Post SMTP Mailer, or similar.', CA_TEXT_DOMAIN ); ?></em></p>
				</div>
			<?php endif; ?>

			<div class="ca-dashboard-grid">
				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html( $total_submissions ); ?></div>
					<div class="ca-dashboard-card-label"><?php esc_html_e( 'Total Submissions', CA_TEXT_DOMAIN ); ?></div>
				</div>

				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html( $completed_count ); ?></div>
					<div class="ca-dashboard-card-label"><?php esc_html_e( 'Completed', CA_TEXT_DOMAIN ); ?></div>
				</div>

				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html( $in_progress_count ); ?></div>
					<div class="ca-dashboard-card-label"><?php esc_html_e( 'In Progress', CA_TEXT_DOMAIN ); ?></div>
				</div>

				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html( $completion_rate ); ?>%</div>
					<div class="ca-dashboard-card-label"><?php esc_html_e( 'Completion Rate', CA_TEXT_DOMAIN ); ?></div>
				</div>

				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html( number_format( $avg_total_score, 1 ) ); ?></div>
					<div class="ca-dashboard-card-label"><?php esc_html_e( 'Avg Total Score', CA_TEXT_DOMAIN ); ?></div>
				</div>

				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html( number_format( $avg_average_score, 2 ) ); ?>/5</div>
					<div class="ca-dashboard-card-label"><?php esc_html_e( 'Avg Score Per Q', CA_TEXT_DOMAIN ); ?></div>
				</div>
			</div>

			<div class="ca-dashboard-section">
				<h2><?php esc_html_e( 'Recent Submissions', CA_TEXT_DOMAIN ); ?></h2>

				<?php if ( empty( $recent_submissions ) ) : ?>
					<p><?php esc_html_e( 'No submissions yet.', CA_TEXT_DOMAIN ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped ca-admin-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Name', CA_TEXT_DOMAIN ); ?></th>
								<th scope="col"><?php esc_html_e( 'Email', CA_TEXT_DOMAIN ); ?></th>
								<th scope="col" class="ca-col-score"><?php esc_html_e( 'Score', CA_TEXT_DOMAIN ); ?></th>
								<th scope="col" class="ca-col-status"><?php esc_html_e( 'Status', CA_TEXT_DOMAIN ); ?></th>
								<th scope="col"><?php esc_html_e( 'Date', CA_TEXT_DOMAIN ); ?></th>
								<th scope="col"><?php esc_html_e( 'Action', CA_TEXT_DOMAIN ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent_submissions as $sub ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $sub->first_name . ' ' . $sub->last_name ); ?></strong></td>
									<td><?php echo esc_html( $sub->email ); ?></td>
									<td class="ca-col-score">
										<?php echo 'completed' === $sub->status ? esc_html( number_format( $sub->average_score, 2 ) ) : '—'; ?>
									</td>
									<td class="ca-col-status">
										<span class="ca-status-badge ca-status--<?php echo esc_attr( $sub->status ); ?>">
											<?php echo esc_html( ucwords( str_replace( '_', ' ', $sub->status ) ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $sub->created_at ) ) ); ?></td>
									<td>
										<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'custom-assessment-submissions', 'view' => 'detail', 'id' => $sub->id ), admin_url( 'admin.php' ) ) ); ?>" class="button button-small">
											<?php esc_html_e( 'View', CA_TEXT_DOMAIN ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'custom-assessment-submissions' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'View All Submissions', CA_TEXT_DOMAIN ); ?>
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
	public function render_list_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', CA_TEXT_DOMAIN ) );
		}

		// Check SMTP configuration - show error if no SMTP detected
		// This ensures users are aware they need SMTP for email functionality
		$smtp_configured = $this->is_smtp_configured();

		// Detail view
		if ( isset( $_GET['view'] ) && 'detail' === $_GET['view'] && ! empty( $_GET['id'] ) ) {
			$id = absint( $_GET['id'] );
			$this->render_detail_page( $id );
			return;
		}

		// List view
		$submissions = CA_Database::get_all_submissions();
		?>
		<div class="wrap ca-admin-wrap">
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-chart-bar"></span>
				<?php esc_html_e( 'Assessment Submissions', CA_TEXT_DOMAIN ); ?>
			</h1>

			<?php if ( ! $smtp_configured ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><strong><?php esc_html_e( 'Warning: No SMTP configuration detected.', CA_TEXT_DOMAIN ); ?></strong></p>
					<p><?php esc_html_e( 'Email notifications for completed assessments may not work properly. Please configure an SMTP plugin to ensure emails are delivered successfully.', CA_TEXT_DOMAIN ); ?></p>
					<p><em><?php esc_html_e( 'Recommended plugins: WP Mail SMTP, Easy WP SMTP, Post SMTP Mailer, or similar.', CA_TEXT_DOMAIN ); ?></em></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['message'] ) && 'deleted' === $_GET['message'] ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Submission deleted successfully.', CA_TEXT_DOMAIN ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['message'] ) && 'email_sent' === $_GET['message'] ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Assessment results email sent successfully to the customer.', CA_TEXT_DOMAIN ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['message'] ) && 'email_failed' === $_GET['message'] ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e( 'Failed to send assessment results email. Please check your SMTP configuration.', CA_TEXT_DOMAIN ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( empty( $submissions ) ) : ?>
				<div class="ca-admin-empty">
					<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					<p><?php esc_html_e( 'No submissions yet. Share the assessment shortcode [custom_assessment] on any page.', CA_TEXT_DOMAIN ); ?></p>
				</div>
			<?php else : ?>

			<table class="wp-list-table widefat fixed striped ca-admin-table">
				<thead>
					<tr>
						<th scope="col" class="ca-col-id"><?php esc_html_e( '#', CA_TEXT_DOMAIN ); ?></th>
						<th scope="col"><?php esc_html_e( 'Name', CA_TEXT_DOMAIN ); ?></th>
						<th scope="col"><?php esc_html_e( 'Email', CA_TEXT_DOMAIN ); ?></th>
						<th scope="col"><?php esc_html_e( 'Phone', CA_TEXT_DOMAIN ); ?></th>
						<th scope="col"><?php esc_html_e( 'Job Title', CA_TEXT_DOMAIN ); ?></th>
						<th scope="col" class="ca-col-score"><?php esc_html_e( 'Total Score', CA_TEXT_DOMAIN ); ?></th>
						<th scope="col" class="ca-col-score"><?php esc_html_e( 'Average', CA_TEXT_DOMAIN ); ?></th>
						<th scope="col" class="ca-col-status"><?php esc_html_e( 'Status', CA_TEXT_DOMAIN ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date', CA_TEXT_DOMAIN ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', CA_TEXT_DOMAIN ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $submissions as $sub ) : ?>
					<tr>
						<td class="ca-col-id"><?php echo esc_html( $sub->id ); ?></td>
						<td>
							<strong><?php echo esc_html( $sub->first_name . ' ' . $sub->last_name ); ?></strong>
						</td>
						<td><?php echo esc_html( $sub->email ); ?></td>
						<td><?php echo esc_html( $sub->phone ); ?></td>
						<td><?php echo esc_html( $sub->job_title ); ?></td>
						<td class="ca-col-score">
							<?php echo 'completed' === $sub->status ? esc_html( $sub->total_score ) : '—'; ?>
						</td>
						<td class="ca-col-score">
							<?php echo 'completed' === $sub->status ? esc_html( number_format( $sub->average_score, 2 ) ) : '—'; ?>
						</td>
						<td class="ca-col-status">
							<span class="ca-status-badge ca-status--<?php echo esc_attr( $sub->status ); ?>">
								<?php echo esc_html( ucwords( str_replace( '_', ' ', $sub->status ) ) ); ?>
							</span>
						</td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $sub->created_at ) ) ); ?></td>
						<td>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'custom-assessment-submissions', 'view' => 'detail', 'id' => $sub->id ) , admin_url( 'admin.php' ) ) ); ?>" class="button button-small">
								<?php esc_html_e( 'View', CA_TEXT_DOMAIN ); ?>
							</a>
							<?php if ( 'completed' === $sub->status ) : ?>
								<div class="ca-export-dropdown-wrapper">
									<div class="ca-export-menu ca-export-dropdown" id="export-<?php echo esc_attr( $sub->id ); ?>">
										<?php $csv_url = add_query_arg( array( 'page' => 'custom-assessment-submissions', 'action' => 'export', 'format' => 'csv', 'id' => $sub->id, '_wpnonce' => wp_create_nonce( 'ca_export_submission_' . $sub->id ) ), admin_url( 'admin.php' ) ); ?>
										<a href="<?php echo esc_url( $csv_url ); ?>" class="ca-export-option">
											CSV
										</a>
										<?php $pdf_url = add_query_arg( array( 'page' => 'custom-assessment-submissions', 'action' => 'export', 'format' => 'pdf', 'id' => $sub->id, '_wpnonce' => wp_create_nonce( 'ca_export_submission_' . $sub->id ) ), admin_url( 'admin.php' ) ); ?>
										<a href="<?php echo esc_url( $pdf_url ); ?>" class="ca-export-option">
											PDF
										</a>
									</div>
									<button type="button" class="button button-small ca-export-dropdown-btn" data-id="<?php echo esc_attr( $sub->id ); ?>">
										<?php esc_html_e( 'Export', CA_TEXT_DOMAIN ); ?> ▼
									</button>
								</div>
								
								<?php $email_url = add_query_arg( array( 'page' => 'custom-assessment-submissions', 'action' => 'send_email', 'id' => $sub->id, '_wpnonce' => wp_create_nonce( 'ca_send_email_' . $sub->id ) ), admin_url( 'admin.php' ) ); ?>
								<a href="<?php echo esc_url( $email_url ); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to resend the assessment results email to this customer?', CA_TEXT_DOMAIN ) ); ?>');">
									<?php esc_html_e( 'Resend Email', CA_TEXT_DOMAIN ); ?>
								</a>
							<?php endif; ?>
							<?php $delete_url = add_query_arg( array( 'page' => 'custom-assessment-submissions', 'action' => 'delete', 'id' => $sub->id, '_wpnonce' => wp_create_nonce( 'ca_delete_submission_' . $sub->id ) ), admin_url( 'admin.php' ) ); ?>
							<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this submission? This action cannot be undone.', CA_TEXT_DOMAIN ) ); ?>');">
								<?php esc_html_e( 'Delete', CA_TEXT_DOMAIN ); ?>
							</a>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the detail view for a single submission.
	 *
	 * @param int $submission_id
	 */
	private function render_detail_page( $submission_id ) {
		$submission  = CA_Database::get_submission( $submission_id );
		$answers     = CA_Database::get_answers( $submission_id );
		$cat_scores  = CA_Database::get_category_scores( $submission_id );
		$flat_q      = CA_Questions::get_flat();

		if ( ! $submission ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Submission not found.', CA_TEXT_DOMAIN ) . '</p></div>';
			return;
		}
		?>
		<div class="wrap ca-admin-wrap">
			<h1 class="ca-admin-title">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=custom-assessment' ) ); ?>" class="ca-admin-back">
					<span class="dashicons dashicons-arrow-left-alt"></span>
				</a>
				<?php esc_html_e( 'Submission Detail', CA_TEXT_DOMAIN ); ?>
			</h1>

			<!-- User Info -->
			<div class="ca-admin-card">
				<h2 class="ca-admin-card-title"><?php esc_html_e( 'Respondent Information', CA_TEXT_DOMAIN ); ?></h2>
				<div class="ca-admin-info-grid">
					<div><label><?php esc_html_e( 'Name', CA_TEXT_DOMAIN ); ?></label><span><?php echo esc_html( $submission->first_name . ' ' . $submission->last_name ); ?></span></div>
					<div><label><?php esc_html_e( 'Email', CA_TEXT_DOMAIN ); ?></label><span><?php echo esc_html( $submission->email ); ?></span></div>
					<div><label><?php esc_html_e( 'Phone', CA_TEXT_DOMAIN ); ?></label><span><?php echo esc_html( $submission->phone ); ?></span></div>
					<div><label><?php esc_html_e( 'Job Title', CA_TEXT_DOMAIN ); ?></label><span><?php echo esc_html( $submission->job_title ); ?></span></div>
					<div><label><?php esc_html_e( 'Submitted', CA_TEXT_DOMAIN ); ?></label><span><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $submission->created_at ) ) ); ?></span></div>
					<div><label><?php esc_html_e( 'Status', CA_TEXT_DOMAIN ); ?></label>
						<span class="ca-status-badge ca-status--<?php echo esc_attr( $submission->status ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $submission->status ) ) ); ?></span>
					</div>
				</div>
			</div>

			<?php if ( 'completed' === $submission->status ) : ?>

			<!-- Overall Scores -->
			<div class="ca-admin-card">
				<h2 class="ca-admin-card-title"><?php esc_html_e( 'Overall Scores', CA_TEXT_DOMAIN ); ?></h2>
				<div class="ca-admin-score-row">
					<div class="ca-admin-score-box">
						<div class="ca-admin-score-value"><?php echo esc_html( $submission->total_score ); ?></div>
						<div class="ca-admin-score-label"><?php esc_html_e( 'Total Score', CA_TEXT_DOMAIN ); ?></div>
						<div class="ca-admin-score-max"><?php echo esc_html( '/ ' . ( CA_Questions::get_total_count() * 5 ) ); ?></div>
					</div>
					<div class="ca-admin-score-box">
						<div class="ca-admin-score-value"><?php echo esc_html( number_format( $submission->average_score, 2 ) ); ?></div>
						<div class="ca-admin-score-label"><?php esc_html_e( 'Average Score', CA_TEXT_DOMAIN ); ?></div>
						<div class="ca-admin-score-max"><?php esc_html_e( '/ 5.00', CA_TEXT_DOMAIN ); ?></div>
					</div>
					<div class="ca-admin-score-box">
						<div class="ca-admin-score-value ca-admin-score-profile"><?php echo esc_html( CA_Scoring::get_overall_profile( (float) $submission->average_score ) ); ?></div>
						<div class="ca-admin-score-label"><?php esc_html_e( 'Profile', CA_TEXT_DOMAIN ); ?></div>
					</div>
				</div>
			</div>

			<!-- Category Scores -->
			<div class="ca-admin-card">
				<h2 class="ca-admin-card-title"><?php esc_html_e( 'Category Scores', CA_TEXT_DOMAIN ); ?></h2>
				<table class="wp-list-table widefat fixed ca-admin-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Category', CA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Subtotal', CA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Average', CA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Summary', CA_TEXT_DOMAIN ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $cat_scores as $cat ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $cat->category_name ); ?></strong></td>
							<td><?php echo esc_html( $cat->subtotal ); ?></td>
							<td><?php echo esc_html( number_format( $cat->average, 2 ) ); ?></td>
							<td class="ca-admin-summary"><?php echo esc_html( CA_Scoring::get_category_summary( $cat->category_name, (float) $cat->average ) ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php endif; ?>

			<!-- All Answers -->
			<div class="ca-admin-card">
				<h2 class="ca-admin-card-title"><?php esc_html_e( 'Question Responses', CA_TEXT_DOMAIN ); ?></h2>
				<?php if ( empty( $answers ) ) : ?>
					<p><?php esc_html_e( 'No answers recorded yet.', CA_TEXT_DOMAIN ); ?></p>
				<?php else : ?>
				<table class="wp-list-table widefat fixed ca-admin-table">
					<thead>
						<tr>
							<th class="ca-col-id"><?php esc_html_e( '#', CA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Category', CA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Question', CA_TEXT_DOMAIN ); ?></th>
							<th class="ca-col-score"><?php esc_html_e( 'Answer', CA_TEXT_DOMAIN ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $flat_q as $q ) : ?>
						<tr>
							<td class="ca-col-id"><?php echo esc_html( $q['index'] + 1 ); ?></td>
							<td><?php echo esc_html( $q['category'] ); ?></td>
							<td><?php echo esc_html( $q['text'] ); ?></td>
							<td class="ca-col-score">
								<?php if ( isset( $answers[ $q['index'] ] ) ) : ?>
									<span class="ca-answer-pill ca-answer-pill--<?php echo esc_attr( $answers[ $q['index'] ] ); ?>">
										<?php echo esc_html( $answers[ $q['index'] ] ); ?>
									</span>
								<?php else : ?>
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
	public function render_questions_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', CA_TEXT_DOMAIN ) );
		}

		$questions = CA_Questions::get_flat();
		$total_questions = CA_Questions::get_total_count();
		$categories = CA_Questions::get_categories();
		
		// Pagination setup
		$per_page = 10;
		$current_page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
		$total_questions_count = count($questions);
		$total_pages = ceil($total_questions_count / $per_page);
		$offset = ($current_page - 1) * $per_page;
		$paged_questions = array_slice($questions, $offset, $per_page);
		
		?>
		<div class="wrap ca-admin-wrap">
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-format-chat"></span>
				<?php esc_html_e( 'Assessment Questions', CA_TEXT_DOMAIN ); ?>
			</h1>

			<div class="ca-questions-header">
				<div class="ca-questions-stats">
					<span class="ca-stat-item">
						<strong><?php echo esc_html( $total_questions ); ?></strong>
						<?php esc_html_e( 'Total Questions', CA_TEXT_DOMAIN ); ?>
					</span>
					<span class="ca-stat-item">
						<strong><?php echo esc_html( count( $categories ) ); ?></strong>
						<?php esc_html_e( 'Categories', CA_TEXT_DOMAIN ); ?>
					</span>
				</div>
			</div>

			<?php if ( empty( $questions ) ) : ?>
				<div class="ca-admin-empty">
					<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
					<p><?php esc_html_e( 'No questions found. Please check your assessment configuration.', CA_TEXT_DOMAIN ); ?></p>
				</div>
			<?php else : ?>
				<div class="tablenav top">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php echo esc_html( $total_questions_count ); ?> <?php esc_html_e( 'items', CA_TEXT_DOMAIN ); ?>
						</span>
						<?php if ( $total_pages > 1 ) : ?>
							<span class="pagination-links">
								<?php
								$base_url = admin_url( 'admin.php?page=custom-assessment-questions' );
								$prev_disabled = $current_page <= 1 ? 'disabled' : '';
								$next_disabled = $current_page >= $total_pages ? 'disabled' : '';
								
								// Previous button
								echo '<a class="prev-page button ' . esc_attr( $prev_disabled ) . '" href="' . esc_url( add_query_arg( 'paged', max(1, $current_page - 1), $base_url ) ) . '">&laquo;</a>';
								
								// Page numbers (show up to 5 page numbers)
								$start_page = max(1, $current_page - 2);
								$end_page = min($total_pages, $start_page + 4);
								
								if ($start_page > 1) {
									echo '<a class="page-numbers" href="' . esc_url( add_query_arg( 'paged', 1, $base_url ) ) . '">1</a>';
									if ($start_page > 2) {
										echo '<span class="dots">…</span>';
									}
								}
								
								for ($i = $start_page; $i <= $end_page; $i++) {
									$active_class = ($i === $current_page) ? 'current' : '';
									echo '<a class="page-numbers ' . esc_attr( $active_class ) . '" href="' . esc_url( add_query_arg( 'paged', $i, $base_url ) ) . '">' . esc_html( $i ) . '</a>';
								}
								
								if ($end_page < $total_pages) {
									if ($end_page < $total_pages - 1) {
										echo '<span class="dots">…</span>';
									}
									echo '<a class="page-numbers" href="' . esc_url( add_query_arg( 'paged', $total_pages, $base_url ) ) . '">' . esc_html( $total_pages ) . '</a>';
								}
								
								// Next button
								echo '<a class="next-page button ' . esc_attr( $next_disabled ) . '" href="' . esc_url( add_query_arg( 'paged', min($total_pages, $current_page + 1), $base_url ) ) . '">&raquo;</a>';
								?>
							</span>
						<?php endif; ?>
					</div>
					<br class="clear">
				</div>

				<table class="wp-list-table widefat fixed striped ca-admin-table">
					<thead>
						<tr>
							<th class="ca-col-id"><?php esc_html_e( '#', CA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Category', CA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Priority', CA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Question', CA_TEXT_DOMAIN ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $paged_questions as $q ) : ?>
							<tr>
								<td class="ca-col-id"><?php echo esc_html( $q['index'] + 1 ); ?></td>
								<td><?php echo esc_html( $q['category'] ); ?></td>
								<td class="ca-col-priority"><?php echo esc_html( $q['priority'] ); ?></td>
								<td><?php echo esc_html( $q['text'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php echo esc_html( $total_questions_count ); ?> <?php esc_html_e( 'items', CA_TEXT_DOMAIN ); ?>
						</span>
						<?php if ( $total_pages > 1 ) : ?>
							<span class="pagination-links">
								<?php
								// Previous button
								echo '<a class="prev-page button ' . esc_attr( $prev_disabled ) . '" href="' . esc_url( add_query_arg( 'paged', max(1, $current_page - 1), $base_url ) ) . '">&laquo;</a>';
								
								// Page numbers (show up to 5 page numbers)
								$start_page = max(1, $current_page - 2);
								$end_page = min($total_pages, $start_page + 4);
								
								if ($start_page > 1) {
									echo '<a class="page-numbers" href="' . esc_url( add_query_arg( 'paged', 1, $base_url ) ) . '">1</a>';
									if ($start_page > 2) {
										echo '<span class="dots">…</span>';
									}
								}
								
								for ($i = $start_page; $i <= $end_page; $i++) {
									$active_class = ($i === $current_page) ? 'current' : '';
									echo '<a class="page-numbers ' . esc_attr( $active_class ) . '" href="' . esc_url( add_query_arg( 'paged', $i, $base_url ) ) . '">' . esc_html( $i ) . '</a>';
								}
								
								if ($end_page < $total_pages) {
									if ($end_page < $total_pages - 1) {
										echo '<span class="dots">…</span>';
									}
									echo '<a class="page-numbers" href="' . esc_url( add_query_arg( 'paged', $total_pages, $base_url ) ) . '">' . esc_html( $total_pages ) . '</a>';
								}
								
								// Next button
								echo '<a class="next-page button ' . esc_attr( $next_disabled ) . '" href="' . esc_url( add_query_arg( 'paged', min($total_pages, $current_page + 1), $base_url ) ) . '">&raquo;</a>';
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
	public function render_categories_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', CA_TEXT_DOMAIN ) );
		}

		// Handle form submissions
		if ( isset( $_POST['ca_action'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ca_categories_action' ) ) {
			if ( 'add_category' === $_POST['ca_action'] && ! empty( $_POST['new_category'] ) ) {
				$new_category = sanitize_text_field( wp_unslash( $_POST['new_category'] ) );
				if ( ! empty( $new_category ) ) {
					// Check if category already exists
					$existing_categories = CA_Questions::get_categories();
					if ( in_array( $new_category, $existing_categories ) ) {
						$message = 'duplicate';
					} else {
						$this->add_category( $new_category );
						$message = 'added';
					}
				}
			} elseif ( 'delete_category' === $_POST['ca_action'] && ! empty( $_POST['category_name'] ) ) {
				$category_name = sanitize_text_field( wp_unslash( $_POST['category_name'] ) );
				if ( ! empty( $category_name ) ) {
					$this->delete_category( $category_name );
					$message = 'deleted';
				}
			}
			
			if ( isset( $message ) ) {
				$redirect_url = add_query_arg( 'message', $message, admin_url( 'admin.php?page=custom-assessment-categories' ) );
				wp_safe_redirect( esc_url_raw( $redirect_url ) );
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
				<?php esc_html_e( 'Assessment Categories', CA_TEXT_DOMAIN ); ?>
			</h1>

			<script type="text/javascript">
				var ca_admin_data = {
					nonce: '<?php echo esc_js( wp_create_nonce( "ca_edit_category_action" ) ); ?>'
				};
			</script>

			<?php if ( isset( $_GET['message'] ) ) : ?>
				<?php if ( 'duplicate' === $_GET['message'] ) : ?>
					<div class="notice notice-error is-dismissible">
						<p><?php esc_html_e( 'Error: Category already exists. Please choose a different name.', CA_TEXT_DOMAIN ); ?></p>
					</div>
				<?php else : ?>
					<div class="notice notice-success is-dismissible">
						<p>
							<?php 
							if ( 'added' === $_GET['message'] ) {
								esc_html_e( 'Category added successfully.', CA_TEXT_DOMAIN );
							} elseif ( 'deleted' === $_GET['message'] ) {
								esc_html_e( 'Category deleted successfully.', CA_TEXT_DOMAIN );
							} elseif ( 'edited' === $_GET['message'] ) {
								esc_html_e( 'Category updated successfully.', CA_TEXT_DOMAIN );
							}
							?>
						</p>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<!-- Basic Statistics -->
			<div class="ca-categories-stats-grid">
				<div class="ca-stat-card">
					<div class="ca-stat-value"><?php echo esc_html( count( $categories ) ); ?></div>
					<div class="ca-stat-label"><?php esc_html_e( 'Total Categories', CA_TEXT_DOMAIN ); ?></div>
				</div>
				
				<?php 
				// Calculate question counts for each category
				$questions = CA_Questions::get_flat();
				$category_counts = array_count_values( array_column( $questions, 'category' ) );
				
				// Find most used category
				$most_used_category = '';
				$most_used_count = 0;
				$least_used_category = '';
				$least_used_count = PHP_INT_MAX;
				
				foreach ( $category_counts as $category => $count ) {
					if ( $count > $most_used_count ) {
						$most_used_count = $count;
						$most_used_category = $category;
					}
					if ( $count < $least_used_count ) {
						$least_used_count = $count;
						$least_used_category = $category;
					}
				}
				?>
				
				<div class="ca-stat-card">
					<div class="ca-stat-value"><?php echo esc_html( $most_used_category ); ?></div>
					<div class="ca-stat-label"><?php esc_html_e( 'Most Used Category', CA_TEXT_DOMAIN ); ?></div>
					<div class="ca-stat-sublabel"><?php echo esc_html( $most_used_count . ' questions' ); ?></div>
				</div>
				
				<div class="ca-stat-card">
					<div class="ca-stat-value"><?php echo esc_html( $least_used_category ); ?></div>
					<div class="ca-stat-label"><?php esc_html_e( 'Least Used Category', CA_TEXT_DOMAIN ); ?></div>
					<div class="ca-stat-sublabel"><?php echo esc_html( $least_used_count . ' questions' ); ?></div>
				</div>
			</div>

			<div class="ca-categories-header">
				<div class="ca-categories-stats">
					<span class="ca-stat-item">
						<strong><?php echo esc_html( count( $categories ) ); ?></strong>
						<?php esc_html_e( 'Total Categories', CA_TEXT_DOMAIN ); ?>
					</span>
				</div>
			</div>

			<div class="ca-categories-actions">
				<div class="ca-category-form">
					<h3><?php esc_html_e( 'Add New Category', CA_TEXT_DOMAIN ); ?></h3>
					<form method="post" action="">
						<?php wp_nonce_field( 'ca_categories_action', '_wpnonce' ); ?>
						<input type="hidden" name="ca_action" value="add_category">
						<div style="display: flex; gap: 10px; align-items: center; margin-bottom: 20px;">
							<div class="ca-form-field">
							<input type="text" id="new_category" name="new_category" placeholder="<?php esc_attr_e( 'Enter category name', CA_TEXT_DOMAIN ); ?>" required>
						</div>
						<div class="ca-form-actions">
							<button type="submit" class="button button-primary">
								<?php esc_html_e( 'Add Category', CA_TEXT_DOMAIN ); ?>
							</button>
						</div>
						</div>
					</form>
				</div>
			</div>

			<?php if ( empty( $categories ) ) : ?>
				<div class="ca-admin-empty">
					<span class="dashicons dashicons-category" aria-hidden="true"></span>
					<p><?php esc_html_e( 'No categories found. Add your first category above.', CA_TEXT_DOMAIN ); ?></p>
				</div>
			<?php else : ?>
				<div class="tablenav top">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php echo esc_html( $total_categories ); ?> <?php esc_html_e( 'items', CA_TEXT_DOMAIN ); ?>
						</span>
						<?php if ( $total_pages > 1 ) : ?>
							<span class="pagination-links">
								<?php
								$base_url = admin_url( 'admin.php?page=custom-assessment-categories' );
								$prev_disabled = $current_page <= 1 ? 'disabled' : '';
								$next_disabled = $current_page >= $total_pages ? 'disabled' : '';
								
								// Previous button
								echo '<a class="prev-page button ' . esc_attr( $prev_disabled ) . '" href="' . esc_url( add_query_arg( 'paged', max(1, $current_page - 1), $base_url ) ) . '">&laquo;</a>';
								
								// Page numbers (show up to 5 page numbers)
								$start_page = max(1, $current_page - 2);
								$end_page = min($total_pages, $start_page + 4);
								
								if ($start_page > 1) {
									echo '<a class="page-numbers" href="' . esc_url( add_query_arg( 'paged', 1, $base_url ) ) . '">1</a>';
									if ($start_page > 2) {
										echo '<span class="dots">…</span>';
									}
								}
								
								for ($i = $start_page; $i <= $end_page; $i++) {
									$active_class = ($i === $current_page) ? 'current' : '';
									echo '<a class="page-numbers ' . esc_attr( $active_class ) . '" href="' . esc_url( add_query_arg( 'paged', $i, $base_url ) ) . '">' . esc_html( $i ) . '</a>';
								}
								
								if ($end_page < $total_pages) {
									if ($end_page < $total_pages - 1) {
										echo '<span class="dots">…</span>';
									}
									echo '<a class="page-numbers" href="' . esc_url( add_query_arg( 'paged', $total_pages, $base_url ) ) . '">' . esc_html( $total_pages ) . '</a>';
								}
								
								// Next button
								echo '<a class="next-page button ' . esc_attr( $next_disabled ) . '" href="' . esc_url( add_query_arg( 'paged', min($total_pages, $current_page + 1), $base_url ) ) . '">&raquo;</a>';
								?>
							</span>
						<?php endif; ?>
					</div>
					<br class="clear">
				</div>

				<table class="wp-list-table widefat fixed striped ca-admin-table">
					<thead>
						<tr>
							<th class="ca-col-id"><?php esc_html_e( '#', CA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Category Name', CA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Questions Count', CA_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Actions', CA_TEXT_DOMAIN ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php 
						$questions = CA_Questions::get_flat();
						$category_counts = array_count_values( array_column( $questions, 'category' ) );
						
						foreach ( $paged_categories as $index => $category ) : 
							$global_index = $offset + $index;
							$count = isset( $category_counts[$category] ) ? $category_counts[$category] : 0;
						?>
							<tr>
								<td class="ca-col-id"><?php echo esc_html( $global_index + 1 ); ?></td>
								<td>
									<strong class="ca-category-name" id="category-name-<?php echo esc_attr( $global_index ); ?>">
										<?php echo esc_html( $category ); ?>
									</strong>
									<input type="text" 
										class="ca-category-input" 
										id="category-input-<?php echo esc_attr( $global_index ); ?>" 
										value="<?php echo esc_attr( $category ); ?>" 
										style="display: none; width: 100%;"
										data-original="<?php echo esc_attr( $category ); ?>">
								</td>
								<td><?php echo esc_html( $count ); ?></td>
								<td>
									<button type="button" 
										class="button button-small ca-edit-btn" 
										data-index="<?php echo esc_attr( $global_index ); ?>"
										data-category="<?php echo esc_attr( $category ); ?>">
										<?php esc_html_e( 'Edit', CA_TEXT_DOMAIN ); ?>
									</button>
									<button type="button" 
										class="button button-small ca-save-btn" 
										style="display: none;"
										data-index="<?php echo esc_attr( $global_index ); ?>"
										data-category="<?php echo esc_attr( $category ); ?>">
										<?php esc_html_e( 'Save', CA_TEXT_DOMAIN ); ?>
									</button>
									<form method="post" style="display: inline;" onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this category? This will also delete all questions in this category.', CA_TEXT_DOMAIN ) ); ?>');">
										<?php wp_nonce_field( 'ca_categories_action', '_wpnonce' ); ?>
										<input type="hidden" name="ca_action" value="delete_category">
										<input type="hidden" name="category_name" value="<?php echo esc_attr( $category ); ?>">
										<button type="submit" class="button button-small button-secondary">
											<?php esc_html_e( 'Delete', CA_TEXT_DOMAIN ); ?>
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
							<?php echo esc_html( $total_categories ); ?> <?php esc_html_e( 'items', CA_TEXT_DOMAIN ); ?>
						</span>
						<?php if ( $total_pages > 1 ) : ?>
							<span class="pagination-links">
								<?php
								// Previous button
								echo '<a class="prev-page button ' . esc_attr( $prev_disabled ) . '" href="' . esc_url( add_query_arg( 'paged', max(1, $current_page - 1), $base_url ) ) . '">&laquo;</a>';
								
								// Page numbers (show up to 5 page numbers)
								$start_page = max(1, $current_page - 2);
								$end_page = min($total_pages, $start_page + 4);
								
								if ($start_page > 1) {
									echo '<a class="page-numbers" href="' . esc_url( add_query_arg( 'paged', 1, $base_url ) ) . '">1</a>';
									if ($start_page > 2) {
										echo '<span class="dots">…</span>';
									}
								}
								
								for ($i = $start_page; $i <= $end_page; $i++) {
									$active_class = ($i === $current_page) ? 'current' : '';
									echo '<a class="page-numbers ' . esc_attr( $active_class ) . '" href="' . esc_url( add_query_arg( 'paged', $i, $base_url ) ) . '">' . esc_html( $i ) . '</a>';
								}
								
								if ($end_page < $total_pages) {
									if ($end_page < $total_pages - 1) {
										echo '<span class="dots">…</span>';
									}
									echo '<a class="page-numbers" href="' . esc_url( add_query_arg( 'paged', $total_pages, $base_url ) ) . '">' . esc_html( $total_pages ) . '</a>';
								}
								
								// Next button
								echo '<a class="next-page button ' . esc_attr( $next_disabled ) . '" href="' . esc_url( add_query_arg( 'paged', min($total_pages, $current_page + 1), $base_url ) ) . '">&raquo;</a>';
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
	private function add_category( $category_name ) {
		// Get existing custom categories
		$custom_categories = get_option( 'ca_custom_categories', array() );
		
		// Add the new category if it doesn't already exist
		if ( ! in_array( $category_name, $custom_categories ) ) {
			$custom_categories[] = $category_name;
			update_option( 'ca_custom_categories', $custom_categories );
		}
	}

	/**
	 * Delete a category from the questions configuration.
	 * 
	 * @param string $category_name
	 */
	private function delete_category( $category_name ) {
		// Get existing custom categories
		$custom_categories = get_option( 'ca_custom_categories', array() );
		
		// Remove the category if it exists
		$key = array_search( $category_name, $custom_categories );
		if ( $key !== false ) {
			unset( $custom_categories[$key] );
			$custom_categories = array_values( $custom_categories ); // Re-index array
			update_option( 'ca_custom_categories', $custom_categories );
		}
	}

	/**
	 * Edit a category name in the questions configuration.
	 * 
	 * @param string $old_category
	 * @param string $new_category
	 */
	private function edit_category( $old_category, $new_category ) {
		// Get existing custom categories
		$custom_categories = get_option( 'ca_custom_categories', array() );
		
		// Find and replace the category name
		$key = array_search( $old_category, $custom_categories );
		if ( $key !== false ) {
			$custom_categories[$key] = $new_category;
			update_option( 'ca_custom_categories', $custom_categories );
		}
	}
}
