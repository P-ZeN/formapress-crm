<?php
/**
 * Test Shortcode Registration
 *
 * Run: wp eval-file test-shortcode-registration.php
 */

// Get all registered shortcodes that start with 'crm_'
global $shortcode_tags;

echo "\n";
echo "=================================================================\n";
echo "FORMAPRESS SHORTCODE REGISTRATION TEST\n";
echo "=================================================================\n\n";

// Count CRM shortcodes
$crm_shortcodes = array();
foreach ( $shortcode_tags as $tag => $callback ) {
	if ( 0 === strpos( $tag, 'crm_' ) ) {
		$crm_shortcodes[] = $tag;
	}
}

echo 'Total CRM shortcodes registered: ' . count( $crm_shortcodes ) . "\n\n";

if ( empty( $crm_shortcodes ) ) {
	echo "❌ ERROR: No CRM shortcodes registered!\n";
	echo "   Check that FormaPress_Shortcode_Manager::init() is called.\n\n";
	exit( 1 );
}

// Group by type
$by_type = array(
	'trainee'     => array(),
	'instructor'  => array(),
	'referent'    => array(),
	'prospect'    => array(),
	'funder'      => array(),
	'company'     => array(),
	'opportunity' => array(),
);

foreach ( $crm_shortcodes as $shortcode ) {
	if ( 0 === strpos( $shortcode, 'crm_trainee_' ) ) {
		$by_type['trainee'][] = $shortcode;
	} elseif ( 0 === strpos( $shortcode, 'crm_instructor_' ) ) {
		$by_type['instructor'][] = $shortcode;
	} elseif ( 0 === strpos( $shortcode, 'crm_referent_' ) ) {
		$by_type['referent'][] = $shortcode;
	} elseif ( 0 === strpos( $shortcode, 'crm_prospect_' ) ) {
		$by_type['prospect'][] = $shortcode;
	} elseif ( 0 === strpos( $shortcode, 'crm_funder_' ) ) {
		$by_type['funder'][] = $shortcode;
	} elseif ( 0 === strpos( $shortcode, 'crm_company_' ) ) {
		$by_type['company'][] = $shortcode;
	} elseif ( 0 === strpos( $shortcode, 'crm_opportunity_' ) ) {
		$by_type['opportunity'][] = $shortcode;
	}
}

// Display results
foreach ( $by_type as $type => $shortcodes ) {
	if ( ! empty( $shortcodes ) ) {
		echo '✅ ' . strtoupper( $type ) . ' (' . count( $shortcodes ) . " shortcodes)\n";
		echo '   First 5: ' . implode( ', ', array_slice( $shortcodes, 0, 5 ) ) . "\n";
		if ( count( $shortcodes ) > 5 ) {
			echo '   ... and ' . ( count( $shortcodes ) - 5 ) . " more\n";
		}
		echo "\n";
	} else {
		echo '⚠️  ' . strtoupper( $type ) . " (0 shortcodes)\n";
		echo "   No schema found for this type\n\n";
	}
}

// Test a shortcode with sample data
echo "=================================================================\n";
echo "SHORTCODE RENDERING TEST\n";
echo "=================================================================\n\n";

// Get a sample person with instructor type
$instructors = get_posts(
	array(
		'post_type'      => 'crm_person',
		'posts_per_page' => 1,
		'tax_query'      => array(
			array(
				'taxonomy' => 'person_type',
				'field'    => 'slug',
				'terms'    => 'instructor',
			),
		),
	)
);

if ( ! empty( $instructors ) ) {
	$instructor = $instructors[0];
	echo "Testing with Instructor: {$instructor->post_title} (ID: {$instructor->ID})\n\n";

	// Get instructor attributes
	$meta_keys        = get_post_meta( $instructor->ID );
	$instructor_attrs = array();

	foreach ( $meta_keys as $key => $value ) {
		if ( 0 === strpos( $key, 'crm_person_instructor_attributs_' ) ) {
			$field                      = str_replace( 'crm_person_instructor_attributs_', '', $key );
			$instructor_attrs[ $field ] = $value[0];
		}
	}

	if ( ! empty( $instructor_attrs ) ) {
		echo 'Found ' . count( $instructor_attrs ) . " attribute(s):\n";

		foreach ( $instructor_attrs as $field => $value ) {
			$shortcode = "[crm_instructor_{$field}]";

			// Set global post for shortcode context
			global $post;
			$post = $instructor;
			setup_postdata( $post );

			// Render shortcode
			$output = do_shortcode( $shortcode );

			echo "  • {$shortcode}\n";
			echo '    Raw value: ' . substr( $value, 0, 50 ) . ( strlen( $value ) > 50 ? '...' : '' ) . "\n";
			echo '    Rendered:  ' . substr( $output, 0, 50 ) . ( strlen( $output ) > 50 ? '...' : '' ) . "\n\n";
		}

		wp_reset_postdata();
	} else {
		echo "⚠️  No attributes found for this instructor\n\n";
	}
} else {
	echo "⚠️  No instructors found to test with\n\n";
}

// Test with trainee
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
	)
);

if ( ! empty( $trainees ) ) {
	$trainee = $trainees[0];
	echo "Testing with Trainee: {$trainee->post_title} (ID: {$trainee->ID})\n\n";

	// Get trainee attributes
	$meta_keys     = get_post_meta( $trainee->ID );
	$trainee_attrs = array();

	foreach ( $meta_keys as $key => $value ) {
		if ( 0 === strpos( $key, 'crm_person_trainee_attributs_' ) ) {
			$field                   = str_replace( 'crm_person_trainee_attributs_', '', $key );
			$trainee_attrs[ $field ] = $value[0];
		}
	}

	if ( ! empty( $trainee_attrs ) ) {
		echo 'Found ' . count( $trainee_attrs ) . " attribute(s):\n";

		// Show first 3
		$count = 0;
		foreach ( $trainee_attrs as $field => $value ) {
			if ( $count >= 3 ) {
				echo '  ... and ' . ( count( $trainee_attrs ) - 3 ) . " more\n";
				break;
			}

			$shortcode = "[crm_trainee_{$field}]";

			global $post;
			$post = $trainee;
			setup_postdata( $post );

			$output = do_shortcode( $shortcode );

			echo "  • {$shortcode}\n";
			echo '    Raw value: ' . substr( $value, 0, 50 ) . ( strlen( $value ) > 50 ? '...' : '' ) . "\n";
			echo '    Rendered:  ' . substr( $output, 0, 50 ) . ( strlen( $output ) > 50 ? '...' : '' ) . "\n\n";

			++$count;
		}

		wp_reset_postdata();
	} else {
		echo "⚠️  No attributes found for this trainee\n\n";
	}
} else {
	echo "⚠️  No trainees found to test with\n\n";
}

echo "=================================================================\n";
echo "TEST COMPLETE\n";
echo "=================================================================\n\n";

if ( count( $crm_shortcodes ) > 0 ) {
	echo "✅ SUCCESS: Shortcode system is working!\n\n";
	exit( 0 );
} else {
	echo "❌ FAILED: No shortcodes registered\n\n";
	exit( 1 );
}
