<?php
/**
 * FormaPress CRM - Invoice PDF Generator
 *
 * Generate invoice PDFs using ZQPM's PDF system and save to session folders.
 *
 * @package FormaPress_CRM
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generate invoice PDF.
 *
 * @param int $invoice_id Invoice post ID.
 * @param int $template_id Document template ID (optional, will prompt if not provided).
 * @return array|WP_Error Array with 'path' and 'url' on success, WP_Error on failure.
 */
function formapress_crm_generate_invoice_pdf( $invoice_id, $template_id = 0 ) {
	// Check if ZQPM is available.
	if ( ! defined( 'ZQPM_PLUGIN_PATH' ) ) {
		return new WP_Error( 'zqpm_unavailable', __( 'Le plugin ZQPM n\'est pas activé.', 'formapress-crm' ) );
	}

	// Get invoice.
	$invoice = get_post( $invoice_id );
	if ( ! $invoice || 'crm_invoice' !== $invoice->post_type ) {
		return new WP_Error( 'invalid_invoice', __( 'Facture invalide.', 'formapress-crm' ) );
	}

	// Get ZQPM ID.
	$zqpm_id = get_post_meta( $invoice_id, '_crm_invoice_zqpm_id', true );
	if ( ! $zqpm_id ) {
		return new WP_Error( 'no_zqpm', __( 'Cette facture n\'est pas liée à une session ZQPM.', 'formapress-crm' ) );
	}

	// Get or select template.
	if ( ! $template_id ) {
		$template_id = formapress_crm_get_default_invoice_template();
		if ( ! $template_id ) {
			return new WP_Error( 'no_template', __( 'Aucun modèle de facture trouvé. Créez un modèle avec le terme "Facturation".', 'formapress-crm' ) );
		}
	}

	// Get template content.
	$template = get_post( $template_id );
	if ( ! $template || 'document_template' !== $template->post_type ) {
		return new WP_Error( 'invalid_template', __( 'Modèle de document invalide.', 'formapress-crm' ) );
	}

	// Process shortcodes in template.
	$html_content = $template->post_content;

	// Apply ZQPM shortcode processing with invoice context.
	if ( has_filter( 'zqpm_process_shortcodes' ) ) {
		$html_content = apply_filters(
			'zqpm_process_shortcodes',
			$html_content,
			'invoice',
			array(
				'invoice_id' => $invoice_id,
				'zqpm_id'    => $zqpm_id,
			)
		);
	}

	// Also process standard WordPress shortcodes.
	$html_content = do_shortcode( $html_content );

	// Convert images to inline data URIs (required for PDF).
	if ( function_exists( 'zqpm_img_to_data' ) ) {
		$html_content = zqpm_img_to_data( $html_content );
	}

	// Ensure PDF folder exists.
	$folder_info = formapress_crm_ensure_invoice_pdf_folder( $zqpm_id );
	if ( is_wp_error( $folder_info ) ) {
		return $folder_info;
	}

	// Generate PDF filename.
	$invoice_number = get_post_meta( $invoice_id, '_crm_invoice_number', true );
	$invoice_date   = get_post_meta( $invoice_id, '_crm_invoice_date', true );
	$date_suffix    = $invoice_date ? date( 'Y-m-d', strtotime( $invoice_date ) ) : date( 'Y-m-d' );
	$filename       = sprintf( 'facture-%s-%s.pdf', sanitize_file_name( $invoice_number ), $date_suffix );
	$filepath       = $folder_info['path'] . $filename;

	// Build complete HTML document with ZQPM styles.
	$zqpm_template_infos = get_option(
		'zqpm_template_infos',
		array(
			'footer' => '',
			'header' => '',
		)
	);

	$full_html = '
	<html>
	<head>
	<title>' . esc_html( $invoice_number ) . '</title>';

	// Load ZQPM PDF styles.
	if ( file_exists( ZQPM_PLUGIN_PATH . 'assets/css/zqpm_pdf_styles.css' ) ) {
		$full_html .= '<style>' . file_get_contents( ZQPM_PLUGIN_PATH . 'assets/css/zqpm_pdf_styles.css' );
	} else {
		$full_html .= '<style>';
	}

	// Add custom CSS.
	$zqpm_custom_pdf_css = get_option( 'zqpm_custom_pdf_css', '' );
	$full_html          .= $zqpm_custom_pdf_css . '</style>';
	$full_html          .= '</head>
	<body>';

	// Header.
	if ( ! empty( $zqpm_template_infos['header'] ) ) {
		$full_html .= '<header>';
		if ( function_exists( 'zqpm_images_to_base64' ) ) {
			$full_html .= nl2br( zqpm_images_to_base64( $zqpm_template_infos['header'] ) );
		} else {
			$full_html .= nl2br( $zqpm_template_infos['header'] );
		}
		$full_html .= '</header>';
	}

	// Footer.
	if ( ! empty( $zqpm_template_infos['footer'] ) ) {
		$full_html .= '<footer>';
		$full_html .= nl2br( $zqpm_template_infos['footer'] );
		$full_html .= '</footer>';
	}

	// Main content.
	$full_html .= '<div id="content">';
	$full_html .= $html_content;
	$full_html .= '</div>';

	$full_html .= '</body>';
	$full_html .= '</html>';

	// Generate PDF using Dompdf (same library as ZQPM).
	if ( ! class_exists( 'Dompdf\Dompdf' ) ) {
		require_once ZQPM_PLUGIN_PATH . '/vendor/autoload.php';
	}

	$options = new \Dompdf\Options();
	$options->set( 'isRemoteEnabled', true );
	$options->set( 'isHtml5ParserEnabled', true );

	$dompdf = new \Dompdf\Dompdf( $options );
	$dompdf->loadHtml( $full_html );
	$dompdf->setPaper( 'A4', 'portrait' );
	$dompdf->render();

	// Save to file.
	$output = $dompdf->output();
	$result = file_put_contents( $filepath, $output );

	if ( ! $result || ! file_exists( $filepath ) ) {
		return new WP_Error( 'pdf_generation_failed', __( 'Échec de la génération du PDF.', 'formapress-crm' ) );
	}

	// Store PDF path in invoice meta.
	update_post_meta( $invoice_id, '_crm_invoice_pdf_path', $filepath );
	update_post_meta( $invoice_id, '_crm_invoice_pdf_url', $folder_info['url'] . $filename );
	update_post_meta( $invoice_id, '_crm_invoice_pdf_generated_date', current_time( 'Y-m-d H:i:s' ) );
	update_post_meta( $invoice_id, '_crm_invoice_pdf_template_id', $template_id );

	return array(
		'path'     => $filepath,
		'url'      => $folder_info['url'] . $filename,
		'filename' => $filename,
	);
}

