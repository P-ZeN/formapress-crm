<?php
/**
 * FormaPress CRM - Communication Sending
 *
 * Handles sending templated emails with PDF attachments and automatic activity logging.
 *
 * @package FormaPress_CRM
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send templated email with PDF attachments.
 *
 * Generates PDFs from document templates, sends email, and logs activity.
 *
 * @param array $data {
 *     Email sending parameters.
 *
 *     @type int    $opportunity_id     CRM opportunity post ID.
 *     @type int    $person_id          CRM person post ID.
 *     @type int    $company_id         CRM company post ID (optional).
 *     @type int    $email_template_id  Email template post ID (optional).
 *     @type array  $document_template_ids Array of document template post IDs.
 *     @type string $to                 Recipient email address.
 *     @type string $subject            Email subject.
 *     @type string $body               Email body (HTML).
 *     @type string $from_name          Sender name (optional).
 *     @type string $from_email         Sender email (optional).
 * }
 *
 * @return array {
 *     Result of send operation.
 *
 *     @type bool   $success      True if email sent successfully.
 *     @type string $message      Success or error message.
 *     @type int    $activity_id  Created activity post ID (if successful).
 *     @type array  $pdf_paths    Paths to generated PDFs.
 * }
 */
function formapress_crm_send_communication( $data ) {
	$result = array(
		'success'     => false,
		'message'     => '',
		'activity_id' => 0,
		'pdf_paths'   => array(),
	);

	// Validate required fields.
	if ( empty( $data['opportunity_id'] ) || empty( $data['to'] ) || empty( $data['subject'] ) ) {
		$result['message'] = __( 'Champs obligatoires manquants.', 'formapress-crm' );
		return $result;
	}

	// Validate email address.
	if ( ! is_email( $data['to'] ) ) {
		$result['message'] = __( 'Adresse email invalide.', 'formapress-crm' );
		return $result;
	}

	// Generate PDF attachments if document templates specified.
	$attachments = array();
	if ( ! empty( $data['document_template_ids'] ) && is_array( $data['document_template_ids'] ) ) {
		$pdf_paths = formapress_crm_generate_multiple_pdfs(
			$data['document_template_ids'],
			$data['opportunity_id'],
			$data['person_id'] ?? 0,
			$data['company_id'] ?? 0
		);

		$attachments         = array_values( $pdf_paths );
		$result['pdf_paths'] = $pdf_paths;
	}

	// Prepare email headers.
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );

	if ( ! empty( $data['from_name'] ) && ! empty( $data['from_email'] ) ) {
		$headers[] = 'From: ' . $data['from_name'] . ' <' . $data['from_email'] . '>';
	}

	// Send email.
	$sent = wp_mail( $data['to'], $data['subject'], $data['body'], $headers, $attachments );

	if ( ! $sent ) {
		$result['message'] = __( 'Échec de l\'envoi de l\'email.', 'formapress-crm' );

		// Clean up generated PDFs.
		if ( ! empty( $attachments ) ) {
			formapress_crm_cleanup_temp_pdfs( $attachments );
		}

		return $result;
	}

	// Log activity automatically.
	$activity_data = array(
		'type'           => 'email',
		'opportunity_id' => $data['opportunity_id'],
		'subject'        => sprintf( __( 'Email envoyé: %s', 'formapress-crm' ), $data['subject'] ),
		'details'        => '',
	);

	// Build activity details.
	$details   = array();
	$details[] = '<strong>' . __( 'Destinataire:', 'formapress-crm' ) . '</strong> ' . esc_html( $data['to'] );
	$details[] = '<strong>' . __( 'Objet:', 'formapress-crm' ) . '</strong> ' . esc_html( $data['subject'] );

	if ( ! empty( $attachments ) ) {
		$details[] = '<strong>' . __( 'Pièces jointes:', 'formapress-crm' ) . '</strong> ' . count( $attachments ) . ' document(s)';

		// List attached template names.
		$template_names = array();
		foreach ( $data['document_template_ids'] as $template_id ) {
			$template = get_post( $template_id );
			if ( $template ) {
				$template_names[] = esc_html( $template->post_title );
			}
		}
		if ( ! empty( $template_names ) ) {
			$details[] = '• ' . implode( '<br>• ', $template_names );
		}
	}

	$activity_data['details'] = implode( '<br>', $details );

	$activity_id = formapress_crm_log_activity( $activity_data );

	if ( $activity_id ) {
		$result['activity_id'] = $activity_id;
	}

	// Clean up temporary PDFs after sending.
	if ( ! empty( $attachments ) ) {
		formapress_crm_cleanup_temp_pdfs( $attachments );
	}

	$result['success'] = true;
	$result['message'] = __( 'Email envoyé avec succès.', 'formapress-crm' );

	return $result;
}

