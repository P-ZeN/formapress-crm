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
 * Include v2 Core Classes (Week 2+)
 */
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/class-formapress-person-manager.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/class-formapress-company-manager.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/class-formapress-migration-manager.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/class-formapress-shortcode-manager.php';

/**
 * Include v2 Admin UI
 */
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/admin/class-formapress-person-meta-boxes.php';

/**
 * Include CPTs and Taxonomies
 */
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/cpt-person.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/taxonomy-company-role.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/synchronization.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/legacy-sync.php'; // Bridge to old system.
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/migration-reimport.php'; // Re-import with all fields.
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/admin-pages.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/cpt-opportunity.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/cpt-invoice.php'; // Financial tracking for BPF.
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/cpt-activity.php';

/**
 * Initialize v2 Managers
 */
FormaPress_Person_Manager::init();
FormaPress_Company_Manager::init();
FormaPress_Shortcode_Manager::init();

/**
 * Enqueue admin scripts and styles.
 */
function formapress_crm_admin_enqueue_scripts_styles( $hook_suffix ) {
	// Get current screen to check post type.
	$screen = get_current_screen();

	// Enqueue zFormations admin scripts (required for attribute management).
	// These provide zformAddRow(), swap_input_elements(), grab_row() functions.
	if ( function_exists( 'wp_enqueue_script' ) ) {
		// Enqueue wp-color-picker (required by zform_scripts).
		wp_enqueue_style( 'wp-color-picker' );

		// Check if zformations scripts are registered, if not, register them.
		if ( ! wp_script_is( 'zform_scripts', 'registered' ) ) {
			wp_register_script(
				'zform_scripts',
				plugins_url( 'zformations/assets/js/zform_admin.js' ),
				array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ),
				'1.0',
				true
			);
		}
		// Enqueue zformations scripts.
		wp_enqueue_script( 'zform_scripts' );

		// Check if zformations styles are registered.
		if ( ! wp_style_is( 'zform_style', 'registered' ) ) {
			wp_register_style(
				'zform_style',
				plugins_url( 'zformations/assets/css/zform_admin.css' ),
				array( 'wp-color-picker' ),
				'1.0'
			);
		}
		// Enqueue zformations styles.
		wp_enqueue_style( 'zform_style' );
	}

	// Enqueue Select2 CSS.
	wp_enqueue_style( 'select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0' );
	// Enqueue custom admin styles.
	wp_enqueue_style( 'formapress-crm-admin-styles', FORMAPRESS_CRM_PLUGIN_URL . 'assets/css/formapress-crm-admin-styles.css', array( 'zform_style' ), FORMAPRESS_CRM_VERSION );

	// Enqueue Select2 JS.
	wp_enqueue_script( 'select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), '4.1.0-rc.0', true );

	// Enqueue custom admin script - depends on zform_scripts for attribute management.
	wp_enqueue_script( 'formapress-crm-admin-script', FORMAPRESS_CRM_PLUGIN_URL . 'assets/js/formapress-crm-admin.js', array( 'jquery', 'select2-js', 'zform_scripts' ), FORMAPRESS_CRM_VERSION, true );

	// Pass data to admin script.
	wp_localize_script(
		'formapress-crm-admin-script',
		'formapressCrmAdmin',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'formapress_crm_admin_nonce' ),
			'i18n'     => array(
				'text'     => __( 'Text', 'zformations' ),
				'number'   => __( 'Number', 'zformations' ),
				'wyswyg'   => __( 'Editor', 'zformations' ),
				'list'     => __( 'Formatted list', 'zformations' ),
				'download' => __( 'Download', 'zformations' ),
				'image'    => __( 'Image', 'zformations' ),
				'video'    => __( 'Video', 'zformations' ),
				'date'     => __( 'Date', 'zformations' ),
				'checkbox' => __( 'Checkbox', 'zformations' ),
				'radio'    => __( 'Radio', 'zformations' ),
				'select'   => __( 'Select', 'zformations' ),
				'tel'      => __( 'Tel', 'zformations' ),
				'mail'     => __( 'Mail', 'zformations' ),
				'textarea' => __( 'Textarea', 'zformations' ),
				'helptext' => __( 'Help text', 'zformations' ),
			),
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
	formapress_crm_register_invoice_cpt();
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
