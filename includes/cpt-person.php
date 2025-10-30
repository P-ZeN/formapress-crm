<?php
/**
 * Register crm_person Custom Post Type and crm_person_type Taxonomy
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register crm_person_type Taxonomy for crm_person.
 * This will categorize persons (e.g., Trainee, Instructor, Company Contact, Primary Referent).
 */
function formapress_crm_register_person_type_taxonomy() {
	$labels = array(
		'name'              => _x( 'Person Types', 'taxonomy general name', 'formapress-crm' ),
		'singular_name'     => _x( 'Person Type', 'taxonomy singular name', 'formapress-crm' ),
		'search_items'      => __( 'Search Person Types', 'formapress-crm' ),
		'all_items'         => __( 'All Person Types', 'formapress-crm' ),
		'parent_item'       => __( 'Parent Person Type', 'formapress-crm' ),
		'parent_item_colon' => __( 'Parent Person Type:', 'formapress-crm' ),
		'edit_item'         => __( 'Edit Person Type', 'formapress-crm' ),
		'update_item'       => __( 'Update Person Type', 'formapress-crm' ),
		'add_new_item'      => __( 'Add New Person Type', 'formapress-crm' ),
		'new_item_name'     => __( 'New Person Type Name', 'formapress-crm' ),
		'menu_name'         => __( 'Person Types', 'formapress-crm' ),
	);

	$args = array(
		'hierarchical'      => true,
		'labels'            => $labels,
		'show_ui'           => true,
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => array( 'slug' => 'crm-person-type' ),
		'show_in_rest'      => true,
	);

	register_taxonomy( 'crm_person_type', array( 'crm_person' ), $args );
}
add_action( 'init', 'formapress_crm_register_person_type_taxonomy', 0 );


/**
 * Register crm_person Custom Post Type.
 */
function formapress_crm_register_person_cpt() {

	$labels = array(
		'name'                  => _x( 'Persons', 'Post type general name', 'formapress-crm' ),
		'singular_name'         => _x( 'Person', 'Post type singular name', 'formapress-crm' ),
		'menu_name'             => _x( 'CRM Persons', 'Admin Menu text', 'formapress-crm' ),
		'name_admin_bar'        => _x( 'Person', 'Add New on Toolbar', 'formapress-crm' ),
		'add_new'               => __( 'Add New', 'formapress-crm' ),
		'add_new_item'          => __( 'Add New Person', 'formapress-crm' ),
		'new_item'              => __( 'New Person', 'formapress-crm' ),
		'edit_item'             => __( 'Edit Person', 'formapress-crm' ),
		'view_item'             => __( 'View Person', 'formapress-crm' ),
		'all_items'             => __( 'All Persons', 'formapress-crm' ),
		'search_items'          => __( 'Search Persons', 'formapress-crm' ),
		'parent_item_colon'     => __( 'Parent Persons:', 'formapress-crm' ),
		'not_found'             => __( 'No persons found.', 'formapress-crm' ),
		'not_found_in_trash'    => __( 'No persons found in Trash.', 'formapress-crm' ),
		'featured_image'        => _x( 'Person Photo', 'Overrides the “Featured Image” phrase for this post type.', 'formapress-crm' ),
		'set_featured_image'    => _x( 'Set person photo', 'Overrides the “Set featured image” phrase for this post type.', 'formapress-crm' ),
		'remove_featured_image' => _x( 'Remove person photo', 'Overrides the “Remove featured image” phrase for this post type.', 'formapress-crm' ),
		'use_featured_image'    => _x( 'Use as person photo', 'Overrides the “Use as featured image” phrase for this post type.', 'formapress-crm' ),
		'archives'              => _x( 'Person archives', 'The post type archive label used in nav menus.', 'formapress-crm' ),
		'insert_into_item'      => _x( 'Insert into person', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post).', 'formapress-crm' ),
		'uploaded_to_this_item' => _x( 'Uploaded to this person', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post).', 'formapress-crm' ),
		'filter_items_list'     => _x( 'Filter persons list', 'Screen reader text for the filter links heading on the post type listing screen.', 'formapress-crm' ),
		'items_list_navigation' => _x( 'Persons list navigation', 'Screen reader text for the pagination heading on the post type listing screen.', 'formapress-crm' ),
		'items_list'            => _x( 'Persons list', 'Screen reader text for the items list heading on the post type listing screen.', 'formapress-crm' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true, // You might want to make this a submenu of a main CRM menu later
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'crm-person' ),
		'capability_type'    => 'post',
		'has_archive'        => 'crm-persons',
		'hierarchical'       => false,
		'menu_position'      => null,
		'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ), // 'title' for name, 'editor' for notes, 'thumbnail' for photo
		'show_in_rest'       => true, // Enable Gutenberg editor and REST API support
		'taxonomies'         => array( 'crm_person_type' ), // Assign the custom taxonomy
	);

	register_post_type( 'crm_person', $args );
}
add_action( 'init', 'formapress_crm_register_person_cpt' );

/**
 * Adds meta boxes for the crm_person CPT.
 */
