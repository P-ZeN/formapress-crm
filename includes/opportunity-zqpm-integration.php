<?php
/**
 * Opportunity → ZQPM Integration
 *
 * Handles creating ZQPMs from opportunities via AJAX.
 *
 * @package FormapressCRM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * AJAX: Create ZQPM from opportunity.
 */
function formapress_crm_create_zqpm_from_opportunity() {
	check_ajax_referer( 'formapress_create_zqpm_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission refusée.', 'formapress-crm' ) ) );
	}

	$opportunity_id = isset( $_POST['opportunity_id'] ) ? absint( $_POST['opportunity_id'] ) : 0;
	$session_id     = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
	$company_id     = isset( $_POST['company_id'] ) ? absint( $_POST['company_id'] ) : 0;
	$person_id      = isset( $_POST['person_id'] ) ? absint( $_POST['person_id'] ) : 0;
	$formation_id   = isset( $_POST['formation_id'] ) ? absint( $_POST['formation_id'] ) : 0;

	// Validate required fields.
	if ( ! $opportunity_id || ! $session_id ) {
		wp_send_json_error( array( 'message' => __( 'ID d\'opportunité et de session requis.', 'formapress-crm' ) ) );
	}

	// Verify opportunity exists and is won.
	if ( 'crm_opportunity' !== get_post_type( $opportunity_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Opportunité invalide.', 'formapress-crm' ) ) );
	}

	$stage = get_post_meta( $opportunity_id, '_crm_opportunity_stage', true );
	if ( 'won' !== $stage ) {
		wp_send_json_error( array( 'message' => __( 'L\'opportunité doit être marquée comme "Gagné".', 'formapress-crm' ) ) );
	}

	// Check if opportunity already has a ZQPM.
	$existing_zqpm = get_post_meta( $opportunity_id, '_crm_opportunity_zqpm_id', true );
	if ( ! empty( $existing_zqpm ) && get_post_type( $existing_zqpm ) === 'zqpm' ) {
		wp_send_json_error( array( 'message' => __( 'Cette opportunité a déjà un Suivi de session lié.', 'formapress-crm' ) ) );
	}

	// Get opportunity and session details for ZQPM title.
	$opportunity_title = get_the_title( $opportunity_id );
	$session_title     = get_the_title( $session_id );

	// Create ZQPM post.
	$zqpm_data = array(
		'post_type'   => 'zqpm',
		'post_status' => 'publish',
		'post_title'  => $session_title . ' - ' . $opportunity_title,
		'post_author' => get_current_user_id(),
	);

	$zqpm_id = wp_insert_post( $zqpm_data );

	if ( is_wp_error( $zqpm_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Erreur lors de la création du Suivi de session.', 'formapress-crm' ) ) );
	}

	// Set ZQPM meta data.
	update_post_meta( $zqpm_id, 'zqpm_session_id', $session_id );

	// Save company information.
	if ( $company_id ) {
		// Store company ID in zqpm_infos_entreprise array (ZQPM expects array of company IDs).
		$companies_array = array( $company_id );
		update_post_meta( $zqpm_id, 'zqpm_infos_entreprise', $companies_array );

		// Initialize zqpmmeta entry for company session info (required for ZQPM step 0).
		// This will be populated later in ZQPM edit screen, but we initialize with empty array.
		if ( function_exists( 'update_zqpmmeta' ) ) {
			update_zqpmmeta( $zqpm_id, $company_id, 'entreprise_infos_session', array() );
		}
	}

	// Save contact (referent) information.
	if ( $person_id ) {
		// Verify person is a company_contact type.
		$person_types = wp_get_object_terms( $person_id, 'person_type', array( 'fields' => 'slugs' ) );
		if ( ! is_wp_error( $person_types ) && in_array( 'company_contact', $person_types, true ) ) {
			// Store referent ID in company meta (if company exists).
			if ( $company_id ) {
				$referents = get_post_meta( $company_id, 'referent', true );
				if ( ! is_array( $referents ) ) {
					$referents = array();
				}
				// Add referent if not already present.
				if ( ! in_array( $person_id, $referents, true ) ) {
					$referents[] = $person_id;
					update_post_meta( $company_id, 'referent', $referents );
				}
			}
		}
	}

	if ( $formation_id ) {
		update_post_meta( $zqpm_id, 'zqpm_formation_id', $formation_id );
	}

	// Link opportunity to ZQPM.
	update_post_meta( $opportunity_id, '_crm_opportunity_zqpm_id', $zqpm_id );

	// Link ZQPM back to opportunity (for reference).
	update_post_meta( $zqpm_id, '_crm_source_opportunity_id', $opportunity_id );

	wp_send_json_success(
		array(
			'message'      => __( 'Suivi de session créé avec succès !', 'formapress-crm' ),
			'zqpm_id'      => $zqpm_id,
			'zqpm_title'   => get_the_title( $zqpm_id ),
			'edit_url'     => get_edit_post_link( $zqpm_id, 'raw' ),
			'redirect_url' => get_edit_post_link( $zqpm_id, 'raw' ),
		)
	);
}

add_action( 'wp_ajax_formapress_create_zqpm_from_opportunity', 'formapress_crm_create_zqpm_from_opportunity' );

/**
 * AJAX: Get all available formations for the modal.
 */
function formapress_crm_get_formations() {
	check_ajax_referer( 'formapress_create_zqpm_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission refusée.', 'formapress-crm' ) ) );
	}

	$args = array(
		'post_type'      => 'zform_formation',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'private' ),
		'orderby'        => 'title',
		'order'          => 'ASC',
	);

	$formations = get_posts( $args );
	$result     = array();
	foreach ( $formations as $formation ) {
		$result[] = array(
			'id'    => $formation->ID,
			'title' => $formation->post_title,
		);
	}
	wp_send_json_success( $result );
}
add_action( 'wp_ajax_formapress_get_formations', 'formapress_crm_get_formations' );

