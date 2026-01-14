<?php
/**
 * Register crm_activity Custom Post Type.
 *
 * @package FormapressCRM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register crm_activity CPT.
 */
function formapress_crm_register_activity_cpt() {
	$labels = array(
		'name'                  => _x( 'Activities', 'Post type general name', 'formapress-crm' ),
		'singular_name'         => _x( 'Activity', 'Post type singular name', 'formapress-crm' ),
		'menu_name'             => _x( 'Activities', 'Admin Menu text', 'formapress-crm' ),
		'name_admin_bar'        => _x( 'Activity', 'Add New on Toolbar', 'formapress-crm' ),
		'add_new'               => __( 'Add New', 'formapress-crm' ),
		'add_new_item'          => __( 'Add New Activity', 'formapress-crm' ),
		'new_item'              => __( 'New Activity', 'formapress-crm' ),
		'edit_item'             => __( 'Edit Activity', 'formapress-crm' ),
		'view_item'             => __( 'View Activity', 'formapress-crm' ),
		'all_items'             => __( 'All Activities', 'formapress-crm' ),
		'search_items'          => __( 'Search Activities', 'formapress-crm' ),
		'parent_item_colon'     => __( 'Parent Activities:', 'formapress-crm' ),
		'not_found'             => __( 'No activities found.', 'formapress-crm' ),
		'not_found_in_trash'    => __( 'No activities found in Trash.', 'formapress-crm' ),
		'featured_image'        => _x( 'Activity Image', 'Overrides the \"Featured Image\" phrase for this post type.', 'formapress-crm' ),
		'set_featured_image'    => _x( 'Set activity image', 'Overrides the \"Set featured image\" phrase for this post type.', 'formapress-crm' ),
		'remove_featured_image' => _x( 'Remove activity image', 'Overrides the \"Remove featured image\" phrase for this post type.', 'formapress-crm' ),
		'use_featured_image'    => _x( 'Use as activity image', 'Overrides the \"Use as featured image\" phrase for this post type.', 'formapress-crm' ),
		'archives'              => _x( 'Activity archives', 'The post type archive label used in nav menus.', 'formapress-crm' ),
		'insert_into_item'      => _x( 'Insert into activity', 'Overrides the \"Insert into post\"/\"Insert into page\" phrase (used when inserting media into a post).', 'formapress-crm' ),
		'uploaded_to_this_item' => _x( 'Uploaded to this activity', 'Overrides the \"Uploaded to this post\"/\"Uploaded to this page\" phrase (used when viewing media attached to a post).', 'formapress-crm' ),
		'filter_items_list'     => _x( 'Filter activities list', 'Screen reader text for the filter links heading on the post type listing screen.', 'formapress-crm' ),
		'items_list_navigation' => _x( 'Activities list navigation', 'Screen reader text for the pagination heading on the post type listing screen.', 'formapress-crm' ),
		'items_list'            => _x( 'Activities list', 'Screen reader text for the items list heading on the post type listing screen.', 'formapress-crm' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => 'formapress-crm-dashboard', // Show under the main CRM menu.
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'crm-activity' ),
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => null,
		'supports'           => array( 'title', 'editor', 'custom-fields' ), // No thumbnail for activities usually.
		'show_in_rest'       => true, // Enable Gutenberg editor and REST API access.
		'menu_icon'          => 'dashicons-list-view',
	);

	register_post_type( 'crm_activity', $args );
}
add_action( 'init', 'formapress_crm_register_activity_cpt' );

/**
 * Adds meta boxes for the crm_activity CPT.
 */
