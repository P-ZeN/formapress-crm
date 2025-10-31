<?php
/**
 * Legacy System Synchronization
 *
 * Syncs new data from old zformations system to new crm_person system
 * This allows CRM features to work WITHOUT migrating historical data
 *
 * @package formapress-crm
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Find person by email address
 */
function fp_find_person_by_email( $email ) {
	if ( empty( $email ) ) {
		return null;
	}

	$query = new WP_Query(
		array(
			'post_type'      => 'crm_person',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'     => '_crm_email',
					'value'   => sanitize_email( $email ),
					'compare' => '=',
				),
			),
		)
	);

	return $query->have_posts() ? $query->posts[0]->ID : null;
}

/**
 * Find person by legacy instructor ID
 */
function fp_find_person_by_legacy_instructor( $instructor_id ) {
	$query = new WP_Query(
		array(
			'post_type'      => 'crm_person',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'     => '_crm_legacy_instructor_id',
					'value'   => intval( $instructor_id ),
					'compare' => '=',
				),
			),
		)
	);

	return $query->have_posts() ? $query->posts[0]->ID : null;
}

/**
 * Sync new registration to crm_person
 * Hook: Called after zform registration is saved
 *
 * @param int   $registration_id Registration ID from wp_zform_registrations
 * @param array $datas Registration form data
 */
function fp_sync_registration_to_person( $registration_id, $datas ) {
	// Skip if syncing is disabled
	if ( get_option( 'fp_disable_legacy_sync', false ) ) {
		return;
	}

	// Get email (try multiple field names)
	$email = $datas['email'] ?? $datas['mail'] ?? '';
	if ( empty( $email ) ) {
		error_log( "FormaPress CRM: Cannot sync registration {$registration_id} - no email" );
		return;
	}

	// Check if person already exists
	$person_id = fp_find_person_by_email( $email );

	if ( ! $person_id ) {
		// Create new person.
		$first_name = $datas['prenom'] ?? $datas['firstname'] ?? '';
		$last_name  = $datas['nom'] ?? $datas['name'] ?? '';
		$full_name  = trim( $first_name . ' ' . $last_name );

		if ( empty( $full_name ) ) {
			$full_name = $email; // Fallback to email.
		}

		$person_id = wp_insert_post(
			array(
				'post_type'   => 'crm_person',
				'post_title'  => $full_name,
				'post_status' => 'publish',
				'post_date'   => $datas['date_submission'] ?? current_time( 'mysql' ),
			)
		);

		if ( is_wp_error( $person_id ) ) {
			error_log( "FormaPress CRM: Failed to create person from registration {$registration_id}" );
			return;
		}

		// Get dynamic registration fields configuration.
		$zform_registrations = get_option( 'zform_registrations', array() );

		// Field name mapping: registration data key => CRM meta key.
		$field_mapping = array(
			'civilite'  => '_crm_civilite',
			'prenom'    => '_crm_firstname',
			'firstname' => '_crm_firstname',
			'nom'       => '_crm_name',
			'name'      => '_crm_name',
			'mail'      => '_crm_email',
			'email'     => '_crm_email',
			'telephone' => '_crm_phone',
			'phone'     => '_crm_phone',
			'societe'   => '_crm_company',
			'adresse'   => '_crm_address',
			'address'   => '_crm_address',
			'cp'        => '_crm_postal_code',
			'ville'     => '_crm_city',
			'city'      => '_crm_city',
			'message'   => '_crm_message',
		);

		// Save ALL configured fields from registration data.
		foreach ( $zform_registrations as $field_key => $field_config ) {
			// Skip helptext and RGPD validation fields.
			if ( isset( $field_config['type'] ) && 'helptext' === $field_config['type'] ) {
				continue;
			}
			if ( in_array( $field_key, array( 'validation-rgpd', 'texte-rgpd' ), true ) ) {
				continue;
			}

			// Check if this field exists in registration data.
			if ( ! isset( $datas[ $field_key ] ) ) {
				continue;
			}

			// Get mapped meta key.
			$meta_key = isset( $field_mapping[ $field_key ] ) ? $field_mapping[ $field_key ] : '_crm_' . $field_key;
			$value    = $datas[ $field_key ];

			// Sanitize based on field type.
			$field_type = isset( $field_config['type'] ) ? $field_config['type'] : 'text';
			if ( 'mail' === $field_type || 'email' === $field_type || strpos( $meta_key, 'email' ) !== false ) {
				$value = sanitize_email( $value );
			} elseif ( 'textarea' === $field_type ) {
				$value = sanitize_textarea_field( $value );
			} elseif ( 'number' === $field_type ) {
				$value = intval( $value );
			} else {
				$value = sanitize_text_field( $value );
			}

			update_post_meta( $person_id, $meta_key, $value );
		}

		// Set person type taxonomy.
		wp_set_object_terms( $person_id, 'trainee', 'crm_person_type' );

		// Link back to legacy registration.
		update_post_meta( $person_id, '_crm_legacy_registration_ids', array( $registration_id ) );

		do_action( 'fp_person_created_from_registration', $person_id, $registration_id, $datas );
	} else {
		// Person exists - add this registration ID to the list
		$existing_reg_ids = get_post_meta( $person_id, '_crm_legacy_registration_ids', true );
		if ( ! is_array( $existing_reg_ids ) ) {
			$existing_reg_ids = array();
		}
		$existing_reg_ids[] = $registration_id;
		update_post_meta( $person_id, '_crm_legacy_registration_ids', array_unique( $existing_reg_ids ) );
	}

	// Log activity
	fp_log_activity(
		$person_id,
		'registration',
		'Registered for training session',
		array(
			'registration_id' => $registration_id,
			'session_id'      => $datas['session_id'] ?? null,
			'formation_id'    => $datas['formation_id'] ?? null,
		)
	);
}