/**
 * Get available sessions for ZQPM creation.
 */
function formapress_crm_get_available_sessions() {
	check_ajax_referer( 'formapress_create_zqpm_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission refusée.', 'formapress-crm' ) ) );
	}

	$formation_id = isset( $_POST['formation_id'] ) ? absint( $_POST['formation_id'] ) : 0;

	// Get sessions (zform_session CPT).

	$args = array(
		'post_type'      => 'zform_session',
		'posts_per_page' => 100,
		'post_status'    => array( 'publish', 'private' ),
		'orderby'        => 'date',
		'order'          => 'DESC',
	);

		// Filter by formation if provided.
	if ( $formation_id ) {
		$args['meta_query'] = array(
			array(
				'key'   => 'formation_id', // Correct meta key
				'value' => $formation_id,
			),
		);
	}

	$sessions = get_posts( $args );
	$result   = array();

	foreach ( $sessions as $session ) {
		$formation_id_meta = get_post_meta( $session->ID, 'zform_formation_id', true );
		$formation_title   = '';
		if ( $formation_id_meta ) {
			$formation = get_post( $formation_id_meta );
			if ( $formation ) {
				$formation_title = $formation->post_title;
			}
		}

		$session_date = get_the_date( 'd/m/Y', $session->ID );

		$result[] = array(
			'id'              => $session->ID,
			'title'           => $session->post_title,
			'date'            => $session_date,
			'formation_title' => $formation_title,
		);
	}

	wp_send_json_success( $result );
}
add_action( 'wp_ajax_formapress_get_available_sessions', 'formapress_crm_get_available_sessions' );

/**
 * Enqueue scripts for opportunity edit screen.
 */
function formapress_crm_enqueue_opportunity_zqpm_scripts( $hook ) {
	// Debug: log the hook value to identify correct page.
	error_log( 'ZQPM Enqueue Hook: ' . $hook );

	// Target the custom opportunity editor page and standard post editor for opportunities.
	$is_opportunity_page = false;

	// Check various possible hook values for opportunity editor.
	$opportunity_hooks = array(
		'admin_page_formapress-crm-edit-opportunity',
		'formapress-crm_page_formapress-crm-edit-opportunity',
		'crm_page_formapress-crm-edit-opportunity',
		'toplevel_page_formapress-crm-edit-opportunity',
	);

	if ( in_array( $hook, $opportunity_hooks, true ) ) {
		$is_opportunity_page = true;
	}

	// Check if we're on standard post editor for opportunities.
	if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
		global $post;
		if ( $post && 'crm_opportunity' === $post->post_type ) {
			$is_opportunity_page = true;
		}
	}

	if ( ! $is_opportunity_page ) {
		return;
	}

	error_log( 'ZQPM Scripts: Enqueuing on hook ' . $hook );

	wp_enqueue_script(
		'formapress-opportunity-zqpm',
		FORMAPRESS_CRM_PLUGIN_URL . 'assets/js/opportunity-zqpm.js',
		array( 'jquery' ),
		FORMAPRESS_CRM_VERSION,
		true
	);

	wp_localize_script(
		'formapress-opportunity-zqpm',
		'formapressOpportunityZQPM',
		array(
			'ajax_url'        => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'formapress_create_zqpm_nonce' ),
			'new_session_url' => admin_url( 'post-new.php?post_type=zform_session' ),
		)
	);

	wp_enqueue_style(
		'formapress-opportunity-zqpm',
		FORMAPRESS_CRM_PLUGIN_URL . 'assets/css/opportunity-zqpm-styles.css',
		array(),
		FORMAPRESS_CRM_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'formapress_crm_enqueue_opportunity_zqpm_scripts' );
