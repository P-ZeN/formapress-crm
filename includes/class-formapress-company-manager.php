<?php
/**
 * FormaPress Company Manager
 *
 * Unified company management system for FormaPress v2.
 * Replaces v1 zqpm_entreprise system and extracts embedded referents to crm_person.
 *
 * @package FormaPress
 * @subpackage CRM
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FormaPress_Company_Manager {

	/**
	 * Initialize the Company Manager
	 */
	public static function init() {
		// Register CPT and taxonomy
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 10 );
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ), 11 );

		// Register custom field shortcodes
		add_action( 'init', array( __CLASS__, 'register_custom_field_shortcodes' ), 20 );
	}

	/**
	 * Register crm_company custom post type
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Companies', 'formapress' ),
			'singular_name'      => __( 'Company', 'formapress' ),
			'menu_name'          => __( 'CRM Companies', 'formapress' ),
			'name_admin_bar'     => __( 'Company', 'formapress' ),
			'add_new'            => __( 'Add New', 'formapress' ),
			'add_new_item'       => __( 'Add New Company', 'formapress' ),
			'new_item'           => __( 'New Company', 'formapress' ),
			'edit_item'          => __( 'Edit Company', 'formapress' ),
			'view_item'          => __( 'View Company', 'formapress' ),
			'all_items'          => __( 'All Companies', 'formapress' ),
			'search_items'       => __( 'Search Companies', 'formapress' ),
			'parent_item_colon'  => __( 'Parent Companies:', 'formapress' ),
			'not_found'          => __( 'No companies found.', 'formapress' ),
			'not_found_in_trash' => __( 'No companies found in Trash.', 'formapress' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => 'formapress-crm',
			'show_in_nav_menus'  => false,
			'show_in_admin_bar'  => true,
			'query_var'          => true,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 26,
			'menu_icon'          => 'dashicons-building',
			'supports'           => array( 'title', 'custom-fields' ),
			'show_in_rest'       => true,
		);

		register_post_type( 'crm_company', $args );
	}

	/**
	 * Register company_type taxonomy
	 */
	public static function register_taxonomy() {
		$labels = array(
			'name'                       => __( 'Company Types', 'formapress' ),
			'singular_name'              => __( 'Company Type', 'formapress' ),
			'search_items'               => __( 'Search Company Types', 'formapress' ),
			'popular_items'              => __( 'Popular Company Types', 'formapress' ),
			'all_items'                  => __( 'All Company Types', 'formapress' ),
			'edit_item'                  => __( 'Edit Company Type', 'formapress' ),
			'update_item'                => __( 'Update Company Type', 'formapress' ),
			'add_new_item'               => __( 'Add New Company Type', 'formapress' ),
			'new_item_name'              => __( 'New Company Type Name', 'formapress' ),
			'separate_items_with_commas' => __( 'Separate company types with commas', 'formapress' ),
			'add_or_remove_items'        => __( 'Add or remove company types', 'formapress' ),
			'choose_from_most_used'      => __( 'Choose from the most used company types', 'formapress' ),
			'not_found'                  => __( 'No company types found.', 'formapress' ),
			'menu_name'                  => __( 'Company Types', 'formapress' ),
		);

		$args = array(
			'labels'            => $labels,
			'hierarchical'      => false,
			'public'            => false,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_nav_menus' => false,
			'show_tagcloud'     => false,
			'show_in_rest'      => true,
		);

		register_taxonomy( 'company_type', array( 'crm_company' ), $args );

		// Register default company types
		$default_types = array(
			'client'   => __( 'Client', 'formapress' ),
			'funder'   => __( 'Funder', 'formapress' ),
			'partner'  => __( 'Partner', 'formapress' ),
			'prospect' => __( 'Prospect', 'formapress' ),
		);

		foreach ( $default_types as $slug => $name ) {
			if ( ! term_exists( $slug, 'company_type' ) ) {
				wp_insert_term( $name, 'company_type', array( 'slug' => $slug ) );
			}
		}
	}

	/**
	 * Create a new company
	 *
	 * @param array $args Company data
	 * @return int|WP_Error Company ID on success, WP_Error on failure
	 */
	public static function create_company( $args ) {
		// Required fields validation
		if ( empty( $args['name'] ) ) {
			return new WP_Error( 'missing_name', __( 'Company name is required.', 'formapress' ) );
		}

		if ( empty( $args['company_type'] ) ) {
			return new WP_Error( 'missing_type', __( 'Company type is required.', 'formapress' ) );
		}

		// Create the company post
		$post_data = array(
			'post_type'   => 'crm_company',
			'post_title'  => sanitize_text_field( $args['name'] ),
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
		);

		$company_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $company_id ) ) {
			return $company_id;
		}

		// Set company type(s)
		$types = is_array( $args['company_type'] ) ? $args['company_type'] : array( $args['company_type'] );
		wp_set_object_terms( $company_id, $types, 'company_type' );

		// Save core fields
		$core_fields = array(
			'siret',
			'nda',
			'naf',
			'email',
			'telephone',
			'fax',
			'adresse',
			'adresse_complement',
			'ville',
			'code_postal',
			'pays',
			'site_web',
			'forme_juridique',
		);

		foreach ( $core_fields as $field ) {
			if ( isset( $args[ $field ] ) ) {
				update_post_meta( $company_id, '_crm_' . $field, sanitize_text_field( $args[ $field ] ) );
			}
		}

		// Save training-specific fields
		if ( isset( $args['numero_declaration'] ) ) {
			update_post_meta( $company_id, '_crm_numero_declaration', sanitize_text_field( $args['numero_declaration'] ) );
		}

		if ( isset( $args['qualiopi_certified'] ) ) {
			update_post_meta( $company_id, '_crm_qualiopi_certified', (bool) $args['qualiopi_certified'] );
		}

		if ( isset( $args['qualiopi_date'] ) ) {
			update_post_meta( $company_id, '_crm_qualiopi_date', sanitize_text_field( $args['qualiopi_date'] ) );
		}

		// Save custom fields (individual meta keys)
		if ( ! empty( $args['custom_fields'] ) ) {
			foreach ( $types as $type ) {
				if ( isset( $args['custom_fields'][ $type ] ) ) {
					foreach ( $args['custom_fields'][ $type ] as $field_slug => $value ) {
						self::update_custom_field_value( $company_id, $type, $field_slug, $value );
					}
				}
			}
		}

		// Audit fields
		update_post_meta( $company_id, '_crm_created_at', current_time( 'mysql' ) );
		update_post_meta( $company_id, '_crm_created_by', get_current_user_id() );
		update_post_meta( $company_id, '_crm_updated_at', current_time( 'mysql' ) );

		// Fire action hook
		do_action( 'formapress_company_created', $company_id, $args );

		return $company_id;
	}

	/**
	 * Update an existing company
	 *
	 * @param int   $company_id Company ID
	 * @param array $args       Company data to update
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public static function update_company( $company_id, $args ) {
		// Verify company exists
		$company = get_post( $company_id );
		if ( ! $company || $company->post_type !== 'crm_company' ) {
			return new WP_Error( 'invalid_company', __( 'Invalid company ID.', 'formapress' ) );
		}

		// Update post title if name changed
		if ( ! empty( $args['name'] ) ) {
			wp_update_post(
				array(
					'ID'         => $company_id,
					'post_title' => sanitize_text_field( $args['name'] ),
				)
			);
		}

		// Update company type(s) if provided
		if ( ! empty( $args['company_type'] ) ) {
			$types = is_array( $args['company_type'] ) ? $args['company_type'] : array( $args['company_type'] );
			wp_set_object_terms( $company_id, $types, 'company_type' );
		}

		// Update core fields
		$core_fields = array(
			'siret',
			'nda',
			'naf',
			'email',
			'telephone',
			'fax',
			'adresse',
			'adresse_complement',
			'ville',
			'code_postal',
			'pays',
			'site_web',
			'forme_juridique',
			'numero_declaration',
			'qualiopi_certified',
			'qualiopi_date',
		);

		foreach ( $core_fields as $field ) {
			if ( isset( $args[ $field ] ) ) {
				update_post_meta( $company_id, '_crm_' . $field, sanitize_text_field( $args[ $field ] ) );
			}
		}

		// Update custom fields
		if ( ! empty( $args['custom_fields'] ) ) {
			$types = self::get_company_types( $company_id );
			foreach ( $types as $type ) {
				if ( isset( $args['custom_fields'][ $type ] ) ) {
					foreach ( $args['custom_fields'][ $type ] as $field_slug => $value ) {
						self::update_custom_field_value( $company_id, $type, $field_slug, $value );
					}
				}
			}
		}

		// Update audit fields
		update_post_meta( $company_id, '_crm_updated_at', current_time( 'mysql' ) );
		update_post_meta( $company_id, '_crm_updated_by', get_current_user_id() );

		// Fire action hook
		do_action( 'formapress_company_updated', $company_id, $args );

		return true;
	}

	/**
	 * Delete a company
	 *
	 * @param int  $company_id Company ID
	 * @param bool $force      Whether to bypass trash and force deletion
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public static function delete_company( $company_id, $force = false ) {
		$company = get_post( $company_id );
		if ( ! $company || $company->post_type !== 'crm_company' ) {
			return new WP_Error( 'invalid_company', __( 'Invalid company ID.', 'formapress' ) );
		}

		// Fire action before deletion
		do_action( 'formapress_company_before_delete', $company_id );

		// Delete the post
		$result = wp_delete_post( $company_id, $force );

		if ( ! $result ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete company.', 'formapress' ) );
		}

		// Fire action after deletion
		do_action( 'formapress_company_deleted', $company_id );

		return true;
	}

	/**
	 * Get a company by ID
	 *
	 * @param int $company_id Company ID
	 * @return array|null Company data or null if not found
	 */
	public static function get_company( $company_id ) {
		$company = get_post( $company_id );
		if ( ! $company || $company->post_type !== 'crm_company' ) {
			return null;
		}

		// Build company data array
		$data = array(
			'id'                 => $company->ID,
			'name'               => $company->post_title,
			'types'              => self::get_company_types( $company_id ),
			'siret'              => get_post_meta( $company_id, '_crm_siret', true ),
			'nda'                => get_post_meta( $company_id, '_crm_nda', true ),
			'naf'                => get_post_meta( $company_id, '_crm_naf', true ),
			'email'              => get_post_meta( $company_id, '_crm_email', true ),
			'telephone'          => get_post_meta( $company_id, '_crm_telephone', true ),
			'fax'                => get_post_meta( $company_id, '_crm_fax', true ),
			'adresse'            => get_post_meta( $company_id, '_crm_adresse', true ),
			'adresse_complement' => get_post_meta( $company_id, '_crm_adresse_complement', true ),
			'ville'              => get_post_meta( $company_id, '_crm_ville', true ),
			'code_postal'        => get_post_meta( $company_id, '_crm_code_postal', true ),
			'pays'               => get_post_meta( $company_id, '_crm_pays', true ),
			'site_web'           => get_post_meta( $company_id, '_crm_site_web', true ),
			'forme_juridique'    => get_post_meta( $company_id, '_crm_forme_juridique', true ),
			'numero_declaration' => get_post_meta( $company_id, '_crm_numero_declaration', true ),
			'qualiopi_certified' => get_post_meta( $company_id, '_crm_qualiopi_certified', true ),
			'qualiopi_date'      => get_post_meta( $company_id, '_crm_qualiopi_date', true ),
			'created_at'         => get_post_meta( $company_id, '_crm_created_at', true ),
			'updated_at'         => get_post_meta( $company_id, '_crm_updated_at', true ),
		);

		return $data;
	}

	/**
	 * Find companies by type
	 *
	 * @param string $type Company type slug
	 * @param array  $args Query arguments
	 * @return array Array of company IDs
	 */
	public static function find_by_type( $type, $args = array() ) {
		$defaults = array(
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		$args              = wp_parse_args( $args, $defaults );
		$args['post_type'] = 'crm_company';
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'company_type',
				'field'    => 'slug',
				'terms'    => $type,
			),
		);

		$query = new WP_Query( $args );
		return $query->posts;
	}

	/**
	 * Search companies
	 *
	 * @param string $term Search term
	 * @param array  $args Additional query arguments
	 * @return array Array of company IDs
	 */
	public static function search_companies( $term, $args = array() ) {
		$defaults = array(
			'posts_per_page' => 50,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		$args              = wp_parse_args( $args, $defaults );
		$args['post_type'] = 'crm_company';
		$args['s']         = sanitize_text_field( $term );

		// Also search in meta fields
		$args['meta_query'] = array(
			'relation' => 'OR',
			array(
				'key'     => '_crm_siret',
				'value'   => $term,
				'compare' => 'LIKE',
			),
			array(
				'key'     => '_crm_email',
				'value'   => $term,
				'compare' => 'LIKE',
			),
		);

		$query = new WP_Query( $args );
		return $query->posts;
	}

	/**
	 * Get company types for a company
	 *
	 * @param int $company_id Company ID
	 * @return array Array of company type slugs
	 */
	public static function get_company_types( $company_id ) {
		$terms = wp_get_object_terms( $company_id, 'company_type', array( 'fields' => 'slugs' ) );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Check if company has a specific type
	 *
	 * @param int    $company_id Company ID
	 * @param string $type       Company type slug
	 * @return bool
	 */
	public static function has_company_type( $company_id, $type ) {
		return has_term( $type, 'company_type', $company_id );
	}

	/**
	 * Get all contacts (persons) for a company
	 *
	 * @param int $company_id Company ID
	 * @return array Array of person IDs
	 */
	public static function get_company_contacts( $company_id ) {
		return FormaPress_Person_Manager::find_by_company( $company_id );
	}

	/**
	 * Link a person to a company
	 *
	 * @param int $person_id  Person ID
	 * @param int $company_id Company ID
	 * @return bool
	 */
	public static function link_person_to_company( $person_id, $company_id ) {
		return update_post_meta( $person_id, '_crm_company_id', absint( $company_id ) ) !== false;
	}

	/**
	 * Unlink a person from a company
	 *
	 * @param int $person_id Person ID
	 * @return bool
	 */
	public static function unlink_person_from_company( $person_id ) {
		return delete_post_meta( $person_id, '_crm_company_id' );
	}

	/**
	 * Get custom fields configuration for a company type
	 *
	 * @param string $company_type Company type slug
	 * @return array Custom fields configuration
	 */
	public static function get_custom_fields_config( $company_type ) {
		$option_key = 'formapress_company_' . $company_type . '_custom_fields';
		$config     = get_option( $option_key, array() );
		return is_array( $config ) ? $config : array();
	}

	/**
	 * Update custom field value for a company
	 *
	 * @param int    $company_id   Company ID
	 * @param string $company_type Company type slug
	 * @param string $field_slug   Custom field slug
	 * @param mixed  $value        Field value
	 * @return bool|WP_Error
	 */
	public static function update_custom_field_value( $company_id, $company_type, $field_slug, $value ) {
		// Build meta key: _crm_company_{company_type}_{field_slug}
		$meta_key = '_crm_company_' . $company_type . '_' . $field_slug;

		// Update individual meta key
		$result = update_post_meta( $company_id, $meta_key, $value );

		// Fire action hook
		do_action( 'formapress_company_custom_field_updated', $company_id, $company_type, $field_slug, $value );

		return $result !== false;
	}

	/**
	 * Get custom field value for a company
	 *
	 * @param int    $company_id   Company ID
	 * @param string $company_type Company type slug
	 * @param string $field_slug   Custom field slug
	 * @return mixed Field value or empty string if not found
	 */
	public static function get_custom_field_value( $company_id, $company_type, $field_slug ) {
		// Build meta key: _crm_company_{company_type}_{field_slug}
		$meta_key = '_crm_company_' . $company_type . '_' . $field_slug;

		// Get individual meta value
		$value = get_post_meta( $company_id, $meta_key, true );

		return $value;
	}

	/**
	 * Register WordPress shortcodes for custom fields
	 */
	public static function register_custom_field_shortcodes() {
		// Get all company types
		$company_types = array( 'client', 'funder', 'partner', 'prospect' );

		foreach ( $company_types as $company_type ) {
			$config = self::get_custom_fields_config( $company_type );

			foreach ( $config as $field_slug => $field_config ) {
				$shortcode_name = 'crm_company_' . $company_type . '_' . $field_slug;

				add_shortcode(
					$shortcode_name,
					function ( $atts ) use ( $company_type, $field_slug, $field_config ) {
						global $post;
						if ( empty( $post->ID ) ) {
							return '';
						}

						// Get field value from individual meta key
						$value = self::get_custom_field_value( $post->ID, $company_type, $field_slug );

						if ( empty( $value ) ) {
							return '';
						}

						// Format output (reuse Person Manager formatting)
						return FormaPress_Person_Manager::format_custom_field_output( $value, $field_config, $atts );
					}
				);
			}
		}
	}

	// ============================================
	// v1 COMPATIBILITY & MIGRATION
	// ============================================

	/**
	 * Migrate v1 entreprise to v2 company + extract referents
	 *
	 * This is the CRITICAL migration that fixes the "worst design error":
	 * Referents stored in entreprise post meta → Individual crm_person records
	 *
	 * @param int $entreprise_id v1 zqpm_entreprise post ID
	 * @return array Migration results ['company_id' => int, 'referents' => array]
	 */
	public static function migrate_v1_entreprise( $entreprise_id ) {
		$entreprise = get_post( $entreprise_id );
		if ( ! $entreprise || $entreprise->post_type !== 'zqpm_entreprise' ) {
			return new WP_Error( 'invalid_entreprise', __( 'Invalid entreprise ID.', 'formapress' ) );
		}

		$results = array(
			'company_id' => null,
			'referents'  => array(),
			'errors'     => array(),
		);

		// Read v1 company data.
		$v1_cp   = get_post_meta( $entreprise_id, 'cp', true );
		$v1_cp   = empty( $v1_cp ) ? get_post_meta( $entreprise_id, 'code_postal', true ) : $v1_cp;
		$v1_site = get_post_meta( $entreprise_id, 'site', true );
		$v1_site = empty( $v1_site ) ? get_post_meta( $entreprise_id, 'siteweb', true ) : $v1_site;

		$v1_data = array(
			'name'               => $entreprise->post_title,
			'siret'              => get_post_meta( $entreprise_id, 'siret', true ),
			'nda'                => get_post_meta( $entreprise_id, 'nda', true ),
			'naf'                => get_post_meta( $entreprise_id, 'naf', true ),
			'email'              => get_post_meta( $entreprise_id, 'email', true ),
			'telephone'          => get_post_meta( $entreprise_id, 'telephone', true ),
			'adresse'            => get_post_meta( $entreprise_id, 'adresse', true ),
			'ville'              => get_post_meta( $entreprise_id, 'ville', true ),
			'code_postal'        => $v1_cp,
			'pays'               => get_post_meta( $entreprise_id, 'pays', true ),
			'site_web'           => $v1_site,
			'numero_declaration' => get_post_meta( $entreprise_id, 'numero_declaration', true ),
		);

		// Read v1 attribute data (all fields for v2 attribute system).
		$v1_attributes = array(
			'raison-sociale' => $entreprise->post_title,
			'siret'          => get_post_meta( $entreprise_id, 'siret', true ),
			'adresse'        => get_post_meta( $entreprise_id, 'adresse', true ),
			'cp'             => $v1_cp,
			'ville'          => get_post_meta( $entreprise_id, 'ville', true ),
			'site'           => $v1_site,
			'tel'            => get_post_meta( $entreprise_id, 'telephone', true ),
			'email'          => get_post_meta( $entreprise_id, 'email', true ),
			'pays'           => get_post_meta( $entreprise_id, 'pays', true ),
			'naf'            => get_post_meta( $entreprise_id, 'naf', true ),
			'nda'            => get_post_meta( $entreprise_id, 'nda', true ),
		);

		// Check if company already exists (for --force updates).
		$existing = get_posts(
			array(
				'post_type'  => 'crm_company',
				'meta_key'   => '_crm_v1_entreprise_id',
				'meta_value' => $entreprise_id,
				'fields'     => 'ids',
			)
		);

		// Determine company type from v1 data.
		$company_type = 'client'; // Default.
		$is_funder    = get_post_meta( $entreprise_id, 'is_financeur', true );
		if ( $is_funder ) {
			$company_type = 'funder';
		}

		if ( ! empty( $existing ) ) {
			// Update existing company.
			$company_id = $existing[0];

			// Update post title and type.
			wp_update_post(
				array(
					'ID'         => $company_id,
					'post_title' => $v1_data['name'],
				)
			);

			// Update company type taxonomy.
			wp_set_object_terms( $company_id, $company_type, 'company_type' );

			// Update all core meta fields.
			update_post_meta( $company_id, '_crm_siret', $v1_data['siret'] );
			update_post_meta( $company_id, '_crm_nda', $v1_data['nda'] );
			update_post_meta( $company_id, '_crm_naf', $v1_data['naf'] );
			update_post_meta( $company_id, '_crm_email', $v1_data['email'] );
			update_post_meta( $company_id, '_crm_telephone', $v1_data['telephone'] );
			update_post_meta( $company_id, '_crm_adresse', $v1_data['adresse'] );
			update_post_meta( $company_id, '_crm_ville', $v1_data['ville'] );
			update_post_meta( $company_id, '_crm_code_postal', $v1_data['code_postal'] );
			update_post_meta( $company_id, '_crm_pays', $v1_data['pays'] );
			update_post_meta( $company_id, '_crm_site_web', $v1_data['site_web'] );
			update_post_meta( $company_id, '_crm_numero_declaration', $v1_data['numero_declaration'] );
		} else {
			// Create new v2 company.
			$company_id = self::create_company(
				array(
					'name'               => $v1_data['name'],
					'company_type'       => $company_type,
					'siret'              => $v1_data['siret'],
					'nda'                => $v1_data['nda'],
					'naf'                => $v1_data['naf'],
					'email'              => $v1_data['email'],
					'telephone'          => $v1_data['telephone'],
					'adresse'            => $v1_data['adresse'],
					'ville'              => $v1_data['ville'],
					'code_postal'        => $v1_data['code_postal'],
					'pays'               => $v1_data['pays'],
					'site_web'           => $v1_data['site_web'],
					'numero_declaration' => $v1_data['numero_declaration'],
				)
			);

			if ( is_wp_error( $company_id ) ) {
				$results['errors'][] = 'Company creation failed: ' . $company_id->get_error_message();
				return $results;
			}

			// Store v1 reference for compatibility.
			update_post_meta( $company_id, '_crm_v1_entreprise_id', $entreprise_id );
		}

		$results['company_id'] = $company_id;

		// Week 4: Save v1 data using ATTRIBUTE SYSTEM for locked v1 fields.
		// This ensures backward compatibility with v1 structure.
		$company_schema = get_option( 'crm_company_attributes', array() );
		foreach ( $v1_attributes as $field_key => $field_value ) {
			if ( isset( $company_schema[ $field_key ] ) && ( ! empty( $field_value ) || '0' === $field_value ) ) {
				$meta_key = 'crm_company_attributes_' . $field_key;
				update_post_meta( $company_id, $meta_key, $field_value );
			}
		}

		// EXTRACT REFERENTS from v1 meta (key is 'referent' not 'referents')
		// v1 stores referents as serialized zqpmReferent objects
		$v1_referents = get_post_meta( $entreprise_id, 'referent', true );

		if ( is_array( $v1_referents ) && ! empty( $v1_referents ) ) {
			foreach ( $v1_referents as $index => $referent_obj ) {
				// Convert zqpmReferent object to array
				$referent_data = is_object( $referent_obj ) ? (array) $referent_obj : $referent_obj;

				$email = $referent_data['mail'] ?? '';

				// Skip referents without email
				if ( empty( $email ) ) {
					$results['errors'][] = "Referent {$index} skipped: no email";
					continue;
				}

				// Check if person already exists by email
				$existing_person_id = FormaPress_Person_Manager::find_by_email( $email );

				if ( $existing_person_id ) {
					// Person exists - update core data and add this company relationship.
					$person_id = $existing_person_id;

					// Update core person data.
					$prenom = $referent_data['prenom'] ?? '';
					$nom    = $referent_data['nom'] ?? '';
					wp_update_post(
						array(
							'ID'         => $person_id,
							'post_title' => $prenom . ' ' . strtoupper( $nom ),
						)
					);                  // Update core meta fields.
					update_post_meta( $person_id, '_crm_civilite', $referent_data['civilite'] ?? '' );
					update_post_meta( $person_id, '_crm_prenom', $referent_data['prenom'] ?? '' );
					update_post_meta( $person_id, '_crm_nom', $referent_data['nom'] ?? '' );
					update_post_meta( $person_id, '_crm_email', $email );
					update_post_meta( $person_id, '_crm_telephone', $referent_data['tel'] ?? '' );

					// Add company_contact type if not already set.
					$existing_types = wp_get_post_terms( $person_id, 'person_type', array( 'fields' => 'slugs' ) );
					if ( is_wp_error( $existing_types ) ) {
						$existing_types = array();
					}
					if ( ! in_array( 'company_contact', $existing_types, true ) ) {
						$existing_types[] = 'company_contact';
						wp_set_object_terms( $person_id, $existing_types, 'person_type' );
					}

					// Store this additional v1 company relationship (allow multiple).
					add_post_meta( $person_id, '_crm_v1_entreprise_id', $entreprise_id, false );

					// Update fonction if provided (will use last value).
					if ( ! empty( $referent_data['poste'] ) ) {
						update_post_meta( $person_id, '_crm_fonction', sanitize_text_field( $referent_data['poste'] ) );
					}
				} else {
					// Create new person.
					$person_id = FormaPress_Person_Manager::create_person(
						array(
							'prenom'      => $referent_data['prenom'] ?? '',
							'nom'         => $referent_data['nom'] ?? '',
							'email'       => $email,
							'telephone'   => $referent_data['tel'] ?? '',
							'fonction'    => $referent_data['poste'] ?? '',
							'person_type' => 'company_contact',
							'company_id'  => $company_id,
						)
					);

					if ( is_wp_error( $person_id ) ) {
						$results['errors'][] = "Referent {$index} migration failed: " . $person_id->get_error_message();
						continue;
					}

					// Store v1 company link (first one for this new person).
					add_post_meta( $person_id, '_crm_v1_entreprise_id', $entreprise_id, false );
				}

				// Store v1 referent index (for this specific company).
				update_post_meta( $person_id, '_crm_v1_referent_index', $index );

				// Week 4: Save referent data using ATTRIBUTE SYSTEM.
				// Use company_contact type slug for meta keys to match person_type taxonomy.
				$referent_schema   = get_option( 'crm_person_referent_attributes', array() );
				$v1_referent_attrs = array(
					'civilite' => $referent_data['civilite'] ?? '',
					'nom'      => $referent_data['nom'] ?? '',
					'prenom'   => $referent_data['prenom'] ?? '',
					'poste'    => $referent_data['poste'] ?? '',
					'tel'      => $referent_data['tel'] ?? '',
					'mail'     => $referent_data['mail'] ?? '',
				);
				foreach ( $v1_referent_attrs as $field_key => $field_value ) {
					if ( isset( $referent_schema[ $field_key ] ) && ( ! empty( $field_value ) || '0' === $field_value ) ) {
						// Use company_contact type slug in meta key.
						$meta_key = 'crm_person_company_contact_attributes_' . $field_key;
						update_post_meta( $person_id, $meta_key, $field_value );
					}
				}

				// NOTE: _crm_v1_entreprise_id is set inside if/else blocks to handle multiple company links.

				$results['referents'][] = array(
					'person_id' => $person_id,
					'v1_index'  => $index,
					'name'      => ( $referent_data['prenom'] ?? '' ) . ' ' . ( $referent_data['nom'] ?? '' ),
					'existing'  => isset( $existing_person_id ),
				);
			}
		}

		// Fire action hook.
		do_action( 'formapress_v1_entreprise_migrated', $entreprise_id, $company_id, $results );

		return $results;
	}

	/**
	 * Get v1 entreprise data (read-only compatibility)
	 *
	 * @param int $entreprise_id v1 zqpm_entreprise post ID
	 * @return array|null Entreprise data in v2 format
	 */
	public static function get_v1_entreprise( $entreprise_id ) {
		$entreprise = get_post( $entreprise_id );
		if ( ! $entreprise || $entreprise->post_type !== 'zqpm_entreprise' ) {
			return null;
		}

		return array(
			'id'              => $entreprise_id,
			'name'            => $entreprise->post_title,
			'siret'           => get_post_meta( $entreprise_id, 'siret', true ),
			'nda'             => get_post_meta( $entreprise_id, 'nda', true ),
			'email'           => get_post_meta( $entreprise_id, 'email', true ),
			'referents'       => get_post_meta( $entreprise_id, 'referent', true ),
			'referents_count' => count( get_post_meta( $entreprise_id, 'referent', true ) ?: array() ),
			'v1_post'         => $entreprise,
		);
	}
}
