<?php
/**
 * Re-import and update Person data from zform_registrations.
 * This script updates existing persons with missing fields and creates new ones.
 *
 * @deprecated 2.0.0 This file contains legacy band-aid migration fixes.
 *             Use WP-CLI with --force flag instead: `wp formapress migrate trainees --force`
 *             See class-formapress-migration-manager.php for the refactored migration system.
 *
 * @package formapress-crm
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Deprecation notice
if ( is_admin() && current_user_can( 'manage_options' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-warning"><p><strong>FormaPress CRM:</strong> migration-reimport.php is deprecated. Use: <code>wp formapress migrate trainees --force</code></p></div>';
		}
	);
}

/**
 * Re-import all registrations from wp_zform_registrations table.
 * Updates existing persons with missing fields and creates new persons.
 *
 * @return array Log of the reimport process.
 */
function formapress_crm_reimport_registrations() {
	global $wpdb;

	$log                 = array();
	$updated_count       = 0;
	$created_count       = 0;
	$skipped_count       = 0;
	$error_count         = 0;
	$table_name          = $wpdb->prefix . 'zform_registrations';
	$zform_registrations = get_option( 'zform_registrations', array() );

	if ( empty( $zform_registrations ) ) {
		$log[] = __( 'ERROR: No zform_registrations configuration found.', 'formapress-crm' );
		return $log;
	}

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
		'company'   => '_crm_company',
		'adresse'   => '_crm_address',
		'address'   => '_crm_address',
		'cp'        => '_crm_postal_code',
		'ville'     => '_crm_city',
		'city'      => '_crm_city',
		'message'   => '_crm_message',
	);

	// Get all registrations.
	$registrations = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY id ASC" );

	if ( empty( $registrations ) ) {
		$log[] = __( 'No registrations found in database.', 'formapress-crm' );
		return $log;
	}

	$log[] = sprintf( __( 'Found %d registrations to process.', 'formapress-crm' ), count( $registrations ) );

	foreach ( $registrations as $registration ) {
		$datas = maybe_unserialize( $registration->datas );

		if ( empty( $datas ) ) {
			++$error_count;
			continue;
		}

		// Get email.
		$email = $datas['mail'] ?? $datas['email'] ?? '';
		if ( empty( $email ) ) {
			++$error_count;
			continue;
		}

		// Check if person exists by email.
		$existing_query = new WP_Query(
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
				'fields'         => 'ids',
			)
		);

		$person_id = null;
		$is_new    = false;

		if ( $existing_query->have_posts() ) {
			// Person exists - update with missing fields.
			$person_id = $existing_query->posts[0];
		} else {
			// Create new person.
			$first_name = $datas['prenom'] ?? $datas['firstname'] ?? '';
			$last_name  = $datas['nom'] ?? $datas['name'] ?? '';
			$full_name  = trim( $first_name . ' ' . $last_name );

			if ( empty( $full_name ) ) {
				$full_name = $email;
			}

			$person_id = wp_insert_post(
				array(
					'post_type'   => 'crm_person',
					'post_title'  => $full_name,
					'post_status' => 'publish',
					'post_date'   => $registration->date_submission ?? current_time( 'mysql' ),
				)
			);

			if ( is_wp_error( $person_id ) ) {
				$log[] = sprintf(
					// translators: %1$s: Email address, %2$s: Error message.
					__( 'ERROR: Failed to create person for %1$s: %2$s', 'formapress-crm' ),
					$email,
					$person_id->get_error_message()
				);
				++$error_count;
				continue;
			}

			$is_new = true;
		}

		// Update/save ALL configured fields.
		$fields_updated = 0;
		foreach ( $zform_registrations as $field_key => $field_config ) {
			// Skip helptext and RGPD fields.
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

			// Skip if already set and not updating.
			$existing_value = get_post_meta( $person_id, $meta_key, true );
			if ( ! empty( $existing_value ) && ! $is_new ) {
				continue; // Don't overwrite existing data.
			}

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
			++$fields_updated;
		}

		// Set person type taxonomy.
		$terms = wp_get_object_terms( $person_id, 'crm_person_type', array( 'fields' => 'slugs' ) );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			wp_set_object_terms( $person_id, 'trainee', 'crm_person_type' );
		}

		// Link to registration.
		$existing_reg_ids = get_post_meta( $person_id, '_crm_legacy_registration_ids', true );
		if ( ! is_array( $existing_reg_ids ) ) {
			$existing_reg_ids = array();
		}
		if ( ! in_array( $registration->id, $existing_reg_ids, true ) ) {
			$existing_reg_ids[] = $registration->id;
			update_post_meta( $person_id, '_crm_legacy_registration_ids', $existing_reg_ids );
		}

		if ( $is_new ) {
			$log[] = sprintf(
				// translators: %1$s: Email address, %2$d: Number of fields saved.
				__( 'CREATED: %1$s (%2$d fields)', 'formapress-crm' ),
				$email,
				$fields_updated
			);
			++$created_count;
		} elseif ( $fields_updated > 0 ) {
			$log[] = sprintf(
				// translators: %1$s: Email address, %2$d: Number of fields updated.
				__( 'UPDATED: %1$s (%2$d fields)', 'formapress-crm' ),
				$email,
				$fields_updated
			);
			++$updated_count;
		} else {
			++$skipped_count;
		}
	}

	$log[] = sprintf(
		// translators: %1$d: Created count, %2$d: Updated count, %3$d: Skipped count, %4$d: Error count.
		__( 'COMPLETE: Created %1$d, Updated %2$d, Skipped %3$d, Errors %4$d', 'formapress-crm' ),
		$created_count,
		$updated_count,
		$skipped_count,
		$error_count
	);

	return $log;
}