function formapress_crm_add_person_meta_boxes() {
	add_meta_box(
		'formapress_crm_person_details_meta_box',
		__( 'Person Details', 'formapress-crm' ),
		'formapress_crm_person_details_meta_box_html',
		'crm_person',
		'normal',
		'high'
	);
	add_meta_box(
		'formapress_crm_person_company_associations_meta_box',
		__( 'Company Associations', 'formapress-crm' ),
		'formapress_crm_person_company_associations_meta_box_html',
		'crm_person',
		'side',
		'default'
	);
	add_meta_box(
		'formapress_crm_person_registrations_meta_box',
		__( 'Associated Registrations', 'formapress-crm' ),
		'formapress_crm_person_registrations_meta_box_html',
		'crm_person',
		'side', // Changed from 'normal' to 'side' for better layout.
		'default'
	);
}
add_action( 'add_meta_boxes_crm_person', 'formapress_crm_add_person_meta_boxes' );

/**
 * Renders the HTML for the Person Details meta box.
 *
 * @param WP_Post $post The current post object.
 */
function formapress_crm_person_details_meta_box_html( $post ) {
	wp_nonce_field( 'formapress_crm_save_person_meta_data', 'formapress_crm_person_meta_nonce' );

	$civility    = get_post_meta( $post->ID, '_crm_civility', true );
	$email       = get_post_meta( $post->ID, '_crm_email', true );
	$phone       = get_post_meta( $post->ID, '_crm_phone', true );
	$job_title   = get_post_meta( $post->ID, '_crm_job_title', true );
	$user_id     = get_post_meta( $post->ID, '_crm_user_id', true );
	$lead_source = get_post_meta( $post->ID, '_crm_lead_source', true );

	?>
	<p>
		<label for="crm_civility"><?php esc_html_e( 'Civility (e.g., Mr., Ms., Dr.)', 'formapress-crm' ); ?>:</label><br />
		<input type="text" id="crm_civility" name="crm_civility" value="<?php echo esc_attr( $civility ); ?>" class="widefat" />
	</p>
	<p>
		<label for="crm_email"><?php esc_html_e( 'Email', 'formapress-crm' ); ?>:</label><br />
		<input type="email" id="crm_email" name="crm_email" value="<?php echo esc_attr( $email ); ?>" class="widefat" />
	</p>
	<p>
		<label for="crm_phone"><?php esc_html_e( 'Phone', 'formapress-crm' ); ?>:</label><br />
		<input type="text" id="crm_phone" name="crm_phone" value="<?php echo esc_attr( $phone ); ?>" class="widefat" />
	</p>
	<p>
		<label for="crm_job_title"><?php esc_html_e( 'Job Title', 'formapress-crm' ); ?>:</label><br />
		<input type="text" id="crm_job_title" name="crm_job_title" value="<?php echo esc_attr( $job_title ); ?>" class="widefat" />
	</p>
	<p>
		<label for="crm_user_id"><?php esc_html_e( 'WordPress User ID (if applicable)', 'formapress-crm' ); ?>:</label><br />
		<input type="number" id="crm_user_id" name="crm_user_id" value="<?php echo esc_attr( $user_id ); ?>" class="widefat" />
	</p>
	<p>
		<label for="crm_lead_source"><?php esc_html_e( 'Lead Source', 'formapress-crm' ); ?>:</label><br />
		<input type="text" id="crm_lead_source" name="crm_lead_source" value="<?php echo esc_attr( $lead_source ); ?>" class="widefat" />
	</p>
	<?php
}

/**
 * Renders the HTML for the Company Associations meta box.
 *
 * @param WP_Post $post The current post object.
 */
