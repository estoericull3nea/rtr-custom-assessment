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
	 * Render assessment dashboard.
	 */
	public function render_dashboard_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', CA_TEXT_DOMAIN ) );
		}

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

			<?php if ( isset( $_GET['message'] ) && 'deleted' === $_GET['message'] ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Submission deleted successfully.', CA_TEXT_DOMAIN ); ?></p>
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
}
