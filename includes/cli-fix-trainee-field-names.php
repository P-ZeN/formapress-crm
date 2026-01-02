<?php
/**
 * WP-CLI Command: Fix Trainee Attribute Field Names
 *
 * Renames incorrectly stored trainee attribute meta keys from French to English schema slugs.
 * This fixes a bug where core trainee fields were stored with French names (nom/prenom/email)
 * instead of the English schema slugs enforced by zregistration_sanitize_options().
 *
 * Usage: wp formapress fix-trainee-fields [--dry-run]
 *
 * @package FormaPress_CRM
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fix trainee attribute field names
 */
class FormaPress_Fix_Trainee_Fields_Command {

	/**
	 * Fix trainee attribute meta keys from French to English schema slugs
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Preview changes without modifying database
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview what will be fixed
	 *     wp formapress fix-trainee-fields --dry-run
	 *
	 *     # Actually fix the data
	 *     wp formapress fix-trainee-fields
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$dry_run = isset( $assoc_args['dry-run'] );

		if ( $dry_run ) {
			WP_CLI::warning( 'DRY RUN MODE - No changes will be made' );
		}

		// Field name mappings: French (wrong) => English (correct schema slug)
		$renames = array(
			'crm_person_trainee_attributes_nom'    => 'crm_person_trainee_attributes_name',
			'crm_person_trainee_attributes_prenom' => 'crm_person_trainee_attributes_firstname',
			'crm_person_trainee_attributes_email'  => 'crm_person_trainee_attributes_mail',
			'crm_person_trainee_attributes_tel'    => 'crm_person_trainee_attributes_telephone',
		);

		// Get all trainees
		$trainees = get_posts(
			array(
				'post_type'      => 'crm_person',
				'posts_per_page' => -1,
				'tax_query'      => array(
					array(
						'taxonomy' => 'person_type',
						'field'    => 'slug',
						'terms'    => 'trainee',
					),
				),
			)
		);

		if ( empty( $trainees ) ) {
			WP_CLI::error( 'No trainees found!' );
			return;
		}

		WP_CLI::log( sprintf( 'Found %d trainees to check...', count( $trainees ) ) );

		$fixed_count  = 0;
		$person_count = 0;

		foreach ( $trainees as $trainee ) {
			$person_fixed = false;

			foreach ( $renames as $old_key => $new_key ) {
				$old_value = get_post_meta( $trainee->ID, $old_key, true );

				if ( ! empty( $old_value ) ) {
					$person_fixed = true;
					++$fixed_count;

					if ( ! $dry_run ) {
						update_post_meta( $trainee->ID, $new_key, $old_value );
						delete_post_meta( $trainee->ID, $old_key );
						WP_CLI::log(
							sprintf(
								'  Person %d: Renamed %s → %s (value: %s)',
								$trainee->ID,
								$old_key,
								$new_key,
								$old_value
							)
						);
					} else {
						WP_CLI::log(
							sprintf(
								'  Person %d: Would rename %s → %s (value: %s)',
								$trainee->ID,
								$old_key,
								$new_key,
								$old_value
							)
						);
					}
				}
			}

			if ( $person_fixed ) {
				++$person_count;
			}
		}

		if ( $dry_run ) {
			WP_CLI::success(
				sprintf(
					'DRY RUN: Would fix %d field(s) across %d trainee(s)',
					$fixed_count,
					$person_count
				)
			);
		} else {
			WP_CLI::success(
				sprintf(
					'Fixed %d field(s) across %d trainee(s)',
					$fixed_count,
					$person_count
				)
			);
		}
	}
}

// Register command if WP-CLI is available
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'formapress fix-trainee-fields', 'FormaPress_Fix_Trainee_Fields_Command' );
}
