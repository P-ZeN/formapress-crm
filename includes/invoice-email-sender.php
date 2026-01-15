<?php
/**
 * FormaPress CRM - Invoice Email Sender
 *
 * Send invoices via email with PDF attachments and tracking.
 *
 * @package FormaPress_CRM
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send invoice email.
 *
 * @param int   $invoice_id  Invoice post ID.
 * @param array $email_data  Email data (to, cc, bcc, subject, message, template_id).
 * @return bool|WP_Error True on success, WP_Error on failure.
 */
function formapress_crm_send_invoice_email( $invoice_id, $email_data ) {
	// Validate invoice.
	$invoice = get_post( $invoice_id );
	if ( ! $invoice || 'crm_invoice' !== $invoice->post_type ) {
		return new WP_Error( 'invalid_invoice', __( 'Facture invalide.', 'formapress-crm' ) );
	}

	// Get ZQPM ID.
	$zqpm_id = get_post_meta( $invoice_id, '_crm_invoice_zqpm_id', true );
	if ( ! $zqpm_id ) {
		return new WP_Error( 'no_zqpm', __( 'Cette facture n\'est pas liée à une session ZQPM.', 'formapress-crm' ) );
	}

	// Ensure PDF exists.
	if ( ! function_exists( 'formapress_crm_get_invoice_pdf_info' ) ) {
		return new WP_Error( 'pdf_system_unavailable', __( 'Le système PDF n\'est pas disponible.', 'formapress-crm' ) );
	}

	$pdf_info = formapress_crm_get_invoice_pdf_info( $invoice_id );
	if ( ! $pdf_info || ! $pdf_info['exists'] ) {
		// Try to generate PDF automatically.
		if ( function_exists( 'formapress_crm_generate_invoice_pdf' ) ) {
			$pdf_result = formapress_crm_generate_invoice_pdf( $invoice_id );
			if ( is_wp_error( $pdf_result ) ) {
				return new WP_Error( 'pdf_generation_failed', __( 'Impossible de générer le PDF avant l\'envoi.', 'formapress-crm' ) );
			}
			$pdf_info = formapress_crm_get_invoice_pdf_info( $invoice_id );
		} else {
			return new WP_Error( 'no_pdf', __( 'Aucun PDF disponible pour cette facture.', 'formapress-crm' ) );
		}
	}

	// Process email content.
	$to      = isset( $email_data['to'] ) ? sanitize_email( $email_data['to'] ) : '';
	$cc      = isset( $email_data['cc'] ) ? sanitize_text_field( $email_data['cc'] ) : '';
	$bcc     = isset( $email_data['bcc'] ) ? sanitize_text_field( $email_data['bcc'] ) : '';
	$subject = isset( $email_data['subject'] ) ? sanitize_text_field( $email_data['subject'] ) : '';
	$message = isset( $email_data['message'] ) ? wp_kses_post( $email_data['message'] ) : '';

	// Validate required fields.
	if ( empty( $to ) ) {
		return new WP_Error( 'missing_recipient', __( 'Destinataire manquant.', 'formapress-crm' ) );
	}

	if ( empty( $subject ) ) {
		return new WP_Error( 'missing_subject', __( 'Sujet manquant.', 'formapress-crm' ) );
	}

	// Process shortcodes in subject and message.
	if ( has_filter( 'zqpm_process_shortcodes' ) ) {
		$subject = apply_filters(
			'zqpm_process_shortcodes',
			$subject,
			'invoice',
			array(
				'invoice_id' => $invoice_id,
				'zqpm_id'    => $zqpm_id,
			)
		);
		$message = apply_filters(
			'zqpm_process_shortcodes',
			$message,
			'invoice',
			array(
				'invoice_id' => $invoice_id,
				'zqpm_id'    => $zqpm_id,
			)
		);
	}

	$subject = do_shortcode( $subject );
	$message = do_shortcode( $message );

	// Prepare headers.
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );

	if ( ! empty( $cc ) ) {
		$cc_emails = array_map( 'trim', explode( ',', $cc ) );
		foreach ( $cc_emails as $cc_email ) {
			if ( is_email( $cc_email ) ) {
				$headers[] = 'Cc: ' . $cc_email;
			}
		}
	}

	if ( ! empty( $bcc ) ) {
		$bcc_emails = array_map( 'trim', explode( ',', $bcc ) );
		foreach ( $bcc_emails as $bcc_email ) {
			if ( is_email( $bcc_email ) ) {
				$headers[] = 'Bcc: ' . $bcc_email;
			}
		}
	}

	// Attach PDF.
	$attachments = array( $pdf_info['path'] );

	// Send email.
	$sent = wp_mail( $to, $subject, $message, $headers, $attachments );

	if ( ! $sent ) {
		return new WP_Error( 'email_failed', __( 'L\'envoi de l\'email a échoué.', 'formapress-crm' ) );
	}

	// Track email send.
	$email_log = array(
		'date'        => current_time( 'Y-m-d H:i:s' ),
		'to'          => $to,
		'cc'          => $cc,
		'bcc'         => $bcc,
		'subject'     => $subject,
		'template_id' => isset( $email_data['template_id'] ) ? intval( $email_data['template_id'] ) : 0,
		'sent_by'     => get_current_user_id(),
	);

	// Get existing email history.
	$email_history = get_post_meta( $invoice_id, '_crm_invoice_emails_sent', true );
	if ( ! is_array( $email_history ) ) {
		$email_history = array();
	}

	// Add new send to history.
	$email_history[] = $email_log;
	update_post_meta( $invoice_id, '_crm_invoice_emails_sent', $email_history );

	// Update last sent date.
	update_post_meta( $invoice_id, '_crm_invoice_last_sent_date', current_time( 'Y-m-d H:i:s' ) );

	return true;
}

