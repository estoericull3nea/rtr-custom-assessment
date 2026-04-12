<?php
/**
 * Shortcodes: [custom_assessment] [social_fluency_assessment] [natural_attributes_cataloging_assessment]
 * Renders trigger buttons; shared modal prints in wp_footer when needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CA_Shortcode {

	/** @var bool */
	private static $needs_modal = false;

	/** @var bool */
	private static $modal_printed = false;

	public function __construct() {
		add_shortcode( 'custom_assessment', array( $this, 'render_mindset' ) );
		add_shortcode( 'social_fluency_assessment', array( $this, 'render_social_fluency' ) );
		add_shortcode( 'natural_attributes_cataloging_assessment', array( $this, 'render_natural_attributes_cataloging' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'maybe_print_modal' ), 5 );
	}

	/**
	 * @param WP_Post|null $post Post to check.
	 * @return bool
	 */
	private function post_has_assessment_shortcode( $post ) {
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return false;
		}
		return has_shortcode( $post->post_content, 'custom_assessment' )
			|| has_shortcode( $post->post_content, 'social_fluency_assessment' )
			|| has_shortcode( $post->post_content, 'natural_attributes_cataloging_assessment' );
	}

	public function enqueue_assets() {
		global $post;

		if ( ! $this->post_has_assessment_shortcode( $post ) ) {
			return;
		}

		wp_enqueue_style(
			'ca-styles',
			CA_PLUGIN_URL . 'assets/css/assessment.css',
			array(),
			CA_VERSION
		);

		wp_enqueue_script(
			'ca-scripts',
			CA_PLUGIN_URL . 'assets/js/assessment.js',
			array( 'jquery' ),
			CA_VERSION,
			true
		);

		$mindset_labels = array(
			1 => __( 'Least like me', 'rtr-custom-assessment' ),
			2 => __( 'Slightly like me', 'rtr-custom-assessment' ),
			3 => __( 'Somewhat like me', 'rtr-custom-assessment' ),
			4 => __( 'Mostly like me', 'rtr-custom-assessment' ),
			5 => __( 'Most like me', 'rtr-custom-assessment' ),
		);

		wp_localize_script(
			'ca-scripts',
			'CA_Config',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ca_nonce' ),
				'assessments' => array(
					CA_Assessment_Types::MINDSET        => array(
						'type'               => CA_Assessment_Types::MINDSET,
						'modal_title'        => __( 'Entrepreneurial Mindset Assessment', 'rtr-custom-assessment' ),
						'scale_max'          => 5,
						'total_questions'    => CA_Questions::get_total_count(),
						'scale_note'         => __( 'Rate this statement on a scale of 1 to 5, where <strong>1</strong> means it is least like you and <strong>5</strong> means it is most like you.', 'rtr-custom-assessment' ),
						'per_number_labels'  => $mindset_labels,
						'questions_priority' => array_values(
							array_map(
								function ( $q ) {
									return array(
										'index'    => isset( $q['index'] ) ? (int) $q['index'] : 0,
										'priority' => isset( $q['priority'] ) ? (int) $q['priority'] : 0,
										'category' => isset( $q['category'] ) ? (string) $q['category'] : '',
									);
								},
								CA_Questions::get_flat()
							)
						),
					),
					CA_Assessment_Types::SOCIAL_FLUENCY => array(
						'type'               => CA_Assessment_Types::SOCIAL_FLUENCY,
						'modal_title'        => __( 'Social Fluency Assessment', 'rtr-custom-assessment' ),
						'scale_max'          => 10,
						'total_questions'    => CA_Social_Fluency_Questions::get_total_count(),
						'scale_note'         => __( 'Rate each question on a scale of 1 to 10, where 1 is the lowest and 10 is the highest for you.', 'rtr-custom-assessment' ),
						'questions_priority' => array_values(
							array_map(
								function ( $q ) {
									return array(
										'index'    => isset( $q['index'] ) ? (int) $q['index'] : 0,
										'priority' => isset( $q['priority'] ) ? (int) $q['priority'] : 0,
										'category' => isset( $q['category'] ) ? (string) $q['category'] : '',
									);
								},
								CA_Social_Fluency_Questions::get_flat()
							)
						),
					),
					CA_Assessment_Types::INNER_DIMENSIONS => array(
						'type'               => CA_Assessment_Types::INNER_DIMENSIONS,
						'modal_title'        => __( 'Natural Attributes Cataloging', 'rtr-custom-assessment' ),
						'scale_max'          => 2,
						'total_questions'    => CA_Inner_Dimensions_Questions::get_total_count(),
						'scale_note'         => __( 'Answer <strong>Yes</strong> or <strong>No</strong> for each statement, based on how true it is for you.', 'rtr-custom-assessment' ),
						'per_number_labels'  => array(),
						'questions_priority' => array_values(
							array_map(
								function ( $q ) {
									return array(
										'index'    => isset( $q['index'] ) ? (int) $q['index'] : 0,
										'priority' => isset( $q['priority'] ) ? (int) $q['priority'] : 0,
										'category' => isset( $q['category'] ) ? (string) $q['category'] : '',
									);
								},
								CA_Inner_Dimensions_Questions::get_flat()
							)
						),
					),
				),
				'inner_results' => array(
					'title'        => __( 'Natural Attributes Cataloging', 'rtr-custom-assessment' ),
					'tagline'      => __( 'Remember Who You Were Before the World Told You Who to Be.', 'rtr-custom-assessment' ),
					'congrats'     => __( 'Congratulations on Completing Your Discovery Journey!', 'rtr-custom-assessment' ),
					'email_lead'   => __( 'Your full report has been emailed to', 'rtr-custom-assessment' ),
					'change_email' => __( 'Change email address', 'rtr-custom-assessment' ),
					'intro'        => __( 'You\'ve taken an important step towards unlocking your potential. Dive into your personalized results below to uncover insights and next steps on your path to enhancing leadership skills and embracing new opportunities.', 'rtr-custom-assessment' ),
				),
				'labels'      => array(
					'next'          => __( 'Next', 'rtr-custom-assessment' ),
					'back'          => __( 'Back', 'rtr-custom-assessment' ),
					'submit'        => __( 'Submit Assessment', 'rtr-custom-assessment' ),
					'start'         => __( 'Start Assessment', 'rtr-custom-assessment' ),
					'loading'       => __( 'Loading…', 'rtr-custom-assessment' ),
					'error_answer'  => __( 'Please select an answer before continuing.', 'rtr-custom-assessment' ),
					'error_generic' => __( 'Something went wrong. Please try again.', 'rtr-custom-assessment' ),
					'yes_no_yes'    => __( 'Yes', 'rtr-custom-assessment' ),
					'yes_no_no'     => __( 'No', 'rtr-custom-assessment' ),
				),
			)
		);
	}

	/**
	 * @param array $atts Shortcode atts.
	 * @return string
	 */
	public function render_mindset( $atts ) {
		self::$needs_modal = true;
		$atts              = shortcode_atts(
			array(
				'button_text' => __( 'Take the Assessment Now', 'rtr-custom-assessment' ),
			),
			$atts,
			'custom_assessment'
		);
		return $this->render_trigger_button( CA_Assessment_Types::MINDSET, $atts['button_text'] );
	}

	/**
	 * @param array $atts Shortcode atts.
	 * @return string
	 */
	public function render_social_fluency( $atts ) {
		self::$needs_modal = true;
		$atts              = shortcode_atts(
			array(
				'button_text' => __( 'Take the Social Fluency Assessment', 'rtr-custom-assessment' ),
			),
			$atts,
			'social_fluency_assessment'
		);
		return $this->render_trigger_button( CA_Assessment_Types::SOCIAL_FLUENCY, $atts['button_text'] );
	}

	/**
	 * @param array $atts Shortcode atts.
	 * @return string
	 */
	public function render_natural_attributes_cataloging( $atts ) {
		self::$needs_modal = true;
		$atts              = shortcode_atts(
			array(
				'button_text' => __( 'Start Natural Attributes Cataloging', 'rtr-custom-assessment' ),
			),
			$atts,
			'natural_attributes_cataloging_assessment'
		);
		return $this->render_trigger_button( CA_Assessment_Types::INNER_DIMENSIONS, $atts['button_text'] );
	}

	/**
	 * @param string $assessment_type Normalized type.
	 * @param string $button_text     Label.
	 * @return string
	 */
	private function render_trigger_button( $assessment_type, $button_text ) {
		$type = esc_attr( CA_Assessment_Types::normalize( $assessment_type ) );
		ob_start();
		?>
		<div class="ca-trigger-wrap">
			<button class="ca-trigger-btn ca-assessment-trigger" type="button"
				data-ca-assessment="<?php echo esc_attr( $type ); ?>"
				aria-haspopup="dialog" aria-controls="ca-modal">
				<span><?php echo esc_html( $button_text ); ?></span>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
					stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M5 12h14M12 5l7 7-7 7" />
				</svg>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}

	public function maybe_print_modal() {
		if ( self::$modal_printed || ! self::$needs_modal ) {
			return;
		}
		self::$modal_printed = true;
		echo $this->get_modal_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is static HTML from plugin template.
	}

	/**
	 * @return string
	 */
	private function get_modal_markup() {
		ob_start();
		?>
		<div id="ca-modal" class="ca-modal" role="dialog" aria-modal="true" aria-labelledby="ca-modal-title" aria-hidden="true">
			<div class="ca-modal-overlay" id="ca-modal-overlay"></div>

			<div class="ca-modal-panel">

				<div class="ca-modal-header">
					<div class="ca-modal-logo">
						<span class="ca-logo-icon" aria-hidden="true">
							<svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
								<circle cx="14" cy="14" r="14" fill="#aa3130" />
								<path d="M8 14l4 4 8-8" stroke="#fff" stroke-width="2.5" stroke-linecap="round"
									stroke-linejoin="round" />
							</svg>
						</span>
						<span class="ca-logo-text" id="ca-modal-title"><?php echo esc_html__( 'Assessment', 'rtr-custom-assessment' ); ?></span>
					</div>
					<button class="ca-close-btn" id="ca-close-modal" type="button" aria-label="<?php esc_attr_e( 'Close assessment', 'rtr-custom-assessment' ); ?>">
						<?php esc_html_e( 'Close', 'rtr-custom-assessment' ); ?>
					</button>
				</div>

				<div class="ca-progress-container" id="ca-progress-container" aria-hidden="true">
					<div class="ca-progress-track">
						<div class="ca-progress-bar" id="ca-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0"
							aria-valuemax="100" style="width:0%"></div>
					</div>
					<span class="ca-progress-label" id="ca-progress-label">0% Complete</span>
				</div>

				<div class="ca-modal-body" id="ca-modal-body">

					<div id="ca-screen-info" class="ca-screen ca-screen-active">
						<div class="ca-screen-content">
							<div class="ca-intro-badge"><?php esc_html_e( 'Step 1 of 2 — Your Information', 'rtr-custom-assessment' ); ?></div>
							<h2 class="ca-screen-title"><?php esc_html_e( 'Let\'s get started', 'rtr-custom-assessment' ); ?></h2>
							<p class="ca-screen-subtitle"><?php esc_html_e( 'Fill in your details below to begin the assessment. All information is kept confidential.', 'rtr-custom-assessment' ); ?></p>

							<form id="ca-info-form" class="ca-form" novalidate>
								<div class="ca-form-row ca-form-row--2col">
									<div class="ca-field-group">
										<label for="ca-first-name" class="ca-label"><?php esc_html_e( 'First Name', 'rtr-custom-assessment' ); ?> <span class="ca-required"
												aria-hidden="true">*</span></label>
										<input type="text" id="ca-first-name" name="first_name" class="ca-input"
											placeholder="<?php esc_attr_e( 'Jane', 'rtr-custom-assessment' ); ?>" autocomplete="given-name" required>
									</div>
									<div class="ca-field-group">
										<label for="ca-last-name" class="ca-label"><?php esc_html_e( 'Last Name', 'rtr-custom-assessment' ); ?> <span class="ca-required"
												aria-hidden="true">*</span></label>
										<input type="text" id="ca-last-name" name="last_name" class="ca-input" placeholder="<?php esc_attr_e( 'Doe', 'rtr-custom-assessment' ); ?>"
											autocomplete="family-name" required>
									</div>
								</div>

								<div class="ca-form-row">
									<div class="ca-field-group">
										<label for="ca-email" class="ca-label"><?php esc_html_e( 'Email Address', 'rtr-custom-assessment' ); ?> <span class="ca-required"
												aria-hidden="true">*</span></label>
										<input type="email" id="ca-email" name="email" class="ca-input"
											placeholder="<?php esc_attr_e( 'jane@example.com', 'rtr-custom-assessment' ); ?>" autocomplete="email" required>
									</div>
								</div>

								<div class="ca-form-row ca-form-row--2col">
									<div class="ca-field-group">
										<label for="ca-phone" class="ca-label"><?php esc_html_e( 'Phone Number', 'rtr-custom-assessment' ); ?> <span class="ca-required"
												aria-hidden="true">*</span></label>
										<input type="tel" id="ca-phone" name="phone" class="ca-input"
											placeholder="<?php esc_attr_e( '+1 (555) 000-0000', 'rtr-custom-assessment' ); ?>" autocomplete="tel" required>
									</div>
									<div class="ca-field-group">
										<label for="ca-job-title" class="ca-label"><?php esc_html_e( 'Job Title', 'rtr-custom-assessment' ); ?> <span class="ca-required"
												aria-hidden="true">*</span></label>
										<input type="text" id="ca-job-title" name="job_title" class="ca-input"
											placeholder="<?php esc_attr_e( 'Founder & CEO', 'rtr-custom-assessment' ); ?>" autocomplete="organization-title" required>
									</div>
								</div>

								<div class="ca-form-error" id="ca-info-error" role="alert" aria-live="polite"></div>

								<div class="ca-form-actions" style="display: flex; justify-content: flex-end;">
									<button type="submit" class="ca-btn ca-btn--primary ca-btn--lg" id="ca-start-btn">
										<span class="ca-btn-text"><?php esc_html_e( 'Start Assessment', 'rtr-custom-assessment' ); ?></span>
										<span class="ca-btn-loading" aria-hidden="true">
											<svg class="ca-spinner" viewBox="0 0 24 24" fill="none">
												<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"
													stroke-dasharray="60" stroke-dashoffset="20" stroke-linecap="round" />
											</svg>
										</span>
										<svg class="ca-btn-icon" width="20" height="20" viewBox="0 0 24 24" fill="none"
											stroke="currentColor" stroke-width="2" stroke-linecap="round"
											stroke-linejoin="round" aria-hidden="true">
											<path d="M5 12h14M12 5l7 7-7 7" />
										</svg>
									</button>
								</div>
							</form>
						</div>
					</div>

					<div id="ca-screen-questions" class="ca-screen">
						<div class="ca-screen-content ca-screen-content--question">
							<div class="ca-question-meta">
								<span class="ca-category-badge" id="ca-category-label"></span>
								<span class="ca-question-counter" id="ca-question-counter"></span>
							</div>

							<div class="ca-question-scale-note" id="ca-question-scale-note" aria-label="<?php esc_attr_e( 'Rating scale instructions', 'rtr-custom-assessment' ); ?>"></div>

							<p class="ca-question-text" id="ca-question-text"></p>

							<div class="ca-answer-group" id="ca-answer-group" role="group" aria-labelledby="ca-question-text"></div>

							<div class="ca-question-error" id="ca-question-error" role="alert" aria-live="polite"></div>

							<div class="ca-question-actions">
								<button type="button" class="ca-btn ca-btn--ghost" id="ca-back-btn" disabled>
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
										stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
										<path d="M19 12H5M12 19l-7-7 7-7" />
									</svg>
									<?php esc_html_e( 'Back', 'rtr-custom-assessment' ); ?>
								</button>
								<button type="button" class="ca-btn ca-btn--primary" id="ca-next-btn">
									<?php esc_html_e( 'Next', 'rtr-custom-assessment' ); ?>
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
										stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
										<path d="M5 12h14M12 5l7 7-7 7" />
									</svg>
								</button>
							</div>
						</div>
					</div>

					<div id="ca-screen-results" class="ca-screen">
						<div class="ca-results-wrap" id="ca-results-content"></div>
					</div>

					<div id="ca-screen-loading" class="ca-screen ca-screen-loading">
						<div class="ca-loading-spinner">
							<svg class="ca-spinner ca-spinner--lg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
								<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="60"
									stroke-dashoffset="20" stroke-linecap="round" />
							</svg>
							<p><?php esc_html_e( 'Loading…', 'rtr-custom-assessment' ); ?></p>
						</div>
					</div>

					<div id="ca-resume-dialog" class="ca-resume-dialog" hidden>
						<div class="ca-resume-dialog-panel">
							<h3><?php esc_html_e( 'In-progress assessment found', 'rtr-custom-assessment' ); ?></h3>
							<p id="ca-resume-email-text"></p>
							<div class="ca-resume-actions">
								<button type="button" id="ca-resume-continue" class="ca-btn ca-btn--primary"><?php esc_html_e( 'Continue assessment', 'rtr-custom-assessment' ); ?></button>
								<button type="button" id="ca-resume-new" class="ca-btn ca-btn--ghost"><?php esc_html_e( 'Start new assessment', 'rtr-custom-assessment' ); ?></button>
							</div>
						</div>
					</div>

				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
