<?php
/**
 * FormaPress CRM - Attribute Sanitization Functions
 *
 * Sanitization functions for CRM attribute options to enforce immutable v1 core fields.
 * Pattern follows zformations/admin/options_default.php sanitization functions.
 *
 * @package FormapressCRM
 * @since 2.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Sanitize company attributes - enforce immutable v1 core fields.
 *
 * This function ensures that locked fields from v1 (raison-sociale, siret, adresse, cp, ville, site)
 * cannot be deleted or modified. New attributes can be added by admins.
 *
 * @param array $new_value Updated attribute configuration.
 * @param array $old_value Previous attribute configuration.
 * @return array Sanitized attributes with v1 core fields protected.
 */
function formapress_company_attributes_sanitize_options( $new_value, $old_value ) {
	// Get default locked fields from schema.
	$defaults = formapress_get_default_company_attributes();

	// Convert numeric keys to slugged keys.
	$new_array = array();
	foreach ( $new_value as $key => $value ) {
		if ( ! empty( $value['name'] ) ) {
			if ( is_numeric( $key ) ) {
				$new_array[ sanitize_title( $value['name'] ) ] = $value;
			} else {
				$new_array[ $key ] = $value;
			}
		}
	}
	$new_value = $new_array;

	// Enforce locked fields - restore from defaults if missing or modified.
	foreach ( $defaults as $field_key => $field_config ) {
		if ( ! empty( $field_config['locked'] ) && $field_config['locked'] ) {
			if ( ! isset( $new_value[ $field_key ] ) || empty( $new_value[ $field_key ] ) ) {
				// Field deleted - restore it.
				$new_value[ $field_key ] = $field_config;
			} else {
				// Field exists - enforce locked properties (name, type, required).
				$new_value[ $field_key ]['name']     = $field_config['name'];
				$new_value[ $field_key ]['type']     = $field_config['type'];
				$new_value[ $field_key ]['required'] = $field_config['required'];
				$new_value[ $field_key ]['locked']   = true; // Cannot be deleted.
				// Allow order and options to be modified by admin.
			}
		}
	}

	// Sort by order field.
	uasort(
		$new_value,
		function ( $a, $b ) {
			$order_a = isset( $a['order'] ) ? (int) $a['order'] : 999;
			$order_b = isset( $b['order'] ) ? (int) $b['order'] : 999;
			return $order_a > $order_b;
		}
	);

	return $new_value;
}

/**
 * Sanitize referent (contact) attributes - enforce immutable v1 core fields.
 *
 * This function ensures that locked fields from v1 (civilite, nom, prenom, poste, tel, mail)
 * cannot be deleted or modified. New attributes can be added by admins.
 *
 * @param array $new_value Updated attribute configuration.
 * @param array $old_value Previous attribute configuration.
 * @return array Sanitized attributes with v1 core fields protected.
 */
function formapress_referent_attributes_sanitize_options( $new_value, $old_value ) {
	// Get default locked fields from schema.
	$defaults = formapress_get_default_referent_attributes();

	// Convert numeric keys to slugged keys.
	$new_array = array();
	foreach ( $new_value as $key => $value ) {
		if ( ! empty( $value['name'] ) ) {
			if ( is_numeric( $key ) ) {
				$new_array[ sanitize_title( $value['name'] ) ] = $value;
			} else {
				$new_array[ $key ] = $value;
			}
		}
	}
	$new_value = $new_array;

	// Enforce locked fields - restore from defaults if missing or modified.
	foreach ( $defaults as $field_key => $field_config ) {
		if ( ! empty( $field_config['locked'] ) && $field_config['locked'] ) {
			if ( ! isset( $new_value[ $field_key ] ) || empty( $new_value[ $field_key ] ) ) {
				// Field deleted - restore it.
				$new_value[ $field_key ] = $field_config;
			} else {
				// Field exists - enforce locked properties (name, type, required).
				$new_value[ $field_key ]['name']     = $field_config['name'];
				$new_value[ $field_key ]['type']     = $field_config['type'];
				$new_value[ $field_key ]['required'] = $field_config['required'];
				$new_value[ $field_key ]['locked']   = true; // Cannot be deleted.
				// Allow order and options to be modified by admin.
			}
		}
	}

	// Sort by order field.
	uasort(
		$new_value,
		function ( $a, $b ) {
			$order_a = isset( $a['order'] ) ? (int) $a['order'] : 999;
			$order_b = isset( $b['order'] ) ? (int) $b['order'] : 999;
			return $order_a > $order_b;
		}
	);

	return $new_value;
}