/**
 * Get invoice email templates.
 *
 * @return array Array of template objects.
 */
function formapress_crm_get_invoice_email_templates() {
	// Check if email_template CPT exists (from ZQPM).
	if ( ! post_type_exists( 'email_template' ) ) {
		return array();
	}

	$templates = get_posts(
		array(
			'post_type'      => 'email_template',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'tax_query'      => array(
				array(
					'taxonomy' => 'zqpm_steps',
					'field'    => 'slug',
					'terms'    => 'step_19',
				),
			),
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	return $templates;
}

/**
 * Get default invoice email template.
 *
 * @return int|false Template ID or false.
 */
function formapress_crm_get_default_invoice_email_template() {
	$templates = formapress_crm_get_invoice_email_templates();
	return ! empty( $templates ) ? $templates[0]->ID : false;
}

/**
 * Get email template content.
 *
 * @param int $template_id Template post ID.
 * @return array|false Array with 'subject' and 'message' or false.
 */
function formapress_crm_get_email_template_content( $template_id ) {
	$template = get_post( $template_id );
	if ( ! $template || 'email_template' !== $template->post_type ) {
		return false;
	}

	// ZQPM email templates store subject in meta and content in post_content.
	$subject = get_post_meta( $template_id, '_email_subject', true );
	if ( empty( $subject ) ) {
		$subject = $template->post_title;
	}

	return array(
		'subject' => $subject,
		'message' => $template->post_content,
	);
}

/**
 * Get invoice email history.
 *
 * @param int $invoice_id Invoice post ID.
 * @return array Array of email send records.
 */
function formapress_crm_get_invoice_email_history( $invoice_id ) {
	$history = get_post_meta( $invoice_id, '_crm_invoice_emails_sent', true );
	return is_array( $history ) ? $history : array();
}

/**
 * AJAX handler for sending invoice email.
 */
function formapress_crm_ajax_send_invoice_email() {
	// Verify nonce.
	check_ajax_referer( 'formapress_crm_invoice_nonce', 'nonce' );

	// Check permissions.
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permissions insuffisantes.', 'formapress-crm' ) ) );
	}

	$invoice_id = isset( $_POST['invoice_id'] ) ? intval( $_POST['invoice_id'] ) : 0;

	if ( ! $invoice_id ) {
		wp_send_json_error( array( 'message' => __( 'ID de facture manquant.', 'formapress-crm' ) ) );
	}

	// Prepare email data.
	$email_data = array(
		'to'          => isset( $_POST['to'] ) ? sanitize_email( $_POST['to'] ) : '',
		'cc'          => isset( $_POST['cc'] ) ? sanitize_text_field( $_POST['cc'] ) : '',
		'bcc'         => isset( $_POST['bcc'] ) ? sanitize_text_field( $_POST['bcc'] ) : '',
		'subject'     => isset( $_POST['subject'] ) ? sanitize_text_field( $_POST['subject'] ) : '',
		'message'     => isset( $_POST['message'] ) ? wp_kses_post( $_POST['message'] ) : '',
		'template_id' => isset( $_POST['template_id'] ) ? intval( $_POST['template_id'] ) : 0,
	);

	// Send email.
	$result = formapress_crm_send_invoice_email( $invoice_id, $email_data );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	wp_send_json_success( array( 'message' => __( 'Facture envoyée avec succès.', 'formapress-crm' ) ) );
}
add_action( 'wp_ajax_formapress_send_invoice_email', 'formapress_crm_ajax_send_invoice_email' );

/**
 * AJAX handler for loading email template.
 */
function formapress_crm_ajax_load_email_template() {
	// Verify nonce.
	check_ajax_referer( 'formapress_crm_invoice_nonce', 'nonce' );

	// Check permissions.
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permissions insuffisantes.', 'formapress-crm' ) ) );
	}

	$template_id = isset( $_POST['template_id'] ) ? intval( $_POST['template_id'] ) : 0;
	$invoice_id  = isset( $_POST['invoice_id'] ) ? intval( $_POST['invoice_id'] ) : 0;

	if ( ! $template_id ) {
		wp_send_json_error( array( 'message' => __( 'ID de modèle manquant.', 'formapress-crm' ) ) );
	}

	$template = get_post( $template_id );
	if ( ! $template ) {
		wp_send_json_error( array( 'message' => __( 'Modèle introuvable.', 'formapress-crm' ) ) );
	}

	$content = formapress_crm_get_email_template_content( $template_id );
	if ( ! $content ) {
		wp_send_json_error( array( 'message' => __( 'Modèle introuvable.', 'formapress-crm' ) ) );
	}

	// Process shortcodes if invoice provided.
	if ( $invoice_id ) {
		$zqpm_id    = get_post_meta( $invoice_id, '_crm_invoice_zqpm_id', true );
		$company_id = get_post_meta( $invoice_id, '_crm_invoice_company_id', true );
		$person_id  = get_post_meta( $invoice_id, '_crm_invoice_person_id', true );

		// Build recipient object for subject processing.
		$destinataire = new stdClass();
		if ( $person_id ) {
			$destinataire->nom    = get_post_meta( $person_id, '_crm_nom', true );
			$destinataire->prenom = get_post_meta( $person_id, '_crm_prenom', true );
			$destinataire->mail   = get_post_meta( $person_id, '_crm_email', true );
		} elseif ( $company_id ) {
			$company_post         = get_post( $company_id );
			$destinataire->nom    = $company_post ? $company_post->post_title : '';
			$destinataire->prenom = '';
			$destinataire->mail   = get_post_meta( $company_id, 'crm_company_attributes_email', true );
		}

		// Process subject using ZQPM function.
		if ( function_exists( 'zqpm_setup_emaill_subject' ) && $zqpm_id ) {
			// Create minimal context for invoice emails.
			$invoice_context             = new stdClass();
			$invoice_context->step       = 19; // Facturation step.
			$invoice_context->zqp_id     = $zqpm_id;
			$invoice_context->session_id = get_post_meta( $zqpm_id, 'zqpm_session_id', true );
			if ( class_exists( 'zSession' ) ) {
				$invoice_context->session = new zSession( $invoice_context->session_id );
			}
			$invoice_context->is_live      = false; // Preview mode.
			$invoice_context->is_collector = false;

			$content['subject'] = zqpm_setup_emaill_subject( $template, $destinataire, $invoice_context );
		}

		// Process message shortcodes.
		if ( $zqpm_id && has_filter( 'zqpm_process_shortcodes' ) ) {
			$content['message'] = apply_filters(
				'zqpm_process_shortcodes',
				$content['message'],
				'invoice',
				array(
					'invoice_id' => $invoice_id,
					'zqpm_id'    => $zqpm_id,
				)
			);
		}
	}

	wp_send_json_success( $content );
}
add_action( 'wp_ajax_formapress_load_email_template', 'formapress_crm_ajax_load_email_template' );

