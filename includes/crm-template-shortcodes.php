<?php
/**
 * CRM Template Shortcodes
 *
 * Registers shortcodes for use in email and document templates.
 *
 * @package FormapressCRM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register CRM shortcodes for templates.
 */
function formapress_crm_register_template_shortcodes() {
	// Opportunity shortcodes.
	add_shortcode( 'crm_opportunity_title', 'formapress_crm_sc_opportunity_title' );
	add_shortcode( 'crm_opportunity_value', 'formapress_crm_sc_opportunity_value' );
	add_shortcode( 'crm_opportunity_value_formatted', 'formapress_crm_sc_opportunity_value_formatted' );
	add_shortcode( 'crm_opportunity_stage', 'formapress_crm_sc_opportunity_stage' );
	add_shortcode( 'crm_opportunity_close_date', 'formapress_crm_sc_opportunity_close_date' );
	add_shortcode( 'crm_opportunity_description', 'formapress_crm_sc_opportunity_description' );

	// Person shortcodes.
	add_shortcode( 'crm_person_nom', 'formapress_crm_sc_person_nom' );
	add_shortcode( 'crm_person_prenom', 'formapress_crm_sc_person_prenom' );
	add_shortcode( 'crm_person_email', 'formapress_crm_sc_person_email' );
	add_shortcode( 'crm_person_telephone', 'formapress_crm_sc_person_telephone' );
	add_shortcode( 'crm_person_nom_complet', 'formapress_crm_sc_person_nom_complet' );

	// Company shortcodes.
	add_shortcode( 'crm_company_name', 'formapress_crm_sc_company_name' );
	add_shortcode( 'crm_company_address', 'formapress_crm_sc_company_address' );
	add_shortcode( 'crm_company_address_full', 'formapress_crm_sc_company_address_full' );
	add_shortcode( 'crm_company_siret', 'formapress_crm_sc_company_siret' );
	add_shortcode( 'crm_company_email', 'formapress_crm_sc_company_email' );
	add_shortcode( 'crm_company_telephone', 'formapress_crm_sc_company_telephone' );
}
add_action( 'init', 'formapress_crm_register_template_shortcodes' );

/**
 * Get context opportunity ID from various sources.
 *
 * @return int|null Opportunity post ID or null if not found.
 */
