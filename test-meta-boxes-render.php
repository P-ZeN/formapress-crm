<?php
/**
 * Test Meta Boxes Rendering
 *
 * Simulates what will be displayed in WordPress admin for Alice Dubois
 */

require_once __DIR__ . '/../../../wp-load.php';

echo "=== TESTING META BOXES RENDERING FOR ALICE DUBOIS ===\n\n";

$person_id = 17484;
$post      = get_post( $person_id );

if ( ! $post ) {
	echo "ERROR: Person $person_id not found!\n";
	exit;
}

echo "Post ID: $person_id\n";
echo "Post Type: {$post->post_type}\n";
echo "Post Title: {$post->post_title}\n\n";

// Check if new meta boxes class is loaded
if ( ! class_exists( 'FormaPress_Person_Meta_Boxes' ) ) {
	echo "ERROR: FormaPress_Person_Meta_Boxes class not loaded!\n";
	exit;
}

// Get data as meta boxes will get it
$person_data = FormaPress_Person_Manager::get_person( $person_id );

echo "=== META BOX: PERSON DETAILS ===\n";
echo 'Civilité: ' . ( $person_data['civilite'] ?? '[empty]' ) . "\n";
echo 'Prénom: ' . ( $person_data['prenom'] ?? '[empty]' ) . "\n";
echo 'Nom: ' . ( $person_data['nom'] ?? '[empty]' ) . "\n";
echo 'Email: ' . ( $person_data['email'] ?? '[empty]' ) . "\n";
echo 'Téléphone: ' . ( $person_data['telephone'] ?? '[empty]' ) . "\n";
echo 'Adresse: ' . ( $person_data['adresse'] ?? '[empty]' ) . "\n";
echo 'Code Postal: ' . ( $person_data['code_postal'] ?? '[empty]' ) . "\n";
echo 'Ville: ' . ( $person_data['ville'] ?? '[empty]' ) . "\n";
echo 'Fonction: ' . ( $person_data['fonction'] ?? '[empty]' ) . "\n";

echo "\n=== META BOX: COMPANY ASSOCIATION ===\n";
$company_id = $person_data['company_id'] ?? '';
if ( $company_id ) {
	$company = get_post( $company_id );
	echo 'Company: ' . ( $company ? $company->post_title . " (ID: $company_id)" : "ID $company_id [not found]" ) . "\n";
} else {
	echo "Company: [No company linked]\n";
}

echo "\n=== META BOX: PERSON TYPES ===\n";
$person_types = FormaPress_Person_Manager::get_person_types( $person_id );
echo 'Types: ' . ( empty( $person_types ) ? '[none]' : implode( ', ', $person_types ) ) . "\n";

// Get available types
$available_types = get_terms(
	array(
		'taxonomy'   => 'person_type',
		'hide_empty' => false,
	)
);
echo "\nAvailable types in taxonomy:\n";
foreach ( $available_types as $type ) {
	$checked = in_array( $type->slug, $person_types ) ? '[✓]' : '[ ]';
	echo "  $checked {$type->name} ({$type->slug})\n";
}

echo "\n=== META BOX: CUSTOM FIELDS ===\n";
if ( empty( $person_types ) ) {
	echo "No custom fields (no person types selected)\n";
} else {
	foreach ( $person_types as $type ) {
		$custom_fields = FormaPress_Person_Manager::get_custom_fields_config( $type );
		if ( empty( $custom_fields ) ) {
			echo "No custom fields configured for type: $type\n";
		} else {
			echo "\n{$type} custom fields:\n";
			foreach ( $custom_fields as $field_slug => $field_config ) {
				$value = FormaPress_Person_Manager::get_custom_field_value( $person_id, $type, $field_slug );
				echo "  - {$field_config['label']}: " . ( $value ?: '[empty]' ) . "\n";
			}
		}
	}
}

echo "\n✓ This is what should be displayed in the WordPress admin edit screen!\n";
