<?php
/**
 * Formapress CRM Synchronization Functions
 *
 * @package FormapressCRM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Synchronizes a WordPress user to a crm_person record.
 *
 * Creates or updates a crm_person when a WP_User with role 'stagiaire' or 'instructor' is created or updated.
 *
 * @param int $user_id The ID of the user being registered or updated.
 */
function formapress_crm_sync_user_to_person( $user_id ) {
	$user = get_userdata( $user_id );

	if ( ! $user ) {
		return;
	}

	$user_roles      = (array) $user->roles;
	$target_roles    = array( 'stagiaire', 'instructor' );
	$has_target_role = false;

	foreach ( $target_roles as $role ) {
		if ( in_array( $role, $user_roles, true ) ) {
			$has_target_role = true;
			break;
		}
	}

	if ( ! $has_target_role ) {
		return; // Not a user role we need to sync for now.
	}

	// Check if a crm_person already exists for this user_id.
	$existing_persons = get_posts(
		array(
			'post_type'      => 'crm_person',
			'meta_key'       => '_crm_user_id',
			'meta_value'     => $user_id,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);

	$person_post_data = array(
		'post_title'  => $user->display_name,
		'post_status' => 'publish',
		'post_type'   => 'crm_person',
		// 'post_author' => $user_id, // Or a specific admin user.
	);

	$person_id = 0;

	if ( ! empty( $existing_persons ) ) {
		$person_id              = $existing_persons[0];
		$person_post_data['ID'] = $person_id;
		wp_update_post( $person_post_data );
	} else {
		$person_id = wp_insert_post( $person_post_data );
	}

	if ( $person_id && ! is_wp_error( $person_id ) ) {
		// Update/set meta fields for the crm_person.
		update_post_meta( $person_id, '_crm_user_id', $user_id );
		update_post_meta( $person_id, '_crm_email', $user->user_email );
		if ( ! empty( $user->first_name ) ) {
			// If you have separate first/last name fields in crm_person meta, update them here.
			// For now, title uses display_name.
		}

		// Assign crm_person_type taxonomy term.
		$person_type_slug = '';
		if ( in_array( 'stagiaire', $user_roles, true ) ) {
			$person_type_slug = 'trainee'; // Assuming you have a term 'trainee' in 'crm_person_type'.
		} elseif ( in_array( 'instructor', $user_roles, true ) ) {
			$person_type_slug = 'instructor'; // Assuming you have a term 'instructor'.
		}

		if ( ! empty( $person_type_slug ) ) {
			$term = get_term_by( 'slug', $person_type_slug, 'crm_person_type' );
			if ( ! $term ) {
				// Optionally create the term if it doesn't exist.
				$term_info = wp_insert_term( ucfirst( $person_type_slug ), 'crm_person_type', array( 'slug' => $person_type_slug ) );
				if ( ! is_wp_error( $term_info ) ) {
					$term_id = $term_info['term_id'];
					wp_set_post_terms( $person_id, array( $term_id ), 'crm_person_type' );
				}
			} else {
				wp_set_post_terms( $person_id, array( $term->term_id ), 'crm_person_type' );
			}
		}
	}
}
add_action( 'user_register', 'formapress_crm_sync_user_to_person', 10, 1 );
add_action( 'profile_update', 'formapress_crm_sync_user_to_person', 10, 1 ); // user_id is the first param for profile_update.

/**
 * Links a zform_registration to a crm_person when the registration is saved.
 *
 * @param int     $post_id The ID of the post being saved.
 * @param WP_Post $post    The post object.
 * @param bool    $update  Whether this is an existing post being updated or not.
 */
function formapress_crm_link_registration_to_person( $post_id, $post, $update ) {
	// Only act on the 'zform_registration' post type.
	if ( 'zform_registration' !== $post->post_type ) {
		return;
	}

	// Prevent infinite loops and ensure it's not an auto-save.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Check if the current user has permission to edit this post.
	// This might need adjustment based on how registrations are created (e.g., front-end form vs. admin).
	if ( ! current_user_can( 'edit_post', $post_id ) && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		// If not admin and not WP_CLI, check for a specific capability or if it's a front-end submission context.
		// For now, we'll assume if it reaches here via a form submission, it's okay, but this needs review for security.
	}

	// Get the user ID associated with this registration.
	// The meta key '_zform_user_id' is an assumption. Replace with the actual meta key used by ZForm.
	$user_id = get_post_meta( $post_id, '_zform_user_id', true );

	if ( empty( $user_id ) ) {
		// If no direct user_id, try to get it from post_author if registrations are created by logged-in users.
		$user_id = $post->post_author;
	}

	if ( empty( $user_id ) ) {
		// If still no user_id, try to get it from an email field and then find the user.
		// The meta key '_zform_email' is an assumption. Replace with the actual meta key for the registrant's email.
		$registrant_email = get_post_meta( $post_id, '_zform_email', true );
		if ( ! empty( $registrant_email ) && is_email( $registrant_email ) ) {
			$user = get_user_by( 'email', $registrant_email );
			if ( $user ) {
				$user_id = $user->ID;
			}
		}
	}

	if ( empty( $user_id ) ) {
		// error_log("[Formapress CRM] No user ID found for zform_registration ID: $post_id to link to crm_person.");
		return; // No user to link to.
	}

	// Find the crm_person associated with this user_id.
	$crm_persons = get_posts(
		array(
			'post_type'      => 'crm_person',
			'meta_key'       => '_crm_user_id',
			'meta_value'     => $user_id,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);

	if ( empty( $crm_persons ) ) {
		// error_log("[Formapress CRM] No crm_person found for user ID: $user_id (from zform_registration ID: $post_id).");
		return; // No crm_person to link to.
	}

	$person_id = $crm_persons[0];

	// Get existing registration IDs for this person.
	$associated_registrations = get_post_meta( $person_id, '_associated_registration_ids', true );
	if ( ! is_array( $associated_registrations ) ) {
		$associated_registrations = array();
	}

	// Add the current registration ID if it's not already there.
	if ( ! in_array( $post_id, $associated_registrations, true ) ) {
		$associated_registrations[] = $post_id;
		// Remove duplicates just in case, though the in_array check should prevent them.
		$associated_registrations = array_unique( $associated_registrations );
		update_post_meta( $person_id, '_associated_registration_ids', $associated_registrations );
		// error_log("[Formapress CRM] Linked zform_registration ID: $post_id to crm_person ID: $person_id.");
	}
}
add_action( 'save_post_zform_registration', 'formapress_crm_link_registration_to_person', 10, 3 );

/**
 * AJAX handler for migrating zqpmReferent data to crm_person posts.
 */
function formapress_crm_ajax_migrate_referents_handler() {
	check_ajax_referer( 'formapress_crm_migrate_referents_nonce', '_ajax_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have sufficient permissions to perform this action.', 'formapress-crm' ) ) );
	}

	$log            = array();
	$migrated_count = 0;
	$skipped_count  = 0;
	$error_count    = 0;

	$log[] = __( 'Starting zqpmReferent to crm_person migration...', 'formapress-crm' );

	// 1. Querying all 'zqpm_entreprise' posts.
	// 2. For each entreprise, get the 'referent' meta field.
	// 3. Loop through each referent array in the meta.
	// 4. For each referent:
	// a. Check if a crm_person already exists.
	// b. If not, create a new crm_person.
	// - Post title: referent->prenom . ' ' . referent->nom
	// - Meta: _crm_civility, _crm_email, _crm_phone, _crm_job_title (from referent->poste)
	// - Meta: _crm_entreprise_ids (link to the parent zqpm_entreprise ID)
	// - Taxonomy: crm_person_type (e.g., 'company-contact')
	// c. Log success, skip, or error.

	$entreprises = get_posts(
		array(
			'post_type'      => 'zqpm_entreprise',
			'posts_per_page' => -1,
			'post_status'    => 'any',
		)
	);

	// translators: %d: Number of entreprises found.
	$log[] = sprintf( __( 'Found %d entreprises to process.', 'formapress-crm' ), count( $entreprises ) );

	foreach ( $entreprises as $entreprise ) {
		$referents_data = get_post_meta( $entreprise->ID, 'referent', true );

		if ( ! empty( $referents_data ) && is_array( $referents_data ) ) {
			// translators: %1$d: Entreprise ID, %2$s: Entreprise Title.
			$log[] = sprintf( __( 'Processing entreprise ID %1$d: %2$s', 'formapress-crm' ), $entreprise->ID, $entreprise->post_title );
			foreach ( $referents_data as $referent_item ) {
				$referent = (object) $referent_item; // Ensure it's an object for consistent access.

				$email  = isset( $referent->mail ) ? sanitize_email( $referent->mail ) : '';
				$nom    = isset( $referent->nom ) ? sanitize_text_field( $referent->nom ) : '';
				$prenom = isset( $referent->prenom ) ? sanitize_text_field( $referent->prenom ) : '';

				if ( empty( $email ) && ( empty( $nom ) || empty( $prenom ) ) ) {
					$log[] = __( 'Skipping referent due to missing email and name.', 'formapress-crm' );
					++$skipped_count;
					continue;
				}

				$existing_person_args = array(
					'post_type'      => 'crm_person',
					'posts_per_page' => 1,
					'meta_query'     => array(
						'relation' => 'AND',
						array(
							'key'     => '_crm_email',
							'value'   => $email,
							'compare' => '=',
						),
						array(
							'key'     => '_crm_entreprise_ids',
							'value'   => strval( $entreprise->ID ), // Check against the simple string ID.
							'compare' => '=', // Exact match for single ID. Use LIKE if it can be comma-separated or serialized.
						),
					),
					'fields'         => 'ids',
				);
				if ( empty( $email ) ) {
					unset( $existing_person_args['meta_query'][0] ); // Remove email condition if email is empty.
				}

				$existing_persons = empty( $email ) ? array() : get_posts( $existing_person_args );

				if ( ! empty( $existing_persons ) ) {
					// translators: %1$s: First name, %2$s: Last name, %3$s: Email, %4$d: Company ID.
					$log[] = sprintf( __( 'Skipped: crm_person already exists for %1$s %2$s (Email: %3$s) linked to company ID %4$d.', 'formapress-crm' ), $prenom, $nom, $email, $entreprise->ID );
					++$skipped_count;
					continue;
				}

				$person_title = trim( $prenom . ' ' . $nom );
				if ( empty( $person_title ) && ! empty( $email ) ) {
					$person_title = $email; // Fallback title if name is empty but email exists.
				} elseif ( empty( $person_title ) && empty( $email ) ) {
					// translators: %d: Entreprise ID.
					$log[] = sprintf( __( 'Skipping referent for entreprise ID %d due to missing name and email for title.', 'formapress-crm' ), $entreprise->ID );
					++$skipped_count;
					continue;
				}

				$person_data = array(
					'post_title'  => $person_title,
					'post_status' => 'publish',
					'post_type'   => 'crm_person',
				);

				$new_person_id = wp_insert_post( $person_data, true ); // true for WP_Error on failure.

				if ( is_wp_error( $new_person_id ) ) {
					// translators: %1$s: First name, %2$s: Last name, %3$s: Error message.
					$log[] = sprintf( __( 'Error creating crm_person for %1$s %2$s: %3$s', 'formapress-crm' ), $prenom, $nom, $new_person_id->get_error_message() );
					++$error_count;
					continue;
				}

				update_post_meta( $new_person_id, '_crm_civility', isset( $referent->civilite ) ? sanitize_text_field( $referent->civilite ) : '' );
				update_post_meta( $new_person_id, '_crm_email', $email );
				update_post_meta( $new_person_id, '_crm_phone', isset( $referent->tel ) ? sanitize_text_field( $referent->tel ) : '' );
				update_post_meta( $new_person_id, '_crm_job_title', isset( $referent->poste ) ? sanitize_text_field( $referent->poste ) : '' );
				update_post_meta( $new_person_id, '_crm_entreprise_ids', strval( $entreprise->ID ) );

				$term_slug = 'company-contact';
				$term_name = 'Company Contact';
				$term      = get_term_by( 'slug', $term_slug, 'crm_person_type' );
				if ( ! $term ) {
					$term_info = wp_insert_term( $term_name, 'crm_person_type', array( 'slug' => $term_slug ) );
					if ( ! is_wp_error( $term_info ) ) {
						wp_set_post_terms( $new_person_id, array( $term_info['term_id'] ), 'crm_person_type' );
					}
				} else {
					wp_set_post_terms( $new_person_id, array( $term->term_id ), 'crm_person_type' );
				}

				// translators: %1$s: First name, %2$s: Last name, %3$d: New Person ID.
				$log[] = sprintf( __( 'Successfully migrated referent %1$s %2$s to crm_person ID %3$d.', 'formapress-crm' ), $prenom, $nom, $new_person_id );
				++$migrated_count;
			}
		} else {
			// translators: %d: Entreprise ID.
			$log[] = sprintf( __( 'No referent data found or data is not an array for entreprise ID %d.', 'formapress-crm' ), $entreprise->ID );
		}
	}

	// translators: %1$d: Migrated count, %2$d: Skipped count, %3$d: Error count.
	$log[] = sprintf( __( 'Migration complete. Migrated: %1$d, Skipped: %2$d, Errors: %3$d.', 'formapress-crm' ), $migrated_count, $skipped_count, $error_count );

	wp_send_json_success(
		array(
			'message' => __( 'Referent migration process finished.', 'formapress-crm' ),
			'log'     => $log,
		)
	);
}
add_action( 'wp_ajax_formapress_crm_migrate_referents', 'formapress_crm_ajax_migrate_referents_handler' );

