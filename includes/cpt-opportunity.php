<?php
/**
 * Register crm_opportunity Custom Post Type.
 *
 * @package FormapressCRM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register crm_opportunity CPT.
 */
function formapress_crm_register_opportunity_cpt() {
	$labels = array(
		'name'                  => _x( 'Opportunities', 'Post type general name', 'formapress-crm' ),
		'singular_name'         => _x( 'Opportunity', 'Post type singular name', 'formapress-crm' ),
		'menu_name'             => _x( 'Opportunities', 'Admin Menu text', 'formapress-crm' ),
		'name_admin_bar'        => _x( 'Opportunity', 'Add New on Toolbar', 'formapress-crm' ),
		'add_new'               => __( 'Add New', 'formapress-crm' ),
		'add_new_item'          => __( 'Add New Opportunity', 'formapress-crm' ),
		'new_item'              => __( 'New Opportunity', 'formapress-crm' ),
		'edit_item'             => __( 'Edit Opportunity', 'formapress-crm' ),
		'view_item'             => __( 'View Opportunity', 'formapress-crm' ),
		'all_items'             => __( 'All Opportunities', 'formapress-crm' ),
		'search_items'          => __( 'Search Opportunities', 'formapress-crm' ),
		'parent_item_colon'     => __( 'Parent Opportunities:', 'formapress-crm' ),
		'not_found'             => __( 'No opportunities found.', 'formapress-crm' ),
		'not_found_in_trash'    => __( 'No opportunities found in Trash.', 'formapress-crm' ),
		'featured_image'        => _x( 'Opportunity Image', 'Overrides the \"Featured Image\" phrase for this post type.', 'formapress-crm' ),
		'set_featured_image'    => _x( 'Set opportunity image', 'Overrides the \"Set featured image\" phrase for this post type.', 'formapress-crm' ),
		'remove_featured_image' => _x( 'Remove opportunity image', 'Overrides the \"Remove featured image\" phrase for this post type.', 'formapress-crm' ),
		'use_featured_image'    => _x( 'Use as opportunity image', 'Overrides the \"Use as featured image\" phrase for this post type.', 'formapress-crm' ),
		'archives'              => _x( 'Opportunity archives', 'The post type archive label used in nav menus.', 'formapress-crm' ),
		'insert_into_item'      => _x( 'Insert into opportunity', 'Overrides the \"Insert into post\"/\"Insert into page\" phrase (used when inserting media into a post).', 'formapress-crm' ),
		'uploaded_to_this_item' => _x( 'Uploaded to this opportunity', 'Overrides the \"Uploaded to this post\"/\"Uploaded to this page\" phrase (used when viewing media attached to a post).', 'formapress-crm' ),
		'filter_items_list'     => _x( 'Filter opportunities list', 'Screen reader text for the filter links heading on the post type listing screen.', 'formapress-crm' ),
		'items_list_navigation' => _x( 'Opportunities list navigation', 'Screen reader text for the pagination heading on the post type listing screen.', 'formapress-crm' ),
		'items_list'            => _x( 'Opportunities list', 'Screen reader text for the items list heading on the post type listing screen.', 'formapress-crm' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => 'formapress-crm-dashboard', // Show under the main CRM menu.
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'crm-opportunity' ),
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => null,
		'supports'           => array( 'title', 'editor', 'custom-fields', 'thumbnail' ),
		'show_in_rest'       => true, // Enable Gutenberg editor and REST API access.
		'menu_icon'          => 'dashicons-chart-line',
	);

	register_post_type( 'crm_opportunity', $args );
}
add_action( 'init', 'formapress_crm_register_opportunity_cpt' );

/**
 * Adds meta boxes for the crm_opportunity CPT.
 */