/**
 * Sanitize funder attributes - enforce immutable v1 core fields.
 *
 * This function ensures that locked fields from v1 (type-financeur, and all predefined types)
 * cannot be deleted or modified. New attributes can be added by admins.
 *
 * @param array $new_value Updated attribute configuration.
 * @param array $old_value Previous attribute configuration.
 * @return array Sanitized attributes with v1 core fields protected.
 */
function formapress_funder_attributes_sanitize_options( $new_value, $old_value ) {
	// Get default locked fields from schema.
	$defaults = formapress_get_default_funder_attributes();

	// Convert numeric keys to slugged keys.
	$new_array = array();
	foreach ( $new_value as $key => $value ) {
		if ( ! empty( $value['name'] ) ) {
			if ( is_numeric( $key ) ) {
				$new_array[ sanitize_title( $value['name'] ) ] = $value;
			} else {
				$new_array[ $key ] = $value;
			}
		}
	}
	$new_value = $new_array;

	// Enforce locked fields - restore from defaults if missing or modified.
	foreach ( $defaults as $field_key => $field_config ) {
		if ( ! empty( $field_config['locked'] ) && $field_config['locked'] ) {
			if ( ! isset( $new_value[ $field_key ] ) || empty( $new_value[ $field_key ] ) ) {
				// Field deleted - restore it.
				$new_value[ $field_key ] = $field_config;
			} else {
				// Field exists - enforce locked properties (name, type, required, options).
				$new_value[ $field_key ]['name']     = $field_config['name'];
				$new_value[ $field_key ]['type']     = $field_config['type'];
				$new_value[ $field_key ]['required'] = $field_config['required'];
				$new_value[ $field_key ]['locked']   = true; // Cannot be deleted.
				// For type-financeur, enforce locked options list from v1.
				if ( 'type-financeur' === $field_key ) {
					$new_value[ $field_key ]['options'] = $field_config['options'];
				}
				// Allow order to be modified by admin.
			}
		}
	}

	// Sort by order field.
	uasort(
		$new_value,
		function ( $a, $b ) {
			$order_a = isset( $a['order'] ) ? (int) $a['order'] : 999;
			$order_b = isset( $b['order'] ) ? (int) $b['order'] : 999;
			return $order_a > $order_b;
		}
	);

	return $new_value;
}

/**
 * Sanitize prospect attributes - convert numeric keys to slugged keys.
 *
 * Prospects have fewer locked fields since they're simpler entities.
 * This ensures consistent key format for attribute storage.
 *
 * @param array $new_value Updated attribute configuration.
 * @param array $old_value Previous attribute configuration.
 * @return array Sanitized attributes with proper key format.
 */
function formapress_prospect_attributes_sanitize_options( $new_value, $old_value ) {
	// Get default fields from schema.
	$defaults = formapress_get_default_prospect_attributes();

	// Convert numeric keys to slugged keys.
	$new_array = array();
	foreach ( $new_value as $key => $value ) {
		if ( ! empty( $value['name'] ) ) {
			if ( is_numeric( $key ) ) {
				$new_array[ sanitize_title( $value['name'] ) ] = $value;
			} else {
				$new_array[ $key ] = $value;
			}
		}
	}
	$new_value = $new_array;

	// Enforce any locked fields from defaults.
	foreach ( $defaults as $field_key => $field_config ) {
		if ( ! empty( $field_config['locked'] ) && $field_config['locked'] ) {
			if ( ! isset( $new_value[ $field_key ] ) || empty( $new_value[ $field_key ] ) ) {
				$new_value[ $field_key ] = $field_config;
			} else {
				$new_value[ $field_key ]['name']     = $field_config['name'];
				$new_value[ $field_key ]['type']     = $field_config['type'];
				$new_value[ $field_key ]['required'] = $field_config['required'];
				$new_value[ $field_key ]['locked']   = true;
			}
		}
	}

	// Sort by order field.
	uasort(
		$new_value,
		function ( $a, $b ) {
			$order_a = isset( $a['order'] ) ? (int) $a['order'] : 999;
			$order_b = isset( $b['order'] ) ? (int) $b['order'] : 999;
			return $order_a > $order_b;
		}
	);

	return $new_value;
}

