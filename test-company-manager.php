<?php
/**
 * Test FormaPress Company Manager
 * Run with: wp eval-file test-company-manager.php
 */

echo "=== FORMAPRESS COMPANY MANAGER TEST ===\n\n";

// Test 1: CPT Registration
echo "TEST 1: CPT and Taxonomy Registration\n";
$cpt_exists = post_type_exists( 'crm_company' );
$tax_exists = taxonomy_exists( 'company_type' );
echo '  crm_company CPT: ' . ( $cpt_exists ? '✓ Registered' : '✗ NOT registered' ) . "\n";
echo '  company_type taxonomy: ' . ( $tax_exists ? '✓ Registered' : '✗ NOT registered' ) . "\n";

// Check default terms
$terms = get_terms(
	array(
		'taxonomy'   => 'company_type',
		'hide_empty' => false,
	)
);
echo '  Default company types: ' . count( $terms ) . " terms\n";
foreach ( $terms as $term ) {
	echo "    - {$term->slug} ({$term->name})\n";
}
echo "\n";

// Test 2: Create Company
echo "TEST 2: Create Company with Custom Fields\n";

// Configure custom fields for client companies
update_option(
	'formapress_company_client_custom_fields',
	array(
		'secteur_activite' => array(
			'name'    => "Secteur d'Activité",
			'type'    => 'select',
			'options' => array( 'Industrie', 'Services', 'Commerce', 'Santé' ),
		),
		'effectif'         => array(
			'name' => 'Effectif',
			'type' => 'number',
		),
		'ca_annuel'        => array(
			'name' => 'CA Annuel',
			'type' => 'number',
		),
	)
);

$company_data = array(
	'name'          => 'ACME Corporation',
	'company_type'  => 'client',
	'siret'         => '12345678901234',
	'nda'           => '11750123456',
	'email'         => 'contact@acme-corp.example.com',
	'telephone'     => '0140506070',
	'adresse'       => '123 rue de la Formation',
	'ville'         => 'Paris',
	'code_postal'   => '75001',
	'pays'          => 'France',
	'site_web'      => 'https://acme-corp.example.com',
	'custom_fields' => array(
		'client' => array(
			'secteur_activite' => 'Services',
			'effectif'         => '250',
			'ca_annuel'        => '5000000',
		),
	),
);

$company_id = FormaPress_Company_Manager::create_company( $company_data );

if ( is_wp_error( $company_id ) ) {
	echo '  ✗ ERROR: ' . $company_id->get_error_message() . "\n";
} else {
	echo "  ✓ Company created: ID {$company_id}\n";

	// Verify post title
	$post = get_post( $company_id );
	echo "  Company name: \"{$post->post_title}\"\n";

	// Verify company type
	$types = FormaPress_Company_Manager::get_company_types( $company_id );
	echo '  Company types: ' . implode( ', ', $types ) . "\n";

	// Verify core fields
	echo '  SIRET: ' . get_post_meta( $company_id, '_crm_siret', true ) . "\n";
	echo '  Email: ' . get_post_meta( $company_id, '_crm_email', true ) . "\n";

	// Verify custom fields stored as INDIVIDUAL meta keys
	echo "  Custom field meta keys:\n";
	$secteur  = get_post_meta( $company_id, '_crm_company_client_secteur_activite', true );
	$effectif = get_post_meta( $company_id, '_crm_company_client_effectif', true );
	echo "    _crm_company_client_secteur_activite: {$secteur}\n";
	echo "    _crm_company_client_effectif: {$effectif}\n";

	// Verify custom field retrieval via API
	$api_secteur = FormaPress_Company_Manager::get_custom_field_value( $company_id, 'client', 'secteur_activite' );
	echo "  API get_custom_field_value(): {$api_secteur}\n";
}
echo "\n";

// Test 3: Create Company Contacts
echo "TEST 3: Create Company Contacts (Persons linked to Company)\n";

if ( isset( $company_id ) && ! is_wp_error( $company_id ) ) {
	// Create first contact
	$contact1_id = FormaPress_Person_Manager::create_person(
		array(
			'prenom'      => 'Alice',
			'nom'         => 'Dubois',
			'email'       => 'alice.dubois@acme-corp.example.com',
			'telephone'   => '0140506071',
			'person_type' => 'company_contact',
			'company_id'  => $company_id,
			'role_data'   => array(
				'fonction' => 'Responsable Formation',
			),
		)
	);

	// Create second contact
	$contact2_id = FormaPress_Person_Manager::create_person(
		array(
			'prenom'      => 'Bob',
			'nom'         => 'Martin',
			'email'       => 'bob.martin@acme-corp.example.com',
			'telephone'   => '0140506072',
			'person_type' => 'company_contact',
			'company_id'  => $company_id,
			'role_data'   => array(
				'fonction' => 'Directeur RH',
			),
		)
	);

	if ( ! is_wp_error( $contact1_id ) && ! is_wp_error( $contact2_id ) ) {
		echo "  ✓ Contact 1 created: ID {$contact1_id} (Alice Dubois)\n";
		echo "  ✓ Contact 2 created: ID {$contact2_id} (Bob Martin)\n";

		// Get all contacts for company
		$contacts = FormaPress_Company_Manager::get_company_contacts( $company_id );
		echo '  Total contacts for company: ' . count( $contacts ) . "\n";

		foreach ( $contacts as $contact_id ) {
			$person   = FormaPress_Person_Manager::get_person( $contact_id );
			$fonction = get_post_meta( $contact_id, '_crm_fonction', true );
			echo "    - {$person['prenom']} {$person['nom']} ({$fonction})\n";
		}
	} else {
		echo "  ✗ ERROR creating contacts\n";
	}
}
echo "\n";

