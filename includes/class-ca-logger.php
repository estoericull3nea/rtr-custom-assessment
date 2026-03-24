<?php
/**
 * Simple plugin logger stored in wp_options.
 */

if (!defined('ABSPATH')) {
	exit;
}

class CA_Logger
{
	const OPTION_KEY = 'ca_action_logs';
	const MAX_ENTRIES = 1000;

	public static function log($action, $status, $message = '', $context = array())
	{
		$logs = self::get_logs();
		$logs[] = array(
			'time' => current_time('mysql'),
			'action' => (string) $action,
			'status' => (string) $status,
			'message' => (string) $message,
			'context' => is_array($context) ? $context : array(),
			'user_id' => get_current_user_id(),
			'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
		);

		if (count($logs) > self::MAX_ENTRIES) {
			$logs = array_slice($logs, -1 * self::MAX_ENTRIES);
		}

		update_option(self::OPTION_KEY, $logs, false);
	}

	public static function get_logs()
	{
		$logs = get_option(self::OPTION_KEY, array());
		return is_array($logs) ? $logs : array();
	}

	public static function clear_logs()
	{
		delete_option(self::OPTION_KEY);
	}
}

