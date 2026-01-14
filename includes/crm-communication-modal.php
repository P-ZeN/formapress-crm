<?php
/**
 * FormaPress CRM - Communication Modal
 *
 * Modal UI for sending templated communications with PDF attachments.
 *
 * @package FormaPress_CRM
 * @since 2.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render communication modal HTML.
 *
 * This modal is included once on opportunity editor page and shown via JavaScript.
 *
 * @param int $opportunity_id Current opportunity post ID.
 */
function formapress_crm_render_communication_modal( $opportunity_id ) {
	// Get opportunity data for pre-filling.
	$person_id  = get_post_meta( $opportunity_id, '_crm_associated_person_id', true );
	$company_id = get_post_meta( $opportunity_id, '_crm_associated_company_id', true );

	// Get recipient email.
	$recipient_email = '';
	$recipient_name  = '';

	if ( $person_id ) {
		$recipient_email = get_post_meta( $person_id, '_crm_email', true );
		$person          = get_post( $person_id );
		$recipient_name  = $person ? $person->post_title : '';
	}

	// Get current stage for template filtering.
	$stage = get_post_meta( $opportunity_id, '_crm_opportunity_stage', true );

	// Get available document templates for this stage.
	$document_templates = formapress_crm_get_stage_document_templates( $opportunity_id );

	?>
	<div id="crm-communication-modal" class="crm-modal" style="display: none;">
		<div class="crm-modal-overlay"></div>
		<div class="crm-modal-dialog">
			<div class="crm-modal-header">
				<h2><?php esc_html_e( 'Envoyer une communication', 'formapress-crm' ); ?></h2>
				<button type="button" class="crm-modal-close" aria-label="<?php esc_attr_e( 'Fermer', 'formapress-crm' ); ?>">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>

			<div class="crm-modal-body">
				<form id="crm-send-communication-form">
					<!-- Hidden fields -->
					<input type="hidden" name="opportunity_id" value="<?php echo esc_attr( $opportunity_id ); ?>">
					<input type="hidden" name="person_id" value="<?php echo esc_attr( $person_id ); ?>">
					<input type="hidden" name="company_id" value="<?php echo esc_attr( $company_id ); ?>">
					<input type="hidden" name="email_template_id" value="">

					<!-- Recipient -->
					<div class="form-field">
						<label for="comm-recipient" class="form-label required">
							<?php esc_html_e( 'Destinataire', 'formapress-crm' ); ?>
						</label>
					<?php if ( $recipient_name && $recipient_email ) : ?>
						<div class="recipient-display">
							<strong><?php echo esc_html( $recipient_name ); ?></strong> &lt;<?php echo esc_html( $recipient_email ); ?>&gt;
						</div>
						<input
							type="hidden"
							name="to"
							value="<?php echo esc_attr( $recipient_email ); ?>"
						>
					<?php else : ?>
						<input
							type="email"
							id="comm-recipient"
							name="to"
							class="form-input"
							placeholder="<?php esc_attr_e( 'email@example.com', 'formapress-crm' ); ?>"
							required
						>
						<p class="form-help"><?php esc_html_e( 'Aucun contact associé à cette opportunité.', 'formapress-crm' ); ?></p>
					<?php endif; ?>
					</div>

					<!-- Email Subject -->
					<div class="form-field">
						<label for="comm-subject" class="form-label required">
							<?php esc_html_e( 'Objet', 'formapress-crm' ); ?>
						</label>
						<input
							type="text"
							id="comm-subject"
							name="subject"
							class="form-input"
							required
						>
					</div>

					<!-- Email Body -->
					<div class="form-field">
						<label for="comm-body" class="form-label required">
							<?php esc_html_e( 'Message', 'formapress-crm' ); ?>
						</label>
						<?php
						wp_editor(
							'',
							'comm_body',
							array(
								'textarea_name' => 'body',
								'textarea_rows' => 10,
								'media_buttons' => false,
								'teeny'         => true,
								'quicktags'     => false,
								'tinymce'       => array(
									'toolbar1' => 'bold,italic,underline,link,unlink,bullist,numlist',
									'toolbar2' => '',
								),
							)
						);
						?>
					</div>

					<!-- Documents -->
					<?php if ( ! empty( $document_templates ) ) : ?>
						<div class="form-field">
							<label class="form-label">
								<?php esc_html_e( 'Documents à joindre (PDF)', 'formapress-crm' ); ?>
							</label>
							<div class="document-checkboxes">
								<?php foreach ( $document_templates as $template ) : ?>
									<label class="checkbox-label">
										<input
											type="checkbox"
											name="document_template_ids[]"
											value="<?php echo esc_attr( $template->ID ); ?>"
										>
										<span><?php echo esc_html( $template->post_title ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
							<p class="form-help">
								<?php esc_html_e( 'Les documents sélectionnés seront générés en PDF et joints à l\'email.', 'formapress-crm' ); ?>
							</p>
						</div>
					<?php else : ?>
						<div class="form-field">
							<p class="no-templates-notice">
								<?php esc_html_e( 'Aucun document disponible pour cette étape. Vous pouvez associer des documents à cette étape dans le Centre de communication.', 'formapress-crm' ); ?>
							</p>
						</div>
					<?php endif; ?>

					<!-- Loading indicator -->
					<div class="comm-loading" style="display: none;">
						<span class="spinner is-active"></span>
						<span><?php esc_html_e( 'Génération des PDF et envoi en cours...', 'formapress-crm' ); ?></span>
					</div>

					<!-- Error message -->
					<div class="comm-error" style="display: none;"></div>
				</form>
			</div>

			<div class="crm-modal-footer">
				<button type="button" class="button button-secondary crm-modal-cancel">
					<?php esc_html_e( 'Annuler', 'formapress-crm' ); ?>
				</button>
				<button type="submit" form="crm-send-communication-form" class="button button-primary crm-modal-send">
					<span class="dashicons dashicons-email"></span>
					<?php esc_html_e( 'Envoyer', 'formapress-crm' ); ?>
				</button>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Note: Assets are enqueued from admin-opportunity-editor.php
 * to ensure they load on the custom opportunity editor page.
 */