/**
 * Retrieves referent information for a given zqpm_entreprise ID.
 *
 * Prioritizes fetching data from crm_person CPTs linked to the entreprise.
 * Falls back to reading the 'referent' meta key if no crm_person records are found
 * or if $use_crm_persons is false.
 *
 * @param int  $entreprise_id The ID of the zqpm_entreprise post.
 * @param bool $use_crm_persons Whether to prioritize crm_person data. Defaults to true.
 * @return array An array of referent data, or an empty array if none found.
 */
function formapress_crm_get_entreprise_referents( $entreprise_id, $use_crm_persons = true ) {
	if ( ! $entreprise_id ) {
		return array();
	}

	$referents_output = array();

	if ( $use_crm_persons ) {
		$crm_person_args = array(
			'post_type'      => 'crm_person',
			'posts_per_page' => -1, // Get all linked persons.
			'meta_query'     => array(
				array(
					'key'     => '_crm_entreprise_ids', // Assumes this stores a single entreprise ID as a string.
					'value'   => strval( $entreprise_id ),
					'compare' => '=',
				),
			),
			// Optionally, filter by crm_person_type if needed, e.g., 'company-contact'.
			// 'tax_query' => array(
			// array(
			// 'taxonomy' => 'crm_person_type',
			// 'field'    => 'slug',
			// 'terms'    => 'company-contact',
			// ),
			// ),
		);

		$crm_persons = get_posts( $crm_person_args );

		if ( ! empty( $crm_persons ) ) {
			foreach ( $crm_persons as $person_post ) {
				$nom_prenom = explode( ' ', $person_post->post_title, 2 );
				$prenom     = $nom_prenom[0];
				$nom        = isset( $nom_prenom[1] ) ? $nom_prenom[1] : '';
				// This parsing of name from title is basic. If you store first/last name separately, use that.

				$referents_output[] = (object) array(
					'civilite'      => get_post_meta( $person_post->ID, '_crm_civility', true ),
					'nom'           => $nom,
					'prenom'        => $prenom,
					'mail'          => get_post_meta( $person_post->ID, '_crm_email', true ),
					'tel'           => get_post_meta( $person_post->ID, '_crm_phone', true ),
					'poste'         => get_post_meta( $person_post->ID, '_crm_job_title', true ),
					'crm_person_id' => $person_post->ID, // Add CRM person ID for reference.
				);
			}
			return $referents_output; // Return data from crm_person.
		}
	}

	// Fallback to old 'referent' meta if no crm_persons found or $use_crm_persons is false.
	$legacy_referents_data = get_post_meta( $entreprise_id, 'referent', true );

	if ( ! empty( $legacy_referents_data ) && is_array( $legacy_referents_data ) ) {
		// Ensure the legacy data is an array of objects for consistency, if it's not already.
		foreach ( $legacy_referents_data as $item ) {
			$referents_output[] = (object) $item;
		}
		return $referents_output;
	}

	return array(); // Return empty array if no referents found.
}

