<?php
/**
 * Course access paywall: [ca_course_access] shortcode + AJAX handlers.
 * Stores course URL server-side; only reveals it after WooCommerce payment is confirmed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CA_Course {

	const OPTION_NAME  = 'ca_course_name';
	const OPTION_PRICE = 'ca_course_price';
	const OPTION_URL   = 'ca_course_url';

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

		add_action( 'woocommerce_thankyou', array( $this, 'render_course_link_on_thankyou' ), 20 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_course_link_on_thankyou' ), 20 );
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
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( ! $order->get_meta( '_ca_course_order' ) ) {
			return;
		}

		$course_url = get_option( self::OPTION_URL, '' );
		if ( ! $course_url ) {
			return;
		}

		$course_name = get_option( self::OPTION_NAME, __( 'Personal Equity Course', 'rtr-custom-assessment' ) );
		?>
		<div class="ca-course-thankyou" style="margin:24px 0; padding:20px 24px; background:#f9f5f5; border-left:4px solid #aa3130; border-radius:4px;">
			<h3 style="margin:0 0 8px;"><?php echo esc_html( $course_name ); ?></h3>
			<p style="margin:0 0 16px;"><?php esc_html_e( 'Your payment is confirmed. Click the button below to access your course.', 'rtr-custom-assessment' ); ?></p>
			<a href="<?php echo esc_url( $course_url ); ?>" class="ca-btn ca-btn--primary" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Access Your Course', 'rtr-custom-assessment' ); ?> &rarr;
			</a>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns the stored course URL if this email has a paid course order, otherwise ''.
	 *
	 * @param string $email
	 * @return string
	 */
	private function get_course_url_for_email( $email ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return '';
		}

		$orders = wc_get_orders( array(
			'meta_key'   => '_ca_course_email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => $email, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'status'     => array( 'wc-completed', 'wc-processing' ),
			'limit'      => 1,
			'return'     => 'ids',
		) );

		if ( empty( $orders ) ) {
			return '';
		}

		return (string) get_option( self::OPTION_URL, '' );
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

	public static function register_settings() {
		register_setting( 'ca_course_settings', self::OPTION_NAME, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'ca_course_settings', self::OPTION_PRICE, array( 'sanitize_callback' => 'floatval' ) );
		register_setting( 'ca_course_settings', self::OPTION_URL, array( 'sanitize_callback' => 'esc_url_raw' ) );
	}
}
