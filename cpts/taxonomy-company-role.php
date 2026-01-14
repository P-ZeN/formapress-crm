<?php
/**
 * Register crm_company_role Taxonomy for zqpm_entreprise CPT.
 *
 * @package FormapressCRM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register crm_company_role Taxonomy.
 * This taxonomy will be used to categorize companies (zqpm_entreprise)
 * such as Client, Prospect, Partner, etc.
 */
function formapress_crm_register_company_role_taxonomy() {
	$labels = array(
		'name'              => _x( 'Company Roles', 'taxonomy general name', 'formapress-crm' ),
		'singular_name'     => _x( 'Company Role', 'taxonomy singular name', 'formapress-crm' ),
		'search_items'      => __( 'Search Company Roles', 'formapress-crm' ),
		'all_items'         => __( 'All Company Roles', 'formapress-crm' ),
		'parent_item'       => __( 'Parent Company Role', 'formapress-crm' ),
		'parent_item_colon' => __( 'Parent Company Role:', 'formapress-crm' ),
		'edit_item'         => __( 'Edit Company Role', 'formapress-crm' ),
		'update_item'       => __( 'Update Company Role', 'formapress-crm' ),
		'add_new_item'      => __( 'Add New Company Role', 'formapress-crm' ),
		'new_item_name'     => __( 'New Company Role Name', 'formapress-crm' ),
		'menu_name'         => __( 'Company Roles', 'formapress-crm' ),
	);

	$args = array(
		'hierarchical'      => true, // True for category-like taxonomy.
		'labels'            => $labels,
		'show_ui'           => true,
		'show_admin_column' => true, // Display in the zqpm_entreprise admin list table.
		'query_var'         => true,
		'rewrite'           => array( 'slug' => 'crm-company-role' ),
		'show_in_rest'      => true, // Available in REST API.
	);

	// Associate with the existing 'zqpm_entreprise' CPT.
	register_taxonomy( 'crm_company_role', array( 'zqpm_entreprise' ), $args );
}
add_action( 'init', 'formapress_crm_register_company_role_taxonomy', 0 );
