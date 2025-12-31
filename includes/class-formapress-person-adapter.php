<?php
/**
 * FormaPress Person Adapter
 *
 * Compatibility layer between v1 and v2 person systems.
 * Provides v1-compatible interfaces while using v2 data sources.
 *
 * This adapter allows ZQPM and other v1 code to work without modification
 * by converting v2 crm_person data into v1-compatible objects.
 *
 * @package FormaPress_CRM
 * @subpackage Compatibility
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Company V1 Compatible Wrapper
 *
 * Wraps v2 company data and adds methods for v1 compatibility.
 * This allows v1 code to call methods like get_infos_session() on v2 company objects.
 */
class FormaPress_Company_V1_Wrapper {
	/**
	 * All company properties are stored dynamically.
	 */
	private $data;

	/**
	 * Constructor
	 *
	 * @param object $company_data Company data object from adapter.
	 */
	public function __construct( $company_data ) {
		$this->data = $company_data;
	}

	/**
	 * Magic getter for all properties.
	 *
	 * @param string $name Property name.
	 * @return mixed Property value.
	 */
	public function __get( $name ) {
		if ( isset( $this->data->$name ) ) {
			$value = $this->data->$name;
			// Ensure arrays are returned as arrays (not wrapped).
			if ( is_array( $value ) ) {
				return $value;
			}
			return $value;
		}
		return null;
	}

	/**
	 * Magic setter for all properties.
	 *
	 * @param string $name  Property name.
	 * @param mixed  $value Property value.
	 */
	public function __set( $name, $value ) {
		$this->data->$name = $value;
	}

	/**
	 * Magic isset check.
	 *
	 * @param string $name Property name.
	 * @return bool Whether property exists.
	 */
	public function __isset( $name ) {
		return isset( $this->data->$name );
	}

	/**
	 * Get all properties as an array for v1 compatibility.
	 *
	 * @return array All company properties.
	 */
	public function get_properties() {
		return get_object_vars( $this->data );
	}

	/**
	 * Get session info for this company in a ZQPM.
	 *
	 * Mimics zqpmEntreprise::get_infos_session() method.
	 *
	 * @param int $zqp_id The ZQPM post ID.
	 * @return array Session info array.
	 */
	public function get_infos_session( $zqp_id ) {
		if ( function_exists( 'get_zqpmmeta' ) && ! empty( $this->data->ID ) ) {
			$zqpmmeta = get_zqpmmeta( $zqp_id, $this->data->ID, 'entreprise_infos_session' );
			if ( is_array( $zqpmmeta ) && ! empty( $zqpmmeta ) && ! empty( $zqpmmeta[0]->meta_value ) ) {
				return $zqpmmeta[0]->meta_value;
			}
		}
		return array();
	}
}

/**
 * Person Adapter Class
 *
 * Provides static methods to get persons in v1-compatible format from v2 sources.
 */
class FormaPress_Person_Adapter {

	/**
	 * Get trainee/stagiaire data (v1-compatible)
	 *
	 * Returns an object compatible with zRegistration_infos structure.
	 * Works with both v1 registration IDs and v2 person IDs.
	 *
	 * @param int    $id      Registration ID (v1) or Person ID (v2).
	 * @param string $id_type 'registration' (default) or 'person'.
	 * @return object|false V1-compatible trainee object or false if not found.
	 */
	public static function get_trainee( $id, $id_type = 'registration' ) {
		// Try v2 first.
		$person_data = FormaPress_Person_Manager::get_person_for_zqpm( $id, $id_type );

		if ( ! empty( $person_data ) ) {
			return self::format_trainee_v1_compatible( $person_data );
		}

		// Fallback to v1 if person not migrated.
		if ( 'registration' === $id_type && class_exists( 'zRegistration_infos' ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'zform_registrations';
			$result     = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE id = %d",
					$id
				)
			);

			if ( $result ) {
				return new zRegistration_infos( $id );
			}
		}