/**
 * AJAX handler for re-importing registrations with full data.
 */
function formapress_crm_ajax_reimport_registrations() {
	check_ajax_referer( 'formapress_crm_reimport_nonce', '_ajax_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied.' ) );
	}

	$result = formapress_crm_reimport_registrations_with_full_data();

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
}
add_action( 'wp_ajax_formapress_crm_reimport_registrations', 'formapress_crm_ajax_reimport_registrations' );

/**
 * Sync company associations for all persons.
 * Fixes legacy data where _crm_company contains names or IDs but _crm_entreprise_ids is empty.
 */
function formapress_crm_sync_company_associations() {
	$persons = get_posts(
		array(
			'post_type'      => 'crm_person',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => 'any',
		)
	);

	$fixed_count   = 0;
	$created_count = 0;
	$skipped_count = 0;
	$log           = array();

	foreach ( $persons as $person_id ) {
		$entreprise_ids = get_post_meta( $person_id, '_crm_entreprise_ids', true );

		// Only process if _crm_entreprise_ids is empty.
		if ( ! empty( $entreprise_ids ) ) {
			++$skipped_count;
			continue;
		}

		$company_value = get_post_meta( $person_id, '_crm_company', true );

		if ( empty( $company_value ) ) {
			++$skipped_count;
			continue;
		}

		// Check if it's a numeric ID.
		if ( is_numeric( $company_value ) ) {
			// Verify the company post exists.
			$company_post = get_post( intval( $company_value ) );
			if ( $company_post && $company_post->post_type === 'zqpm_entreprise' ) {
				update_post_meta( $person_id, '_crm_entreprise_ids', $company_value );
				++$fixed_count;
				$log[] = 'Person #' . $person_id . ': Linked to existing company #' . $company_value . ' (' . $company_post->post_title . ')';
			} else {
				$log[] = 'Person #' . $person_id . ': Invalid company ID ' . $company_value;
				++$skipped_count;
			}
		} else {
			// It's a company name - try to find or create.
			$matching_companies = get_posts(
				array(
					'post_type'      => 'zqpm_entreprise',
					'title'          => $company_value,
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);

			if ( ! empty( $matching_companies ) ) {
				// Found existing company.
				$company_id = $matching_companies[0];
				update_post_meta( $person_id, '_crm_entreprise_ids', $company_id );
				update_post_meta( $person_id, '_crm_company', $company_id ); // Update to use ID.
				++$fixed_count;
				$log[] = 'Person #' . $person_id . ': Linked to existing company #' . $company_id . ' (' . $company_value . ')';
			} else {
				// Create new company.
				$company_id = wp_insert_post(
					array(
						'post_type'   => 'zqpm_entreprise',
						'post_title'  => $company_value,
						'post_status' => 'publish',
					)
				);

				if ( ! is_wp_error( $company_id ) ) {
					update_post_meta( $person_id, '_crm_entreprise_ids', $company_id );
					update_post_meta( $person_id, '_crm_company', $company_id ); // Update to use ID.
					++$created_count;
					++$fixed_count;
					$log[] = 'Person #' . $person_id . ': Created new company #' . $company_id . ' (' . $company_value . ')';
				} else {
					$log[] = 'Person #' . $person_id . ': Failed to create company "' . $company_value . '"';
					++$skipped_count;
				}
			}
		}
	}

	return array(
		'success'       => true,
		'fixed_count'   => $fixed_count,
		'created_count' => $created_count,
		'skipped_count' => $skipped_count,
		'log'           => $log,
	);
}

/**
 * AJAX handler for syncing company associations.
 */
function formapress_crm_ajax_sync_company_associations() {
	check_ajax_referer( 'formapress_crm_sync_companies_nonce', '_ajax_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied.' ) );
	}

	$result = formapress_crm_sync_company_associations();

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
}
add_action( 'wp_ajax_formapress_crm_sync_company_associations', 'formapress_crm_ajax_sync_company_associations' );
