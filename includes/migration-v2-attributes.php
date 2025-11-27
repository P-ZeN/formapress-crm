<?php
/**
 * FormaPress CRM - v1 to v2 Attribute System Migration
 *
 * Unified migration tool for converting v1 data (direct meta keys) to v2 attribute system.
 * This tool can be run iteratively to refine migration logic as issues are discovered.
 *
 * @deprecated 2.0.0 This file was a transitional tool during attribute system refactoring.
 *             The migration now saves attributes correctly from the start.
 *             Use WP-CLI: `wp formapress migrate companies|instructors|trainees --force`
 *             See class-formapress-migration-manager.php for the refactored migration system.
 *
 * @package FormapressCRM
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Deprecation notice
if ( is_admin() && current_user_can( 'manage_options' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-info"><p><strong>FormaPress CRM:</strong> migration-v2-attributes.php is no longer needed. Migrations save attributes correctly from the start.</p></div>';
		}
	);
}

/**
 * Main migration orchestrator for v1→v2 attribute system
 */
class FormaPress_V2_Attribute_Migration {

	/**
	 * Migration status option key
	 */
	const STATUS_OPTION = 'formapress_v2_attr_migration_status';

	/**
	 * Migration log option key
	 */
	const LOG_OPTION = 'formapress_v2_attr_migration_log';

	/**
	 * Get migration status for all entity types
	 *
	 * @return array Status array with counts and completion flags
	 */
	public static function get_status() {
		$default = array(
			'companies' => array(
				'total'     => 0,
				'migrated'  => 0,
				'errors'    => 0,
				'completed' => false,
				'last_run'  => null,
			),
			'referents' => array(
				'total'     => 0,
				'migrated'  => 0,
				'errors'    => 0,
				'completed' => false,
				'last_run'  => null,
			),
			'funders'   => array(
				'total'     => 0,
				'migrated'  => 0,
				'errors'    => 0,
				'completed' => false,
				'last_run'  => null,
			),
		);

		return get_option( self::STATUS_OPTION, $default );
	}

	/**
	 * Update migration status for an entity type
	 *
	 * @param string $type Entity type (companies, referents, funders).
	 * @param int    $total Total records to migrate.
	 * @param int    $migrated Successfully migrated records.
	 * @param int    $errors Error count.
	 * @param bool   $completed Whether migration is complete.
	 */
	private static function update_status( $type, $total, $migrated, $errors, $completed = false ) {
		$status          = self::get_status();
		$status[ $type ] = array(
			'total'     => $total,
			'migrated'  => $migrated,
			'errors'    => $errors,
			'completed' => $completed,
			'last_run'  => current_time( 'mysql' ),
		);
		update_option( self::STATUS_OPTION, $status );
	}

	/**
	 * Log a migration event
	 *
	 * @param string $type Entity type.
	 * @param string $level Log level (info, warning, error).
	 * @param string $message Log message.
	 * @param int    $entity_id Optional entity ID.
	 */
	private static function log( $type, $level, $message, $entity_id = null ) {
		$log   = get_option( self::LOG_OPTION, array() );
		$log[] = array(
			'timestamp' => current_time( 'mysql' ),
			'type'      => $type,
			'level'     => $level,
			'message'   => $message,
			'entity_id' => $entity_id,
		);

		// Keep last 500 entries only.
		if ( count( $log ) > 500 ) {
			$log = array_slice( $log, -500 );
		}

		update_option( self::LOG_OPTION, $log );
	}

	/**
	 * Get recent log entries
	 *
	 * @param int $limit Number of entries to retrieve.
	 * @return array Log entries.
	 */
	public static function get_log( $limit = 100 ) {
		$log = get_option( self::LOG_OPTION, array() );
		return array_slice( $log, -$limit );
	}