/**
 * Log communication activity in CRM.
 *
 * Creates a crm_activity post to record the communication.
 *
 * @param array $data {
 *     Activity data.
 *
 *     @type string $type           Activity type (email, call, meeting, etc.).
 *     @type int    $opportunity_id CRM opportunity post ID.
 *     @type string $subject        Activity subject/title.
 *     @type string $details        Activity details (HTML allowed).
 * }
 *
 * @return int|false Activity post ID on success, false on failure.
 */
function formapress_crm_log_activity( $data ) {
	// Validate required fields.
	if ( empty( $data['type'] ) || empty( $data['opportunity_id'] ) || empty( $data['subject'] ) ) {
		return false;
	}

	// Create activity post.
	$activity_id = wp_insert_post(
		array(
			'post_type'    => 'crm_activity',
			'post_title'   => $data['subject'],
			'post_content' => $data['details'] ?? '',
			'post_status'  => 'publish',
			'post_author'  => get_current_user_id(),
		)
	);

	if ( is_wp_error( $activity_id ) || ! $activity_id ) {
		return false;
	}

	// Save activity metadata.
	update_post_meta( $activity_id, '_crm_activity_type', sanitize_text_field( $data['type'] ) );
	update_post_meta( $activity_id, '_crm_activity_opportunity_id', absint( $data['opportunity_id'] ) );
	update_post_meta( $activity_id, '_crm_activity_date', current_time( 'mysql' ) );

	return $activity_id;
}

/**
 * AJAX handler for sending communication.
 *
 * Handles AJAX request from communication modal.
 */
function formapress_crm_ajax_send_communication() {
	// Verify nonce.
	check_ajax_referer( 'formapress_crm_send_communication', 'nonce' );

	// Verify user permissions.
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permissions insuffisantes.', 'formapress-crm' ) ) );
	}

	// Get and sanitize POST data.
	$opportunity_id        = absint( $_POST['opportunity_id'] ?? 0 );
	$person_id             = absint( $_POST['person_id'] ?? 0 );
	$company_id            = absint( $_POST['company_id'] ?? 0 );
	$email_template_id     = absint( $_POST['email_template_id'] ?? 0 );
	$document_template_ids = array_map( 'absint', $_POST['document_template_ids'] ?? array() );
	$to                    = sanitize_email( $_POST['to'] ?? '' );
	$subject               = sanitize_text_field( $_POST['subject'] ?? '' );
	$body                  = wp_kses_post( $_POST['body'] ?? '' );

	// Send communication.
	$result = formapress_crm_send_communication(
		array(
			'opportunity_id'        => $opportunity_id,
			'person_id'             => $person_id,
			'company_id'            => $company_id,
			'email_template_id'     => $email_template_id,
			'document_template_ids' => $document_template_ids,
			'to'                    => $to,
			'subject'               => $subject,
			'body'                  => $body,
		)
	);

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
}
add_action( 'wp_ajax_formapress_crm_send_communication', 'formapress_crm_ajax_send_communication' );

/**
 * AJAX handler for previewing email template.
 *
 * Renders email template with shortcodes replaced based on CRM context.
 */
function formapress_crm_ajax_preview_email_template() {
	// Verify nonce.
	check_ajax_referer( 'formapress_crm_preview_template', 'nonce' );

	$template_id    = absint( $_POST['template_id'] ?? 0 );
	$opportunity_id = absint( $_POST['opportunity_id'] ?? 0 );
	$person_id      = absint( $_POST['person_id'] ?? 0 );
	$company_id     = absint( $_POST['company_id'] ?? 0 );

	if ( ! $template_id ) {
		wp_send_json_error( array( 'message' => __( 'Template non spécifié.', 'formapress-crm' ) ) );
	}

	$template = get_post( $template_id );

	if ( ! $template || 'email_template' !== $template->post_type ) {
		wp_send_json_error( array( 'message' => __( 'Template invalide.', 'formapress-crm' ) ) );
	}

	// Set CRM context globals for shortcode processing.
	global $crm_context_opportunity_id, $crm_context_person_id, $crm_context_company_id;

	$crm_context_opportunity_id = $opportunity_id;
	$crm_context_person_id      = $person_id;
	$crm_context_company_id     = $company_id;

	// Process shortcodes in template content.
	$subject = do_shortcode( get_post_meta( $template_id, '_email_subject', true ) );
	$body    = do_shortcode( $template->post_content );

	// Clear globals.
	$crm_context_opportunity_id = null;
	$crm_context_person_id      = null;
	$crm_context_company_id     = null;

	wp_send_json_success(
		array(
			'subject' => $subject,
			'body'    => $body,
		)
	);
}
add_action( 'wp_ajax_formapress_crm_preview_email_template', 'formapress_crm_ajax_preview_email_template' );

