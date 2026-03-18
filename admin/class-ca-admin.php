<?php
/**
 * Admin page: list and detail view for all submissions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CA_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function register_menu() {
		add_menu_page(
			__( 'Assessment Results', CA_TEXT_DOMAIN ),
			__( 'Assessment', CA_TEXT_DOMAIN ),
			'manage_options',
			'custom-assessment',
			array( $this, 'render_list_page' ),
			'dashicons-chart-bar',
			56
		);

		add_submenu_page(
			'custom-assessment',
			__( 'All Submissions', CA_TEXT_DOMAIN ),
			__( 'All Submissions', CA_TEXT_DOMAIN ),
			'manage_options',
			'custom-assessment',
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

			<?php if ( empty( $submissions ) ) : ?>
				<div class="ca-admin-empty">
					<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>
					<p><?php esc_html_e( 'No submissions yet. Share the assessment shortcode [custom_assessment] on any page.', CA_TEXT_DOMAIN ); ?></p>
				</div>
			<?php else : ?>

			<div class="ca-admin-stats-bar">
				<div class="ca-admin-stat">
					<strong><?php echo esc_html( count( $submissions ) ); ?></strong>
					<span><?php esc_html_e( 'Total Submissions', CA_TEXT_DOMAIN ); ?></span>
				</div>
				<div class="ca-admin-stat">
					<strong><?php echo esc_html( count( array_filter( $submissions, fn($s) => $s->status === 'completed' ) ) ); ?></strong>
					<span><?php esc_html_e( 'Completed', CA_TEXT_DOMAIN ); ?></span>
				</div>
			</div>

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
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'custom-assessment', 'view' => 'detail', 'id' => $sub->id ) , admin_url( 'admin.php' ) ) ); ?>" class="button button-small">
								<?php esc_html_e( 'View', CA_TEXT_DOMAIN ); ?>
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