function formapress_crm_get_context_opportunity_id() {
	// Check if explicitly set via global (used in template rendering).
	global $crm_context_opportunity_id;
	if ( isset( $crm_context_opportunity_id ) && $crm_context_opportunity_id > 0 ) {
		return (int) $crm_context_opportunity_id;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Context detection only, no data modification.
	if ( isset( $_GET['opportunity_id'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return (int) $_GET['opportunity_id'];
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Context detection only, no data modification.
	if ( isset( $_POST['opportunity_id'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return (int) $_POST['opportunity_id'];
	}

	return null;
}

/**
 * Get context person ID from various sources.
 *
 * @return int|null Person post ID or null if not found.
 */
function formapress_crm_get_context_person_id() {
	global $crm_context_person_id;
	if ( isset( $crm_context_person_id ) && $crm_context_person_id > 0 ) {
		return (int) $crm_context_person_id;
	}

	// Try to get from opportunity's primary contact.
	$opportunity_id = formapress_crm_get_context_opportunity_id();
	if ( $opportunity_id ) {
		$person_id = get_post_meta( $opportunity_id, '_crm_associated_person_id', true );
		if ( $person_id ) {
			return (int) $person_id;
		}
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Context detection only, no data modification.
	if ( isset( $_GET['person_id'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return (int) $_GET['person_id'];
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Context detection only, no data modification.
	if ( isset( $_POST['person_id'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return (int) $_POST['person_id'];
	}

	return null;
}

/**
 * Get context company ID from various sources.
 *
 * @return int|null Company post ID or null if not found.
 */
function formapress_crm_get_context_company_id() {
	global $crm_context_company_id;
	if ( isset( $crm_context_company_id ) && $crm_context_company_id > 0 ) {
		return (int) $crm_context_company_id;
	}

	// Try to get from opportunity's company.
	$opportunity_id = formapress_crm_get_context_opportunity_id();
	if ( $opportunity_id ) {
		$company_id = get_post_meta( $opportunity_id, '_crm_associated_company_id', true );
		if ( $company_id ) {
			return (int) $company_id;
		}
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Context detection only, no data modification.
	if ( isset( $_GET['company_id'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return (int) $_GET['company_id'];
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Context detection only, no data modification.
	if ( isset( $_POST['company_id'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return (int) $_POST['company_id'];
	}

	return null;
}

// ==================== OPPORTUNITY SHORTCODES ====================

/**
 * Shortcode: [crm_opportunity_title]
 */
function formapress_crm_sc_opportunity_title( $atts ) {
	$opportunity_id = formapress_crm_get_context_opportunity_id();
	if ( ! $opportunity_id ) {
		return '';
	}

	return get_the_title( $opportunity_id );
}

/**
 * Shortcode: [crm_opportunity_value]
 */
function formapress_crm_sc_opportunity_value( $atts ) {
	$opportunity_id = formapress_crm_get_context_opportunity_id();
	if ( ! $opportunity_id ) {
		return '';
	}

	$value = get_post_meta( $opportunity_id, '_formapress_crm_opportunity_value', true );
	return $value ? $value : '0';
}

/**
 * Shortcode: [crm_opportunity_value_formatted]
 */
function formapress_crm_sc_opportunity_value_formatted( $atts ) {
	$opportunity_id = formapress_crm_get_context_opportunity_id();
	if ( ! $opportunity_id ) {
		return '';
	}

	$value = get_post_meta( $opportunity_id, '_formapress_crm_opportunity_value', true );
	if ( ! $value ) {
		return '0 €';
	}

	return number_format( (float) $value, 2, ',', ' ' ) . ' €';
}

/**
 * Shortcode: [crm_opportunity_stage]
 */
function formapress_crm_sc_opportunity_stage( $atts ) {
	$opportunity_id = formapress_crm_get_context_opportunity_id();
	if ( ! $opportunity_id ) {
		return '';
	}

	$stage = get_post_meta( $opportunity_id, '_formapress_crm_opportunity_stage', true );
	if ( ! $stage ) {
		return '';
	}

	// Get stage labels.
	$stages = formapress_crm_get_opportunity_stages();
	return isset( $stages[ $stage ] ) ? $stages[ $stage ]['label'] : $stage;
}

/**
 * Shortcode: [crm_opportunity_close_date]
 */
function formapress_crm_sc_opportunity_close_date( $atts ) {
	$opportunity_id = formapress_crm_get_context_opportunity_id();
	if ( ! $opportunity_id ) {
		return '';
	}

	$close_date = get_post_meta( $opportunity_id, '_formapress_crm_opportunity_close_date', true );
	if ( ! $close_date ) {
		return '';
	}

	// Format date.
	$timestamp = strtotime( $close_date );
	return date_i18n( 'd/m/Y', $timestamp );
}

/**
 * Shortcode: [crm_opportunity_description]
 */
function formapress_crm_sc_opportunity_description( $atts ) {
	$opportunity_id = formapress_crm_get_context_opportunity_id();
	if ( ! $opportunity_id ) {
		return '';
	}

	$post = get_post( $opportunity_id );
	return $post ? $post->post_content : '';
}

// ==================== PERSON SHORTCODES ====================

/**
 * Shortcode: [crm_person_nom]
 */
function formapress_crm_sc_person_nom( $atts ) {
	$person_id = formapress_crm_get_context_person_id();
	if ( ! $person_id ) {
		return '';
	}

	if ( class_exists( 'FormaPress_Person_Manager' ) ) {
		$identity = FormaPress_Person_Manager::get_person_identity_from_attributes( $person_id );
		return $identity['nom'] ?? '';
	}

	return '';
}

/**
 * Shortcode: [crm_person_prenom]
 */
function formapress_crm_sc_person_prenom( $atts ) {
	$person_id = formapress_crm_get_context_person_id();
	if ( ! $person_id ) {
		return '';
	}

	if ( class_exists( 'FormaPress_Person_Manager' ) ) {
		$identity = FormaPress_Person_Manager::get_person_identity_from_attributes( $person_id );
		return $identity['prenom'] ?? '';
	}

	return '';
}

/**
 * Shortcode: [crm_person_nom_complet]
 */
function formapress_crm_sc_person_nom_complet( $atts ) {
	$person_id = formapress_crm_get_context_person_id();
	if ( ! $person_id ) {
		return '';
	}

	if ( class_exists( 'FormaPress_Person_Manager' ) ) {
		$identity = FormaPress_Person_Manager::get_person_identity_from_attributes( $person_id );
		$prenom   = $identity['prenom'] ?? '';
		$nom      = $identity['nom'] ?? '';

		$parts = array_filter( array( $prenom, $nom ) );
		return implode( ' ', $parts );
	}

	return '';
}

/**
 * Shortcode: [crm_person_email]
 */
function formapress_crm_sc_person_email( $atts ) {
	$person_id = formapress_crm_get_context_person_id();
	if ( ! $person_id ) {
		return '';
	}

	return get_post_meta( $person_id, '_crm_email', true );
}

/**
 * Shortcode: [crm_person_telephone]
 */
function formapress_crm_sc_person_telephone( $atts ) {
	$person_id = formapress_crm_get_context_person_id();
	if ( ! $person_id ) {
		return '';
	}

	return get_post_meta( $person_id, '_crm_telephone', true );
}

// ==================== COMPANY SHORTCODES ====================

/**
 * Shortcode: [crm_company_name]
 */
function formapress_crm_sc_company_name( $atts ) {
	$company_id = formapress_crm_get_context_company_id();
	if ( ! $company_id ) {
		return '';
	}

	return get_the_title( $company_id );
}

/**
 * Shortcode: [crm_company_address]
 */
function formapress_crm_sc_company_address( $atts ) {
	$company_id = formapress_crm_get_context_company_id();
	if ( ! $company_id ) {
		return '';
	}

	return get_post_meta( $company_id, '_crm_company_address', true );
}

/**
 * Shortcode: [crm_company_address_full]
 */
function formapress_crm_sc_company_address_full( $atts ) {
	$company_id = formapress_crm_get_context_company_id();
	if ( ! $company_id ) {
		return '';
	}

	$address = get_post_meta( $company_id, '_crm_company_address', true );
	$cp      = get_post_meta( $company_id, '_crm_company_postal_code', true );
	$ville   = get_post_meta( $company_id, '_crm_company_city', true );

	$parts = array_filter( array( $address, $cp . ' ' . $ville ) );
	return implode( '<br>', $parts );
}

/**
 * Shortcode: [crm_company_siret]
 */
function formapress_crm_sc_company_siret( $atts ) {
	$company_id = formapress_crm_get_context_company_id();
	if ( ! $company_id ) {
		return '';
	}

	return get_post_meta( $company_id, '_crm_company_siret', true );
}

/**
 * Shortcode: [crm_company_email]
 */
function formapress_crm_sc_company_email( $atts ) {
	$company_id = formapress_crm_get_context_company_id();
	if ( ! $company_id ) {
		return '';
	}

	return get_post_meta( $company_id, '_crm_company_email', true );
}

/**
 * Shortcode: [crm_company_telephone]
 */
function formapress_crm_sc_company_telephone( $atts ) {
	$company_id = formapress_crm_get_context_company_id();
	if ( ! $company_id ) {
		return '';
	}

	return get_post_meta( $company_id, '_crm_company_telephone', true );
}