/**
 * AJAX: Preview PDF document (HTML output in new tab).
 */
function formapress_crm_ajax_preview_pdf() {
	check_ajax_referer( 'formapress_opportunity_editor', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( __( 'Permission refusée', 'formapress-crm' ) );
	}

	$template_id    = absint( $_GET['template_id'] ?? 0 );
	$opportunity_id = absint( $_GET['opportunity_id'] ?? 0 );

	if ( ! $template_id || ! $opportunity_id ) {
		wp_die( __( 'Paramètres manquants', 'formapress-crm' ) );
	}

	$template = get_post( $template_id );
	if ( ! $template || 'document_template' !== $template->post_type ) {
		wp_die( __( 'Modèle invalide', 'formapress-crm' ) );
	}

	$opportunity = get_post( $opportunity_id );
	if ( ! $opportunity || 'crm_opportunity' !== $opportunity->post_type ) {
		wp_die( __( 'Opportunité invalide', 'formapress-crm' ) );
	}

	// Get associated entities.
	$person_id  = get_post_meta( $opportunity_id, '_crm_associated_person_id', true );
	$company_id = get_post_meta( $opportunity_id, '_crm_associated_company_id', true );

	// Set CRM context globals for shortcode processing.
	global $crm_context_opportunity_id, $crm_context_person_id, $crm_context_company_id;
	$crm_context_opportunity_id = $opportunity_id;
	$crm_context_person_id      = $person_id;
	$crm_context_company_id     = $company_id;

	// Process shortcodes in template content.
	$content = do_shortcode( $template->post_content );

	// Get custom CSS.
	$custom_css = get_option( 'zqpm_custom_pdf_css', '' );

	// Get header/footer from ZQPM options.
	$template_infos = get_option(
		'zqpm_template_infos',
		array(
			'header' => '',
			'footer' => '',
		)
	);

	// Build HTML output.
	?>
	<!DOCTYPE html>
	<html lang="fr">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title><?php echo esc_html( $template->post_title ); ?> - Aperçu</title>
		<style>
			body {
				font-family: Arial, sans-serif;
				line-height: 1.6;
				max-width: 800px;
				margin: 20px auto;
				padding: 20px;
				background: #f5f5f5;
			}
			.preview-wrapper {
				background: white;
				padding: 40px;
				box-shadow: 0 0 10px rgba(0,0,0,0.1);
			}
			.preview-header {
				background: #0073aa;
				color: white;
				padding: 15px 20px;
				margin: -40px -40px 30px;
				border-radius: 3px 3px 0 0;
			}
			.preview-header h1 {
				margin: 0;
				font-size: 18px;
				font-weight: normal;
			}
			.preview-header .badge {
				display: inline-block;
				background: rgba(255,255,255,0.2);
				padding: 3px 10px;
				border-radius: 3px;
				font-size: 12px;
				margin-left: 10px;
			}
			header {
				margin-bottom: 30px;
				padding-bottom: 20px;
				border-bottom: 1px solid #ddd;
			}
			footer {
				margin-top: 30px;
				padding-top: 20px;
				border-top: 1px solid #ddd;
				font-size: 12px;
				color: #666;
			}
			#content h1 {
				font-size: 24px;
				color: #333;
				margin-top: 0;
			}
			<?php echo wp_kses_post( $custom_css ); ?>
		</style>
	</head>
	<body>
		<div class="preview-wrapper">
			<div class="preview-header">
				<h1>
					<?php echo esc_html( $template->post_title ); ?>
					<span class="badge">APERÇU</span>
				</h1>
			</div>

			<?php if ( ! empty( $template_infos['header'] ) ) : ?>
				<header>
					<?php echo wp_kses_post( nl2br( $template_infos['header'] ) ); ?>
				</header>
			<?php endif; ?>

			<div id="content">
				<h1><?php echo esc_html( $template->post_title ); ?></h1>
				<?php echo wp_kses_post( $content ); ?>
			</div>

			<?php if ( ! empty( $template_infos['footer'] ) ) : ?>
				<footer>
					<?php echo wp_kses_post( nl2br( $template_infos['footer'] ) ); ?>
				</footer>
			<?php endif; ?>
		</div>
	</body>
	</html>
	<?php

	// Clear globals.
	$crm_context_opportunity_id = null;
	$crm_context_person_id      = null;
	$crm_context_company_id     = null;

	exit;
}
add_action( 'wp_ajax_formapress_crm_preview_pdf', 'formapress_crm_ajax_preview_pdf' );
