<?php
/**
 * Default attribute schemas for v1 backward compatibility
 *
 * @package FormaPress_CRM
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get default company attributes (v1 zqpm_entreprise structure)
 *
 * Defines immutable core fields from v1 that cannot be deleted by users.
 * These fields are essential for backward compatibility with ZQPM and other v1 processes.
 *
 * @since 2.0.0
 * @return array Associative array of attribute definitions
 */
function formapress_get_default_company_attributes() {
	return array(
		'raison-sociale' => array(
			'name'     => 'Raison sociale',
			'type'     => 'text',
			'order'    => 0,
			'required' => 1,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'siret'          => array(
			'name'     => 'SIRET',
			'type'     => 'text',
			'order'    => 1,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'adresse'        => array(
			'name'     => 'Adresse',
			'type'     => 'textarea',
			'order'    => 2,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'cp'             => array(
			'name'     => 'Code postal',
			'type'     => 'text',
			'order'    => 3,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'ville'          => array(
			'name'     => 'Ville',
			'type'     => 'text',
			'order'    => 4,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'site'           => array(
			'name'     => 'Site internet',
			'type'     => 'url',
			'order'    => 5,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'tel'            => array(
			'name'     => 'Téléphone',
			'type'     => 'tel',
			'order'    => 6,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'email'          => array(
			'name'     => 'Email',
			'type'     => 'email',
			'order'    => 7,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'pays'           => array(
			'name'     => 'Pays',
			'type'     => 'text',
			'order'    => 8,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'naf'            => array(
			'name'     => 'Code NAF',
			'type'     => 'text',
			'order'    => 9,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'nda'            => array(
			'name'     => 'Numéro de déclaration d\'activité',
			'type'     => 'text',
			'order'    => 10,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
	);
}

/**
 * Get default referent (company contact) attributes (v1 zqpm_entreprise.referent structure)
 *
 * Defines immutable core fields for company contacts stored in v1 as serialized
 * array in entreprise.referent meta.
 *
 * @since 2.0.0
 * @return array Associative array of attribute definitions
 */
function formapress_get_default_referent_attributes() {
	return array(
		'civilite' => array(
			'name'     => 'Civilité',
			'type'     => 'select',
			'options'  => "Mme|Mme\nMr|Mr",
			'order'    => 0,
			'required' => 1,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'nom'      => array(
			'name'     => 'Nom',
			'type'     => 'text',
			'order'    => 1,
			'required' => 1,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'prenom'   => array(
			'name'     => 'Prénom',
			'type'     => 'text',
			'order'    => 2,
			'required' => 1,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'poste'    => array(
			'name'     => 'Poste',
			'type'     => 'text',
			'order'    => 3,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'tel'      => array(
			'name'     => 'Téléphone',
			'type'     => 'tel',
			'order'    => 4,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'mail'     => array(
			'name'     => 'E-mail',
			'type'     => 'mail',
			'order'    => 5,
			'required' => 1,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
	);
}

/**
 * Get default instructor attributes (v1 zqpm_formateur structure)
 *
 * Defines immutable core fields for instructors/formateurs.
 *
 * @since 2.0.0
 * @return array Associative array of attribute definitions
 */
function formapress_get_default_instructor_attributes() {
	return array(
		'civilite' => array(
			'name'     => 'Civilité',
			'type'     => 'select',
			'options'  => "Mme|Mme\nMr|Mr",
			'order'    => 0,
			'required' => 1,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'nom'      => array(
			'name'     => 'Nom',
			'type'     => 'text',
			'order'    => 1,
			'required' => 1,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'prenom'   => array(
			'name'     => 'Prénom',
			'type'     => 'text',
			'order'    => 2,
			'required' => 1,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'email'    => array(
			'name'     => 'Email',
			'type'     => 'email',
			'order'    => 3,
			'required' => 1,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'tel'      => array(
			'name'     => 'Téléphone',
			'type'     => 'tel',
			'order'    => 4,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
	);
}

/**
 * Get default trainee attributes (v1 zqpm_stagiaire structure)
 *
 * Defines immutable core fields for trainees/stagiaires.
 *
 * @since 2.0.0
 * @return array Associative array of attribute definitions
 */
function formapress_get_default_trainee_attributes() {
	return array(
		'civilite' => array(
			'name'     => 'Civilité',
			'type'     => 'select',
			'options'  => "Mme|Mme\nMr|Mr",
			'order'    => 0,
			'required' => 1,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'nom'      => array(
			'name'     => 'Nom',
			'type'     => 'text',
			'order'    => 1,
			'required' => 1,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'prenom'   => array(
			'name'     => 'Prénom',
			'type'     => 'text',
			'order'    => 2,
			'required' => 1,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'email'    => array(
			'name'     => 'Email',
			'type'     => 'email',
			'order'    => 3,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'tel'      => array(
			'name'     => 'Téléphone',
			'type'     => 'tel',
			'order'    => 4,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'adresse'  => array(
			'name'     => 'Adresse',
			'type'     => 'textarea',
			'order'    => 5,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'cp'       => array(
			'name'     => 'Code postal',
			'type'     => 'text',
			'order'    => 6,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'ville'    => array(
			'name'     => 'Ville',
			'type'     => 'text',
			'order'    => 7,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
	);
}

/**
 * Get default funder attributes (v1 zqpm_financeur structure)
 *
 * Defines immutable core fields for funders/financeurs.
 * Type field includes all v1 predefined funder types.
 *
 * @since 2.0.0
 * @return array Associative array of attribute definitions
 */
function formapress_get_default_funder_attributes() {
	return array(
		'nom'           => array(
			'name'     => 'Nom',
			'type'     => 'text',
			'order'    => 0,
			'required' => 1,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'type'          => array(
			'name'     => 'Type',
			'type'     => 'select',
			'options'  => "0|AGEFPIH\n1|Caisse des Dépôts\n2|Etat\n3|Fond d'assurance formation des non salariés\n4|Instances Européennes\n5|OPACIF\n6|OPCA\n7|OPCO\n8|Pôle Emploi\n9|Région\n10|Autre\n11|Autre public",
			'order'    => 1,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'mail'          => array(
			'name'     => 'E-mail',
			'type'     => 'mail',
			'order'    => 2,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'telephone'     => array(
			'name'     => 'Téléphone',
			'type'     => 'tel',
			'order'    => 3,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'site'          => array(
			'name'     => 'Site internet',
			'type'     => 'text',
			'order'    => 4,
			'required' => 0,
			'locked'   => false, // Can be customized.
		),
		'adresse'       => array(
			'name'     => 'Adresse',
			'type'     => 'textarea',
			'order'    => 5,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'cp'            => array(
			'name'     => 'Code postal',
			'type'     => 'text',
			'order'    => 6,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'ville'         => array(
			'name'     => 'Ville',
			'type'     => 'text',
			'order'    => 7,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'siret'         => array(
			'name'     => 'N° SIRET',
			'type'     => 'text',
			'order'    => 8,
			'required' => 0,
			'locked'   => true, // Cannot be deleted - v1 core field.
		),
		'numero-compta' => array(
			'name'     => 'N° de compte en comptabilité',
			'type'     => 'text',
			'order'    => 9,
			'required' => 0,
			'locked'   => false, // Can be customized.
		),
	);
}

/**
 * Get default prospect attributes
 *
 * Basic schema for prospects. Can be extended by users.
 *
 * @since 2.0.0
 * @return array Associative array of attribute definitions
 */
function formapress_get_default_prospect_attributes() {
	return array(
		'civilite' => array(
			'name'     => 'Civilité',
			'type'     => 'select',
			'options'  => "Mme|Mme\nMr|Mr",
			'order'    => 0,
			'required' => 0,
			'locked'   => true,
		),
		'nom'      => array(
			'name'     => 'Nom',
			'type'     => 'text',
			'order'    => 1,
			'required' => 1,
			'locked'   => true,
		),
		'prenom'   => array(
			'name'     => 'Prénom',
			'type'     => 'text',
			'order'    => 2,
			'required' => 0,
			'locked'   => true,
		),
		'mail'     => array(
			'name'     => 'E-mail',
			'type'     => 'mail',
			'order'    => 3,
			'required' => 0,
			'locked'   => true,
		),
		'tel'      => array(
			'name'     => 'Téléphone',
			'type'     => 'tel',
			'order'    => 4,
			'required' => 0,
			'locked'   => true,
		),
	);
}

/**
 * Get default opportunity attributes
 *
 * Basic schema for opportunities. Can be extended by users.
 *
 * @since 2.0.0
 * @return array Associative array of attribute definitions
 */
function formapress_get_default_opportunity_attributes() {
	return array(
		'montant'      => array(
			'name'     => 'Montant',
			'type'     => 'number',
			'order'    => 0,
			'required' => 0,
			'locked'   => true,
		),
		'probabilite'  => array(
			'name'     => 'Probabilité',
			'type'     => 'select',
			'options'  => "0|0%\n25|25%\n50|50%\n75|75%\n100|100%",
			'order'    => 1,
			'required' => 0,
			'locked'   => true,
		),
		'date-cloture' => array(
			'name'     => 'Date de clôture prévue',
			'type'     => 'text',
			'order'    => 2,
			'required' => 0,
			'locked'   => true,
		),
	);
}
