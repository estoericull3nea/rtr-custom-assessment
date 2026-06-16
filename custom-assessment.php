<?php
/**
 * Plugin Name: Custom Assessment
 * Description: A full-screen AJAX-powered entrepreneurial mindset assessment with admin dashboard.
 * Version:     1.0.0
 * Author:      Ericson Palisoc
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License:     GPL-2.0+
 * Text Domain: rtr-custom-assessment
 */

if (!defined('ABSPATH')) {
	exit;
}

// Constants
define('CA_VERSION', '2.0.1');
define('CA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CA_TEXT_DOMAIN', 'rtr-custom-assessment');

if (is_readable(CA_PLUGIN_DIR . 'vendor/autoload.php')) {
	require_once CA_PLUGIN_DIR . 'vendor/autoload.php';
}

// Load includes
require_once CA_PLUGIN_DIR . 'includes/class-ca-database.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-assessment-types.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-questions.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-social-fluency-questions.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-inner-dimensions-questions.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-assessment-registry.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-scoring.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-logger.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-pdf.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-ajax.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-shortcode.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-mailer.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-unpaid-email-log.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-recaptcha.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-course.php';
require_once CA_PLUGIN_DIR . 'admin/class-ca-admin.php';

// Activation / Deactivation hooks
register_activation_hook(__FILE__, array('CA_Database', 'create_tables'));

// Boot the plugin
add_action('plugins_loaded', static function () {
	CA_Database::maybe_upgrade();
	add_action('admin_init', array('CA_Recaptcha', 'register_settings'));
	add_action('admin_init', array('CA_Course', 'register_settings'));
	new CA_Ajax();
	new CA_Shortcode();
	new CA_Course();
	new CA_Admin();
});
