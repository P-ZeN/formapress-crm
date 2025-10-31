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
		'supports'           => array( 'title', 'thumbnail' ), // 'title' auto-filled from first+last name, 'thumbnail' for photo. NO 'editor' to avoid Gutenberg conflicts with metaboxes.
		'show_in_rest'       => false, // Disable Gutenberg to use classic metaboxes properly.
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
 * Dynamically generates fields based on zform_registrations configuration.
 *
 * @param WP_Post $post The current post object.
 */
function formapress_crm_person_details_meta_box_html( $post ) {
	wp_nonce_field( 'formapress_crm_save_person_meta_data', 'formapress_crm_person_meta_nonce' );

	// Get dynamic registration fields configuration.
	$zform_registrations = get_option( 'zform_registrations', array() );

	if ( empty( $zform_registrations ) ) {
		echo '<p>' . esc_html__( 'No registration fields configured. Please configure fields in zFormations settings.', 'formapress-crm' ) . '</p>';
		return;
	}

	// Field name mapping: zform field name => CRM meta key prefix.
	$field_mapping = array(
		'civilite'  => 'civilite',
		'name'      => 'name',
		'firstname' => 'firstname',
		'mail'      => 'email',
		'telephone' => 'phone',
		'societe'   => 'company',
		'adresse'   => 'address',
		'cp'        => 'postal_code',
		'ville'     => 'city',
		'message'   => 'message',
	);

	?>
	<div class="crm-person-form">
		<table class="form-table">
			<?php
			// Sort fields by order.
			uasort(
				$zform_registrations,
				function ( $a, $b ) {
					$order_a = isset( $a['order'] ) ? intval( $a['order'] ) : 999;
					$order_b = isset( $b['order'] ) ? intval( $b['order'] ) : 999;
					return $order_a - $order_b;
				}
			);

			foreach ( $zform_registrations as $field_key => $field_config ) {
				// Skip helptext fields (they're informational only).
				if ( isset( $field_config['type'] ) && 'helptext' === $field_config['type'] ) {
					continue;
				}

				// Skip RGPD validation checkbox (not needed in CRM).
				if ( in_array( $field_key, array( 'validation-rgpd', 'texte-rgpd' ), true ) ) {
					continue;
				}

				// Get field configuration.
				$field_name     = isset( $field_config['name'] ) ? $field_config['name'] : ucfirst( $field_key );
				$field_type     = isset( $field_config['type'] ) ? $field_config['type'] : 'text';
				$field_options  = isset( $field_config['options'] ) ? $field_config['options'] : '';
				$field_required = isset( $field_config['required'] ) && $field_config['required'];

				// Map to CRM meta key (use mapped name if available, otherwise use field_key).
				$crm_meta_key = isset( $field_mapping[ $field_key ] ) ? $field_mapping[ $field_key ] : $field_key;
				$meta_key     = '_crm_' . $crm_meta_key;

				// Get current value.
				$current_value = get_post_meta( $post->ID, $meta_key, true );

				// Render field.
				echo '<tr>';
				echo '<th><label for="crm_' . esc_attr( $crm_meta_key ) . '">' . esc_html( $field_name ) . '</label></th>';
				echo '<td>';

				formapress_crm_render_field( $crm_meta_key, $field_type, $current_value, $field_options, $field_required );

				echo '</td>';
				echo '</tr>';
			}
			?>
		</table>
	</div>
	<?php
}

/**
 * Renders a form field based on type.
 *
 * @param string $field_key Field key for name/id.
 * @param string $field_type Field type (text, email, tel, textarea, radio, select, checkbox, etc.).
 * @param mixed  $current_value Current field value.
 * @param string $field_options Options for select/radio (pipe-separated: "value|label").
 * @param bool   $required Whether field is required.
 */
