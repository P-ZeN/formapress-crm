<?php
/**
 * Custom Invoice Editor Page
 *
 * Provides a modern, clean interface for editing invoices
 * with better UX than the default WordPress editor.
 *
 * @package FormapressCRM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Redirect invoice edit screen to custom editor.
 */
function formapress_crm_redirect_invoice_editor() {
	global $pagenow, $typenow;

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect only, no data modification.
	if ( 'post.php' === $pagenow && isset( $_GET['action'] ) && 'edit' === $_GET['action'] && isset( $_GET['post'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = absint( $_GET['post'] );
		if ( $post_id ) {
			$post_type = get_post_type( $post_id );
			if ( 'crm_invoice' === $post_type ) {
				wp_safe_redirect( admin_url( 'admin.php?page=formapress-crm-edit-invoice&id=' . $post_id ) );
				exit;
			}
		}
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect only, no data modification.
	if ( 'post.php' === $pagenow && 'crm_invoice' === $typenow && isset( $_GET['action'] ) && 'edit' === $_GET['action'] ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( $post_id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=formapress-crm-edit-invoice&id=' . $post_id ) );
			exit;
		}
	}

	// Redirect new invoice screen.
	if ( 'post-new.php' === $pagenow && 'crm_invoice' === $typenow ) {
		wp_safe_redirect( admin_url( 'admin.php?page=formapress-crm-edit-invoice' ) );
		exit;
	}
}
add_action( 'admin_init', 'formapress_crm_redirect_invoice_editor' );

/**
 * Register custom invoice editor page.
 */
function formapress_crm_register_invoice_editor() {
	add_submenu_page(
		null, // Hidden from menu (accessed via redirect).
		'Modifier la facture',
		'Modifier la facture',
		'edit_posts',
		'formapress-crm-edit-invoice',
		'formapress_crm_render_invoice_editor'
	);
}
add_action( 'admin_menu', 'formapress_crm_register_invoice_editor' );

/**
 * Enqueue scripts for invoice editor.
 */
function formapress_crm_enqueue_invoice_editor_scripts( $hook ) {
	if ( 'admin_page_formapress-crm-edit-invoice' !== $hook ) {
		return;
	}

	// Enqueue Select2 for searchable dropdowns.
	wp_enqueue_style(
		'select2',
		'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
		array(),
		'4.1.0'
	);
	wp_enqueue_script(
		'select2',
		'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
		array( 'jquery' ),
		'4.1.0',
		true
	);

	// Enqueue custom editor script.
	wp_enqueue_script(
		'formapress-invoice-editor',
		plugins_url( '../assets/js/invoice-editor.js', __FILE__ ),
		array( 'jquery', 'select2' ),
		'1.0.0',
		true
	);

	// Enqueue base editor styles (shared layout components).
	wp_enqueue_style(
		'formapress-opportunity-editor',
		plugins_url( '../assets/css/opportunity-editor-styles.css', __FILE__ ),
		array(),
		'1.0.0'
	);

	// Enqueue invoice-specific styles (payment tracking, invoice badge).
	wp_enqueue_style(
		'formapress-invoice-editor',
		plugins_url( '../assets/css/invoice-editor-styles.css', __FILE__ ),
		array( 'formapress-opportunity-editor' ),
		'1.0.0'
	);

	// Localize script with data.
	wp_localize_script(
		'formapress-invoice-editor',
		'formapressInvoiceEditor',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'formapress_invoice_editor' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'formapress_crm_enqueue_invoice_editor_scripts' );

/**
 * Render the invoice editor page.
 */
function formapress_crm_render_invoice_editor() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just reading GET parameter for display.
	$invoice_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	$is_new     = 0 === $invoice_id;

	// Get invoice data if editing.
	if ( ! $is_new ) {
		$invoice = get_post( $invoice_id );
		if ( ! $invoice || 'crm_invoice' !== $invoice->post_type ) {
			wp_die( esc_html__( 'Facture non trouvée.', 'formapress-crm' ) );
		}

		// Get invoice meta.
		$invoice_number   = get_post_meta( $invoice_id, '_crm_invoice_number', true );
		$amount           = get_post_meta( $invoice_id, '_crm_invoice_amount', true );
		$payment_status   = get_post_meta( $invoice_id, '_crm_invoice_payment_status', true );
		$funding_source   = get_post_meta( $invoice_id, '_crm_invoice_funding_source', true );
		$invoice_date     = get_post_meta( $invoice_id, '_crm_invoice_date', true );
		$due_date         = get_post_meta( $invoice_id, '_crm_invoice_due_date', true );
		$payment_date     = get_post_meta( $invoice_id, '_crm_invoice_payment_date', true );
		$opco_name        = get_post_meta( $invoice_id, '_crm_invoice_opco_name', true );
		$agreement_number = get_post_meta( $invoice_id, '_crm_invoice_agreement_number', true );
		$company_id       = get_post_meta( $invoice_id, '_crm_invoice_company_id', true );
		$person_id        = get_post_meta( $invoice_id, '_crm_invoice_person_id', true );
		$zqpm_id          = get_post_meta( $invoice_id, '_crm_invoice_zqpm_id', true );
		$payment_method   = get_post_meta( $invoice_id, '_crm_invoice_payment_method', true );
		$payments         = get_post_meta( $invoice_id, '_crm_invoice_payments', true );
		$payments         = $payments ? $payments : array();
		$invoice_title    = $invoice->post_title;
		$invoice_notes    = $invoice->post_content;
	} else {
		// Default values for new invoice.
		$invoice_number   = ''; // Will be auto-generated on save.
		$amount           = '';
		$payment_status   = 'pending';
		$funding_source   = '';
		$invoice_date     = gmdate( 'Y-m-d' );
		$due_date         = gmdate( 'Y-m-d', strtotime( '+30 days' ) );
		$payment_date     = '';
		$opco_name        = '';
		$agreement_number = '';
		$company_id       = 0;
		$person_id        = 0;
		$zqpm_id          = 0;
		$payment_method   = '';
		$payments         = array();
		$invoice_title    = '';
		$invoice_notes    = '';

		// Check for prefill from ZQPM.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only prefill.
		if ( isset( $_GET['prefill'] ) && 'true' === $_GET['prefill'] ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$zqpm_id = isset( $_GET['zqpm_id'] ) ? absint( $_GET['zqpm_id'] ) : 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$company_id = isset( $_GET['company_id'] ) ? absint( $_GET['company_id'] ) : 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$person_id = isset( $_GET['person_id'] ) ? absint( $_GET['person_id'] ) : 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$amount = isset( $_GET['amount'] ) ? sanitize_text_field( wp_unslash( $_GET['amount'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$invoice_title = isset( $_GET['title'] ) ? sanitize_text_field( wp_unslash( $_GET['title'] ) ) : '';

			// Auto-set funding source if company is set.
			if ( $company_id ) {
				$funding_source = 'company';
			} elseif ( $person_id ) {
				$funding_source = 'individual';
			}
		}
	}

	// Calculate balance due.
	$total_paid = 0;
	foreach ( $payments as $payment ) {
		$total_paid += floatval( $payment['amount'] );
	}
	$balance_due = floatval( $amount ) - $total_paid;

	// Get company name if set.
	$company_name = '';
	if ( $company_id ) {
		$company      = get_post( $company_id );
		$company_name = $company ? $company->post_title : '';
	}

	// Get person name if set.
	$person_name = '';
	if ( $person_id ) {
		$person      = get_post( $person_id );
		$person_name = $person ? $person->post_title : '';
	}

	// Get ZQPM session name if set.
	$zqpm_name = '';
	if ( $zqpm_id ) {
		$zqpm = get_post( $zqpm_id );
		if ( $zqpm && function_exists( 'zqpm_construct_new_title' ) ) {
			$zqpm_name = zqpm_construct_new_title( $zqpm->post_title, $zqpm->ID );
		} elseif ( $zqpm ) {
			$zqpm_name = $zqpm->post_title;
		}
	}

	// Payment status options.
	$payment_statuses = array(
		'pending'   => 'En attente',
		'partial'   => 'Paiement partiel',
		'paid'      => 'Payée',
		'cancelled' => 'Annulée',
	);

	// Funding source options.
	$funding_sources = array(
		'company'    => 'Entreprise',
		'individual' => 'Individuel',
		'opco'       => 'OPCO',
		'mixed'      => 'Mixte',
	);

	// Payment method options.
	$payment_methods = array(
		'bank_transfer' => 'Virement bancaire',
		'check'         => 'Chèque',
		'card'          => 'Carte bancaire',
		'cash'          => 'Espèces',
		'other'         => 'Autre',
	);

	?>
	<div class="wrap formapress-editor-wrap">
		<h1 class="wp-heading-inline">
			<?php echo $is_new ? esc_html__( 'Nouvelle facture', 'formapress-crm' ) : esc_html__( 'Modifier la facture', 'formapress-crm' ); ?>
			<?php if ( ! $is_new && $invoice_number ) : ?>
				<span class="invoice-number-badge"><?php echo esc_html( $invoice_number ); ?></span>
			<?php endif; ?>
		</h1>

		<form id="invoice-editor-form" method="post" action="">
			<?php wp_nonce_field( 'formapress_save_invoice', 'formapress_invoice_nonce' ); ?>
			<input type="hidden" name="invoice_id" value="<?php echo esc_attr( $invoice_id ); ?>">
			<input type="hidden" name="action" value="formapress_save_invoice">

			<div class="formapress-editor-container">
				<!-- Main Content -->
				<div class="formapress-editor-main">
					<!-- Invoice Details Section -->
					<div class="editor-section">
						<h2>Détails de la facture</h2>

						<div class="form-row">
							<div class="form-group">
								<label for="invoice_title">Titre de la facture <span class="required">*</span></label>
								<input type="text" id="invoice_title" name="invoice_title" value="<?php echo esc_attr( $invoice_title ); ?>" class="widefat" required placeholder="Ex: Formation WordPress - Janvier 2026">
								<p class="description">Description courte de la prestation facturée</p>
							</div>
						</div>

						<div class="form-row">
							<div class="form-group half">
								<label for="invoice_date">Date de facture <span class="required">*</span></label>
								<input type="date" id="invoice_date" name="invoice_date" value="<?php echo esc_attr( $invoice_date ); ?>" class="widefat" required>
							</div>

							<div class="form-group half">
								<label for="due_date">Date d'échéance</label>
								<input type="date" id="due_date" name="due_date" value="<?php echo esc_attr( $due_date ); ?>" class="widefat">
								<p class="description">Par défaut: +30 jours</p>
							</div>
						</div>

						<div class="form-row">
							<div class="form-group half">
								<label for="amount">Montant (€) <span class="required">*</span></label>
								<input type="number" step="0.01" id="amount" name="amount" value="<?php echo esc_attr( $amount ); ?>" class="widefat" required placeholder="0.00">
							</div>

							<div class="form-group half">
								<label for="payment_status">Statut de paiement</label>
								<select id="payment_status" name="payment_status" class="widefat">
									<?php foreach ( $payment_statuses as $status_key => $status_label ) : ?>
										<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $payment_status, $status_key ); ?>>
											<?php echo esc_html( $status_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<div class="form-row payment-details" style="<?php echo 'paid' === $payment_status || 'partial' === $payment_status ? '' : 'display: none;'; ?>">
							<div class="form-group half">
								<label for="payment_date">Date de paiement</label>
								<input type="date" id="payment_date" name="payment_date" value="<?php echo esc_attr( $payment_date ); ?>" class="widefat">
							</div>

							<div class="form-group half">
								<label for="payment_method">Méthode de paiement</label>
								<select id="payment_method" name="payment_method" class="widefat">
									<option value="">— Sélectionner —</option>
									<?php foreach ( $payment_methods as $method_key => $method_label ) : ?>
										<option value="<?php echo esc_attr( $method_key ); ?>" <?php selected( $payment_method, $method_key ); ?>>
											<?php echo esc_html( $method_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<div class="form-row">
							<div class="form-group">
								<label for="invoice_notes">Notes / Description</label>
								<textarea id="invoice_notes" name="invoice_notes" class="widefat" rows="4" placeholder="Détails de la prestation, conditions particulières..."><?php echo esc_textarea( $invoice_notes ); ?></textarea>
							</div>
						</div>
					</div>

					<!-- Client Information Section -->
					<div class="editor-section">
						<h2>Informations client</h2>

						<div class="form-row">
							<div class="form-group half">
								<label for="company_id">Entreprise</label>
								<select id="company_id" name="company_id" class="widefat select2-ajax" data-ajax-action="formapress_search_companies">
									<?php if ( $company_id && $company_name ) : ?>
										<option value="<?php echo esc_attr( $company_id ); ?>" selected>
											<?php echo esc_html( $company_name ); ?>
										</option>
									<?php else : ?>
										<option value="">— Sélectionner une entreprise —</option>
									<?php endif; ?>
								</select>
							</div>

							<div class="form-group half">
								<label for="person_id">Contact principal</label>
								<select id="person_id" name="person_id" class="widefat select2-ajax" data-ajax-action="formapress_search_persons">
									<?php if ( $person_id && $person_name ) : ?>
										<option value="<?php echo esc_attr( $person_id ); ?>" selected>
											<?php echo esc_html( $person_name ); ?>
										</option>
									<?php else : ?>
										<option value="">— Sélectionner un contact —</option>
									<?php endif; ?>
								</select>
							</div>
						</div>
					</div>

					<!-- Funding Information Section -->
					<div class="editor-section">
						<h2>Source de financement</h2>

						<div class="form-row">
							<div class="form-group half">
								<label for="funding_source">Type de financement</label>
								<select id="funding_source" name="funding_source" class="widefat">
									<option value="">— Sélectionner —</option>
									<?php foreach ( $funding_sources as $source_key => $source_label ) : ?>
										<option value="<?php echo esc_attr( $source_key ); ?>" <?php selected( $funding_source, $source_key ); ?>>
											<?php echo esc_html( $source_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<div class="opco-fields" style="<?php echo 'opco' === $funding_source || 'mixed' === $funding_source ? '' : 'display: none;'; ?>">
							<div class="form-row">
								<div class="form-group half">
									<label for="opco_name">Nom OPCO</label>
									<input type="text" id="opco_name" name="opco_name" value="<?php echo esc_attr( $opco_name ); ?>" class="widefat" placeholder="Ex: OPCO EP, AFDAS...">
								</div>

								<div class="form-group half">
									<label for="agreement_number">Numéro d'accord/convention</label>
									<input type="text" id="agreement_number" name="agreement_number" value="<?php echo esc_attr( $agreement_number ); ?>" class="widefat">
								</div>
							</div>
						</div>
					</div>

					<!-- Payment Tracking Section -->
					<?php if ( ! $is_new ) : ?>
						<div class="editor-section">
							<h2>💰 Suivi des paiements</h2>

							<!-- Balance Summary -->
							<div class="payment-balance-summary">
								<div class="balance-item">
									<span class="balance-label">Montant total:</span>
									<span class="balance-value"><?php echo esc_html( number_format( floatval( $amount ), 2, ',', ' ' ) ); ?> €</span>
								</div>
								<div class="balance-item">
									<span class="balance-label">Total encaissé:</span>
									<span class="balance-value paid-value"><?php echo esc_html( number_format( $total_paid, 2, ',', ' ' ) ); ?> €</span>
								</div>
								<div class="balance-item balance-due-item">
									<span class="balance-label">Reste à payer:</span>
									<span class="balance-value <?php echo $balance_due > 0 ? 'pending-value' : 'paid-value'; ?>">
										<?php echo esc_html( number_format( $balance_due, 2, ',', ' ' ) ); ?> €
									</span>
								</div>
							</div>

							<!-- Add Payment Button -->
							<button type="button" class="button button-secondary" id="add-payment-btn">
								<span class="dashicons dashicons-plus-alt"></span>
								Ajouter un paiement
							</button>

							<!-- Payments List -->
							<div id="payments-list" class="payments-list">
								<?php if ( ! empty( $payments ) ) : ?>
									<?php foreach ( $payments as $index => $payment ) : ?>
										<div class="payment-item" data-index="<?php echo esc_attr( $index ); ?>">
											<input type="hidden" name="payments[<?php echo esc_attr( $index ); ?>][date]" value="<?php echo esc_attr( $payment['date'] ); ?>">
											<input type="hidden" name="payments[<?php echo esc_attr( $index ); ?>][amount]" value="<?php echo esc_attr( $payment['amount'] ); ?>">
											<input type="hidden" name="payments[<?php echo esc_attr( $index ); ?>][method]" value="<?php echo esc_attr( $payment['method'] ); ?>">
											<input type="hidden" name="payments[<?php echo esc_attr( $index ); ?>][reference]" value="<?php echo esc_attr( $payment['reference'] ?? '' ); ?>">
											<input type="hidden" name="payments[<?php echo esc_attr( $index ); ?>][notes]" value="<?php echo esc_attr( $payment['notes'] ?? '' ); ?>">

											<div class="payment-item-content">
												<div class="payment-date">
													<span class="dashicons dashicons-calendar-alt"></span>
													<?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $payment['date'] ) ) ); ?>
												</div>
												<div class="payment-amount">
													<?php echo esc_html( number_format( floatval( $payment['amount'] ), 2, ',', ' ' ) ); ?> €
												</div>
												<div class="payment-method">
													<?php echo esc_html( $payment_methods[ $payment['method'] ] ?? $payment['method'] ); ?>
												</div>
												<?php if ( ! empty( $payment['reference'] ) ) : ?>
													<div class="payment-reference">
														Réf: <?php echo esc_html( $payment['reference'] ); ?>
													</div>
												<?php endif; ?>
											</div>
											<div class="payment-item-actions">
												<button type="button" class="button button-small edit-payment-btn" data-index="<?php echo esc_attr( $index ); ?>">
													<span class="dashicons dashicons-edit"></span>
												</button>
												<button type="button" class="button button-small button-link-delete delete-payment-btn" data-index="<?php echo esc_attr( $index ); ?>">
													<span class="dashicons dashicons-trash"></span>
												</button>
											</div>
										</div>
									<?php endforeach; ?>
								<?php else : ?>
									<p class="no-payments">Aucun paiement enregistré</p>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<!-- Sidebar -->
				<div class="formapress-editor-sidebar">
					<!-- Save Actions -->
					<div class="editor-section">
						<h3>Actions</h3>
						<div class="editor-actions">
							<button type="submit" name="save_invoice" class="button button-primary button-large" style="width: 100%; margin-bottom: 10px;">
								<span class="dashicons dashicons-yes"></span>
								<?php echo $is_new ? esc_html__( 'Créer la facture', 'formapress-crm' ) : esc_html__( 'Enregistrer', 'formapress-crm' ); ?>
							</button>

							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=crm_invoice' ) ); ?>" class="button button-secondary" style="width: 100%; text-align: center;">
								<span class="dashicons dashicons-arrow-left-alt"></span>
								Retour à la liste
							</a>

							<?php if ( ! $is_new ) : ?>
								<hr>
								<a href="<?php echo esc_url( get_delete_post_link( $invoice_id ) ); ?>" class="button button-link-delete" style="width: 100%; text-align: center;" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette facture ?');">
									<span class="dashicons dashicons-trash"></span>
									Supprimer
								</a>
							<?php endif; ?>
						</div>
					</div>

					<!-- Training Session Link -->
					<div class="editor-section">
						<h3>Session de formation</h3>
						<div class="form-group">
							<label for="zqpm_id">Session associée</label>
							<select id="zqpm_id" name="zqpm_id" class="widefat select2-ajax" data-ajax-action="formapress_search_zqpm">
								<?php if ( $zqpm_id && $zqpm_name ) : ?>
									<option value="<?php echo esc_attr( $zqpm_id ); ?>" selected>
										<?php echo esc_html( $zqpm_name ); ?>
									</option>
								<?php else : ?>
									<option value="">— Aucune —</option>
								<?php endif; ?>
							</select>
							<p class="description">Lier à un suivi de session ZQPM</p>
						</div>
					</div>

					<?php if ( ! $is_new ) : ?>
						<!-- Invoice Info -->
						<div class="editor-section">
							<h3>Informations</h3>
							<div class="invoice-meta">
								<?php if ( $invoice_number ) : ?>
									<p><strong>N° de facture:</strong><br><?php echo esc_html( $invoice_number ); ?></p>
								<?php endif; ?>
								<p><strong>Créée le:</strong><br><?php echo esc_html( get_the_date( 'd/m/Y', $invoice_id ) ); ?></p>
								<p><strong>Modifiée le:</strong><br><?php echo esc_html( get_the_modified_date( 'd/m/Y H:i', $invoice_id ) ); ?></p>
							</div>
						</div>

						<!-- PDF Status -->
						<?php
						if ( function_exists( 'formapress_crm_invoice_pdf_status_display' ) ) {
							formapress_crm_invoice_pdf_status_display( $invoice_id );
						}
						?>

						<!-- Email History & Send -->
						<?php
						if ( function_exists( 'formapress_crm_invoice_email_history_display' ) ) {
							formapress_crm_invoice_email_history_display( $invoice_id );
						}
						?>
					<?php endif; ?>
				</div>
			</div>
		</form>

		<!-- Payment Modal -->
		<div id="payment-modal" class="payment-modal" style="display: none;">
			<div class="payment-modal-content">
				<div class="payment-modal-header">
					<h3 id="payment-modal-title">Ajouter un paiement</h3>
					<button type="button" class="payment-modal-close">&times;</button>
				</div>
				<div class="payment-modal-body">
					<input type="hidden" id="payment-edit-index" value="">

					<div class="form-group">
						<label for="payment-date">Date de paiement <span class="required">*</span></label>
						<input type="date" id="payment-date" class="widefat" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" required>
					</div>

					<div class="form-group">
						<label for="payment-amount">Montant (€) <span class="required">*</span></label>
						<input type="number" step="0.01" id="payment-amount" class="widefat" placeholder="0.00" required>
					</div>

					<div class="form-group">
						<label for="payment-method">Méthode de paiement <span class="required">*</span></label>
						<select id="payment-method-select" class="widefat" required>
							<option value="">— Sélectionner —</option>
							<?php foreach ( $payment_methods as $method_key => $method_label ) : ?>
								<option value="<?php echo esc_attr( $method_key ); ?>"><?php echo esc_html( $method_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="form-group">
						<label for="payment-reference">Référence de transaction</label>
						<input type="text" id="payment-reference" class="widefat" placeholder="Ex: N° de chèque, référence virement...">
					</div>

					<div class="form-group">
						<label for="payment-notes">Notes</label>
						<textarea id="payment-notes" class="widefat" rows="3" placeholder="Notes additionnelles..."></textarea>
					</div>
				</div>
				<div class="payment-modal-footer">
					<button type="button" class="button button-secondary payment-modal-close">Annuler</button>
					<button type="button" class="button button-primary" id="save-payment-btn">Enregistrer le paiement</button>
				</div>
			</div>
		</div>
	</div>

	<script>
	jQuery(document).ready(function($) {
		// Show/hide payment details based on payment status
		$('#payment_status').on('change', function() {
			var status = $(this).val();
			if (status === 'paid' || status === 'partial') {
				$('.payment-details').slideDown();
			} else {
				$('.payment-details').slideUp();
			}
		});

		// Show/hide OPCO fields based on funding source
		$('#funding_source').on('change', function() {
			var source = $(this).val();
			if (source === 'opco' || source === 'mixed') {
				$('.opco-fields').slideDown();
			} else {
				$('.opco-fields').slideUp();
			}
		});
	});
	</script>
	<?php
}

/**
 * Handle invoice save via form submission.
 */
function formapress_crm_handle_invoice_save() {
	// Check if this is an invoice save request.
	if ( ! isset( $_POST['action'] ) || 'formapress_save_invoice' !== $_POST['action'] ) {
		return;
	}

	// Verify nonce.
	if ( ! isset( $_POST['formapress_invoice_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['formapress_invoice_nonce'] ) ), 'formapress_save_invoice' ) ) {
		wp_die( esc_html__( 'Nonce verification failed', 'formapress-crm' ) );
	}

	// Check permissions.
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to save invoices', 'formapress-crm' ) );
	}

	// Get invoice ID.
	$invoice_id = isset( $_POST['invoice_id'] ) ? absint( $_POST['invoice_id'] ) : 0;
	$is_new     = 0 === $invoice_id;

	// Validate required fields.
	if ( empty( $_POST['invoice_title'] ) || empty( $_POST['amount'] ) || empty( $_POST['invoice_date'] ) ) {
		wp_die( esc_html__( 'Required fields are missing', 'formapress-crm' ) );
	}

	// Prepare post data.
	$post_data = array(
		'post_type'    => 'crm_invoice',
		'post_title'   => sanitize_text_field( wp_unslash( $_POST['invoice_title'] ) ),
		'post_content' => isset( $_POST['invoice_notes'] ) ? wp_kses_post( wp_unslash( $_POST['invoice_notes'] ) ) : '',
		'post_status'  => 'publish',
	);

	if ( ! $is_new ) {
		$post_data['ID'] = $invoice_id;
		$saved_id        = wp_update_post( $post_data );
	} else {
		$saved_id = wp_insert_post( $post_data );
	}

	if ( is_wp_error( $saved_id ) ) {
		wp_die( esc_html( $saved_id->get_error_message() ) );
	}

	// Generate invoice number if new and not set.
	if ( $is_new ) {
		$invoice_number = formapress_crm_generate_invoice_number();
		update_post_meta( $saved_id, '_crm_invoice_number', $invoice_number );
	}

	// Save meta fields.
	$meta_fields = array(
		'_crm_invoice_amount'           => floatval( $_POST['amount'] ),
		'_crm_invoice_payment_status'   => sanitize_text_field( wp_unslash( $_POST['payment_status'] ) ),
		'_crm_invoice_funding_source'   => isset( $_POST['funding_source'] ) ? sanitize_text_field( wp_unslash( $_POST['funding_source'] ) ) : '',
		'_crm_invoice_date'             => sanitize_text_field( wp_unslash( $_POST['invoice_date'] ) ),
		'_crm_invoice_due_date'         => isset( $_POST['due_date'] ) ? sanitize_text_field( wp_unslash( $_POST['due_date'] ) ) : '',
		'_crm_invoice_payment_date'     => isset( $_POST['payment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_date'] ) ) : '',
		'_crm_invoice_payment_method'   => isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : '',
		'_crm_invoice_opco_name'        => isset( $_POST['opco_name'] ) ? sanitize_text_field( wp_unslash( $_POST['opco_name'] ) ) : '',
		'_crm_invoice_agreement_number' => isset( $_POST['agreement_number'] ) ? sanitize_text_field( wp_unslash( $_POST['agreement_number'] ) ) : '',
		'_crm_invoice_company_id'       => isset( $_POST['company_id'] ) ? absint( $_POST['company_id'] ) : 0,
		'_crm_invoice_person_id'        => isset( $_POST['person_id'] ) ? absint( $_POST['person_id'] ) : 0,
		'_crm_invoice_zqpm_id'          => isset( $_POST['zqpm_id'] ) ? absint( $_POST['zqpm_id'] ) : 0,
	);

	foreach ( $meta_fields as $meta_key => $meta_value ) {
		if ( empty( $meta_value ) && '_crm_invoice_amount' !== $meta_key ) {
			delete_post_meta( $saved_id, $meta_key );
		} else {
			update_post_meta( $saved_id, $meta_key, $meta_value );
		}
	}

	// Save payments array.
	if ( isset( $_POST['invoice_payments'] ) && is_array( $_POST['invoice_payments'] ) ) {
		$payments = array();

		foreach ( $_POST['invoice_payments'] as $payment_json ) {
			$payment = json_decode( wp_unslash( $payment_json ), true );

			if ( $payment && is_array( $payment ) ) {
				$payments[] = array(
					'date'      => sanitize_text_field( $payment['date'] ?? '' ),
					'amount'    => floatval( $payment['amount'] ?? 0 ),
					'method'    => sanitize_text_field( $payment['method'] ?? '' ),
					'reference' => sanitize_text_field( $payment['reference'] ?? '' ),
					'notes'     => sanitize_textarea_field( $payment['notes'] ?? '' ),
				);
			}
		}

		update_post_meta( $saved_id, '_crm_invoice_payments', $payments );

		// Auto-update payment status based on balance.
		$amount     = floatval( $_POST['amount'] );
		$total_paid = 0;
		foreach ( $payments as $payment ) {
			$total_paid += floatval( $payment['amount'] );
		}
		$balance_due = $amount - $total_paid;

		if ( $balance_due <= 0 ) {
			$auto_status = 'paid';
		} elseif ( $total_paid > 0 ) {
			$auto_status = 'partial';
		} else {
			$auto_status = 'pending';
		}

		update_post_meta( $saved_id, '_crm_invoice_payment_status', $auto_status );
	} else {
		delete_post_meta( $saved_id, '_crm_invoice_payments' );
	}

	// Redirect to the list or back to editor.
	$redirect_url = add_query_arg(
		array(
			'page'    => 'formapress-crm-edit-invoice',
			'id'      => $saved_id,
			'message' => 'saved',
		),
		admin_url( 'admin.php' )
	);

	wp_safe_redirect( $redirect_url );
	exit;
}
add_action( 'admin_init', 'formapress_crm_handle_invoice_save' );

/**
 * Generate a unique invoice number.
 *
 * @return string Invoice number in format FAC-YYYY-NNNN.
 */
function formapress_crm_generate_invoice_number() {
	$year          = gmdate( 'Y' );
	$counter_key   = 'crm_invoice_counter_' . $year;
	$current_count = (int) get_option( $counter_key, 0 );
	$new_count     = $current_count + 1;

	update_option( $counter_key, $new_count );

	return sprintf( 'FAC-%s-%04d', $year, $new_count );
}

/**
 * AJAX handler for searching ZQPM sessions.
 */
function formapress_crm_ajax_search_zqpm() {
	// Verify nonce.
	check_ajax_referer( 'formapress_invoice_editor', 'nonce' );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
	$search_term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

	$args = array(
		'post_type'      => 'zqpm',
		'posts_per_page' => 20,
		'post_status'    => 'publish',
		's'              => $search_term,
	);

	$posts   = get_posts( $args );
	$results = array();

	foreach ( $posts as $post ) {
		$title = $post->post_title;
		if ( function_exists( 'zqpm_construct_new_title' ) ) {
			$title = zqpm_construct_new_title( $post->post_title, $post->ID );
		}
		$results[] = array(
			'id'   => $post->ID,
			'text' => $title,
		);
	}

	wp_send_json( array( 'results' => $results ) );
}
add_action( 'wp_ajax_formapress_search_zqpm', 'formapress_crm_ajax_search_zqpm' );

/**
 * AJAX handler for searching companies.
 */
function formapress_crm_ajax_search_companies() {
	// Verify nonce.
	check_ajax_referer( 'formapress_invoice_editor', 'nonce' );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
	$search_term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

	$args = array(
		'post_type'      => 'crm_company',
		'posts_per_page' => 20,
		'post_status'    => 'publish',
		's'              => $search_term,
	);

	$posts   = get_posts( $args );
	$results = array();

	foreach ( $posts as $post ) {
		$results[] = array(
			'id'   => $post->ID,
			'text' => $post->post_title,
		);
	}

	wp_send_json( array( 'results' => $results ) );
}
add_action( 'wp_ajax_formapress_search_companies', 'formapress_crm_ajax_search_companies' );

/**
 * AJAX handler for searching persons (with optional company filter).
 */
function formapress_crm_ajax_search_persons() {
	// Verify nonce.
	check_ajax_referer( 'formapress_invoice_editor', 'nonce' );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
	$search_term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
	$company_id  = isset( $_GET['company_id'] ) ? absint( $_GET['company_id'] ) : 0;

	$args = array(
		'post_type'      => 'crm_person',
		'posts_per_page' => 50,
		'post_status'    => 'publish',
		's'              => $search_term,
		'tax_query'      => array(
			array(
				'taxonomy' => 'person_type',
				'field'    => 'slug',
				'terms'    => 'company_contact',
			),
		),
	);

	// Filter by company if specified.
	if ( $company_id ) {
		$args['meta_query'] = array(
			array(
				'key'     => '_crm_company_id',
				'value'   => $company_id,
				'compare' => '=',
			),
		);
	}

	$posts   = get_posts( $args );
	$results = array();

	foreach ( $posts as $post ) {
		$results[] = array(
			'id'   => $post->ID,
			'text' => $post->post_title,
		);
	}

	wp_send_json( array( 'results' => $results ) );
}
add_action( 'wp_ajax_formapress_search_persons', 'formapress_crm_ajax_search_persons' );

/**
 * AJAX handler to get contacts for a specific company.
 */
function formapress_crm_ajax_get_company_contacts() {
	// Verify nonce.
	check_ajax_referer( 'formapress_invoice_editor', 'nonce' );

	$company_id = isset( $_POST['company_id'] ) ? absint( $_POST['company_id'] ) : 0;

	if ( ! $company_id ) {
		wp_send_json_error( array( 'message' => 'Invalid company ID' ) );
	}

	$args = array(
		'post_type'      => 'crm_person',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'tax_query'      => array(
			array(
				'taxonomy' => 'person_type',
				'field'    => 'slug',
				'terms'    => 'company_contact',
			),
		),
		'meta_query'     => array(
			array(
				'key'     => '_crm_company_id',
				'value'   => $company_id,
				'compare' => '=',
			),
		),
	);

	$contacts = get_posts( $args );
	$results  = array();

	foreach ( $contacts as $contact ) {
		$results[] = array(
			'id'   => $contact->ID,
			'text' => $contact->post_title,
		);
	}

	wp_send_json_success( array( 'contacts' => $results ) );
}
add_action( 'wp_ajax_formapress_crm_get_company_contacts', 'formapress_crm_ajax_get_company_contacts' );
