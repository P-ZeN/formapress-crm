<?php
/**
 * FORMA PRESS - CRM
 *
 * @package           zform
 * @author            Philippe Zénone
 * @copyright         2021 Philippe Zénone
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       FORMA PRESS - CRM
 * Plugin URI:        https://philippezenone.net/repo/wordpress/zformations/
 * Description:       A CRM plugin to augment the Formapress suite, managing contacts, opportunities, and activities.
 * Version:           2.0.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Philippe Zénone
 * Author URI:        https://philippezenone.net/
 * Text Domain:       formapress-crm
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
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
 * Note: Person and Company CPTs are now registered in zFormations base plugin.
 * These managers are kept for their utility functions only.
 */
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/default-schemas.php'; // Week 4: Immutable attribute schemas.
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/attribute-sanitization.php'; // Week 4: Sanitization filters for locked fields.
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/migration-v2-attributes.php'; // Week 4: Unified v1→v2 attribute migration.
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/class-formapress-person-manager.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/class-formapress-company-manager.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/class-formapress-migration-manager.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/class-formapress-shortcode-manager.php';

/**
 * Include v2 Admin UI
 */
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/admin/class-formapress-person-meta-boxes.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/admin/class-formapress-company-meta-boxes.php';

/**
 * Include CPTs and Taxonomies
 * Note: crm_person and crm_company CPTs are now in zFormations base (includes/cpt-person.php, includes/cpt-company.php)
 */
// require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/cpt-person.php'; // Moved to zFormations.
// require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/taxonomy-company-role.php'; // Moved to zFormations.
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/synchronization.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/legacy-sync.php'; // Bridge to old system.
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/migration-reimport.php'; // Re-import with all fields.
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/admin-pages.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/cpt-opportunity.php';
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/cpt-invoice.php'; // Financial tracking for BPF.
require_once FORMAPRESS_CRM_PLUGIN_DIR . 'includes/cpt-activity.php';

/**
 * Initialize v2 Managers
 * Note: Person and Company managers' init() disabled to prevent duplicate CPT registration.
 * CPTs are now registered in zFormations. Managers kept for utility functions.
 */
// FormaPress_Person_Manager::init(); // Disabled - CPT now in zFormations.
// FormaPress_Company_Manager::init(); // Disabled - CPT now in zFormations.
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
	// Note: crm_person and crm_company are now in zFormations base.
	formapress_crm_register_opportunity_cpt();
	formapress_crm_register_invoice_cpt();
	formapress_crm_register_activity_cpt();
	flush_rewrite_rules();

	// Week 4: Initialize default attribute schemas for backward compatibility with v1.
	// These options store immutable field definitions that cannot be deleted by admins.
	formapress_crm_initialize_default_attributes();
}

/**
 * Initialize default attribute schemas on plugin activation.
 *
 * This ensures v1 core fields (from zqpm_entreprise, zqpm_financeur, referent)
 * are present as locked attributes in the CRM system. Admins can add new attributes
 * but cannot delete these v1 core fields.
 *
 * @return void
 */
function formapress_crm_initialize_default_attributes() {
	// Only initialize if options don't exist yet (first activation).
	// Company attributes (from v1 zqpm_entreprise).
	if ( false === get_option( 'crm_company_attributes' ) ) {
		add_option( 'crm_company_attributes', formapress_get_default_company_attributes() );
	}

	// Referent attributes (from v1 entreprise.referent serialized array).
	if ( false === get_option( 'crm_person_referent_attributes' ) ) {
		add_option( 'crm_person_referent_attributes', formapress_get_default_referent_attributes() );
	}

	// Instructor attributes (from v1 zqpm_formateur).
	if ( false === get_option( 'crm_person_instructor_attributes' ) ) {
		add_option( 'crm_person_instructor_attributes', formapress_get_default_instructor_attributes() );
	}

	// Trainee attributes (from v1 zqpm_stagiaire).
	if ( false === get_option( 'crm_person_trainee_attributes' ) ) {
		add_option( 'crm_person_trainee_attributes', formapress_get_default_trainee_attributes() );
	}

	// Funder attributes (from v1 zqpm_financeur).
	if ( false === get_option( 'crm_person_funder_attributes' ) ) {
		add_option( 'crm_person_funder_attributes', formapress_get_default_funder_attributes() );
	}

	// Prospect attributes (new in v2 for pipeline).
	if ( false === get_option( 'crm_person_prospect_attributes' ) ) {
		add_option( 'crm_person_prospect_attributes', formapress_get_default_prospect_attributes() );
	}

	// Opportunity attributes (new in v2 for pipeline sales tracking).
	if ( false === get_option( 'crm_opportunity_attributes' ) ) {
		add_option( 'crm_opportunity_attributes', formapress_get_default_opportunity_attributes() );
	}
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