	/**
	 * Migrate companies from v1 direct meta keys to v2 attribute system
	 *
	 * V1 structure (zqpm_entreprise):
	 * - Direct meta keys: raison_sociale, siret, adresse, cp, ville, site
	 *
	 * V2 structure (crm_company):
	 * - Attribute system: crm_company_attributes_raison-sociale, crm_company_attributes_siret, etc.
	 *
	 * @param array $args Migration arguments (limit, force, dry_run).
	 * @return array Migration results.
	 */
	public static function migrate_companies( $args = array() ) {
		$defaults = array(
			'limit'   => -1,
			'force'   => false,
			'dry_run' => false,
		);
		$args     = wp_parse_args( $args, $defaults );

		self::log( 'companies', 'info', 'Starting company migration', null );

		// Get all v1 companies (zqpm_entreprise).
		$v1_companies = get_posts(
			array(
				'post_type'      => 'zqpm_entreprise',
				'posts_per_page' => $args['limit'],
				'post_status'    => 'any',
			)
		);

		$total    = count( $v1_companies );
		$migrated = 0;
		$errors   = 0;
		$skipped  = 0;
		$log      = array();

		$log[] = sprintf( 'Found %d v1 companies (zqpm_entreprise) to migrate', $total );

		// Get attribute schema for field mapping.
		$schema = formapress_get_default_company_attributes();

		foreach ( $v1_companies as $v1_company ) {
			try {
				// Check if already migrated to v2.
				$v2_company = get_posts(
					array(
						'post_type'   => 'crm_company',
						'meta_key'    => '_crm_v1_entreprise_id',
						'meta_value'  => $v1_company->ID,
						'numberposts' => 1,
					)
				);

				if ( empty( $v2_company ) ) {
					$log[] = sprintf( 'ERROR: Company "%s" (ID: %d) not yet migrated to v2. Run v1→v2 CPT migration first.', $v1_company->post_title, $v1_company->ID );
					++$errors;
					self::log( 'companies', 'error', 'Company not migrated to v2 CPT yet', $v1_company->ID );
					continue;
				}

				$v2_company_id = $v2_company[0]->ID;

				// Check if attribute migration already done (unless force=true).
				$migration_flag = get_post_meta( $v2_company_id, '_crm_attr_migration_done', true );
				if ( $migration_flag && ! $args['force'] ) {
					++$skipped;
					continue;
				}

				if ( $args['dry_run'] ) {
					$log[] = sprintf( '[DRY RUN] Would migrate company: %s (v1 ID: %d → v2 ID: %d)', $v1_company->post_title, $v1_company->ID, $v2_company_id );
					++$migrated;
					continue;
				}

				// Migrate each attribute field from v1 to v2 system.
				$fields_migrated = 0;

				foreach ( $schema as $field_key => $field_config ) {
					// Map v1 meta key to v2 attribute key.
					$v1_meta_key = str_replace( '-', '_', $field_key ); // raison-sociale → raison_sociale.
					$v2_meta_key = 'crm_company_attributes_' . $field_key; // → crm_company_attributes_raison-sociale.

					// Get v1 value (from original zqpm_entreprise post).
					$v1_value = get_post_meta( $v1_company->ID, $v1_meta_key, true );

					if ( ! empty( $v1_value ) || '0' === $v1_value ) {
						// Save to v2 attribute system.
						update_post_meta( $v2_company_id, $v2_meta_key, $v1_value );
						++$fields_migrated;
					}
				}

				// Mark migration as complete.
				update_post_meta( $v2_company_id, '_crm_attr_migration_done', current_time( 'mysql' ) );

				$log[] = sprintf( 'Migrated company: %s (%d fields)', $v1_company->post_title, $fields_migrated );
				++$migrated;

				self::log( 'companies', 'info', sprintf( 'Migrated %d fields', $fields_migrated ), $v2_company_id );

			} catch ( Exception $e ) {
				++$errors;
				$log[] = sprintf( 'ERROR: %s (ID: %d) - %s', $v1_company->post_title, $v1_company->ID, $e->getMessage() );
				self::log( 'companies', 'error', $e->getMessage(), $v1_company->ID );
			}
		}

		// Update status.
		if ( ! $args['dry_run'] ) {
			self::update_status( 'companies', $total, $migrated, $errors, ( $migrated + $skipped + $errors ) >= $total );
		}

		return array(
			'success'  => true,
			'total'    => $total,
			'migrated' => $migrated,
			'errors'   => $errors,
			'skipped'  => $skipped,
			'log'      => $log,
		);
	}

