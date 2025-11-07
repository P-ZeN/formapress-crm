<?php
/**
 * FormaPress Shortcode Manager
 *
 * Auto-registers shortcodes for all dynamic attributes across all entity types.
 * Replaces v1's manual shortcode registration with automatic schema-driven system.
 *
 * @package FormaPress_CRM
 * @subpackage Shortcodes
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FormaPress_Shortcode_Manager {

	/**
	 * Initialize shortcode manager
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_attribute_shortcodes' ), 20 );
	}

	/**
	 * Auto-register shortcodes for all person types and their attributes
	 *
	 * This runs on init hook and creates shortcodes like:
	 * - [crm_trainee_niveau_etudes]
	 * - [crm_instructor_biographie]
	 * - [crm_referent_fonction]
	 * - [crm_company_secteur_activite]
	 */
	public static function register_attribute_shortcodes() {
		// Register person type attribute shortcodes.
		$person_types = array( 'trainee', 'instructor', 'referent', 'prospect', 'funder' );

		foreach ( $person_types as $type ) {
			self::register_person_type_shortcodes( $type );
		}

		// Register company attribute shortcodes.
		self::register_company_shortcodes();

		// Register opportunity attribute shortcodes (future).
		self::register_opportunity_shortcodes();
	}

	/**
	 * Register shortcodes for a specific person type
	 *
	 * @param string $type Person type (trainee, instructor, referent, prospect, funder).
	 */
	private static function register_person_type_shortcodes( $type ) {
		$schema = self::get_schema_for_person_type( $type );

		if ( empty( $schema ) ) {
			return;
		}

		foreach ( $schema as $field_slug => $field_config ) {
			// For trainee type only, skip core fields - they have dedicated shortcodes in v1.
			// For other types (instructor, referent, etc), all schema fields are attributes.
			if ( 'trainee' === $type && self::is_core_field( $field_slug ) ) {
				continue;
			}

			$shortcode_name = "crm_{$type}_{$field_slug}";

			add_shortcode(
				$shortcode_name,
				function ( $atts ) use ( $field_slug, $field_config, $type ) {
					return self::render_person_attribute_shortcode( $field_slug, $field_config, $type, $atts );
				}
			);
		}
	}

	/**
	 * Register company attribute shortcodes
	 */
	private static function register_company_shortcodes() {
		$schema = get_option( 'crm_company_attributes', array() );

		if ( empty( $schema ) ) {
			return;
		}

		foreach ( $schema as $field_slug => $field_config ) {
			$shortcode_name = "crm_company_{$field_slug}";

			add_shortcode(
				$shortcode_name,
				function ( $atts ) use ( $field_slug, $field_config ) {
					return self::render_company_attribute_shortcode( $field_slug, $field_config, $atts );
				}
			);
		}
	}

	/**
	 * Register opportunity attribute shortcodes
	 */
	private static function register_opportunity_shortcodes() {
		$schema = get_option( 'crm_opportunity_attributes', array() );

		if ( empty( $schema ) ) {
			return;
		}

		foreach ( $schema as $field_slug => $field_config ) {
			$shortcode_name = "crm_opportunity_{$field_slug}";

			add_shortcode(
				$shortcode_name,
				function ( $atts ) use ( $field_slug, $field_config ) {
					return self::render_opportunity_attribute_shortcode( $field_slug, $field_config, $atts );
				}
			);
		}
	}

	/**
	 * Get schema for person type
	 *
	 * Maps person types to their schema option names.
	 * Supports both v1 options (trainees, instructors) and v2 options (new types).
	 *
	 * @param string $type Person type.
	 * @return array Schema array or empty array if not found.
	 */
	private static function get_schema_for_person_type( $type ) {
		$option_map = array(
			'trainee'    => 'zform_registrations',              // v1 trainee registration form schema.
			'instructor' => 'zform_Instructors_attributes',     // v1 instructor attributes schema.
			'referent'   => 'crm_person_referent_attributes',   // v2 company contact schema.
			'prospect'   => 'crm_person_prospect_attributes',   // v2 sales prospect schema.
			'funder'     => 'crm_person_funder_attributes',     // v2 funder/OPCO schema.
		);

		$option_name = isset( $option_map[ $type ] ) ? $option_map[ $type ] : null;

		if ( ! $option_name ) {
			return array();
		}

		return get_option( $option_name, array() );
	}

	/**
	 * Check if a field is a core field (not an attribute)
	 *
	 * Core fields like 'name', 'firstname', 'mail' are handled separately
	 * and should not be registered as attribute shortcodes.
	 *
	 * @param string $field_slug Field slug.
	 * @return bool True if core field.
	 */
	private static function is_core_field( $field_slug ) {
		$core_fields = array(
			'name',
			'firstname',
			'mail',
			'telephone',
			'civilite',
			'societe',
			'adresse',
			'cp',
			'ville',
		);

		return in_array( $field_slug, $core_fields, true );
	}

	/**
	 * Render person attribute shortcode
	 *
	 * @param string $field_slug   Attribute field slug.
	 * @param array  $field_config Field configuration from schema.
	 * @param string $type         Person type (trainee, instructor, etc).
	 * @param array  $atts         Shortcode attributes.
	 * @return string Rendered value.
	 */
	private static function render_person_attribute_shortcode( $field_slug, $field_config, $type, $atts ) {
		global $post;

		// Parse shortcode attributes.
		$atts = shortcode_atts(
			array(
				'person_id' => 0,
				'format'    => '',
			),
			$atts
		);

		// Get person ID from atts or current post.
		$person_id = intval( $atts['person_id'] );
		if ( ! $person_id && isset( $post->ID ) ) {
			$person_id = $post->ID;
		}

		if ( ! $person_id ) {
			return '';
		}

		// Read from exploded meta key.
		$meta_key = "crm_person_{$type}_attributs_{$field_slug}";
		$value    = get_post_meta( $person_id, $meta_key, true );

		// Format and return.
		return self::format_attribute_value( $value, $field_config, $atts );
	}

	/**
	 * Render company attribute shortcode
	 *
	 * @param string $field_slug   Attribute field slug.
	 * @param array  $field_config Field configuration from schema.
	 * @param array  $atts         Shortcode attributes.
	 * @return string Rendered value.
	 */
	private static function render_company_attribute_shortcode( $field_slug, $field_config, $atts ) {
		global $post;

		$atts = shortcode_atts(
			array(
				'company_id' => 0,
				'format'     => '',
			),
			$atts
		);

		$company_id = intval( $atts['company_id'] );
		if ( ! $company_id && isset( $post->ID ) ) {
			$company_id = $post->ID;
		}

		if ( ! $company_id ) {
			return '';
		}

		$meta_key = "crm_company_attributs_{$field_slug}";
		$value    = get_post_meta( $company_id, $meta_key, true );

		return self::format_attribute_value( $value, $field_config, $atts );
	}

	/**
	 * Render opportunity attribute shortcode
	 *
	 * @param string $field_slug   Attribute field slug.
	 * @param array  $field_config Field configuration from schema.
	 * @param array  $atts         Shortcode attributes.
	 * @return string Rendered value.
	 */
	private static function render_opportunity_attribute_shortcode( $field_slug, $field_config, $atts ) {
		global $post;

		$atts = shortcode_atts(
			array(
				'opportunity_id' => 0,
				'format'         => '',
			),
			$atts
		);

		$opportunity_id = intval( $atts['opportunity_id'] );
		if ( ! $opportunity_id && isset( $post->ID ) ) {
			$opportunity_id = $post->ID;
		}

		if ( ! $opportunity_id ) {
			return '';
		}

		$meta_key = "crm_opportunity_attributs_{$field_slug}";
		$value    = get_post_meta( $opportunity_id, $meta_key, true );

		return self::format_attribute_value( $value, $field_config, $atts );
	}

	/**
	 * Format attribute value based on field type
	 *
	 * Handles different field types from v1 registration system:
	 * - text, number, tel, email: Plain text
	 * - textarea, wyswyg: Paragraphs
	 * - date: Formatted date
	 * - checkbox: Yes/No
	 * - select, radio: Selected value
	 * - list: Unordered list
	 * - download: Download link
	 * - image: Image tag
	 * - video: Video embed (future)
	 *
	 * @param mixed $value        Attribute value from meta.
	 * @param array $field_config Field configuration.
	 * @param array $atts         Shortcode attributes.
	 * @return string Formatted value.
	 */
	private static function format_attribute_value( $value, $field_config, $atts ) {
		if ( empty( $value ) && '0' !== $value ) {
			return '';
		}

		$type = isset( $field_config['type'] ) ? $field_config['type'] : 'text';

		switch ( $type ) {
			case 'textarea':
				// Simple textarea - add paragraph tags.
				return wpautop( $value );

			case 'wyswyg':
				// WYSIWYG editor - may contain HTML, apply content filters.
				return apply_filters( 'the_content', $value );

			case 'date':
				// Format date.
				$format    = ! empty( $atts['format'] ) ? $atts['format'] : get_option( 'date_format' );
				$timestamp = strtotime( $value );
				if ( $timestamp ) {
					return date_i18n( $format, $timestamp );
				}
				return esc_html( $value );

			case 'checkbox':
				// Convert to Yes/No.
				if ( '1' === $value || 'yes' === strtolower( $value ) || 'oui' === strtolower( $value ) ) {
					return __( 'Yes', 'formapress-crm' );
				} elseif ( '0' === $value || 'no' === strtolower( $value ) || 'non' === strtolower( $value ) ) {
					return __( 'No', 'formapress-crm' );
				}
				return esc_html( $value );

			case 'download':
				// Create download link if URL.
				if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
					$filename = basename( $value );
					return sprintf(
						'<a href="%s" download class="crm-download-link">%s</a>',
						esc_url( $value ),
						esc_html( $filename )
					);
				}
				return esc_html( $value );

			case 'image':
				// Display image if URL.
				if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
					return sprintf(
						'<img src="%s" alt="" class="crm-attribute-image">',
						esc_url( $value )
					);
				}
				// Check if it's an attachment ID.
				if ( is_numeric( $value ) ) {
					$image = wp_get_attachment_image( intval( $value ), 'medium', false, array( 'class' => 'crm-attribute-image' ) );
					if ( $image ) {
						return $image;
					}
				}
				return esc_html( $value );

			case 'video':
				// Embed video if URL (YouTube, Vimeo, etc).
				if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
					$embed = wp_oembed_get( $value );
					if ( $embed ) {
						return $embed;
					}
					return sprintf(
						'<a href="%s" target="_blank" class="crm-video-link">%s</a>',
						esc_url( $value ),
						esc_html__( 'Watch Video', 'formapress-crm' )
					);
				}
				return esc_html( $value );

			case 'list':
				// v1 stores lists as line-separated strings - convert to HTML list.
				$items = explode( "\n", $value );
				$items = array_map( 'trim', $items );
				$items = array_filter( $items ); // Remove empty lines.

				if ( empty( $items ) ) {
					return '';
				}

				$output = '<ul class="crm-attribute-list">';
				foreach ( $items as $item ) {
					$output .= '<li>' . esc_html( $item ) . '</li>';
				}
				$output .= '</ul>';
				return $output;

			case 'select':
			case 'radio':
				// Selected value - just display as text.
				return esc_html( $value );

			case 'number':
			case 'tel':
			case 'email':
			case 'text':
			default:
				// Plain text - escape and return.
				return esc_html( $value );
		}
	}

	/**
	 * Get all registered attribute shortcodes
	 *
	 * Useful for debugging and documentation.
	 *
	 * @return array Array of shortcode names.
	 */
	public static function get_registered_attribute_shortcodes() {
		global $shortcode_tags;

		$attribute_shortcodes = array();

		foreach ( $shortcode_tags as $tag => $callback ) {
			// Check if shortcode starts with crm_.
			if ( 0 === strpos( $tag, 'crm_' ) ) {
				$attribute_shortcodes[] = $tag;
			}
		}

		return $attribute_shortcodes;
	}

	/**
	 * Get shortcode documentation
	 *
	 * Returns available shortcodes with their descriptions.
	 *
	 * @return array Array of shortcode documentation.
	 */
	public static function get_shortcode_documentation() {
		$docs = array();

		// Person types.
		$person_types = array(
			'trainee'    => __( 'Trainee', 'formapress-crm' ),
			'instructor' => __( 'Instructor', 'formapress-crm' ),
			'referent'   => __( 'Company Contact', 'formapress-crm' ),
			'prospect'   => __( 'Prospect', 'formapress-crm' ),
			'funder'     => __( 'Funder', 'formapress-crm' ),
		);

		foreach ( $person_types as $type => $label ) {
			$schema = self::get_schema_for_person_type( $type );

			foreach ( $schema as $field_slug => $field_config ) {
				if ( self::is_core_field( $field_slug ) ) {
					continue;
				}

				$shortcode  = "crm_{$type}_{$field_slug}";
				$name       = isset( $field_config['name'] ) ? $field_config['name'] : ucfirst( $field_slug );
				$field_type = isset( $field_config['type'] ) ? $field_config['type'] : 'text';

				$docs[] = array(
					'shortcode' => "[{$shortcode}]",
					'entity'    => $label,
					'field'     => $name,
					'type'      => $field_type,
					'usage'     => "[{$shortcode}] or [{$shortcode} person_id=\"123\"]",
				);
			}
		}

		return $docs;
	}
}
