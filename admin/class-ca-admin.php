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
		add_action('admin_init', array($this, 'maybe_redirect_legacy_admin_pages'), 0);
		add_action('admin_init', array($this, 'handle_delete_action'));
		add_action('admin_init', array($this, 'handle_export_action'));
		add_action('admin_init', array($this, 'handle_send_email_action'));
		add_action('admin_init', array($this, 'handle_logs_action'));
		add_action('admin_init', array($this, 'handle_categories_action'));
		add_action('admin_init', array($this, 'handle_edit_category_action'));
		add_action('admin_init', array($this, 'handle_questions_action'));
		add_action('wp_ajax_ca_edit_question_ajax', array($this, 'handle_edit_question_ajax'));
		add_action('wp_ajax_ca_unpaid_bulk_send_emails', array($this, 'ajax_unpaid_bulk_send_emails'));
		add_action('admin_menu', array($this, 'register_menu'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
	}

	public function register_menu()
	{
		add_menu_page(
			__('Assessments', 'rtr-custom-assessment'),
			__('Assessments', 'rtr-custom-assessment'),
			'manage_options',
			'custom-assessment-hub',
			array($this, 'render_hub_page'),
			'dashicons-chart-bar',
			56
		);

		add_submenu_page(
			'custom-assessment-hub',
			__('Dashboard', 'rtr-custom-assessment'),
			__('Dashboard', 'rtr-custom-assessment'),
			'manage_options',
			'custom-assessment-hub',
			array($this, 'render_hub_page')
		);

		add_submenu_page(
			'custom-assessment-hub',
			__('Mindset', 'rtr-custom-assessment'),
			__('Mindset', 'rtr-custom-assessment'),
			'manage_options',
			'custom-assessment-mindset',
			array($this, 'render_mindset_section_page')
		);

		add_submenu_page(
			'custom-assessment-hub',
			__('Social Fluency', 'rtr-custom-assessment'),
			__('Social Fluency', 'rtr-custom-assessment'),
			'manage_options',
			'custom-assessment-social',
			array($this, 'render_social_fluency_section_page')
		);

		add_submenu_page(
			'custom-assessment-hub',
			__('Natural Attributes Cataloging', 'rtr-custom-assessment'),
			__('Natural Attributes Cataloging', 'rtr-custom-assessment'),
			'manage_options',
			'custom-assessment-inner',
			array($this, 'render_inner_section_page')
		);

		add_submenu_page(
			'custom-assessment-hub',
			__('All Submissions', 'rtr-custom-assessment'),
			__('All Submissions', 'rtr-custom-assessment'),
			'manage_options',
			'custom-assessment-submissions-all',
			array($this, 'render_all_submissions_page')
		);

		add_submenu_page(
			'custom-assessment-hub',
			__('Unpaid Full Results', 'rtr-custom-assessment'),
			__('Unpaid Full Results', 'rtr-custom-assessment'),
			'manage_options',
			'custom-assessment-unpaid',
			array($this, 'render_unpaid_full_results_page')
		);

		add_submenu_page(
			'custom-assessment-hub',
			__('Settings', 'rtr-custom-assessment'),
			__('Settings', 'rtr-custom-assessment'),
			'manage_options',
			'custom-assessment-settings',
			array($this, 'render_settings_page')
		);

		add_submenu_page(
			'custom-assessment-hub',
			__('Logs', 'rtr-custom-assessment'),
			__('Logs', 'rtr-custom-assessment'),
			'manage_options',
			'custom-assessment-logs',
			array($this, 'render_logs_page')
		);
	}

	/**
	 * Plugin settings (reCAPTCHA for all assessment types).
	 */
	public function render_settings_page()
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'rtr-custom-assessment'));
		}

		$enabled = 'yes' === get_option(CA_Recaptcha::OPTION_ENABLED, 'no');
		$site_key = CA_Recaptcha::get_site_key();
		$secret_key = CA_Recaptcha::get_secret_key();
		$configured = CA_Recaptcha::is_enabled();
		?>
		<div class="wrap ca-admin-wrap">
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-admin-settings"></span>
				<?php esc_html_e('Assessment Settings', 'rtr-custom-assessment'); ?>
			</h1>

			<?php if (isset($_GET['settings-updated'])) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Settings saved.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php" class="ca-settings-form">
				<?php
				settings_fields('ca_recaptcha_settings');
				?>

				<div class="ca-admin-card" style="max-width:720px;">
					<h2 class="ca-admin-card-title"><?php esc_html_e('Google reCAPTCHA', 'rtr-custom-assessment'); ?></h2>
					<p class="description">
						<?php esc_html_e('When enabled, visitors must pass reCAPTCHA on the user information step before starting any assessment (Natural Attributes, Social Fluency, Entrepreneurial Mindset, or Bundle).', 'rtr-custom-assessment'); ?>
					</p>

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e('Enable reCAPTCHA', 'rtr-custom-assessment'); ?></th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr(CA_Recaptcha::OPTION_ENABLED); ?>" value="yes" <?php checked($enabled); ?>>
										<?php esc_html_e('Require verification before starting assessment', 'rtr-custom-assessment'); ?>
									</label>
									<?php if ($enabled && !$configured) : ?>
										<p class="description" style="color:#b32d2e;">
											<?php esc_html_e('Add both keys below for reCAPTCHA to take effect on the site.', 'rtr-custom-assessment'); ?>
										</p>
									<?php elseif ($configured) : ?>
										<p class="description" style="color:#1e4620;">
											<?php esc_html_e('reCAPTCHA is active for all assessment types.', 'rtr-custom-assessment'); ?>
										</p>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="ca-recaptcha-site-key"><?php esc_html_e('Site key', 'rtr-custom-assessment'); ?></label>
								</th>
								<td>
									<input type="text" id="ca-recaptcha-site-key" name="<?php echo esc_attr(CA_Recaptcha::OPTION_SITE_KEY); ?>" value="<?php echo esc_attr($site_key); ?>" class="regular-text" autocomplete="off">
									<p class="description"><?php esc_html_e('Public key from Google reCAPTCHA admin (v2 “I’m not a robot” Checkbox).', 'rtr-custom-assessment'); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="ca-recaptcha-secret-key"><?php esc_html_e('Secret key', 'rtr-custom-assessment'); ?></label>
								</th>
								<td>
									<input type="password" id="ca-recaptcha-secret-key" name="<?php echo esc_attr(CA_Recaptcha::OPTION_SECRET_KEY); ?>" value="<?php echo esc_attr($secret_key); ?>" class="regular-text" autocomplete="off">
									<p class="description"><?php esc_html_e('Secret key used for server-side verification.', 'rtr-custom-assessment'); ?></p>
								</td>
							</tr>
						</tbody>
					</table>

					<?php submit_button(__('Save settings', 'rtr-custom-assessment')); ?>
				</div>
			</form>

			<form method="post" action="options.php" class="ca-settings-form" style="margin-top:32px;">
				<?php settings_fields('ca_course_settings'); ?>

				<div class="ca-admin-card" style="max-width:720px;">
					<h2 class="ca-admin-card-title"><?php esc_html_e('Course Access', 'rtr-custom-assessment'); ?></h2>
					<p class="description">
						<?php esc_html_e('Configure the Personal Equity Course paywall. The course URL is stored securely here and is only revealed to users after a completed WooCommerce payment.', 'rtr-custom-assessment'); ?>
					</p>

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="ca-course-name"><?php esc_html_e('Course Name', 'rtr-custom-assessment'); ?></label>
								</th>
								<td>
									<input type="text" id="ca-course-name" name="<?php echo esc_attr(CA_Course::OPTION_NAME); ?>"
										value="<?php echo esc_attr(CA_Course::get_course_name()); ?>" class="regular-text">
									<p class="description"><?php esc_html_e('Displayed on the button modal and WooCommerce order.', 'rtr-custom-assessment'); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="ca-course-price"><?php esc_html_e('Price (USD)', 'rtr-custom-assessment'); ?></label>
								</th>
								<td>
									<input type="number" id="ca-course-price" name="<?php echo esc_attr(CA_Course::OPTION_PRICE); ?>"
										value="<?php echo esc_attr(CA_Course::get_course_price()); ?>"
										class="small-text" min="0" step="0.01">
									<p class="description"><?php esc_html_e('Set to 0 to grant free access after form submission.', 'rtr-custom-assessment'); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="ca-course-url"><?php esc_html_e('Course URL', 'rtr-custom-assessment'); ?></label>
								</th>
								<td>
									<input type="url" id="ca-course-url" name="<?php echo esc_attr(CA_Course::OPTION_URL); ?>"
										value="<?php echo esc_attr(CA_Course::get_course_url()); ?>" class="regular-text"
										placeholder="https://...">
									<p class="description"><?php esc_html_e('The private S3 or hosted course URL. Never exposed to the public — only returned after payment verification.', 'rtr-custom-assessment'); ?></p>
								</td>
							</tr>
						</tbody>
					</table>

					<?php submit_button(__('Save course settings', 'rtr-custom-assessment')); ?>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Redirect old ?page= slugs to section screens (Mindset / Social) with ca_tab.
	 */
	public function maybe_redirect_legacy_admin_pages()
	{
		if (!is_admin() || !current_user_can('manage_options')) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		if (!isset($_GET['page'])) {
			return;
		}
		$page = sanitize_key(wp_unslash($_GET['page']));
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$legacy = array(
			'custom-assessment-dashboard' => array(
				'page' => 'custom-assessment-mindset',
				'ca_tab' => 'dashboard',
			),
			'custom-assessment-submissions' => array(
				'page' => 'custom-assessment-mindset',
				'ca_tab' => 'submissions',
			),
			'custom-assessment-questions' => array(
				'page' => 'custom-assessment-mindset',
				'ca_tab' => 'questions',
			),
			'custom-assessment-categories' => array(
				'page' => 'custom-assessment-mindset',
				'ca_tab' => 'categories',
			),
			'custom-assessment-sf-dashboard' => array(
				'page' => 'custom-assessment-social',
				'ca_tab' => 'dashboard',
			),
			'custom-assessment-sf-submissions' => array(
				'page' => 'custom-assessment-social',
				'ca_tab' => 'submissions',
			),
			'custom-assessment-sf-questions' => array(
				'page' => 'custom-assessment-social',
				'ca_tab' => 'questions',
			),
			'custom-assessment-sf-categories' => array(
				'page' => 'custom-assessment-social',
				'ca_tab' => 'categories',
			),
		);

		if (!isset($legacy[$page])) {
			return;
		}

		$query = isset($_GET) ? wp_unslash($_GET) : array();
		unset($query['page']);
		$query = array_merge($query, $legacy[$page]);
		wp_safe_redirect(add_query_arg($query, admin_url('admin.php')));
		exit;
	}

	/**
	 * Overview: links into Mindset, Social Fluency, and Natural Attributes Cataloging.
	 */
	public function render_hub_page()
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'rtr-custom-assessment'));
		}

		$mindset_url = add_query_arg(
			array(
				'page' => 'custom-assessment-mindset',
				'ca_tab' => 'dashboard',
			),
			admin_url('admin.php')
		);
		$social_url = add_query_arg(
			array(
				'page' => 'custom-assessment-social',
				'ca_tab' => 'dashboard',
			),
			admin_url('admin.php')
		);
		$inner_url = add_query_arg(
			array(
				'page' => 'custom-assessment-inner',
				'ca_tab' => 'dashboard',
			),
			admin_url('admin.php')
		);
		?>
		<div class="wrap ca-admin-wrap ca-assessment-hub-wrap">
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-chart-bar"></span>
				<?php esc_html_e('Dashboard', 'rtr-custom-assessment'); ?>
			</h1>
			<p class="description"><?php esc_html_e('Open an assessment to view its dashboard, submissions, questions, and categories.', 'rtr-custom-assessment'); ?></p>
			<div class="ca-dashboard-hub-grid">
				<a class="ca-dashboard-hub-card" href="<?php echo esc_url($mindset_url); ?>">
					<h2><?php esc_html_e('Mindset', 'rtr-custom-assessment'); ?></h2>
					<p><?php esc_html_e('Entrepreneurial Mindset assessment', 'rtr-custom-assessment'); ?></p>
				</a>
				<a class="ca-dashboard-hub-card" href="<?php echo esc_url($social_url); ?>">
					<h2><?php esc_html_e('Social Fluency', 'rtr-custom-assessment'); ?></h2>
					<p><?php esc_html_e('Social Fluency assessment', 'rtr-custom-assessment'); ?></p>
				</a>
				<a class="ca-dashboard-hub-card" href="<?php echo esc_url($inner_url); ?>">
					<h2><?php esc_html_e('Natural Attributes Cataloging', 'rtr-custom-assessment'); ?></h2>
					<p><?php esc_html_e('Yes / No natural attributes catalog', 'rtr-custom-assessment'); ?></p>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Mindset section: routes by ca_tab.
	 */
	public function render_mindset_section_page()
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'rtr-custom-assessment'));
		}

		$tab = $this->get_current_ca_tab();
		switch ($tab) {
			case 'submissions':
				$this->render_list_page();
				return;
			case 'questions':
				$this->render_questions_page();
				return;
			case 'categories':
				$this->render_categories_page();
				return;
			default:
				$this->render_dashboard_page();
				return;
		}
	}

	/**
	 * Social Fluency section: routes by ca_tab.
	 */
	public function render_social_fluency_section_page()
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'rtr-custom-assessment'));
		}

		$tab = $this->get_current_ca_tab();
		switch ($tab) {
			case 'submissions':
				$this->render_sf_list_page();
				return;
			case 'questions':
				$this->render_sf_questions_page();
				return;
			case 'categories':
				$this->render_sf_categories_page();
				return;
			default:
				$this->render_sf_dashboard_page();
				return;
		}
	}

	/**
	 * Natural Attributes Cataloging section: routes by ca_tab.
	 */
	public function render_inner_section_page()
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'rtr-custom-assessment'));
		}

		$tab = $this->get_current_ca_tab();
		switch ($tab) {
			case 'submissions':
				$this->render_inner_list_page();
				return;
			case 'questions':
				$this->render_inner_questions_page();
				return;
			case 'categories':
				$this->render_inner_categories_page();
				return;
			default:
				$this->render_inner_dashboard_page();
				return;
		}
	}

	/**
	 * Natural Attributes Cataloging dashboard.
	 */
	public function render_inner_dashboard_page()
	{
		$this->render_dashboard_for_assessment(CA_Assessment_Types::INNER_DIMENSIONS);
	}

	/**
	 * Natural Attributes Cataloging submissions.
	 */
	public function render_inner_list_page()
	{
		$this->render_submissions_list_for_type(CA_Assessment_Types::INNER_DIMENSIONS);
	}

	/**
	 * Current tab for section screens (custom-assessment-mindset / custom-assessment-social).
	 *
	 * @return string dashboard|submissions|questions|categories
	 */
	private function get_current_ca_tab()
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$tab = isset($_GET['ca_tab']) ? sanitize_key(wp_unslash($_GET['ca_tab'])) : 'dashboard';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$allowed = array('dashboard', 'submissions', 'questions', 'categories');
		return in_array($tab, $allowed, true) ? $tab : 'dashboard';
	}

	/**
	 * Whether we are on a tabbed assessment section admin screen.
	 */
	private function is_assessment_section_screen()
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return in_array($page, array('custom-assessment-mindset', 'custom-assessment-social', 'custom-assessment-inner'), true);
	}

	/**
	 * Admin page slug for a tabbed assessment section.
	 *
	 * @param string $assessment_type Normalized type.
	 * @return string
	 */
	private function admin_section_page_slug_for_type($assessment_type)
	{
		$t = CA_Assessment_Types::normalize($assessment_type);
		if (CA_Assessment_Types::SOCIAL_FLUENCY === $t) {
			return 'custom-assessment-social';
		}
		if (CA_Assessment_Types::INNER_DIMENSIONS === $t) {
			return 'custom-assessment-inner';
		}
		return 'custom-assessment-mindset';
	}

	/**
	 * Horizontal tabs for Mindset / Social Fluency section screens.
	 *
	 * @param string $assessment_type Normalized assessment type.
	 * @param string $current_tab     Active tab slug.
	 */
	private function render_assessment_section_nav_tabs($assessment_type, $current_tab)
	{
		$section_page = $this->admin_section_page_slug_for_type($assessment_type);

		$tabs = array(
			'dashboard' => __('Dashboard', 'rtr-custom-assessment'),
			'submissions' => __('Submissions', 'rtr-custom-assessment'),
			'questions' => __('Questions', 'rtr-custom-assessment'),
			'categories' => __('Categories', 'rtr-custom-assessment'),
		);

		echo '<nav class="nav-tab-wrapper ca-assessment-nav-tabs" aria-label="' . esc_attr__('Assessment sections', 'rtr-custom-assessment') . '">';
		foreach ($tabs as $slug => $label) {
			$url = add_query_arg(
				array(
					'page' => $section_page,
					'ca_tab' => $slug,
				),
				admin_url('admin.php')
			);
			$classes = 'nav-tab' . ($slug === $current_tab ? ' nav-tab-active' : '');
			printf(
				'<a href="%1$s" class="%2$s">%3$s</a>',
				esc_url($url),
				esc_attr($classes),
				esc_html($label)
			);
		}
		echo '</nav>';
	}

	/**
	 * Query args for a screen inside Mindset or Social Fluency (page + ca_tab).
	 *
	 * @param string $screen          dashboard|submissions|questions|categories
	 * @param string $assessment_type Normalized type.
	 * @return array<string, string>
	 */
	private function admin_screen_query_args($screen, $assessment_type)
	{
		$section_page = $this->admin_section_page_slug_for_type($assessment_type);
		$tab_map = array(
			'dashboard' => 'dashboard',
			'submissions' => 'submissions',
			'questions' => 'questions',
			'categories' => 'categories',
		);
		$tab = isset($tab_map[$screen]) ? $tab_map[$screen] : 'dashboard';
		return array(
			'page' => $section_page,
			'ca_tab' => $tab,
		);
	}

	/**
	 * Full admin URL for a Mindset / Social Fluency screen.
	 *
	 * @param string $screen          dashboard|submissions|questions|categories
	 * @param string $assessment_type Normalized type.
	 * @return string
	 */
	private function admin_screen_url($screen, $assessment_type)
	{
		return add_query_arg($this->admin_screen_query_args($screen, $assessment_type), admin_url('admin.php'));
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

		if ($this->is_unpaid_full_results_admin_screen($hook)) {
			wp_enqueue_editor();
			$tab = $this->get_unpaid_admin_tab();
			wp_localize_script(
				'ca-admin-scripts',
				'caUnpaidBulkEmail',
				array(
					'ajaxUrl' => admin_url('admin-ajax.php'),
					'nonce' => wp_create_nonce('ca_unpaid_bulk_email'),
					'tab' => $tab,
					'defaults' => $this->get_unpaid_bulk_email_defaults($tab),
					'defaultCc' => (string) get_option('ca_unpaid_bulk_email_default_cc', ''),
					'strings' => array(
						'selectOne' => __('Select at least one customer.', 'rtr-custom-assessment'),
						'sending' => __('Sending…', 'rtr-custom-assessment'),
						'sent' => __('Emails sent successfully.', 'rtr-custom-assessment'),
						'partial' => __('Some emails could not be sent.', 'rtr-custom-assessment'),
						'failed' => __('Could not send emails.', 'rtr-custom-assessment'),
						'confirmSend' => __('Send this email to all selected recipients?', 'rtr-custom-assessment'),
						'sendEmails' => __('Send emails', 'rtr-custom-assessment'),
						'reloading' => __('Reloading page in %d seconds…', 'rtr-custom-assessment'),
						'viewTimestamp' => __('View timestamp', 'rtr-custom-assessment'),
						'emailHistoryTitle' => __('Email send history', 'rtr-custom-assessment'),
						'noEmailHistory' => __('No send history recorded.', 'rtr-custom-assessment'),
					),
				)
			);
		}
	}

	/**
	 * Whether the current admin screen is Unpaid Full Results.
	 *
	 * @param string $hook_suffix Admin page hook.
	 * @return bool
	 */
	private function is_unpaid_full_results_admin_screen($hook_suffix)
	{
		return false !== strpos((string) $hook_suffix, 'custom-assessment-unpaid');
	}

	/**
	 * Active tab on the unpaid full-results screen.
	 *
	 * @return string inner|social|bundle
	 */
	private function get_unpaid_admin_tab()
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$tab = isset($_GET['ca_unpaid_tab']) ? sanitize_key(wp_unslash($_GET['ca_unpaid_tab'])) : 'inner';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$allowed = array('inner', 'social', 'bundle');
		return in_array($tab, $allowed, true) ? $tab : 'inner';
	}

	/**
	 * Assessment type implied by the current admin ?page=.
	 *
	 * @return string|null Normalized type, or null for all-submissions list.
	 */
	private function admin_current_assessment_type()
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ('custom-assessment-submissions-all' === $page) {
			return null;
		}
		if ('custom-assessment-inner' === $page) {
			return CA_Assessment_Types::INNER_DIMENSIONS;
		}
		if ('custom-assessment-social' === $page) {
			return CA_Assessment_Types::SOCIAL_FLUENCY;
		}
		if ('custom-assessment-mindset' === $page) {
			return CA_Assessment_Types::MINDSET;
		}
		if (0 === strpos($page, 'custom-assessment-sf-')) {
			return CA_Assessment_Types::SOCIAL_FLUENCY;
		}
		return CA_Assessment_Types::MINDSET;
	}

	/**
	 * Mindset categories screen (legacy slug or section + tab).
	 */
	private function is_mindset_categories_admin_page()
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		$tab = isset($_GET['ca_tab']) ? sanitize_key(wp_unslash($_GET['ca_tab'])) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ('custom-assessment-categories' === $page) {
			return true;
		}
		return ('custom-assessment-mindset' === $page && 'categories' === $tab);
	}

	/**
	 * Mindset questions screen (legacy slug or section + tab).
	 */
	private function is_mindset_questions_admin_page()
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		$tab = isset($_GET['ca_tab']) ? sanitize_key(wp_unslash($_GET['ca_tab'])) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ('custom-assessment-questions' === $page) {
			return true;
		}
		return ('custom-assessment-mindset' === $page && 'questions' === $tab);
	}

	/**
	 * Social Fluency questions screen (section + tab).
	 */
	private function is_social_fluency_questions_admin_page()
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		$tab = isset($_GET['ca_tab']) ? sanitize_key(wp_unslash($_GET['ca_tab'])) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return ('custom-assessment-social' === $page && 'questions' === $tab);
	}

	/**
	 * Natural Attributes Cataloging questions screen (section + tab).
	 */
	private function is_inner_questions_admin_page()
	{
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
		$tab = isset($_GET['ca_tab']) ? sanitize_key(wp_unslash($_GET['ca_tab'])) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return ('custom-assessment-inner' === $page && 'questions' === $tab);
	}

	/**
	 * Option keys for admin-managed questions (custom rows, overrides, empty categories).
	 *
	 * @param string $assessment_type Normalized type.
	 * @return array{custom_questions:string,question_overrides:string,custom_categories:string}
	 */
	private function questions_storage_keys($assessment_type)
	{
		$t = CA_Assessment_Types::normalize($assessment_type);
		if (CA_Assessment_Types::SOCIAL_FLUENCY === $t) {
			return array(
				'custom_questions' => 'ca_sf_custom_questions',
				'question_overrides' => 'ca_sf_question_overrides',
				'custom_categories' => 'ca_sf_custom_categories',
			);
		}
		if (CA_Assessment_Types::INNER_DIMENSIONS === $t) {
			return array(
				'custom_questions' => 'ca_inner_custom_questions',
				'question_overrides' => 'ca_inner_question_overrides',
				'custom_categories' => 'ca_inner_custom_categories',
			);
		}
		return array(
			'custom_questions' => 'ca_custom_questions',
			'question_overrides' => 'ca_question_overrides',
			'custom_categories' => 'ca_custom_categories',
		);
	}

	/**
	 * @param string $assessment_type Normalized assessment type.
	 * @return array
	 */
	private function get_admin_questions_flat($assessment_type)
	{
		$t = CA_Assessment_Types::normalize($assessment_type);
		if (CA_Assessment_Types::SOCIAL_FLUENCY === $t) {
			return CA_Social_Fluency_Questions::get_flat();
		}
		if (CA_Assessment_Types::INNER_DIMENSIONS === $t) {
			return CA_Inner_Dimensions_Questions::get_flat();
		}
		return CA_Questions::get_flat();
	}

	/**
	 * @param string $assessment_type Normalized assessment type.
	 * @return array
	 */
	private function get_admin_questions_categories($assessment_type)
	{
		$t = CA_Assessment_Types::normalize($assessment_type);
		if (CA_Assessment_Types::SOCIAL_FLUENCY === $t) {
			return CA_Social_Fluency_Questions::get_categories();
		}
		if (CA_Assessment_Types::INNER_DIMENSIONS === $t) {
			return CA_Inner_Dimensions_Questions::get_categories();
		}
		return CA_Questions::get_categories();
	}

	/**
	 * Allowed admin pages for submission delete / export / email actions.
	 *
	 * @return string[]
	 */
	private function admin_submission_action_pages()
	{
		return array(
			'custom-assessment-mindset',
			'custom-assessment-social',
			'custom-assessment-inner',
			'custom-assessment-submissions-all',
			'custom-assessment-dashboard',
			'custom-assessment-submissions',
			'custom-assessment-sf-dashboard',
			'custom-assessment-sf-submissions',
		);
	}

	/**
	 * Handle delete action early on admin_init before any output.
	 */
	public function handle_delete_action()
	{
		if (!isset($_GET['page']) || !in_array($_GET['page'], $this->admin_submission_action_pages(), true)) {
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
		if (!isset($_GET['page']) || !in_array($_GET['page'], $this->admin_submission_action_pages(), true)) {
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
			$export_scope = $this->admin_current_assessment_type();
			$all_submissions = null === $export_scope
				? CA_Database::get_all_submissions()
				: CA_Database::get_all_submissions($export_scope);

			if ('csv' === $format) {
				CA_Logger::log('admin_export_all', 'success', 'Export all CSV requested.');
				$this->export_all_as_csv($all_submissions, $export_scope, null === $export_scope);
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
		if (!isset($_GET['page']) || !in_array($_GET['page'], $this->admin_submission_action_pages(), true)) {
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
		if (!$this->is_mindset_categories_admin_page()) {
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
				$redirect_url = add_query_arg('message', $message, $this->admin_screen_url('categories', CA_Assessment_Types::MINDSET));
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
		if (!$this->is_mindset_categories_admin_page()) {
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

				$redirect_url = add_query_arg('message', $message, $this->admin_screen_url('categories', CA_Assessment_Types::MINDSET));
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
		$questions_assessment_type = null;
		if ($this->is_mindset_questions_admin_page()) {
			$questions_assessment_type = CA_Assessment_Types::MINDSET;
		} elseif ($this->is_social_fluency_questions_admin_page()) {
			$questions_assessment_type = CA_Assessment_Types::SOCIAL_FLUENCY;
		} elseif ($this->is_inner_questions_admin_page()) {
			$questions_assessment_type = CA_Assessment_Types::INNER_DIMENSIONS;
		} else {
			return;
		}

		if (!current_user_can('manage_options')) {
			return;
		}

		if (
			isset($_POST['ca_action'], $_POST['_wpnonce']) &&
			'export_questions_json' === $_POST['ca_action'] &&
			wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_export_questions_json_action')
		) {
			$this->export_questions_json($questions_assessment_type);
			exit;
		}

		if (
			isset($_POST['ca_action'], $_POST['_wpnonce']) &&
			'import_questions_json' === $_POST['ca_action'] &&
			wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_import_questions_json_action')
		) {
			$message = $this->import_questions_json($questions_assessment_type);
			$redirect_url = add_query_arg('message', $message, $this->admin_screen_url('questions', $questions_assessment_type));
			wp_safe_redirect(esc_url_raw($redirect_url));
			exit;
		}

		if (
			isset($_POST['ca_action'], $_POST['_wpnonce']) &&
			'delete_all_questions' === $_POST['ca_action'] &&
			wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_delete_all_questions_action')
		) {
			$this->delete_all_questions_config($questions_assessment_type);
			$redirect_url = add_query_arg('message', 'questions_deleted_all', $this->admin_screen_url('questions', $questions_assessment_type));
			wp_safe_redirect(esc_url_raw($redirect_url));
			exit;
		}

		if (
			isset($_POST['ca_action'], $_POST['_wpnonce'], $_POST['question_index']) &&
			'delete_question' === $_POST['ca_action'] &&
			wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'ca_delete_question_action')
		) {
			$question_index = absint($_POST['question_index']);
			if ($question_index >= 0) {
				$this->delete_question($question_index, $questions_assessment_type);
				$message = 'question_deleted';

				$redirect_url = add_query_arg('message', $message, $this->admin_screen_url('questions', $questions_assessment_type));
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
				$flat_questions = $this->get_admin_questions_flat($questions_assessment_type);
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
					$redirect_url = add_query_arg('message', $message, $this->admin_screen_url('questions', $questions_assessment_type));
					wp_safe_redirect(esc_url_raw($redirect_url));
					exit;
				}

				$edited = $this->edit_question($question_index, $new_category, $new_question_text, $new_priority, $questions_assessment_type);
				$message = $edited ? 'question_edited' : 'question_edit_failed';

				$redirect_url = add_query_arg('message', $message, $this->admin_screen_url('questions', $questions_assessment_type));
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
				$redirect_url = add_query_arg('message', $message, $this->admin_screen_url('questions', $questions_assessment_type));
				wp_safe_redirect(esc_url_raw($redirect_url));
				exit;
			}

			$flat_questions = $this->get_admin_questions_flat($questions_assessment_type);
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
				$redirect_url = add_query_arg('message', $message, $this->admin_screen_url('questions', $questions_assessment_type));
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
				$redirect_url = add_query_arg('message', $message, $this->admin_screen_url('questions', $questions_assessment_type));
				wp_safe_redirect(esc_url_raw($redirect_url));
				exit;
			}

			$updated_any = false;
			foreach ($targets as $idx => $t) {
				$ok = $this->edit_question($idx, $t['category'], $t['text'], $t['priority'], $questions_assessment_type);
				if ($ok) {
					$updated_any = true;
				}
			}

			$message = $updated_any ? 'bulk_edit_success' : 'bulk_edit_failed';
			$redirect_url = add_query_arg('message', $message, $this->admin_screen_url('questions', $questions_assessment_type));
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
				$flat_questions = $this->get_admin_questions_flat($questions_assessment_type);
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
					$redirect_url = add_query_arg('message', $message, $this->admin_screen_url('questions', $questions_assessment_type));
					wp_safe_redirect(esc_url_raw($redirect_url));
					exit;
				}

				$this->add_question($question_text, $question_category, $question_priority, $questions_assessment_type);
				$message = 'question_added';

				$redirect_url = add_query_arg('message', $message, $this->admin_screen_url('questions', $questions_assessment_type));
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
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified above.
		$raw_atype = isset($_POST['assessment_type']) ? sanitize_key(wp_unslash($_POST['assessment_type'])) : CA_Assessment_Types::MINDSET;
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$assessment_type = CA_Assessment_Types::normalize($raw_atype);
		if (CA_Assessment_Types::SOCIAL_FLUENCY !== $assessment_type && CA_Assessment_Types::INNER_DIMENSIONS !== $assessment_type) {
			$assessment_type = CA_Assessment_Types::MINDSET;
		}

		if ($question_index < 0 || '' === $new_category || '' === $new_question_text || $new_priority <= 0) {
			wp_send_json_error(array('message' => 'Invalid input.'), 400);
		}

		// Enforce unique priority within the same category (except the current question).
		$flat_questions = $this->get_admin_questions_flat($assessment_type);
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

		$edited = $this->edit_question($question_index, $new_category, $new_question_text, $new_priority, $assessment_type);
		if (!$edited) {
			wp_send_json_error(array('message' => 'Unable to update this question.'), 404);
		}

		$updated = CA_Assessment_Registry::get_question($assessment_type, $question_index);
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
		$sub_type = CA_Assessment_Types::from_submission($submission);
		$scale_max = CA_Assessment_Types::get_scale_max($sub_type);
		$flat_q = CA_Assessment_Registry::get_flat($sub_type);
		$total_q = CA_Assessment_Registry::get_total_count($sub_type);

		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="submission_' . $submission_id . '_' . sanitize_file_name($submission->first_name . '_' . $submission->last_name) . '.csv"');

		$output = fopen('php://output', 'w');
		fputcsv($output, array('Respondent Information'));
		fputcsv($output, array('Field', 'Value'));
		fputcsv($output, array('Name', $submission->first_name . ' ' . $submission->last_name));
		fputcsv($output, array('Email', $submission->email));
		fputcsv($output, array('Phone', $submission->phone));
		fputcsv($output, array('Job Title', $submission->job_title));
		fputcsv($output, array('Assessment Type', $sub_type));
		fputcsv($output, array('Total Score', $submission->total_score . ' / ' . ($total_q * $scale_max)));
		fputcsv($output, array('Average Score', number_format($submission->average_score, 2) . ' / ' . number_format((float) $scale_max, 2)));
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
				CA_Scoring::get_category_summary($cat->category_name, (float) $cat->average, $sub_type)
			));
		}

		fputcsv($output, array());
		fputcsv($output, array('Question Responses'));
		fputcsv($output, array('Question', 'Response'));
		foreach ($flat_q as $q) {
			$idx = isset($q['index']) ? (int) $q['index'] : 0;
			$answer = isset($answers[$idx]) ? $answers[$idx] : null;
			fputcsv($output, array($q['text'], $answer ? $answer : 'No answer'));
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing a stream opened on php://output.
		fclose($output);
	}

	/**
	 * Export all submissions as CSV.
	 *
	 * @param array       $submissions                    List of submission objects.
	 * @param string|null $question_column_assessment_type When not mixed, question columns use this set.
	 * @param bool        $mixed_types                    True when rows may mix mindset + social (no per-question columns).
	 */
	private function export_all_as_csv($submissions, $question_column_assessment_type = null, $mixed_types = false)
	{
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="all_submissions_' . gmdate('Ymd_His') . '.csv"');

		$output = fopen('php://output', 'w');

		if ($mixed_types) {
			$header = array(
				'ID',
				'First Name',
				'Last Name',
				'Email',
				'Phone',
				'Job Title',
				'Assessment Type',
				'Status',
				'Total Score',
				'Average Score',
				'Created At',
				'Updated At',
				'Answers (JSON)',
				'Category Scores (name:subtotal:average)',
				'Category Summaries (name:summary)',
			);
			fputcsv($output, $header);

			foreach ($submissions as $submission) {
				$row_sub_type = CA_Assessment_Types::from_submission($submission);
				$submission_id = isset($submission->id) ? absint($submission->id) : 0;
				$answers_map = $submission_id > 0 ? CA_Database::get_answers($submission_id) : array();
				$row = array(
					$submission_id,
					isset($submission->first_name) ? $submission->first_name : '',
					isset($submission->last_name) ? $submission->last_name : '',
					isset($submission->email) ? $submission->email : '',
					isset($submission->phone) ? $submission->phone : '',
					isset($submission->job_title) ? $submission->job_title : '',
					$row_sub_type,
					isset($submission->status) ? $submission->status : '',
					isset($submission->total_score) ? $submission->total_score : '',
					isset($submission->average_score) ? $submission->average_score : '',
					isset($submission->created_at) ? $submission->created_at : '',
					isset($submission->updated_at) ? $submission->updated_at : '',
					wp_json_encode($answers_map),
				);
				$category_scores = $submission_id > 0 ? CA_Database::get_category_scores($submission_id) : array();
				$scores_summary = array();
				$text_summary = array();
				foreach ($category_scores as $cat) {
					$cat_name = isset($cat->category_name) ? (string) $cat->category_name : '';
					$cat_subtotal = isset($cat->subtotal) ? (int) $cat->subtotal : 0;
					$cat_average = isset($cat->average) ? (float) $cat->average : 0.0;
					$scores_summary[] = $cat_name . ':' . $cat_subtotal . ':' . number_format($cat_average, 2);
					$text_summary[] = $cat_name . ':' . CA_Scoring::get_category_summary($cat_name, $cat_average, $row_sub_type);
				}
				$row[] = implode(' | ', $scores_summary);
				$row[] = implode(' | ', $text_summary);
				fputcsv($output, $row);
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing a stream opened on php://output.
			fclose($output);
			return;
		}

		$q_type = null !== $question_column_assessment_type
			? CA_Assessment_Types::normalize($question_column_assessment_type)
			: CA_Assessment_Types::MINDSET;
		$flat_questions = CA_Assessment_Registry::get_flat($q_type);
		$header = array(
			'ID',
			'First Name',
			'Last Name',
			'Email',
			'Phone',
			'Job Title',
			'Assessment Type',
			'Status',
			'Total Score',
			'Average Score',
			'Created At',
			'Updated At',
		);

		foreach ($flat_questions as $idx => $question) {
			$header[] = sprintf(
				'Q%d: %s',
				$idx + 1,
				isset($question['text']) ? (string) $question['text'] : ''
			);
		}

		$header[] = 'Category Scores (name:subtotal:average)';
		$header[] = 'Category Summaries (name:summary)';

		fputcsv($output, $header);

		foreach ($submissions as $submission) {
			$row_sub_type = CA_Assessment_Types::from_submission($submission);
			$row = array(
				isset($submission->id) ? $submission->id : '',
				isset($submission->first_name) ? $submission->first_name : '',
				isset($submission->last_name) ? $submission->last_name : '',
				isset($submission->email) ? $submission->email : '',
				isset($submission->phone) ? $submission->phone : '',
				isset($submission->job_title) ? $submission->job_title : '',
				$row_sub_type,
				isset($submission->status) ? $submission->status : '',
				isset($submission->total_score) ? $submission->total_score : '',
				isset($submission->average_score) ? $submission->average_score : '',
				isset($submission->created_at) ? $submission->created_at : '',
				isset($submission->updated_at) ? $submission->updated_at : '',
			);

			$submission_id = isset($submission->id) ? absint($submission->id) : 0;
			$answers_map = $submission_id > 0 ? CA_Database::get_answers($submission_id) : array();
			foreach ($flat_questions as $question) {
				$q_index = isset($question['index']) ? (int) $question['index'] : 0;
				$answer = isset($answers_map[$q_index]) ? $answers_map[$q_index] : '';
				$row[] = ('' !== $answer && null !== $answer) ? $answer : 'No answer';
			}

			$category_scores = $submission_id > 0 ? CA_Database::get_category_scores($submission_id) : array();
			$scores_summary = array();
			$text_summary = array();
			foreach ($category_scores as $cat) {
				$cat_name = isset($cat->category_name) ? (string) $cat->category_name : '';
				$cat_subtotal = isset($cat->subtotal) ? (int) $cat->subtotal : 0;
				$cat_average = isset($cat->average) ? (float) $cat->average : 0.0;
				$scores_summary[] = $cat_name . ':' . $cat_subtotal . ':' . number_format($cat_average, 2);
				$text_summary[] = $cat_name . ':' . CA_Scoring::get_category_summary($cat_name, $cat_average, $row_sub_type);
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
					'assessment_type' => CA_Assessment_Types::from_submission($submission),
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
		$sub_type = CA_Assessment_Types::from_submission($submission);
		$scale_max = CA_Assessment_Types::get_scale_max($sub_type);
		$flat_q = CA_Assessment_Registry::get_flat($sub_type);
		$total_q = CA_Assessment_Registry::get_total_count($sub_type);

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
					<div><span class="info-label">Total Score:</span> ' . esc_html($submission->total_score . ' / ' . ($total_q * $scale_max)) . '</div>
					<div><span class="info-label">Average Score:</span> ' . esc_html(number_format($submission->average_score, 2) . ' / ' . number_format((float) $scale_max, 2)) . '</div>
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
				<td>' . esc_html(CA_Scoring::get_category_summary($cat->category_name, (float) $cat->average, $sub_type)) . '</td>
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

		foreach ($flat_q as $q) {
			$idx = isset($q['index']) ? (int) $q['index'] : 0;
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
	 * Mindset assessment dashboard.
	 */
	public function render_dashboard_page()
	{
		$this->render_dashboard_for_assessment(CA_Assessment_Types::MINDSET);
	}

	/**
	 * Social Fluency assessment dashboard.
	 */
	public function render_sf_dashboard_page()
	{
		$this->render_dashboard_for_assessment(CA_Assessment_Types::SOCIAL_FLUENCY);
	}

	/**
	 * @param string $assessment_type Normalized type.
	 */
	private function render_dashboard_for_assessment($assessment_type)
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'rtr-custom-assessment'));
		}

		// Check SMTP configuration - show error if no SMTP detected
		// This ensures users are aware they need SMTP for email functionality
		$smtp_configured = $this->is_smtp_configured();

		$assessment_type = CA_Assessment_Types::normalize($assessment_type);
		$scale_max = CA_Assessment_Types::get_scale_max($assessment_type);
		$submissions_list_args = $this->admin_screen_query_args('submissions', $assessment_type);
		if (CA_Assessment_Types::SOCIAL_FLUENCY === $assessment_type) {
			$dashboard_title = __('Social Fluency — Dashboard', 'rtr-custom-assessment');
		} elseif (CA_Assessment_Types::INNER_DIMENSIONS === $assessment_type) {
			$dashboard_title = __('Natural Attributes Cataloging — Dashboard', 'rtr-custom-assessment');
		} else {
			$dashboard_title = __('Entrepreneurial Mindset — Dashboard', 'rtr-custom-assessment');
		}

		$submissions = CA_Database::get_all_submissions($assessment_type);
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
			<?php if ($this->is_assessment_section_screen()) : ?>
				<?php $this->render_assessment_section_nav_tabs($assessment_type, 'dashboard'); ?>
			<?php endif; ?>
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-chart-bar"></span>
				<?php echo esc_html($dashboard_title); ?>
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
					<div class="ca-dashboard-card-value"><?php echo esc_html(number_format($avg_average_score, 2)); ?>/<?php echo esc_html((string) $scale_max); ?>
					</div>
					<div class="ca-dashboard-card-label"><?php esc_html_e('Avg Score Per Q', 'rtr-custom-assessment'); ?></div>
				</div>
			</div>

			<div class="ca-dashboard-section">
				<h2><?php esc_html_e('Recent Submissions', 'rtr-custom-assessment'); ?></h2>

				<?php if (empty($recent_submissions)): ?>
					<p>
						<?php
						if (CA_Assessment_Types::SOCIAL_FLUENCY === $assessment_type) {
							esc_html_e('No Social Fluency submissions yet. Use the shortcode [social_fluency_assessment] on a page.', 'rtr-custom-assessment');
						} elseif (CA_Assessment_Types::INNER_DIMENSIONS === $assessment_type) {
							esc_html_e('No Natural Attributes Cataloging submissions yet. Use the shortcode [natural_attributes_cataloging_assessment] on a page.', 'rtr-custom-assessment');
						} else {
							esc_html_e('No submissions yet. Share the shortcode [custom_assessment] on any page.', 'rtr-custom-assessment');
						}
						?>
					</p>
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
										<a href="<?php echo esc_url(add_query_arg(array_merge($submissions_list_args, array('view' => 'detail', 'id' => $sub->id)), admin_url('admin.php'))); ?>"
											class="button button-small">
											<?php esc_html_e('View', 'rtr-custom-assessment'); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p>
						<a href="<?php echo esc_url(add_query_arg($submissions_list_args, admin_url('admin.php'))); ?>"
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
	 * Mindset submissions list / detail.
	 */
	public function render_list_page()
	{
		$this->render_submissions_list_for_type(CA_Assessment_Types::MINDSET);
	}

	/**
	 * Social Fluency submissions list / detail.
	 */
	public function render_sf_list_page()
	{
		$this->render_submissions_list_for_type(CA_Assessment_Types::SOCIAL_FLUENCY);
	}

	/**
	 * Combined list of every submission (mindset + social fluency).
	 */
	public function render_all_submissions_page()
	{
		$this->render_submissions_list_for_type(null);
	}

	/**
	 * Completed assessments with full results not yet purchased (per assessment + bundle).
	 */
	public function render_unpaid_full_results_page()
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'rtr-custom-assessment'));
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing for admin UI.
		$tab = isset($_GET['ca_unpaid_tab']) ? sanitize_key(wp_unslash($_GET['ca_unpaid_tab'])) : 'inner';
		$list_view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';
		$list_id = isset($_GET['id']) ? absint($_GET['id']) : 0;
		$current_page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$allowed_tabs = array('inner', 'social', 'bundle');
		if (!in_array($tab, $allowed_tabs, true)) {
			$tab = 'inner';
		}

		$page_args = array(
			'page' => 'custom-assessment-unpaid',
			'ca_unpaid_tab' => $tab,
		);

		if ('detail' === $list_view && $list_id > 0 && 'bundle' !== $tab) {
			$this->render_detail_page($list_id, $page_args);
			return;
		}

		if ('inner' === $tab) {
			$heading = __('Natural Attributes Quick Scan — Unpaid Full Results', 'rtr-custom-assessment');
			$description = __('Completed Natural Attributes Cataloging assessments where the customer has not purchased the full PDF report (standalone, not bundle).', 'rtr-custom-assessment');
			$rows = $this->get_unpaid_full_results_submissions(CA_Assessment_Types::INNER_DIMENSIONS);
		} elseif ('social' === $tab) {
			$heading = __('Social Fluency — Unpaid Full Results', 'rtr-custom-assessment');
			$description = __('Completed Social Fluency assessments where the customer has not purchased the full PDF report (standalone, not bundle).', 'rtr-custom-assessment');
			$rows = $this->get_unpaid_full_results_submissions(CA_Assessment_Types::SOCIAL_FLUENCY);
		} else {
			$heading = __('Bundle — Unpaid Full Results', 'rtr-custom-assessment');
			$description = __('Customers who completed both Natural Attributes and Social Fluency via the bundle flow but have not paid for the combined full results.', 'rtr-custom-assessment');
			$rows = $this->get_unpaid_bundle_pairs();
		}

		$per_page = 10;
		$total_count = count($rows);
		$total_pages = max(1, (int) ceil($total_count / $per_page));
		$offset = ($current_page - 1) * $per_page;
		$paged_rows = array_slice($rows, $offset, $per_page);

		$ajax = CA_Ajax::get_instance();
		?>
		<div class="wrap ca-admin-wrap">
			<?php $this->render_unpaid_full_results_nav_tabs($tab); ?>
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-money-alt"></span>
				<?php echo esc_html($heading); ?>
			</h1>
			<p class="description"><?php echo esc_html($description); ?></p>

			<?php if (!function_exists('wc_get_orders')) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e('WooCommerce is not active. Payment status cannot be determined.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<div class="ca-questions-stats-grid" style="margin-top:16px;">
				<div class="ca-stat-card">
					<div class="ca-stat-value"><?php echo esc_html($total_count); ?></div>
					<div class="ca-stat-label"><?php esc_html_e('Awaiting payment', 'rtr-custom-assessment'); ?></div>
				</div>
			</div>

			<?php if (empty($rows)) : ?>
				<div class="ca-admin-empty" style="margin-top:24px;">
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<p><?php esc_html_e('No unpaid full-results customers in this category.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php else : ?>
				<div class="ca-bulk-actions-bar ca-unpaid-bulk-bar">
					<button type="button" class="button button-primary ca-unpaid-bulk-email-open" disabled>
						<?php esc_html_e('Send emails', 'rtr-custom-assessment'); ?>
					</button>
					<span class="ca-bulk-selected-count ca-unpaid-selected-count">0 <?php esc_html_e('selected', 'rtr-custom-assessment'); ?></span>
				</div>

				<table class="wp-list-table widefat fixed striped ca-admin-table ca-unpaid-table" style="margin-top:12px;">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column">
								<label class="screen-reader-text" for="ca-unpaid-select-all"><?php esc_html_e('Select all', 'rtr-custom-assessment'); ?></label>
								<input type="checkbox" id="ca-unpaid-select-all">
							</td>
							<?php if ('bundle' === $tab) : ?>
								<th scope="col"><?php esc_html_e('Name', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Email', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('NAC #', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('SF #', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Order', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Completed', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Email status', 'rtr-custom-assessment'); ?></th>
							<?php else : ?>
								<th scope="col" class="ca-col-id"><?php esc_html_e('#', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Name', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Email', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Phone', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Order', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Completed', 'rtr-custom-assessment'); ?></th>
								<th scope="col"><?php esc_html_e('Email status', 'rtr-custom-assessment'); ?></th>
							<?php endif; ?>
							<th scope="col"><?php esc_html_e('Actions', 'rtr-custom-assessment'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ($paged_rows as $row) :
							if ('bundle' === $tab) :
								$inner = $row['inner'];
								$social = $row['social'];
								$pay_url = $ajax ? (string) $ajax->get_bundle_order_pay_url_for_submissions((int) $row['inner_id'], (int) $row['social_id']) : '';
								$completed_at = max(strtotime((string) $inner->updated_at), strtotime((string) $social->updated_at));
								$recipient_token = 'bundle:' . (int) $row['inner_id'] . ':' . (int) $row['social_id'];
								$recipient_name = trim((string) $inner->first_name . ' ' . (string) $inner->last_name);
								?>
								<tr>
									<th scope="row" class="check-column">
										<input
											type="checkbox"
											class="ca-unpaid-select"
											value="<?php echo esc_attr($recipient_token); ?>"
											data-email="<?php echo esc_attr((string) $inner->email); ?>"
											data-name="<?php echo esc_attr($recipient_name); ?>"
											data-pay-link="<?php echo esc_attr($pay_url); ?>"
											data-assessment="<?php echo esc_attr__('Bundle: Natural Attributes + Social Fluency', 'rtr-custom-assessment'); ?>"
										>
									</th>
									<td><strong><?php echo esc_html($recipient_name); ?></strong></td>
									<td><?php echo esc_html((string) $inner->email); ?></td>
									<td class="ca-col-id"><?php echo esc_html((string) $row['inner_id']); ?></td>
									<td class="ca-col-id"><?php echo esc_html((string) $row['social_id']); ?></td>
									<td>
										<?php if (!empty($row['order_id'])) : ?>
											<a href="<?php echo esc_url(get_edit_post_link((int) $row['order_id'])); ?>">#<?php echo esc_html((string) $row['order_id']); ?></a>
											<span class="ca-status-badge ca-status--<?php echo esc_attr(sanitize_html_class((string) $row['order_status'])); ?>"><?php echo esc_html((string) $row['order_status']); ?></span>
										<?php else : ?>
											—
										<?php endif; ?>
									</td>
									<td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $completed_at)); ?></td>
									<?php $this->render_unpaid_email_status_cell($recipient_token, $recipient_name); ?>
									<td>
										<a href="<?php echo esc_url(add_query_arg(array_merge($page_args, array('view' => 'detail', 'id' => (int) $row['inner_id'])), admin_url('admin.php'))); ?>" class="button button-small"><?php esc_html_e('View NAC', 'rtr-custom-assessment'); ?></a>
										<a href="<?php echo esc_url(add_query_arg(array_merge($page_args, array('view' => 'detail', 'id' => (int) $row['social_id'], 'ca_unpaid_tab' => 'bundle')), admin_url('admin.php'))); ?>" class="button button-small"><?php esc_html_e('View SF', 'rtr-custom-assessment'); ?></a>
										<?php if ('' !== $pay_url) : ?>
											<a href="<?php echo esc_url($pay_url); ?>" class="button button-small" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Pay link', 'rtr-custom-assessment'); ?></a>
										<?php endif; ?>
									</td>
								</tr>
								<?php
							else :
								$sub = $row;
								$pay_url = $ajax ? (string) $ajax->get_paid_full_results_order_pay_url_for_submission((int) $sub->id) : '';
								$order_info = $this->get_latest_unpaid_full_results_order_for_submission((int) $sub->id, CA_Assessment_Types::from_submission($sub));
								$recipient_token = 'sub:' . (int) $sub->id;
								$recipient_name = trim((string) $sub->first_name . ' ' . (string) $sub->last_name);
								$assessment_label = $this->admin_submission_assessment_label($sub);
								?>
								<tr>
									<th scope="row" class="check-column">
										<input
											type="checkbox"
											class="ca-unpaid-select"
											value="<?php echo esc_attr($recipient_token); ?>"
											data-email="<?php echo esc_attr((string) $sub->email); ?>"
											data-name="<?php echo esc_attr($recipient_name); ?>"
											data-pay-link="<?php echo esc_attr($pay_url); ?>"
											data-assessment="<?php echo esc_attr($assessment_label); ?>"
										>
									</th>
									<td class="ca-col-id"><?php echo esc_html((string) $sub->id); ?></td>
									<td><strong><?php echo esc_html(trim((string) $sub->first_name . ' ' . (string) $sub->last_name)); ?></strong></td>
									<td><?php echo esc_html((string) $sub->email); ?></td>
									<td><?php echo esc_html((string) $sub->phone); ?></td>
									<td>
										<?php if (!empty($order_info['order_id'])) : ?>
											<a href="<?php echo esc_url(get_edit_post_link((int) $order_info['order_id'])); ?>">#<?php echo esc_html((string) $order_info['order_id']); ?></a>
											<span class="ca-status-badge ca-status--<?php echo esc_attr(sanitize_html_class((string) $order_info['status'])); ?>"><?php echo esc_html((string) $order_info['status']); ?></span>
										<?php else : ?>
											—
										<?php endif; ?>
									</td>
									<td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime((string) $sub->updated_at))); ?></td>
									<?php $this->render_unpaid_email_status_cell($recipient_token, $recipient_name); ?>
									<td>
										<a href="<?php echo esc_url(add_query_arg(array_merge($page_args, array('view' => 'detail', 'id' => (int) $sub->id)), admin_url('admin.php'))); ?>" class="button button-small"><?php esc_html_e('View', 'rtr-custom-assessment'); ?></a>
										<?php if ('' !== $pay_url) : ?>
											<a href="<?php echo esc_url($pay_url); ?>" class="button button-small" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Pay link', 'rtr-custom-assessment'); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ($total_pages > 1) : ?>
					<div class="tablenav bottom">
						<div class="tablenav-pages">
							<span class="displaying-num">
								<?php
								printf(
									/* translators: %d: number of rows */
									esc_html(_n('%d customer', '%d customers', $total_count, 'rtr-custom-assessment')),
									(int) $total_count
								);
								?>
							</span>
							<span class="pagination-links">
								<?php
								$base_url = add_query_arg($page_args, admin_url('admin.php'));
								$prev_disabled = $current_page <= 1 ? 'disabled' : '';
								$next_disabled = $current_page >= $total_pages ? 'disabled' : '';
								echo '<a class="prev-page button ' . esc_attr($prev_disabled) . '" href="' . esc_url(add_query_arg('paged', max(1, $current_page - 1), $base_url)) . '">&laquo;</a>';
								for ($i = 1; $i <= $total_pages; $i++) {
									$active_class = ($i === $current_page) ? 'current' : '';
									echo '<a class="page-numbers ' . esc_attr($active_class) . '" href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '">' . esc_html((string) $i) . '</a>';
								}
								echo '<a class="next-page button ' . esc_attr($next_disabled) . '" href="' . esc_url(add_query_arg('paged', min($total_pages, $current_page + 1), $base_url)) . '">&raquo;</a>';
								?>
							</span>
						</div>
					</div>
				<?php endif; ?>

				<?php $this->render_unpaid_bulk_email_modal($tab); ?>
				<?php $this->render_unpaid_email_history_modal(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Email status column for unpaid listing rows.
	 *
	 * @param string $recipient_token sub:{id} or bundle:{inner}:{social}.
	 * @param string $recipient_label Display name for history modal.
	 */
	private function render_unpaid_email_status_cell($recipient_token, $recipient_label = '')
	{
		$history = CA_Unpaid_Email_Log::get_history($recipient_token);
		$has_sent = !empty($history);
		$history_json = wp_json_encode($history);
		if (false === $history_json) {
			$history_json = '[]';
		}
		?>
		<td class="ca-unpaid-email-status-cell">
			<?php if ($has_sent) : ?>
				<span class="ca-email-status-badge ca-email-status-badge--sent"><?php esc_html_e('Sent', 'rtr-custom-assessment'); ?></span>
				<button
					type="button"
					class="button button-small ca-unpaid-email-history-open"
					data-token="<?php echo esc_attr($recipient_token); ?>"
					data-name="<?php echo esc_attr($recipient_label); ?>"
					data-history="<?php echo esc_attr($history_json); ?>"
				><?php esc_html_e('View timestamp', 'rtr-custom-assessment'); ?></button>
			<?php else : ?>
				<span class="ca-email-status-badge ca-email-status-badge--not-sent"><?php esc_html_e('Not sent', 'rtr-custom-assessment'); ?></span>
			<?php endif; ?>
		</td>
		<?php
	}

	/**
	 * Modal listing send timestamps for a recipient.
	 */
	private function render_unpaid_email_history_modal()
	{
		?>
		<div class="ca-bulk-edit-modal-overlay ca-unpaid-history-modal-overlay" id="ca-unpaid-history-modal-overlay" style="display:none;" aria-hidden="true">
			<div class="ca-bulk-edit-modal ca-unpaid-history-modal" role="dialog" aria-labelledby="ca-unpaid-history-modal-title">
				<h3 id="ca-unpaid-history-modal-title"><?php esc_html_e('Email send history', 'rtr-custom-assessment'); ?></h3>
				<p class="description ca-unpaid-history-modal-subtitle"></p>
				<ul class="ca-unpaid-email-history-list"></ul>
				<div class="ca-bulk-edit-actions">
					<button type="button" class="button ca-unpaid-history-close"><?php esc_html_e('Close', 'rtr-custom-assessment'); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Modal for composing and sending bulk emails to unpaid customers.
	 *
	 * @param string $tab inner|social|bundle.
	 */
	private function render_unpaid_bulk_email_modal($tab)
	{
		$defaults = $this->get_unpaid_bulk_email_defaults($tab);
		$default_cc = (string) get_option('ca_unpaid_bulk_email_default_cc', '');
		?>
		<div class="ca-bulk-edit-modal-overlay ca-unpaid-email-modal-overlay" id="ca-unpaid-email-modal-overlay" style="display:none;" aria-hidden="true">
			<div class="ca-bulk-edit-modal ca-unpaid-email-modal" role="dialog" aria-labelledby="ca-unpaid-email-modal-title">
				<h3 id="ca-unpaid-email-modal-title"><?php esc_html_e('Send email to selected customers', 'rtr-custom-assessment'); ?></h3>
				<p class="description ca-unpaid-email-recipient-hint"></p>

				<div class="ca-unpaid-email-fields">
					<div class="ca-bulk-field ca-unpaid-field-full">
						<label for="ca-unpaid-email-to"><?php esc_html_e('To', 'rtr-custom-assessment'); ?></label>
						<textarea id="ca-unpaid-email-to" rows="3" readonly class="ca-unpaid-email-to"></textarea>
						<p class="description"><?php esc_html_e('One recipient per send. Placeholders in the message are replaced per customer.', 'rtr-custom-assessment'); ?></p>
					</div>

					<div class="ca-bulk-field ca-unpaid-field-full">
						<label for="ca-unpaid-email-subject"><?php esc_html_e('Subject', 'rtr-custom-assessment'); ?></label>
						<input type="text" id="ca-unpaid-email-subject" class="widefat" value="<?php echo esc_attr((string) $defaults['subject']); ?>">
					</div>

					<div class="ca-bulk-field ca-unpaid-field-full">
						<label for="ca-unpaid-email-body"><?php esc_html_e('Message', 'rtr-custom-assessment'); ?></label>
						<p class="description">
							<?php
							esc_html_e('Placeholders:', 'rtr-custom-assessment');
							echo ' {name}, {first_name}, {last_name}, {email}, {pay_link}, {pay_link_html}, {assessment}';
							?>
						</p>
						<?php
						wp_editor(
							(string) $defaults['body'],
							'ca_unpaid_email_body',
							array(
								'textarea_name' => 'ca_unpaid_email_body',
								'textarea_rows' => 12,
								'media_buttons' => false,
								'teeny' => false,
								'quicktags' => true,
							)
						);
						?>
					</div>

					<div class="ca-bulk-field">
						<label for="ca-unpaid-email-cc"><?php esc_html_e('CC', 'rtr-custom-assessment'); ?></label>
						<input type="text" id="ca-unpaid-email-cc" class="widefat" value="<?php echo esc_attr($default_cc); ?>" placeholder="<?php esc_attr_e('email@example.com, another@example.com', 'rtr-custom-assessment'); ?>">
						<p class="description"><?php esc_html_e('Comma-separated addresses. Saved as default for next time.', 'rtr-custom-assessment'); ?></p>
					</div>

					<div class="ca-bulk-field">
						<label for="ca-unpaid-email-attachment"><?php esc_html_e('Attachment', 'rtr-custom-assessment'); ?></label>
						<input type="file" id="ca-unpaid-email-attachment" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.gif,.webp">
						<p class="description"><?php esc_html_e('Optional. Max 10 MB. PDF, Word, or image.', 'rtr-custom-assessment'); ?></p>
					</div>
				</div>

				<div id="ca-unpaid-email-send-result" class="ca-unpaid-email-send-result" style="display:none;"></div>

				<div class="ca-bulk-edit-actions">
					<button type="button" class="button ca-unpaid-email-cancel"><?php esc_html_e('Cancel', 'rtr-custom-assessment'); ?></button>
					<button type="button" class="button button-primary ca-unpaid-email-send"><?php esc_html_e('Send emails', 'rtr-custom-assessment'); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Default subject/body for unpaid bulk email by tab.
	 *
	 * @param string $tab inner|social|bundle.
	 * @return array{subject:string,body:string}
	 */
	private function get_unpaid_bulk_email_defaults($tab)
	{
		$blog_name = get_bloginfo('name');
		$tab = in_array($tab, array('inner', 'social', 'bundle'), true) ? $tab : 'inner';

		if ('bundle' === $tab) {
			$subject = sprintf(
				/* translators: %s: site name */
				__('Unlock your full assessment bundle — %s', 'rtr-custom-assessment'),
				$blog_name
			);
			$assessment = __('Natural Attributes + Social Fluency bundle', 'rtr-custom-assessment');
		} elseif ('social' === $tab) {
			$subject = sprintf(
				/* translators: %s: site name */
				__('Unlock your Social Fluency full results — %s', 'rtr-custom-assessment'),
				$blog_name
			);
			$assessment = __('Social Fluency', 'rtr-custom-assessment');
		} else {
			$subject = sprintf(
				/* translators: %s: site name */
				__('Unlock your Natural Attributes full results — %s', 'rtr-custom-assessment'),
				$blog_name
			);
			$assessment = __('Natural Attributes Quick Scan', 'rtr-custom-assessment');
		}

		$body = '<p>' . esc_html__('Hi {name},', 'rtr-custom-assessment') . '</p>';
		$body .= '<p>' . sprintf(
			/* translators: %s: assessment name */
			esc_html__('Thank you for completing the %s assessment. Your full personalized report is ready — complete checkout using your secure link below.', 'rtr-custom-assessment'),
			esc_html($assessment)
		) . '</p>';
		$body .= '<p style="margin:24px 0;"><a href="{pay_link}" style="display:inline-block;background:#aa3130;color:#fff;text-decoration:none;padding:12px 22px;border-radius:6px;font-weight:600;">' . esc_html__('Unlock full results', 'rtr-custom-assessment') . '</a></p>';
		$body .= '<p style="font-size:13px;color:#666;">' . esc_html__('If the button does not work, copy and paste this link into your browser:', 'rtr-custom-assessment') . '<br>{pay_link_html}</p>';
		$body .= '<p>' . esc_html__('Thank you,', 'rtr-custom-assessment') . '<br>' . esc_html($blog_name) . '</p>';

		return array(
			'subject' => $subject,
			'body' => $body,
		);
	}

	/**
	 * AJAX: send bulk emails to selected unpaid customers.
	 */
	public function ajax_unpaid_bulk_send_emails()
	{
		check_ajax_referer('ca_unpaid_bulk_email', 'nonce');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('Permission denied.', 'rtr-custom-assessment')), 403);
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified above.
		$raw_recipients = isset($_POST['recipients']) ? wp_unslash($_POST['recipients']) : array();
		$subject_tpl = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
		$body_tpl = isset($_POST['body']) ? wp_kses_post(wp_unslash($_POST['body'])) : '';
		$cc = isset($_POST['cc']) ? sanitize_text_field(wp_unslash($_POST['cc'])) : '';
		$tab = isset($_POST['tab']) ? sanitize_key(wp_unslash($_POST['tab'])) : $this->get_unpaid_admin_tab();
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if (!is_array($raw_recipients) || array() === $raw_recipients) {
			wp_send_json_error(array('message' => __('No recipients selected.', 'rtr-custom-assessment')));
		}

		if ('' === trim($subject_tpl) || '' === trim(wp_strip_all_tags($body_tpl))) {
			wp_send_json_error(array('message' => __('Subject and message are required.', 'rtr-custom-assessment')));
		}

		update_option('ca_unpaid_bulk_email_default_cc', $cc, false);

		$attachment_path = $this->handle_unpaid_bulk_email_attachment_upload();
		if (is_wp_error($attachment_path)) {
			wp_send_json_error(array('message' => $attachment_path->get_error_message()));
		}

		$attachments = array();
		if (is_string($attachment_path) && '' !== $attachment_path) {
			$attachments[] = $attachment_path;
		}

		$sent = 0;
		$failed = 0;
		$errors = array();

		foreach ($raw_recipients as $token) {
			$recipient = $this->resolve_unpaid_bulk_recipient(sanitize_text_field((string) $token));
			if (is_wp_error($recipient)) {
				$failed++;
				$errors[] = $recipient->get_error_message();
				continue;
			}

			$to = sanitize_email((string) $recipient['email']);
			if (!is_email($to)) {
				$failed++;
				$errors[] = sprintf(
					/* translators: %s: recipient label */
					__('Invalid email for %s.', 'rtr-custom-assessment'),
					(string) $recipient['name']
				);
				continue;
			}

			$subject = $this->replace_unpaid_email_placeholders($subject_tpl, $recipient);
			$body = $this->replace_unpaid_email_placeholders($body_tpl, $recipient);
			$headers = $this->build_unpaid_bulk_email_headers($cc);

			$ok = wp_mail($to, $subject, $body, $headers, $attachments);
			if ($ok) {
				$sent++;
				CA_Unpaid_Email_Log::record_sent(
					(string) $token,
					array(
						'subject' => $subject,
						'email' => $to,
						'tab' => $tab,
					)
				);
				CA_Logger::log(
					'admin_unpaid_bulk_email',
					'success',
					'Bulk reminder email sent.',
					array(
						'email' => $to,
						'tab' => $tab,
						'token' => (string) $token,
					)
				);
			} else {
				$failed++;
				$errors[] = sprintf(
					/* translators: %s: email address */
					__('Failed to send to %s.', 'rtr-custom-assessment'),
					$to
				);
				CA_Logger::log(
					'admin_unpaid_bulk_email',
					'error',
					'Bulk reminder email failed.',
					array('email' => $to, 'tab' => $tab)
				);
			}
		}

		if (is_string($attachment_path) && '' !== $attachment_path && file_exists($attachment_path)) {
			wp_delete_file($attachment_path);
		}

		if ($sent > 0 && 0 === $failed) {
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %d: number of emails */
						_n('%d email sent.', '%d emails sent.', $sent, 'rtr-custom-assessment'),
						$sent
					),
					'sent' => $sent,
					'failed' => $failed,
				)
			);
		}

		if ($sent > 0) {
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: 1: sent count, 2: failed count */
						__('%1$d sent, %2$d failed.', 'rtr-custom-assessment'),
						$sent,
						$failed
					),
					'sent' => $sent,
					'failed' => $failed,
					'errors' => $errors,
				)
			);
		}

		wp_send_json_error(
			array(
				'message' => __('No emails were sent.', 'rtr-custom-assessment'),
				'errors' => $errors,
			)
		);
	}

	/**
	 * Build headers for unpaid bulk email.
	 *
	 * @param string $cc Comma-separated CC addresses.
	 * @return array<int, string>
	 */
	private function build_unpaid_bulk_email_headers($cc)
	{
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
		);

		$cc_list = array_filter(array_map('trim', explode(',', (string) $cc)));
		$valid_cc = array();
		foreach ($cc_list as $addr) {
			$clean = sanitize_email($addr);
			if (is_email($clean)) {
				$valid_cc[] = $clean;
			}
		}
		if (!empty($valid_cc)) {
			$headers[] = 'Cc: ' . implode(', ', $valid_cc);
		}

		return $headers;
	}

	/**
	 * Handle optional attachment upload for bulk email.
	 *
	 * @return string|\WP_Error Path to temp file, empty string if none, or error.
	 */
	private function handle_unpaid_bulk_email_attachment_upload()
	{
		if (empty($_FILES['attachment']['name'])) {
			return '';
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$overrides = array(
			'test_form' => false,
			'mimes' => array(
				'pdf' => 'application/pdf',
				'doc' => 'application/msword',
				'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'png' => 'image/png',
				'jpg|jpeg' => 'image/jpeg',
				'gif' => 'image/gif',
				'webp' => 'image/webp',
			),
		);

		$uploaded = wp_handle_upload($_FILES['attachment'], $overrides);
		if (isset($uploaded['error'])) {
			return new WP_Error('ca_upload_failed', (string) $uploaded['error']);
		}

		if (empty($uploaded['file'])) {
			return new WP_Error('ca_upload_failed', __('Could not upload attachment.', 'rtr-custom-assessment'));
		}

		$max_bytes = 10 * 1024 * 1024;
		if (filesize($uploaded['file']) > $max_bytes) {
			wp_delete_file($uploaded['file']);
			return new WP_Error('ca_upload_too_large', __('Attachment must be 10 MB or smaller.', 'rtr-custom-assessment'));
		}

		return (string) $uploaded['file'];
	}

	/**
	 * Resolve a bulk-email recipient token to send data.
	 *
	 * @param string $token sub:{id} or bundle:{inner}:{social}.
	 * @return array<string, string>|\WP_Error
	 */
	private function resolve_unpaid_bulk_recipient($token)
	{
		$ajax = CA_Ajax::get_instance();

		if (0 === strpos($token, 'sub:')) {
			$submission_id = (int) substr($token, 4);
			$submission = CA_Database::get_submission($submission_id);
			if (!$submission || 'completed' !== (string) $submission->status) {
				return new WP_Error('ca_invalid_submission', __('Invalid submission.', 'rtr-custom-assessment'));
			}
			if (CA_Mailer::submission_has_paid_full_results_order($submission)) {
				return new WP_Error('ca_already_paid', __('Submission already paid.', 'rtr-custom-assessment'));
			}

			$pay_link = $ajax ? (string) $ajax->get_paid_full_results_order_pay_url_for_submission($submission_id) : '';

			return array(
				'email' => (string) $submission->email,
				'first_name' => (string) $submission->first_name,
				'last_name' => (string) $submission->last_name,
				'name' => trim((string) $submission->first_name . ' ' . (string) $submission->last_name),
				'pay_link' => $pay_link,
				'assessment' => $this->admin_submission_assessment_label($submission),
			);
		}

		if (0 === strpos($token, 'bundle:')) {
			$parts = explode(':', $token);
			if (count($parts) < 3) {
				return new WP_Error('ca_invalid_bundle', __('Invalid bundle selection.', 'rtr-custom-assessment'));
			}
			$inner_id = (int) $parts[1];
			$social_id = (int) $parts[2];
			$inner = CA_Database::get_submission($inner_id);
			$social = CA_Database::get_submission($social_id);
			if (
				!$inner || !$social
				|| 'completed' !== (string) $inner->status
				|| 'completed' !== (string) $social->status
			) {
				return new WP_Error('ca_invalid_bundle', __('Invalid bundle submissions.', 'rtr-custom-assessment'));
			}

			$pay_link = $ajax ? (string) $ajax->get_bundle_order_pay_url_for_submissions($inner_id, $social_id) : '';

			return array(
				'email' => (string) $inner->email,
				'first_name' => (string) $inner->first_name,
				'last_name' => (string) $inner->last_name,
				'name' => trim((string) $inner->first_name . ' ' . (string) $inner->last_name),
				'pay_link' => $pay_link,
				'assessment' => __('Bundle: Natural Attributes + Social Fluency', 'rtr-custom-assessment'),
			);
		}

		return new WP_Error('ca_invalid_token', __('Invalid recipient.', 'rtr-custom-assessment'));
	}

	/**
	 * Replace merge tags in bulk email subject/body.
	 *
	 * @param string               $text      Template text.
	 * @param array<string, string> $recipient Recipient data.
	 * @return string
	 */
	private function replace_unpaid_email_placeholders($text, $recipient)
	{
		$pay_link = (string) $recipient['pay_link'];
		$pay_link_url = '' !== $pay_link ? esc_url($pay_link) : '';
		$pay_link_html = '' !== $pay_link
			? '<a href="' . esc_url($pay_link) . '">' . esc_html($pay_link) . '</a>'
			: esc_html__('(payment link unavailable)', 'rtr-custom-assessment');

		$map = array(
			'{name}' => (string) $recipient['name'],
			'{first_name}' => (string) $recipient['first_name'],
			'{last_name}' => (string) $recipient['last_name'],
			'{email}' => (string) $recipient['email'],
			'{pay_link}' => $pay_link_url,
			'{pay_link_html}' => $pay_link_html,
			'{assessment}' => (string) $recipient['assessment'],
		);

		return str_replace(array_keys($map), array_values($map), $text);
	}

	/**
	 * Tabs for unpaid full-results admin screen.
	 *
	 * @param string $current_tab inner|social|bundle.
	 */
	private function render_unpaid_full_results_nav_tabs($current_tab)
	{
		$tabs = array(
			'inner' => __('Natural Attributes Quick Scan', 'rtr-custom-assessment'),
			'social' => __('Social Fluency', 'rtr-custom-assessment'),
			'bundle' => __('Bundle', 'rtr-custom-assessment'),
		);

		echo '<nav class="nav-tab-wrapper ca-assessment-nav-tabs" aria-label="' . esc_attr__('Unpaid full results', 'rtr-custom-assessment') . '">';
		foreach ($tabs as $slug => $label) {
			$url = add_query_arg(
				array(
					'page' => 'custom-assessment-unpaid',
					'ca_unpaid_tab' => $slug,
				),
				admin_url('admin.php')
			);
			$classes = 'nav-tab' . ($slug === $current_tab ? ' nav-tab-active' : '');
			printf(
				'<a href="%1$s" class="%2$s">%3$s</a>',
				esc_url($url),
				esc_attr($classes),
				esc_html($label)
			);
		}
		echo '</nav>';
	}

	/**
	 * Completed submissions without paid full results (excludes bundle pairs).
	 *
	 * @param string $assessment_type inner_dimensions|social_fluency.
	 * @return array<int, object>
	 */
	private function get_unpaid_full_results_submissions($assessment_type)
	{
		$assessment_type = CA_Assessment_Types::normalize($assessment_type);
		$all = CA_Database::get_all_submissions($assessment_type);
		$unpaid = array();

		foreach ($all as $sub) {
			if ('completed' !== (string) $sub->status) {
				continue;
			}
			if ($this->submission_is_part_of_bundle((int) $sub->id)) {
				continue;
			}
			if (CA_Mailer::submission_has_paid_full_results_order($sub)) {
				continue;
			}
			$unpaid[] = $sub;
		}

		return $unpaid;
	}

	/**
	 * Bundle pairs (NAC + SF) with no paid bundle order.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_unpaid_bundle_pairs()
	{
		if (!function_exists('wc_get_orders')) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'limit' => -1,
				'orderby' => 'date',
				'order' => 'DESC',
				'meta_query' => array(
					array(
						'key' => '_ca_bundle_full_results',
						'value' => 'yes',
					),
				),
				'return' => 'objects',
			)
		);

		$pairs = array();
		foreach ($orders as $order) {
			if (!$order instanceof \WC_Order) {
				continue;
			}

			$inner_id = (int) $order->get_meta('_ca_bundle_inner_submission_id');
			$social_id = (int) $order->get_meta('_ca_bundle_social_submission_id');
			if ($inner_id <= 0 || $social_id <= 0) {
				continue;
			}

			$key = $inner_id . ':' . $social_id;
			if (!isset($pairs[$key])) {
				$pairs[$key] = array(
					'inner_id' => $inner_id,
					'social_id' => $social_id,
					'paid' => false,
					'order_id' => 0,
					'order_status' => '',
				);
			}

			if ($order->is_paid()) {
				$pairs[$key]['paid'] = true;
			}

			if (0 === (int) $pairs[$key]['order_id']) {
				$pairs[$key]['order_id'] = (int) $order->get_id();
				$pairs[$key]['order_status'] = (string) $order->get_status();
			}
		}

		$result = array();
		foreach ($pairs as $pair) {
			if (!empty($pair['paid'])) {
				continue;
			}

			$inner = CA_Database::get_submission((int) $pair['inner_id']);
			$social = CA_Database::get_submission((int) $pair['social_id']);
			if (
				!$inner || !$social
				|| 'completed' !== (string) $inner->status
				|| 'completed' !== (string) $social->status
			) {
				continue;
			}

			$pair['inner'] = $inner;
			$pair['social'] = $social;
			$result[] = $pair;
		}

		return $result;
	}

	/**
	 * Latest pending/failed paid-results order for a submission (if any).
	 *
	 * @param int    $submission_id   Submission ID.
	 * @param string $assessment_type Normalized assessment type.
	 * @return array{order_id:int,status:string}
	 */
	private function get_latest_unpaid_full_results_order_for_submission($submission_id, $assessment_type)
	{
		$submission_id = (int) $submission_id;
		$assessment_type = CA_Assessment_Types::normalize($assessment_type);
		if ($submission_id <= 0 || !function_exists('wc_get_orders')) {
			return array(
				'order_id' => 0,
				'status' => '',
			);
		}

		$order_ids = wc_get_orders(
			array(
				'limit' => 1,
				'orderby' => 'date',
				'order' => 'DESC',
				'status' => array('pending', 'failed', 'on-hold', 'cancelled'),
				'meta_query' => array(
					array(
						'key' => '_ca_submission_id',
						'value' => $submission_id,
					),
					array(
						'key' => '_ca_assessment_type',
						'value' => $assessment_type,
					),
				),
				'return' => 'ids',
			)
		);

		if (empty($order_ids)) {
			return array(
				'order_id' => 0,
				'status' => '',
			);
		}

		$order = wc_get_order((int) $order_ids[0]);
		if (!$order instanceof \WC_Order) {
			return array(
				'order_id' => 0,
				'status' => '',
			);
		}

		return array(
			'order_id' => (int) $order->get_id(),
			'status' => (string) $order->get_status(),
		);
	}

	/**
	 * Human-readable assessment label for a submission row.
	 *
	 * @param object $submission DB row.
	 * @return string
	 */
	private function admin_submission_assessment_label($submission)
	{
		$t = CA_Assessment_Types::from_submission($submission);
		if (CA_Assessment_Types::SOCIAL_FLUENCY === $t) {
			return __('Social Fluency', 'rtr-custom-assessment');
		}
		if (CA_Assessment_Types::INNER_DIMENSIONS === $t) {
			return __('Natural Attributes Cataloging', 'rtr-custom-assessment');
		}
		return __('Entrepreneurial Mindset', 'rtr-custom-assessment');
	}

	/**
	 * @param string|null $assessment_type Normalized type, or null for all assessments.
	 */
	private function render_submissions_list_for_type($assessment_type)
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'rtr-custom-assessment'));
		}

		$list_all_types = null === $assessment_type;
		if ($list_all_types) {
			$list_page_args = array('page' => 'custom-assessment-submissions-all');
			$list_heading = __('All Submissions', 'rtr-custom-assessment');
		} else {
			$assessment_type = CA_Assessment_Types::normalize($assessment_type);
			$list_page_args = $this->admin_screen_query_args('submissions', $assessment_type);
			if (CA_Assessment_Types::SOCIAL_FLUENCY === $assessment_type) {
				$list_heading = __('Social Fluency — Submissions', 'rtr-custom-assessment');
			} elseif (CA_Assessment_Types::INNER_DIMENSIONS === $assessment_type) {
				$list_heading = __('Natural Attributes Cataloging — Submissions', 'rtr-custom-assessment');
			} else {
				$list_heading = __('Entrepreneurial Mindset — Submissions', 'rtr-custom-assessment');
			}
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
			$this->render_detail_page($list_id, $list_page_args);
			return;
		}

		// List view
		$all_submissions = $list_all_types
			? CA_Database::get_all_submissions()
			: CA_Database::get_all_submissions($assessment_type);

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
			<?php if (!$list_all_types && $this->is_assessment_section_screen()) : ?>
				<?php $this->render_assessment_section_nav_tabs($assessment_type, 'submissions'); ?>
			<?php endif; ?>
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-chart-bar"></span>
				<?php echo esc_html($list_heading); ?>
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
					<p>
						<?php
						if ($list_all_types) {
							esc_html_e('No submissions yet.', 'rtr-custom-assessment');
						} elseif (CA_Assessment_Types::SOCIAL_FLUENCY === $assessment_type) {
							esc_html_e('No Social Fluency submissions yet. Use the shortcode [social_fluency_assessment] on a page.', 'rtr-custom-assessment');
						} elseif (CA_Assessment_Types::INNER_DIMENSIONS === $assessment_type) {
							esc_html_e('No Natural Attributes Cataloging submissions yet. Use the shortcode [natural_attributes_cataloging_assessment] on a page.', 'rtr-custom-assessment');
						} else {
							esc_html_e('No submissions yet. Share the shortcode [custom_assessment] on any page.', 'rtr-custom-assessment');
						}
						?>
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
						<?php $export_all_csv_url = add_query_arg(array_merge($list_page_args, array('action' => 'export_all', 'format' => 'csv', '_wpnonce' => wp_create_nonce('ca_export_all_submissions'))), admin_url('admin.php')); ?>
						<a href="<?php echo esc_url($export_all_csv_url); ?>" class="button button-secondary">
							<?php esc_html_e('Export All CSV', 'rtr-custom-assessment'); ?>
						</a>
						<?php $export_all_json_url = add_query_arg(array_merge($list_page_args, array('action' => 'export_all', 'format' => 'json', '_wpnonce' => wp_create_nonce('ca_export_all_submissions'))), admin_url('admin.php')); ?>
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
								<?php if ($list_all_types) : ?>
									<th scope="col"><?php esc_html_e('Assessment', 'rtr-custom-assessment'); ?></th>
								<?php endif; ?>
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
									<td class="ca-sub-name">
										<strong><?php echo esc_html($sub->first_name . ' ' . $sub->last_name); ?></strong>
									</td>
									<td class="ca-sub-email"><?php echo esc_html($sub->email); ?></td>
									<td class="ca-sub-phone"><?php echo esc_html($sub->phone); ?></td>
									<td class="ca-sub-job"><?php echo esc_html($sub->job_title); ?></td>
									<?php if ($list_all_types) : ?>
										<td class="ca-sub-assessment"><?php echo esc_html($this->admin_submission_assessment_label($sub)); ?></td>
									<?php endif; ?>
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
										<a href="<?php echo esc_url(add_query_arg(array_merge($list_page_args, array('view' => 'detail', 'id' => $sub->id)), admin_url('admin.php'))); ?>"
											class="button button-small">
											<?php esc_html_e('View', 'rtr-custom-assessment'); ?>
										</a>
										<?php if ('completed' === $sub->status): ?>
											<div class="ca-export-dropdown-wrapper">
												<div class="ca-export-menu ca-export-dropdown"
													id="export-<?php echo esc_attr($sub->id); ?>">
													<?php $csv_url = add_query_arg(array_merge($list_page_args, array('action' => 'export', 'format' => 'csv', 'id' => $sub->id, '_wpnonce' => wp_create_nonce('ca_export_submission_' . $sub->id))), admin_url('admin.php')); ?>
													<a href="<?php echo esc_url($csv_url); ?>" class="ca-export-option">
														CSV
													</a>
													<?php $pdf_url = add_query_arg(array_merge($list_page_args, array('action' => 'export', 'format' => 'pdf', 'id' => $sub->id, '_wpnonce' => wp_create_nonce('ca_export_submission_' . $sub->id))), admin_url('admin.php')); ?>
													<a href="<?php echo esc_url($pdf_url); ?>" class="ca-export-option">
														PDF
													</a>
												</div>
												<button type="button" class="button button-small ca-export-dropdown-btn"
													data-id="<?php echo esc_attr($sub->id); ?>">
													<?php esc_html_e('Export', 'rtr-custom-assessment'); ?> ▼
												</button>
											</div>

											<?php $email_url = add_query_arg(array_merge($list_page_args, array('action' => 'send_email', 'id' => $sub->id, '_wpnonce' => wp_create_nonce('ca_send_email_' . $sub->id))), admin_url('admin.php')); ?>
											<a href="<?php echo esc_url($email_url); ?>" class="button button-small"
												onclick="return confirm('<?php echo esc_js(__('Are you sure you want to resend the assessment results email to this customer?', 'rtr-custom-assessment')); ?>');">
												<?php esc_html_e('Resend Email', 'rtr-custom-assessment'); ?>
											</a>
										<?php endif; ?>
										<?php $delete_url = add_query_arg(array_merge($list_page_args, array('action' => 'delete', 'id' => $sub->id, '_wpnonce' => wp_create_nonce('ca_delete_submission_' . $sub->id))), admin_url('admin.php')); ?>
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
								$base_url = add_query_arg($list_page_args, admin_url('admin.php'));
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
	 * @param int         $submission_id   Submission ID.
	 * @param array<string, string>|null $list_page_args Query args for list back link (page, ca_tab, …); defaults from submission type.
	 */
	private function render_detail_page($submission_id, $list_page_args = null)
	{
		$submission = CA_Database::get_submission($submission_id);
		$answers = CA_Database::get_answers($submission_id);
		$cat_scores = CA_Database::get_category_scores($submission_id);
		$woo_orders = $this->get_submission_woocommerce_orders((int) $submission_id);
		$is_bundle_submission = $this->submission_is_part_of_bundle((int) $submission_id);

		if (!$submission) {
			echo '<div class="wrap"><p>' . esc_html__('Submission not found.', 'rtr-custom-assessment') . '</p></div>';
			return;
		}

		$sub_type = CA_Assessment_Types::from_submission($submission);
		$scale_max = CA_Assessment_Types::get_scale_max($sub_type);
		$flat_q = CA_Assessment_Registry::get_flat($sub_type);
		$total_q = CA_Assessment_Registry::get_total_count($sub_type);
		if (!is_array($list_page_args) || array() === $list_page_args) {
			$list_page_args = $this->admin_screen_query_args('submissions', $sub_type);
		}
		?>
		<div class="wrap ca-admin-wrap">
			<h1 class="ca-admin-title">
				<a href="<?php echo esc_url(add_query_arg($list_page_args, admin_url('admin.php'))); ?>"
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
					<div>
						<label><?php esc_html_e('Assessment', 'rtr-custom-assessment'); ?></label>
						<span><?php echo esc_html($this->admin_submission_assessment_label($submission)); ?></span>
					</div>
				</div>
			</div>

			<?php if (!$is_bundle_submission): ?>
				<div class="ca-admin-card">
					<h2 class="ca-admin-card-title"><?php esc_html_e('WooCommerce Orders', 'rtr-custom-assessment'); ?></h2>
					<?php if (!function_exists('wc_get_orders')): ?>
						<p><?php esc_html_e('WooCommerce is not active.', 'rtr-custom-assessment'); ?></p>
					<?php elseif (empty($woo_orders)): ?>
						<p><?php esc_html_e('No WooCommerce orders found for this submission.', 'rtr-custom-assessment'); ?></p>
					<?php else: ?>
						<table class="wp-list-table widefat fixed ca-admin-table">
							<thead>
								<tr>
									<th><?php esc_html_e('Order', 'rtr-custom-assessment'); ?></th>
									<th><?php esc_html_e('Status', 'rtr-custom-assessment'); ?></th>
									<th><?php esc_html_e('Total', 'rtr-custom-assessment'); ?></th>
									<th><?php esc_html_e('Created', 'rtr-custom-assessment'); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($woo_orders as $woo_order): ?>
									<?php
									$order_id = (int) $woo_order->get_id();
									$order_number = $woo_order->get_order_number();
									$order_edit_url = admin_url('post.php?post=' . $order_id . '&action=edit');
									$order_status = wc_get_order_status_name($woo_order->get_status());
									$date_created = $woo_order->get_date_created();
									?>
									<tr>
										<td>
											<a href="<?php echo esc_url($order_edit_url); ?>">
												<?php echo esc_html('#' . $order_number); ?>
											</a>
										</td>
										<td><?php echo esc_html($order_status); ?></td>
										<td><?php echo wp_kses_post($woo_order->get_formatted_order_total()); ?></td>
										<td>
											<?php
											echo esc_html(
												$date_created
													? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $date_created->getTimestamp())
													: '—'
											);
											?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ('completed' === $submission->status): ?>

				<!-- Overall Scores -->
				<div class="ca-admin-card">
					<h2 class="ca-admin-card-title"><?php esc_html_e('Overall Scores', 'rtr-custom-assessment'); ?></h2>
					<div class="ca-admin-score-row">
						<div class="ca-admin-score-box">
							<div class="ca-admin-score-value"><?php echo esc_html($submission->total_score); ?></div>
							<div class="ca-admin-score-label"><?php esc_html_e('Total Score', 'rtr-custom-assessment'); ?></div>
							<div class="ca-admin-score-max">
								<?php echo esc_html('/ ' . ($total_q * $scale_max)); ?>
							</div>
						</div>
						<div class="ca-admin-score-box">
							<div class="ca-admin-score-value">
								<?php echo esc_html(number_format($submission->average_score, 2)); ?>
							</div>
							<div class="ca-admin-score-label"><?php esc_html_e('Average Score', 'rtr-custom-assessment'); ?></div>
							<div class="ca-admin-score-max"><?php echo esc_html('/ ' . number_format((float) $scale_max, 2)); ?></div>
						</div>
						<div class="ca-admin-score-box">
							<div class="ca-admin-score-value ca-admin-score-profile">
								<?php echo esc_html(CA_Scoring::get_overall_profile((float) $submission->average_score, $sub_type)); ?>
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
										<?php echo esc_html(CA_Scoring::get_category_summary($cat->category_name, (float) $cat->average, $sub_type)); ?>
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
	 * Fetch WooCommerce orders linked to a submission.
	 *
	 * @param int $submission_id Submission ID.
	 * @return array
	 */
	private function get_submission_woocommerce_orders($submission_id)
	{
		$submission_id = (int) $submission_id;
		if ($submission_id <= 0 || !function_exists('wc_get_orders')) {
			return array();
		}

		$order_ids = wc_get_orders(array(
			'limit' => -1,
			'orderby' => 'date',
			'order' => 'DESC',
			'return' => 'ids',
			'meta_query' => array(
				array(
					'key' => '_ca_submission_id',
					'value' => $submission_id,
				),
			),
		));

		if (empty($order_ids) || !is_array($order_ids)) {
			return array();
		}

		$orders = array();
		foreach ($order_ids as $order_id) {
			$order = wc_get_order((int) $order_id);
			if ($order instanceof WC_Order) {
				$orders[] = $order;
			}
		}

		return $orders;
	}

	/**
	 * Whether submission is part of a bundle order.
	 *
	 * @param int $submission_id
	 * @return bool
	 */
	private function submission_is_part_of_bundle($submission_id)
	{
		$submission_id = (int) $submission_id;
		if ($submission_id <= 0 || !function_exists('wc_get_orders')) {
			return false;
		}

		$bundle_ids = wc_get_orders(array(
			'limit' => 1,
			'return' => 'ids',
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => '_ca_bundle_inner_submission_id',
					'value' => $submission_id,
				),
				array(
					'key' => '_ca_bundle_social_submission_id',
					'value' => $submission_id,
				),
			),
		));

		return !empty($bundle_ids);
	}

	/**
	 * Entrepreneurial Mindset — Questions admin tab.
	 */
	public function render_questions_page()
	{
		$this->render_assessment_questions_admin_page(CA_Assessment_Types::MINDSET);
	}

	/**
	 * Shared questions list UI (add / edit / delete / bulk / search / JSON import-export) for Mindset, Social Fluency, or Natural Attributes.
	 *
	 * @param string $assessment_type Normalized assessment type.
	 */
	private function render_assessment_questions_admin_page($assessment_type)
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'rtr-custom-assessment'));
		}

		$assessment_type = CA_Assessment_Types::normalize($assessment_type);
		$questions_tab_form_action = $this->admin_screen_url('questions', $assessment_type);
		$questions = $this->get_admin_questions_flat($assessment_type);
		$total_questions = count($questions);
		$categories = $this->get_admin_questions_categories($assessment_type);

		// Priority range for edit dropdown: 1..max (at least 5 for consistency with Add New Question).
		$priority_max = 0;
		foreach ($questions as $q) {
			if (isset($q['priority'])) {
				$priority_max = max($priority_max, (int) $q['priority']);
			}
		}
		if (CA_Assessment_Types::SOCIAL_FLUENCY === $assessment_type || CA_Assessment_Types::INNER_DIMENSIONS === $assessment_type) {
			$priority_floor = 10;
		} else {
			$priority_floor = 5;
		}
		$priority_end = max($priority_floor, (int) $priority_max);

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
			<?php if ($this->is_assessment_section_screen()) : ?>
				<?php $this->render_assessment_section_nav_tabs($assessment_type, 'questions'); ?>
			<?php endif; ?>
			<script type="text/javascript">
				window.CA_ADMIN_QUESTIONS_ALL = <?php echo wp_json_encode($all_questions_js); ?>;
				window.CA_ADMIN_AJAX_URL = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
				window.CA_ADMIN_QUESTIONS_DELETE_NONCE = <?php echo wp_json_encode($delete_question_nonce); ?>;
				window.CA_ADMIN_QUESTIONS_DELETE_CONFIRM = <?php echo wp_json_encode($delete_question_confirm); ?>;
				window.CA_ADMIN_QUESTIONS_EDIT_NONCE = <?php echo wp_json_encode($edit_question_nonce); ?>;
				window.CA_ADMIN_QUESTIONS_CATEGORIES = <?php echo wp_json_encode($categories); ?>;
				window.CA_ADMIN_QUESTIONS_PRIORITY_MAX = <?php echo (int) $priority_end; ?>;
				window.CA_ADMIN_QUESTIONS_ASSESSMENT_TYPE = <?php echo wp_json_encode($assessment_type); ?>;
			</script>
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-format-chat"></span>
				<?php
				if (CA_Assessment_Types::SOCIAL_FLUENCY === $assessment_type) {
					esc_html_e('Social Fluency — Questions', 'rtr-custom-assessment');
				} elseif (CA_Assessment_Types::INNER_DIMENSIONS === $assessment_type) {
					esc_html_e('Natural Attributes Cataloging — Questions', 'rtr-custom-assessment');
				} else {
					esc_html_e('Entrepreneurial Mindset — Questions', 'rtr-custom-assessment');
				}
				?>
			</h1>

			<?php if (CA_Assessment_Types::INNER_DIMENSIONS === $assessment_type) : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e('Respondents answer each statement with Yes or No. Custom questions use the same Yes/No flow.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

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
					<form method="post" action="<?php echo esc_url($questions_tab_form_action); ?>">
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

			<div class="ca-questions-actions">
				<div class="ca-question-form">
					<h3><?php esc_html_e('Import / Export Questions (JSON)', 'rtr-custom-assessment'); ?></h3>
					<p class="description" style="margin-top: 0;">
						<?php esc_html_e('Export this assessment question setup (questions, overrides, and categories) to JSON, then import it on this same Questions tab.', 'rtr-custom-assessment'); ?>
					</p>
					<div class="ca-form-actions" style="display: flex; align-items: end; gap: 12px; flex-wrap: wrap;">
						<form method="post" action="<?php echo esc_url($questions_tab_form_action); ?>" style="margin: 0;">
							<?php wp_nonce_field('ca_export_questions_json_action', '_wpnonce'); ?>
							<input type="hidden" name="ca_action" value="export_questions_json">
							<button type="submit" class="button button-secondary">
								<?php esc_html_e('Export JSON', 'rtr-custom-assessment'); ?>
							</button>
						</form>

						<form method="post" action="<?php echo esc_url($questions_tab_form_action); ?>" enctype="multipart/form-data" style="margin: 0; display: flex; align-items: end; gap: 8px; flex-wrap: wrap;">
							<?php wp_nonce_field('ca_import_questions_json_action', '_wpnonce'); ?>
							<input type="hidden" name="ca_action" value="import_questions_json">
							<div class="ca-form-field" style="margin: 0;">
								<label for="ca-import-questions-json" style="display: block;"><?php esc_html_e('JSON File', 'rtr-custom-assessment'); ?></label>
								<input type="file" id="ca-import-questions-json" name="questions_json_file" accept=".json,application/json" required>
							</div>
							<button type="submit" class="button button-primary">
								<?php esc_html_e('Import JSON', 'rtr-custom-assessment'); ?>
							</button>
						</form>

						<form method="post" action="<?php echo esc_url($questions_tab_form_action); ?>" style="margin: 0;" onsubmit="return confirm('<?php echo esc_js(__('Are you sure you want to delete all saved questions configuration for this assessment? This cannot be undone.', 'rtr-custom-assessment')); ?>');">
							<?php wp_nonce_field('ca_delete_all_questions_action', '_wpnonce'); ?>
							<input type="hidden" name="ca_action" value="delete_all_questions">
							<button type="submit" class="button button-link-delete">
								<?php esc_html_e('Delete All', 'rtr-custom-assessment'); ?>
							</button>
						</form>
					</div>
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
					<form method="post" action="<?php echo esc_url($questions_tab_form_action); ?>" id="ca-bulk-edit-form">
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

			<?php if ('questions_exported' === $questions_message): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Questions exported successfully.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if ('questions_imported' === $questions_message): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('Questions imported successfully.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if ('questions_import_invalid' === $questions_message): ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e('Invalid JSON file. Please export a valid file and try again.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if ('questions_import_type_mismatch' === $questions_message): ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e('This JSON file is for a different assessment type. Please import it on the matching Questions tab.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if ('questions_import_failed' === $questions_message): ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e('Could not import questions from this file. Please try again.', 'rtr-custom-assessment'); ?></p>
				</div>
			<?php endif; ?>

			<?php if ('questions_deleted_all' === $questions_message): ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e('All saved questions configuration was deleted for this assessment type.', 'rtr-custom-assessment'); ?></p>
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
									<form method="post" action="<?php echo esc_url($questions_tab_form_action); ?>" id="ca-edit-question-form-<?php echo esc_attr($q['index']); ?>"
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
									<form method="post" action="<?php echo esc_url($questions_tab_form_action); ?>" style="display: inline;"
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
								$base_url = $this->admin_screen_url('questions', $assessment_type);
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
				$redirect_url = add_query_arg('message', $message, $this->admin_screen_url('categories', CA_Assessment_Types::MINDSET));
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
			<?php if ($this->is_assessment_section_screen()) : ?>
				<?php $this->render_assessment_section_nav_tabs(CA_Assessment_Types::MINDSET, 'categories'); ?>
			<?php endif; ?>
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-category"></span>
				<?php esc_html_e('Entrepreneurial Mindset — Categories', 'rtr-custom-assessment'); ?>
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
								$base_url = $this->admin_screen_url('categories', CA_Assessment_Types::MINDSET);
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
	 * Social Fluency — Questions admin tab (same CRUD as Mindset; custom rows stored in options).
	 */
	public function render_sf_questions_page()
	{
		$this->render_assessment_questions_admin_page(CA_Assessment_Types::SOCIAL_FLUENCY);
	}

	/**
	 * Social Fluency categories (read-only).
	 */
	public function render_sf_categories_page()
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'rtr-custom-assessment'));
		}

		$blocks = CA_Social_Fluency_Questions::get_all();
		?>
		<div class="wrap ca-admin-wrap">
			<?php if ($this->is_assessment_section_screen()) : ?>
				<?php $this->render_assessment_section_nav_tabs(CA_Assessment_Types::SOCIAL_FLUENCY, 'categories'); ?>
			<?php endif; ?>
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-category"></span>
				<?php esc_html_e('Social Fluency — Categories', 'rtr-custom-assessment'); ?>
			</h1>

			<div class="notice notice-info inline">
				<p>
					<?php esc_html_e('Categories for the Social Fluency assessment are fixed in the plugin. Use Mindset — Categories to manage the entrepreneurial mindset assessment only.', 'rtr-custom-assessment'); ?>
				</p>
			</div>

			<?php if (empty($blocks)) : ?>
				<p><?php esc_html_e('No categories found.', 'rtr-custom-assessment'); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped ca-admin-table">
					<thead>
						<tr>
							<th><?php esc_html_e('Category', 'rtr-custom-assessment'); ?></th>
							<th><?php esc_html_e('Questions', 'rtr-custom-assessment'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($blocks as $block) : ?>
							<?php
							$cat_name = isset($block['category']) ? (string) $block['category'] : '';
							$qcount = isset($block['questions']) && is_array($block['questions']) ? count($block['questions']) : 0;
							?>
							<tr>
								<td><strong><?php echo esc_html($cat_name); ?></strong></td>
								<td><?php echo esc_html((string) $qcount); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Natural Attributes Cataloging — Questions (same CRUD as Mindset / Social; options-backed).
	 */
	public function render_inner_questions_page()
	{
		$this->render_assessment_questions_admin_page(CA_Assessment_Types::INNER_DIMENSIONS);
	}

	/**
	 * Natural Attributes Cataloging categories (read-only).
	 */
	public function render_inner_categories_page()
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to view this page.', 'rtr-custom-assessment'));
		}

		$blocks = CA_Inner_Dimensions_Questions::get_all();
		?>
		<div class="wrap ca-admin-wrap">
			<?php if ($this->is_assessment_section_screen()) : ?>
				<?php $this->render_assessment_section_nav_tabs(CA_Assessment_Types::INNER_DIMENSIONS, 'categories'); ?>
			<?php endif; ?>
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-category"></span>
				<?php esc_html_e('Natural Attributes Cataloging — Categories', 'rtr-custom-assessment'); ?>
			</h1>

			<div class="notice notice-info inline">
				<p>
					<?php esc_html_e('Categories for Natural Attributes Cataloging are fixed in the plugin.', 'rtr-custom-assessment'); ?>
				</p>
			</div>

			<?php if (empty($blocks)) : ?>
				<p><?php esc_html_e('No categories found.', 'rtr-custom-assessment'); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped ca-admin-table">
					<thead>
						<tr>
							<th><?php esc_html_e('Category', 'rtr-custom-assessment'); ?></th>
							<th><?php esc_html_e('Questions', 'rtr-custom-assessment'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($blocks as $block) : ?>
							<?php
							$cat_name = isset($block['category']) ? (string) $block['category'] : '';
							$qcount = isset($block['questions']) && is_array($block['questions']) ? count($block['questions']) : 0;
							?>
							<tr>
								<td><strong><?php echo esc_html($cat_name); ?></strong></td>
								<td><?php echo esc_html((string) $qcount); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
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
	private function delete_question($question_index, $assessment_type = null)
	{
		if (null === $assessment_type) {
			$assessment_type = CA_Assessment_Types::MINDSET;
		}
		$assessment_type = CA_Assessment_Types::normalize($assessment_type);
		$keys = $this->questions_storage_keys($assessment_type);
		$flat_questions = $this->get_admin_questions_flat($assessment_type);

		if (!isset($flat_questions[$question_index])) {
			return;
		}

		$question_to_delete = $flat_questions[$question_index];
		$category_to_delete = $question_to_delete['category'];
		$priority_to_delete = $question_to_delete['priority'];

		$custom_questions = get_option($keys['custom_questions'], array());
		if (!is_array($custom_questions)) {
			$custom_questions = array();
		}

		$found = false;
		foreach ($custom_questions as $key => $custom_question) {
			if (
				isset($custom_question['category'], $custom_question['priority'], $custom_question['text']) &&
				$custom_question['category'] === $category_to_delete &&
				(int) $custom_question['priority'] === (int) $priority_to_delete &&
				$custom_question['text'] === $question_to_delete['text']
			) {
				unset($custom_questions[$key]);
				$found = true;
				break;
			}
		}

		if ($found) {
			update_option($keys['custom_questions'], array_values($custom_questions));
		}
	}

	/**
	 * Edit a question's category and text (updates custom questions only).
	 *
	 * @return bool True if edited, false if question is not found in custom questions.
	 */
	private function edit_question($question_index, $new_category, $new_question_text, $new_priority, $assessment_type = null)
	{
		if (null === $assessment_type) {
			$assessment_type = CA_Assessment_Types::MINDSET;
		}
		$assessment_type = CA_Assessment_Types::normalize($assessment_type);
		$keys = $this->questions_storage_keys($assessment_type);

		$flat_questions = $this->get_admin_questions_flat($assessment_type);
		if (!isset($flat_questions[$question_index])) {
			return false;
		}

		$question_to_edit = $flat_questions[$question_index];
		$original_category = isset($question_to_edit['category']) ? (string) $question_to_edit['category'] : '';
		$original_priority = isset($question_to_edit['priority']) ? (int) $question_to_edit['priority'] : 0;
		$original_text = isset($question_to_edit['text']) ? (string) $question_to_edit['text'] : '';

		$custom_questions = get_option($keys['custom_questions'], array());
		if (!is_array($custom_questions)) {
			$custom_questions = array();
		}

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
			update_option($keys['custom_questions'], array_values($custom_questions));
			return true;
		}

		$overrides = get_option($keys['question_overrides'], array());
		if (!is_array($overrides)) {
			$overrides = array();
		}

		$overrides[(int) $question_index] = array(
			'category' => $new_category,
			'text' => $new_question_text,
			'priority' => (int) $new_priority,
		);

		update_option($keys['question_overrides'], $overrides);

		return true;
	}

	/**
	 * Add a new question to the questions configuration.
	 * 
	 * @param string $question_text
	 * @param string $question_category
	 * @param int $question_priority
	 */
	private function add_question($question_text, $question_category, $question_priority, $assessment_type = null)
	{
		if (null === $assessment_type) {
			$assessment_type = CA_Assessment_Types::MINDSET;
		}
		$keys = $this->questions_storage_keys($assessment_type);
		$questions = get_option($keys['custom_questions'], array());
		if (!is_array($questions)) {
			$questions = array();
		}

		$questions[] = array(
			'text' => $question_text,
			'category' => $question_category,
			'priority' => $question_priority,
		);

		update_option($keys['custom_questions'], $questions);
	}

	/**
	 * Export assessment questions configuration as JSON file download.
	 *
	 * @param string $assessment_type
	 */
	private function export_questions_json($assessment_type)
	{
		$assessment_type = CA_Assessment_Types::normalize($assessment_type);
		$keys = $this->questions_storage_keys($assessment_type);

		$payload = array(
			'plugin' => 'rtr-custom-assessment',
			'version' => 1,
			'assessment_type' => $assessment_type,
			'exported_at_gmt' => gmdate('c'),
			'custom_questions' => get_option($keys['custom_questions'], array()),
			'question_overrides' => get_option($keys['question_overrides'], array()),
			'custom_categories' => get_option($keys['custom_categories'], array()),
			'questions_flat' => $this->get_admin_questions_flat($assessment_type),
			'categories' => $this->get_admin_questions_categories($assessment_type),
		);

		if (!is_array($payload['custom_questions'])) {
			$payload['custom_questions'] = array();
		}
		if (!is_array($payload['question_overrides'])) {
			$payload['question_overrides'] = array();
		}
		if (!is_array($payload['custom_categories'])) {
			$payload['custom_categories'] = array();
		}
		if (!is_array($payload['questions_flat'])) {
			$payload['questions_flat'] = array();
		}
		if (!is_array($payload['categories'])) {
			$payload['categories'] = array();
		}
		$payload['base_question_count'] = max(0, count($payload['questions_flat']) - count($payload['custom_questions']));

		$filename = sprintf(
			'ca-questions-%s-%s.json',
			sanitize_file_name($assessment_type),
			gmdate('Ymd-His')
		);

		header('Content-Type: application/json; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: no-cache, no-store, must-revalidate');
		header('Pragma: no-cache');
		header('Expires: 0');

		echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	/**
	 * Import assessment questions configuration from uploaded JSON file.
	 *
	 * @param string $assessment_type
	 * @return string Redirect message key.
	 */
	private function import_questions_json($assessment_type)
	{
		$assessment_type = CA_Assessment_Types::normalize($assessment_type);
		$keys = $this->questions_storage_keys($assessment_type);
		$existing_custom_questions = get_option($keys['custom_questions'], array());
		$existing_question_overrides = get_option($keys['question_overrides'], array());
		$existing_custom_categories = get_option($keys['custom_categories'], array());
		if (!is_array($existing_custom_questions)) {
			$existing_custom_questions = array();
		}
		if (!is_array($existing_question_overrides)) {
			$existing_question_overrides = array();
		}
		if (!is_array($existing_custom_categories)) {
			$existing_custom_categories = array();
		}

		if (!isset($_FILES['questions_json_file']) || !is_array($_FILES['questions_json_file'])) {
			return 'questions_import_failed';
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Upload metadata validated below.
		$file = $_FILES['questions_json_file'];
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if (!isset($file['error']) || UPLOAD_ERR_OK !== (int) $file['error']) {
			return 'questions_import_failed';
		}

		$tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
		if ('' === $tmp_name || !is_uploaded_file($tmp_name)) {
			return 'questions_import_failed';
		}

		$raw = file_get_contents($tmp_name);
		if (false === $raw || '' === $raw) {
			return 'questions_import_invalid';
		}

		$data = json_decode($raw, true);
		if (!is_array($data)) {
			return 'questions_import_invalid';
		}

		$file_assessment_type = isset($data['assessment_type']) ? CA_Assessment_Types::normalize((string) $data['assessment_type']) : '';
		if ($file_assessment_type !== $assessment_type) {
			return 'questions_import_type_mismatch';
		}

		$custom_questions = isset($data['custom_questions']) && is_array($data['custom_questions']) ? $data['custom_questions'] : null;
		$question_overrides = isset($data['question_overrides']) && is_array($data['question_overrides']) ? $data['question_overrides'] : null;
		$custom_categories = isset($data['custom_categories']) && is_array($data['custom_categories']) ? $data['custom_categories'] : null;
		$questions_flat = isset($data['questions_flat']) && is_array($data['questions_flat']) ? $data['questions_flat'] : null;

		// When full snapshot is present, append all imported rows as custom questions.
		if (is_array($questions_flat) && !empty($questions_flat)) {
			$imported_rows_as_custom = array();
			$max_idx = count($questions_flat) - 1;

			for ($i = 0; $i <= $max_idx; $i++) {
				$row = $questions_flat[$i];
				if (!is_array($row)) {
					return 'questions_import_invalid';
				}

				$row_category = isset($row['category']) ? sanitize_text_field((string) $row['category']) : '';
				$row_text = isset($row['text']) ? sanitize_text_field((string) $row['text']) : '';
				$row_priority = isset($row['priority']) ? absint($row['priority']) : 0;

				if ('' === $row_category || '' === $row_text || $row_priority <= 0) {
					return 'questions_import_invalid';
				}

				$imported_rows_as_custom[] = array(
					'category' => $row_category,
					'text' => $row_text,
					'priority' => $row_priority,
				);
			}

			$rebuilt_custom_categories = array();
			if (isset($data['categories']) && is_array($data['categories'])) {
				foreach ($data['categories'] as $cat) {
					$cat = sanitize_text_field((string) $cat);
					if ('' !== $cat && !in_array($cat, $rebuilt_custom_categories, true)) {
						$rebuilt_custom_categories[] = $cat;
					}
				}
			}
			foreach ($imported_rows_as_custom as $row) {
				$cat = (string) $row['category'];
				if ('' !== $cat && !in_array($cat, $rebuilt_custom_categories, true)) {
					$rebuilt_custom_categories[] = $cat;
				}
			}

			$merged_custom_questions = array_merge($existing_custom_questions, $imported_rows_as_custom);

			// Keep existing overrides as-is; optionally append imported override indexes if provided.
			$merged_question_overrides = $existing_question_overrides;
			$imported_overrides = isset($data['question_overrides']) && is_array($data['question_overrides']) ? $data['question_overrides'] : array();
			foreach ($imported_overrides as $idx => $ov) {
				if (!isset($merged_question_overrides[$idx])) {
					$merged_question_overrides[$idx] = $ov;
				}
			}

			$merged_custom_categories = $existing_custom_categories;
			foreach ($rebuilt_custom_categories as $cat) {
				if ('' !== $cat && !in_array($cat, $merged_custom_categories, true)) {
					$merged_custom_categories[] = $cat;
				}
			}

			update_option($keys['custom_questions'], $merged_custom_questions);
			update_option($keys['question_overrides'], $merged_question_overrides);
			update_option($keys['custom_categories'], $merged_custom_categories);

			return 'questions_imported';
		}

		if (null === $custom_questions || null === $question_overrides || null === $custom_categories) {
			return 'questions_import_invalid';
		}

		$merged_custom_questions = array_merge($existing_custom_questions, $custom_questions);

		// Append-only for base overrides: keep existing index values, add imported only if missing.
		$merged_question_overrides = $existing_question_overrides;
		foreach ($question_overrides as $idx => $ov) {
			if (!isset($merged_question_overrides[$idx])) {
				$merged_question_overrides[$idx] = $ov;
			}
		}

		$merged_custom_categories = $existing_custom_categories;
		foreach ($custom_categories as $cat) {
			$cat = sanitize_text_field((string) $cat);
			if ('' !== $cat && !in_array($cat, $merged_custom_categories, true)) {
				$merged_custom_categories[] = $cat;
			}
		}

		update_option($keys['custom_questions'], $merged_custom_questions);
		update_option($keys['question_overrides'], $merged_question_overrides);
		update_option($keys['custom_categories'], $merged_custom_categories);

		return 'questions_imported';
	}

	/**
	 * Delete all saved questions configuration for an assessment type.
	 *
	 * @param string $assessment_type
	 */
	private function delete_all_questions_config($assessment_type)
	{
		$assessment_type = CA_Assessment_Types::normalize($assessment_type);
		$keys = $this->questions_storage_keys($assessment_type);

		update_option($keys['custom_questions'], array());
		update_option($keys['question_overrides'], array());
		update_option($keys['custom_categories'], array());
	}
}


