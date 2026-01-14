<?php
/**
 * FormaPress CRM - PDF Generation Integration
 *
 * Wrapper for ZQPM's PDF generation system that provides CRM context.
 * Reuses existing zqpm_html_to_pdf() with opportunity/person context.
 *
 * @package FormaPress_CRM
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate PDF from document template with CRM context.
 *
 * This function wraps ZQPM's PDF generation system and sets appropriate
 * globals so CRM shortcodes can resolve context during template rendering.
 *
 * @param int $template_id     Document template post ID.
 * @param int $opportunity_id  CRM opportunity post ID.
 * @param int $person_id       CRM person post ID (optional).
 * @param int $company_id      CRM company post ID (optional).
 *
 * @return string|false Path to generated PDF file, or false on failure.
 */
function formapress_crm_generate_pdf( $template_id, $opportunity_id, $person_id = 0, $company_id = 0 ) {
	// Verify ZQPM PDF function exists.
	if ( ! function_exists( 'zqpm_html_to_pdf' ) ) {
		error_log( 'FormaPress CRM: zqpm_html_to_pdf() function not found. Is ZQPM plugin active?' );
		return false;
	}

	// Verify template exists and is correct type.
	$template = get_post( $template_id );
	if ( ! $template || 'document_template' !== $template->post_type ) {
		error_log( "FormaPress CRM: Invalid document template ID: {$template_id}" );
		return false;
	}

	// Set CRM context globals for shortcode resolution.
	// These are checked by formapress_crm_get_context_*() functions in crm-template-shortcodes.php.
	global $current_crm_opportunity, $current_crm_person, $current_crm_company;

	$current_crm_opportunity = $opportunity_id;
	$current_crm_person      = $person_id;
	$current_crm_company     = $company_id;

	// Generate unique filename.
	$opportunity = get_post( $opportunity_id );
	$filename    = sanitize_title( $template->post_title ) . '-' . sanitize_title( $opportunity->post_title ) . '-' . date( 'YmdHis' );

	// Call ZQPM PDF generation.
	// Parameters: template_id, person_id, session_id (we use opportunity_id), step_context, filename.
	$pdf_path = zqpm_html_to_pdf(
		$template_id,
		$person_id,
		$opportunity_id,
		'crm_opportunity', // Step context identifier.
		$filename
	);

	// Clear CRM context globals.
	$current_crm_opportunity = null;
	$current_crm_person      = null;
	$current_crm_company     = null;

	if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
		error_log( "FormaPress CRM: PDF generation failed for template {$template_id}" );
		return false;
	}

	return $pdf_path;
}

/**
 * Generate multiple PDFs from document templates.
 *
 * Generates PDFs for all provided template IDs with the same CRM context.
 *
 * @param array $template_ids  Array of document template post IDs.
 * @param int   $opportunity_id CRM opportunity post ID.
 * @param int   $person_id      CRM person post ID (optional).
 * @param int   $company_id     CRM company post ID (optional).
 *
 * @return array Array of generated PDF paths (template_id => path).
 */
function formapress_crm_generate_multiple_pdfs( $template_ids, $opportunity_id, $person_id = 0, $company_id = 0 ) {
	$pdf_paths = array();

	foreach ( $template_ids as $template_id ) {
		$pdf_path = formapress_crm_generate_pdf( $template_id, $opportunity_id, $person_id, $company_id );

		if ( $pdf_path ) {
			$pdf_paths[ $template_id ] = $pdf_path;
		}
	}

	return $pdf_paths;
}

/**
 * Get available document templates for current opportunity stage.
 *
 * Returns document templates that are associated with the opportunity's current stage
 * via the zqpm_steps taxonomy.
 *
 * @param int $opportunity_id CRM opportunity post ID.
 *
 * @return array Array of WP_Post objects (document templates).
 */
function formapress_crm_get_stage_document_templates( $opportunity_id ) {
	$stage = get_post_meta( $opportunity_id, '_crm_opportunity_stage', true );

	if ( ! $stage ) {
		return array();
	}

	// Map CRM stage to taxonomy term slug.
	$term_slug = 'crm_opportunity_' . $stage;

	// Query document templates with this taxonomy term.
	$args = array(
		'post_type'      => 'document_template',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'title',
		'order'          => 'ASC',
		'tax_query'      => array(
			array(
				'taxonomy' => 'zqpm_steps',
				'field'    => 'slug',
				'terms'    => $term_slug,
			),
		),
	);

	$templates = get_posts( $args );

	return $templates;
}

/**
 * Clean up generated PDF files after sending.
 *
 * ZQPM generates PDFs in wp-content/uploads/zformations/pdf/.
 * This function can be called after sending to remove temporary files.
 *
 * @param array|string $pdf_paths Path or array of paths to PDF files.
 *
 * @return bool True if all files deleted, false otherwise.
 */
function formapress_crm_cleanup_temp_pdfs( $pdf_paths ) {
	if ( ! is_array( $pdf_paths ) ) {
		$pdf_paths = array( $pdf_paths );
	}

	$all_deleted = true;

	foreach ( $pdf_paths as $pdf_path ) {
		if ( file_exists( $pdf_path ) ) {
			if ( ! wp_delete_file( $pdf_path ) ) {
				$all_deleted = false;
				error_log( "FormaPress CRM: Failed to delete temporary PDF: {$pdf_path}" );
			}
		}
	}

	return $all_deleted;
}