	/**
	 * Migrate referents from v1 direct meta keys to v2 attribute system
	 *
	 * V1 structure:
	 * - Serialized array in zqpm_entreprise.referent meta
	 * - Fields: civilite, nom, prenom, poste, tel, mail
	 *
	 * V2 structure:
	 * - crm_person with person_type=referent
	 * - Attribute system: crm_person_referent_attributes_civilite, etc.
	 *
	 * @param array $args Migration arguments.
	 * @return array Migration results.
	 */
	public static function migrate_referents( $args = array() ) {
		$defaults = array(
			'limit'   => -1,
			'force'   => false,
			'dry_run' => false,
		);
		$args     = wp_parse_args( $args, $defaults );

		self::log( 'referents', 'info', 'Starting referent migration', null );

		// Get all v2 referents (already extracted to crm_person).
		$v2_referents = get_posts(
			array(
				'post_type'      => 'crm_person',
				'posts_per_page' => $args['limit'],
				'post_status'    => 'any',
				'tax_query'      => array(
					array(
						'taxonomy' => 'person_type',
						'field'    => 'slug',
						'terms'    => 'referent',
					),
				),
				'meta_query'     => array(
					array(
						'key'     => '_crm_v1_referent_index',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$total    = count( $v2_referents );
		$migrated = 0;
		$errors   = 0;
		$skipped  = 0;
		$log      = array();

		$log[] = sprintf( 'Found %d referents to migrate to attribute system', $total );

		// Get attribute schema.
		$schema = formapress_get_default_referent_attributes();

		foreach ( $v2_referents as $referent ) {
			try {
				// Check if attribute migration already done.
				$migration_flag = get_post_meta( $referent->ID, '_crm_attr_migration_done', true );
				if ( $migration_flag && ! $args['force'] ) {
					++$skipped;
					continue;
				}

				if ( $args['dry_run'] ) {
					$log[] = sprintf( '[DRY RUN] Would migrate referent: %s (ID: %d)', $referent->post_title, $referent->ID );
					++$migrated;
					continue;
				}

				// Get v1 source data (stored during initial extraction).
				$v1_entreprise_id  = get_post_meta( $referent->ID, '_crm_v1_entreprise_id', true );
				$v1_referent_index = get_post_meta( $referent->ID, '_crm_v1_referent_index', true );

				if ( ! $v1_entreprise_id || ! is_numeric( $v1_referent_index ) ) {
					$log[] = sprintf( 'WARNING: Referent %s missing v1 source data', $referent->post_title );
					++$errors;
					continue;
				}

				// Get original v1 data from entreprise.referent array.
				$v1_referents = get_post_meta( $v1_entreprise_id, 'referent', true );
				if ( ! is_array( $v1_referents ) || ! isset( $v1_referents[ $v1_referent_index ] ) ) {
					$log[] = sprintf( 'WARNING: Cannot find v1 source data for referent %s', $referent->post_title );
					++$errors;
					continue;
				}

				$v1_data         = $v1_referents[ $v1_referent_index ];
				$fields_migrated = 0;

				// Migrate each attribute field from v1 to v2 system.
				foreach ( $schema as $field_key => $field_config ) {
					$v2_meta_key = 'crm_person_referent_attributes_' . $field_key;

					// Get v1 value (from serialized referent array).
					$v1_value = isset( $v1_data[ $field_key ] ) ? $v1_data[ $field_key ] : '';

					if ( ! empty( $v1_value ) || '0' === $v1_value ) {
						update_post_meta( $referent->ID, $v2_meta_key, $v1_value );
						++$fields_migrated;
					}
				}

				// Mark migration as complete.
				update_post_meta( $referent->ID, '_crm_attr_migration_done', current_time( 'mysql' ) );

				$log[] = sprintf( 'Migrated referent: %s (%d fields)', $referent->post_title, $fields_migrated );
				++$migrated;

				self::log( 'referents', 'info', sprintf( 'Migrated %d fields', $fields_migrated ), $referent->ID );

			} catch ( Exception $e ) {
				++$errors;
				$log[] = sprintf( 'ERROR: %s (ID: %d) - %s', $referent->post_title, $referent->ID, $e->getMessage() );
				self::log( 'referents', 'error', $e->getMessage(), $referent->ID );
			}
		}

		// Update status.
		if ( ! $args['dry_run'] ) {
			self::update_status( 'referents', $total, $migrated, $errors, ( $migrated + $skipped + $errors ) >= $total );
		}

		return array(
			'success'  => true,
			'total'    => $total,
			'migrated' => $migrated,
			'errors'   => $errors,
			'skipped'  => $skipped,
			'log'      => $log,
		);
	}

	/**
	 * Migrate funders from v1 direct meta keys to v2 attribute system
	 *
	 * V1 structure (zqpm_financeur):
	 * - Direct meta keys: type_financeur
	 *
	 * V2 structure (crm_person with person_type=funder):
	 * - Attribute system: crm_person_funder_attributes_type-financeur
	 *
	 * @param array $args Migration arguments.
	 * @return array Migration results.
	 */
	public static function migrate_funders( $args = array() ) {
		$defaults = array(
			'limit'   => -1,
			'force'   => false,
			'dry_run' => false,
		);
		$args     = wp_parse_args( $args, $defaults );

		self::log( 'funders', 'info', 'Starting funder migration', null );

		// Get all v1 funders.
		$v1_funders = get_posts(
			array(
				'post_type'      => 'zqpm_financeur',
				'posts_per_page' => $args['limit'],
				'post_status'    => 'any',
			)
		);

		$total    = count( $v1_funders );
		$migrated = 0;
		$errors   = 0;
		$skipped  = 0;
		$log      = array();

		$log[] = sprintf( 'Found %d v1 funders (zqpm_financeur) to migrate', $total );

		// Get attribute schema.
		$schema = formapress_get_default_funder_attributes();

		foreach ( $v1_funders as $v1_funder ) {
			try {
				// Check if already migrated to v2.
				$v2_funder = get_posts(
					array(
						'post_type'   => 'crm_person',
						'meta_key'    => '_crm_v1_financeur_id',
						'meta_value'  => $v1_funder->ID,
						'numberposts' => 1,
					)
				);

				if ( empty( $v2_funder ) ) {
					$log[] = sprintf( 'ERROR: Funder "%s" (ID: %d) not yet migrated to v2. Run v1→v2 CPT migration first.', $v1_funder->post_title, $v1_funder->ID );
					++$errors;
					self::log( 'funders', 'error', 'Funder not migrated to v2 CPT yet', $v1_funder->ID );
					continue;
				}

				$v2_funder_id = $v2_funder[0]->ID;

				// Check if attribute migration already done.
				$migration_flag = get_post_meta( $v2_funder_id, '_crm_attr_migration_done', true );
				if ( $migration_flag && ! $args['force'] ) {
					++$skipped;
					continue;
				}

				if ( $args['dry_run'] ) {
					$log[] = sprintf( '[DRY RUN] Would migrate funder: %s (v1 ID: %d → v2 ID: %d)', $v1_funder->post_title, $v1_funder->ID, $v2_funder_id );
					++$migrated;
					continue;
				}

				// Migrate each attribute field.
				$fields_migrated = 0;

				foreach ( $schema as $field_key => $field_config ) {
					$v1_meta_key = str_replace( '-', '_', $field_key );
					$v2_meta_key = 'crm_person_funder_attributes_' . $field_key;

					// Get v1 value.
					$v1_value = get_post_meta( $v1_funder->ID, $v1_meta_key, true );

					if ( ! empty( $v1_value ) || '0' === $v1_value ) {
						update_post_meta( $v2_funder_id, $v2_meta_key, $v1_value );
						++$fields_migrated;
					}
				}

				// Mark migration as complete.
				update_post_meta( $v2_funder_id, '_crm_attr_migration_done', current_time( 'mysql' ) );

				$log[] = sprintf( 'Migrated funder: %s (%d fields)', $v1_funder->post_title, $fields_migrated );
				++$migrated;

				self::log( 'funders', 'info', sprintf( 'Migrated %d fields', $fields_migrated ), $v2_funder_id );

			} catch ( Exception $e ) {
				++$errors;
				$log[] = sprintf( 'ERROR: %s (ID: %d) - %s', $v1_funder->post_title, $v1_funder->ID, $e->getMessage() );
				self::log( 'funders', 'error', $e->getMessage(), $v1_funder->ID );
			}
		}

		// Update status.
		if ( ! $args['dry_run'] ) {
			self::update_status( 'funders', $total, $migrated, $errors, ( $migrated + $skipped + $errors ) >= $total );
		}

		return array(
			'success'  => true,
			'total'    => $total,
			'migrated' => $migrated,
			'errors'   => $errors,
			'skipped'  => $skipped,
			'log'      => $log,
		);
	}

	/**
	 * Migrate all entity types
	 *
	 * @param array $args Migration arguments.
	 * @return array Aggregated results.
	 */
	public static function migrate_all( $args = array() ) {
		$results = array(
			'companies' => self::migrate_companies( $args ),
			'referents' => self::migrate_referents( $args ),
			'funders'   => self::migrate_funders( $args ),
		);

		return array(
			'success' => true,
			'results' => $results,
		);
	}
}

/**
 * AJAX handler for unified v2 attribute migration
 */
function formapress_crm_ajax_v2_attr_migration() {
	check_ajax_referer( 'formapress_crm_v2_migration_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied' ) );
	}

	$entity_type = isset( $_POST['entity_type'] ) ? sanitize_text_field( wp_unslash( $_POST['entity_type'] ) ) : 'all';
	$dry_run     = isset( $_POST['dry_run'] ) && 'true' === $_POST['dry_run'];
	$force       = isset( $_POST['force'] ) && 'true' === $_POST['force'];

	$args = array(
		'dry_run' => $dry_run,
		'force'   => $force,
	);

	switch ( $entity_type ) {
		case 'companies':
			$result = FormaPress_V2_Attribute_Migration::migrate_companies( $args );
			break;
		case 'referents':
			$result = FormaPress_V2_Attribute_Migration::migrate_referents( $args );
			break;
		case 'funders':
			$result = FormaPress_V2_Attribute_Migration::migrate_funders( $args );
			break;
		case 'all':
			$result = FormaPress_V2_Attribute_Migration::migrate_all( $args );
			break;
		default:
			wp_send_json_error( array( 'message' => 'Invalid entity type' ) );
			return;
	}

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
}
add_action( 'wp_ajax_formapress_crm_v2_attr_migration', 'formapress_crm_ajax_v2_attr_migration' );

/**
 * AJAX handler for getting migration status
 */
function formapress_crm_ajax_get_v2_migration_status() {
	check_ajax_referer( 'formapress_crm_v2_migration_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Permission denied' ) );
	}

	$status = FormaPress_V2_Attribute_Migration::get_status();
	$log    = FormaPress_V2_Attribute_Migration::get_log( 50 );

	wp_send_json_success(
		array(
			'status' => $status,
			'log'    => $log,
		)
	);
}
add_action( 'wp_ajax_formapress_crm_get_v2_migration_status', 'formapress_crm_ajax_get_v2_migration_status' );
