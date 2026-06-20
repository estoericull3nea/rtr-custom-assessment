<?php
/**
 * WooCommerce email: Course access link for paid course orders.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CA_Email_Course_Access', false ) ) :

	/**
	 * Customer email with tokenized course access link.
	 */
	class CA_Email_Course_Access extends WC_Email {

		/** @var string */
		public $course_name = '';

		/** @var string */
		public $course_url = '';

		/** @var string */
		public $course_password = '';

		/** @var int */
		public $expiry_hours = 24;

		public function __construct() {
			$this->id             = 'ca_course_access';
			$this->customer_email = true;
			$this->title          = __( 'Course access', 'rtr-custom-assessment' );
			$this->description    = __( 'Sent to customers when a course order is paid, with their personal course access link.', 'rtr-custom-assessment' );
			$this->template_html  = 'emails/ca-course-access.php';
			$this->template_plain = 'emails/plain/ca-course-access.php';
			$this->template_base  = CA_PLUGIN_DIR . 'templates/';
			$this->placeholders   = array(
				'{course_name}'    => '',
				'{course_url}'     => '',
				'{order_date}'     => '',
				'{order_number}'   => '',
				'{customer_name}'  => '',
			);

			parent::__construct();
		}

		/**
		 * @return string
		 */
		public function get_default_subject() {
			return __( 'Your course access: {course_name}', 'rtr-custom-assessment' );
		}

		/**
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Your course is ready', 'rtr-custom-assessment' );
		}

		/**
		 * @param int                   $order_id Order ID.
		 * @param \WC_Order|false       $order    Order object.
		 * @param array<string, string> $context  Course name and URL.
		 * @return void
		 */
		public function trigger( $order_id, $order = false, $context = array() ) {
			$this->setup_locale();

			if ( $order_id && ! $order instanceof WC_Order ) {
				$order = wc_get_order( $order_id );
			}

			if ( ! $order instanceof WC_Order ) {
				$this->restore_locale();
				return;
			}

			if ( 'yes' !== (string) $order->get_meta( '_ca_course_order' ) ) {
				$this->restore_locale();
				return;
			}

			$course_name = isset( $context['name'] ) ? (string) $context['name'] : CA_Course::get_course_name();
			$course_url  = isset( $context['url'] ) ? (string) $context['url'] : CA_Course::build_course_access_url( $order );
			$password    = isset( $context['password'] ) ? (string) $context['password'] : '';
			$expiry      = isset( $context['expiry_hours'] ) ? (int) $context['expiry_hours'] : CA_Course::get_token_expiry_hours();

			if ( '' === $course_url ) {
				$this->restore_locale();
				return;
			}

			$this->object            = $order;
			$this->recipient         = $order->get_billing_email();
			$this->course_name       = $course_name;
			$this->course_url        = $course_url;
			$this->course_password   = $password;
			$this->expiry_hours      = $expiry;

			$this->placeholders['{course_name}']   = $course_name;
			$this->placeholders['{course_url}']    = $course_url;
			$this->placeholders['{order_date}']    = wc_format_datetime( $order->get_date_created() );
			$this->placeholders['{order_number}']  = $order->get_order_number();
			$this->placeholders['{customer_name}'] = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}

			$this->restore_locale();
		}

		/**
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				array(
					'order'           => $this->object,
					'email_heading'   => $this->get_heading(),
					'course_name'     => $this->course_name,
					'course_url'      => $this->course_url,
					'course_password' => $this->course_password,
					'expiry_hours'    => $this->expiry_hours,
					'sent_to_admin'   => false,
					'plain_text'      => false,
					'email'           => $this,
				),
				'',
				$this->template_base
			);
		}

		/**
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				array(
					'order'           => $this->object,
					'email_heading'   => $this->get_heading(),
					'course_name'     => $this->course_name,
					'course_url'      => $this->course_url,
					'course_password' => $this->course_password,
					'expiry_hours'    => $this->expiry_hours,
					'sent_to_admin'   => false,
					'plain_text'      => true,
					'email'           => $this,
				),
				'',
				$this->template_base
			);
		}
	}

endif;
