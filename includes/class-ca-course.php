<?php
/**
 * Course access paywall: [ca_course_access] shortcode + AJAX handlers.
 * Stores course URL server-side; only reveals it after WooCommerce payment is confirmed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CA_Course {

	const OPTION_NAME         = 'ca_course_name';
	const OPTION_PRICE        = 'ca_course_price';
	const OPTION_URL          = 'ca_course_url';
	const OPTION_REDIRECT_URL = 'ca_course_redirect_url';
	const OPTION_TOKEN_EXPIRY_HOURS = 'ca_course_token_expiry_hours';
	const OPTION_TEST_TOKEN   = 'ca_course_test_token';
	const OPTION_TEST_TOKEN_CREATED = 'ca_course_test_token_created';

	const META_ACCESS_TOKEN   = '_ca_course_access_token';
	const META_TOKEN_CREATED  = '_ca_course_token_created';
	const META_ACCESS_PASSWORD = '_ca_course_access_password';
	const META_COURSE_SLUG    = '_ca_course_slug';
	const META_ACCESS_EMAIL_SENT = '_ca_course_access_email_sent';

	const DEFAULT_COURSE_SLUG = 'personal-equity';

	/** @var bool */
	private static $modal_printed = false;

	/** @var bool */
	private static $needs_modal = false;

	public function __construct() {
		add_shortcode( 'ca_course_access', array( $this, 'render_button' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'maybe_print_modal' ), 10 );

		add_action( 'wp_ajax_ca_course_checkout',       array( $this, 'ajax_course_checkout' ) );
		add_action( 'wp_ajax_nopriv_ca_course_checkout', array( $this, 'ajax_course_checkout' ) );

		add_action( 'wp_ajax_ca_get_course_access',       array( $this, 'ajax_get_course_access' ) );
		add_action( 'wp_ajax_nopriv_ca_get_course_access', array( $this, 'ajax_get_course_access' ) );

		add_action( 'woocommerce_thankyou', array( $this, 'render_course_link_on_thankyou' ), 5 );

		add_action( 'woocommerce_payment_complete', array( $this, 'on_course_order_paid' ), 20, 1 );
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_course_order_paid' ), 20, 1 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_course_order_paid' ), 20, 1 );

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'rest_pre_serve_request', array( $this, 'send_course_verify_cors_headers' ), 10, 4 );

		add_action( 'wp_ajax_ca_course_test_create_token', array( $this, 'ajax_admin_create_test_token' ) );
		add_action( 'wp_ajax_ca_course_test_verify_token', array( $this, 'ajax_admin_verify_test_token' ) );
		add_action( 'wp_ajax_ca_course_test_delete_token', array( $this, 'ajax_admin_delete_test_token' ) );
		add_action( 'wp_ajax_ca_course_resend_access', array( $this, 'ajax_admin_resend_course_access' ) );

		add_filter( 'woocommerce_email_classes', array( $this, 'register_course_access_email' ) );
	}

	// -------------------------------------------------------------------------
	// Shortcode
	// -------------------------------------------------------------------------

	/**
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_button( $atts ) {
		self::$needs_modal = true;

		$atts = shortcode_atts(
			array(
				'button_text' => __( 'Access the Course', 'rtr-custom-assessment' ),
			),
			$atts,
			'ca_course_access'
		);

		ob_start();
		?>
		<div class="ca-trigger-wrap">
			<button class="ca-trigger-btn ca-course-trigger" type="button"
				aria-haspopup="dialog" aria-controls="ca-course-modal">
				<span><?php echo esc_html( $atts['button_text'] ); ?></span>
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
					stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<path d="M5 12h14M12 5l7 7-7 7" />
				</svg>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public function enqueue_assets() {
		global $post;

		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'ca_course_access' ) ) {
			return;
		}

		wp_enqueue_style(
			'ca-styles',
			CA_PLUGIN_URL . 'assets/css/assessment.css',
			array(),
			CA_VERSION
		);

		wp_enqueue_script(
			'ca-course',
			CA_PLUGIN_URL . 'assets/js/course.js',
			array( 'jquery' ),
			CA_VERSION,
			true
		);

		wp_localize_script(
			'ca-course',
			'CA_Course_Config',
			array(
				'ajax_url'    => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'ca_nonce' ),
				'course_name' => get_option( self::OPTION_NAME, __( 'Personal Equity Course', 'rtr-custom-assessment' ) ),
				'price'       => (float) get_option( self::OPTION_PRICE, 0 ),
				'labels'      => array(
					'loading'       => __( 'Loading…', 'rtr-custom-assessment' ),
					'error_generic' => __( 'Something went wrong. Please try again.', 'rtr-custom-assessment' ),
					'redirecting'   => __( 'Redirecting to checkout…', 'rtr-custom-assessment' ),
					'accessing'     => __( 'Opening your course…', 'rtr-custom-assessment' ),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Modal HTML
	// -------------------------------------------------------------------------

	public function maybe_print_modal() {
		if ( self::$modal_printed || ! self::$needs_modal ) {
			return;
		}
		self::$modal_printed = true;
		echo $this->get_modal_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	private function get_modal_markup() {
		$course_name = esc_html( get_option( self::OPTION_NAME, __( 'Personal Equity Course', 'rtr-custom-assessment' ) ) );
		$price       = (float) get_option( self::OPTION_PRICE, 0 );

		ob_start();
		?>
		<div id="ca-course-modal" class="ca-modal" role="dialog" aria-modal="true" aria-labelledby="ca-course-modal-title" aria-hidden="true">
			<div class="ca-modal-overlay" id="ca-course-modal-overlay"></div>

			<div class="ca-modal-panel">

				<div class="ca-modal-header">
					<div class="ca-modal-logo">
						<span class="ca-logo-icon" aria-hidden="true">
							<svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
								<circle cx="14" cy="14" r="14" fill="#aa3130" />
								<path d="M8 14l4 4 8-8" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
							</svg>
						</span>
						<span class="ca-logo-text" id="ca-course-modal-title"><?php echo $course_name; ?></span>
					</div>
					<button class="ca-close-btn" id="ca-course-close-modal" type="button" aria-label="<?php esc_attr_e( 'Close', 'rtr-custom-assessment' ); ?>">
						<?php esc_html_e( 'Close', 'rtr-custom-assessment' ); ?>
					</button>
				</div>

				<div class="ca-modal-body" id="ca-course-modal-body">

					<!-- Screen: user info / payment -->
					<div id="ca-course-screen-info" class="ca-screen ca-screen-active">
						<div class="ca-screen-content">
							<div class="ca-intro-badge"><?php esc_html_e( 'Course Access', 'rtr-custom-assessment' ); ?></div>
							<h2 class="ca-screen-title"><?php echo $course_name; ?></h2>

							<?php if ( $price > 0 ) : ?>
								<p class="ca-screen-subtitle">
									<?php
									printf(
										/* translators: %s: formatted price */
										esc_html__( 'One-time payment of $%s USD to unlock lifetime access.', 'rtr-custom-assessment' ),
										esc_html( number_format( $price, 2 ) )
									);
									?>
								</p>
							<?php endif; ?>

							<form id="ca-course-info-form" class="ca-form" novalidate>
								<div class="ca-form-row ca-form-row--2col">
									<div class="ca-field-group">
										<label for="ca-course-first-name" class="ca-label">
											<?php esc_html_e( 'First Name', 'rtr-custom-assessment' ); ?>
											<span class="ca-required" aria-hidden="true">*</span>
										</label>
										<input type="text" id="ca-course-first-name" name="first_name" class="ca-input"
											placeholder="<?php esc_attr_e( 'Jane', 'rtr-custom-assessment' ); ?>"
											autocomplete="given-name" required>
									</div>
									<div class="ca-field-group">
										<label for="ca-course-last-name" class="ca-label">
											<?php esc_html_e( 'Last Name', 'rtr-custom-assessment' ); ?>
											<span class="ca-required" aria-hidden="true">*</span>
										</label>
										<input type="text" id="ca-course-last-name" name="last_name" class="ca-input"
											placeholder="<?php esc_attr_e( 'Doe', 'rtr-custom-assessment' ); ?>"
											autocomplete="family-name" required>
									</div>
								</div>

								<div class="ca-form-row">
									<div class="ca-field-group">
										<label for="ca-course-email" class="ca-label">
											<?php esc_html_e( 'Email Address', 'rtr-custom-assessment' ); ?>
											<span class="ca-required" aria-hidden="true">*</span>
										</label>
										<input type="email" id="ca-course-email" name="email" class="ca-input"
											placeholder="<?php esc_attr_e( 'jane@example.com', 'rtr-custom-assessment' ); ?>"
											autocomplete="email" required>
									</div>
								</div>

								<div class="ca-form-error" id="ca-course-info-error" role="alert" aria-live="polite"></div>

								<div class="ca-form-actions" style="display:flex; justify-content:flex-end;">
									<button type="submit" class="ca-btn ca-btn--primary ca-btn--lg" id="ca-course-submit-btn">
										<span class="ca-btn-text">
											<?php
											if ( $price > 0 ) {
												printf(
													/* translators: %s: price */
													esc_html__( 'Continue to Payment — $%s', 'rtr-custom-assessment' ),
													esc_html( number_format( $price, 2 ) )
												);
											} else {
												esc_html_e( 'Access Course', 'rtr-custom-assessment' );
											}
											?>
										</span>
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

								<p class="ca-screen-subtitle" style="margin-top:16px; font-size:0.85em; text-align:center;">
									<?php esc_html_e( 'Already paid? Enter your email above and click the button — we\'ll look up your purchase.', 'rtr-custom-assessment' ); ?>
								</p>
							</form>
						</div>
					</div>

					<!-- Screen: loading -->
					<div id="ca-course-screen-loading" class="ca-screen ca-screen-loading" style="display:none;">
						<div class="ca-loading-spinner">
							<svg class="ca-spinner ca-spinner--lg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
								<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"
									stroke-dasharray="60" stroke-dashoffset="20" stroke-linecap="round" />
							</svg>
							<p class="ca-loading-spinner__title" id="ca-course-loading-text">
								<?php esc_html_e( 'Loading…', 'rtr-custom-assessment' ); ?>
							</p>
						</div>
					</div>

				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// AJAX: prepare checkout or return access URL if already paid
	// -------------------------------------------------------------------------

	public function ajax_course_checkout() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ca_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'rtr-custom-assessment' ) ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] )  ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) )  : '';
		$email      = isset( $_POST['email'] )       ? sanitize_email( wp_unslash( $_POST['email'] ) )           : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $first_name || ! $last_name || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all required fields.', 'rtr-custom-assessment' ) ) );
		}

		// Check for existing paid order.
		$access_url = $this->get_course_url_for_email( $email );
		if ( $access_url ) {
			wp_send_json_success( array(
				'already_paid'   => true,
				'course_url'     => $access_url,
			) );
		}

		if ( ! function_exists( 'wc_create_order' ) || ! class_exists( 'WC_Product_Simple' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is required but is not active.', 'rtr-custom-assessment' ) ) );
		}

		$price = (float) get_option( self::OPTION_PRICE, 0 );
		if ( $price <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Course price is not configured. Please contact the site admin.', 'rtr-custom-assessment' ) ) );
		}

		$course_name = get_option( self::OPTION_NAME, __( 'Personal Equity Course', 'rtr-custom-assessment' ) );

		// Upsert hidden WC product.
		$product_id = $this->upsert_course_product( $course_name, $price );
		if ( $product_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'Could not prepare checkout. Please try again.', 'rtr-custom-assessment' ) ) );
		}

		// Create pending WC order.
		$order = $this->create_course_order( $product_id, $first_name, $last_name, $email, $price );
		if ( ! $order instanceof \WC_Order ) {
			wp_send_json_error( array( 'message' => __( 'Could not create order. Please try again.', 'rtr-custom-assessment' ) ) );
		}

		$checkout_url = $order->get_checkout_payment_url( false );
		if ( ! $checkout_url ) {
			wp_send_json_error( array( 'message' => __( 'Could not build checkout URL. Please try again.', 'rtr-custom-assessment' ) ) );
		}

		wp_send_json_success( array(
			'already_paid'   => false,
			'checkout_url'   => $checkout_url,
		) );
	}

	// -------------------------------------------------------------------------
	// AJAX: verify access by email (for re-checking after return)
	// -------------------------------------------------------------------------

	public function ajax_get_course_access() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ca_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'rtr-custom-assessment' ) ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'rtr-custom-assessment' ) ) );
		}

		$url = $this->get_course_url_for_email( $email );
		if ( ! $url ) {
			wp_send_json_error( array( 'message' => __( 'No paid order found for this email address.', 'rtr-custom-assessment' ) ) );
		}

		wp_send_json_success( array( 'course_url' => $url ) );
	}

	// -------------------------------------------------------------------------
	// WooCommerce: show course link on thank-you / order details page
	// -------------------------------------------------------------------------

	/**
	 * @param int $order_id
	 */
	public function render_course_link_on_thankyou( $order_id ) {
		static $rendered = array();

		$order_id = (int) $order_id;
		if ( isset( $rendered[ $order_id ] ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		$context = $this->get_course_access_context_for_order( $order );
		if ( ! $context ) {
			return;
		}

		$rendered[ $order_id ] = true;
		$this->render_course_access_html_block( $context );
	}

	/**
	 * Register the dedicated course access WooCommerce email.
	 *
	 * @param array<string, WC_Email> $emails Email classes.
	 * @return array<string, WC_Email>
	 */
	public function register_course_access_email( $emails ) {
		require_once CA_PLUGIN_DIR . 'includes/class-ca-email-course-access.php';
		$emails['CA_Email_Course_Access'] = new CA_Email_Course_Access();
		return $emails;
	}

	/**
	 * Send the course access email once per paid course order.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	private function maybe_send_course_access_email( $order ) {
		if ( ! $order instanceof \WC_Order || ! function_exists( 'WC' ) ) {
			return;
		}

		if ( 'yes' === (string) $order->get_meta( self::META_ACCESS_EMAIL_SENT ) ) {
			return;
		}

		$context = $this->get_course_access_context_for_order( $order );
		if ( ! $context ) {
			return;
		}

		$this->send_course_access_email( $order, $context );
	}

	/**
	 * Regenerate credentials and resend the course access email (admin).
	 *
	 * @param int $order_id Order ID.
	 * @return true|\WP_Error
	 */
	public function resend_course_access_for_order( $order_id ) {
		$order = wc_get_order( (int) $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return new \WP_Error( 'ca_course_order_missing', __( 'Order not found.', 'rtr-custom-assessment' ) );
		}
		if ( 'yes' !== (string) $order->get_meta( '_ca_course_order' ) ) {
			return new \WP_Error( 'ca_course_order_invalid', __( 'This is not a course order.', 'rtr-custom-assessment' ) );
		}
		if ( ! self::order_has_access( $order ) ) {
			return new \WP_Error( 'ca_course_order_unpaid', __( 'Order is not paid.', 'rtr-custom-assessment' ) );
		}

		$context = $this->ensure_access_credentials( $order, true );
		if ( ! $context ) {
			return new \WP_Error( 'ca_course_access_failed', __( 'Could not create access credentials.', 'rtr-custom-assessment' ) );
		}

		$order->delete_meta_data( self::META_ACCESS_EMAIL_SENT );
		$order->save();

		$this->send_course_access_email( $order, $context );

		return true;
	}

	/**
	 * AJAX: resend course access email with new URL and password.
	 *
	 * @return void
	 */
	public function ajax_admin_resend_course_access() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rtr-custom-assessment' ) ), 403 );
		}
		check_ajax_referer( 'ca_course_resend_access', 'nonce' );

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$result   = $this->resend_course_access_for_order( $order_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'New course access link and password sent to the customer.', 'rtr-custom-assessment' ),
			)
		);
	}

	/**
	 * Course access context for a paid course order, or null.
	 *
	 * @param \WC_Order|null $order Order.
	 * @return array<string, mixed>|null
	 */
	private function get_course_access_context_for_order( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return null;
		}
		if ( 'yes' !== (string) $order->get_meta( '_ca_course_order' ) ) {
			return null;
		}
		if ( ! self::order_has_access( $order ) ) {
			return null;
		}

		return $this->ensure_access_credentials( $order, false );
	}

	/**
	 * Create or rotate token + password for a course order.
	 *
	 * @param \WC_Order $order     Order.
	 * @param bool      $force_new Regenerate even if credentials exist.
	 * @return array<string, mixed>|null
	 */
	private function ensure_access_credentials( $order, $force_new = false ) {
		if ( ! $order instanceof \WC_Order ) {
			return null;
		}
		if ( 'yes' !== (string) $order->get_meta( '_ca_course_order' ) ) {
			return null;
		}
		if ( ! $this->order_has_course_access( $order ) ) {
			return null;
		}

		$token        = (string) $order->get_meta( self::META_ACCESS_TOKEN );
		$password_hash = (string) $order->get_meta( self::META_ACCESS_PASSWORD );
		$password_plain = '';

		if ( $force_new || '' === $token ) {
			$token          = bin2hex( random_bytes( 32 ) );
			$password_plain = wp_generate_password( 12, false );
			$order->update_meta_data( self::META_ACCESS_TOKEN, $token );
			$order->update_meta_data( self::META_ACCESS_PASSWORD, wp_hash_password( $password_plain ) );
			$order->update_meta_data( self::META_TOKEN_CREATED, current_time( 'mysql' ) );
			$order->save();
		} elseif ( '' === $password_hash ) {
			$password_plain = wp_generate_password( 12, false );
			$order->update_meta_data( self::META_ACCESS_PASSWORD, wp_hash_password( $password_plain ) );
			$order->save();
		}

		$course_url = self::build_course_access_url( $order );
		if ( '' === $course_url ) {
			return null;
		}

		$course_name = get_option( self::OPTION_NAME, __( 'Personal Equity Course', 'rtr-custom-assessment' ) );

		return array(
			'name'         => (string) $course_name,
			'url'          => $course_url,
			'password'     => $password_plain,
			'expiry_hours' => self::get_token_expiry_hours(),
			'expires_at'   => self::get_token_expires_at( $order ),
		);
	}

	/**
	 * Send course access email with full context.
	 *
	 * @param \WC_Order              $order   Order.
	 * @param array<string, mixed>   $context Access context.
	 * @return void
	 */
	private function send_course_access_email( $order, $context ) {
		if ( ! function_exists( 'WC' ) || ! $order instanceof \WC_Order ) {
			return;
		}

		$mailer = WC()->mailer();
		if ( ! $mailer ) {
			return;
		}

		$emails = $mailer->get_emails();
		if ( empty( $emails['CA_Email_Course_Access'] ) || ! $emails['CA_Email_Course_Access'] instanceof CA_Email_Course_Access ) {
			return;
		}

		$emails['CA_Email_Course_Access']->trigger( $order->get_id(), $order, $context );
		$order->update_meta_data( self::META_ACCESS_EMAIL_SENT, 'yes' );
		$order->save();
	}

	/**
	 * Thank-you / order details page block.
	 *
	 * @param array<string, mixed> $context Access context.
	 * @return void
	 */
	private function render_course_access_html_block( $context ) {
		$course_name   = isset( $context['name'] ) ? (string) $context['name'] : '';
		$course_url    = isset( $context['url'] ) ? (string) $context['url'] : '';
		$expiry_hours  = isset( $context['expiry_hours'] ) ? (int) $context['expiry_hours'] : self::get_token_expiry_hours();
		?>
		<div class="ca-course-thankyou" style="margin:24px 0; padding:20px 24px; background:#f9f5f5; border:2px solid #aa3130; border-radius:4px;">
			<h3 style="margin:0 0 8px;"><?php echo esc_html( $course_name ); ?></h3>
			<p style="margin:0 0 16px;">
				<?php
				printf(
					/* translators: %d: number of hours */
					esc_html__( 'Your payment is confirmed. Open the link below and enter the access password from your course access email. This link expires in %d hours.', 'rtr-custom-assessment' ),
					$expiry_hours
				);
				?>
			</p>
			<a href="<?php echo esc_url( $course_url ); ?>" style="display:inline-block;font-size:18px;font-weight:700;color:#aa3130;text-decoration:none;border-bottom:2px solid #aa3130;padding-bottom:2px;" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Access Your Course', 'rtr-custom-assessment' ); ?> &rarr;
			</a>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Create access token when a course order is paid.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function on_course_order_paid( $order_id ) {
		$order = wc_get_order( (int) $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		if ( 'yes' !== (string) $order->get_meta( '_ca_course_order' ) ) {
			return;
		}

		$this->ensure_access_credentials( $order, false );
		$this->maybe_send_course_access_email( $order );
	}

	/**
	 * REST: verify course access token (called from index.html on S3/CloudFront).
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'ca/v1',
			'/course/verify',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'rest_verify_course_token' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'token' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'password' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rest_verify_course_token' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'token' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'password' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function rest_verify_course_token( $request ) {
		$token    = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$password = sanitize_text_field( (string) $request->get_param( 'password' ) );
		$order    = $this->find_course_order_by_token( $token );
		$expired  = ( $order instanceof \WC_Order ) && self::is_order_token_expired( $order );
		$valid    = ( '' !== $token ) && $this->verify_access_token( $token, $password );

		return new \WP_REST_Response(
			array(
				'valid'   => $valid,
				'expired' => $expired && ! $valid,
			),
			200
		);
	}

	/**
	 * CORS headers for course verify endpoint (fetch from S3-hosted HTML).
	 *
	 * @param bool              $served  Whether request was served.
	 * @param \WP_HTTP_Response $result  Response.
	 * @param \WP_REST_Request  $request Request.
	 * @param \WP_REST_Server   $server  Server.
	 * @return bool
	 */
	public function send_course_verify_cors_headers( $served, $result, $request, $server ) {
		unset( $server );
		if ( ! $request instanceof \WP_REST_Request ) {
			return $served;
		}
		if ( '/ca/v1/course/verify' !== $request->get_route() ) {
			return $served;
		}

		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type, Accept' );

		return $served;
	}

	/**
	 * @param string $token    Access token.
	 * @param string $password Access password.
	 * @return bool
	 */
	private function verify_access_token( $token, $password = '' ) {
		if ( $this->is_test_access_token( $token ) ) {
			return true;
		}

		$order = $this->find_course_order_by_token( $token );
		if ( ! $order instanceof \WC_Order ) {
			return false;
		}
		if ( ! $this->order_has_course_access( $order ) ) {
			return false;
		}
		if ( self::is_order_token_expired( $order ) ) {
			return false;
		}

		$password_hash = (string) $order->get_meta( self::META_ACCESS_PASSWORD );
		if ( '' === $password_hash ) {
			return false;
		}
		if ( '' === $password || ! wp_check_password( $password, $password_hash ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param \WC_Order $order Order.
	 * @return bool
	 */
	private function order_has_course_access( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return false;
		}
		if ( $order->is_paid() ) {
			return true;
		}
		return in_array( $order->get_status(), array( 'processing', 'completed' ), true );
	}

	/**
	 * Build course index URL with access token query arg.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	public static function build_course_access_url( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return '';
		}
		$base = self::get_course_url();
		if ( '' === $base ) {
			return '';
		}
		$token = (string) $order->get_meta( self::META_ACCESS_TOKEN );
		if ( '' === $token ) {
			return '';
		}
		return add_query_arg( 'token', $token, $base );
	}

	/**
	 * @param string $token Access token.
	 * @return \WC_Order|null
	 */
	private function find_course_order_by_token( $token ) {
		if ( ! function_exists( 'wc_get_orders' ) || '' === $token ) {
			return null;
		}

		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'   => '_ca_course_order',
						'value' => 'yes',
					),
					array(
						'key'   => self::META_ACCESS_TOKEN,
						'value' => $token,
					),
				),
				'return'     => 'objects',
			)
		);

		if ( empty( $orders ) || ! ( $orders[0] instanceof \WC_Order ) ) {
			return null;
		}

		return $orders[0];
	}

	/**
	 * @param string $email Customer email.
	 * @return \WC_Order|null
	 */
	private function get_paid_course_order_for_email( $email ) {
		if ( ! function_exists( 'wc_get_orders' ) || ! is_email( $email ) ) {
			return null;
		}

		$orders = wc_get_orders(
			array(
				'meta_key'   => '_ca_course_email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'status'     => array( 'wc-completed', 'wc-processing' ),
				'limit'      => 1,
				'return'     => 'objects',
			)
		);

		if ( empty( $orders ) || ! ( $orders[0] instanceof \WC_Order ) ) {
			return null;
		}

		return $orders[0];
	}

	/**
	 * Returns tokenized course URL if this email has a paid course order, otherwise ''.
	 *
	 * @param string $email Customer email.
	 * @return string
	 */
	private function get_course_url_for_email( $email ) {
		$order = $this->get_paid_course_order_for_email( $email );
		if ( ! $order instanceof \WC_Order ) {
			return '';
		}

		$this->ensure_access_credentials( $order, false );

		return self::build_course_access_url( $order );
	}

	/**
	 * Find or create a single hidden WC product for the course.
	 *
	 * @param string $course_name
	 * @param float  $price
	 * @return int Product ID or 0 on failure.
	 */
	private function upsert_course_product( $course_name, $price ) {
		$existing = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => array( 'publish', 'private', 'draft' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_ca_course_product',
					'value' => 'yes',
				),
			),
		) );

		$product = ! empty( $existing ) ? wc_get_product( $existing[0] ) : null;
		if ( ! $product ) {
			$product = new WC_Product_Simple();
		}

		$product->set_name( $course_name );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_downloadable( false );
		$product->set_regular_price( wc_format_decimal( $price, 2 ) );
		$product->set_sold_individually( false );
		$product->set_downloads( array() );
		$product->set_manage_stock( false );

		$product_id = (int) $product->save();

		if ( $product_id > 0 ) {
			update_post_meta( $product_id, '_ca_course_product', 'yes' );
		}

		return $product_id;
	}

	/**
	 * Create a pending WC order for the course.
	 *
	 * @param int    $product_id
	 * @param string $first_name
	 * @param string $last_name
	 * @param string $email
	 * @param float  $price
	 * @return \WC_Order|null
	 */
	private function create_course_order( $product_id, $first_name, $last_name, $email, $price ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return null;
		}

		$order = wc_create_order( array(
			'status'       => 'pending',
			'customer_id'  => 0,
			'created_via'  => 'ca_course_access',
		) );

		if ( is_wp_error( $order ) || ! ( $order instanceof \WC_Order ) ) {
			return null;
		}

		$order->add_product( $product, 1 );

		$order->set_billing_first_name( $first_name );
		$order->set_billing_last_name( $last_name );
		$order->set_billing_email( $email );

		$order->update_meta_data( '_ca_course_order', 'yes' );
		$order->update_meta_data( '_ca_course_email', $email );
		$order->update_meta_data( self::META_COURSE_SLUG, self::DEFAULT_COURSE_SLUG );

		$order->calculate_totals();
		$order->save();

		// Allow guests to pay without logging in.
		$order->set_customer_id( 0 );
		$order->save();

		return $order;
	}

	// -------------------------------------------------------------------------
	// Static helpers for admin
	// -------------------------------------------------------------------------

	public static function get_course_name() {
		return (string) get_option( self::OPTION_NAME, '' );
	}

	public static function get_course_price() {
		return (float) get_option( self::OPTION_PRICE, 0 );
	}

	public static function get_course_url() {
		return (string) get_option( self::OPTION_URL, '' );
	}

	/**
	 * URL to redirect unauthorized course visitors (configured in admin).
	 *
	 * @return string
	 */
	public static function get_redirect_url() {
		$url = (string) get_option( self::OPTION_REDIRECT_URL, 'https://roottorise.ddev.site/' );
		return '' !== $url ? $url : 'https://roottorise.ddev.site/';
	}

	/**
	 * Hours until a course access link expires (0 = never).
	 *
	 * @return int
	 */
	public static function get_token_expiry_hours() {
		$hours = (int) get_option( self::OPTION_TOKEN_EXPIRY_HOURS, 24 );
		return max( 0, $hours );
	}

	/**
	 * Expiry datetime string for an order token, or empty if no expiry.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	public static function get_token_expires_at( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return '';
		}

		$hours = self::get_token_expiry_hours();
		if ( $hours <= 0 ) {
			return '';
		}

		$created = (string) $order->get_meta( self::META_TOKEN_CREATED );
		if ( '' === $created ) {
			return '';
		}

		$expires = strtotime( $created ) + ( $hours * HOUR_IN_SECONDS );
		if ( false === $expires ) {
			return '';
		}

		return gmdate( 'Y-m-d H:i:s', $expires );
	}

	/**
	 * @param \WC_Order $order Order.
	 * @return bool
	 */
	public static function is_order_token_expired( $order ) {
		$expires_at = self::get_token_expires_at( $order );
		if ( '' === $expires_at ) {
			return false;
		}

		return time() > strtotime( $expires_at );
	}

	/**
	 * REST endpoint used by the course index.html access gate.
	 *
	 * @return string
	 */
	public static function get_verify_api_url() {
		return rest_url( 'ca/v1/course/verify' );
	}

	/**
	 * Registered courses for admin catalog (extensible for multiple courses).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_catalog_courses() {
		return array(
			array(
				'slug'           => self::DEFAULT_COURSE_SLUG,
				'name'           => self::get_course_name(),
				'price'          => self::get_course_price(),
				'url'            => self::get_course_url(),
				'redirect_url'   => self::get_redirect_url(),
				'verify_api_url' => self::get_verify_api_url(),
				'shortcode'      => '[ca_course_access]',
				'product_id'     => self::get_course_product_id(),
			),
		);
	}

	/**
	 * Hidden WooCommerce product used for course checkout.
	 *
	 * @return int
	 */
	public static function get_course_product_id() {
		if ( ! function_exists( 'get_posts' ) ) {
			return 0;
		}

		$existing = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'private', 'draft' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_ca_course_product',
						'value' => 'yes',
					),
				),
			)
		);

		return ! empty( $existing ) ? (int) $existing[0] : 0;
	}

	/**
	 * @param array<string, mixed> $args wc_get_orders args.
	 * @return array<int, \WC_Order>
	 */
	public static function get_all_course_orders( $args = array() ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$query_args = array_merge(
			array(
				'limit'    => -1,
				'orderby'  => 'date',
				'order'    => 'DESC',
				'return'   => 'objects',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => '_ca_course_order',
						'value' => 'yes',
					),
				),
			),
			$args
		);

		$orders = wc_get_orders( $query_args );
		if ( ! is_array( $orders ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$orders,
				static function ( $order ) {
					return $order instanceof \WC_Order;
				}
			)
		);
	}

	/**
	 * Summary stats for the courses dashboard.
	 *
	 * @return array<string, int|float|string>
	 */
	public static function get_course_order_stats() {
		$stats = array(
			'total'             => 0,
			'paid'              => 0,
			'pending'           => 0,
			'revenue'           => 0.0,
			'with_token'        => 0,
			'latest_order_date' => '',
		);

		foreach ( self::get_all_course_orders() as $order ) {
			$stats['total']++;

			if ( self::order_has_access( $order ) ) {
				$stats['paid']++;
				$stats['revenue'] += (float) $order->get_total();
			}

			if ( in_array( $order->get_status(), array( 'pending', 'on-hold', 'failed', 'cancelled' ), true ) ) {
				$stats['pending']++;
			}

			if ( '' !== (string) $order->get_meta( self::META_ACCESS_TOKEN ) ) {
				$stats['with_token']++;
			}

			$date = $order->get_date_created();
			if ( $date ) {
				$created = $date->date( 'Y-m-d H:i:s' );
				if ( '' === $stats['latest_order_date'] || $created > $stats['latest_order_date'] ) {
					$stats['latest_order_date'] = $created;
				}
			}
		}

		return $stats;
	}

	/**
	 * Whether a course order currently has access (paid / processing / completed).
	 *
	 * @param \WC_Order $order Order.
	 * @return bool
	 */
	public static function order_has_access( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return false;
		}
		if ( $order->is_paid() ) {
			return true;
		}
		return in_array( $order->get_status(), array( 'processing', 'completed' ), true );
	}

	/**
	 * Normalize a course order for admin list/detail views.
	 *
	 * @param \WC_Order $order Order.
	 * @return array<string, mixed>
	 */
	public static function format_order_admin_row( $order ) {
		$token         = (string) $order->get_meta( self::META_ACCESS_TOKEN );
		$token_created = (string) $order->get_meta( self::META_TOKEN_CREATED );
		$access_url    = '' !== $token ? self::build_course_access_url( $order ) : '';
		$course_slug   = (string) $order->get_meta( self::META_COURSE_SLUG );
		if ( '' === $course_slug ) {
			$course_slug = self::DEFAULT_COURSE_SLUG;
		}

		$date_created = $order->get_date_created();
		$date_paid    = $order->get_date_paid();
		$expires_at   = self::get_token_expires_at( $order );
		$is_expired   = self::is_order_token_expired( $order );

		return array(
			'id'            => $order->get_id(),
			'name'          => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'email'         => (string) $order->get_billing_email(),
			'status'        => (string) $order->get_status(),
			'status_label'  => function_exists( 'wc_get_order_status_name' )
				? wc_get_order_status_name( $order->get_status() )
				: $order->get_status(),
			'total'         => (float) $order->get_total(),
			'currency'      => (string) $order->get_currency(),
			'date_created'  => $date_created ? $date_created->date( 'Y-m-d H:i:s' ) : '',
			'date_paid'     => $date_paid ? $date_paid->date( 'Y-m-d H:i:s' ) : '',
			'has_access'    => self::order_has_access( $order ),
			'token'         => $token,
			'token_created' => $token_created,
			'expires_at'    => $expires_at,
			'is_expired'    => $is_expired,
			'expiry_hours'  => self::get_token_expiry_hours(),
			'access_url'    => $access_url,
			'course_slug'   => $course_slug,
			'wc_edit_url'   => method_exists( $order, 'get_edit_order_url' ) ? $order->get_edit_order_url() : '',
		);
	}

	/**
	 * Course label from slug.
	 *
	 * @param string $slug Course slug.
	 * @return string
	 */
	public static function get_course_label_for_slug( $slug ) {
		$slug = sanitize_key( $slug );
		foreach ( self::get_catalog_courses() as $course ) {
			if ( isset( $course['slug'] ) && $slug === $course['slug'] ) {
				return (string) $course['name'];
			}
		}
		return $slug;
	}

	/**
	 * Admin-only ephemeral token for testing the verify endpoint.
	 *
	 * @return string
	 */
	public static function get_test_token() {
		return (string) get_option( self::OPTION_TEST_TOKEN, '' );
	}

	/**
	 * @return int Unix timestamp when test token was created, or 0.
	 */
	public static function get_test_token_created() {
		return (int) get_option( self::OPTION_TEST_TOKEN_CREATED, 0 );
	}

	/**
	 * @return bool
	 */
	public static function has_test_token() {
		return '' !== self::get_test_token();
	}

	/**
	 * Course URL with the current admin test token appended.
	 *
	 * @return string
	 */
	public static function get_test_course_access_url() {
		$token = self::get_test_token();
		$base  = self::get_course_url();
		if ( '' === $token || '' === $base ) {
			return '';
		}
		return add_query_arg( 'token', $token, $base );
	}

	/**
	 * @param string $token Access token.
	 * @return bool
	 */
	private function is_test_access_token( $token ) {
		$test = self::get_test_token();
		return '' !== $test && hash_equals( $test, $token );
	}

	/**
	 * Create or replace the admin test token (AJAX).
	 *
	 * @return void
	 */
	public function ajax_admin_create_test_token() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rtr-custom-assessment' ) ), 403 );
		}
		check_ajax_referer( 'ca_course_token_test', 'nonce' );

		$token = bin2hex( random_bytes( 32 ) );
		update_option( self::OPTION_TEST_TOKEN, $token );
		update_option( self::OPTION_TEST_TOKEN_CREATED, time() );

		wp_send_json_success(
			array(
				'token'      => $token,
				'verify_url' => add_query_arg( 'token', $token, self::get_verify_api_url() ),
				'course_url' => self::get_test_course_access_url(),
				'created'    => self::get_test_token_created(),
			)
		);
	}

	/**
	 * Hit the verify REST endpoint with the test token (AJAX).
	 *
	 * @return void
	 */
	public function ajax_admin_verify_test_token() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rtr-custom-assessment' ) ), 403 );
		}
		check_ajax_referer( 'ca_course_token_test', 'nonce' );

		$token = self::get_test_token();
		if ( '' === $token ) {
			wp_send_json_error( array( 'message' => __( 'No test token exists. Create one first.', 'rtr-custom-assessment' ) ) );
		}

		$verify_url = add_query_arg( 'token', $token, self::get_verify_api_url() );
		$response   = wp_remote_get(
			$verify_url,
			array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'message'    => $response->get_error_message(),
					'verify_url' => $verify_url,
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$valid = is_array( $body ) && ! empty( $body['valid'] );

		if ( 200 !== $code || ! $valid ) {
			wp_send_json_error(
				array(
					'message'    => __( 'Verify endpoint did not return valid: true.', 'rtr-custom-assessment' ),
					'verify_url' => $verify_url,
					'status'     => $code,
					'body'       => $body,
				)
			);
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Verify endpoint is working.', 'rtr-custom-assessment' ),
				'verify_url' => $verify_url,
				'body'       => $body,
			)
		);
	}

	/**
	 * Remove the admin test token (AJAX).
	 *
	 * @return void
	 */
	public function ajax_admin_delete_test_token() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'rtr-custom-assessment' ) ), 403 );
		}
		check_ajax_referer( 'ca_course_token_test', 'nonce' );

		delete_option( self::OPTION_TEST_TOKEN );
		delete_option( self::OPTION_TEST_TOKEN_CREATED );

		wp_send_json_success(
			array(
				'message' => __( 'Test token deleted.', 'rtr-custom-assessment' ),
			)
		);
	}

	public static function register_settings() {
		register_setting( 'ca_course_settings', self::OPTION_NAME, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'ca_course_settings', self::OPTION_PRICE, array( 'sanitize_callback' => 'floatval' ) );
		register_setting( 'ca_course_settings', self::OPTION_URL, array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'ca_course_settings', self::OPTION_REDIRECT_URL, array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'ca_course_settings', self::OPTION_TOKEN_EXPIRY_HOURS, array( 'sanitize_callback' => 'absint' ) );
	}
}