/**
 * Ensure invoice PDF folder exists.
 *
 * Creates folder within ZQPM session folder: /uploads/pdf/zqpm_{id}_pdfs/factures/
 *
 * @param int $zqpm_id ZQPM post ID.
 * @return array|WP_Error Array with 'path' and 'url' on success, WP_Error on failure.
 */
function formapress_crm_ensure_invoice_pdf_folder( $zqpm_id ) {
	// Get ZQPM session folder using ZQPM's function.
	if ( ! function_exists( 'zqpm_session_folder_infos' ) ) {
		return new WP_Error( 'zqpm_function_missing', __( 'Fonction ZQPM manquante.', 'formapress-crm' ) );
	}

	$session_folder = zqpm_session_folder_infos( $zqpm_id );
	$base_path      = $session_folder['path'];
	$base_url       = $session_folder['url'];

	// Create invoices subfolder within ZQPM session folder.
	$invoices_path = $base_path . 'factures';
	$invoices_url  = $base_url . 'factures';

	if ( ! file_exists( $invoices_path ) ) {
		if ( ! wp_mkdir_p( $invoices_path ) ) {
			return new WP_Error( 'folder_creation_failed', __( 'Impossible de créer le dossier des factures.', 'formapress-crm' ) );
		}
	}

	// Verify folder is writable.
	if ( ! is_writable( $invoices_path ) ) {
		return new WP_Error( 'folder_not_writable', __( 'Le dossier des factures n\'est pas accessible en écriture.', 'formapress-crm' ) );
	}

	return array(
		'path' => trailingslashit( $invoices_path ),
		'url'  => trailingslashit( $invoices_url ),
	);
}

/**
 * Get default invoice template.
 *
 * Finds first document_template with "Facturation" (step_19) taxonomy term.
 *
 * @return int|false Template ID or false if not found.
 */