/**
 * Bulk import instructors and trainees into crm_person.
 *
 * @return array Log of the import process.
 */
function formapress_crm_bulk_import_persons() {
	$log            = array();
	$imported_count = 0;
	$skipped_count  = 0;

	// Query all users with roles 'instructor' and 'stagiaire'.
	$user_query = new WP_User_Query(
		array(
			'role__in' => array( 'instructor', 'stagiaire' ),
			'fields'   => 'all',
		)
	);

	$users = $user_query->get_results();

	if ( empty( $users ) ) {
		$log[] = __( 'No users found with roles instructor or stagiaire.', 'formapress-crm' );
		return $log;
	}

	foreach ( $users as $user ) {
		// Check if a crm_person already exists for this user.
		$existing_persons = get_posts(
			array(
				'post_type'      => 'crm_person',
				'meta_key'       => '_crm_user_id',
				'meta_value'     => $user->ID,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing_persons ) ) {
			// translators: %1$s: User display name, %2$d: User ID.
			$log[] = sprintf( __( 'Skipped: crm_person already exists for user %1$s (ID: %2$d).', 'formapress-crm' ), $user->display_name, $user->ID );
			++$skipped_count;
			continue;
		}

		// Create a new crm_person post.
		$person_data = array(
			'post_title'  => $user->display_name,
			'post_status' => 'publish',
			'post_type'   => 'crm_person',
		);

		$person_id = wp_insert_post( $person_data );

		if ( is_wp_error( $person_id ) ) {
			// translators: %1$s: User display name, %2$d: User ID, %3$s: Error message.
			$log[] = sprintf( __( 'Error creating crm_person for user %1$s (ID: %2$d): %3$s', 'formapress-crm' ), $user->display_name, $user->ID, $person_id->get_error_message() );
			continue;
		}

		// Update meta fields for the crm_person.
		update_post_meta( $person_id, '_crm_user_id', $user->ID );
		update_post_meta( $person_id, '_crm_email', $user->user_email );
		update_post_meta( $person_id, '_crm_phone', $user->user_phone ?? '' ); // Assuming 'user_phone' meta exists.

		// Assign crm_person_type taxonomy term.
		$person_type_slug = in_array( 'instructor', (array) $user->roles, true ) ? 'instructor' : 'trainee';
		$term             = get_term_by( 'slug', $person_type_slug, 'crm_person_type' );
		if ( ! $term ) {
			$term_info = wp_insert_term( ucfirst( $person_type_slug ), 'crm_person_type', array( 'slug' => $person_type_slug ) );
			if ( ! is_wp_error( $term_info ) ) {
				wp_set_post_terms( $person_id, array( $term_info['term_id'] ), 'crm_person_type' );
			}
		} else {
			wp_set_post_terms( $person_id, array( $term->term_id ), 'crm_person_type' );
		}

		// translators: %1$s: User display name, %2$d: User ID.
		$log[] = sprintf( __( 'Imported: crm_person created for user %1$s (ID: %2$d).', 'formapress-crm' ), $user->display_name, $user->ID );
		++$imported_count;
	}

	// translators: %1$d: Imported count, %2$d: Skipped count.
	$log[] = sprintf( __( 'Import complete. Imported: %1$d, Skipped: %2$d.', 'formapress-crm' ), $imported_count, $skipped_count );

	return $log;
}

