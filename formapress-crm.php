<?php
/**
 * Plugin Name: Formapress CRM
 * Plugin URI:  https://example.com/formapress-crm
 * Description: A CRM plugin to augment the Formapress suite, managing contacts, opportunities, and activities.
 * Version:     0.1.0
 * Author:      Your Name
 * Author URI:  https://example.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: formapress-crm
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Define constants
 */
define( 'FORMAPRESS_CRM_VERSION', '0.1.0' );
define( 'FORMAPRESS_CRM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FORMAPRESS_CRM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load plugin textdomain.
 */
function formapress_crm_load_textdomain() {
	load_plugin_textdomain( 'formapress-crm', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'formapress_crm_load_textdomain' );

/**
 * Include CPTs and Taxonomies
 */
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/cpt-person.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/taxonomy-company-role.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/synchronization.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/legacy-sync.php'; // NEW: Bridge to old system.
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/migration-reimport.php'; // Re-import with all fields.
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/admin-pages.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/cpt-opportunity.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/cpt-activity.php';

/**
 * Enqueue admin scripts and styles.
 */
function formapress_crm_admin_enqueue_scripts_styles( $hook_suffix ) {
	// Get current screen to check post type
	$screen = get_current_screen();

	// Enqueue Select2 CSS
	wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0' );
	// Enqueue your custom admin styles
	wp_enqueue_style( 'formapress-crm-admin-styles', FORMAPRESS_CRM_PLUGIN_URL . 'assets/css/formapress-crm-admin-styles.css', array(), FORMAPRESS_CRM_VERSION );

	// Enqueue Select2 JS
	wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0-rc.0', true );

	// Enqueue your custom admin script for initializing Select2 and other JS needs
	wp_enqueue_script( 'formapress-crm-admin-script', FORMAPRESS_CRM_PLUGIN_URL . 'assets/js/formapress-crm-admin.js', array( 'jquery', 'select2-js' ), FORMAPRESS_CRM_VERSION, true );

	// Pass data to your admin script
	wp_localize_script(
		'formapress-crm-admin-script',
		'formapressCrmAdmin',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'formapress_crm_admin_nonce' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'formapress_crm_admin_enqueue_scripts_styles' );

/**
 * Activation hook
 */
function formapress_crm_activate() {
	// Register CPTs and Taxonomies to ensure rewrite rules are flushed.
	formapress_crm_register_person_cpt();
	formapress_crm_register_company_role_taxonomy();
	formapress_crm_register_opportunity_cpt();
	formapress_crm_register_activity_cpt();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'formapress_crm_activate' );

/**
 * Deactivation hook
 */
function formapress_crm_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'formapress_crm_deactivate' );

// Add other plugin functionalities below, like admin menus, settings pages, etc.