		return false;
	}

	/**
	 * Get instructor data (v1-compatible)
	 *
	 * Returns an object compatible with zInstructor_infos structure.
	 * Works with both v1 instructor post IDs and v2 person IDs.
	 *
	 * @param int    $id      Instructor post ID (v1) or Person ID (v2).
	 * @param string $id_type 'instructor' (default) or 'person'.
	 * @return object|false V1-compatible instructor object or false if not found.
	 */
	public static function get_instructor( $id, $id_type = 'instructor' ) {
		global $wpdb;

		// Convert v1 instructor ID to v2 person ID if needed.
		if ( 'instructor' === $id_type ) {
			$person_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta}
					 WHERE meta_key = '_crm_v1_instructor_id'
					 AND meta_value = %d
					 LIMIT 1",
					$id
				)
			);
			$v1_id     = $id;
		} else {
			$person_id = $id;
			$v1_id     = get_post_meta( $id, '_crm_v1_instructor_id', true );
		}

		if ( $person_id ) {
			// Get v2 person data.
			$person_data = self::get_person_data( $person_id, 'instructor' );

			if ( ! empty( $person_data ) ) {
				return self::format_instructor_v1_compatible( $person_data, $v1_id );
			}
		}

		// Fallback to v1 if not migrated.
		if ( 'instructor' === $id_type && class_exists( 'zInstructor_infos' ) ) {
			$post = get_post( $id );
			if ( $post && 'zform_instructor' === $post->post_type ) {
				return new zInstructor_infos( $id );
			}
		}

		return false;
	}

	/**
	 * Get company/entreprise data (v1-compatible)
	 *
	 * Returns an object compatible with zqpmEntreprise structure.
	 * Works with v1 entreprise post IDs, v2 crm_company IDs, or v2 person IDs.
	 *
	 * @param int    $id      Entreprise post ID (v1), crm_company ID (v2), or Person ID (v2).
	 * @param string $id_type 'entreprise' (v1), 'company' (v2 crm_company), or 'person' (v2 crm_person).
	 * @return object|false V1-compatible company object or false if not found.
	 */
	public static function get_company( $id, $id_type = 'entreprise' ) {
		global $wpdb;

		// Handle v2 crm_company CPT directly.
		if ( 'company' === $id_type ) {
			$post = get_post( $id );
			if ( $post && 'crm_company' === $post->post_type ) {
				return self::format_crm_company_v1_compatible( $id );
			}
			return false;
		}

		// Convert v1 entreprise ID to v2 ID if needed.
		if ( 'entreprise' === $id_type ) {
			// First check if it's already a v2 crm_company.
			$post = get_post( $id );
			if ( $post && 'crm_company' === $post->post_type ) {
				return self::format_crm_company_v1_compatible( $id );
			}

			// Try to find migrated v2 crm_company.
			$company_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta}
					 WHERE meta_key = '_crm_v1_entreprise_id'
					 AND meta_value = %d
					 LIMIT 1",
					$id
				)
			);

			if ( $company_id ) {
				return self::format_crm_company_v1_compatible( $company_id );
			}

			// Fallback to v1 if not migrated.
			if ( class_exists( 'zqpmEntreprise' ) ) {
				if ( $post && 'zqpm_entreprise' === $post->post_type ) {
					return new zqpmEntreprise( $id );
				}
			}
		}

		// Handle v2 person (legacy - companies should be crm_company now).
		if ( 'person' === $id_type ) {
			$person_data = self::get_person_data( $id, 'company' );
			if ( ! empty( $person_data ) ) {
				$v1_id = get_post_meta( $id, '_crm_v1_entreprise_id', true );
				return self::format_company_v1_compatible( $person_data, $v1_id );
			}
		}

		return false;
	}

	/**
	 * Get referent data (v1-compatible)
	 *
	 * Returns an object compatible with zqpmReferent structure.
	 *
	 * @param int $person_id  Person ID.
	 * @param int $company_id Company person ID (for context).
	 * @return object|false V1-compatible referent object or false if not found.
	 */
	public static function get_referent( $person_id, $company_id = null ) {
		$person_data = self::get_person_data( $person_id, 'referent' );

		if ( ! empty( $person_data ) ) {
			return self::format_referent_v1_compatible( $person_data, $company_id );
		}

		return false;
	}

	/**
	 * Get all instructors for a session (v2-compatible query)
	 *
	 * @param int $session_id Session post ID.
	 * @return array Array of v1-compatible instructor objects.
	 */
	public static function get_session_instructors( $session_id ) {
		global $wpdb;

		// Query v2 crm_person records with person_type=instructor linked to this session.
		// Instructors are linked via _crm_session_ids meta (serialized array of session IDs).
		// The array can contain integers or strings, so we search for both patterns.
		$query = $wpdb->prepare(
			"SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
			INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			WHERE p.post_type = 'crm_person'
			AND p.post_status = 'publish'
			AND tt.taxonomy = 'person_type'
			AND t.slug = 'instructor'
			AND pm.meta_key = '_crm_session_ids'
			AND (pm.meta_value LIKE %s OR pm.meta_value LIKE %s)",
			'%i:' . intval( $session_id ) . ';%',  // Match integer in array: i:15815;
			'%s:' . strlen( $session_id ) . ':"' . $wpdb->esc_like( $session_id ) . '";%'  // Match string in array: s:5:"15815";
		);

		$person_ids  = $wpdb->get_col( $query );
		$instructors = array();

		foreach ( $person_ids as $person_id ) {
			$instructor = self::get_instructor( $person_id, 'person' );
			if ( $instructor ) {
				$instructors[] = $instructor;
			}
		}

		return $instructors;
	}

	/**
	 * Get all trainees for a session (v2-compatible query)
	 *
	 * @param int   $session_id Session post ID.
	 * @param array $args       Additional query args.
	 * @return array Array of v1-compatible trainee objects.
	 */
	public static function get_session_trainees( $session_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'  => 'any',
			'orderby' => 'name',
			'order'   => 'ASC',
		);
		$args     = wp_parse_args( $args, $defaults );

		// Query v1 registrations table (still used for session links).
		$table_name = $wpdb->prefix . 'zform_registrations';
		$query      = $wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE session_id = %d ORDER BY id ASC",
			$session_id
		);

		$registration_ids = $wpdb->get_col( $query );
		$trainees         = array();

		foreach ( $registration_ids as $reg_id ) {
			$trainee = self::get_trainee( $reg_id, 'registration' );
			if ( $trainee ) {
				$trainees[] = $trainee;
			}
		}

		return $trainees;
	}

	/**
	 * Get person data from v2 system
	 *
	 * @param int    $person_id   Person post ID.
	 * @param string $person_type Expected person type for validation.
	 * @return array Person data array.
	 */
	private static function get_person_data( $person_id, $person_type = null ) {
		$person = get_post( $person_id );

		if ( ! $person || 'crm_person' !== $person->post_type ) {
			return array();
		}

		// Validate person type if specified.
		if ( $person_type ) {
			$types = wp_get_post_terms( $person_id, 'person_type', array( 'fields' => 'slugs' ) );
			if ( ! in_array( $person_type, $types, true ) ) {
				return array();
			}
		}

		// Get all meta data.
		$all_meta = get_post_meta( $person_id );
		$data     = array(
			'person_id' => $person_id,
			'post'      => $person,
		);

		// Extract core fields.
		$core_fields = array(
			'civilite',
			'nom',
			'prenom',
			'email',
			'telephone',
			'adresse',
			'cp',
			'ville',
			'company_id',
			'v1_registration_id',
			'v1_instructor_id',
			'v1_entreprise_id',
		);

		foreach ( $core_fields as $field ) {
			$meta_key       = '_crm_' . $field;
			$data[ $field ] = isset( $all_meta[ $meta_key ][0] ) ? $all_meta[ $meta_key ][0] : '';
		}

		// Extract attributes based on person type.
		$attributes = array();
		$prefix     = 'crm_person_' . $person_type . '_attributes_';

		foreach ( $all_meta as $meta_key => $meta_value ) {
			if ( 0 === strpos( $meta_key, $prefix ) ) {
				$field_slug                = str_replace( $prefix, '', $meta_key );
				$attributes[ $field_slug ] = isset( $meta_value[0] ) ? $meta_value[0] : '';
			}
		}

		$data['attributes'] = $attributes;

		return $data;
	}

	/**
	 * Format trainee data as v1-compatible object
	 *
	 * Creates an object that mimics zRegistration_infos structure.
	 *
	 * @param array $person_data Person data from v2.
	 * @return object V1-compatible trainee object.
	 */
	private static function format_trainee_v1_compatible( $person_data ) {
		$trainee = new stdClass();

		// Core IDs.
		$trainee->id                 = $person_data['person_id']; // Use person_id for v2 trainees.
		$trainee->person_id          = $person_data['person_id'];
		$trainee->v1_registration_id = ! empty( $person_data['registration_id'] ) ? intval( $person_data['registration_id'] ) : 0;
		$trainee->registration_id    = $trainee->id;

		// Personal info - expose with both v1 and v2 naming conventions.
		$trainee->civilite  = $person_data['civilite'];
		$trainee->nom       = $person_data['nom'];
		$trainee->name      = $person_data['nom']; // Alias for ZQPM.
		$trainee->prenom    = $person_data['prenom'];
		$trainee->firstname = $person_data['prenom']; // Alias for ZQPM.
		$trainee->email     = $person_data['email'];
		$trainee->mail      = $person_data['email']; // Alias for ZQPM.
		$trainee->telephone = $person_data['telephone'];

		// Address.
		$trainee->adresse = $person_data['adresse'];
		$trainee->cp      = $person_data['cp'];
		$trainee->ville   = $person_data['ville'];

		// Company - expose as both company_id and societe for ZQPM form.
		$trainee->company_id = $person_data['company_id'];
		$trainee->societe    = $person_data['company_id']; // ZQPM uses 'societe' in forms.

		// Expose ALL attributes as direct properties for dynamic form access.
		// This allows $stagiaire->{'validation-rgpd'}, $stagiaire->visio, etc.
		if ( ! empty( $person_data['attributes'] ) ) {
			foreach ( $person_data['attributes'] as $attr_key => $attr_value ) {
				$trainee->{$attr_key} = $attr_value;
			}
		}

		// Also keep attributes array for backward compatibility.
		$trainee->attributs = $person_data['attributes'];

		// Legacy v1 fields (will be empty for v2-only data).
		$trainee->session_id   = 0;
		$trainee->formation_id = 0;
		$trainee->datas        = null;
		$trainee->status       = 1;

		// Flag for adapter usage.
		$trainee->_is_v2_adapted = true;

		return $trainee;
	}

	/**
	 * Format instructor data as v1-compatible object
	 *
	 * Creates an object that mimics zInstructor_infos structure.
	 *
	 * @param array $person_data Person data from v2.
	 * @param int   $v1_id       Original v1 instructor post ID.
	 * @return object V1-compatible instructor object.
	 */
	private static function format_instructor_v1_compatible( $person_data, $v1_id = null ) {
		$instructor = new stdClass();

		// Core IDs.
		$instructor->id               = $person_data['person_id']; // Always use v2 person_id.
		$instructor->person_id        = $person_data['person_id'];
		$instructor->ID               = $person_data['person_id']; // Uppercase for post ID compat.
		$instructor->v1_instructor_id = $v1_id ? intval( $v1_id ) : 0; // Keep v1 reference if available.

		// Personal info.
		$instructor->civilite  = $person_data['civilite'];
		$instructor->nom       = $person_data['nom'];
		$instructor->name      = $person_data['nom'];
		$instructor->prenom    = $person_data['prenom'];
		$instructor->firstname = $person_data['prenom'];
		$instructor->email     = $person_data['email'];
		$instructor->mail      = $person_data['email'];
		$instructor->telephone = $person_data['telephone'];

		// Address.
		$instructor->adresse = $person_data['adresse'];
		$instructor->cp      = $person_data['cp'];
		$instructor->ville   = $person_data['ville'];

		// Attributes.
		$instructor->attributs = $person_data['attributes'];

		// Post title (for compatibility with post references).
		$instructor->post_title = $person_data['prenom'] . ' ' . $person_data['nom'];

		// Flag for adapter usage.
		$instructor->_is_v2_adapted = true;

		return $instructor;
	}

	/**
	 * Format company data as v1-compatible object
	 *
	 * Creates an object that mimics zqpmEntreprise structure.
	 *
	 * @param array $person_data Person data from v2.
	 * @param int   $v1_id       Original v1 entreprise post ID.
	 * @return object V1-compatible company object.
	 */
	private static function format_company_v1_compatible( $person_data, $v1_id = null ) {
		$company = new stdClass();

		// Core IDs.
		$company->id               = $v1_id ? intval( $v1_id ) : 0;
		$company->person_id        = $person_data['person_id'];
		$company->ID               = $v1_id ? intval( $v1_id ) : 0;
		$company->v1_entreprise_id = $company->id;

		// Company info (nom is company name for companies).
		$company->nom            = $person_data['nom'];
		$company->name           = $person_data['nom'];
		$company->raison_sociale = $person_data['nom'];
		$company->email          = $person_data['email'];
		$company->telephone      = $person_data['telephone'];

		// Address.
		$company->adresse = $person_data['adresse'];
		$company->cp      = $person_data['cp'];
		$company->ville   = $person_data['ville'];

		// Attributes.
		$company->attributs = $person_data['attributes'];

		// Referents (will be loaded separately).
		$company->referent = array();

		// Load referents linked to this company.
		$referents = get_posts(
			array(
				'post_type'  => 'crm_person',
				'meta_query' => array(
					array(
						'key'   => '_crm_company_id',
						'value' => $person_data['person_id'],
					),
				),
				'tax_query'  => array(
					array(
						'taxonomy' => 'person_type',
						'field'    => 'slug',
						'terms'    => 'referent',
					),
				),
				'fields'     => 'ids',
			)
		);

		foreach ( $referents as $ref_id ) {
			$ref_data = self::get_person_data( $ref_id, 'referent' );
			if ( ! empty( $ref_data ) ) {
				$ref_obj = self::format_referent_v1_compatible( $ref_data, $person_data['person_id'] );
				// Use email as key (v1 compat).
				$key                       = ! empty( $ref_obj->email ) ? $ref_obj->email : $ref_id;
				$company->referent[ $key ] = $ref_obj;
			}
		}

		// Flag for adapter usage.
		$company->_is_v2_adapted = true;

		return $company;
	}

	/**
	 * Format crm_company CPT data as v1-compatible object
	 *
	 * Creates an object that mimics zqpmEntreprise structure for crm_company CPT.
	 *
	 * @param int $company_id The crm_company post ID.
	 * @return object V1-compatible company object.
	 */
	private static function format_crm_company_v1_compatible( $company_id ) {
		$post = get_post( $company_id );
		if ( ! $post || 'crm_company' !== $post->post_type ) {
			return false;
		}

		$company = new stdClass();

		// Core IDs.
		$company->id         = intval( $company_id );
		$company->ID         = intval( $company_id );
		$company->company_id = intval( $company_id );

		// Company info from post and meta.
		$company->raison_sociale = get_the_title( $company_id );
		$company->nom            = $company->raison_sociale;
		$company->name           = $company->raison_sociale;

		// Try new attribute keys first, fallback to old _crm_ keys.
		$company->siret     = get_post_meta( $company_id, 'crm_company_attributes_siret', true ) ?: get_post_meta( $company_id, '_crm_siret', true );
		$company->email     = get_post_meta( $company_id, 'crm_company_attributes_email', true ) ?: get_post_meta( $company_id, '_crm_email', true );
		$company->telephone = get_post_meta( $company_id, 'crm_company_attributes_telephone', true ) ?: get_post_meta( $company_id, '_crm_telephone', true );
		$company->site      = get_post_meta( $company_id, 'crm_company_attributes_site', true ) ?: get_post_meta( $company_id, '_crm_site_web', true );

		// Address.
		$company->adresse   = get_post_meta( $company_id, 'crm_company_attributes_adresse', true ) ?: get_post_meta( $company_id, '_crm_adresse', true );
		$company->cp        = get_post_meta( $company_id, 'crm_company_attributes_cp', true ) ?: get_post_meta( $company_id, '_crm_code_postal', true );
		$company->ville     = get_post_meta( $company_id, 'crm_company_attributes_ville', true ) ?: get_post_meta( $company_id, '_crm_ville', true );     // Load all attributes for v1 compatibility.
		$company->attributs = array();
		$all_meta           = get_post_meta( $company_id );
		foreach ( $all_meta as $key => $values ) {
			if ( strpos( $key, 'crm_company_attributes_' ) === 0 ) {
				$attr_key                        = str_replace( 'crm_company_attributes_', '', $key );
				$company->attributs[ $attr_key ] = isset( $values[0] ) ? $values[0] : '';
			}
		}

		// Referents - query v2 crm_person records with person_type=company_contact.
		$company->referent = array();

		// Query for referents linked to this company.
		$referent_ids = get_posts(
			array(
				'post_type'      => 'crm_person',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_crm_company_id',
						'value' => $company_id,
					),
				),
				'tax_query'      => array(
					array(
						'taxonomy' => 'person_type',
						'field'    => 'slug',
						'terms'    => 'company_contact',
					),
				),
			)
		);

		foreach ( $referent_ids as $ref_id ) {
			$referent = new stdClass();

			// Core fields.
			$referent->id            = $ref_id;
			$referent->person_id     = $ref_id;
			$referent->entreprise_id = $company_id;
			$referent->civilite      = get_post_meta( $ref_id, '_crm_civilite', true );
			$referent->prenom        = get_post_meta( $ref_id, '_crm_prenom', true );
			$referent->nom           = get_post_meta( $ref_id, '_crm_nom', true );
			$referent->mail          = get_post_meta( $ref_id, '_crm_email', true );
			$referent->tel           = get_post_meta( $ref_id, '_crm_telephone', true );
			$referent->poste         = get_post_meta( $ref_id, '_crm_fonction', true );

			// Also check attributes.
			if ( empty( $referent->tel ) ) {
				$referent->tel = get_post_meta( $ref_id, 'crm_person_company_contact_attributes_tel', true );
			}
			if ( empty( $referent->poste ) ) {
				$referent->poste = get_post_meta( $ref_id, 'crm_person_company_contact_attributes_poste', true );
			}

			// Check for tokens_infos (needed for step-send).
			$referent->tokens_infos = array();

			// Add to array with numeric index (v1 form compatibility).
			$company->referent[] = $referent;
		}       // Check if this was migrated from v1.
		$v1_id = get_post_meta( $company_id, '_crm_v1_entreprise_id', true );
		if ( $v1_id ) {
			$company->v1_entreprise_id = intval( $v1_id );
		}

		// Flag for adapter usage.
		$company->_is_v2_adapted      = true;
		$company->_is_crm_company_cpt = true;

		// Wrap in v1-compatible wrapper that adds methods.
		return new FormaPress_Company_V1_Wrapper( $company );
	}   /**
		 * Format referent data as v1-compatible object
		 *
		 * Creates an object that mimics zqpmReferent structure.
		 *
		 * @param array $person_data Person data from v2.
		 * @param int   $company_id  Company person ID.
		 * @return object V1-compatible referent object.
		 */
	private static function format_referent_v1_compatible( $person_data, $company_id = null ) {
		$referent = new stdClass();

		// Core IDs.
		$referent->id            = $person_data['person_id'];
		$referent->person_id     = $person_data['person_id'];
		$referent->entreprise_id = $company_id ? intval( $company_id ) : intval( $person_data['company_id'] );

		// Personal info.
		$referent->civilite  = $person_data['civilite'];
		$referent->nom       = $person_data['nom'];
		$referent->prenom    = $person_data['prenom'];
		$referent->email     = $person_data['email'];
		$referent->telephone = $person_data['telephone'];

		// Tokens (will be from v2 token table).
		$referent->tokens_infos = array();

		// Flag for adapter usage.
		$referent->_is_v2_adapted = true;

		return $referent;
	}

	/**
	 * Check if a person has been migrated to v2
	 *
	 * @param int    $id      ID to check.
	 * @param string $id_type Type: 'registration', 'instructor', 'entreprise'.
	 * @return bool True if migrated to v2.
	 */
	public static function is_migrated_to_v2( $id, $id_type = 'registration' ) {
		global $wpdb;

		$meta_key_map = array(
			'registration' => '_crm_v1_registration_id',
			'instructor'   => '_crm_v1_instructor_id',
			'entreprise'   => '_crm_v1_entreprise_id',
		);

		$meta_key = isset( $meta_key_map[ $id_type ] ) ? $meta_key_map[ $id_type ] : $meta_key_map['registration'];

		$person_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = %s
				 AND meta_value = %d
				 LIMIT 1",
				$meta_key,
				$id
			)
		);

		return ! empty( $person_id );
	}
}