function formapress_crm_add_opportunity_meta_boxes() {
	add_meta_box(
		'formapress_crm_opportunity_details_meta_box',
		__( 'Opportunity Details', 'formapress-crm' ),
		'formapress_crm_opportunity_details_meta_box_html',
		'crm_opportunity',
		'normal',
		'high'
	);
	add_meta_box(
		'formapress_crm_opportunity_associations_meta_box',
		__( 'Associated Entities', 'formapress-crm' ),
		'formapress_crm_opportunity_associations_meta_box_html',
		'crm_opportunity',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_crm_opportunity', 'formapress_crm_add_opportunity_meta_boxes' );

/**
 * Renders the HTML for the Opportunity Details meta box.
 *
 * @param WP_Post $post The current post object.
 */
function formapress_crm_opportunity_details_meta_box_html( $post ) {
	wp_nonce_field( 'formapress_crm_save_opportunity_meta_data', 'formapress_crm_opportunity_meta_nonce' );

	$status     = get_post_meta( $post->ID, '_crm_opportunity_status', true );
	$value      = get_post_meta( $post->ID, '_crm_opportunity_value', true );
	$close_date = get_post_meta( $post->ID, '_crm_opportunity_close_date', true );
	// Add more fields like probability, stage, etc. as needed.

	?>
	<p>
		<label for="crm_opportunity_status"><?php esc_html_e( 'Status', 'formapress-crm' ); ?>:</label><br />
		<input type="text" id="crm_opportunity_status" name="crm_opportunity_status" value="<?php echo esc_attr( $status ); ?>" class="widefat" />
		<small><?php esc_html_e( 'E.g., Prospecting, Qualification, Proposal, Negotiation, Closed Won, Closed Lost', 'formapress-crm' ); ?></small>
	</p>
	<p>
		<label for="crm_opportunity_value"><?php esc_html_e( 'Estimated Value', 'formapress-crm' ); ?>:</label><br />
		<input type="number" step="0.01" id="crm_opportunity_value" name="crm_opportunity_value" value="<?php echo esc_attr( $value ); ?>" class="widefat" />
	</p>
	<p>
		<label for="crm_opportunity_close_date"><?php esc_html_e( 'Expected Close Date', 'formapress-crm' ); ?>:</label><br />
		<input type="date" id="crm_opportunity_close_date" name="crm_opportunity_close_date" value="<?php echo esc_attr( $close_date ); ?>" class="widefat" />
	</p>
	<?php
}

/**
 * Renders the HTML for the Associated Entities meta box.
 *
 * @param WP_Post $post The current post object.
 */
function formapress_crm_opportunity_associations_meta_box_html( $post ) {
	// Nonce is in the details meta box.
	$person_id  = get_post_meta( $post->ID, '_crm_associated_person_id', true );
	$company_id = get_post_meta( $post->ID, '_crm_associated_company_id', true );

	?>
	<p>
		<label for="crm_associated_person_id"><?php esc_html_e( 'Associated Person (crm_person ID)', 'formapress-crm' ); ?>:</label><br />
		<input type="number" id="crm_associated_person_id" name="crm_associated_person_id" value="<?php echo esc_attr( $person_id ); ?>" class="widefat" />
		<small><?php esc_html_e( 'Enter the Post ID of the crm_person.', 'formapress-crm' ); ?></small>
	</p>
	<p>
		<label for="crm_associated_company_id"><?php esc_html_e( 'Associated Company (zqpm_entreprise ID)', 'formapress-crm' ); ?>:</label><br />
		<input type="number" id="crm_associated_company_id" name="crm_associated_company_id" value="<?php echo esc_attr( $company_id ); ?>" class="widefat" />
		<small><?php esc_html_e( 'Enter the Post ID of the zqpm_entreprise.', 'formapress-crm' ); ?></small>
	</p>
	<?php
	// Future enhancement: Use select dropdowns populated with existing persons/companies.
}

/**
 * Saves the meta data for the crm_opportunity CPT.
 *
 * @param int $post_id The ID of the post being saved.
 */
function formapress_crm_save_opportunity_meta_data( $post_id ) {
	if ( ! isset( $_POST['formapress_crm_opportunity_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['formapress_crm_opportunity_meta_nonce'] ) ), 'formapress_crm_save_opportunity_meta_data' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Sanitize and save Opportunity Details.
	$fields_to_save = array(
		'crm_opportunity_status'     => '_crm_opportunity_status',
		'crm_opportunity_value'      => '_crm_opportunity_value',
		'crm_opportunity_close_date' => '_crm_opportunity_close_date',
		'crm_associated_person_id'   => '_crm_associated_person_id',
		'crm_associated_company_id'  => '_crm_associated_company_id',
	);

	foreach ( $fields_to_save as $post_key => $meta_key ) {
		if ( isset( $_POST[ $post_key ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
			if ( in_array( $meta_key, array( '_crm_opportunity_value', '_crm_associated_person_id', '_crm_associated_company_id' ), true ) ) {
				// For numeric values, ensure they are properly formatted or cast.
				// Value can be float, IDs are int.
				if ( '_crm_opportunity_value' === $meta_key ) {
					$value = floatval( $value );
				} else {
					$value = intval( $value );
				}
			}
			update_post_meta( $post_id, $meta_key, $value );
		} else {
			// Delete meta if field is not set (e.g. for checkboxes if any were added).
			// For text/number fields, empty string or 0 will be saved if submitted empty.
			// delete_post_meta( $post_id, $meta_key );
		}
	}
}
add_action( 'save_post_crm_opportunity', 'formapress_crm_save_opportunity_meta_data' );
