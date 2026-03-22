<?php
/**
 * Shortcode: [custom_assessment]
 * Renders the trigger button, modal overlay, and enqueues all assets.
 */

if (!defined('ABSPATH')) {
	exit;
}

class CA_Shortcode
{

	public function __construct()
	{
		add_shortcode('custom_assessment', array($this, 'render'));
		add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
	}

	public function enqueue_assets()
	{
		global $post;

		// Only load on pages that have the shortcode
		if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'custom_assessment')) {
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
			array('jquery'),
			CA_VERSION,
			true
		);

		wp_localize_script('ca-scripts', 'CA_Config', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('ca_nonce'),
			'total_questions' => CA_Questions::get_total_count(),
			// Used to order questions on the frontend (smallest -> largest priority).
			// Each entry is keyed by the stable question_index used by backend storage.
			'questions_priority' => array_values(
				array_map(
					function ($q) {
						return array(
							'index' => isset($q['index']) ? (int) $q['index'] : 0,
							'priority' => isset($q['priority']) ? (int) $q['priority'] : 0,
						);
					},
					CA_Questions::get_flat()
				)
			),
			'labels' => array(
				'next' => __('Next', 'custom-assessment'),
				'back' => __('Back', 'custom-assessment'),
				'submit' => __('Submit Assessment', 'custom-assessment'),
				'start' => __('Start Assessment', 'custom-assessment'),
				'loading' => __('Loading…', 'custom-assessment'),
				'error_answer' => __('Please select an answer before continuing.', 'custom-assessment'),
				'error_generic' => __('Something went wrong. Please try again.', 'custom-assessment'),
			),
		));
	}

	public function render($atts)
	{
		$atts = shortcode_atts(array(), $atts, 'custom_assessment');

		ob_start();
		?>
		<!-- Assessment Trigger Button -->
		<div class="ca-trigger-wrap">
			<button class="ca-trigger-btn" id="ca-open-modal" type="button" aria-haspopup="dialog" aria-controls="ca-modal">
				<span>Take the Assessment Now</span>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
					stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M5 12h14M12 5l7 7-7 7" />
				</svg>
			</button>
		</div>

		<!-- Modal Overlay -->
		<div id="ca-modal" class="ca-modal" role="dialog" aria-modal="true" aria-labelledby="ca-modal-title" aria-hidden="true">
			<div class="ca-modal-overlay" id="ca-modal-overlay"></div>

			<div class="ca-modal-panel">

				<!-- Header -->
				<div class="ca-modal-header">
					<div class="ca-modal-logo">
						<span class="ca-logo-icon" aria-hidden="true">
							<svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
								<circle cx="14" cy="14" r="14" fill="#aa3130" />
								<path d="M8 14l4 4 8-8" stroke="#fff" stroke-width="2.5" stroke-linecap="round"
									stroke-linejoin="round" />
							</svg>
						</span>
						<span class="ca-logo-text" id="ca-modal-title">Entrepreneurial Mindset Assessment</span>
					</div>
					<button class="ca-close-btn" id="ca-close-modal" type="button" aria-label="Close assessment">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
							stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<line x1="18" y1="6" x2="6" y2="18" />
							<line x1="6" y1="6" x2="18" y2="18" />
						</svg>
					</button>
				</div>

				<!-- Progress Bar (shown during assessment) -->
				<div class="ca-progress-container" id="ca-progress-container" aria-hidden="true">
					<div class="ca-progress-track">
						<div class="ca-progress-bar" id="ca-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0"
							aria-valuemax="100" style="width:0%"></div>
					</div>
					<span class="ca-progress-label" id="ca-progress-label">0% Complete</span>
				</div>

				<!-- Modal Body -->
				<div class="ca-modal-body" id="ca-modal-body">

					<!-- SCREEN 1: Info Form -->
					<div id="ca-screen-info" class="ca-screen ca-screen-active">
						<div class="ca-screen-content">
							<div class="ca-intro-badge">Step 1 of 2 — Your Information</div>
							<h2 class="ca-screen-title">Let's get started</h2>
							<p class="ca-screen-subtitle">Fill in your details below to begin the assessment. All information is
								kept confidential.</p>

							<form id="ca-info-form" class="ca-form" novalidate>
								<div class="ca-form-row ca-form-row--2col">
									<div class="ca-field-group">
										<label for="ca-first-name" class="ca-label">First Name <span class="ca-required"
												aria-hidden="true">*</span></label>
										<input type="text" id="ca-first-name" name="first_name" class="ca-input"
											placeholder="Jane" autocomplete="given-name" required>
									</div>
									<div class="ca-field-group">
										<label for="ca-last-name" class="ca-label">Last Name <span class="ca-required"
												aria-hidden="true">*</span></label>
										<input type="text" id="ca-last-name" name="last_name" class="ca-input" placeholder="Doe"
											autocomplete="family-name" required>
									</div>
								</div>

								<div class="ca-form-row">
									<div class="ca-field-group">
										<label for="ca-email" class="ca-label">Email Address <span class="ca-required"
												aria-hidden="true">*</span></label>
										<input type="email" id="ca-email" name="email" class="ca-input"
											placeholder="jane@example.com" autocomplete="email" required>
									</div>
								</div>

								<div class="ca-form-row ca-form-row--2col">
									<div class="ca-field-group">
										<label for="ca-phone" class="ca-label">Phone Number <span class="ca-required"
												aria-hidden="true">*</span></label>
										<input type="tel" id="ca-phone" name="phone" class="ca-input"
											placeholder="+1 (555) 000-0000" autocomplete="tel" required>
									</div>
									<div class="ca-field-group">
										<label for="ca-job-title" class="ca-label">Job Title <span class="ca-required"
												aria-hidden="true">*</span></label>
										<input type="text" id="ca-job-title" name="job_title" class="ca-input"
											placeholder="Founder & CEO" autocomplete="organization-title" required>
									</div>
								</div>

								<div class="ca-form-error" id="ca-info-error" role="alert" aria-live="polite"></div>

								<div class="ca-form-actions" style="display: flex; justify-content: flex-end;">
									<button type="submit" class="ca-btn ca-btn--primary ca-btn--lg" id="ca-start-btn">
										<span class="ca-btn-text">Start Assessment</span>
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

					<!-- SCREEN 2: Questions -->
					<div id="ca-screen-questions" class="ca-screen">
						<div class="ca-screen-content ca-screen-content--question">
							<div class="ca-question-meta">
								<span class="ca-category-badge" id="ca-category-label"></span>
								<span class="ca-question-counter" id="ca-question-counter"></span>
							</div>

							<div class="ca-question-scale-note" aria-label="Rating scale instructions">
								Rate this statement on a scale of 1 to 5, where <strong>1</strong> means it is least like you
								and <strong>5</strong> means it is most like you.
							</div>

							<p class="ca-question-text" id="ca-question-text"></p>

							<div class="ca-answer-group" id="ca-answer-group" role="group" aria-labelledby="ca-question-text">
								<?php for ($i = 1; $i <= 5; $i++): ?>
									<label class="ca-answer-option" data-value="<?php echo esc_attr($i); ?>">
										<input type="radio" name="ca_answer" value="<?php echo esc_attr($i); ?>"
											class="ca-answer-radio" aria-label="<?php echo esc_attr($i); ?>">
										<span class="ca-answer-btn">
											<span class="ca-answer-num"><?php echo esc_html($i); ?></span>
											<span class="ca-answer-label">
												<?php
												$labels = array(1 => 'Least like me', 2 => 'Slightly like me', 3 => 'Somewhat like me', 4 => 'Mostly like me', 5 => 'Most like me');
												echo esc_html($labels[$i]);
												?>
											</span>
										</span>
									</label>
								<?php endfor; ?>
							</div>

							<div class="ca-question-error" id="ca-question-error" role="alert" aria-live="polite"></div>

							<div class="ca-question-actions">
								<button type="button" class="ca-btn ca-btn--ghost" id="ca-back-btn" disabled>
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
										stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
										<path d="M19 12H5M12 19l-7-7 7-7" />
									</svg>
									Back
								</button>
								<button type="button" class="ca-btn ca-btn--primary" id="ca-next-btn">
									Next
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
										stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
										<path d="M5 12h14M12 5l7 7-7 7" />
									</svg>
								</button>
							</div>
						</div>
					</div>

					<!-- SCREEN 3: Results -->
					<div id="ca-screen-results" class="ca-screen">
						<div class="ca-results-wrap" id="ca-results-content">
							<!-- Filled dynamically by JS -->
						</div>
					</div>

					<!-- Loading State -->
					<div id="ca-screen-loading" class="ca-screen ca-screen-loading">
						<div class="ca-loading-spinner">
							<svg class="ca-spinner ca-spinner--lg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
								<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="60"
									stroke-dashoffset="20" stroke-linecap="round" />
							</svg>
							<p>Loading…</p>
						</div>
					</div>


					<!-- Resume prompt (centered modal style) -->
					<div id="ca-resume-dialog" class="ca-resume-dialog" hidden>
						<div class="ca-resume-dialog-panel">
							<h3>In-progress assessment found</h3>
							<p id="ca-resume-email-text"></p>
							<div class="ca-resume-actions">
								<button type="button" id="ca-resume-continue" class="ca-btn ca-btn--primary">Continue
									assessment</button>
								<button type="button" id="ca-resume-new" class="ca-btn ca-btn--ghost">Start new
									assessment</button>
							</div>
						</div>
					</div>

				</div><!-- .ca-modal-body -->
			</div><!-- .ca-modal-panel -->
		</div><!-- #ca-modal -->
		<?php
		return ob_get_clean();
	}
}