function formapress_crm_render_field( $field_key, $field_type, $current_value, $field_options = '', $required = false ) {
	$field_id       = 'crm_' . $field_key;
	$field_name     = 'crm_' . $field_key;
	$required_attr  = $required ? ' required' : '';
	$required_label = $required ? ' <strong style="color: #d63638;">*</strong>' : '';

	switch ( $field_type ) {
		case 'radio':
			// Parse options: "value|label\nvalue2|label2".
			$options = array_filter( explode( "\n", $field_options ) );
			echo '<div class="crm-radio-group">';
			$first = true;
			foreach ( $options as $option ) {
				$parts = explode( '|', trim( $option ) );
				$value = isset( $parts[0] ) ? trim( $parts[0] ) : '';
				$label = isset( $parts[1] ) ? trim( $parts[1] ) : $value;

				$checked = ( $first && empty( $current_value ) ) || ( $current_value === $value ) ? ' checked' : '';
				echo '<label style="display: inline-block; margin-right: 15px;">';
				echo '<input type="radio" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $value ) . '"' . $checked . $required_attr . ' />';
				echo ' ' . esc_html( $label );
				echo '</label>';
				$first = false;
			}
			echo '</div>';
			break;

		case 'select':
			// Parse options: "value|label\nvalue2|label2".
			$options = array_filter( explode( "\n", $field_options ) );
			echo '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="regular-text"' . $required_attr . '>';
			echo '<option value="">' . esc_html__( '-- Select --', 'formapress-crm' ) . '</option>';
			foreach ( $options as $option ) {
				$parts = explode( '|', trim( $option ) );
				$value = isset( $parts[0] ) ? trim( $parts[0] ) : '';
				$label = isset( $parts[1] ) ? trim( $parts[1] ) : $value;

				$selected = selected( $current_value, $value, false );
				echo '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
			break;

		case 'textarea':
			echo '<textarea id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" class="large-text" rows="4"' . $required_attr . '>';
			echo esc_textarea( $current_value );
			echo '</textarea>';
			break;

		case 'checkbox':
			$checked = checked( $current_value, '1', false );
			echo '<label>';
			echo '<input type="checkbox" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" value="1"' . $checked . $required_attr . ' />';
			echo ' ' . wp_kses_post( $field_options );
			echo '</label>';
			break;

		case 'mail':
		case 'email':
			echo '<input type="email" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $current_value ) . '" class="regular-text"' . $required_attr . ' />';
			break;

		case 'tel':
			echo '<input type="tel" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $current_value ) . '" class="regular-text"' . $required_attr . ' />';
			break;

		case 'number':
			echo '<input type="number" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $current_value ) . '" class="regular-text"' . $required_attr . ' />';
			break;

		case 'text':
		default:
			echo '<input type="text" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $current_value ) . '" class="regular-text"' . $required_attr . ' />';
			break;
	}
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
 * Dynamically saves all fields based on zform_registrations configuration.
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

	// Get dynamic registration fields configuration.
	$zform_registrations = get_option( 'zform_registrations', array() );

	// Field name mapping: zform field name => CRM meta key prefix.
	$field_mapping = array(
		'civilite'  => 'civilite',
		'name'      => 'name',
		'firstname' => 'firstname',
		'mail'      => 'email',
		'telephone' => 'phone',
		'societe'   => 'company',
		'adresse'   => 'address',
		'cp'        => 'postal_code',
		'ville'     => 'city',
		'message'   => 'message',
	);

	// Save all dynamic fields.
	foreach ( $zform_registrations as $field_key => $field_config ) {
		// Skip helptext and RGPD fields.
		if ( isset( $field_config['type'] ) && 'helptext' === $field_config['type'] ) {
			continue;
		}
		if ( in_array( $field_key, array( 'validation-rgpd', 'texte-rgpd' ), true ) ) {
			continue;
		}

		// Map to CRM meta key.
		$crm_meta_key = isset( $field_mapping[ $field_key ] ) ? $field_mapping[ $field_key ] : $field_key;
		$post_key     = 'crm_' . $crm_meta_key;
		$meta_key     = '_crm_' . $crm_meta_key;

		if ( isset( $_POST[ $post_key ] ) ) {
			$field_type = isset( $field_config['type'] ) ? $field_config['type'] : 'text';

			// Sanitize based on field type.
			if ( 'mail' === $field_type || 'email' === $field_type ) {
				$value = sanitize_email( wp_unslash( $_POST[ $post_key ] ) );
			} elseif ( 'textarea' === $field_type ) {
				$value = sanitize_textarea_field( wp_unslash( $_POST[ $post_key ] ) );
			} elseif ( 'number' === $field_type ) {
				$value = intval( $_POST[ $post_key ] );
			} else {
				$value = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
			}

			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	// Update post title with full name (firstname + name).
	$first = isset( $_POST['crm_firstname'] ) ? sanitize_text_field( wp_unslash( $_POST['crm_firstname'] ) ) : '';
	$last  = isset( $_POST['crm_name'] ) ? sanitize_text_field( wp_unslash( $_POST['crm_name'] ) ) : '';
	$title = trim( $first . ' ' . $last );

	if ( ! empty( $title ) ) {
		// Prevent infinite loop by unhooking save action.
		remove_action( 'save_post_crm_person', 'formapress_crm_save_person_meta_data' );
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => $title,
			)
		);
		add_action( 'save_post_crm_person', 'formapress_crm_save_person_meta_data' );
	}

	// Handle crm_entreprise_ids (multiple select).
	if ( isset( $_POST['crm_entreprise_ids'] ) && is_array( $_POST['crm_entreprise_ids'] ) ) {
		$sanitized_entreprise_ids = array_map( 'intval', $_POST['crm_entreprise_ids'] );
		$sanitized_entreprise_ids = array_filter( $sanitized_entreprise_ids );
		$entreprise_ids_string    = implode( ',', $sanitized_entreprise_ids );
		update_post_meta( $post_id, '_crm_entreprise_ids', $entreprise_ids_string );
	} else {
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
