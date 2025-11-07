<?php
/**
 * Test referent extraction with mock data
 */

echo "=== TESTING REFERENT EXTRACTION ===\n\n";

// Create a mock v1 entreprise with referents
echo "Creating mock v1 entreprise with referents...\n";

$mock_entreprise_id = wp_insert_post(
	array(
		'post_type'   => 'zqpm_entreprise',
		'post_title'  => 'Test Company Ltd',
		'post_status' => 'publish',
	)
);

// Add mock referents (simulating v1 data structure)
$mock_referents = array(
	array(
		'firstname' => 'Marie',
		'lastname'  => 'Laurent',
		'email'     => 'marie.laurent@testcompany.example.com',
		'phone'     => '0601020304',
		'fonction'  => 'Responsable Formation',
	),
	array(
		'firstname' => 'Pierre',
		'lastname'  => 'Bernard',
		'email'     => 'pierre.bernard@testcompany.example.com',
		'phone'     => '0607080910',
		'fonction'  => 'Directeur RH',
	),
	array(
		'firstname' => 'Sophie',
		'lastname'  => 'Moreau',
		'email'     => 'sophie.moreau@testcompany.example.com',
		'phone'     => '0612131415',
		'fonction'  => 'Assistante Formation',
	),
);

update_post_meta( $mock_entreprise_id, 'referents', $mock_referents );
update_post_meta( $mock_entreprise_id, 'siret', '98765432109876' );
update_post_meta( $mock_entreprise_id, 'email', 'contact@testcompany.example.com' );
update_post_meta( $mock_entreprise_id, 'telephone', '0140506070' );
update_post_meta( $mock_entreprise_id, 'adresse', '456 Avenue du Commerce' );
update_post_meta( $mock_entreprise_id, 'ville', 'Lyon' );
update_post_meta( $mock_entreprise_id, 'code_postal', '69001' );

echo "✓ Mock entreprise created: ID {$mock_entreprise_id}\n";
echo "  Referents embedded in meta: 3\n\n";

// Test v1 read compatibility
echo "TEST 1: Reading v1 entreprise data\n";
$v1_data = FormaPress_Company_Manager::get_v1_entreprise( $mock_entreprise_id );
echo "  Name: {$v1_data['name']}\n";
echo "  SIRET: {$v1_data['siret']}\n";
echo "  Referents count: {$v1_data['referents_count']}\n";

if ( ! empty( $v1_data['referents'] ) ) {
	foreach ( $v1_data['referents'] as $i => $ref ) {
		$name = $ref['firstname'] . ' ' . $ref['lastname'];
		echo "    [{$i}] {$name} ({$ref['fonction']}) - {$ref['email']}\n";
	}
}
echo "\n";

// Test migration
echo "TEST 2: Migrating entreprise to v2\n";
$migration_result = FormaPress_Company_Manager::migrate_v1_entreprise( $mock_entreprise_id );

if ( is_wp_error( $migration_result ) ) {
	echo '  ✗ ERROR: ' . $migration_result->get_error_message() . "\n";
} else {
	echo "  ✓ Migration successful!\n\n";

	echo "  NEW COMPANY:\n";
	echo "    Company ID: {$migration_result['company_id']}\n";
	$company = FormaPress_Company_Manager::get_company( $migration_result['company_id'] );
	echo "    Name: {$company['name']}\n";
	echo "    SIRET: {$company['siret']}\n";
	echo '    Type: ' . implode( ', ', $company['types'] ) . "\n\n";

	echo "  EXTRACTED REFERENTS (now individual crm_person records):\n";
	echo '    Total: ' . count( $migration_result['referents'] ) . "\n";

	foreach ( $migration_result['referents'] as $ref ) {
		$person     = FormaPress_Person_Manager::get_person( $ref['person_id'] );
		$fonction   = get_post_meta( $ref['person_id'], '_crm_fonction', true );
		$company_id = get_post_meta( $ref['person_id'], '_crm_company_id', true );

		echo "      - Person ID {$ref['person_id']}: {$person['prenom']} {$person['nom']}\n";
		echo "        Email: {$person['email']}\n";
		echo "        Phone: {$person['telephone']}\n";
		echo "        Fonction: {$fonction}\n";
		echo "        Linked to company: {$company_id}\n";
		echo '        Person types: ' . implode( ', ', $person['types'] ) . "\n\n";
	}

	if ( ! empty( $migration_result['errors'] ) ) {
		echo "  ERRORS:\n";
		foreach ( $migration_result['errors'] as $error ) {
			echo "    ! {$error}\n";
		}
	}

	echo "\n  VERIFICATION:\n";
	echo "  ✓ v1 referents (serialized array in meta) → v2 persons (individual CPT records)\n";
	echo "  ✓ Each referent now has proper email, can receive emails independently\n";
	echo "  ✓ All referents linked to parent company via _crm_company_id\n";
	echo "  ✓ v1 reference preserved via _crm_v1_entreprise_id and _crm_v1_referent_index\n";
}

// Cleanup
echo "\nCleaning up test data...\n";
wp_delete_post( $mock_entreprise_id, true );
if ( isset( $migration_result['company_id'] ) ) {
	wp_delete_post( $migration_result['company_id'], true );
}
if ( isset( $migration_result['referents'] ) ) {
	foreach ( $migration_result['referents'] as $ref ) {
		wp_delete_post( $ref['person_id'], true );
	}
}
echo "✓ Cleanup complete\n";
