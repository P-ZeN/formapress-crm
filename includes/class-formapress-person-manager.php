<?php
/**
 * FormaPress Person Manager
 *
 * Unified person management system for FormaPress v2.
 * Replaces fragmented v1 systems: zform_instructor, zqpmReferent, zRegistration_infos, zqpmCommanditaire.
 *
 * @package FormaPress
 * @subpackage CRM
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FormaPress_Person_Manager {

	/**
	 * Initialize the Person Manager
	 */
	public static function init() {
		// Register CPT and taxonomy
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 10 );
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ), 11 );

		// Register custom field shortcodes
		add_action( 'init', array( __CLASS__, 'register_custom_field_shortcodes' ), 20 );

		// Email uniqueness validation
		add_action( 'save_post_crm_person', array( __CLASS__, 'validate_email_uniqueness' ), 10, 3 );
	}

	/**
	 * Register crm_person custom post type
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'Persons', 'formapress' ),
			'singular_name'      => __( 'Person', 'formapress' ),
			'menu_name'          => __( 'CRM Persons', 'formapress' ),
			'name_admin_bar'     => __( 'Person', 'formapress' ),
			'add_new'            => __( 'Add New', 'formapress' ),
			'add_new_item'       => __( 'Add New Person', 'formapress' ),
			'new_item'           => __( 'New Person', 'formapress' ),
			'edit_item'          => __( 'Edit Person', 'formapress' ),
			'view_item'          => __( 'View Person', 'formapress' ),
			'all_items'          => __( 'All Persons', 'formapress' ),
			'search_items'       => __( 'Search Persons', 'formapress' ),
			'parent_item_colon'  => __( 'Parent Persons:', 'formapress' ),
			'not_found'          => __( 'No persons found.', 'formapress' ),
			'not_found_in_trash' => __( 'No persons found in Trash.', 'formapress' ),
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
			'menu_position'      => 25,
			'menu_icon'          => 'dashicons-groups',
			'supports'           => array( 'title', 'custom-fields' ),
			'show_in_rest'       => true,
		);

		register_post_type( 'crm_person', $args );
	}

	/**
	 * Register person_type taxonomy
	 */
	public static function register_taxonomy() {
		$labels = array(
			'name'                       => __( 'Person Types', 'formapress' ),
			'singular_name'              => __( 'Person Type', 'formapress' ),
			'search_items'               => __( 'Search Person Types', 'formapress' ),
			'popular_items'              => __( 'Popular Person Types', 'formapress' ),
			'all_items'                  => __( 'All Person Types', 'formapress' ),
			'edit_item'                  => __( 'Edit Person Type', 'formapress' ),
			'update_item'                => __( 'Update Person Type', 'formapress' ),
			'add_new_item'               => __( 'Add New Person Type', 'formapress' ),
			'new_item_name'              => __( 'New Person Type Name', 'formapress' ),
			'separate_items_with_commas' => __( 'Separate person types with commas', 'formapress' ),
			'add_or_remove_items'        => __( 'Add or remove person types', 'formapress' ),
			'choose_from_most_used'      => __( 'Choose from the most used person types', 'formapress' ),
			'not_found'                  => __( 'No person types found.', 'formapress' ),
			'menu_name'                  => __( 'Person Types', 'formapress' ),
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
			'meta_box_cb'       => false, // Disable default metabox - we have custom one
		);

		register_taxonomy( 'person_type', array( 'crm_person' ), $args );

		// Register default person types
		$default_types = array(
			'instructor'      => __( 'Instructor', 'formapress' ),
			'company_contact' => __( 'Company Contact', 'formapress' ),
			'trainee'         => __( 'Trainee', 'formapress' ),
			'funder_contact'  => __( 'Funder Contact', 'formapress' ),
			'prospect'        => __( 'Prospect', 'formapress' ),
		);

		foreach ( $default_types as $slug => $name ) {
			if ( ! term_exists( $slug, 'person_type' ) ) {
				wp_insert_term( $name, 'person_type', array( 'slug' => $slug ) );
			}
		}
	}

	/**
	 * Create a new person
	 *
	 * @param array $args Person data
	 * @return int|WP_Error Person ID on success, WP_Error on failure
	 */
	public static function create_person( $args ) {
		// Required fields validation
		if ( empty( $args['email'] ) ) {
			return new WP_Error( 'missing_email', __( 'Email is required.', 'formapress' ) );
		}

		if ( empty( $args['person_type'] ) ) {
			return new WP_Error( 'missing_type', __( 'Person type is required.', 'formapress' ) );
		}

		// Email uniqueness check
		$existing = self::find_by_email( $args['email'] );
		if ( $existing ) {
			return new WP_Error( 'duplicate_email', __( 'A person with this email already exists.', 'formapress' ) );
		}

		// Build post title: "Prénom NOM" or email if names missing
		$title = '';
		if ( ! empty( $args['prenom'] ) && ! empty( $args['nom'] ) ) {
			$title = $args['prenom'] . ' ' . strtoupper( $args['nom'] );
		} else {
			$title = $args['email'];
		}

		// Create the person post
		$post_data = array(
			'post_type'   => 'crm_person',
			'post_title'  => sanitize_text_field( $title ),
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
		);

		$person_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $person_id ) ) {
			return $person_id;
		}

		// Set person type(s)
		$types = is_array( $args['person_type'] ) ? $args['person_type'] : array( $args['person_type'] );
		wp_set_object_terms( $person_id, $types, 'person_type' );

		// Save core identity fields
		$core_fields = array( 'civilite', 'prenom', 'nom', 'email', 'telephone', 'mobile', 'fonction', 'adresse', 'ville', 'code_postal', 'pays' );
		foreach ( $core_fields as $field ) {
			if ( isset( $args[ $field ] ) ) {
				update_post_meta( $person_id, '_crm_' . $field, sanitize_text_field( $args[ $field ] ) );
			}
		}

		// Save relationship fields
		if ( ! empty( $args['company_id'] ) ) {
			update_post_meta( $person_id, '_crm_company_id', absint( $args['company_id'] ) );
		}

		if ( ! empty( $args['user_id'] ) ) {
			update_post_meta( $person_id, '_crm_user_id', absint( $args['user_id'] ) );
		}

		// Save role-specific fields
		if ( ! empty( $args['role_data'] ) ) {
			foreach ( $args['role_data'] as $key => $value ) {
				update_post_meta( $person_id, '_crm_' . sanitize_key( $key ), $value );
			}
		}

		// Save custom fields (individual meta keys)
		if ( ! empty( $args['custom_fields'] ) ) {
			foreach ( $types as $type ) {
				if ( isset( $args['custom_fields'][ $type ] ) ) {
					foreach ( $args['custom_fields'][ $type ] as $field_slug => $value ) {
						self::update_custom_field_value( $person_id, $type, $field_slug, $value );
					}
				}
			}
		}

		// Audit fields
		update_post_meta( $person_id, '_crm_created_at', current_time( 'mysql' ) );
		update_post_meta( $person_id, '_crm_created_by', get_current_user_id() );
		update_post_meta( $person_id, '_crm_updated_at', current_time( 'mysql' ) );

		// Fire action hook
		do_action( 'formapress_person_created', $person_id, $args );

		return $person_id;
	}

	/**
	 * Update an existing person
	 *
	 * @param int   $person_id Person ID
	 * @param array $args      Person data to update
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public static function update_person( $person_id, $args ) {
		// Verify person exists
		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'crm_person' ) {
			return new WP_Error( 'invalid_person', __( 'Invalid person ID.', 'formapress' ) );
		}

		// Email uniqueness check (if email is being changed)
		if ( ! empty( $args['email'] ) ) {
			$current_email = get_post_meta( $person_id, '_crm_email', true );
			if ( $args['email'] !== $current_email ) {
				$existing = self::find_by_email( $args['email'] );
				if ( $existing && $existing !== $person_id ) {
					return new WP_Error( 'duplicate_email', __( 'A person with this email already exists.', 'formapress' ) );
				}
			}
		}

		// Update post title if names changed
		if ( ! empty( $args['prenom'] ) || ! empty( $args['nom'] ) ) {
			$prenom = ! empty( $args['prenom'] ) ? $args['prenom'] : get_post_meta( $person_id, '_crm_prenom', true );
			$nom    = ! empty( $args['nom'] ) ? $args['nom'] : get_post_meta( $person_id, '_crm_nom', true );

			if ( $prenom && $nom ) {
				wp_update_post(
					array(
						'ID'         => $person_id,
						'post_title' => $prenom . ' ' . strtoupper( $nom ),
					)
				);
			}
		}

		// Update person type(s) if provided
		if ( ! empty( $args['person_type'] ) ) {
			$types = is_array( $args['person_type'] ) ? $args['person_type'] : array( $args['person_type'] );
			wp_set_object_terms( $person_id, $types, 'person_type' );
		}

		// Update core identity fields
		$core_fields = array( 'civilite', 'prenom', 'nom', 'email', 'telephone', 'mobile', 'fonction', 'adresse', 'ville', 'code_postal', 'pays' );
		foreach ( $core_fields as $field ) {
			if ( isset( $args[ $field ] ) ) {
				update_post_meta( $person_id, '_crm_' . $field, sanitize_text_field( $args[ $field ] ) );
			}
		}

		// Update relationship fields
		if ( isset( $args['company_id'] ) ) {
			update_post_meta( $person_id, '_crm_company_id', absint( $args['company_id'] ) );
		}

		if ( isset( $args['user_id'] ) ) {
			update_post_meta( $person_id, '_crm_user_id', absint( $args['user_id'] ) );
		}

		// Update role-specific fields
		if ( ! empty( $args['role_data'] ) ) {
			foreach ( $args['role_data'] as $key => $value ) {
				update_post_meta( $person_id, '_crm_' . sanitize_key( $key ), $value );
			}
		}

		// Update custom fields
		if ( ! empty( $args['custom_fields'] ) ) {
			$types = self::get_person_types( $person_id );
			foreach ( $types as $type ) {
				if ( isset( $args['custom_fields'][ $type ] ) ) {
					foreach ( $args['custom_fields'][ $type ] as $field_slug => $value ) {
						self::update_custom_field_value( $person_id, $type, $field_slug, $value );
					}
				}
			}
		}

		// Update audit fields
		update_post_meta( $person_id, '_crm_updated_at', current_time( 'mysql' ) );
		update_post_meta( $person_id, '_crm_updated_by', get_current_user_id() );

		// Fire action hook
		do_action( 'formapress_person_updated', $person_id, $args );

		return true;
	}

	/**
	 * Delete a person
	 *
	 * @param int  $person_id Person ID
	 * @param bool $force     Whether to bypass trash and force deletion
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	public static function delete_person( $person_id, $force = false ) {
		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'crm_person' ) {
			return new WP_Error( 'invalid_person', __( 'Invalid person ID.', 'formapress' ) );
		}

		// Fire action before deletion
		do_action( 'formapress_person_before_delete', $person_id );

		// Delete the post
		$result = wp_delete_post( $person_id, $force );

		if ( ! $result ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete person.', 'formapress' ) );
		}

		// Fire action after deletion
		do_action( 'formapress_person_deleted', $person_id );

		return true;
	}

	/**
	 * Get a person by ID
	 *
	 * @param int $person_id Person ID
	 * @return array|null Person data or null if not found
	 */
	public static function get_person( $person_id ) {
		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'crm_person' ) {
			return null;
		}

		// Build person data array
		$data = array(
			'id'          => $person->ID,
			'title'       => $person->post_title,
			'types'       => self::get_person_types( $person_id ),
			'civilite'    => get_post_meta( $person_id, '_crm_civilite', true ),
			'prenom'      => get_post_meta( $person_id, '_crm_prenom', true ),
			'nom'         => get_post_meta( $person_id, '_crm_nom', true ),
			'email'       => get_post_meta( $person_id, '_crm_email', true ),
			'telephone'   => get_post_meta( $person_id, '_crm_telephone', true ),
			'mobile'      => get_post_meta( $person_id, '_crm_mobile', true ),
			'fonction'    => get_post_meta( $person_id, '_crm_fonction', true ),
			'adresse'     => get_post_meta( $person_id, '_crm_adresse', true ),
			'ville'       => get_post_meta( $person_id, '_crm_ville', true ),
			'code_postal' => get_post_meta( $person_id, '_crm_code_postal', true ),
			'pays'        => get_post_meta( $person_id, '_crm_pays', true ),
			'company_id'  => get_post_meta( $person_id, '_crm_company_id', true ),
			'user_id'     => get_post_meta( $person_id, '_crm_user_id', true ),
			'created_at'  => get_post_meta( $person_id, '_crm_created_at', true ),
			'updated_at'  => get_post_meta( $person_id, '_crm_updated_at', true ),
		);

		return $data;
	}

	/**
	 * Find person by email
	 *
	 * @param string $email Email address
	 * @return int|null Person ID or null if not found
	 */
	public static function find_by_email( $email ) {
		global $wpdb;

		$person_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta
             WHERE meta_key = '_crm_email'
             AND meta_value = %s
             LIMIT 1",
				sanitize_email( $email )
			)
		);

		return $person_id ? (int) $person_id : null;
	}

	/**
	 * Find persons by company
	 *
	 * @param int $company_id Company ID
	 * @return array Array of person IDs
	 */
	public static function find_by_company( $company_id ) {
		global $wpdb;

		$person_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta
             WHERE meta_key = '_crm_company_id'
             AND meta_value = %d",
				$company_id
			)
		);

		return array_map( 'intval', $person_ids );
	}

	/**
	 * Find persons by type
	 *
	 * @param string $type      Person type slug
	 * @param array  $args      Query arguments
	 * @return array Array of person IDs
	 */
	public static function find_by_type( $type, $args = array() ) {
		$defaults = array(
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		$args              = wp_parse_args( $args, $defaults );
		$args['post_type'] = 'crm_person';
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'person_type',
				'field'    => 'slug',
				'terms'    => $type,
			),
		);

		$query = new WP_Query( $args );
		return $query->posts;
	}

	/**
	 * Search persons
	 *
	 * @param string $term Search term
	 * @param array  $args Additional query arguments
	 * @return array Array of person IDs
	 */
	public static function search_persons( $term, $args = array() ) {
		$defaults = array(
			'posts_per_page' => 50,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		);

		$args              = wp_parse_args( $args, $defaults );
		$args['post_type'] = 'crm_person';
		$args['s']         = sanitize_text_field( $term );

		// Also search in meta fields
		$args['meta_query'] = array(
			'relation' => 'OR',
			array(
				'key'     => '_crm_email',
				'value'   => $term,
				'compare' => 'LIKE',
			),
			array(
				'key'     => '_crm_telephone',
				'value'   => $term,
				'compare' => 'LIKE',
			),
		);

		$query = new WP_Query( $args );
		return $query->posts;
	}

	/**
	 * Get person types for a person
	 *
	 * @param int $person_id Person ID
	 * @return array Array of person type slugs
	 */
	public static function get_person_types( $person_id ) {
		$terms = wp_get_object_terms( $person_id, 'person_type', array( 'fields' => 'slugs' ) );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Check if person has a specific type
	 *
	 * @param int    $person_id Person ID
	 * @param string $type      Person type slug
	 * @return bool
	 */
	public static function has_person_type( $person_id, $type ) {
		return has_term( $type, 'person_type', $person_id );
	}

	/**
	 * Add person type to a person
	 *
	 * @param int    $person_id Person ID
	 * @param string $type      Person type slug
	 * @return bool|WP_Error
	 */
	public static function add_person_type( $person_id, $type ) {
		return wp_add_object_terms( $person_id, $type, 'person_type' );
	}

	/**
	 * Remove person type from a person
	 *
	 * @param int    $person_id Person ID
	 * @param string $type      Person type slug
	 * @return bool|WP_Error
	 */
	public static function remove_person_type( $person_id, $type ) {
		return wp_remove_object_terms( $person_id, $type, 'person_type' );
	}

	/**
	 * Get custom fields configuration for a person type
	 * Uses ZForm_Attributes_Core for unified schema management
	 *
	 * @param string $person_type Person type slug (trainee, instructor, referent, prospect, funder).
	 * @return array Custom fields configuration.
	 */
	public static function get_custom_fields_config( $person_type ) {
		// Use core attributes system.
		$schema = ZForm_Attributes_Core::get_attributes_schema( $person_type );

		if ( empty( $schema ) ) {
			return array();
		}

		// Filter out core fields (they're shown in main Details meta box).
		$core_fields = array( 'name', 'firstname', 'nom', 'prenom', 'mail', 'email', 'telephone', 'societe', 'civilite', 'adresse', 'cp', 'ville', 'code_postal', 'fonction' );

		$custom_fields = array();
		foreach ( $schema as $field_slug => $field_config ) {
			if ( ! in_array( $field_slug, $core_fields, true ) ) {
				$custom_fields[ $field_slug ] = $field_config;
			}
		}

		return $custom_fields;
	}

	/**
	 * Update custom field value for a person
	 * Uses ZForm_Attributes_Core for unified attribute management
	 *
	 * @param int    $person_id   Person ID.
	 * @param string $person_type Person type slug.
	 * @param string $field_slug  Custom field slug.
	 * @param mixed  $value       Field value.
	 * @return bool|WP_Error
	 */
	public static function update_custom_field_value( $person_id, $person_type, $field_slug, $value ) {
		// Use core attributes system.
		return ZForm_Attributes_Core::update_attribute( $person_id, $person_type, $field_slug, $value );
	}

	/**
	 * Get custom field value for a person
	 * Uses ZForm_Attributes_Core for unified attribute management
	 *
	 * @param int    $person_id   Person ID.
	 * @param string $person_type Person type slug.
	 * @param string $field_slug  Custom field slug.
	 * @return mixed Field value or empty string if not found.
	 */
	public static function get_custom_field_value( $person_id, $person_type, $field_slug ) {
		// Use core attributes system.
		return ZForm_Attributes_Core::get_attribute( $person_id, $person_type, $field_slug );
	}

	/**
	 * Register WordPress shortcodes for all person fields
	 * Includes both core fields (prenom, nom, email, etc.) and custom fields
	 * Preserves v1 pattern: [crm_field_slug] or [crm_type_field_slug]
	 */
	public static function register_custom_field_shortcodes() {
		// Register core field shortcodes (work for any person type)
		$core_fields = array(
			'civilite'    => array(
				'label' => 'Civility',
				'type'  => 'text',
			),
			'prenom'      => array(
				'label' => 'First Name',
				'type'  => 'text',
			),
			'nom'         => array(
				'label' => 'Last Name',
				'type'  => 'text',
			),
			'email'       => array(
				'label' => 'Email',
				'type'  => 'email',
			),
			'telephone'   => array(
				'label' => 'Phone',
				'type'  => 'tel',
			),
			'mobile'      => array(
				'label' => 'Mobile',
				'type'  => 'tel',
			),
			'fonction'    => array(
				'label' => 'Job Title',
				'type'  => 'text',
			),
			'adresse'     => array(
				'label' => 'Address',
				'type'  => 'textarea',
			),
			'ville'       => array(
				'label' => 'City',
				'type'  => 'text',
			),
			'code_postal' => array(
				'label' => 'Postal Code',
				'type'  => 'text',
			),
			'pays'        => array(
				'label' => 'Country',
				'type'  => 'text',
			),
		);

		foreach ( $core_fields as $field_slug => $field_info ) {
			$shortcode_name = 'crm_' . $field_slug;

			add_shortcode(
				$shortcode_name,
				function ( $atts ) use ( $field_slug, $field_info ) {
					global $post;
					if ( empty( $post->ID ) || $post->post_type !== 'crm_person' ) {
						return '';
					}

					// Get field value
					$value = get_post_meta( $post->ID, '_crm_' . $field_slug, true );

					if ( empty( $value ) ) {
						return '';
					}

					// Simple formatting for core fields
					$balise_in  = isset( $atts['balise'] ) ? '<' . esc_attr( $atts['balise'] ) . '>' : '<span>';
					$balise_out = isset( $atts['balise'] ) ? '</' . esc_attr( $atts['balise'] ) . '>' : '</span>';

					return $balise_in . esc_html( $value ) . $balise_out;
				}
			);
		}

		// Register custom field shortcodes (type-specific)
		$person_types = array( 'instructor', 'company_contact', 'trainee', 'funder_contact', 'prospect' );

		foreach ( $person_types as $person_type ) {
			$config = self::get_custom_fields_config( $person_type );

			foreach ( $config as $field_slug => $field_config ) {
				$shortcode_name = 'crm_' . $person_type . '_' . $field_slug;

				add_shortcode(
					$shortcode_name,
					function ( $atts ) use ( $person_type, $field_slug, $field_config ) {
						global $post;
						if ( empty( $post->ID ) ) {
							return '';
						}

						// Get field value from individual meta key
						$value = self::get_custom_field_value( $post->ID, $person_type, $field_slug );

						if ( empty( $value ) ) {
							return '';
						}

						// Format output based on field type
						return self::format_custom_field_output( $value, $field_config, $atts );
					}
				);
			}
		}
	}

	/**
	 * Format custom field value for shortcode output
	 * Preserves v1 formatting logic
	 *
	 * @param mixed $value        Field value
	 * @param array $field_config Field configuration
	 * @param array $atts         Shortcode attributes
	 * @return string Formatted output
	 */
	private static function format_custom_field_output( $value, $field_config, $atts = array() ) {
		$type   = $field_config['type'] ?? 'text';
		$output = '';

		switch ( $type ) {
			case 'wyswyg':
				// Rich text with optional title
				if ( ! isset( $atts['title'] ) || $atts['title'] !== 'no' ) {
					$title_tag = $atts['title'] ?? 'h3';
					$output   .= '<' . $title_tag . '>' . esc_html( $field_config['name'] ) . '</' . $title_tag . '>';
				}
				$output .= wp_kses_post( $value );
				break;

			case 'file':
			case 'download':
				// File attachment URL
				$output = wp_get_attachment_url( $value );
				break;

			case 'image':
				// Image tag
				$size   = $atts['size'] ?? 'full';
				$output = wp_get_attachment_image( $value, $size );
				break;

			default:
				// Text, number, textarea, etc.
				$balise_in  = isset( $atts['balise'] ) ? '<' . esc_attr( $atts['balise'] ) . '>' : '<span>';
				$balise_out = isset( $atts['balise'] ) ? '</' . esc_attr( $atts['balise'] ) . '>' : '</span>';
				$output     = $balise_in . nl2br( esc_html( $value ) ) . $balise_out;
				break;
		}

		return $output;
	}

	/**
	 * Validate email uniqueness on save
	 *
	 * @param int     $post_id Post ID
	 * @param WP_Post $post    Post object
	 * @param bool    $update  Whether this is an update
	 */
	public static function validate_email_uniqueness( $post_id, $post, $update ) {
		// Skip autosaves and revisions
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Check email uniqueness
		$email = get_post_meta( $post_id, '_crm_email', true );
		if ( ! empty( $email ) ) {
			$existing = self::find_by_email( $email );
			if ( $existing && $existing !== $post_id ) {
				// Email already exists for another person
				update_post_meta( $post_id, '_crm_email_duplicate_warning', true );
			} else {
				delete_post_meta( $post_id, '_crm_email_duplicate_warning' );
			}
		}
	}

	// ============================================
	// v1 COMPATIBILITY LAYER
	// ============================================

	/**
	 * Get v1 instructor data (read-only compatibility)
	 *
	 * @param int $instructor_id v1 zform_instructor post ID
	 * @return array|null Instructor data in v2 format
	 */
	public static function get_v1_instructor( $instructor_id ) {
		$instructor = get_post( $instructor_id );
		if ( ! $instructor || $instructor->post_type !== 'zform_instructor' ) {
			return null;
		}

		// Read v1 custom attributes (all data stored in serialized 'instructors_attributs')
		$v1_attrs = get_post_meta( $instructor_id, 'instructors_attributs', true );
		if ( ! is_array( $v1_attrs ) ) {
			$v1_attrs = array();
		}

		// Extract name from post_title (format: "Prénom NOM")
		$name_parts = explode( ' ', $instructor->post_title, 2 );
		$prenom     = isset( $name_parts[0] ) ? $name_parts[0] : '';
		$nom        = isset( $name_parts[1] ) ? $name_parts[1] : '';

		return array(
			'id'         => $instructor_id,
			'prenom'     => $prenom,
			'nom'        => $nom,
			'email'      => $v1_attrs['email'] ?? '',
			'telephone'  => $v1_attrs['telephone'] ?? '',
			'attributes' => $v1_attrs, // All custom attributes
			'v1_post'    => $instructor,
		);
	}

	/**
	 * Get v1 referent data from entreprise meta (read-only compatibility)
	 *
	 * @param int $entreprise_id v1 zqpm_entreprise post ID
	 * @param int $index         Referent index in the array
	 * @return array|null Referent data in v2 format
	 */
	public static function get_v1_referent( $entreprise_id, $index ) {
		// v1 uses 'referent' (singular) not 'referents' (plural)
		$referents = get_post_meta( $entreprise_id, 'referent', true );

		if ( ! is_array( $referents ) || ! isset( $referents[ $index ] ) ) {
			return null;
		}

		$referent_obj = $referents[ $index ];

		// v1 referents are zqpmReferent objects, convert to array
		$referent = is_object( $referent_obj ) ? (array) $referent_obj : $referent_obj;

		return array(
			'entreprise_id' => $entreprise_id,
			'index'         => $index,
			'prenom'        => $referent['prenom'] ?? '',
			'nom'           => $referent['nom'] ?? '',
			'email'         => $referent['mail'] ?? '',
			'telephone'     => $referent['tel'] ?? '',
			'fonction'      => $referent['poste'] ?? '',
			'v1_data'       => $referent,
		);
	}

	/**
	 * Get v1 trainee data from zRegistration_infos (read-only compatibility)
	 *
	 * @param int $registration_id v1 registration post ID
	 * @return array|null Trainee data in v2 format
	 */
	public static function get_v1_registration_person( $registration_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'zform_registrations';

		// Trainees are in custom table wp_zform_registrations, not posts.
		$registration = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d",
				$registration_id
			)
		);

		if ( ! $registration ) {
			return null;
		}

		// Unserialize the data column.
		$data = maybe_unserialize( $registration->datas );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		// Get zqp_id from responses table (wp_zqpm_reponses).
		// Each registration can have multiple responses, but they all have the same zqp_id.
		$zqp_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT zqp_id FROM {$wpdb->prefix}zqpm_reponses WHERE stagiaire_id = %d LIMIT 1",
				$registration_id
			)
		);

		// Fallback 1: Try zqpm_id from datas if not in responses table.
		if ( empty( $zqp_id ) ) {
			$zqp_id = $data['zqpm_id'] ?? '';
		}

		// Fallback 2: Look up ZQPM by session_id (for registrations without responses).
		// Some registrations have ZQPM records but no questionnaire responses (only PDFs).
		if ( empty( $zqp_id ) && ! empty( $registration->session_id ) ) {
			$zqpm_posts = get_posts(
				array(
					'post_type'      => 'zqpm',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => 'zqpm_session_id',
					'meta_value'     => $registration->session_id,
				)
			);

			if ( ! empty( $zqpm_posts ) ) {
				$zqp_id = $zqpm_posts[0];
			}
		}

		// Get the registration form schema to know ALL fields (including custom attributes).
		$schema = get_option( 'zform_registrations' );

		// Core fields mapping (v1 mandatory fields with English names).
		$person_data = array(
			'id'         => $registration_id,
			'prenom'     => $data['firstname'] ?? $data['prenom'] ?? '',  // v1 uses 'firstname' (English).
			'nom'        => $data['name'] ?? $data['nom'] ?? '',          // v1 uses 'name' (English).
			'email'      => $data['mail'] ?? '',
			'telephone'  => $data['telephone'] ?? '',
			'societe'    => $data['societe'] ?? '',
			'civilite'   => $data['civilite'] ?? '',
			'zqpm_id'    => $zqp_id,
			'session_id' => $registration->session_id,
			'v1_data'    => $data,
		);

		// Add ALL custom attributes from schema dynamically.
		// These will be stored as individual meta keys: crm_person_trainee_attributs_{field_slug}.
		$person_data['attributes'] = array();

		if ( is_array( $schema ) ) {
			foreach ( $schema as $field_slug => $field_config ) {
				// Store ALL fields as attributes (for v1 compatibility and shortcodes).
				// Store attribute value if it exists in registration data.
				if ( isset( $data[ $field_slug ] ) ) {
					$person_data['attributes'][ $field_slug ] = $data[ $field_slug ];
				}
			}
		}

		return $person_data;
	}

	// ============================================
	// ZQPM COMPATIBILITY - v1 ↔ v2 ID MAPPING
	// ============================================

	/**
	 * Get v2 person ID from v1 registration ID
	 *
	 * ZQPM uses v1 registration table IDs in wp_zqpm_reponses.stagiaire_id.
	 * This function maps those v1 IDs to v2 crm_person post IDs.
	 *
	 * @param int $registration_id V1 registration table row ID.
	 * @return int|null Person post ID or null if not found.
	 */
	public static function get_person_id_from_v1_registration_id( $registration_id ) {
		global $wpdb;

		$person_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = '_crm_v1_registration_id'
				 AND meta_value = %d
				 LIMIT 1",
				$registration_id
			)
		);

		return $person_id ? intval( $person_id ) : null;
	}

	/**
	 * Get v1 registration ID from v2 person ID
	 *
	 * Reverse lookup for ZQPM compatibility.
	 *
	 * @param int $person_id Person post ID.
	 * @return int|null V1 registration ID or null if not found.
	 */
	public static function get_v1_registration_id_from_person_id( $person_id ) {
		$registration_id = get_post_meta( $person_id, '_crm_v1_registration_id', true );
		return $registration_id ? intval( $registration_id ) : null;
	}

	/**
	 * Get person data for ZQPM (supports both v1 and v2 IDs)
	 *
	 * This is the main function ZQPM should use to fetch trainee data.
	 * It transparently handles v1 registration IDs and v2 person IDs.
	 *
	 * @param int    $id      Registration ID or Person ID.
	 * @param string $id_type 'registration' (v1) or 'person' (v2).
	 * @return array Person data with both v1 and v2 references.
	 */
	public static function get_person_for_zqpm( $id, $id_type = 'registration' ) {
		// Convert to person ID if needed.
		if ( 'registration' === $id_type ) {
			$person_id = self::get_person_id_from_v1_registration_id( $id );
		} else {
			$person_id = $id;
		}

		if ( ! $person_id ) {
			return array();
		}

		// Get core person data.
		$person_data = array(
			'person_id'       => $person_id,
			'registration_id' => get_post_meta( $person_id, '_crm_v1_registration_id', true ),
			'civilite'        => get_post_meta( $person_id, '_crm_civilite', true ),
			'nom'             => get_post_meta( $person_id, '_crm_nom', true ),
			'prenom'          => get_post_meta( $person_id, '_crm_prenom', true ),
			'email'           => get_post_meta( $person_id, '_crm_email', true ),
			'telephone'       => get_post_meta( $person_id, '_crm_telephone', true ),
			'adresse'         => get_post_meta( $person_id, '_crm_adresse', true ),
			'cp'              => get_post_meta( $person_id, '_crm_cp', true ),
			'ville'           => get_post_meta( $person_id, '_crm_ville', true ),
			'company_id'      => get_post_meta( $person_id, '_crm_company_id', true ),
		);

		// Get all trainee attributes (exploded meta keys).
		$all_meta   = get_post_meta( $person_id );
		$attributes = array();

		foreach ( $all_meta as $meta_key => $meta_value ) {
			if ( 0 === strpos( $meta_key, 'crm_person_trainee_attributs_' ) ) {
				$field_slug                = str_replace( 'crm_person_trainee_attributs_', '', $meta_key );
				$attributes[ $field_slug ] = isset( $meta_value[0] ) ? $meta_value[0] : '';
			}
		}

		$person_data['attributes'] = $attributes;

		return $person_data;
	}

	/**
	 * Bulk get persons for ZQPM
	 *
	 * Optimized bulk lookup for ZQPM processes with multiple trainees.
	 *
	 * @param array  $ids      Array of registration IDs or person IDs.
	 * @param string $id_type  'registration' (v1) or 'person' (v2).
	 * @return array Array of person data keyed by input ID.
	 */
	public static function get_persons_for_zqpm_bulk( $ids, $id_type = 'registration' ) {
		if ( empty( $ids ) ) {
			return array();
		}

		$results = array();

		// If using v1 registration IDs, first map them to person IDs.
		if ( 'registration' === $id_type ) {
			global $wpdb;

			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$query        = $wpdb->prepare(
				"SELECT meta_value as registration_id, post_id as person_id
				 FROM {$wpdb->postmeta}
				 WHERE meta_key = '_crm_v1_registration_id'
				 AND meta_value IN ($placeholders)",
				...$ids
			);

			$mapping = $wpdb->get_results( $query, OBJECT_K );

			// Fetch person data for each.
			foreach ( $ids as $registration_id ) {
				if ( isset( $mapping[ $registration_id ] ) ) {
					$person_id                   = $mapping[ $registration_id ]->person_id;
					$results[ $registration_id ] = self::get_person_for_zqpm( $person_id, 'person' );
				} else {
					$results[ $registration_id ] = array();
				}
			}
		} else {
			// Direct person ID lookup.
			foreach ( $ids as $person_id ) {
				$results[ $person_id ] = self::get_person_for_zqpm( $person_id, 'person' );
			}
		}

		return $results;
	}
}
