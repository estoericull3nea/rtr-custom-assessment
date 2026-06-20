<?php
/**
 * Courses admin section — dashboard, orders, catalog, and settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CA_Courses_Admin {

	const PAGE_SLUG = 'custom-assessment-courses';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'custom-assessment-hub',
			__( 'Courses', 'rtr-custom-assessment' ),
			__( 'Courses', 'rtr-custom-assessment' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * @param string $hook_suffix Admin hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( strpos( $hook_suffix, self::PAGE_SLUG ) === false ) {
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

		if ( 'settings' === $this->get_current_tab() ) {
			wp_localize_script(
				'ca-admin-scripts',
				'caCourseTokenTest',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'ca_course_token_test' ),
					'strings' => array(
						'creating'      => __( 'Creating test token…', 'rtr-custom-assessment' ),
						'testing'       => __( 'Testing verify API…', 'rtr-custom-assessment' ),
						'deleting'      => __( 'Deleting test token…', 'rtr-custom-assessment' ),
						'createFailed'  => __( 'Could not create test token.', 'rtr-custom-assessment' ),
						'verifyFailed'  => __( 'Verify test failed.', 'rtr-custom-assessment' ),
						'deleteFailed'  => __( 'Could not delete test token.', 'rtr-custom-assessment' ),
						'deleteConfirm' => __( 'Delete the active test token?', 'rtr-custom-assessment' ),
						'tokenActive'   => __( 'A test token is active (created %s).', 'rtr-custom-assessment' ),
						'noToken'       => __( 'No test token. Create one to verify the endpoint before going live.', 'rtr-custom-assessment' ),
						'replaceToken'  => __( 'Replace test token', 'rtr-custom-assessment' ),
						'createToken'   => __( 'Create test token', 'rtr-custom-assessment' ),
					),
				)
			);
		}

		if ( in_array( $this->get_current_tab(), array( 'orders', 'dashboard' ), true ) ) {
			wp_localize_script(
				'ca-admin-scripts',
				'caCourseResendAccess',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'ca_course_resend_access' ),
					'strings' => array(
						'confirm'  => __( 'Send a new access link and password to this customer? The previous link will stop working.', 'rtr-custom-assessment' ),
						'sending'  => __( 'Sending…', 'rtr-custom-assessment' ),
						'sent'     => __( 'New course access email sent.', 'rtr-custom-assessment' ),
						'failed'   => __( 'Could not resend course access email.', 'rtr-custom-assessment' ),
					),
				)
			);
		}
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'rtr-custom-assessment' ) );
		}

		$tab = $this->get_current_tab();
		switch ( $tab ) {
			case 'orders':
				$this->render_orders_page();
				return;
			case 'catalog':
				$this->render_catalog_page();
				return;
			case 'settings':
				$this->render_settings_page();
				return;
			default:
				$this->render_dashboard_page();
				return;
		}
	}

	/**
	 * @return string dashboard|orders|catalog|settings
	 */
	private function get_current_tab() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only routing.
		$tab = isset( $_GET['ca_course_tab'] ) ? sanitize_key( wp_unslash( $_GET['ca_course_tab'] ) ) : 'dashboard';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$allowed = array( 'dashboard', 'orders', 'catalog', 'settings' );
		return in_array( $tab, $allowed, true ) ? $tab : 'dashboard';
	}

	/**
	 * @param string $current_tab Active tab.
	 */
	private function render_nav_tabs( $current_tab ) {
		$tabs = array(
			'dashboard' => __( 'Dashboard', 'rtr-custom-assessment' ),
			'orders'    => __( 'Orders', 'rtr-custom-assessment' ),
			'catalog'   => __( 'Catalog', 'rtr-custom-assessment' ),
			'settings'  => __( 'Settings', 'rtr-custom-assessment' ),
		);

		echo '<nav class="nav-tab-wrapper ca-assessment-nav-tabs" aria-label="' . esc_attr__( 'Course sections', 'rtr-custom-assessment' ) . '">';
		foreach ( $tabs as $slug => $label ) {
			$url     = add_query_arg(
				array(
					'page'           => self::PAGE_SLUG,
					'ca_course_tab'  => $slug,
				),
				admin_url( 'admin.php' )
			);
			$classes = 'nav-tab' . ( $slug === $current_tab ? ' nav-tab-active' : '' );
			printf(
				'<a href="%1$s" class="%2$s">%3$s</a>',
				esc_url( $url ),
				esc_attr( $classes ),
				esc_html( $label )
			);
		}
		echo '</nav>';
	}

	/**
	 * @param string $tab Tab slug.
	 * @return array<string, string>
	 */
	private function screen_query_args( $tab ) {
		return array(
			'page'          => self::PAGE_SLUG,
			'ca_course_tab' => $tab,
		);
	}

	private function render_dashboard_page() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			$this->render_woocommerce_missing_notice();
			return;
		}

		$stats         = CA_Course::get_course_order_stats();
		$all_orders    = CA_Course::get_all_course_orders();
		$recent_orders = array_slice( $all_orders, 0, 5 );
		$orders_url    = add_query_arg( $this->screen_query_args( 'orders' ), admin_url( 'admin.php' ) );
		$latest_display = $stats['latest_order_date']
			? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( (string) $stats['latest_order_date'] ) )
			: '—';
		?>
		<div class="wrap ca-admin-wrap">
			<?php $this->render_nav_tabs( 'dashboard' ); ?>
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-welcome-learn-more"></span>
				<?php esc_html_e( 'Courses — Dashboard', 'rtr-custom-assessment' ); ?>
			</h1>

			<div class="ca-dashboard-grid">
				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html( (string) $stats['total'] ); ?></div>
					<div class="ca-dashboard-card-label"><?php esc_html_e( 'Total Orders', 'rtr-custom-assessment' ); ?></div>
				</div>
				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html( (string) $stats['paid'] ); ?></div>
					<div class="ca-dashboard-card-label"><?php esc_html_e( 'Paid / Active', 'rtr-custom-assessment' ); ?></div>
				</div>
				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html( (string) $stats['pending'] ); ?></div>
					<div class="ca-dashboard-card-label"><?php esc_html_e( 'Pending / Unpaid', 'rtr-custom-assessment' ); ?></div>
				</div>
				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html( (string) $stats['with_token'] ); ?></div>
					<div class="ca-dashboard-card-label"><?php esc_html_e( 'With Access Token', 'rtr-custom-assessment' ); ?></div>
				</div>
				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value">
						<?php
						if ( function_exists( 'wc_price' ) ) {
							echo wp_kses_post( wc_price( (float) $stats['revenue'] ) );
						} else {
							echo esc_html( number_format( (float) $stats['revenue'], 2 ) );
						}
						?>
					</div>
					<div class="ca-dashboard-card-label"><?php esc_html_e( 'Revenue (Paid)', 'rtr-custom-assessment' ); ?></div>
				</div>
				<div class="ca-dashboard-card">
					<div class="ca-dashboard-card-value"><?php echo esc_html( (string) count( CA_Course::get_catalog_courses() ) ); ?></div>
					<div class="ca-dashboard-card-label"><?php esc_html_e( 'Courses in Catalog', 'rtr-custom-assessment' ); ?></div>
				</div>
			</div>

			<div class="ca-dashboard-section">
				<h2><?php esc_html_e( 'Recent Orders', 'rtr-custom-assessment' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Latest course purchases. Latest order:', 'rtr-custom-assessment' ); ?>
					<strong><?php echo esc_html( $latest_display ); ?></strong>
				</p>

				<?php if ( empty( $recent_orders ) ) : ?>
					<div class="ca-admin-empty">
						<span class="dashicons dashicons-welcome-learn-more" aria-hidden="true"></span>
						<p><?php esc_html_e( 'No course orders yet. Use the shortcode [ca_course_access] on a page.', 'rtr-custom-assessment' ); ?></p>
					</div>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped ca-admin-table">
						<thead>
							<tr>
								<th scope="col" class="ca-col-id"><?php esc_html_e( 'Order', 'rtr-custom-assessment' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Customer', 'rtr-custom-assessment' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Email', 'rtr-custom-assessment' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Course', 'rtr-custom-assessment' ); ?></th>
								<th scope="col" class="ca-col-status"><?php esc_html_e( 'Status', 'rtr-custom-assessment' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Date', 'rtr-custom-assessment' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Action', 'rtr-custom-assessment' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $recent_orders as $order ) : ?>
								<?php $row = CA_Course::format_order_admin_row( $order ); ?>
								<tr>
									<td class="ca-col-id">#<?php echo esc_html( (string) $row['id'] ); ?></td>
									<td><strong><?php echo esc_html( $row['name'] ?: '—' ); ?></strong></td>
									<td><?php echo esc_html( $row['email'] ?: '—' ); ?></td>
									<td><?php echo esc_html( CA_Course::get_course_label_for_slug( $row['course_slug'] ) ); ?></td>
									<td class="ca-col-status">
										<span class="ca-status-badge ca-status--<?php echo esc_attr( $row['has_access'] ? 'completed' : 'in_progress' ); ?>">
											<?php echo esc_html( $row['status_label'] ); ?>
										</span>
									</td>
									<td>
										<?php
										echo $row['date_created']
											? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['date_created'] ) ) )
											: '—';
										?>
									</td>
									<td>
										<a href="<?php echo esc_url( $this->order_detail_url( (int) $row['id'] ) ); ?>" class="button button-small">
											<?php esc_html_e( 'View', 'rtr-custom-assessment' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p style="margin-top:12px;">
						<a href="<?php echo esc_url( $orders_url ); ?>" class="button button-secondary">
							<?php esc_html_e( 'View all orders', 'rtr-custom-assessment' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private function render_orders_page() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			$this->render_woocommerce_missing_notice();
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only UI state.
		$view         = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
		$order_id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$search       = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$current_page = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 'detail' === $view && $order_id > 0 ) {
			$this->render_order_detail_page( $order_id );
			return;
		}

		$all_orders = CA_Course::get_all_course_orders();
		if ( '' !== $search ) {
			$needle = strtolower( $search );
			$all_orders = array_values(
				array_filter(
					$all_orders,
					static function ( $order ) use ( $needle ) {
						$row = CA_Course::format_order_admin_row( $order );
						$hay = strtolower(
							implode(
								' ',
								array(
									(string) $row['id'],
									(string) $row['name'],
									(string) $row['email'],
									(string) $row['status'],
									(string) $row['course_slug'],
								)
							)
						);
						return false !== strpos( $hay, $needle );
					}
				)
			);
		}

		$stats              = CA_Course::get_course_order_stats();
		$per_page           = 10;
		$total_orders_count = count( $all_orders );
		$total_pages        = max( 1, (int) ceil( $total_orders_count / $per_page ) );
		$current_page       = min( $current_page, $total_pages );
		$offset             = ( $current_page - 1 ) * $per_page;
		$paged_orders       = array_slice( $all_orders, $offset, $per_page );
		$list_url           = add_query_arg( $this->screen_query_args( 'orders' ), admin_url( 'admin.php' ) );
		?>
		<div class="wrap ca-admin-wrap">
			<?php $this->render_nav_tabs( 'orders' ); ?>
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-welcome-learn-more"></span>
				<?php esc_html_e( 'Courses — Orders', 'rtr-custom-assessment' ); ?>
			</h1>

			<?php if ( empty( $all_orders ) && '' === $search ) : ?>
				<div class="ca-admin-empty">
					<span class="dashicons dashicons-welcome-learn-more" aria-hidden="true"></span>
					<p><?php esc_html_e( 'No course orders yet. Use the shortcode [ca_course_access] on a page.', 'rtr-custom-assessment' ); ?></p>
				</div>
			<?php else : ?>
				<div class="ca-questions-stats-grid">
					<div class="ca-stat-card">
						<div class="ca-stat-value"><?php echo esc_html( (string) $stats['total'] ); ?></div>
						<div class="ca-stat-label"><?php esc_html_e( 'Total Orders', 'rtr-custom-assessment' ); ?></div>
					</div>
					<div class="ca-stat-card">
						<div class="ca-stat-value"><?php echo esc_html( (string) $stats['paid'] ); ?></div>
						<div class="ca-stat-label"><?php esc_html_e( 'Paid / Active', 'rtr-custom-assessment' ); ?></div>
					</div>
					<div class="ca-stat-card">
						<div class="ca-stat-value"><?php echo esc_html( (string) $stats['with_token'] ); ?></div>
						<div class="ca-stat-label"><?php esc_html_e( 'With Token', 'rtr-custom-assessment' ); ?></div>
					</div>
					<div class="ca-stat-card">
						<div class="ca-stat-value">
							<?php
							if ( function_exists( 'wc_price' ) ) {
								echo wp_kses_post( wc_price( (float) $stats['revenue'] ) );
							} else {
								echo esc_html( number_format( (float) $stats['revenue'], 2 ) );
							}
							?>
						</div>
						<div class="ca-stat-label"><?php esc_html_e( 'Revenue (Paid)', 'rtr-custom-assessment' ); ?></div>
					</div>
				</div>

				<form method="get" action="" class="ca-questions-search" style="text-align:end; margin-top:16px;">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
					<input type="hidden" name="ca_course_tab" value="orders">
					<div class="ca-search-field">
						<label for="ca-search-course-orders"><?php esc_html_e( 'Search Orders', 'rtr-custom-assessment' ); ?></label>
						<input type="search" id="ca-search-course-orders" name="s" value="<?php echo esc_attr( $search ); ?>"
							placeholder="<?php esc_attr_e( 'Order ID, name, email, status, course slug…', 'rtr-custom-assessment' ); ?>">
					</div>
					<p class="submit" style="margin-top:8px;">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Search', 'rtr-custom-assessment' ); ?></button>
						<?php if ( '' !== $search ) : ?>
							<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Reset', 'rtr-custom-assessment' ); ?></a>
						<?php endif; ?>
					</p>
				</form>

				<div id="ca-course-resend-notice" class="notice" style="display:none; margin-top:16px;"></div>

				<table class="wp-list-table widefat fixed striped ca-admin-table" style="margin-top:16px;">
					<thead>
						<tr>
							<th scope="col" class="ca-col-id"><?php esc_html_e( 'Order', 'rtr-custom-assessment' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Customer', 'rtr-custom-assessment' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Email', 'rtr-custom-assessment' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Course', 'rtr-custom-assessment' ); ?></th>
							<th scope="col" class="ca-col-status"><?php esc_html_e( 'Status', 'rtr-custom-assessment' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Total', 'rtr-custom-assessment' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Token', 'rtr-custom-assessment' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Course Expired?', 'rtr-custom-assessment' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Date', 'rtr-custom-assessment' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'rtr-custom-assessment' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $paged_orders ) ) : ?>
							<tr>
								<td colspan="10"><?php esc_html_e( 'No orders match your search.', 'rtr-custom-assessment' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $paged_orders as $order ) : ?>
								<?php $row = CA_Course::format_order_admin_row( $order ); ?>
								<tr>
									<td class="ca-col-id">#<?php echo esc_html( (string) $row['id'] ); ?></td>
									<td><strong><?php echo esc_html( $row['name'] ?: '—' ); ?></strong></td>
									<td><?php echo esc_html( $row['email'] ?: '—' ); ?></td>
									<td><?php echo esc_html( CA_Course::get_course_label_for_slug( $row['course_slug'] ) ); ?></td>
									<td class="ca-col-status">
										<span class="ca-status-badge ca-status--<?php echo esc_attr( $row['has_access'] ? 'completed' : 'in_progress' ); ?>">
											<?php echo esc_html( $row['status_label'] ); ?>
										</span>
									</td>
									<td>
										<?php
										if ( function_exists( 'wc_price' ) ) {
											echo wp_kses_post( wc_price( $row['total'], array( 'currency' => $row['currency'] ) ) );
										} else {
											echo esc_html( number_format( (float) $row['total'], 2 ) );
										}
										?>
									</td>
									<td><?php echo $row['token'] ? esc_html__( 'Yes', 'rtr-custom-assessment' ) : esc_html__( 'No', 'rtr-custom-assessment' ); ?></td>
									<td>
										<?php
										echo ! empty( $row['is_expired'] )
											? esc_html__( 'Yes', 'rtr-custom-assessment' )
											: esc_html__( 'No', 'rtr-custom-assessment' );
										?>
									</td>
									<td>
										<?php
										echo $row['date_created']
											? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $row['date_created'] ) ) )
											: '—';
										?>
									</td>
									<td>
										<a href="<?php echo esc_url( $this->order_detail_url( (int) $row['id'] ) ); ?>" class="button button-small">
											<?php esc_html_e( 'View', 'rtr-custom-assessment' ); ?>
										</a>
										<?php if ( ! empty( $row['is_expired'] ) && $row['has_access'] ) : ?>
											<button type="button" class="button button-small ca-course-resend-access" data-order-id="<?php echo esc_attr( (string) $row['id'] ); ?>">
												<?php esc_html_e( 'Resend access', 'rtr-custom-assessment' ); ?>
											</button>
										<?php endif; ?>
										<?php if ( $row['wc_edit_url'] ) : ?>
											<a href="<?php echo esc_url( $row['wc_edit_url'] ); ?>" class="button button-small" target="_blank" rel="noopener noreferrer">
												<?php esc_html_e( 'WooCommerce', 'rtr-custom-assessment' ); ?>
											</a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav bottom">
						<div class="tablenav-pages">
							<span class="displaying-num">
								<?php
								printf(
									esc_html(
										/* translators: %s: number of orders */
										_n( '%s order', '%s orders', $total_orders_count, 'rtr-custom-assessment' )
									),
									esc_html( (string) $total_orders_count )
								);
								?>
							</span>
							<?php
							echo wp_kses_post(
								paginate_links(
									array(
										'base'      => add_query_arg( 'paged', '%#%', $list_url ),
										'format'    => '',
										'prev_text' => '&laquo;',
										'next_text' => '&raquo;',
										'total'     => $total_pages,
										'current'   => $current_page,
									)
								)
							);
							?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param int $order_id Order ID.
	 */
	private function render_order_detail_page( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			$this->render_woocommerce_missing_notice();
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || 'yes' !== (string) $order->get_meta( '_ca_course_order' ) ) {
			wp_die( esc_html__( 'Course order not found.', 'rtr-custom-assessment' ) );
		}

		$row       = CA_Course::format_order_admin_row( $order );
		$list_url  = add_query_arg( $this->screen_query_args( 'orders' ), admin_url( 'admin.php' ) );
		$date_fmt  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		?>
		<div class="wrap ca-admin-wrap">
			<?php $this->render_nav_tabs( 'orders' ); ?>
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-welcome-learn-more"></span>
				<?php
				printf(
					/* translators: %d: order ID */
					esc_html__( 'Course Order #%d', 'rtr-custom-assessment' ),
					(int) $row['id']
				);
				?>
			</h1>

			<p>
				<a href="<?php echo esc_url( $list_url ); ?>" class="button button-secondary">
					&larr; <?php esc_html_e( 'Back to orders', 'rtr-custom-assessment' ); ?>
				</a>
				<?php if ( $row['has_access'] ) : ?>
					<button type="button" class="button button-primary ca-course-resend-access" data-order-id="<?php echo esc_attr( (string) $row['id'] ); ?>">
						<?php esc_html_e( 'Resend access email', 'rtr-custom-assessment' ); ?>
					</button>
				<?php endif; ?>
				<?php if ( $row['wc_edit_url'] ) : ?>
					<a href="<?php echo esc_url( $row['wc_edit_url'] ); ?>" class="button button-secondary" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Open in WooCommerce', 'rtr-custom-assessment' ); ?>
					</a>
				<?php endif; ?>
			</p>

			<div id="ca-course-resend-notice" class="notice" style="display:none; margin:16px 0;"></div>

			<div class="ca-admin-card" style="max-width:960px;">
				<h2 class="ca-admin-card-title"><?php esc_html_e( 'Customer', 'rtr-custom-assessment' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Name', 'rtr-custom-assessment' ); ?></th>
							<td><?php echo esc_html( $row['name'] ?: '—' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Email', 'rtr-custom-assessment' ); ?></th>
							<td><?php echo esc_html( $row['email'] ?: '—' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Course', 'rtr-custom-assessment' ); ?></th>
							<td><?php echo esc_html( CA_Course::get_course_label_for_slug( $row['course_slug'] ) ); ?> <code><?php echo esc_html( $row['course_slug'] ); ?></code></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="ca-admin-card" style="max-width:960px; margin-top:24px;">
				<h2 class="ca-admin-card-title"><?php esc_html_e( 'Payment', 'rtr-custom-assessment' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Status', 'rtr-custom-assessment' ); ?></th>
							<td>
								<span class="ca-status-badge ca-status--<?php echo esc_attr( $row['has_access'] ? 'completed' : 'in_progress' ); ?>">
									<?php echo esc_html( $row['status_label'] ); ?>
								</span>
								<?php if ( $row['has_access'] ) : ?>
									<span class="description"> — <?php esc_html_e( 'Access granted', 'rtr-custom-assessment' ); ?></span>
								<?php else : ?>
									<span class="description"> — <?php esc_html_e( 'No access', 'rtr-custom-assessment' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Total', 'rtr-custom-assessment' ); ?></th>
							<td>
								<?php
								if ( function_exists( 'wc_price' ) ) {
									echo wp_kses_post( wc_price( $row['total'], array( 'currency' => $row['currency'] ) ) );
								} else {
									echo esc_html( number_format( (float) $row['total'], 2 ) );
								}
								?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Order date', 'rtr-custom-assessment' ); ?></th>
							<td><?php echo $row['date_created'] ? esc_html( date_i18n( $date_fmt, strtotime( $row['date_created'] ) ) ) : '—'; ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Paid date', 'rtr-custom-assessment' ); ?></th>
							<td><?php echo $row['date_paid'] ? esc_html( date_i18n( $date_fmt, strtotime( $row['date_paid'] ) ) ) : '—'; ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="ca-admin-card" style="max-width:960px; margin-top:24px;">
				<h2 class="ca-admin-card-title"><?php esc_html_e( 'Access', 'rtr-custom-assessment' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Access token', 'rtr-custom-assessment' ); ?></th>
							<td>
								<?php if ( $row['token'] ) : ?>
									<code style="word-break:break-all;"><?php echo esc_html( $row['token'] ); ?></code>
								<?php else : ?>
									<?php esc_html_e( 'Not generated yet (created when order is paid).', 'rtr-custom-assessment' ); ?>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Token created', 'rtr-custom-assessment' ); ?></th>
							<td>
								<?php
								echo $row['token_created']
									? esc_html( date_i18n( $date_fmt, strtotime( $row['token_created'] ) ) )
									: '—';
								?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Link expires', 'rtr-custom-assessment' ); ?></th>
							<td>
								<?php
								if ( $row['expiry_hours'] <= 0 ) {
									esc_html_e( 'No expiry', 'rtr-custom-assessment' );
								} elseif ( $row['expires_at'] ) {
									echo esc_html( date_i18n( $date_fmt, strtotime( $row['expires_at'] ) ) );
									if ( $row['is_expired'] ) {
										echo ' <span class="description">(' . esc_html__( 'expired', 'rtr-custom-assessment' ) . ')</span>';
									}
								} else {
									echo '—';
								}
								?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Course access URL', 'rtr-custom-assessment' ); ?></th>
							<td>
								<?php if ( $row['access_url'] ) : ?>
									<a href="<?php echo esc_url( $row['access_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $row['access_url'] ); ?></a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private function render_catalog_page() {
		$courses = CA_Course::get_catalog_courses();
		?>
		<div class="wrap ca-admin-wrap">
			<?php $this->render_nav_tabs( 'catalog' ); ?>
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-welcome-learn-more"></span>
				<?php esc_html_e( 'Courses — Catalog', 'rtr-custom-assessment' ); ?>
			</h1>
			<p class="description"><?php esc_html_e( 'All registered courses. Add more courses here as the plugin grows.', 'rtr-custom-assessment' ); ?></p>

			<?php if ( empty( $courses ) ) : ?>
				<div class="ca-admin-empty">
					<span class="dashicons dashicons-welcome-learn-more" aria-hidden="true"></span>
					<p><?php esc_html_e( 'No courses configured yet.', 'rtr-custom-assessment' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped ca-admin-table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Course', 'rtr-custom-assessment' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Slug', 'rtr-custom-assessment' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Price', 'rtr-custom-assessment' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Shortcode', 'rtr-custom-assessment' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Course URL', 'rtr-custom-assessment' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Product', 'rtr-custom-assessment' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $courses as $course ) : ?>
							<tr>
								<td><strong><?php echo esc_html( (string) $course['name'] ); ?></strong></td>
								<td><code><?php echo esc_html( (string) $course['slug'] ); ?></code></td>
								<td>
									<?php
									if ( function_exists( 'wc_price' ) ) {
										echo wp_kses_post( wc_price( (float) $course['price'] ) );
									} else {
										echo esc_html( number_format( (float) $course['price'], 2 ) );
									}
									?>
								</td>
								<td><code><?php echo esc_html( (string) $course['shortcode'] ); ?></code></td>
								<td>
									<?php if ( ! empty( $course['url'] ) ) : ?>
										<a href="<?php echo esc_url( (string) $course['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $course['url'] ); ?></a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td>
									<?php if ( ! empty( $course['product_id'] ) ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( (int) $course['product_id'] ) ); ?>">
											#<?php echo esc_html( (string) $course['product_id'] ); ?>
										</a>
									<?php else : ?>
										<?php esc_html_e( 'Not created', 'rtr-custom-assessment' ); ?>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php foreach ( $courses as $course ) : ?>
					<div class="ca-admin-card" style="max-width:960px; margin-top:24px;">
						<h2 class="ca-admin-card-title"><?php echo esc_html( (string) $course['name'] ); ?></h2>
						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row"><?php esc_html_e( 'Verify API', 'rtr-custom-assessment' ); ?></th>
									<td><code><?php echo esc_html( (string) $course['verify_api_url'] ); ?></code></td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Unauthorized redirect', 'rtr-custom-assessment' ); ?></th>
									<td><code><?php echo esc_html( (string) $course['redirect_url'] ); ?></code></td>
								</tr>
							</tbody>
						</table>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_settings_page() {
		$test_token  = CA_Course::get_test_token();
		$test_created = CA_Course::get_test_token_created();
		$test_course = CA_Course::get_test_course_access_url();
		$test_verify = '' !== $test_token
			? add_query_arg( 'token', $test_token, CA_Course::get_verify_api_url() )
			: '';
		?>
		<div class="wrap ca-admin-wrap">
			<?php $this->render_nav_tabs( 'settings' ); ?>
			<h1 class="ca-admin-title">
				<span class="ca-admin-title-icon dashicons dashicons-admin-settings"></span>
				<?php esc_html_e( 'Courses — Settings', 'rtr-custom-assessment' ); ?>
			</h1>

			<?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Course settings saved.', 'rtr-custom-assessment' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php" class="ca-settings-form">
				<?php settings_fields( 'ca_course_settings' ); ?>

				<div class="ca-admin-card" style="max-width:720px;">
					<h2 class="ca-admin-card-title"><?php esc_html_e( 'Course Access', 'rtr-custom-assessment' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Configure course paywall settings. After payment, customers receive the course URL with a unique access token.', 'rtr-custom-assessment' ); ?>
					</p>

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="ca-course-name"><?php esc_html_e( 'Course Name', 'rtr-custom-assessment' ); ?></label>
								</th>
								<td>
									<input type="text" id="ca-course-name" name="<?php echo esc_attr( CA_Course::OPTION_NAME ); ?>"
										value="<?php echo esc_attr( CA_Course::get_course_name() ); ?>" class="regular-text">
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="ca-course-price"><?php esc_html_e( 'Price (USD)', 'rtr-custom-assessment' ); ?></label>
								</th>
								<td>
									<input type="number" id="ca-course-price" name="<?php echo esc_attr( CA_Course::OPTION_PRICE ); ?>"
										value="<?php echo esc_attr( CA_Course::get_course_price() ); ?>"
										class="small-text" min="0" step="0.01">
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="ca-course-url"><?php esc_html_e( 'Course URL', 'rtr-custom-assessment' ); ?></label>
								</th>
								<td>
									<input type="url" id="ca-course-url" name="<?php echo esc_attr( CA_Course::OPTION_URL ); ?>"
										value="<?php echo esc_attr( CA_Course::get_course_url() ); ?>" class="regular-text"
										placeholder="https://...">
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="ca-course-redirect-url"><?php esc_html_e( 'Unauthorized redirect URL', 'rtr-custom-assessment' ); ?></label>
								</th>
								<td>
									<input type="url" id="ca-course-redirect-url" name="<?php echo esc_attr( CA_Course::OPTION_REDIRECT_URL ); ?>"
										value="<?php echo esc_attr( CA_Course::get_redirect_url() ); ?>" class="regular-text">
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="ca-course-token-expiry-hours"><?php esc_html_e( 'Access link expiry (hours)', 'rtr-custom-assessment' ); ?></label>
								</th>
								<td>
									<input type="number" id="ca-course-token-expiry-hours" name="<?php echo esc_attr( CA_Course::OPTION_TOKEN_EXPIRY_HOURS ); ?>"
										value="<?php echo esc_attr( CA_Course::get_token_expiry_hours() ); ?>" class="small-text" min="0" step="1">
									<p class="description"><?php esc_html_e( 'Hours before each access link expires. Default 24. Set 0 for no expiry.', 'rtr-custom-assessment' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Token verify API', 'rtr-custom-assessment' ); ?></th>
								<td>
									<code><?php echo esc_html( CA_Course::get_verify_api_url() ); ?></code>
								</td>
							</tr>
						</tbody>
					</table>

					<?php submit_button( __( 'Save course settings', 'rtr-custom-assessment' ) ); ?>
				</div>
			</form>

			<div id="ca-course-token-test" class="ca-admin-card" style="max-width:720px; margin-top:32px;">
				<h2 class="ca-admin-card-title"><?php esc_html_e( 'Token verify test', 'rtr-custom-assessment' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Create a temporary test token, call the verify API, then delete it when finished.', 'rtr-custom-assessment' ); ?>
				</p>

				<div id="ca-course-token-test-status" class="notice inline <?php echo CA_Course::has_test_token() ? 'notice-info' : 'notice-warning'; ?>" style="margin:16px 0;">
					<p id="ca-course-token-test-status-text">
						<?php
						if ( CA_Course::has_test_token() ) {
							printf(
								/* translators: %s: localized date/time */
								esc_html__( 'A test token is active (created %s).', 'rtr-custom-assessment' ),
								esc_html( $test_created ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $test_created ) : '—' )
							);
						} else {
							esc_html_e( 'No test token. Create one to verify the endpoint before going live.', 'rtr-custom-assessment' );
						}
						?>
					</p>
				</div>

				<table class="form-table" role="presentation">
					<tbody>
						<tr id="ca-course-token-test-row" <?php echo CA_Course::has_test_token() ? '' : 'style="display:none;"'; ?>>
							<th scope="row"><?php esc_html_e( 'Test token', 'rtr-custom-assessment' ); ?></th>
							<td><code id="ca-course-token-test-value"><?php echo esc_html( $test_token ); ?></code></td>
						</tr>
						<tr id="ca-course-token-test-verify-row" <?php echo CA_Course::has_test_token() ? '' : 'style="display:none;"'; ?>>
							<th scope="row"><?php esc_html_e( 'Test verify URL', 'rtr-custom-assessment' ); ?></th>
							<td>
								<a id="ca-course-token-test-verify-link" href="<?php echo esc_url( $test_verify ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $test_verify ); ?></a>
							</td>
						</tr>
						<tr id="ca-course-token-test-course-row" <?php echo ( '' !== $test_course ) ? '' : 'style="display:none;"'; ?>>
							<th scope="row"><?php esc_html_e( 'Test course URL', 'rtr-custom-assessment' ); ?></th>
							<td>
								<a id="ca-course-token-test-course-link" href="<?php echo esc_url( $test_course ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $test_course ); ?></a>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
					<button type="button" class="button button-primary" id="ca-course-token-test-create">
						<?php
						echo esc_html(
							CA_Course::has_test_token()
								? __( 'Replace test token', 'rtr-custom-assessment' )
								: __( 'Create test token', 'rtr-custom-assessment' )
						);
						?>
					</button>
					<button type="button" class="button" id="ca-course-token-test-verify" <?php echo CA_Course::has_test_token() ? '' : 'disabled'; ?>>
						<?php esc_html_e( 'Test verify API', 'rtr-custom-assessment' ); ?>
					</button>
					<button type="button" class="button" id="ca-course-token-test-delete" <?php echo CA_Course::has_test_token() ? '' : 'disabled'; ?>>
						<?php esc_html_e( 'Delete test token', 'rtr-custom-assessment' ); ?>
					</button>
				</p>

				<div id="ca-course-token-test-result" aria-live="polite"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param int $order_id Order ID.
	 * @return string
	 */
	private function order_detail_url( $order_id ) {
		return add_query_arg(
			array(
				'page'          => self::PAGE_SLUG,
				'ca_course_tab' => 'orders',
				'view'          => 'detail',
				'id'            => $order_id,
			),
			admin_url( 'admin.php' )
		);
	}

	private function render_woocommerce_missing_notice() {
		?>
		<div class="wrap ca-admin-wrap">
			<?php $this->render_nav_tabs( $this->get_current_tab() ); ?>
			<div class="notice notice-warning">
				<p><?php esc_html_e( 'WooCommerce is required to manage course orders.', 'rtr-custom-assessment' ); ?></p>
			</div>
		</div>
		<?php
	}
}