function formapress_crm_get_default_invoice_template() {
	$templates = get_posts(
		array(
			'post_type'      => 'document_template',
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'tax_query'      => array(
				array(
					'taxonomy' => 'zqpm_steps',
					'field'    => 'slug',
					'terms'    => 'step_19',
				),
			),
			'fields'         => 'ids',
		)
	);

	return ! empty( $templates ) ? $templates[0] : false;
}

/**
 * Get invoice PDF info.
 *
 * @param int $invoice_id Invoice post ID.
 * @return array|false Array with PDF info or false if no PDF.
 */
function formapress_crm_get_invoice_pdf_info( $invoice_id ) {
	$pdf_path = get_post_meta( $invoice_id, '_crm_invoice_pdf_path', true );

	if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
		return false;
	}

	return array(
		'path'           => $pdf_path,
		'url'            => get_post_meta( $invoice_id, '_crm_invoice_pdf_url', true ),
		'generated_date' => get_post_meta( $invoice_id, '_crm_invoice_pdf_generated_date', true ),
		'template_id'    => get_post_meta( $invoice_id, '_crm_invoice_pdf_template_id', true ),
		'exists'         => true,
	);
}

/**
 * Delete invoice PDF.
 *
 * @param int $invoice_id Invoice post ID.
 * @return bool True on success, false on failure.
 */
function formapress_crm_delete_invoice_pdf( $invoice_id ) {
	$pdf_path = get_post_meta( $invoice_id, '_crm_invoice_pdf_path', true );

	if ( $pdf_path && file_exists( $pdf_path ) ) {
		wp_delete_file( $pdf_path );
	}

	// Remove meta.
	delete_post_meta( $invoice_id, '_crm_invoice_pdf_path' );
	delete_post_meta( $invoice_id, '_crm_invoice_pdf_url' );
	delete_post_meta( $invoice_id, '_crm_invoice_pdf_generated_date' );
	delete_post_meta( $invoice_id, '_crm_invoice_pdf_template_id' );

	return true;
}

/**
 * AJAX handler for generating invoice PDF.
 */
function formapress_crm_ajax_generate_invoice_pdf() {
	// Verify nonce.
	check_ajax_referer( 'formapress_crm_invoice_nonce', 'nonce' );

	// Check permissions.
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permissions insuffisantes.', 'formapress-crm' ) ) );
	}

	$invoice_id  = isset( $_POST['invoice_id'] ) ? intval( $_POST['invoice_id'] ) : 0;
	$template_id = isset( $_POST['template_id'] ) ? intval( $_POST['template_id'] ) : 0;

	if ( ! $invoice_id ) {
		wp_send_json_error( array( 'message' => __( 'ID de facture manquant.', 'formapress-crm' ) ) );
	}

	// Generate PDF.
	$result = formapress_crm_generate_invoice_pdf( $invoice_id, $template_id );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success(
		array(
			'message'  => __( 'PDF généré avec succès.', 'formapress-crm' ),
			'pdf_url'  => $result['url'],
			'filename' => $result['filename'],
		)
	);
}
add_action( 'wp_ajax_formapress_generate_invoice_pdf', 'formapress_crm_ajax_generate_invoice_pdf' );

/**
 * AJAX handler for previewing invoice HTML before PDF generation.
 */
function formapress_crm_ajax_preview_invoice_html() {
	// Verify nonce.
	check_ajax_referer( 'formapress_crm_invoice_nonce', 'nonce' );

	// Check permissions.
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permissions insuffisantes.', 'formapress-crm' ) ) );
	}

	$invoice_id  = isset( $_POST['invoice_id'] ) ? intval( $_POST['invoice_id'] ) : 0;
	$template_id = isset( $_POST['template_id'] ) ? intval( $_POST['template_id'] ) : 0;

	if ( ! $invoice_id ) {
		wp_send_json_error( array( 'message' => __( 'ID de facture manquant.', 'formapress-crm' ) ) );
	}

	// Get ZQPM ID.
	$zqpm_id = get_post_meta( $invoice_id, '_crm_invoice_zqpm_id', true );
	if ( ! $zqpm_id ) {
		wp_send_json_error( array( 'message' => __( 'Cette facture n\'est pas liée à une session ZQPM.', 'formapress-crm' ) ) );
	}

	// Get or select template.
	if ( ! $template_id ) {
		$template_id = formapress_crm_get_default_invoice_template();
		if ( ! $template_id ) {
			wp_send_json_error( array( 'message' => __( 'Aucun modèle de facture trouvé.', 'formapress-crm' ) ) );
		}
	}

	// Get template content.
	$template = get_post( $template_id );
	if ( ! $template || 'document_template' !== $template->post_type ) {
		wp_send_json_error( array( 'message' => __( 'Modèle de document invalide.', 'formapress-crm' ) ) );
	}

	// Process shortcodes.
	$html_content = $template->post_content;

	if ( has_filter( 'zqpm_process_shortcodes' ) ) {
		$html_content = apply_filters(
			'zqpm_process_shortcodes',
			$html_content,
			'invoice',
			array(
				'invoice_id' => $invoice_id,
				'zqpm_id'    => $zqpm_id,
			)
		);
	}

	$html_content = do_shortcode( $html_content );

	// Wrap in basic styling for preview.
	$preview_html  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
	$preview_html .= '<style>body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }</style>';
	$preview_html .= '</head><body>' . $html_content . '</body></html>';

	wp_send_json_success( array( 'html' => $preview_html ) );
}
add_action( 'wp_ajax_formapress_preview_invoice_html', 'formapress_crm_ajax_preview_invoice_html' );