/**
 * Display email history in invoice editor sidebar.
 *
 * @param int $invoice_id Invoice post ID.
 */
function formapress_crm_invoice_email_history_display( $invoice_id ) {
	$email_history   = formapress_crm_get_invoice_email_history( $invoice_id );
	$email_templates = formapress_crm_get_invoice_email_templates();

	echo '<div class="invoice-email-history" style="margin-top: 15px; padding: 10px; background: #f0f0f1; border-radius: 4px;">';
	echo '<h4 style="margin-top: 0;">' . esc_html__( 'Envois par email', 'formapress-crm' ) . '</h4>';

	if ( ! empty( $email_history ) ) {
		echo '<div style="max-height: 200px; overflow-y: auto;">';
		foreach ( array_reverse( $email_history ) as $email ) {
			$user = get_userdata( $email['sent_by'] );
			echo '<div style="margin-bottom: 10px; padding: 8px; background: white; border-radius: 3px; font-size: 12px;">';
			echo '<div style="margin-bottom: 3px;"><strong>' . esc_html( date_i18n( 'd/m/Y H:i', strtotime( $email['date'] ) ) ) . '</strong></div>';
			echo '<div style="color: #646970;">À: ' . esc_html( $email['to'] ) . '</div>';
			if ( ! empty( $email['cc'] ) ) {
				echo '<div style="color: #646970;">Cc: ' . esc_html( $email['cc'] ) . '</div>';
			}
			echo '<div style="color: #50575e; margin-top: 3px;">' . esc_html( wp_trim_words( $email['subject'], 8 ) ) . '</div>';
			if ( $user ) {
				echo '<div style="color: #787c82; font-size: 11px; margin-top: 3px;">Par: ' . esc_html( $user->display_name ) . '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
		echo '<p style="margin: 10px 0 5px; font-size: 12px; color: #646970;">';
		printf(
			esc_html__( '%d envoi(s) au total', 'formapress-crm' ),
			count( $email_history )
		);
		echo '</p>';
	} else {
		echo '<p style="margin: 5px 0; color: #646970;">' . esc_html__( 'Aucun envoi enregistré', 'formapress-crm' ) . '</p>';
	}

	echo '<button type="button" class="button button-secondary" id="send-invoice-email-btn" style="width: 100%; margin-top: 10px;">';
	echo '<span class="dashicons dashicons-email"></span> ' . esc_html__( 'Envoyer la facture', 'formapress-crm' );
	echo '</button>';

	echo '</div>';

	// Email modal.
	formapress_crm_render_invoice_email_modal( $invoice_id );
}

/**
 * Render invoice email modal.
 *
 * @param int $invoice_id Invoice post ID.
 */
function formapress_crm_render_invoice_email_modal( $invoice_id ) {
	$company_id = get_post_meta( $invoice_id, '_crm_invoice_company_id', true );
	$person_id  = get_post_meta( $invoice_id, '_crm_invoice_person_id', true );

	// Try to get default recipient email - prioritize person contact over company.
	$default_to = '';
	if ( $person_id ) {
		$default_to = get_post_meta( $person_id, '_crm_email', true );
	} elseif ( $company_id ) {
		$default_to = get_post_meta( $company_id, 'crm_company_attributes_email', true );
	}

	// Get email templates.
	$email_templates = formapress_crm_get_invoice_email_templates();

	?>
	<div id="invoice-email-modal" class="invoice-email-modal" style="display: none;">
		<div class="invoice-email-modal-backdrop"></div>
		<div class="invoice-email-modal-content">
			<div class="invoice-email-modal-header">
				<h2><?php esc_html_e( 'Envoyer la facture par email', 'formapress-crm' ); ?></h2>
				<button type="button" class="invoice-email-modal-close">&times;</button>
			</div>
			<div class="invoice-email-modal-body">
				<form id="invoice-email-form">
					<?php if ( ! empty( $email_templates ) ) : ?>
						<div class="form-group">
							<label for="email-template-select"><?php esc_html_e( 'Modèle d\'email', 'formapress-crm' ); ?></label>
							<select id="email-template-select" class="widefat">
								<option value=""><?php esc_html_e( '— Sans modèle —', 'formapress-crm' ); ?></option>
								<?php foreach ( $email_templates as $template ) : ?>
									<option value="<?php echo esc_attr( $template->ID ); ?>">
										<?php echo esc_html( $template->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<div class="form-group">
						<label for="email-to"><?php esc_html_e( 'Destinataire', 'formapress-crm' ); ?> <span class="required">*</span></label>
						<input type="email" id="email-to" class="widefat" value="<?php echo esc_attr( $default_to ); ?>">
					</div>

					<div class="form-group">
						<label for="email-cc"><?php esc_html_e( 'Cc (optionnel)', 'formapress-crm' ); ?></label>
						<input type="text" id="email-cc" class="widefat" placeholder="email1@example.com, email2@example.com">
						<p class="description"><?php esc_html_e( 'Plusieurs adresses séparées par des virgules', 'formapress-crm' ); ?></p>
					</div>

					<div class="form-group">
						<label for="email-bcc"><?php esc_html_e( 'Bcc (optionnel)', 'formapress-crm' ); ?></label>
						<input type="text" id="email-bcc" class="widefat" placeholder="email@example.com">
					</div>

					<div class="form-group">
						<label for="email-subject"><?php esc_html_e( 'Sujet', 'formapress-crm' ); ?> <span class="required">*</span></label>
						<input type="text" id="email-subject" class="widefat">
					</div>

					<div class="form-group">
						<label for="email-message"><?php esc_html_e( 'Message', 'formapress-crm' ); ?></label>
						<?php
						wp_editor(
							'',
							'email-message',
							array(
								'textarea_rows' => 10,
								'media_buttons' => false,
								'teeny'         => true,
								'quicktags'     => false,
							)
						);
						?>
						<p class="description">
							<?php esc_html_e( 'Vous pouvez utiliser les shortcodes de facture: [zinvoice_number], [zinvoice_company_name], etc.', 'formapress-crm' ); ?>
						</p>
					</div>

					<div class="form-group">
						<label>
							<input type="checkbox" id="email-attach-pdf" checked disabled>
							<?php esc_html_e( 'Joindre le PDF de la facture', 'formapress-crm' ); ?>
						</label>
					</div>
				</form>
			</div>
			<div class="invoice-email-modal-footer">
				<button type="button" class="button button-secondary invoice-email-modal-close">
					<?php esc_html_e( 'Annuler', 'formapress-crm' ); ?>
				</button>
				<button type="button" class="button button-primary" id="send-email-btn">
					<span class="dashicons dashicons-email"></span>
					<?php esc_html_e( 'Envoyer', 'formapress-crm' ); ?>
				</button>
			</div>
		</div>
	</div>

	<style>
		.invoice-email-modal {
			position: fixed;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			z-index: 100000;
		}
		.invoice-email-modal-backdrop {
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(0, 0, 0, 0.7);
		}
		.invoice-email-modal-content {
			position: absolute;
			top: 50%;
			left: 50%;
			transform: translate(-50%, -50%);
			background: white;
			border-radius: 4px;
			width: 90%;
			max-width: 700px;
			max-height: 90vh;
			display: flex;
			flex-direction: column;
			box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
		}
		.invoice-email-modal-header {
			padding: 20px;
			border-bottom: 1px solid #dcdcde;
			display: flex;
			justify-content: space-between;
			align-items: center;
		}
		.invoice-email-modal-header h2 {
			margin: 0;
		}
		.invoice-email-modal-close {
			background: none;
			border: none;
			font-size: 24px;
			cursor: pointer;
			padding: 0;
			width: 30px;
			height: 30px;
			line-height: 1;
		}
		.invoice-email-modal-body {
			padding: 20px;
			overflow-y: auto;
			flex: 1;
		}
		.invoice-email-modal-body .form-group {
			margin-bottom: 15px;
		}
		.invoice-email-modal-body label {
			display: block;
			font-weight: 600;
			margin-bottom: 5px;
		}
		.invoice-email-modal-body .required {
			color: #d63638;
		}
		.invoice-email-modal-footer {
			padding: 15px 20px;
			border-top: 1px solid #dcdcde;
			display: flex;
			justify-content: flex-end;
			gap: 10px;
		}
	</style>

	<script type="text/javascript">
	jQuery(document).ready(function($) {
		var invoiceId = <?php echo intval( $invoice_id ); ?>;
		var nonce = '<?php echo esc_js( wp_create_nonce( 'formapress_crm_invoice_nonce' ) ); ?>';
		var $modal = $('#invoice-email-modal');
		var currentTemplateId = 0;
		var availableTemplates =
		<?php
		echo wp_json_encode(
			array_map(
				function ( $t ) {
					return $t->ID;
				},
				$email_templates
			)
		);
		?>
									;

		// Helper function to load template content.
		function loadTemplateContent(templateId, callback) {
			if (!templateId) {
				$('#email-subject').val('');
				if (typeof tinyMCE !== 'undefined' && tinyMCE.get('email-message')) {
					tinyMCE.get('email-message').setContent('');
				} else {
					$('#email-message').val('');
				}
				if (callback) callback();
				return;
			}

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'formapress_load_email_template',
					template_id: templateId,
					invoice_id: invoiceId,
					nonce: nonce
				},
				success: function(response) {
					if (response.success) {
						$('#email-subject').val(response.data.subject);
						if (typeof tinyMCE !== 'undefined' && tinyMCE.get('email-message')) {
							tinyMCE.get('email-message').setContent(response.data.message);
						} else {
							$('#email-message').val(response.data.message);
						}
						if (callback) callback();
					} else {
						alert('Erreur : ' + response.data.message);
					}
				},
				error: function() {
					alert('Erreur réseau lors du chargement du modèle.');
				}
			});
		}

		// Open modal with first template loaded by default.
		$('#send-invoice-email-btn').on('click', function(e) {
			e.preventDefault();

			// Load first template if available.
			if (availableTemplates.length > 0) {
				var firstTemplateId = availableTemplates[0];
				currentTemplateId = firstTemplateId;
				$('#email-template-select').val(firstTemplateId);
				loadTemplateContent(firstTemplateId, function() {
					$modal.show();
				});
			} else {
				currentTemplateId = 0;
				$modal.show();
			}
		});

		// Template dropdown change - reload content.
		$('#email-template-select').on('change', function() {
			var templateId = $(this).val();
			currentTemplateId = templateId ? parseInt(templateId) : 0;
			loadTemplateContent(currentTemplateId);
		});

		// Close modal.
		$('.invoice-email-modal-close, .invoice-email-modal-backdrop').on('click', function() {
			$modal.hide();
		});

		// Send email.
		$('#send-email-btn').on('click', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var originalText = $btn.html();

			// Get editor content.
			var message = '';
			if (typeof tinyMCE !== 'undefined' && tinyMCE.get('email-message')) {
				message = tinyMCE.get('email-message').getContent();
			} else {
				message = $('#email-message').val();
			}

			// Validate required fields.
			var to = $('#email-to').val();
			var subject = $('#email-subject').val();
			if (!to || !subject) {
				alert('Veuillez remplir les champs requis (destinataire et sujet).');
				return;
			}

			// Validate that email recipient matches invoice contact.
			if (!to) {
				alert('Erreur: Aucun destinataire trouvé. Assurez-vous qu\'un contact est sélectionné sur la facture.');
				return;
			}

			$btn.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> Envoi...');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'formapress_send_invoice_email',
					invoice_id: invoiceId,
					to: to,
					cc: $('#email-cc').val(),
					bcc: $('#email-bcc').val(),
					subject: subject,
					message: message,
					template_id: currentTemplateId,
					nonce: nonce
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						$modal.hide();
						location.reload();
					} else {
						alert('Erreur : ' + response.data.message);
						$btn.prop('disabled', false).html(originalText);
					}
				},
				error: function() {
					alert('Erreur réseau lors de l\'envoi.');
					$btn.prop('disabled', false).html(originalText);
				}
			});
		});
	});
	</script>
	<?php
}
