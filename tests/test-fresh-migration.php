<?php
/**
 * Test fresh referent migration
 */

echo "=== TESTING FRESH REFERENT MIGRATION ===\n\n";

// Use EMC2 (ID 6673) which has 1 referent
$entreprise_id = 6673;

echo "Entreprise ID: {$entreprise_id}\n";
$v1_data = FormaPress_Company_Manager::get_v1_entreprise( $entreprise_id );
echo "Name: {$v1_data['name']}\n";
echo "SIRET: {$v1_data['siret']}\n";
echo "Referents: {$v1_data['referents_count']}\n\n";

// Check if already migrated
global $wpdb;
$already_migrated = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_crm_v1_entreprise_id' AND meta_value = %d LIMIT 1",
		$entreprise_id
	)
);

if ( $already_migrated ) {
	echo "Already migrated to company ID {$already_migrated}\n";
	echo "Deleting to test fresh migration...\n";

	// Delete migrated contacts
	$contacts = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_crm_v1_entreprise_id' AND meta_value = %d",
			$entreprise_id
		)
	);
	foreach ( $contacts as $contact_id ) {
		wp_delete_post( $contact_id, true );
	}

	// Delete migrated company
	wp_delete_post( $already_migrated, true );
	echo "Previous migration deleted.\n\n";
}

echo "Starting fresh migration...\n";
$result = FormaPress_Company_Manager::migrate_v1_entreprise( $entreprise_id );

if ( is_wp_error( $result ) ) {
	echo 'ERROR: ' . $result->get_error_message() . "\n";
} else {
	echo "✓ Migration successful!\n\n";

	$company = FormaPress_Company_Manager::get_company( $result['company_id'] );
	echo "NEW COMPANY:\n";
	echo "  ID: {$company['id']}\n";
	echo "  Name: {$company['name']}\n";
	echo "  SIRET: {$company['siret']}\n";
	echo '  Types: ' . implode( ', ', $company['types'] ) . "\n\n";

	echo "EXTRACTED REFERENTS:\n";
	echo '  Count: ' . count( $result['referents'] ) . "\n";

	foreach ( $result['referents'] as $ref ) {
		$person   = FormaPress_Person_Manager::get_person( $ref['person_id'] );
		$fonction = get_post_meta( $ref['person_id'], '_crm_fonction', true );

		echo "  - Person ID {$ref['person_id']}: {$person['prenom']} {$person['nom']}\n";
		echo "    Email: {$person['email']}\n";
		echo "    Phone: {$person['telephone']}\n";
		echo "    Fonction: {$fonction}\n";
		echo "    Company ID: {$person['company_id']}\n";
	}

	if ( ! empty( $result['errors'] ) ) {
		echo "\nERRORS:\n";
		foreach ( $result['errors'] as $error ) {
			echo "  ! {$error}\n";
		}
	}

	echo "\n✓ THE \"WORST DESIGN ERROR\" HAS BEEN FIXED!\n";
	echo "  v1: Referents buried in serialized array → Can't query, can't email individually\n";
	echo "  v2: Each referent is a proper crm_person → Can query, email, manage independently\n";
}
