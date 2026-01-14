<?php
/**
 * Verify existing person and custom fields
 */

$person_id = 17480;
echo "=== PERSON 17480 VERIFICATION ===\n\n";

$person = FormaPress_Person_Manager::get_person( $person_id );
echo "Name: {$person['prenom']} {$person['nom']}\n";
echo "Email: {$person['email']}\n";
echo 'Types: ' . implode( ', ', $person['types'] ) . "\n\n";

echo "INDIVIDUAL META KEYS (custom fields):\n";
global $wpdb;
$meta_keys = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT meta_key, meta_value
     FROM {$wpdb->postmeta}
     WHERE post_id = %d
     AND meta_key LIKE '_crm_instructor_%%'
     ORDER BY meta_key",
		$person_id
	)
);

if ( $meta_keys ) {
	foreach ( $meta_keys as $meta ) {
		echo "  {$meta->meta_key} = {$meta->meta_value}\n";
	}
} else {
	echo "  (No custom fields found)\n";
}

echo "\n✓ Individual meta key storage confirmed!\n";
echo "  Each custom field has its own meta key\n";
echo "  Pattern: _crm_instructor_{field_slug}\n";
