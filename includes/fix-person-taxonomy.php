<?php
/**
 * Fix Person Type Taxonomy for Existing Persons
 *
 * This script ensures all crm_person posts have the correct person_type taxonomy term set.
 * Run with: wp eval-file wp-content/plugins/formapress-crm/includes/fix-person-taxonomy.php
 *
 * @package FormaPress_CRM
 * @since 2.0.0
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	die( 'This script must be run via WP-CLI' );
}

WP_CLI::line( '' );
WP_CLI::line( WP_CLI::colorize( '%Y╔═══════════════════════════════════════════════════════════╗%n' ) );
WP_CLI::line( WP_CLI::colorize( '%Y║        Fix Person Type Taxonomy for All Persons          ║%n' ) );
WP_CLI::line( WP_CLI::colorize( '%Y╚═══════════════════════════════════════════════════════════╝%n' ) );
WP_CLI::line( '' );

// Get all crm_person posts.
$all_persons = get_posts(
	array(
		'post_type'      => 'crm_person',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);

$total = count( $all_persons );
WP_CLI::line( "Found {$total} persons to check" );
WP_CLI::line( '' );

$fixed = array(
	'instructor'      => 0,
	'trainee'         => 0,
	'company_contact' => 0,
	'funder_contact'  => 0,
	'prospect'        => 0,
	'already_set'     => 0,
	'unknown'         => 0,
);

$progress = \WP_CLI\Utils\make_progress_bar( 'Fixing person_type taxonomy', $total );

foreach ( $all_persons as $person_id ) {
	// Check current taxonomy terms.
	$current_terms = wp_get_object_terms( $person_id, 'person_type', array( 'fields' => 'slugs' ) );

	if ( ! empty( $current_terms ) && ! is_wp_error( $current_terms ) ) {
		++$fixed['already_set'];
		$progress->tick();
		continue;
	}

	// Person has no person_type term - determine type from v1 meta.
	$type = null;

	if ( get_post_meta( $person_id, '_crm_v1_instructor_id', true ) ) {
		$type = 'instructor';
	} elseif ( get_post_meta( $person_id, '_crm_v1_registration_id', true ) ) {
		$type = 'trainee';
	} elseif ( get_post_meta( $person_id, '_crm_v1_referent_id', true ) ) {
		$type = 'company_contact';
	} elseif ( get_post_meta( $person_id, '_crm_v1_funder_id', true ) ) {
		$type = 'funder_contact';
	} elseif ( get_post_meta( $person_id, '_crm_entreprise_ids', true ) ) {
		// Has company link but no v1 ID - likely a referent/company contact.
		$type = 'company_contact';
	}

	if ( $type ) {
		// Set the taxonomy term.
		$result = wp_set_object_terms( $person_id, $type, 'person_type' );

		if ( ! is_wp_error( $result ) ) {
			++$fixed[ $type ];
		} else {
			WP_CLI::warning( "Failed to set person_type for person #{$person_id}: " . $result->get_error_message() );
			++$fixed['unknown'];
		}
	} else {
		// Can't determine type - this shouldn't happen for migrated data.
		++$fixed['unknown'];
		WP_CLI::debug( "Person #{$person_id} has no v1 migration meta - skipping" );
	}

	$progress->tick();
}

$progress->finish();

WP_CLI::line( '' );
WP_CLI::line( WP_CLI::colorize( '%G✓ Taxonomy Fix Complete!%n' ) );
WP_CLI::line( '' );
WP_CLI::line( 'Summary:' );
WP_CLI::line( sprintf( '  • Already had taxonomy:  %d', $fixed['already_set'] ) );
WP_CLI::line( sprintf( '  • Fixed instructors:     %d', $fixed['instructor'] ) );
WP_CLI::line( sprintf( '  • Fixed trainees:        %d', $fixed['trainee'] ) );
WP_CLI::line( sprintf( '  • Fixed company_contact: %d', $fixed['company_contact'] ) );
WP_CLI::line( sprintf( '  • Fixed funder_contact:  %d', $fixed['funder_contact'] ) );
WP_CLI::line( sprintf( '  • Fixed prospects:       %d', $fixed['prospect'] ) );
WP_CLI::line( sprintf( '  • Unknown/skipped:       %d', $fixed['unknown'] ) );
WP_CLI::line( '' );
WP_CLI::line( sprintf( 'Total persons processed: %d', $total ) );
WP_CLI::line( sprintf( 'Total fixed: %d', $fixed['instructor'] + $fixed['trainee'] + $fixed['company_contact'] + $fixed['funder_contact'] + $fixed['prospect'] ) );
WP_CLI::line( '' );