function formapress_crm_add_activity_meta_boxes() {
	add_meta_box(
		'formapress_crm_activity_details_meta_box',
		__( 'Activity Details', 'formapress-crm' ),
		'formapress_crm_activity_details_meta_box_html',
		'crm_activity',
		'normal',
		'high'
	);
	add_meta_box(
		'formapress_crm_activity_associations_meta_box',
		__( 'Associated Entities', 'formapress-crm' ),
		'formapress_crm_activity_associations_meta_box_html',
		'crm_activity',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_crm_activity', 'formapress_crm_add_activity_meta_boxes' );

/**
 * Renders the HTML for the Activity Details meta box.
 *
 * @param WP_Post $post The current post object.
 */
function formapress_crm_activity_details_meta_box_html( $post ) {
	wp_nonce_field( 'formapress_crm_save_activity_meta_data', 'formapress_crm_activity_meta_nonce' );

	$type     = get_post_meta( $post->ID, '_crm_activity_type', true );
	$due_date = get_post_meta( $post->ID, '_crm_activity_due_date', true );
	$status   = get_post_meta( $post->ID, '_crm_activity_status', true );

	?>
	<p>
		<label for="crm_activity_type"><?php esc_html_e( 'Activity Type', 'formapress-crm' ); ?>:</label><br />
		<input type="text" id="crm_activity_type" name="crm_activity_type" value="<?php echo esc_attr( $type ); ?>" class="widefat" />
		<small><?php esc_html_e( 'E.g., Call, Email, Meeting, Task', 'formapress-crm' ); ?></small>
	</p>
	<p>
		<label for="crm_activity_due_date"><?php esc_html_e( 'Due Date', 'formapress-crm' ); ?>:</label><br />
		<input type="date" id="crm_activity_due_date" name="crm_activity_due_date" value="<?php echo esc_attr( $due_date ); ?>" class="widefat" />
	</p>
	<p>
		<label for="crm_activity_status"><?php esc_html_e( 'Status', 'formapress-crm' ); ?>:</label><br />
		<input type="text" id="crm_activity_status" name="crm_activity_status" value="<?php echo esc_attr( $status ); ?>" class="widefat" />
		<small><?php esc_html_e( 'E.g., Open, Completed, Pending', 'formapress-crm' ); ?></small>
	</p>
	<?php
}

/**
 * Renders the HTML for the Associated Entities meta box.
 *
 * @param WP_Post $post The current post object.
 */
function formapress_crm_activity_associations_meta_box_html( $post ) {
	// Nonce is in the details meta box.
	$person_id      = get_post_meta( $post->ID, '_crm_activity_associated_person_id', true );
	$company_id     = get_post_meta( $post->ID, '_crm_activity_associated_company_id', true );
	$opportunity_id = get_post_meta( $post->ID, '_crm_activity_associated_opportunity_id', true );

	// Fetch crm_person posts for dropdown.
	$persons = get_posts(
		array(
			'post_type'      => 'crm_person',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	// Fetch zqpm_entreprise posts for dropdown.
	$companies = get_posts(
		array(
			'post_type'      => 'zqpm_entreprise',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	// Fetch crm_opportunity posts for dropdown.
	$opportunities = get_posts(
		array(
			'post_type'      => 'crm_opportunity',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	?>
	<p>
		<label for="crm_activity_associated_person_id"><?php esc_html_e( 'Associated Person', 'formapress-crm' ); ?>:</label><br />
		<select id="crm_activity_associated_person_id" name="crm_activity_associated_person_id" class="widefat">
			<option value=""><?php esc_html_e( '-- Select Person --', 'formapress-crm' ); ?></option>
			<?php foreach ( $persons as $person ) : ?>
				<option value="<?php echo esc_attr( $person->ID ); ?>" <?php selected( $person_id, $person->ID ); ?>>
					<?php echo esc_html( $person->post_title ); ?> (ID: <?php echo esc_html( $person->ID ); ?>)
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="crm_activity_associated_company_id"><?php esc_html_e( 'Associated Company', 'formapress-crm' ); ?>:</label><br />
		<select id="crm_activity_associated_company_id" name="crm_activity_associated_company_id" class="widefat">
			<option value=""><?php esc_html_e( '-- Select Company --', 'formapress-crm' ); ?></option>
			<?php foreach ( $companies as $company ) : ?>
				<option value="<?php echo esc_attr( $company->ID ); ?>" <?php selected( $company_id, $company->ID ); ?>>
					<?php echo esc_html( $company->post_title ); ?> (ID: <?php echo esc_html( $company->ID ); ?>)
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="crm_activity_associated_opportunity_id"><?php esc_html_e( 'Associated Opportunity', 'formapress-crm' ); ?>:</label><br />
		<select id="crm_activity_associated_opportunity_id" name="crm_activity_associated_opportunity_id" class="widefat">
			<option value=""><?php esc_html_e( '-- Select Opportunity --', 'formapress-crm' ); ?></option>
			<?php foreach ( $opportunities as $opportunity ) : ?>
				<option value="<?php echo esc_attr( $opportunity->ID ); ?>" <?php selected( $opportunity_id, $opportunity->ID ); ?>>
					<?php echo esc_html( $opportunity->post_title ); ?> (ID: <?php echo esc_html( $opportunity->ID ); ?>)
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<?php
}

/**
 * Saves the meta data for the crm_activity CPT.
 *
 * @param int $post_id The ID of the post being saved.
 */
function formapress_crm_save_activity_meta_data( $post_id ) {
	if ( ! isset( $_POST['formapress_crm_activity_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['formapress_crm_activity_meta_nonce'] ) ), 'formapress_crm_save_activity_meta_data' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Sanitize and save Activity Details.
	$fields_to_save = array(
		'crm_activity_type'                      => '_crm_activity_type',
		'crm_activity_due_date'                  => '_crm_activity_due_date',
		'crm_activity_status'                    => '_crm_activity_status',
		'crm_activity_associated_person_id'      => '_crm_activity_associated_person_id',
		'crm_activity_associated_company_id'     => '_crm_activity_associated_company_id',
		'crm_activity_associated_opportunity_id' => '_crm_activity_associated_opportunity_id',
	);

	foreach ( $fields_to_save as $post_key => $meta_key ) {
		if ( isset( $_POST[ $post_key ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
			// For numeric IDs, ensure they are integers.
			if ( in_array( $meta_key, array( '_crm_activity_associated_person_id', '_crm_activity_associated_company_id', '_crm_activity_associated_opportunity_id' ), true ) ) {
				$value = intval( $value );
			}
			update_post_meta( $post_id, $meta_key, $value );
		} else {
			// Delete meta if field is not set (e.g. for checkboxes if any were added).
			// For text/number fields, empty string or 0 will be saved if submitted empty.
			// delete_post_meta( $post_id, $meta_key );
		}
	}
}
add_action( 'save_post_crm_activity', 'formapress_crm_save_activity_meta_data' );
