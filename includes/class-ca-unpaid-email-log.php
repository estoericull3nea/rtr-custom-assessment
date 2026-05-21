<?php
/**
 * Tracks unpaid full-results reminder emails per recipient (submission or bundle).
 */

if (!defined('ABSPATH')) {
	exit;
}

class CA_Unpaid_Email_Log
{
	const OPTION_KEY = 'ca_unpaid_email_send_history';
	const MAX_PER_RECIPIENT = 50;

	/**
	 * Record a successful reminder email for a recipient token.
	 *
	 * @param string               $token Recipient token (sub:ID or bundle:inner:social).
	 * @param array<string, mixed> $meta  Optional subject, email, tab.
	 * @return void
	 */
	public static function record_sent($token, $meta = array())
	{
		$token = sanitize_text_field((string) $token);
		if ('' === $token) {
			return;
		}

		$all = self::get_all();
		if (!isset($all[$token]) || !is_array($all[$token])) {
			$all[$token] = array();
		}

		$user = wp_get_current_user();
		$all[$token][] = array(
			'time' => current_time('mysql'),
			'timestamp' => time(),
			'status' => 'sent',
			'subject' => isset($meta['subject']) ? sanitize_text_field((string) $meta['subject']) : '',
			'email' => isset($meta['email']) ? sanitize_email((string) $meta['email']) : '',
			'tab' => isset($meta['tab']) ? sanitize_key((string) $meta['tab']) : '',
			'user_id' => get_current_user_id(),
			'user' => $user instanceof WP_User ? (string) $user->display_name : '',
		);

		if (count($all[$token]) > self::MAX_PER_RECIPIENT) {
			$all[$token] = array_slice($all[$token], -1 * self::MAX_PER_RECIPIENT);
		}

		update_option(self::OPTION_KEY, $all, false);
	}

	/**
	 * Whether at least one reminder was sent to this recipient.
	 *
	 * @param string $token Recipient token.
	 * @return bool
	 */
	public static function has_sent($token)
	{
		return !empty(self::get_history($token));
	}

	/**
	 * Send history for a recipient (newest first).
	 *
	 * @param string $token Recipient token.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_history($token)
	{
		$token = sanitize_text_field((string) $token);
		if ('' === $token) {
			return array();
		}

		$all = self::get_all();
		$entries = isset($all[$token]) && is_array($all[$token]) ? $all[$token] : array();

		if (empty($entries)) {
			$entries = self::history_from_logger($token);
		}

		usort(
			$entries,
			static function ($a, $b) {
				$ta = isset($a['timestamp']) ? (int) $a['timestamp'] : strtotime((string) ($a['time'] ?? ''));
				$tb = isset($b['timestamp']) ? (int) $b['timestamp'] : strtotime((string) ($b['time'] ?? ''));
				return $tb <=> $ta;
			}
		);

		$date_format = get_option('date_format') . ' ' . get_option('time_format');
		foreach ($entries as $key => $entry) {
			$ts = isset($entry['timestamp']) ? (int) $entry['timestamp'] : strtotime((string) ($entry['time'] ?? ''));
			if ($ts > 0) {
				$entries[ $key ]['time_display'] = date_i18n($date_format, $ts);
			}
		}

		return $entries;
	}

	/**
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private static function get_all()
	{
		$all = get_option(self::OPTION_KEY, array());
		return is_array($all) ? $all : array();
	}

	/**
	 * Backfill history from CA_Logger entries (older sends before dedicated storage).
	 *
	 * @param string $token Recipient token.
	 * @return array<int, array<string, mixed>>
	 */
	private static function history_from_logger($token)
	{
		if (!class_exists('CA_Logger')) {
			return array();
		}

		$entries = array();
		foreach (CA_Logger::get_logs() as $log) {
			if (!is_array($log)) {
				continue;
			}
			if ('admin_unpaid_bulk_email' !== (string) ($log['action'] ?? '')) {
				continue;
			}
			if ('success' !== (string) ($log['status'] ?? '')) {
				continue;
			}
			$context = isset($log['context']) && is_array($log['context']) ? $log['context'] : array();
			if ((string) ($context['token'] ?? '') !== $token) {
				continue;
			}

			$time = (string) ($log['time'] ?? '');
			$entries[] = array(
				'time' => $time,
				'timestamp' => $time ? strtotime($time) : 0,
				'status' => 'sent',
				'subject' => '',
				'email' => (string) ($context['email'] ?? ''),
				'tab' => (string) ($context['tab'] ?? ''),
				'user_id' => isset($log['user_id']) ? (int) $log['user_id'] : 0,
			);
		}

		return $entries;
	}
}
