<?php
/**
 * Uninstall script for "Custom Assessment".
 *
 * Removes plugin-created database tables and stored options.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

/**
 * Delete options and drop custom tables for the currently active blog.
 */
function ca_custom_assessment_uninstall_for_current_blog() : void {
	global $wpdb;

	// Options created/managed by the plugin.
	$options = array(
		'ca_custom_categories',
		'ca_custom_questions',
		'ca_question_overrides',
		'ca_action_logs',
	);

	foreach ($options as $option_key) {
		delete_option($option_key);
	}

	// Custom tables created on activation.
	$tables = array(
		$wpdb->prefix . 'ca_submissions',
		$wpdb->prefix . 'ca_answers',
		$wpdb->prefix . 'ca_category_scores',
	);

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	foreach ($tables as $table) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is derived from $wpdb->prefix.
		$wpdb->query('DROP TABLE IF EXISTS ' . $table);
	}
}

// Handle multisite: remove data from all sites if the plugin was network-activated.
if (is_multisite()) {
	$original_blog_id = get_current_blog_id();
	$site_ids = function_exists('get_sites') ? get_sites(array('fields' => 'ids')) : array();

	if (!empty($site_ids)) {
		foreach ($site_ids as $blog_id) {
			switch_to_blog((int) $blog_id);
			ca_custom_assessment_uninstall_for_current_blog();
		}
		switch_to_blog((int) $original_blog_id);
	} else {
		// Fallback: just delete for the current blog.
		ca_custom_assessment_uninstall_for_current_blog();
	}
} else {
	ca_custom_assessment_uninstall_for_current_blog();
}

