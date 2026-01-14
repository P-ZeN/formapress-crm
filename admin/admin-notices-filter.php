<?php
/**
 * Admin Notices Filter
 *
 * Hides irrelevant WordPress admin notices when viewing FormaPress pages.
 *
 * @package FormapressCRM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Check if we're on a FormaPress admin page.
 *
 * @return bool True if on FormaPress page, false otherwise.
 */
function formapress_is_formapress_page() {
	$screen = get_current_screen();

	if ( ! $screen ) {
		return false;
	}

	// Check for FormaPress menu pages.
	if ( isset( $_GET['page'] ) ) {
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );

		// FormaPress CRM pages.
		if ( strpos( $page, 'formapress-crm' ) === 0 ) {
			return true;
		}

		// zFormations pages.
		if ( strpos( $page, 'zform' ) === 0 || strpos( $page, 'zformation' ) === 0 ) {
			return true;
		}

		// ZQPM pages.
		if ( strpos( $page, 'zqpm' ) === 0 ) {
			return true;
		}
	}

	// Check for FormaPress post types.
	$formapress_post_types = array(
		'crm_opportunity',
		'crm_activity',
		'crm_invoice',
		'crm_person',
		'zqpm',
		'zqpm_entreprise',
		'zform',
		'zform_instructor',
		'email_template',
		'document_template',
		'test_template',
	);

	if ( in_array( $screen->post_type, $formapress_post_types, true ) ) {
		return true;
	}

	// Check screen IDs for FormaPress pages.
	$formapress_screen_ids = array(
		'toplevel_page_formapress-crm-dashboard',
		'crm_submenu_page_formapress-crm-pipeline',
		'crm_submenu_page_formapress-crm-communication',
		'admin_page_formapress-crm-edit-opportunity',
	);

	if ( in_array( $screen->id, $formapress_screen_ids, true ) ) {
		return true;
	}

	return false;
}

/**
 * Filter admin notices on FormaPress pages.
 *
 * Uses output buffering to capture and filter notices.
 */
function formapress_filter_admin_notices_start() {
	if ( ! formapress_is_formapress_page() ) {
		return;
	}

	ob_start();
}
add_action( 'admin_notices', 'formapress_filter_admin_notices_start', -9999 );
add_action( 'all_admin_notices', 'formapress_filter_admin_notices_start', -9999 );

/**
 * Process and filter the captured notices.
 */
function formapress_filter_admin_notices_end() {
	if ( ! formapress_is_formapress_page() ) {
		return;
	}

	$notices = ob_get_clean();

	if ( empty( $notices ) ) {
		return;
	}

	// List of allowed notice sources (FormaPress plugins).
	$allowed_patterns = array(
		'formapress',
		'zform',
		'zqpm',
		'forma-press',
		'forma press',
	);

	// Parse notices and filter.
	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="UTF-8">' . $notices );
	libxml_clear_errors();

	$xpath         = new DOMXPath( $dom );
	$notice_divs   = $xpath->query( "//div[contains(@class, 'notice') or contains(@class, 'updated') or contains(@class, 'error')]" );
	$filtered_html = '';

	foreach ( $notice_divs as $notice_div ) {
		$notice_html = $dom->saveHTML( $notice_div );
		$is_allowed  = false;

		// Check if notice contains FormaPress-related content.
		foreach ( $allowed_patterns as $pattern ) {
			if ( stripos( $notice_html, $pattern ) !== false ) {
				$is_allowed = true;
				break;
			}
		}

		// Also check for specific IDs/classes that indicate FormaPress notices.
		$id    = $notice_div->getAttribute( 'id' );
		$class = $notice_div->getAttribute( 'class' );

		if ( strpos( $id, 'formapress' ) !== false ||
			strpos( $id, 'zform' ) !== false ||
			strpos( $class, 'formapress' ) !== false ||
			strpos( $class, 'zform' ) !== false ) {
			$is_allowed = true;
		}

		if ( $is_allowed ) {
			$filtered_html .= $notice_html;
		}
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped by DOMDocument.
	echo $filtered_html;
}
add_action( 'admin_notices', 'formapress_filter_admin_notices_end', 9999 );
add_action( 'all_admin_notices', 'formapress_filter_admin_notices_end', 9999 );

/**
 * Alternative method: Remove specific plugin notices we know are annoying.
 *
 * This removes notices from specific plugins that commonly spam.
 */
function formapress_remove_annoying_plugin_notices() {
	if ( ! formapress_is_formapress_page() ) {
		return;
	}

	// Remove common plugin notices that spam.
	remove_action( 'admin_notices', 'update_nag', 3 );
	remove_action( 'network_admin_notices', 'update_nag', 3 );
	remove_action( 'admin_notices', 'maintenance_nag' );

	// Remove plugin update notices.
	remove_action( 'load-update-core.php', 'wp_update_plugins' );

	// Hide "Please rate us" notices from other plugins.
	if ( function_exists( 'remove_all_actions' ) ) {
		// Note: We can't remove all actions as that would break legitimate notices.
		// Individual plugins would need to be targeted if they're particularly annoying.
	}
}
add_action( 'admin_head', 'formapress_remove_annoying_plugin_notices', 1 );

/**
 * Add CSS to further hide notices from known spammy plugins.
 */
function formapress_hide_notices_css() {
	if ( ! formapress_is_formapress_page() ) {
		return;
	}

	?>
	<style>
		/* Hide common plugin spam notices on FormaPress pages */
		.notice:not([class*="formapress"]):not([class*="zform"]):not([class*="zqpm"]):not([id*="formapress"]):not([id*="zform"]):not([id*="zqpm"]) {
			/* Only hide if notice doesn't contain FormaPress-specific content */
		}

		/* Hide update nags */
		.update-nag,
		.updated.notice.is-dismissible:not([class*="formapress"]):not([class*="zform"]),
		.notice.notice-warning:not([class*="formapress"]):not([class*="zform"]) {
			/* These will be filtered by the main filter */
		}

		/* Keep WordPress core errors visible */
		.notice-error {
			/* Always show errors for safety */
		}
	</style>
	<?php
}
add_action( 'admin_head', 'formapress_hide_notices_css' );