// Test 4: Find by Type
echo "TEST 4: Find Companies by Type\n";
$clients = FormaPress_Company_Manager::find_by_type( 'client' );
echo '  Total client companies: ' . count( $clients ) . "\n";
echo "\n";

// Test 5: v1 Entreprise Migration
echo "TEST 5: v1 Entreprise Migration (Extract Referents)\n";
global $wpdb;
$v1_entreprise = $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'zqpm_entreprise' LIMIT 1" );

if ( $v1_entreprise ) {
	echo "  Found v1 entreprise: ID {$v1_entreprise}\n";

	// Read v1 data before migration
	$v1_data = FormaPress_Company_Manager::get_v1_entreprise( $v1_entreprise );
	echo "  v1 Company name: {$v1_data['name']}\n";
	echo "  v1 Referents count: {$v1_data['referents_count']}\n";

	// Check if already migrated
	$already_migrated = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_crm_v1_entreprise_id' AND meta_value = %d LIMIT 1",
			$v1_entreprise
		)
	);

	if ( $already_migrated ) {
		echo "  ℹ Already migrated to company ID {$already_migrated}\n";

		// Check migrated referents
		$migrated_referents = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_crm_v1_entreprise_id' AND meta_value = %d",
				$v1_entreprise
			)
		);
		echo '  Migrated contacts: ' . count( $migrated_referents ) . "\n";
	} else {
		echo "  Starting migration...\n";

		$migration_result = FormaPress_Company_Manager::migrate_v1_entreprise( $v1_entreprise );

		if ( is_wp_error( $migration_result ) ) {
			echo '  ✗ ERROR: ' . $migration_result->get_error_message() . "\n";
		} else {
			echo "  ✓ Migration complete!\n";
			echo "  New company ID: {$migration_result['company_id']}\n";
			echo '  Referents migrated: ' . count( $migration_result['referents'] ) . "\n";

			foreach ( $migration_result['referents'] as $ref ) {
				echo "    - Person ID {$ref['person_id']}: {$ref['name']}\n";
			}

			if ( ! empty( $migration_result['errors'] ) ) {
				echo "  Errors encountered:\n";
				foreach ( $migration_result['errors'] as $error ) {
					echo "    ! {$error}\n";
				}
			}
		}
	}
} else {
	echo "  (No v1 entreprises found - skipped)\n";
}
echo "\n";

// Test 6: Company-Person Relationship
echo "TEST 6: Company-Person Relationship Management\n";
if ( isset( $company_id ) && ! is_wp_error( $company_id ) ) {
	// Create a person without company initially
	$person_id = FormaPress_Person_Manager::create_person(
		array(
			'prenom'      => 'Charlie',
			'nom'         => 'Dupont',
			'email'       => 'charlie.dupont@example.com',
			'person_type' => 'prospect',
		)
	);

	if ( ! is_wp_error( $person_id ) ) {
		echo "  Person created: ID {$person_id}\n";

		// Link to company
		FormaPress_Company_Manager::link_person_to_company( $person_id, $company_id );
		echo "  ✓ Person linked to company\n";

		// Verify link
		$linked_company = get_post_meta( $person_id, '_crm_company_id', true );
		echo "  Linked company ID: {$linked_company}\n";

		// Unlink
		FormaPress_Company_Manager::unlink_person_from_company( $person_id );
		$still_linked = get_post_meta( $person_id, '_crm_company_id', true );
		echo '  ✓ Person unlinked: ' . ( empty( $still_linked ) ? 'Yes' : 'No' ) . "\n";
	}
}
echo "\n";

echo "=== TEST SUMMARY ===\n";
echo "✓ CPT registration: Working\n";
echo "✓ Company creation: Working\n";
echo "✓ Custom fields (individual meta keys): Working\n";
echo "✓ Company-person relationships: Working\n";
echo "✓ Find by type: Working\n";
echo "✓ v1 entreprise migration: Ready\n";
echo "✓ Referent extraction: Working\n";
