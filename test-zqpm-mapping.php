<?php
/**
 * Test ZQPM Mapping Functions
 *
 * Run: wp eval-file test-zqpm-mapping.php
 */

echo "\n";
echo "=================================================================\n";
echo "ZQPM MAPPING FUNCTIONS TEST\n";
echo "=================================================================\n\n";

// Test 1: Get a sample trainee
$trainees = get_posts(
	array(
		'post_type'      => 'crm_person',
		'posts_per_page' => 1,
		'tax_query'      => array(
			array(
				'taxonomy' => 'person_type',
				'field'    => 'slug',
				'terms'    => 'trainee',
			),
		),
		'meta_query'     => array(
			array(
				'key'     => '_crm_v1_registration_id',
				'compare' => 'EXISTS',
			),
		),
	)
);

if ( empty( $trainees ) ) {
	echo "❌ ERROR: No trainees with v1 registration IDs found!\n\n";
	exit( 1 );
}

$trainee = $trainees[0];
echo "Test Trainee: {$trainee->post_title} (Person ID: {$trainee->ID})\n";

// Get v1 registration ID
$v1_registration_id = get_post_meta( $trainee->ID, '_crm_v1_registration_id', true );
echo "V1 Registration ID: {$v1_registration_id}\n\n";

// TEST 1: v1 ID -> v2 ID mapping
echo "Test 1: get_person_id_from_v1_registration_id({$v1_registration_id})\n";
$mapped_person_id = FormaPress_Person_Manager::get_person_id_from_v1_registration_id( $v1_registration_id );

if ( $mapped_person_id === $trainee->ID ) {
	echo "✅ PASS: Correctly mapped to person ID {$mapped_person_id}\n\n";
} else {
	echo "❌ FAIL: Expected {$trainee->ID}, got {$mapped_person_id}\n\n";
	exit( 1 );
}

// TEST 2: v2 ID -> v1 ID mapping
echo "Test 2: get_v1_registration_id_from_person_id({$trainee->ID})\n";
$reverse_mapped_id = FormaPress_Person_Manager::get_v1_registration_id_from_person_id( $trainee->ID );

if ( $reverse_mapped_id == $v1_registration_id ) {
	echo "✅ PASS: Correctly mapped back to registration ID {$reverse_mapped_id}\n\n";
} else {
	echo "❌ FAIL: Expected {$v1_registration_id}, got {$reverse_mapped_id}\n\n";
	exit( 1 );
}

// TEST 3: get_person_for_zqpm with v1 ID
echo "Test 3: get_person_for_zqpm({$v1_registration_id}, 'registration')\n";
$person_data = FormaPress_Person_Manager::get_person_for_zqpm( $v1_registration_id, 'registration' );

if ( ! empty( $person_data ) && $person_data['person_id'] === $trainee->ID ) {
	echo "✅ PASS: Retrieved person data\n";
	echo "   Person ID: {$person_data['person_id']}\n";
	echo "   Registration ID: {$person_data['registration_id']}\n";
	echo "   Name: {$person_data['prenom']} {$person_data['nom']}\n";
	echo "   Email: {$person_data['email']}\n";
	echo '   Attributes: ' . count( $person_data['attributes'] ) . " fields\n\n";
} else {
	echo "❌ FAIL: Could not retrieve person data\n\n";
	exit( 1 );
}

// TEST 4: get_person_for_zqpm with v2 ID
echo "Test 4: get_person_for_zqpm({$trainee->ID}, 'person')\n";
$person_data_v2 = FormaPress_Person_Manager::get_person_for_zqpm( $trainee->ID, 'person' );

if ( ! empty( $person_data_v2 ) && $person_data_v2['person_id'] === $trainee->ID ) {
	echo "✅ PASS: Retrieved person data using v2 ID\n";
	echo '   Data matches: ' . ( $person_data === $person_data_v2 ? 'YES' : 'NO' ) . "\n\n";
} else {
	echo "❌ FAIL: Could not retrieve person data with v2 ID\n\n";
	exit( 1 );
}

// TEST 5: Bulk lookup
echo "Test 5: Bulk lookup with 3 trainees\n";
$more_trainees = get_posts(
	array(
		'post_type'      => 'crm_person',
		'posts_per_page' => 3,
		'tax_query'      => array(
			array(
				'taxonomy' => 'person_type',
				'field'    => 'slug',
				'terms'    => 'trainee',
			),
		),
		'meta_query'     => array(
			array(
				'key'     => '_crm_v1_registration_id',
				'compare' => 'EXISTS',
			),
		),
	)
);

$v1_ids = array();
foreach ( $more_trainees as $t ) {
	$v1_ids[] = get_post_meta( $t->ID, '_crm_v1_registration_id', true );
}

$bulk_results = FormaPress_Person_Manager::get_persons_for_zqpm_bulk( $v1_ids, 'registration' );

if ( count( $bulk_results ) === count( $v1_ids ) ) {
	echo '✅ PASS: Bulk lookup returned ' . count( $bulk_results ) . " results\n";
	foreach ( $bulk_results as $reg_id => $person ) {
		if ( ! empty( $person['person_id'] ) ) {
			echo "   Registration {$reg_id} -> Person {$person['person_id']} ({$person['prenom']} {$person['nom']})\n";
		}
	}
	echo "\n";
} else {
	echo '❌ FAIL: Expected ' . count( $v1_ids ) . ' results, got ' . count( $bulk_results ) . "\n\n";
	exit( 1 );
}

echo "=================================================================\n";
echo "ALL TESTS PASSED!\n";
echo "=================================================================\n\n";

echo "ZQPM Integration Ready:\n";
echo "✅ v1 registration IDs can be converted to v2 person IDs\n";
echo "✅ v2 person IDs can be converted back to v1 registration IDs\n";
echo "✅ get_person_for_zqpm() works with both ID types\n";
echo "✅ Bulk lookup optimized for ZQPM processes\n\n";

echo "ZQPM can now:\n";
echo "• Query wp_zqpm_reponses using v1 stagiaire_id\n";
echo "• Convert to v2 person_id for data access\n";
echo "• Use shortcodes with v2 person data\n";
echo "• Generate documents with full trainee information\n\n";

exit( 0 );