/**
 * Sync instructor to crm_person when assigned to session
 * Hook: When instructor is added to a session
 *
 * @param int $instructor_id Instructor post ID (zform_instructor CPT)
 * @param int $session_id Session post ID
 */
function fp_sync_instructor_to_person( $instructor_id, $session_id ) {
	if ( get_option( 'fp_disable_legacy_sync', false ) ) {
		return;
	}

	// Check if person exists for this instructor
	$person_id = fp_find_person_by_legacy_instructor( $instructor_id );

	if ( ! $person_id ) {
		$instructor = get_post( $instructor_id );
		if ( ! $instructor ) {
			return;
		}

		$person_id = wp_insert_post(
			array(
				'post_type'    => 'crm_person',
				'post_title'   => $instructor->post_title,
				'post_content' => $instructor->post_content, // Bio
				'post_status'  => 'publish',
				'post_date'    => $instructor->post_date,
			)
		);

		if ( is_wp_error( $person_id ) ) {
			error_log( "FormaPress CRM: Failed to create person from instructor {$instructor_id}" );
			return;
		}

		// Copy instructor meta
		$email = get_post_meta( $instructor_id, 'instructor_email', true );
		$phone = get_post_meta( $instructor_id, 'instructor_phone', true );

		update_post_meta( $person_id, '_crm_email', $email );
		update_post_meta( $person_id, '_crm_phone', $phone );
		update_post_meta( $person_id, '_crm_legacy_instructor_id', $instructor_id );

		// Set person type
		wp_set_object_terms( $person_id, 'instructor', 'crm_person_type' );

		do_action( 'fp_person_created_from_instructor', $person_id, $instructor_id );
	}

	// Log activity
	fp_log_activity(
		$person_id,
		'teaching',
		'Assigned to teach session',
		array(
			'session_id' => $session_id,
		)
	);
}

/**
 * Log activity for a person
 *
 * @param int    $person_id Person post ID
 * @param string $type Activity type (registration, teaching, call, email, meeting)
 * @param string $description Activity description
 * @param array  $meta Additional metadata
 */
