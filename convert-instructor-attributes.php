<?php
/**
 * Convert Instructor Attributes from Numeric to Slugged Keys
 *
 * This script converts the zform_Instructors_attributes option from
 * numeric array format to associative array with slugged keys.
 *
 * Run: wp eval-file convert-instructor-attributes.php
 */

echo "\n";
echo "=================================================================\n";
echo "CONVERT INSTRUCTOR ATTRIBUTES TO SLUGGED KEYS\n";
echo "=================================================================\n\n";

// Get current instructor attributes
$current = get_option( 'zform_Instructors_attributes', array() );

if ( empty( $current ) ) {
	echo "❌ ERROR: No instructor attributes found!\n\n";
	exit( 1 );
}

echo "Current structure:\n";
print_r( array_keys( $current ) );
echo "\n";

// Check if already using slugged keys
$has_numeric_keys = false;
foreach ( array_keys( $current ) as $key ) {
	if ( is_numeric( $key ) ) {
		$has_numeric_keys = true;
		break;
	}
}

if ( ! $has_numeric_keys ) {
	echo "✅ Instructor attributes already using slugged keys!\n";
	echo "   No conversion needed.\n\n";
	exit( 0 );
}

echo "Converting from numeric keys to slugged keys...\n\n";

// Convert to slugged keys
$converted = array();
foreach ( $current as $key => $value ) {
	if ( ! empty( $value['name'] ) ) {
		$slug               = sanitize_title( $value['name'] );
		$converted[ $slug ] = $value;

		echo "  {$key} -> '{$slug}' ({$value['name']})\n";
	}
}

// Sort by order
uasort(
	$converted,
	function ( $a, $b ) {
		$order_a = isset( $a['order'] ) ? intval( $a['order'] ) : 0;
		$order_b = isset( $b['order'] ) ? intval( $b['order'] ) : 0;
		return $order_a > $order_b;
	}
);

echo "\n";
echo "New structure:\n";
print_r( array_keys( $converted ) );
echo "\n";

// Update option
$updated = update_option( 'zform_Instructors_attributes', $converted );

if ( $updated ) {
	echo "✅ SUCCESS: Instructor attributes converted to slugged keys!\n\n";

	// Verify
	$verify = get_option( 'zform_Instructors_attributes', array() );
	echo "Verification - Field slugs:\n";
	foreach ( array_keys( $verify ) as $slug ) {
		echo "  • {$slug}\n";
	}
	echo "\n";

} else {
	echo "❌ ERROR: Failed to update option\n\n";
	exit( 1 );
}

echo "=================================================================\n";
echo "NEXT STEP: Re-run shortcode registration test\n";
echo "=================================================================\n\n";
echo "Run: wp eval-file formapress-crm/test-shortcode-registration.php\n\n";

exit( 0 );