/**
 * Bulk import users with a specific role into crm_person.
 *
 * @param string $role The user role to import ('instructor' or 'stagiaire').
 * @return array Log of the import process.
 */
function formapress_crm_bulk_import_persons_by_role( $role ) {
	$log            = array();
	$imported_count = 0;
	$skipped_count  = 0;

	if ( ! in_array( $role, array( 'instructor', 'stagiaire' ), true ) ) {
		$log[] = __( 'Invalid role specified for import.', 'formapress-crm' );
		return $log;
	}

	$user_query = new WP_User_Query(
		array(
			'role'   => $role,
			'fields' => 'all',
		)
	);

	$users = $user_query->get_results();

	if ( empty( $users ) ) {
		// translators: %s: User role.
		$log[] = sprintf( __( 'No users found with role %s.', 'formapress-crm' ), $role );
		return $log;
	}

	foreach ( $users as $user ) {
		$existing_persons = get_posts(
			array(
				'post_type'      => 'crm_person',
				'meta_key'       => '_crm_user_id',
				'meta_value'     => $user->ID,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing_persons ) ) {
			// translators: %1$s: User display name, %2$d: User ID.
			$log[] = sprintf( __( 'Skipped: crm_person already exists for user %1$s (ID: %2$d).', 'formapress-crm' ), $user->display_name, $user->ID );
			++$skipped_count;
			continue;
		}

		$person_data = array(
			'post_title'  => $user->display_name,
			'post_status' => 'publish',
			'post_type'   => 'crm_person',
		);

		$person_id = wp_insert_post( $person_data );

		if ( is_wp_error( $person_id ) ) {
			// translators: %1$s: User display name, %2$d: User ID, %3$s: Error message.
			$log[] = sprintf( __( 'Error creating crm_person for user %1$s (ID: %2$d): %3$s', 'formapress-crm' ), $user->display_name, $user->ID, $person_id->get_error_message() );
			continue;
		}

		update_post_meta( $person_id, '_crm_user_id', $user->ID );
		update_post_meta( $person_id, '_crm_email', $user->user_email );
		update_post_meta( $person_id, '_crm_phone', $user->user_phone ?? '' );

		$person_type_slug = ( $role === 'instructor' ) ? 'instructor' : 'trainee';
		$term             = get_term_by( 'slug', $person_type_slug, 'crm_person_type' );
		if ( ! $term ) {
			$term_info = wp_insert_term( ucfirst( $person_type_slug ), 'crm_person_type', array( 'slug' => $person_type_slug ) );
			if ( ! is_wp_error( $term_info ) ) {
				wp_set_post_terms( $person_id, array( $term_info['term_id'] ), 'crm_person_type' );
			}
		} else {
			wp_set_post_terms( $person_id, array( $term->term_id ), 'crm_person_type' );
		}

		// translators: %1$s: User display name, %2$d: User ID.
		$log[] = sprintf( __( 'Imported: crm_person created for user %1$s (ID: %2$d).', 'formapress-crm' ), $user->display_name, $user->ID );
		++$imported_count;
	}

	// translators: %1$d: Imported count, %2$d: Skipped count.
	$log[] = sprintf( __( 'Import complete. Imported: %1$d, Skipped: %2$d.', 'formapress-crm' ), $imported_count, $skipped_count );

	return $log;
}