/**
 * Sanitize opportunity attributes - convert numeric keys to slugged keys.
 *
 * Opportunities track sales pipeline stages with basic fields (montant, probabilite, date-cloture).
 * This ensures consistent key format for attribute storage.
 *
 * @param array $new_value Updated attribute configuration.
 * @param array $old_value Previous attribute configuration.
 * @return array Sanitized attributes with proper key format.
 */
function formapress_opportunity_attributes_sanitize_options( $new_value, $old_value ) {
	// Get default fields from schema.
	$defaults = formapress_get_default_opportunity_attributes();

	// Convert numeric keys to slugged keys.
	$new_array = array();
	foreach ( $new_value as $key => $value ) {
		if ( ! empty( $value['name'] ) ) {
			if ( is_numeric( $key ) ) {
				$new_array[ sanitize_title( $value['name'] ) ] = $value;
			} else {
				$new_array[ $key ] = $value;
			}
		}
	}
	$new_value = $new_array;

	// Enforce any locked fields from defaults.
	foreach ( $defaults as $field_key => $field_config ) {
		if ( ! empty( $field_config['locked'] ) && $field_config['locked'] ) {
			if ( ! isset( $new_value[ $field_key ] ) || empty( $new_value[ $field_key ] ) ) {
				$new_value[ $field_key ] = $field_config;
			} else {
				$new_value[ $field_key ]['name']     = $field_config['name'];
				$new_value[ $field_key ]['type']     = $field_config['type'];
				$new_value[ $field_key ]['required'] = $field_config['required'];
				$new_value[ $field_key ]['locked']   = true;
			}
		}
	}

	// Sort by order field.
	uasort(
		$new_value,
		function ( $a, $b ) {
			$order_a = isset( $a['order'] ) ? (int) $a['order'] : 999;
			$order_b = isset( $b['order'] ) ? (int) $b['order'] : 999;
			return $order_a > $order_b;
		}
	);

	return $new_value;
}

/**
 * Register sanitization filters for all CRM attribute options.
 *
 * These filters run when the options are updated via Settings API,
 * ensuring locked v1 fields cannot be deleted or modified.
 *
 * Called on 'admin_init' hook from main plugin file.
 *
 * @return void
 */
function formapress_register_attribute_sanitization_filters() {
	// Company attributes (zqpm_entreprise → crm_company).
	add_filter( 'pre_update_option_crm_company_attributes', 'formapress_company_attributes_sanitize_options', 10, 2 );

	// Referent attributes (entreprise.referent → crm_person with role=referent).
	add_filter( 'pre_update_option_crm_person_referent_attributes', 'formapress_referent_attributes_sanitize_options', 10, 2 );

	// Funder attributes (zqpm_financeur → crm_person with role=funder).
	add_filter( 'pre_update_option_crm_person_funder_attributes', 'formapress_funder_attributes_sanitize_options', 10, 2 );

	// Prospect attributes (new entity for pipeline).
	add_filter( 'pre_update_option_crm_person_prospect_attributes', 'formapress_prospect_attributes_sanitize_options', 10, 2 );

	// Opportunity attributes (pipeline sales opportunities).
	add_filter( 'pre_update_option_crm_opportunity_attributes', 'formapress_opportunity_attributes_sanitize_options', 10, 2 );
}
add_action( 'admin_init', 'formapress_register_attribute_sanitization_filters' );
