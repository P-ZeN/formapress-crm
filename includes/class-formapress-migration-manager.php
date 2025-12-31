<?php
/**
 * FormaPress Migration Manager
 *
 * Handles migration from v1 fragmented person systems to v2 unified structure.
 * Provides utilities for progress tracking, error handling, and rollback.
 *
 * @package FormaPress_CRM
 * @subpackage Migration
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FormaPress_Migration_Manager {

	/**
	 * Migration option keys
	 */
	const OPTION_MIGRATION_STATUS = 'formapress_migration_status';
	const OPTION_MIGRATION_LOG    = 'formapress_migration_log';

	/**
	 * Initialize migration manager
	 */
	public static function init() {
		// Register WP-CLI commands if WP-CLI is available.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'formapress migrate-schemas', array( __CLASS__, 'cli_migrate_schemas' ) );
			WP_CLI::add_command( 'formapress migrate', array( __CLASS__, 'cli_migrate' ) );
			WP_CLI::add_command( 'formapress migrate-v2', array( __CLASS__, 'cli_migrate_v2' ) );
			WP_CLI::add_command( 'formapress migrate-status', array( __CLASS__, 'cli_status' ) );
			WP_CLI::add_command( 'formapress migrate-rollback', array( __CLASS__, 'cli_rollback' ) );
		}
	}

	/**
	 * WP-CLI: Main migration command
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : Type of migration to run: instructors, companies, trainees, or all
	 *
	 * [--dry-run]
	 * : Run migration without making changes
	 *
	 * [--limit=<number>]
	 * : Limit number of records to migrate
	 *
	 * [--force]
	 * : Force re-migration even if already completed
	 *
	 * ## EXAMPLES
	 *
	 *     wp formapress migrate instructors --dry-run
	 *     wp formapress migrate companies --limit=10
	 *     wp formapress migrate trainees
	 *     wp formapress migrate all
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function cli_migrate( $args, $assoc_args ) {
		$type    = $args[0] ?? 'all';
		$dry_run = isset( $assoc_args['dry-run'] );
		$limit   = isset( $assoc_args['limit'] ) ? intval( $assoc_args['limit'] ) : null;
		$force   = isset( $assoc_args['force'] );

		if ( $dry_run ) {
			WP_CLI::warning( 'DRY RUN MODE - No changes will be made' );
		}

		switch ( $type ) {
			case 'instructors':
				self::migrate_instructors( $dry_run, $limit, $force );
				break;

			case 'companies':
				self::migrate_companies( $dry_run, $limit, $force );
				break;

			case 'trainees':
				self::migrate_trainees( $dry_run, $limit, $force );
				break;

			case 'all':
				WP_CLI::log( 'Running complete migration...' );
				self::migrate_instructors( $dry_run, $limit, $force );
				self::migrate_companies( $dry_run, $limit, $force );
				self::migrate_trainees( $dry_run, $limit, $force );
				WP_CLI::success( 'Complete migration finished!' );
				break;

			default:
				WP_CLI::error( "Invalid migration type: {$type}. Use: instructors, companies, trainees, or all" );
		}
	}

	/**
	 * WP-CLI: Unified v2 migration command
	 *
	 * Runs complete migration in correct sequence:
	 * 1. Person migrations (instructors → companies → trainees)
	 * 2. Token migration (trainee, instructor, referent)
	 * 3. Token linking (trainee → instructor)
	 * 4. Verification
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Run migration without making changes
	 *
	 * [--force]
	 * : Force re-migration even if already completed
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview complete migration
	 *     wp formapress migrate-v2 --dry-run
	 *
	 *     # Run complete migration on production
	 *     wp formapress migrate-v2 --force
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function cli_migrate_v2( $args, $assoc_args ) {
		$dry_run = isset( $assoc_args['dry-run'] );
		$force   = isset( $assoc_args['force'] );

		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%Y╔═══════════════════════════════════════════════════════════╗%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%Y║        FormaPress v1 → v2 Complete Migration             ║%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%Y╚═══════════════════════════════════════════════════════════╝%n' ) );
		WP_CLI::line( '' );

		if ( $dry_run ) {
			WP_CLI::warning( '🔍 DRY RUN MODE - No changes will be made' );
			WP_CLI::line( '' );
		}

		if ( $force ) {
			WP_CLI::warning( '⚠️  FORCE MODE - Will re-migrate existing data' );
			WP_CLI::line( '' );
		}

		$start_time = microtime( true );
		$steps      = array();

		// Step 1: Migrate persons.
		WP_CLI::line( WP_CLI::colorize( '%B▶ Step 1/5: Person Migration%n' ) );
		WP_CLI::line( '' );

		$step_start = microtime( true );
		self::migrate_instructors( $dry_run, null, $force );
		self::migrate_companies( $dry_run, null, $force );
		self::migrate_trainees( $dry_run, null, $force );
		$steps['persons'] = microtime( true ) - $step_start;

		WP_CLI::line( '' );
		WP_CLI::success( sprintf( 'Person migration completed in %.2fs', $steps['persons'] ) );
		WP_CLI::line( '' );

		// Step 2: Migrate tokens.
		WP_CLI::line( WP_CLI::colorize( '%B▶ Step 2/5: Token Migration%n' ) );
		WP_CLI::line( '' );

		if ( ! class_exists( 'FormaPress_Token_Migration' ) ) {
			WP_CLI::error( 'FormaPress_Token_Migration class not found. Make sure zformations plugin is active.' );
			return;
		}

		$step_start = microtime( true );
		$token_args = array(
			'dry_run' => $dry_run,
			'force'   => $force,
			'verbose' => true,
		);

		$token_results = array(
			'trainee'    => FormaPress_Token_Migration::migrate_trainee_tokens( $token_args ),
			'instructor' => FormaPress_Token_Migration::migrate_instructor_tokens( $token_args ),
			'referent'   => FormaPress_Token_Migration::migrate_referent_tokens( $token_args ),
		);

		$total_tokens = $token_results['trainee']['migrated']
			+ $token_results['instructor']['migrated']
			+ $token_results['referent']['migrated'];

		$steps['tokens'] = microtime( true ) - $step_start;

		WP_CLI::line( '' );
		WP_CLI::success( sprintf( 'Token migration completed: %d tokens in %.2fs', $total_tokens, $steps['tokens'] ) );
		WP_CLI::line( '' );

		// Step 3: Link trainee tokens.
		WP_CLI::line( WP_CLI::colorize( '%B▶ Step 3/5: Link Trainee Tokens%n' ) );
		WP_CLI::line( '' );

		$step_start             = microtime( true );
		$trainee_link_result    = FormaPress_Token_Migration::update_trainee_tokens_to_v2_persons(
			array(
				'dry_run' => $dry_run,
				'verbose' => true,
			)
		);
		$steps['trainee_links'] = microtime( true ) - $step_start;

		WP_CLI::line( '' );
		WP_CLI::success(
			sprintf(
				'Trainee token linking completed: %d linked in %.2fs',
				$trainee_link_result['updated'],
				$steps['trainee_links']
			)
		);
		WP_CLI::line( '' );

		// Step 4: Link instructor tokens.
		WP_CLI::line( WP_CLI::colorize( '%B▶ Step 4/5: Link Instructor Tokens%n' ) );
		WP_CLI::line( '' );

		$step_start                = microtime( true );
		$instructor_link_result    = FormaPress_Token_Migration::update_instructor_tokens_to_v2_persons(
			array(
				'dry_run' => $dry_run,
				'verbose' => true,
			)
		);
		$steps['instructor_links'] = microtime( true ) - $step_start;

		WP_CLI::line( '' );
		WP_CLI::success(
			sprintf(
				'Instructor token linking completed: %d linked in %.2fs',
				$instructor_link_result['updated'],
				$steps['instructor_links']
			)
		);
		WP_CLI::line( '' );

		// Step 5: Verification.
		WP_CLI::line( WP_CLI::colorize( '%B▶ Step 5/5: Verification%n' ) );
		WP_CLI::line( '' );

		$step_start            = microtime( true );
		$verify_results        = FormaPress_Token_Migration::verify_migration();
		$steps['verification'] = microtime( true ) - $step_start;

		foreach ( $verify_results['log'] as $log_line ) {
			if ( strpos( $log_line, 'WARNING' ) !== false ) {
				WP_CLI::warning( $log_line );
			} else {
				WP_CLI::line( $log_line );
			}
		}

		WP_CLI::line( '' );
		if ( $verify_results['duplicates'] > 0 ) {
			WP_CLI::error( 'Verification failed - duplicate tokens found!' );
			return;
		}

		WP_CLI::success( sprintf( 'Verification completed in %.2fs', $steps['verification'] ) );

		// Final summary.
		$total_time = microtime( true ) - $start_time;

		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%G╔═══════════════════════════════════════════════════════════╗%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%G║                  Migration Complete! ✓                    ║%n' ) );
		WP_CLI::line( WP_CLI::colorize( '%G╚═══════════════════════════════════════════════════════════╝%n' ) );
		WP_CLI::line( '' );
		WP_CLI::line( 'Summary:' );
		WP_CLI::line( sprintf( '  • Persons migrated: instructors + companies + trainees' ) );
		WP_CLI::line( sprintf( '  • Tokens migrated: %d', $total_tokens ) );
		WP_CLI::line(
			sprintf(
				'  • Tokens linked: %d trainee + %d instructor',
				$trainee_link_result['updated'],
				$instructor_link_result['updated']
			)
		);
		WP_CLI::line( '' );
		WP_CLI::line( sprintf( 'Total time: %.2fs', $total_time ) );
		WP_CLI::line( '' );

		if ( $dry_run ) {
			WP_CLI::line( WP_CLI::colorize( '%YRun without --dry-run to perform actual migration%n' ) );
		} else {
			WP_CLI::line( WP_CLI::colorize( '%G🎉 Migration successful! Your v2 system is ready.%n' ) );
		}

		WP_CLI::line( '' );
	}

	/**
	 * WP-CLI: Show migration status
	 *
	 * ## EXAMPLES
	 *
	 *     wp formapress migrate-status
	 */
	public static function cli_status( $args, $assoc_args ) {
		$status = self::get_migration_status();

		WP_CLI::log( '=== FORMAPRESS MIGRATION STATUS ===' );
		WP_CLI::log( '' );

		foreach ( $status as $type => $info ) {
			$icon   = $info['completed'] ? '✓' : ( $info['in_progress'] ? '⏳' : '○' );
			$status = $info['completed'] ? 'Completed' : ( $info['in_progress'] ? 'In Progress' : 'Not Started' );

			WP_CLI::log( "{$icon} {$type}: {$status}" );
			WP_CLI::log( "   Total: {$info['total']} | Migrated: {$info['migrated']} | Errors: {$info['errors']}" );

			if ( ! empty( $info['last_run'] ) ) {
				WP_CLI::log( "   Last run: {$info['last_run']}" );
			}

			WP_CLI::log( '' );
		}

		// Show recent errors.
		$log = self::get_migration_log( 10 );
		if ( ! empty( $log ) ) {
			WP_CLI::log( '=== RECENT ERRORS ===' );
			foreach ( $log as $entry ) {
				if ( 'error' === $entry['level'] ) {
					WP_CLI::log( "[{$entry['timestamp']}] {$entry['type']}: {$entry['message']}" );
				}
			}
		}
	}

	/**
	 * WP-CLI: Rollback migration
	 *
	 * ## OPTIONS
	 *
	 * <type>
	 * : Type of migration to rollback: instructors, companies, trainees, or all
	 *
	 * [--confirm]
	 * : Confirm rollback action
	 *
	 * ## EXAMPLES
	 *
	 *     wp formapress migrate-rollback instructors --confirm
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public static function cli_rollback( $args, $assoc_args ) {
		$type = $args[0] ?? 'all';

		if ( ! isset( $assoc_args['confirm'] ) ) {
			WP_CLI::error( 'Rollback requires --confirm flag. This will delete migrated v2 records!' );
			return;
		}

		WP_CLI::warning( 'Rolling back migration - this will delete v2 records!' );

		switch ( $type ) {
			case 'instructors':
				self::rollback_instructors();
				break;

			case 'companies':
				self::rollback_companies();
				break;

			case 'trainees':
				self::rollback_trainees();
				break;

			case 'all':
				self::rollback_instructors();
				self::rollback_companies();
				self::rollback_trainees();
				break;

			default:
				WP_CLI::error( "Invalid rollback type: {$type}" );
		}
	}

	/**
	 * Migrate instructors from zform_instructor to crm_person
	 *
	 * @param bool $dry_run Whether to run in dry-run mode.
	 * @param int  $limit   Limit number of records.
	 * @param bool $force   Force re-migration.
	 */
	public static function migrate_instructors( $dry_run = false, $limit = null, $force = false ) {
		WP_CLI::log( '=== Migrating Instructors ===' );

		// Check if already completed.
		$status = self::get_migration_status();
		if ( ! $force && $status['instructors']['completed'] ) {
			WP_CLI::warning( 'Instructors migration already completed. Use --force to re-run.' );
			return;
		}

		// Get all v1 instructors.
		$args = array(
			'post_type'      => 'zform_instructor',
			'posts_per_page' => $limit ?? -1,
			'post_status'    => 'any',
		);

		$instructors = get_posts( $args );
		$total       = count( $instructors );

		WP_CLI::log( "Found {$total} instructors to migrate" );

		if ( $dry_run ) {
			WP_CLI::log( 'DRY RUN - Showing what would be migrated:' );
		}

		$migrated = 0;
		$errors   = 0;
		$progress = \WP_CLI\Utils\make_progress_bar( 'Migrating instructors', $total );

		foreach ( $instructors as $instructor ) {
			try {
				if ( ! $dry_run ) {
					// Get v1 data.
					$v1_data = FormaPress_Person_Manager::get_v1_instructor( $instructor->ID );

					// Check if already migrated by v1 ID.
					$existing = get_posts(
						array(
							'post_type'  => 'crm_person',
							'meta_key'   => '_crm_v1_instructor_id',
							'meta_value' => $instructor->ID,
							'fields'     => 'ids',
						)
					);

					// Also check by email if not found by v1 ID.
					if ( empty( $existing ) && ! empty( $v1_data['email'] ) ) {
						$existing_by_email = FormaPress_Person_Manager::find_by_email( $v1_data['email'] );
						if ( $existing_by_email ) {
							$existing = array( $existing_by_email );
						}
					}

					if ( ! empty( $existing ) && ! $force ) {
						$progress->tick();
						continue;
					}               // Extract civilite from attributes (if present).
					$civilite = '';
					if ( ! empty( $v1_data['attributes']['civilite'] ) ) {
						$civilite = $v1_data['attributes']['civilite'];
					}

					// If person exists and --force, update it. Otherwise create new.
					if ( ! empty( $existing ) && $force ) {
						$person_id = $existing[0];
						// Update core person data.
						wp_update_post(
							array(
								'ID'         => $person_id,
								'post_title' => ( $v1_data['prenom'] ?? '' ) . ' ' . strtoupper( $v1_data['nom'] ?? '' ),
							)
						);
						update_post_meta( $person_id, '_crm_civilite', $civilite );
						update_post_meta( $person_id, '_crm_prenom', $v1_data['prenom'] ?? '' );
						update_post_meta( $person_id, '_crm_nom', $v1_data['nom'] ?? '' );
						update_post_meta( $person_id, '_crm_email', $v1_data['email'] ?? '' );
						update_post_meta( $person_id, '_crm_telephone', $v1_data['telephone'] ?? '' );
					} else {
						// Create v2 person.
						$person_id = FormaPress_Person_Manager::create_person(
							array(
								'civilite'    => $civilite,
								'prenom'      => $v1_data['prenom'] ?? '',
								'nom'         => $v1_data['nom'] ?? '',
								'email'       => $v1_data['email'] ?? '',
								'telephone'   => $v1_data['telephone'] ?? '',
								'person_type' => 'instructor',
							)
						);

						if ( is_wp_error( $person_id ) ) {
							throw new Exception( $person_id->get_error_message() );
						}
					}

					// Store v1 reference.
					update_post_meta( $person_id, '_crm_v1_instructor_id', $instructor->ID );

					// Migrate sessions_ids relationship.
					$sessions_ids = get_post_meta( $instructor->ID, 'sessions_ids', true );
					if ( ! empty( $sessions_ids ) && is_array( $sessions_ids ) ) {
						update_post_meta( $person_id, '_crm_session_ids', $sessions_ids );
						update_post_meta( $person_id, '_crm_v1_sessions_ids', $sessions_ids ); // Keep for reference.
					}

					// Week 4: Save ALL custom attributes using ATTRIBUTE SYSTEM (not core attributes).
					// Use proper v2 attribute meta keys: crm_person_instructor_attributes_*.
					if ( ! empty( $v1_data['attributes'] ) && is_array( $v1_data['attributes'] ) ) {
						$instructor_schema = get_option( 'crm_person_instructor_attributes', array() );
						foreach ( $v1_data['attributes'] as $field_key => $field_value ) {
							if ( ! empty( $field_value ) || '0' === $field_value ) {
								$meta_key = 'crm_person_instructor_attributes_' . sanitize_key( $field_key );
								update_post_meta( $person_id, $meta_key, $field_value );
							}
						}
					}                   ++$migrated;
				} else {
					WP_CLI::log( "  Would migrate: {$instructor->post_title} (ID: {$instructor->ID})" );
					++$migrated;
				}
			} catch ( Exception $e ) {
				++$errors;
				self::log_migration_error( 'instructors', $instructor->ID, $e->getMessage() );
				WP_CLI::warning( "Error migrating instructor {$instructor->ID}: {$e->getMessage()}" );
			}

			$progress->tick();
		}

		$progress->finish();

		// Update status.
		if ( ! $dry_run ) {
			self::update_migration_status( 'instructors', $total, $migrated, $errors, true );
		}

		WP_CLI::success( "Migrated {$migrated} instructors ({$errors} errors)" );
	}

	/**
	 * Migrate companies and extract referents
	 *
	 * @param bool $dry_run Whether to run in dry-run mode.
	 * @param int  $limit   Limit number of records.
	 * @param bool $force   Force re-migration.
	 */
	public static function migrate_companies( $dry_run = false, $limit = null, $force = false ) {
		WP_CLI::log( '=== Migrating Companies & Extracting Referents ===' );

		// Check if already completed.
		$status = self::get_migration_status();
		if ( ! $force && $status['companies']['completed'] ) {
			WP_CLI::warning( 'Companies migration already completed. Use --force to re-run.' );
			return;
		}

		// Get all v1 companies.
		$args = array(
			'post_type'      => 'zqpm_entreprise',
			'posts_per_page' => $limit ?? -1,
			'post_status'    => 'any',
		);

		$companies = get_posts( $args );
		$total     = count( $companies );

		WP_CLI::log( "Found {$total} companies to migrate" );

		if ( $dry_run ) {
			WP_CLI::log( 'DRY RUN - Showing what would be migrated:' );
		}

		$migrated         = 0;
		$errors           = 0;
		$referents_total  = 0;
		$referents_errors = 0;
		$progress         = \WP_CLI\Utils\make_progress_bar( 'Migrating companies', $total );

		foreach ( $companies as $company ) {
			try {
				if ( ! $dry_run ) {
					// Check if already migrated.
					$existing = get_posts(
						array(
							'post_type'  => 'crm_company',
							'meta_key'   => '_crm_v1_entreprise_id',
							'meta_value' => $company->ID,
							'fields'     => 'ids',
						)
					);

					if ( ! empty( $existing ) && ! $force ) {
						$progress->tick();
						continue;
					}

					// Migrate company and extract referents.
					$result = FormaPress_Company_Manager::migrate_v1_entreprise( $company->ID );

					if ( is_wp_error( $result ) ) {
						throw new Exception( $result->get_error_message() );
					}

					++$migrated;
					$referents_total  += count( $result['referents'] );
					$referents_errors += count( $result['errors'] );

					if ( ! empty( $result['errors'] ) ) {
						foreach ( $result['errors'] as $error ) {
							WP_CLI::warning( "  Referent error: {$error}" );
						}
					}
				} else {
					// Get referent count for dry-run.
					$referents = get_post_meta( $company->ID, 'referent', true );
					$ref_count = is_array( $referents ) ? count( $referents ) : 0;
					WP_CLI::log( "  Would migrate: {$company->post_title} (ID: {$company->ID}, {$ref_count} referents)" );
					++$migrated;
					$referents_total += $ref_count;
				}
			} catch ( Exception $e ) {
				++$errors;
				self::log_migration_error( 'companies', $company->ID, $e->getMessage() );
				WP_CLI::warning( "Error migrating company {$company->ID}: {$e->getMessage()}" );
			}

			$progress->tick();
		}

		$progress->finish();

		// Update status.
		if ( ! $dry_run ) {
			self::update_migration_status( 'companies', $total, $migrated, $errors, true );
		}

		WP_CLI::success( "Migrated {$migrated} companies with {$referents_total} referents ({$errors} company errors, {$referents_errors} referent errors)" );
	}

	/**
	 * Migrate trainees from custom table
	 *
	 * @param bool $dry_run Whether to run in dry-run mode.
	 * @param int  $limit   Limit number of records.
	 * @param bool $force   Force re-migration.
	 */
	public static function migrate_trainees( $dry_run = false, $limit = null, $force = false ) {
		WP_CLI::log( '=== Migrating Trainees ===' );

		// Check if already completed.
		$status = self::get_migration_status();
		if ( ! $force && $status['trainees']['completed'] ) {
			WP_CLI::warning( 'Trainees migration already completed. Use --force to re-run.' );
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'zform_registrations';

		// Get total count.
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

		WP_CLI::log( "Found {$total} trainees to migrate" );

		if ( $dry_run ) {
			WP_CLI::log( 'DRY RUN - Showing what would be migrated:' );
		}

		// Track persons created/reused in THIS migration run to avoid duplicates within same batch.
		$person_by_email = array();

		// Get registrations in batches.
		$batch_size = 100;
		$offset     = 0;
		$migrated   = 0;
		$errors     = 0;
		$progress   = \WP_CLI\Utils\make_progress_bar( 'Migrating trainees', $limit ?? $total );        while ( true ) {
			$batch_limit = $limit ? min( $batch_size, $limit - $migrated ) : $batch_size;
			if ( $batch_limit <= 0 ) {
				break;
			}

			$registrations = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} ORDER BY id LIMIT %d OFFSET %d",
					$batch_limit,
					$offset
				)
			);

			if ( empty( $registrations ) ) {
				break;
			}

			foreach ( $registrations as $reg ) {
				try {
					if ( ! $dry_run ) {
						// Get v1 data.
						$v1_data = FormaPress_Person_Manager::get_v1_registration_person( $reg->id );

						// Check if already migrated.
						$existing = get_posts(
							array(
								'post_type'  => 'crm_person',
								'meta_key'   => '_crm_v1_registration_id',
								'meta_value' => $reg->id,
								'fields'     => 'ids',
							)
						);

						if ( ! empty( $existing ) && ! $force ) {
							$progress->tick();
							++$migrated;
							continue;
						}

						// Map v1 civilite values to v2 format.
						$civilite_map = array(
							'Madame'   => 'Mme',
							'Monsieur' => 'M.',
							'Mme'      => 'Mme',
							'Mr'       => 'M.',
							'M.'       => 'M.',
							'Mlle'     => 'Mlle',
						);
						$v1_civilite  = $v1_data['civilite'] ?? '';
						$civilite     = isset( $civilite_map[ $v1_civilite ] ) ? $civilite_map[ $v1_civilite ] : $v1_civilite;

						// Check if person exists by email (same trainee in multiple sessions).
						// First check in-memory cache from THIS migration run.
						$email_key = sanitize_email( $v1_data['email'] );
						if ( isset( $person_by_email[ $email_key ] ) ) {
							$existing_person_id = $person_by_email[ $email_key ];
						} else {
							// Check database for existing person.
							$existing_person_id = FormaPress_Person_Manager::find_by_email( $v1_data['email'] );
						}

						if ( $existing_person_id ) {
							// Person exists - reuse them for this session.
							$person_id = $existing_person_id;
							// Cache in memory for subsequent registrations.
							$person_by_email[ $email_key ] = $person_id;                            // Add trainee type if not already set.
							$existing_types                = wp_get_post_terms( $person_id, 'person_type', array( 'fields' => 'slugs' ) );
							if ( is_wp_error( $existing_types ) ) {
								$existing_types = array();
							}
							if ( ! in_array( 'trainee', $existing_types, true ) ) {
								$existing_types[] = 'trainee';
								wp_set_object_terms( $person_id, $existing_types, 'person_type' );
							}                           // Update civilite if we have a better value.
							if ( ! empty( $civilite ) ) {
								update_post_meta( $person_id, '_crm_civilite', $civilite );
							}
						} else {
							// Create new person.
							$person_id = FormaPress_Person_Manager::create_person(
								array(
									'prenom'      => $v1_data['prenom'] ?? '',
									'nom'         => $v1_data['nom'] ?? '',
									'email'       => $v1_data['email'] ?? '',
									'telephone'   => $v1_data['telephone'] ?? '',
									'civilite'    => $civilite,
									'person_type' => 'trainee',
								)
							);

							if ( is_wp_error( $person_id ) ) {
								throw new Exception( $person_id->get_error_message() );
							}

							// Cache newly created person for subsequent registrations with same email.
							$person_by_email[ $email_key ] = $person_id;
						}                       // Store v1 session/registration links (allow multiple).
						add_post_meta( $person_id, '_crm_v1_registration_id', $reg->id, false );
						add_post_meta( $person_id, '_crm_v1_formation_id', $reg->formation_id, false );
						add_post_meta( $person_id, '_crm_v1_session_id', $reg->session_id, false );
						add_post_meta( $person_id, '_crm_v1_zqpm_id', $v1_data['zqpm_id'], false );

						// Save attributes using v2 schema: crm_person_trainee_attributes_*.
						// Include BOTH core fields AND custom fields for meta box display.
						$trainee_schema = get_option( 'crm_person_trainee_attributes', array() );

						// Build complete attribute data starting with CORE person fields from v1_data root.
						$attribute_data = array(
							'civilite' => $civilite,
							'nom'      => $v1_data['nom'] ?? '',
							'prenom'   => $v1_data['prenom'] ?? '',
							'email'    => $v1_data['email'] ?? '',
							'tel'      => $v1_data['telephone'] ?? '',
						);

						// Add ADDITIONAL custom fields from v1 registration attributes (adresse, cp, ville, etc.).
						// Only add fields that are NOT core fields (to avoid overwriting with empty values).
						if ( ! empty( $v1_data['attributes'] ) && is_array( $v1_data['attributes'] ) ) {
							$core_fields = array( 'name', 'firstname', 'mail', 'telephone', 'civilite' );

							foreach ( $v1_data['attributes'] as $v1_key => $field_value ) {
								// Skip core fields - they're already set from v1_data root level.
								if ( in_array( $v1_key, $core_fields, true ) ) {
									continue;
								}

								// Use v1 key directly for non-core fields (adresse, cp, ville, societe, etc.).
								$attribute_data[ $v1_key ] = $field_value;
							}
						}

						// Save all attributes to individual meta keys.
						foreach ( $attribute_data as $field_key => $field_value ) {
							if ( isset( $trainee_schema[ $field_key ] ) ) {
								$meta_key = 'crm_person_trainee_attributes_' . sanitize_key( $field_key );
								update_post_meta( $person_id, $meta_key, $field_value );
							}
						}

						++$migrated;

						// Link to company if societe value exists (handles both numeric IDs and text names).
						if ( ! empty( $v1_data['societe'] ) ) {
							$company_id = self::find_company_by_v1_societe( $v1_data['societe'] );

							if ( $company_id ) {
								update_post_meta( $person_id, '_crm_company_id', $company_id );
							}
						}
					} else {
						++$migrated;
					}
				} catch ( Exception $e ) {
					++$errors;
					self::log_migration_error( 'trainees', $reg->id, $e->getMessage() );
				}

				$progress->tick();
			}

			$offset += $batch_size;

			if ( $limit && $migrated >= $limit ) {
				break;
			}
		}

		$progress->finish();

		// Update status.
		if ( ! $dry_run ) {
			self::update_migration_status( 'trainees', $total, $migrated, $errors, true );
		}

		WP_CLI::success( "Migrated {$migrated} trainees ({$errors} errors)" );
	}

	/**
	 * Rollback instructors migration
	 */
	private static function rollback_instructors() {
		WP_CLI::log( 'Rolling back instructors...' );

		$persons = get_posts(
			array(
				'post_type'      => 'crm_person',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => '_crm_v1_instructor_id',
						'compare' => 'EXISTS',
					),
				),
				'fields'         => 'ids',
			)
		);

		foreach ( $persons as $person_id ) {
			wp_delete_post( $person_id, true );
		}

		self::clear_migration_status( 'instructors' );

		WP_CLI::success( 'Rolled back ' . count( $persons ) . ' instructors' );
	}

	/**
	 * Rollback companies migration
	 */
	private static function rollback_companies() {
		WP_CLI::log( 'Rolling back companies and referents...' );

		// Delete companies.
		$companies = get_posts(
			array(
				'post_type'      => 'crm_company',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => '_crm_v1_entreprise_id',
						'compare' => 'EXISTS',
					),
				),
				'fields'         => 'ids',
			)
		);

		foreach ( $companies as $company_id ) {
			wp_delete_post( $company_id, true );
		}

		// Delete referents.
		$referents = get_posts(
			array(
				'post_type'      => 'crm_person',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => '_crm_v1_referent_index',
						'compare' => 'EXISTS',
					),
				),
				'fields'         => 'ids',
			)
		);

		foreach ( $referents as $person_id ) {
			wp_delete_post( $person_id, true );
		}

		self::clear_migration_status( 'companies' );

		WP_CLI::success( 'Rolled back ' . count( $companies ) . ' companies and ' . count( $referents ) . ' referents' );
	}

	/**
	 * Rollback trainees migration
	 */
	private static function rollback_trainees() {
		WP_CLI::log( 'Rolling back trainees...' );

		$persons = get_posts(
			array(
				'post_type'      => 'crm_person',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => '_crm_v1_registration_id',
						'compare' => 'EXISTS',
					),
				),
				'fields'         => 'ids',
			)
		);

		foreach ( $persons as $person_id ) {
			wp_delete_post( $person_id, true );
		}

		self::clear_migration_status( 'trainees' );

		WP_CLI::success( 'Rolled back ' . count( $persons ) . ' trainees' );
	}

	/**
	 * Get migration status
	 *
	 * @return array Migration status for all types.
	 */
	public static function get_migration_status() {
		$default_status = array(
			'instructors' => array(
				'total'       => 0,
				'migrated'    => 0,
				'errors'      => 0,
				'completed'   => false,
				'in_progress' => false,
				'last_run'    => null,
			),
			'companies'   => array(
				'total'       => 0,
				'migrated'    => 0,
				'errors'      => 0,
				'completed'   => false,
				'in_progress' => false,
				'last_run'    => null,
			),
			'trainees'    => array(
				'total'       => 0,
				'migrated'    => 0,
				'errors'      => 0,
				'completed'   => false,
				'in_progress' => false,
				'last_run'    => null,
			),
		);

		$status = get_option( self::OPTION_MIGRATION_STATUS, $default_status );

		return wp_parse_args( $status, $default_status );
	}

	/**
	 * Update migration status
	 *
	 * @param string $type      Migration type.
	 * @param int    $total     Total records.
	 * @param int    $migrated  Migrated records.
	 * @param int    $errors    Error count.
	 * @param bool   $completed Whether completed.
	 */
	private static function update_migration_status( $type, $total, $migrated, $errors, $completed = false ) {
		$status = self::get_migration_status();

		$status[ $type ] = array(
			'total'       => $total,
			'migrated'    => $migrated,
			'errors'      => $errors,
			'completed'   => $completed,
			'in_progress' => ! $completed,
			'last_run'    => current_time( 'mysql' ),
		);

		update_option( self::OPTION_MIGRATION_STATUS, $status );
	}

	/**
	 * Clear migration status for a type
	 *
	 * @param string $type Migration type.
	 */
	private static function clear_migration_status( $type ) {
		$status = self::get_migration_status();

		$status[ $type ] = array(
			'total'       => 0,
			'migrated'    => 0,
			'errors'      => 0,
			'completed'   => false,
			'in_progress' => false,
			'last_run'    => null,
		);

		update_option( self::OPTION_MIGRATION_STATUS, $status );
	}

	/**
	 * Log migration error
	 *
	 * @param string $type    Migration type.
	 * @param int    $item_id Item ID.
	 * @param string $message Error message.
	 */
	private static function log_migration_error( $type, $item_id, $message ) {
		$log = get_option( self::OPTION_MIGRATION_LOG, array() );

		$log[] = array(
			'timestamp' => current_time( 'mysql' ),
			'type'      => $type,
			'item_id'   => $item_id,
			'level'     => 'error',
			'message'   => $message,
		);

		// Keep only last 1000 entries.
		if ( count( $log ) > 1000 ) {
			$log = array_slice( $log, -1000 );
		}

		update_option( self::OPTION_MIGRATION_LOG, $log );
	}

	/**
	 * Get migration log
	 *
	 * @param int $limit Number of entries to retrieve.
	 * @return array Log entries.
	 */
	public static function get_migration_log( $limit = 100 ) {
		$log = get_option( self::OPTION_MIGRATION_LOG, array() );

		return array_slice( $log, -$limit );
	}

	/**
	 * Find company by v1 societe value (handles both numeric IDs and text names)
	 *
	 * The v1 'societe' field is dirty data - can be either:
	 * 1. Numeric string (zqpm_entreprise post ID) - clean data
	 * 2. Company name text - from bad CSV imports
	 *
	 * @param string $societe_value Value from v1 registration data.
	 * @return int|null Company post ID or null if not found.
	 */
	private static function find_company_by_v1_societe( $societe_value ) {
		if ( empty( $societe_value ) ) {
			return null;
		}

		global $wpdb;

		// Strategy 1: If numeric, look up by v1 entreprise ID (clean data).
		if ( is_numeric( $societe_value ) ) {
			$company_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT pm.post_id
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					WHERE pm.meta_key = '_crm_v1_entreprise_id'
					AND pm.meta_value = %s
					AND p.post_type = 'crm_company'
					LIMIT 1",
					$societe_value
				)
			);

			if ( $company_id ) {
				return (int) $company_id;
			}
		}       // Strategy 2: Text search by company name (dirty data from CSV imports).
		// Try exact match first (case-insensitive).
		$company = get_posts(
			array(
				'post_type'      => 'crm_company',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'title'          => $societe_value,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $company ) ) {
			return (int) $company[0];
		}

		// Strategy 3: Fuzzy match - search in title (for variations).
		$company = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'crm_company'
				AND post_status = 'publish'
				AND LOWER(post_title) = LOWER(%s)
				LIMIT 1",
				$societe_value
			)
		);

		if ( $company ) {
			return (int) $company;
		}

		// Not found - log for manual review.
		error_log(
			sprintf(
				'FormaPress Migration: Company not found for societe value "%s"',
				$societe_value
			)
		);

		return null;
	}

	/**
	 * WP-CLI: Migrate attribute schemas from v1 to v2
	 *
	 * Copies v1 dynamic attribute configurations to v2 options for each entity type.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would be migrated without making changes
	 *
	 * ## EXAMPLES
	 *
	 *     wp formapress migrate-schemas
	 *     wp formapress migrate-schemas --dry-run
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public static function cli_migrate_schemas( $args, $assoc_args ) {
		$dry_run = isset( $assoc_args['dry-run'] );

		if ( $dry_run ) {
			WP_CLI::log( 'DRY RUN MODE - No changes will be made' );
			WP_CLI::log( '' );
		}

		WP_CLI::log( '=== FormaPress Schema Migration ===' );
		WP_CLI::log( '' );

		$migrations = array(
			array(
				'source' => 'zform_registrations',
				'target' => 'crm_person_trainee_attributes',
				'label'  => 'Trainee Attributes (Registration Form Fields)',
			),
			array(
				'source' => 'zform_Instructors_attributes',
				'target' => 'crm_person_instructor_attributes',
				'label'  => 'Instructor Attributes',
			),
			array(
				'source' => 'zform_sessions_attributes',
				'target' => 'crm_session_attributes',
				'label'  => 'Session Attributes',
			),
			array(
				'source' => 'zform_formations_attributes',
				'target' => 'crm_formation_attributes',
				'label'  => 'Formation Attributes',
			),
		);

		$success_count = 0;
		$skip_count    = 0;
		$error_count   = 0;

		foreach ( $migrations as $migration ) {
			WP_CLI::log( WP_CLI::colorize( "%B{$migration['label']}%n" ) );
			WP_CLI::log( "  Source: {$migration['source']}" );
			WP_CLI::log( "  Target: {$migration['target']}" );

			// Check if source exists.
			$source_schema = get_option( $migration['source'] );

			if ( empty( $source_schema ) ) {
				WP_CLI::warning( '  Source schema not found - skipping' );
				++$skip_count;
				WP_CLI::log( '' );
				continue;
			}

			$field_count = is_array( $source_schema ) ? count( $source_schema ) : 0;
			WP_CLI::log( "  Found {$field_count} fields in source schema" );

			// Check if target already exists.
			$target_exists = get_option( $migration['target'] );
			if ( false !== $target_exists ) {
				WP_CLI::warning( '  Target schema already exists - will overwrite' );
			}

			if ( ! $dry_run ) {
				$updated = update_option( $migration['target'], $source_schema );
				if ( $updated || false !== $target_exists ) {
					WP_CLI::success( '  Schema migrated successfully' );
					++$success_count;
				} else {
					WP_CLI::error( '  Failed to update schema', false );
					++$error_count;
				}
			} else {
				WP_CLI::log( '  Would migrate schema (dry-run)' );
				// Show sample of fields.
				if ( is_array( $source_schema ) && ! empty( $source_schema ) ) {
					$sample_fields = array_slice( array_keys( $source_schema ), 0, 3 );
					WP_CLI::log( '  Sample fields: ' . implode( ', ', $sample_fields ) );
				}
			}

			WP_CLI::log( '' );
		}

		// Summary.
		WP_CLI::log( '=== Migration Summary ===' );
		if ( ! $dry_run ) {
			WP_CLI::log( "Successfully migrated: {$success_count}" );
			WP_CLI::log( "Skipped (not found): {$skip_count}" );
			if ( $error_count > 0 ) {
				WP_CLI::log( WP_CLI::colorize( "%RErrors: {$error_count}%n" ) );
			}
		} else {
			WP_CLI::log( 'DRY RUN - No changes made' );
		}

		WP_CLI::log( '' );
		WP_CLI::success( 'Schema migration complete!' );
	}
}

// Initialize.
FormaPress_Migration_Manager::init();
