<?php
/**
 * Google reCAPTCHA v2 for all assessment types when enabled in settings.
 */

if (!defined('ABSPATH')) {
	exit;
}

class CA_Recaptcha
{
	const OPTION_ENABLED = 'ca_recaptcha_enabled';
	const OPTION_SITE_KEY = 'ca_recaptcha_site_key';
	const OPTION_SECRET_KEY = 'ca_recaptcha_secret_key';

	/**
	 * Register settings (admin).
	 */
	public static function register_settings()
	{
		register_setting(
			'ca_recaptcha_settings',
			self::OPTION_ENABLED,
			array(
				'type' => 'string',
				'sanitize_callback' => array(__CLASS__, 'sanitize_enabled'),
				'default' => 'no',
			)
		);
		register_setting(
			'ca_recaptcha_settings',
			self::OPTION_SITE_KEY,
			array(
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => '',
			)
		);
		register_setting(
			'ca_recaptcha_settings',
			self::OPTION_SECRET_KEY,
			array(
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => '',
			)
		);
	}

	/**
	 * @param mixed $value Raw option value.
	 * @return string yes|no
	 */
	public static function sanitize_enabled($value)
	{
		return 'yes' === $value ? 'yes' : 'no';
	}

	/**
	 * @return bool
	 */
	public static function is_enabled()
	{
		return 'yes' === get_option(self::OPTION_ENABLED, 'no')
			&& '' !== self::get_site_key()
			&& '' !== self::get_secret_key();
	}

	/**
	 * reCAPTCHA applies before starting any assessment when configured.
	 *
	 * @param string $assessment_type Normalized type.
	 * @return bool
	 */
	public static function applies_to_assessment($assessment_type)
	{
		if (!self::is_enabled()) {
			return false;
		}
		$t = CA_Assessment_Types::normalize($assessment_type);
		return CA_Assessment_Types::MINDSET === $t
			|| CA_Assessment_Types::INNER_DIMENSIONS === $t
			|| CA_Assessment_Types::SOCIAL_FLUENCY === $t
			|| CA_Assessment_Types::BUNDLE === $t;
	}

	/**
	 * @return string
	 */
	public static function get_site_key()
	{
		return (string) get_option(self::OPTION_SITE_KEY, '');
	}

	/**
	 * @return string
	 */
	public static function get_secret_key()
	{
		return (string) get_option(self::OPTION_SECRET_KEY, '');
	}

	/**
	 * Client-side config for assessment.js.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_client_config()
	{
		return array(
			'enabled' => self::is_enabled(),
			'siteKey' => self::get_site_key(),
			'errorMessage' => __('Please complete the reCAPTCHA verification before starting the assessment.', 'rtr-custom-assessment'),
		);
	}

	/**
	 * Verify token with Google.
	 *
	 * @param string $token Response from g-recaptcha-response.
	 * @return bool
	 */
	public static function verify_response($token)
	{
		$token = sanitize_text_field((string) $token);
		$secret = self::get_secret_key();

		if ('' === $token || '' === $secret) {
			return false;
		}

		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 15,
				'body' => array(
					'secret' => $secret,
					'response' => $token,
					'remoteip' => isset($_SERVER['REMOTE_ADDR'])
						? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
						: '',
				),
			)
		);

		if (is_wp_error($response)) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		if ($code < 200 || $code >= 300) {
			return false;
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);
		return is_array($body) && !empty($body['success']);
	}
}