/**
 * Add PDF status to invoice editor sidebar.
 *
 * @param int $invoice_id Invoice post ID.
 */
function formapress_crm_invoice_pdf_status_display( $invoice_id ) {
	$pdf_info = formapress_crm_get_invoice_pdf_info( $invoice_id );

	echo '<div class="invoice-pdf-status" style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-radius: 4px;">';
	echo '<h4 style="margin-top: 0;">' . esc_html__( 'Document PDF', 'formapress-crm' ) . '</h4>';

	if ( $pdf_info ) {
		echo '<p style="margin: 5px 0;"><span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> ';
		echo esc_html__( 'PDF généré', 'formapress-crm' ) . '</p>';
		echo '<p style="margin: 5px 0; font-size: 12px; color: #646970;">';
		echo esc_html__( 'Généré le', 'formapress-crm' ) . ' : ' . esc_html( date_i18n( 'd/m/Y H:i', strtotime( $pdf_info['generated_date'] ) ) );
		echo '</p>';
		echo '<p style="margin: 10px 0;">';
		echo '<a href="' . esc_url( $pdf_info['url'] ) . '" class="button button-secondary" target="_blank">';
		echo '<span class="dashicons dashicons-download"></span> ' . esc_html__( 'Télécharger', 'formapress-crm' );
		echo '</a></p>';
		echo '<p style="margin: 10px 0;">';
		echo '<button type="button" class="button button-secondary" id="regenerate-pdf-btn">';
		echo '<span class="dashicons dashicons-update"></span> ' . esc_html__( 'Regénérer', 'formapress-crm' );
		echo '</button></p>';
	} else {
		echo '<p style="margin: 5px 0;"><span class="dashicons dashicons-warning" style="color: #dba617;"></span> ';
		echo esc_html__( 'Aucun PDF généré', 'formapress-crm' ) . '</p>';
		echo '<p style="margin: 10px 0;">';
		echo '<button type="button" class="button button-primary" id="generate-pdf-btn">';
		echo '<span class="dashicons dashicons-media-document"></span> ' . esc_html__( 'Générer le PDF', 'formapress-crm' );
		echo '</button></p>';
	}

	echo '</div>';

	// Add JavaScript for PDF generation.
	?>
	<script type="text/javascript">
	jQuery(document).ready(function($) {
		var invoiceId = <?php echo intval( $invoice_id ); ?>;
		var nonce = '<?php echo esc_js( wp_create_nonce( 'formapress_crm_invoice_nonce' ) ); ?>';

		// Generate PDF button.
		$('#generate-pdf-btn, #regenerate-pdf-btn').on('click', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var originalText = $btn.html();

			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> Génération...');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'formapress_generate_invoice_pdf',
					invoice_id: invoiceId,
					nonce: nonce
				},
				success: function(response) {
					if (response.success) {
						// Open PDF in new tab instead of alert.
						window.open(response.data.pdf_url, '_blank');
						// Reload page to show updated status.
						setTimeout(function() {
							location.reload();
						}, 500);
					} else {
						alert('Erreur : ' + response.data.message);
						$btn.prop('disabled', false).html(originalText);
					}
				},
				error: function() {
					alert('Erreur réseau lors de la génération du PDF.');
					$btn.prop('disabled', false).html(originalText);
				}
			});
		});
	});
	</script>
	<?php
}
