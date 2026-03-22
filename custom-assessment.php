<?php
/**
 * Plugin Name: Custom Assessment
 * Description: A full-screen AJAX-powered entrepreneurial mindset assessment with admin dashboard.
 * Version:     3.1.0
 * Author:      Ericson Palisoc
 * License:     GPL-2.0+
 * Text Domain: rtr-custom-assessment
 */

if (!defined('ABSPATH')) {
	exit;
}

// Constants
define('CA_VERSION', '2.0.0');
define('CA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CA_TEXT_DOMAIN', 'rtr-custom-assessment');

// Load includes
require_once CA_PLUGIN_DIR . 'includes/class-ca-database.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-questions.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-scoring.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-ajax.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-shortcode.php';
require_once CA_PLUGIN_DIR . 'includes/class-ca-mailer.php';
require_once CA_PLUGIN_DIR . 'admin/class-ca-admin.php';

// Activation / Deactivation hooks
register_activation_hook(__FILE__, array('CA_Database', 'create_tables'));

// Boot the plugin
add_action('plugins_loaded', static function () {
	new CA_Ajax();
	new CA_Shortcode();
	new CA_Admin();
});
