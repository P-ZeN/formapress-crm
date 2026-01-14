<?php
/**
 * Repair script: Build _crm_entreprise_ids array for persons linked to multiple companies
 *
 * This script identifies crm_person posts that have multiple _crm_v1_entreprise_id values
 * (stored by migration as non-unique meta) and consolidates them into the _crm_entreprise_ids array.
 *
 * Background:
 * - In v1, referents were stored as properties of zqpm_entreprise posts
 * - Same person (by email) could be referent for multiple companies
 * - Migration calls migrate_v1_entreprise() for each company sequentially
 * - When same email found, migration adds to _crm_v1_entreprise_id (non-unique) but didn't populate _crm_entreprise_ids
 * - Now fixed in migration code, but existing data needs repair
 *
 * Usage:
 *   wp eval-file wp-content/plugins/formapress-crm/includes/fix-person-multiple-companies.php
 *
 * @package FormaPress
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'This script must be run via WP-CLI' );
}

WP_CLI::line( 'Starting person multiple company links repair...' );
WP_CLI::line( '' );

// Get all crm_person posts.
$persons = get_posts(
	array(
		'post_type'      => 'crm_person',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);

WP_CLI::line( sprintf( 'Found %d crm_person posts to check.', count( $persons ) ) );
WP_CLI::line( '' );

$fixed    = 0;
$multiple = 0;
$single   = 0;
$none     = 0;
$errors   = 0;

foreach ( $persons as $person_id ) {
	$person_title = get_the_title( $person_id );

	// Get all _crm_v1_entreprise_id values (non-unique meta, stored by migration).
	$v1_company_ids = get_post_meta( $person_id, '_crm_v1_entreprise_id', false );

	// Remove duplicates and empty values.
	$v1_company_ids = array_filter( array_unique( $v1_company_ids ) );

	if ( empty( $v1_company_ids ) ) {
		++$none;
		continue;
	}

	if ( count( $v1_company_ids ) === 1 ) {
		++$single;
		// Single company - ensure both _crm_company_id and _crm_entreprise_ids are set.
		$v1_id      = $v1_company_ids[0];
		$v2_company = get_posts(
			array(
				'post_type'      => 'crm_company',
				'meta_key'       => '_crm_v1_entreprise_id',
				'meta_value'     => $v1_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $v2_company ) ) {
			$company_id = $v2_company[0];

			// Ensure _crm_company_id is set.
			$existing_primary = get_post_meta( $person_id, '_crm_company_id', true );
			if ( empty( $existing_primary ) ) {
				update_post_meta( $person_id, '_crm_company_id', $company_id );
				WP_CLI::line( sprintf( '[Single] Person %d (%s): Set _crm_company_id = %d', $person_id, $person_title, $company_id ) );
				++$fixed;
			}

			// Ensure _crm_entreprise_ids is set as array.
			$existing_array = get_post_meta( $person_id, '_crm_entreprise_ids', true );
			if ( empty( $existing_array ) || ! is_array( $existing_array ) ) {
				update_post_meta( $person_id, '_crm_entreprise_ids', array( $company_id ) );
				WP_CLI::line( sprintf( '[Single] Person %d (%s): Set _crm_entreprise_ids = [%d]', $person_id, $person_title, $company_id ) );
				++$fixed;
			}
		}
		continue;
	}

	// Multiple companies - this is the critical case.
	++$multiple;
	WP_CLI::line( sprintf( '[Multiple] Person %d (%s): Found %d v1 company links', $person_id, $person_title, count( $v1_company_ids ) ) );

	$v2_company_ids = array();

	// Map v1 company IDs to v2 company IDs.
	foreach ( $v1_company_ids as $v1_id ) {
		$v2_company = get_posts(
			array(
				'post_type'      => 'crm_company',
				'meta_key'       => '_crm_v1_entreprise_id',
				'meta_value'     => $v1_id,
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $v2_company ) ) {
			$v2_company_ids[] = $v2_company[0];
		} else {
			WP_CLI::warning( sprintf( '  → v1 company %d not found in v2 (not migrated?)', $v1_id ) );
		}
	}

	if ( empty( $v2_company_ids ) ) {
		WP_CLI::warning( sprintf( '  → No v2 companies found for person %d', $person_id ) );
		++$errors;
		continue;
	}

	// Remove duplicates.
	$v2_company_ids = array_unique( $v2_company_ids );

	// Set _crm_entreprise_ids to full array.
	update_post_meta( $person_id, '_crm_entreprise_ids', $v2_company_ids );
	WP_CLI::success( sprintf( '  → Set _crm_entreprise_ids = [%s]', implode( ', ', $v2_company_ids ) ) );

	// Set _crm_company_id to first company (primary for backward compatibility).
	$existing_primary = get_post_meta( $person_id, '_crm_company_id', true );
	if ( empty( $existing_primary ) ) {
		update_post_meta( $person_id, '_crm_company_id', $v2_company_ids[0] );
		WP_CLI::line( sprintf( '  → Set _crm_company_id = %d (primary)', $v2_company_ids[0] ) );
	} else {
		WP_CLI::line( sprintf( '  → Kept existing _crm_company_id = %s (primary)', $existing_primary ) );
	}

	// Display v2 company titles for verification.
	$company_names = array();
	foreach ( $v2_company_ids as $cid ) {
		$company_names[] = get_the_title( $cid );
	}
	WP_CLI::line( sprintf( '  → Companies: %s', implode( ', ', $company_names ) ) );

	++$fixed;
}

WP_CLI::line( '' );
WP_CLI::line( '==================================' );
WP_CLI::line( 'Summary:' );
WP_CLI::line( sprintf( '  Total persons: %d', count( $persons ) ) );
WP_CLI::line( sprintf( '  No company links: %d', $none ) );
WP_CLI::line( sprintf( '  Single company: %d', $single ) );
WP_CLI::line( sprintf( '  Multiple companies: %d', $multiple ) );
WP_CLI::line( sprintf( '  Fixed: %d', $fixed ) );
WP_CLI::line( sprintf( '  Errors: %d', $errors ) );
WP_CLI::line( '==================================' );

if ( $multiple > 0 ) {
	WP_CLI::success( sprintf( 'Successfully repaired %d persons with multiple company links!', $multiple ) );
} else {
	WP_CLI::line( 'No persons with multiple company links found.' );
}