function fp_log_activity( $person_id, $type, $description, $meta = array() ) {
	$activity_id = wp_insert_post(
		array(
			'post_type'   => 'crm_activity',
			'post_title'  => $description,
			'post_status' => 'publish',
			'post_date'   => current_time( 'mysql' ),
		)
	);

	if ( is_wp_error( $activity_id ) ) {
		return false;
	}

	update_post_meta( $activity_id, '_activity_person_id', $person_id );
	update_post_meta( $activity_id, '_activity_type', $type );
	update_post_meta( $activity_id, '_activity_meta', $meta );

	do_action( 'fp_activity_logged', $activity_id, $person_id, $type );

	return $activity_id;
}

/**
 * Get unified person data (from new OR old system)
 * This allows code to work with both migrated and non-migrated persons
 *
 * @param int    $id Person ID or legacy registration ID
 * @param string $source 'auto', 'person', 'registration', 'instructor'
 * @return object|null Unified person object
 */
function fp_get_unified_person( $id, $source = 'auto' ) {
	if ( $source === 'auto' ) {
		// Try to detect source
		$post = get_post( $id );
		if ( $post && $post->post_type === 'crm_person' ) {
			$source = 'person';
		}
	}

	if ( $source === 'person' || $source === 'auto' ) {
		$person = get_post( $id );
		if ( $person && $person->post_type === 'crm_person' ) {
			return (object) array(
				'id'                      => $person->ID,
				'type'                    => 'person',
				'first_name'              => get_post_meta( $person->ID, '_crm_first_name', true ),
				'last_name'               => get_post_meta( $person->ID, '_crm_last_name', true ),
				'email'                   => get_post_meta( $person->ID, '_crm_email', true ),
				'phone'                   => get_post_meta( $person->ID, '_crm_phone', true ),
				'legacy_registration_ids' => get_post_meta( $person->ID, '_crm_legacy_registration_ids', true ),
				'legacy_instructor_id'    => get_post_meta( $person->ID, '_crm_legacy_instructor_id', true ),
			);
		}
	}

	// Fallback to legacy systems
	// TODO: Add legacy registration table lookup
	// TODO: Add legacy instructor CPT lookup

	return null;
}

/**
 * Hook into zformations registration handler to sync new registrations.
 * Called after successful registration save in zform_registrations_handler().
 */
function fp_hook_registration_save() {
	if ( ! function_exists( 'zform_registrations_handler' ) ) {
		return; // zformations not active.
	}

	add_action(
		'wp_ajax_zform_registrations',
		function () {
			// Hook AFTER the registration is saved (priority 999).
			add_action(
				'wp_ajax_zform_registrations',
				'fp_intercept_registration_save',
				999
			);
		},
		1
	);

	add_action(
		'wp_ajax_nopriv_zform_registrations',
		function () {
			add_action(
				'wp_ajax_nopriv_zform_registrations',
				'fp_intercept_registration_save',
				999
			);
		},
		1
	);
}
add_action( 'init', 'fp_hook_registration_save' );

/**
 * Intercept registration save to sync with CRM.
 * This runs after zform's handler but before the response is sent.
 */
function fp_intercept_registration_save() {
	global $wpdb;

	// Get the last inserted registration ID.
	$table_name        = $wpdb->prefix . 'zform_registrations';
	$last_registration = $wpdb->get_row( "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT 1" );

	if ( ! $last_registration ) {
		return;
	}

	$registration_id = $last_registration->id;
	$datas           = maybe_unserialize( $last_registration->datas );

	if ( empty( $datas ) ) {
		return;
	}

	// Add IDs to datas for context.
	$datas['formation_id']    = $last_registration->formation_id;
	$datas['session_id']      = $last_registration->session_id;
	$datas['date_submission'] = $last_registration->date_submission;

	// Sync to CRM person.
	fp_sync_registration_to_person( $registration_id, $datas );
}