/**
 * AJAX handler for importing instructors.
 */
function formapress_crm_ajax_import_instructors_handler() {
	check_ajax_referer( 'formapress_crm_import_instructors_nonce', '_ajax_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have sufficient permissions to perform this action.', 'formapress-crm' ) ) );
	}
	$log = formapress_crm_bulk_import_persons_by_role( 'instructor' );
	wp_send_json_success(
		array(
			'message' => __( 'Instructor import process finished.', 'formapress-crm' ),
			'log'     => $log,
		)
	);
}
add_action( 'wp_ajax_formapress_crm_import_instructors', 'formapress_crm_ajax_import_instructors_handler' );

/**
 * AJAX handler for importing trainees.
 */
function formapress_crm_ajax_import_trainees_handler() {
	check_ajax_referer( 'formapress_crm_import_trainees_nonce', '_ajax_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have sufficient permissions to perform this action.', 'formapress-crm' ) ) );
	}
	$log = formapress_crm_bulk_import_persons_by_role( 'stagiaire' );
	wp_send_json_success(
		array(
			'message' => __( 'Trainee import process finished.', 'formapress-crm' ),
			'log'     => $log,
		)
	);
}
add_action( 'wp_ajax_formapress_crm_import_trainees', 'formapress_crm_ajax_import_trainees_handler' );