function formapress_crm_person_company_associations_meta_box_html( $post ) {
	// Nonce is already in the details meta box, or add one if this can be saved independently.
	// For simplicity, we assume one save action for all meta boxes of this CPT.
	$entreprise_ids_string   = get_post_meta( $post->ID, '_crm_entreprise_ids', true );
	$selected_entreprise_ids = ! empty( $entreprise_ids_string ) ? explode( ',', $entreprise_ids_string ) : array();
	// Ensure all elements in $selected_entreprise_ids are integers for comparison.
	$selected_entreprise_ids = array_map( 'intval', $selected_entreprise_ids );

	$all_entreprises = get_posts(
		array(
			'post_type'      => 'zqpm_entreprise',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	?>
	<p>
		<label for="crm_entreprise_ids"><?php esc_html_e( 'Associated Companies', 'formapress-crm' ); ?>:</label><br />
		<select id="crm_entreprise_ids" name="crm_entreprise_ids[]" multiple="multiple" class="widefat" style="min-height: 150px;">
			<?php if ( ! empty( $all_entreprises ) ) : ?>
				<?php foreach ( $all_entreprises as $entreprise ) : ?>
					<option value="<?php echo esc_attr( $entreprise->ID ); ?>" <?php selected( in_array( $entreprise->ID, $selected_entreprise_ids, true ) ); ?>>
						<?php echo esc_html( $entreprise->post_title ); ?> (ID: <?php echo esc_html( $entreprise->ID ); ?>)
					</option>
				<?php endforeach; ?>
			<?php else : ?>
				<option value="" disabled><?php esc_html_e( 'No companies found.', 'formapress-crm' ); ?></option>
			<?php endif; ?>
		</select>
		<small><?php esc_html_e( 'Hold down Ctrl (Windows/Linux) or Command (Mac) to select multiple companies.', 'formapress-crm' ); ?></small>
	</p>
	<?php
}

/**
 * Renders the HTML for the Associated Registrations meta box.
 * This is a read-only meta box for now.
 *
 * @param WP_Post $post The current post object.
 */
function formapress_crm_person_registrations_meta_box_html( $post ) {
	// No nonce needed for read-only display.
	$registration_ids = get_post_meta( $post->ID, '_associated_registration_ids', true );

	if ( ! empty( $registration_ids ) && is_array( $registration_ids ) ) {
		echo '<ul>';
		foreach ( $registration_ids as $reg_id ) {
			$reg_id             = intval( $reg_id );
			$registration_title = get_the_title( $reg_id );
			$registration_link  = get_edit_post_link( $reg_id );
			if ( $registration_link ) {
				echo '<li><a href="' . esc_url( $registration_link ) . '">' . esc_html( $registration_title ) . ' (ID: ' . esc_html( $reg_id ) . ')</a></li>';
			} else {
				echo '<li>' . esc_html( $registration_title ) . ' (ID: ' . esc_html( $reg_id ) . ') - Registration not found or no edit link.</li>';
			}
		}
		echo '</ul>';
	} else {
		echo '<p>' . esc_html__( 'No registrations associated with this person yet.', 'formapress-crm' ) . '</p>';
	}
	echo '<p><small>' . esc_html__( 'Registrations are linked automatically when a zform_registration is saved for the user associated with this person.', 'formapress-crm' ) . '</small></p>';
}


/**
 * Saves the meta data for the crm_person CPT.
 *
 * @param int $post_id The ID of the post being saved.
 */
function formapress_crm_save_person_meta_data( $post_id ) {
	// Check if nonce is set.
	if ( ! isset( $_POST['formapress_crm_person_meta_nonce'] ) ) {
		return;
	}
	// Verify that the nonce is valid.
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['formapress_crm_person_meta_nonce'] ) ), 'formapress_crm_save_person_meta_data' ) ) {
		return;
	}
	// If this is an autosave, our form has not been submitted, so we don't want to do anything.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	// Check the user's permissions.
	if ( isset( $_POST['post_type'] ) && 'crm_person' === $_POST['post_type'] ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
	}

	// Sanitize and save Person Details.
	$fields_to_save = array(
		'crm_civility'    => '_crm_civility',
		'crm_email'       => '_crm_email',
		'crm_phone'       => '_crm_phone',
		'crm_job_title'   => '_crm_job_title',
		'crm_user_id'     => '_crm_user_id',
		'crm_lead_source' => '_crm_lead_source',
		// 'crm_entreprise_ids' will be handled separately due to array to string conversion.
	);

	foreach ( $fields_to_save as $post_key => $meta_key ) {
		if ( isset( $_POST[ $post_key ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
			if ( '_crm_user_id' === $meta_key ) {
				$value = intval( $value ); // Ensure user ID is an integer.
			}
			if ( '_crm_email' === $meta_key ) {
				$value = sanitize_email( wp_unslash( $_POST[ $post_key ] ) );
			}
			update_post_meta( $post_id, $meta_key, $value );
		} else {
			// If the field is not set (e.g., checkbox unchecked), delete the meta.
			// For text fields, an empty string will be saved if submitted empty,
			// so explicit deletion might not be needed unless you want to remove the key.
			// delete_post_meta( $post_id, $meta_key );
		}
	}

	// Handle crm_entreprise_ids (multiple select).
	if ( isset( $_POST['crm_entreprise_ids'] ) && is_array( $_POST['crm_entreprise_ids'] ) ) {
		$sanitized_entreprise_ids = array_map( 'intval', $_POST['crm_entreprise_ids'] );
		// Filter out 0s that might result from non-numeric values if any sneak through, though intval helps.
		$sanitized_entreprise_ids = array_filter( $sanitized_entreprise_ids );
		$entreprise_ids_string    = implode( ',', $sanitized_entreprise_ids );
		update_post_meta( $post_id, '_crm_entreprise_ids', $entreprise_ids_string );
	} else {
		// If no companies were selected or the field wasn't submitted, save an empty string or delete.
		// Saving an empty string is consistent with how it might have been if a text field was cleared.
		update_post_meta( $post_id, '_crm_entreprise_ids', '' );
	}
}
add_action( 'save_post_crm_person', 'formapress_crm_save_person_meta_data' );

// We will add meta boxes here for fields like:
// - Civility (Mr., Ms., etc.)
// - First Name (if not using title for full name)
// - Last Name (if not using title for full name)
// - Email
// - Phone
// - Job Title
// - Link to WP_User ID (user_id)
// - Link to zqpm_entreprise (company_id or an array of company_ids)
// - Lead Source
// - Communication Preferences
// - _associated_registrations (for trainees)
