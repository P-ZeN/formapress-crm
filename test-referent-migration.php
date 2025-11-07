<?php
/**
 * Find v1 entreprises with referents
 */

global $wpdb;

// Find entreprises with referents (v1 uses 'referent' not 'referents')
$entreprises = $wpdb->get_results(
	"
    SELECT p.ID, p.post_title, pm.meta_value
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'referent'
    WHERE p.post_type = 'zqpm_entreprise'
    AND pm.meta_value IS NOT NULL
    AND pm.meta_value != ''
    LIMIT 5
"
);

echo "=== V1 ENTREPRISES WITH REFERENTS ===\n\n";

if ( empty( $entreprises ) ) {
	echo "No entreprises with referents found.\n";
} else {
	foreach ( $entreprises as $ent ) {
		$referents = maybe_unserialize( $ent->meta_value );
		$count     = is_array( $referents ) ? count( $referents ) : 0;

		echo "ID {$ent->ID}: {$ent->post_title}\n";
		echo "  Referents: {$count}\n";

		if ( $count > 0 && is_array( $referents ) ) {
			foreach ( $referents as $i => $ref ) {
				// v1 referents are zqpmReferent objects
				$ref_array = is_object( $ref ) ? (array) $ref : $ref;
				$name      = ( $ref_array['prenom'] ?? '' ) . ' ' . ( $ref_array['nom'] ?? '' );
				$email     = $ref_array['mail'] ?? '(no email)';
				$fonction  = $ref_array['poste'] ?? '(no fonction)';
				echo "    [{$i}] {$name} - {$email} - {$fonction}\n";
			}
		}
		echo "\n";
	}

	// Test migration on first one with referents
	$test_ent = null;
	foreach ( $entreprises as $ent ) {
		$referents = maybe_unserialize( $ent->meta_value );
		if ( is_array( $referents ) && count( $referents ) > 0 ) {
			$test_ent = $ent;
			break;
		}
	}

	if ( $test_ent ) {
		echo "\n=== TESTING MIGRATION ON ID {$test_ent->ID} ===\n\n";

		// Check if already migrated
		$already_migrated = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_crm_v1_entreprise_id' AND meta_value = %d LIMIT 1",
				$test_ent->ID
			)
		);

		if ( $already_migrated ) {
			echo "Already migrated to company ID {$already_migrated}\n";

			// Show migrated referents
			$migrated_contacts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, p.post_title, pm.meta_value as v1_index
                 FROM {$wpdb->posts} p
                 JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_crm_v1_referent_index'
                 JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_crm_v1_entreprise_id' AND pm2.meta_value = %d
                 WHERE p.post_type = 'crm_person'",
					$test_ent->ID
				)
			);

			echo 'Migrated contacts: ' . count( $migrated_contacts ) . "\n";
			foreach ( $migrated_contacts as $contact ) {
				$person   = FormaPress_Person_Manager::get_person( $contact->ID );
				$fonction = get_post_meta( $contact->ID, '_crm_fonction', true );
				echo "  - Person {$contact->ID}: {$person['prenom']} {$person['nom']} ({$fonction})\n";
			}
		} else {
			echo "Starting migration...\n";
			$result = FormaPress_Company_Manager::migrate_v1_entreprise( $test_ent->ID );

			if ( is_wp_error( $result ) ) {
				echo 'ERROR: ' . $result->get_error_message() . "\n";
			} else {
				echo "✓ Migration successful!\n";
				echo "  Company ID: {$result['company_id']}\n";
				echo '  Referents migrated: ' . count( $result['referents'] ) . "\n";

				foreach ( $result['referents'] as $ref ) {
					echo "    - Person {$ref['person_id']}: {$ref['name']}\n";
				}

				if ( ! empty( $result['errors'] ) ) {
					echo "  Errors:\n";
					foreach ( $result['errors'] as $error ) {
						echo "    ! {$error}\n";
					}
				}
			}
		}
	}
}
