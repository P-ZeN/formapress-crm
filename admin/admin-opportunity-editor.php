<?php
/**
 * Custom Opportunity Editor Page
 *
 * Provides a modern, clean interface for editing opportunities
 * with better UX than the default WordPress editor.
 *
 * @package FormapressCRM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Redirect opportunity edit screen to custom editor.
 */
function formapress_crm_redirect_opportunity_editor() {
	global $pagenow, $typenow;

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect only, no data modification.
	// Check if we're on the opportunity edit screen.
	if ( 'post.php' === $pagenow && isset( $_GET['action'] ) && 'edit' === $_GET['action'] && isset( $_GET['post'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = absint( $_GET['post'] );
		if ( $post_id ) {
			$post_type = get_post_type( $post_id );
			if ( 'crm_opportunity' === $post_type ) {
				wp_safe_redirect( admin_url( 'admin.php?page=formapress-crm-edit-opportunity&id=' . $post_id ) );
				exit;
			}
		}
	}

	// Also check via $typenow for cases where post type is set but post ID might not be loaded yet.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect only, no data modification.
	if ( 'post.php' === $pagenow && 'crm_opportunity' === $typenow && isset( $_GET['action'] ) && 'edit' === $_GET['action'] ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( $post_id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=formapress-crm-edit-opportunity&id=' . $post_id ) );
			exit;
		}
	}

	// Redirect new opportunity screen.
	if ( 'post-new.php' === $pagenow && 'crm_opportunity' === $typenow ) {
		wp_safe_redirect( admin_url( 'admin.php?page=formapress-crm-edit-opportunity' ) );
		exit;
	}
}
add_action( 'admin_init', 'formapress_crm_redirect_opportunity_editor' );

/**
 * Register custom opportunity editor page.
 */
function formapress_crm_register_opportunity_editor() {
	add_submenu_page(
		null, // Hidden from menu (accessed via redirect).
		'Modifier l\'opportunité',
		'Modifier l\'opportunité',
		'edit_posts',
		'formapress-crm-edit-opportunity',
		'formapress_crm_render_opportunity_editor'
	);
}
add_action( 'admin_menu', 'formapress_crm_register_opportunity_editor' );

/**
 * Enqueue scripts for opportunity editor.
 */
function formapress_crm_enqueue_opportunity_editor_scripts( $hook ) {
	if ( 'admin_page_formapress-crm-edit-opportunity' !== $hook ) {
		return;
	}

	// Enqueue WordPress media library.
	wp_enqueue_media();

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
		'formapress-opportunity-editor',
		plugins_url( '../assets/js/opportunity-editor.js', __FILE__ ),
		array( 'jquery', 'select2' ),
		'1.0.0',
		true
	);

	// Enqueue communication modal script.
	wp_enqueue_script(
		'formapress-communication-modal',
		plugins_url( '../assets/js/communication-modal.js', __FILE__ ),
		array( 'jquery' ),
		'1.0.0',
		true
	);

	// Enqueue custom editor styles.
	wp_enqueue_style(
		'formapress-opportunity-editor',
		plugins_url( '../assets/css/opportunity-editor-styles.css', __FILE__ ),
		array(),
		'1.0.0'
	);

	// Enqueue communication modal styles.
	wp_enqueue_style(
		'formapress-communication-modal',
		plugins_url( '../assets/css/communication-modal-styles.css', __FILE__ ),
		array(),
		'1.0.0'
	);

	// Get stages for localization.
	if ( function_exists( 'formapress_crm_get_opportunity_stages' ) ) {
		$stages = formapress_crm_get_opportunity_stages();
	} else {
		$stages = array(
			'new'                  => 'Nouveau prospect',
			'qualified'            => 'Qualifié',
			'proposal_in_progress' => 'Proposition en préparation',
			'proposal'             => 'Proposition envoyée',
			'negotiation'          => 'En négociation',
			'won'                  => 'Gagné',
			'lost'                 => 'Perdu',
		);
	}

	// Localize script with data.
	wp_localize_script(
		'formapress-opportunity-editor',
		'formapressOpportunityEditor',
		array(
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'formapress_opportunity_editor' ),
			'post_id'    => isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0,
			'stages'     => $stages,
			'ajax_delay' => 300, // Debounce delay for search.
		)
	);

	// Localize communication modal script.
	wp_localize_script(
		'formapress-communication-modal',
		'formapressCrmModal',
		array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'sendNonce'    => wp_create_nonce( 'formapress_crm_send_communication' ),
			'previewNonce' => wp_create_nonce( 'formapress_crm_preview_template' ),
			'i18n'         => array(
				'sending'     => __( 'Envoi en cours...', 'formapress-crm' ),
				'success'     => __( 'Email envoyé avec succès !', 'formapress-crm' ),
				'error'       => __( 'Erreur lors de l\'envoi.', 'formapress-crm' ),
				'confirmSend' => __( 'Êtes-vous sûr de vouloir envoyer cet email ?', 'formapress-crm' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'formapress_crm_enqueue_opportunity_editor_scripts' );



/**
 * Render the custom opportunity editor page.
 */
function formapress_crm_render_opportunity_editor() {
	$post_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	$is_new  = empty( $post_id );

	// If editing, verify post exists and is an opportunity.
	if ( ! $is_new ) {
		$post = get_post( $post_id );
		if ( ! $post || 'crm_opportunity' !== $post->post_type ) {
			wp_die( __( 'Opportunité non trouvée.', 'formapress-crm' ) );
		}
	}

	// Get current values.
	$title      = $is_new ? '' : get_the_title( $post_id );
	$content    = $is_new ? '' : get_post_field( 'post_content', $post_id );
	$stage      = $is_new ? 'new' : get_post_meta( $post_id, '_crm_opportunity_stage', true );
	$value      = $is_new ? '' : get_post_meta( $post_id, '_crm_opportunity_value', true );
	$close_date = $is_new ? '' : get_post_meta( $post_id, '_crm_opportunity_close_date', true );
	$person_id  = $is_new ? '' : get_post_meta( $post_id, '_crm_associated_person_id', true );
	$company_id = $is_new ? '' : get_post_meta( $post_id, '_crm_associated_company_id', true );
	$zqpm_id    = $is_new ? '' : get_post_meta( $post_id, '_crm_opportunity_zqpm_id', true );

	// Get person and company details if set.
	$person_name  = '';
	$company_name = '';
	if ( $person_id ) {
		$person      = get_post( $person_id );
		$person_name = $person ? $person->post_title : '';
	}
	if ( $company_id ) {
		$company      = get_post( $company_id );
		$company_name = $company ? $company->post_title : '';
	}

	// Get custom attributes schema.
	$attributes_schema = get_option( 'crm_opportunity_attributes', array() );

	// Get stages.
	if ( function_exists( 'formapress_crm_get_opportunity_stages' ) ) {
		$stages = formapress_crm_get_opportunity_stages();
	} else {
		$stages = array(
			'new'                  => 'Nouveau prospect',
			'qualified'            => 'Qualifié',
			'proposal_in_progress' => 'Proposition en préparation',
			'proposal'             => 'Proposition envoyée',
			'negotiation'          => 'En négociation',
			'won'                  => 'Gagné',
			'lost'                 => 'Perdu',
		);
	}

	// Get stage labels with CSS variable mappings for visualization.
	$stage_config = array(
		'new'                  => array(
			'label'   => $stages['new'],
			'css_var' => '--zform_color_bleu2',
			'icon'    => 'dashicons-star-filled',
		),
		'qualified'            => array(
			'label'   => $stages['qualified'],
			'css_var' => '--zform_color_bleu',
			'icon'    => 'dashicons-yes-alt',
		),
		'proposal_in_progress' => array(
			'label'   => $stages['proposal_in_progress'],
			'css_var' => '--zform_color_orange2',
			'icon'    => 'dashicons-media-document',
		),
		'proposal'             => array(
			'label'   => $stages['proposal'],
			'css_var' => '--zform_color_orange',
			'icon'    => 'dashicons-media-document',
		),
		'negotiation'          => array(
			'label'   => $stages['negotiation'],
			'css_var' => '--zform_color_orange',
			'icon'    => 'dashicons-businessman',
		),
		'won'                  => array(
			'label'   => $stages['won'],
			'css_var' => '--zform_color_turquoise',
			'icon'    => 'dashicons-awards',
		),
		'lost'                 => array(
			'label'   => $stages['lost'],
			'css_var' => '--zform_gray',
			'icon'    => 'dashicons-dismiss',
		),
	);

	$current_stage_config = $stage_config[ $stage ] ?? $stage_config['new'];

	?>
	<div class="wrap formapress-opportunity-editor">
		<?php if ( ! $is_new ) : ?>
			<!-- Prominent Header Banner (ZQPM-style) -->
			<div class="opportunity-header-banner" data-stage="<?php echo esc_attr( $stage ); ?>">
				<div class="banner-content">
					<div class="banner-title-section">
						<span class="banner-icon dashicons <?php echo esc_attr( $current_stage_config['icon'] ); ?>"></span>
						<h1 class="banner-title"><?php echo esc_html( $title ?: __( 'Opportunité sans titre', 'formapress-crm' ) ); ?></h1>
					</div>
					<div class="banner-meta">
						<?php if ( $value ) : ?>
							<div class="banner-meta-item">
								<span class="dashicons dashicons-money-alt"></span>
								<strong><?php echo esc_html( number_format( (float) $value, 2, ',', ' ' ) . ' €' ); ?></strong>
							</div>
						<?php endif; ?>
						<?php if ( $close_date ) : ?>
							<div class="banner-meta-item">
								<span class="dashicons dashicons-calendar-alt"></span>
								<?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $close_date ) ) ); ?>
							</div>
						<?php endif; ?>
						<?php if ( $person_name ) : ?>
							<div class="banner-meta-item">
								<span class="dashicons dashicons-admin-users"></span>
								<?php echo esc_html( $person_name ); ?>
							</div>
						<?php endif; ?>
						<?php if ( $company_name ) : ?>
							<div class="banner-meta-item">
								<span class="dashicons dashicons-building"></span>
								<?php echo esc_html( $company_name ); ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
				<div class="banner-stage-indicator">
					<span class="stage-badge"><?php echo esc_html( $current_stage_config['label'] ); ?></span>
				</div>
			</div>
		<?php else : ?>
			<h1><?php esc_html_e( 'Nouvelle opportunité', 'formapress-crm' ); ?></h1>
		<?php endif; ?>

		<?php
		// Show success message if updated.
		if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Opportunité enregistrée avec succès.', 'formapress-crm' ); ?></p>
			</div>
			<?php
		}
		?>

		<form id="opportunity-editor-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="formapress_save_opportunity">
			<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
			<?php wp_nonce_field( 'formapress_save_opportunity', 'formapress_opportunity_nonce' ); ?>

			<?php if ( ! $is_new ) : ?>
				<!-- Stage Progress Sidebar (ZQPM-style) -->
				<div class="opportunity-stage-progress">
					<h3><?php esc_html_e( 'Pipeline', 'formapress-crm' ); ?></h3>
					<ul class="stage-steps">
						<?php
						$step_number = 1;
						foreach ( $stage_config as $stage_key => $stage_info ) :
							$is_current  = $stage_key === $stage;
							$is_past     = false;
							$stage_order = array( 'new', 'qualified', 'proposal_in_progress', 'proposal', 'negotiation', 'won', 'lost' );
							$current_idx = array_search( $stage, $stage_order, true );
							$stage_idx   = array_search( $stage_key, $stage_order, true );

							// Mark as past if before current stage (but not for won/lost)
							if ( $current_idx !== false && $stage_idx !== false &&
								$stage_idx < $current_idx &&
								! in_array( $stage, array( 'won', 'lost' ), true ) ) {
								$is_past = true;
							}

							$step_class = '';
							if ( $is_current ) {
								$step_class = 'current';
							} elseif ( $is_past ) {
								$step_class = 'past';
							}
							?>
							<li class="stage-step <?php echo esc_attr( $step_class ); ?>" data-stage="<?php echo esc_attr( $stage_key ); ?>">
								<span class="step-number"><?php echo esc_html( $step_number ); ?></span>
								<span class="step-label"><?php echo esc_html( $stage_info['label'] ); ?></span>
							</li>
							<?php
							++$step_number;
						endforeach;
						?>
					</ul>

					<!-- Probability Slider -->
					<?php
					$probability = $is_new ? 50 : (int) get_post_meta( $post_id, 'crm_opportunity_attributes_probabilite', true );
					if ( empty( $probability ) && 0 !== $probability ) {
						$probability = 50;
					}
					?>
					<div class="probability-panel">
						<h3><?php esc_html_e( 'Probabilité de réussite', 'formapress-crm' ); ?></h3>
						<div class="probability-slider-container">
							<input
								type="range"
								id="opportunity-probability"
								name="opportunity_attr_probabilite"
								class="probability-slider"
								min="0"
								max="100"
								step="5"
								value="<?php echo esc_attr( $probability ); ?>"
							>
							<div class="probability-value">
								<span id="probability-display"><?php echo esc_html( $probability ); ?>%</span>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<div class="opportunity-editor-container">
				<div class="opportunity-editor-main">
					<!-- Title Section -->
					<div class="editor-section">
						<label for="opportunity-title" class="editor-label required">
							<?php esc_html_e( 'Titre de l\'opportunité', 'formapress-crm' ); ?>
						</label>
						<input
							type="text"
							id="opportunity-title"
							name="opportunity_title"
							class="editor-input-large"
							value="<?php echo esc_attr( $title ); ?>"
							placeholder="<?php esc_attr_e( 'Ex: Formation WordPress pour 10 personnes', 'formapress-crm' ); ?>"
							required
						>
					</div>

					<!-- Formation Section -->
					<div class="editor-section">
						<label for="associated-formation" class="editor-label">
							<?php esc_html_e( 'Formation concernée', 'formapress-crm' ); ?>
						</label>
						<?php
						$formation_id = $is_new ? 0 : (int) get_post_meta( $post_id, '_crm_associated_formation_id', true );
						$formation    = $formation_id ? get_post( $formation_id ) : null;
						?>
						<select name="associated_formation_id" id="associated-formation" class="crm-entity-select" data-entity-type="formation">
							<option value=""><?php esc_html_e( '-- Sélectionner une formation --', 'formapress-crm' ); ?></option>
							<?php if ( $formation && 'zform_formation' === $formation->post_type ) : ?>
								<option value="<?php echo esc_attr( $formation->ID ); ?>" selected>
									<?php echo esc_html( $formation->post_title ); ?>
								</option>
							<?php endif; ?>
						</select>
						<p class="editor-help-text" style="margin-top: 5px; font-size: 12px; color: #666;">
							<?php esc_html_e( 'Quelle formation est associée à cette opportunité de vente ?', 'formapress-crm' ); ?>
						</p>
					</div>

					<!-- Description Section -->
					<div class="editor-section">
						<label for="opportunity-content" class="editor-label">
							<?php esc_html_e( 'Description', 'formapress-crm' ); ?>
						</label>
						<textarea
							id="opportunity-content"
							name="opportunity_content"
							class="editor-textarea"
							rows="8"
							placeholder="<?php esc_attr_e( 'Détails sur l\'opportunité, besoins du client, notes...', 'formapress-crm' ); ?>"
						><?php echo esc_textarea( $content ); ?></textarea>
					</div>



					<!-- Quick Actions (Stage-Based Templates) -->
					<?php if ( ! $is_new ) : ?>
						<div class="editor-section">
							<h3 class="editor-section-title"><?php esc_html_e( '⚡ Actions rapides', 'formapress-crm' ); ?></h3>
							<?php formapress_crm_render_quick_actions( $post_id, $stage ); ?>
						</div>
					<?php endif; ?>

					<!-- Activity Timeline -->
					<?php if ( ! $is_new ) : ?>
						<div class="editor-section">
							<h3 class="editor-section-title"><?php esc_html_e( '📋 Historique des activités', 'formapress-crm' ); ?></h3>
							<?php formapress_crm_render_activity_timeline( $post_id ); ?>
						</div>
					<?php endif; ?>
				</div>

				<!-- Sidebar -->
				<div class="opportunity-editor-sidebar">
					<!-- Save Actions -->
					<div class="editor-panel">
						<div class="editor-panel-actions">
							<button type="submit" class="button button-primary button-large" id="save-opportunity">
								<?php echo $is_new ? __( 'Créer l\'opportunité', 'formapress-crm' ) : __( 'Enregistrer', 'formapress-crm' ); ?>
							</button>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=crm_opportunity' ) ); ?>" class="button button-secondary button-large">
								<?php esc_html_e( 'Annuler', 'formapress-crm' ); ?>
							</a>
						</div>
					</div>

					<!-- Stage (hidden - controlled by visual pipeline) -->
					<input type="hidden" name="opportunity_stage" id="opportunity-stage" value="<?php echo esc_attr( $stage ); ?>" required>

					<!-- Financial Details -->
					<div class="editor-panel">
						<h3 class="editor-panel-title"><?php esc_html_e( 'Détails financiers', 'formapress-crm' ); ?></h3>
						<div class="editor-field">
							<label for="opportunity-value" class="editor-label-small">
								<?php esc_html_e( 'Montant estimé (€)', 'formapress-crm' ); ?>
							</label>
							<input
								type="number"
								step="0.01"
								id="opportunity-value"
								name="opportunity_value"
								class="editor-input"
								value="<?php echo esc_attr( $value ); ?>"
								placeholder="0.00"
							>
						</div>
						<div class="editor-field">
							<label for="opportunity-close-date" class="editor-label-small">
								<?php esc_html_e( 'Date de clôture prévue', 'formapress-crm' ); ?>
							</label>
							<input
								type="date"
								id="opportunity-close-date"
								name="opportunity_close_date"
								class="editor-input"
								value="<?php echo esc_attr( $close_date ); ?>"
							>
						</div>
					</div>

					<!-- Associated Entities -->
					<div class="editor-panel">
						<h3 class="editor-panel-title"><?php esc_html_e( 'Entités associées', 'formapress-crm' ); ?></h3>
						<div class="editor-field">
							<label for="associated-person" class="editor-label-small">
								<?php esc_html_e( 'Personne', 'formapress-crm' ); ?>
							</label>
							<select name="associated_person_id" id="associated-person" class="crm-entity-select" data-entity-type="person">
								<?php if ( $person_id && $person_name ) : ?>
									<option value="<?php echo esc_attr( $person_id ); ?>" selected>
										<?php echo esc_html( $person_name ); ?>
									</option>
								<?php endif; ?>
							</select>
						</div>
						<div class="editor-field">
							<label for="associated-company" class="editor-label-small">
								<?php esc_html_e( 'Entreprise', 'formapress-crm' ); ?>
							</label>
							<select name="associated_company_id" id="associated-company" class="crm-entity-select" data-entity-type="company">
								<?php if ( $company_id && $company_name ) : ?>
									<option value="<?php echo esc_attr( $company_id ); ?>" selected>
										<?php echo esc_html( $company_name ); ?>
									</option>
								<?php endif; ?>
							</select>
						</div>
					</div>

					<!-- Suivi de session Link -->
					<?php if ( 'won' === $stage ) : ?>
						<div class="editor-panel">
							<h3 class="editor-panel-title"><?php esc_html_e( 'Suivi de session', 'formapress-crm' ); ?></h3>
							<?php if ( $zqpm_id && get_post_type( $zqpm_id ) === 'zqpm' ) : ?>
								<p class="editor-success-message">
									<strong><?php esc_html_e( '✓ Suivi de session créé', 'formapress-crm' ); ?></strong>
								</p>
								<a href="<?php echo esc_url( get_edit_post_link( $zqpm_id ) ); ?>" class="button button-secondary" style="width: 100%; margin-bottom: 10px;">
									<?php echo esc_html( get_the_title( $zqpm_id ) ?: 'Suivi de session #' . $zqpm_id ); ?>
								</a>
							<?php else : ?>
								<p class="editor-help-text">
									<?php esc_html_e( 'Créez un Suivi de session pour cette opportunité gagnée.', 'formapress-crm' ); ?>
								</p>
								<button
									type="button"
									class="button button-secondary open-zqpm-modal"
									style="width: 100%; margin-bottom: 10px;"
									data-opportunity-id="<?php echo esc_attr( $post_id ); ?>"
									data-formation-id="<?php echo esc_attr( $formation_id ); ?>"
									data-company-id="<?php echo esc_attr( $company_id ); ?>"
									data-person-id="<?php echo esc_attr( $person_id ); ?>"
								>
									<?php esc_html_e( '+ Créer un Suivi de session', 'formapress-crm' ); ?>
								</button>
							<?php endif; ?>
							<div class="editor-field">
								<label for="zqpm-id" class="editor-label-small">
									<?php esc_html_e( 'ID Suivi de session', 'formapress-crm' ); ?>
								</label>
								<input
									type="number"
									id="zqpm-id"
									name="opportunity_zqpm_id"
									class="editor-input"
									value="<?php echo esc_attr( $zqpm_id ); ?>"
									placeholder="<?php esc_attr_e( 'ID du Suivi de session', 'formapress-crm' ); ?>"
								>
								<small class="editor-help-text"><?php esc_html_e( 'Entrez l\'ID pour lier un Suivi de session existant.', 'formapress-crm' ); ?></small>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</form>

		<?php
		// Render communication modal if not a new opportunity.
		if ( ! $is_new ) {
			formapress_crm_render_communication_modal( $post_id );
		}
		?>

		<!-- ZQPM Creation Modal -->
		<div id="zqpm-creation-modal" class="zqpm-modal" style="display: none;">
			<div class="zqpm-modal-overlay"></div>
			<div class="zqpm-modal-content">
				<div class="zqpm-modal-header">
					<h2 class="zqpm-modal-title"><?php esc_html_e( 'Créer un Suivi de session', 'formapress-crm' ); ?></h2>
					<button type="button" class="zqpm-modal-close">&times;</button>
				</div>
				<div class="zqpm-modal-body">

					<!-- Step 1: Formation Selection -->
					<div class="zqpm-step" data-step="1">
						<div class="zqpm-step-header">
							<h3><?php esc_html_e( 'Étape 1 : Confirmer la formation', 'formapress-crm' ); ?></h3>
							<p class="description"><?php esc_html_e( 'Confirmez ou sélectionnez la formation concernée.', 'formapress-crm' ); ?></p>
						</div>
						<div class="zqpm-form-field">
							<label for="zqpm-formation-select">
								<?php esc_html_e( 'Formation :', 'formapress-crm' ); ?>
								<span class="required">*</span>
							</label>
							<select id="zqpm-formation-select" class="zqpm-select" required>
								<option value=""><?php esc_html_e( 'Chargement...', 'formapress-crm' ); ?></option>
							</select>
						</div>
					</div>

					<!-- Step 2: Session Selection -->
					<div class="zqpm-step" data-step="2" style="display: none;">
						<div class="zqpm-step-header">
							<h3><?php esc_html_e( 'Étape 2 : Sélectionner une session', 'formapress-crm' ); ?></h3>
							<p class="description"><?php esc_html_e( 'Choisissez une session existante ou créez-en une nouvelle.', 'formapress-crm' ); ?></p>
						</div>
						<div class="zqpm-form-field">
							<label for="zqpm-session-select">
								<?php esc_html_e( 'Session :', 'formapress-crm' ); ?>
								<span class="required">*</span>
							</label>
							<select id="zqpm-session-select" class="zqpm-select" required>
								<option value=""><?php esc_html_e( 'Chargement...', 'formapress-crm' ); ?></option>
							</select>
						</div>
						<div class="zqpm-create-session-option">
							<p>
								<strong><?php esc_html_e( 'Aucune session disponible ?', 'formapress-crm' ); ?></strong><br>
								<a href="#" class="zqpm-create-new-session" target="_blank">
									<?php esc_html_e( '+ Créer une nouvelle session', 'formapress-crm' ); ?>
								</a>
							</p>
						</div>
					</div>

					<!-- Step 3: Confirmation -->
					<div class="zqpm-step" data-step="3" style="display: none;">
						<div class="zqpm-step-header">
							<h3><?php esc_html_e( 'Étape 3 : Confirmer la création', 'formapress-crm' ); ?></h3>
							<p class="description"><?php esc_html_e( 'Vérifiez les informations avant de créer le Suivi de session.', 'formapress-crm' ); ?></p>
						</div>
						<div class="zqpm-summary">
							<dl>
								<dt><?php esc_html_e( 'Formation :', 'formapress-crm' ); ?></dt>
								<dd class="zqpm-summary-formation">—</dd>
								<dt><?php esc_html_e( 'Session :', 'formapress-crm' ); ?></dt>
								<dd class="zqpm-summary-session">—</dd>
							</dl>
						</div>
					</div>

					<div class="zqpm-error-message" style="display: none;"></div>
				</div>
				<div class="zqpm-modal-footer">
					<button type="button" class="button button-secondary zqpm-btn-back" style="display: none;">
						<?php esc_html_e( '← Retour', 'formapress-crm' ); ?>
					</button>
					<button type="button" class="button button-secondary cancel-zqpm-creation">
						<?php esc_html_e( 'Annuler', 'formapress-crm' ); ?>
					</button>
					<button type="button" class="button button-primary zqpm-btn-next">
						<?php esc_html_e( 'Suivant →', 'formapress-crm' ); ?>
					</button>
					<button type="button" class="button button-primary confirm-zqpm-creation" style="display: none;">
						<?php esc_html_e( 'Créer le Suivi de session', 'formapress-crm' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Nested Session Creation Modal -->
		<div id="zqpm-create-session-modal" class="zqpm-modal zqpm-nested-modal" style="display: none;">
			<div class="zqpm-modal-overlay"></div>
			<div class="zqpm-modal-content zqpm-nested-content">
				<div class="zqpm-modal-header">
					<h2 class="zqpm-modal-title"><?php esc_html_e( 'Créer une nouvelle session', 'formapress-crm' ); ?></h2>
					<button type="button" class="zqpm-modal-close">&times;</button>
				</div>
				<div class="zqpm-modal-body">
					<!-- Session form will be loaded here via AJAX -->
				</div>
			</div>
		</div>

	</div>
	<?php
}

/**
 * Render an attribute field.
 *
 * @param string $field_slug   Field slug.
 * @param array  $field_config Field configuration.
 * @param mixed  $value        Current value.
 */
function formapress_crm_render_attribute_field( $field_slug, $field_config, $value ) {
	$field_name  = 'opportunity_attr_' . $field_slug;
	$field_label = $field_config['label'] ?? ucfirst( str_replace( '-', ' ', $field_slug ) );
	$field_type  = $field_config['type'] ?? 'text';
	$required    = ! empty( $field_config['required'] );
	?>
	<div class="editor-field">
		<label for="<?php echo esc_attr( $field_slug ); ?>" class="editor-label-small <?php echo $required ? 'required' : ''; ?>">
			<?php echo esc_html( $field_label ); ?>
		</label>
		<?php
		switch ( $field_type ) {
			case 'textarea':
				?>
				<textarea
					id="<?php echo esc_attr( $field_slug ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					class="editor-input"
					rows="3"
					<?php echo $required ? 'required' : ''; ?>
				><?php echo esc_textarea( $value ); ?></textarea>
				<?php
				break;

			case 'select':
				?>
				<select
					id="<?php echo esc_attr( $field_slug ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					class="editor-select"
					<?php echo $required ? 'required' : ''; ?>
				>
					<option value=""><?php esc_html_e( '-- Sélectionner --', 'formapress-crm' ); ?></option>
					<?php
					// Parse options from string format: "value|label\nvalue2|label2".
					if ( ! empty( $field_config['options'] ) ) {
						if ( is_string( $field_config['options'] ) ) {
							// Parse string format.
							$options_raw = array_filter( explode( "\n", $field_config['options'] ) );
							foreach ( $options_raw as $option ) {
								$parts        = explode( '|', trim( $option ) );
								$option_value = isset( $parts[0] ) ? trim( $parts[0] ) : '';
								$option_label = isset( $parts[1] ) ? trim( $parts[1] ) : $option_value;
								?>
								<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
									<?php echo esc_html( $option_label ); ?>
								</option>
								<?php
							}
						} elseif ( is_array( $field_config['options'] ) ) {
							// Already parsed array format.
							foreach ( $field_config['options'] as $option_value => $option_label ) {
								?>
								<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
									<?php echo esc_html( $option_label ); ?>
								</option>
								<?php
							}
						}
					}
					?>
				</select>
				<?php
				break;

			case 'checkbox':
				?>
				<label class="editor-checkbox">
					<input
						type="checkbox"
						id="<?php echo esc_attr( $field_slug ); ?>"
						name="<?php echo esc_attr( $field_name ); ?>"
						value="1"
						<?php checked( $value, '1' ); ?>
						<?php echo $required ? 'required' : ''; ?>
					>
					<?php echo esc_html( $field_config['checkbox_label'] ?? $field_label ); ?>
				</label>
				<?php
				break;

			case 'number':
				?>
				<input
					type="number"
					step="<?php echo esc_attr( $field_config['step'] ?? '1' ); ?>"
					id="<?php echo esc_attr( $field_slug ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					class="editor-input"
					value="<?php echo esc_attr( $value ); ?>"
					<?php echo $required ? 'required' : ''; ?>
				>
				<?php
				break;

			case 'date':
				?>
				<input
					type="date"
					id="<?php echo esc_attr( $field_slug ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					class="editor-input"
					value="<?php echo esc_attr( $value ); ?>"
					<?php echo $required ? 'required' : ''; ?>
				>
				<?php
				break;

			case 'email':
				?>
				<input
					type="email"
					id="<?php echo esc_attr( $field_slug ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					class="editor-input"
					value="<?php echo esc_attr( $value ); ?>"
					<?php echo $required ? 'required' : ''; ?>
				>
				<?php
				break;

			case 'url':
				?>
				<input
					type="url"
					id="<?php echo esc_attr( $field_slug ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					class="editor-input"
					value="<?php echo esc_attr( $value ); ?>"
					<?php echo $required ? 'required' : ''; ?>
				>
				<?php
				break;

			default: // text.
				?>
				<input
					type="text"
					id="<?php echo esc_attr( $field_slug ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					class="editor-input"
					value="<?php echo esc_attr( $value ); ?>"
					<?php echo $required ? 'required' : ''; ?>
				>
				<?php
				break;
		}
		?>
	</div>
	<?php
}

/**
 * Handle opportunity save.
 */
function formapress_crm_handle_save_opportunity() {
	// Verify nonce.
	if ( ! isset( $_POST['formapress_opportunity_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['formapress_opportunity_nonce'] ) ), 'formapress_save_opportunity' ) ) {
		wp_die( __( 'Vérification de sécurité échouée.', 'formapress-crm' ) );
	}

	// Check permissions.
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( __( 'Permission refusée.', 'formapress-crm' ) );
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	$is_new  = empty( $post_id );

	// Prepare post data.
	$post_data = array(
		'post_title'   => isset( $_POST['opportunity_title'] ) ? sanitize_text_field( wp_unslash( $_POST['opportunity_title'] ) ) : '',
		'post_content' => isset( $_POST['opportunity_content'] ) ? wp_kses_post( wp_unslash( $_POST['opportunity_content'] ) ) : '',
		'post_type'    => 'crm_opportunity',
		'post_status'  => 'publish',
	);

	if ( ! $is_new ) {
		$post_data['ID'] = $post_id;
	}

	// Save post.
	if ( $is_new ) {
		$post_id = wp_insert_post( $post_data );
	} else {
		wp_update_post( $post_data );
	}

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		wp_die( __( 'Erreur lors de l\'enregistrement de l\'opportunité.', 'formapress-crm' ) );
	}

	// Save meta fields.
	$meta_fields = array(
		'opportunity_stage'       => '_crm_opportunity_stage',
		'opportunity_value'       => '_crm_opportunity_value',
		'opportunity_close_date'  => '_crm_opportunity_close_date',
		'associated_person_id'    => '_crm_associated_person_id',
		'associated_company_id'   => '_crm_associated_company_id',
		'associated_formation_id' => '_crm_associated_formation_id',
		'opportunity_zqpm_id'     => '_crm_opportunity_zqpm_id',
	);

	foreach ( $meta_fields as $post_key => $meta_key ) {
		if ( isset( $_POST[ $post_key ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );

			// Cast numeric values.
			if ( '_crm_opportunity_value' === $meta_key ) {
				$value = floatval( $value );
			} elseif ( in_array( $meta_key, array( '_crm_associated_person_id', '_crm_associated_company_id', '_crm_associated_formation_id', '_crm_opportunity_zqpm_id' ), true ) ) {
				$value = absint( $value );
			}

			// Delete if empty, otherwise update.
			if ( empty( $value ) && in_array( $meta_key, array( '_crm_associated_person_id', '_crm_associated_company_id', '_crm_associated_formation_id', '_crm_opportunity_zqpm_id' ), true ) ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $value );
			}
		}
	}

	// Save custom attributes.
	$attributes_schema = get_option( 'crm_opportunity_attributes', array() );
	foreach ( $attributes_schema as $field_slug => $field_config ) {
		$post_key = 'opportunity_attr_' . $field_slug;
		$meta_key = 'crm_opportunity_attributes_' . $field_slug;

		if ( isset( $_POST[ $post_key ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	// Redirect back to editor or list.
	$redirect_url = add_query_arg(
		array(
			'page'    => 'formapress-crm-edit-opportunity',
			'id'      => $post_id,
			'updated' => '1',
		),
		admin_url( 'admin.php' )
	);

	wp_safe_redirect( $redirect_url );
	exit;
}
add_action( 'admin_post_formapress_save_opportunity', 'formapress_crm_handle_save_opportunity' );

/**
 * AJAX handler for searching entities (persons/companies).
 */
function formapress_crm_ajax_search_entities() {
	check_ajax_referer( 'formapress_opportunity_editor', 'nonce' );

	$entity_type = isset( $_GET['entity_type'] ) ? sanitize_text_field( wp_unslash( $_GET['entity_type'] ) ) : '';
	$search      = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
	$company_id  = isset( $_GET['company_id'] ) ? (int) $_GET['company_id'] : 0;

	$results = array();

	if ( 'person' === $entity_type ) {
		// Search crm_person posts - only company_contact type.
		$query_args = array(
			'post_type'      => 'crm_person',
			'posts_per_page' => 20,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
			'tax_query'      => array(
				array(
					'taxonomy' => 'person_type',
					'field'    => 'slug',
					'terms'    => 'company_contact',
				),
			),
		);

		// Build meta_query for search and filtering.
		$meta_query = array( 'relation' => 'AND' );

		// Search by name (using indexed _crm_nom and _crm_prenom fields).
		if ( ! empty( $search ) ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => '_crm_nom',
					'value'   => $search,
					'compare' => 'LIKE',
				),
				array(
					'key'     => '_crm_prenom',
					'value'   => $search,
					'compare' => 'LIKE',
				),
			);
		}

		// Filter by company if provided.
		if ( $company_id > 0 ) {
			$meta_query[] = array(
				'key'     => '_crm_entreprise_ids',
				'value'   => '"' . $company_id . '"',
				'compare' => 'LIKE',
			);
		}

		if ( count( $meta_query ) > 1 ) {
			$query_args['meta_query'] = $meta_query;
		}

		$query = new WP_Query( $query_args );

		foreach ( $query->posts as $post ) {
			$results[] = array(
				'id'   => $post->ID,
				'text' => $post->post_title,
			);
		}
	} elseif ( 'company' === $entity_type ) {
		// Search crm_company posts (v2 only, no legacy support).
		$query = new WP_Query(
			array(
				'post_type'      => 'crm_company',
				's'              => $search,
				'posts_per_page' => 20,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);

		foreach ( $query->posts as $post ) {
			$results[] = array(
				'id'   => $post->ID,
				'text' => $post->post_title,
			);
		}
	} elseif ( 'formation' === $entity_type ) {
		// Search zform_formation posts.
		$query = new WP_Query(
			array(
				'post_type'      => 'zform_formation',
				's'              => $search,
				'posts_per_page' => 20,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);

		foreach ( $query->posts as $post ) {
			$results[] = array(
				'id'   => $post->ID,
				'text' => $post->post_title,
			);
		}
	}

	wp_send_json( array( 'results' => $results ) );
}
add_action( 'wp_ajax_formapress_search_entities', 'formapress_crm_ajax_search_entities' );

/**
 * AJAX: Get companies for a person (cascading dropdown).
 */
function formapress_crm_ajax_get_person_companies() {
	check_ajax_referer( 'formapress_opportunity_editor', 'nonce' );

	$person_id = isset( $_GET['person_id'] ) ? (int) $_GET['person_id'] : 0;

	if ( ! $person_id || get_post_type( $person_id ) !== 'crm_person' ) {
		wp_send_json_error( array( 'message' => 'Invalid person ID' ) );
	}

	// Get person's associated companies.
	// Try multiple possible meta keys (v1 and v2 formats).
	$company_ids = get_post_meta( $person_id, '_crm_entreprise_ids', true );
	error_log( '[FormaPress] Person ' . $person_id . ' _crm_entreprise_ids: ' . print_r( $company_ids, true ) );

	// Fallback to singular version (used by FormaPress_Person_Manager).
	if ( empty( $company_ids ) ) {
		$company_id = get_post_meta( $person_id, '_crm_company_id', true );
		error_log( '[FormaPress] Fallback _crm_company_id: ' . $company_id );
		if ( ! empty( $company_id ) ) {
			$company_ids = $company_id;
		}
	}

	if ( empty( $company_ids ) ) {
		error_log( '[FormaPress] No company IDs found, returning empty' );
		wp_send_json_success( array( 'companies' => array() ) );
		return;
	}

	// Handle both single ID and array of IDs.
	if ( ! is_array( $company_ids ) ) {
		$company_ids = array( $company_ids );
	}

	error_log( '[FormaPress] Company IDs to query: ' . print_r( $company_ids, true ) );

	$companies = array();
	foreach ( $company_ids as $company_id ) {
		// Skip if not numeric or empty.
		if ( empty( $company_id ) || ! is_numeric( $company_id ) ) {
			error_log( '[FormaPress] Skipping invalid company_id: ' . print_r( $company_id, true ) );
			continue;
		}

		$company = get_post( (int) $company_id );
		error_log( '[FormaPress] get_post(' . $company_id . '): ' . ( $company ? $company->post_type : 'NULL' ) );

		// Accept both v1 (zqpm_entreprise) and v2 (crm_company) post types.
		if ( $company && in_array( $company->post_type, array( 'zqpm_entreprise', 'crm_company' ), true ) ) {
			$companies[] = array(
				'id'   => $company->ID,
				'text' => $company->post_title,
			);
		}
	}

	error_log( '[FormaPress] Final companies array: ' . print_r( $companies, true ) );

	wp_send_json_success( array( 'companies' => $companies ) );
}
add_action( 'wp_ajax_formapress_crm_get_person_companies', 'formapress_crm_ajax_get_person_companies' );

/**
 * Render Quick Actions section (stage-based templates).
 *
 * @param int    $opportunity_id Opportunity post ID.
 * @param string $stage          Current opportunity stage.
 */
function formapress_crm_render_quick_actions( $opportunity_id, $stage ) {
	// Get email templates for this stage.
	$email_templates = get_posts(
		array(
			'post_type'      => 'email_template',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'zqpm_steps',
					'field'    => 'slug',
					'terms'    => 'crm_opportunity_' . $stage,
				),
			),
		)
	);

	// Get document templates for this stage.
	$document_templates = get_posts(
		array(
			'post_type'      => 'document_template',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'zqpm_steps',
					'field'    => 'slug',
					'terms'    => 'crm_opportunity_' . $stage,
				),
			),
		)
	);

	if ( empty( $email_templates ) ) {
		?>
		<div class="crm-quick-actions-empty">
			<p><?php esc_html_e( 'Aucun modèle d\'email configuré pour cette étape.', 'formapress-crm' ); ?></p>
			<p><small><?php esc_html_e( 'Créez un modèle d\'email et associez-le à l\'étape actuelle. Les documents PDF seront proposés comme pièces jointes lors de l\'envoi.', 'formapress-crm' ); ?></small></p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=formapress-crm-communication' ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Gérer les modèles', 'formapress-crm' ); ?>
			</a>
		</div>
		<?php
		return;
	}

	?>
	<div class="crm-quick-actions">
		<div class="quick-actions-group">
			<h4 class="quick-actions-title">📧 Envoyer une communication</h4>
			<p class="quick-actions-help"><?php esc_html_e( 'Cliquez pour envoyer un email. Vous pourrez sélectionner des documents PDF à joindre.', 'formapress-crm' ); ?></p>
			<?php foreach ( $email_templates as $template ) : ?>
				<button
					type="button"
					class="crm-quick-action-btn email-template-btn"
					data-template-id="<?php echo esc_attr( $template->ID ); ?>"
					data-template-type="email"
					data-opportunity-id="<?php echo esc_attr( $opportunity_id ); ?>"
				>
					<span class="dashicons dashicons-email"></span>
					<?php echo esc_html( $template->post_title ); ?>
					<?php if ( ! empty( $document_templates ) ) : ?>
						<span class="template-badge"><?php echo esc_html( sprintf( _n( '%d document disponible', '%d documents disponibles', count( $document_templates ), 'formapress-crm' ), count( $document_templates ) ) ); ?></span>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>
		</div>

		<?php if ( ! empty( $document_templates ) ) : ?>
			<div class="quick-actions-group">
				<h4 class="quick-actions-title">📄 Prévisualiser les documents PDF</h4>
				<p class="quick-actions-help"><?php esc_html_e( 'Ouvrir un aperçu du document avec les données actuelles (sans l\'enregistrer).', 'formapress-crm' ); ?></p>
				<?php foreach ( $document_templates as $doc_template ) : ?>
					<button
						type="button"
						class="crm-quick-action-btn preview-pdf-btn"
						data-template-id="<?php echo esc_attr( $doc_template->ID ); ?>"
						data-opportunity-id="<?php echo esc_attr( $opportunity_id ); ?>"
					>
						<span class="dashicons dashicons-visibility"></span>
						<?php echo esc_html( $doc_template->post_title ); ?>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="quick-actions-group">
			<h4 class="quick-actions-title">✅ Autres actions</h4>
			<button
				type="button"
				class="crm-quick-action-btn add-activity-btn"
				data-opportunity-id="<?php echo esc_attr( $opportunity_id ); ?>"
			>
				<span class="dashicons dashicons-plus"></span>
				<?php esc_html_e( 'Ajouter une activité', 'formapress-crm' ); ?>
			</button>
		</div>
	</div>
	<?php
}

/**
 * Render Activity Timeline section.
 *
 * @param int $opportunity_id Opportunity post ID.
 */
function formapress_crm_render_activity_timeline( $opportunity_id ) {
	// Get activities for this opportunity.
	$activities = get_posts(
		array(
			'post_type'      => 'crm_activity',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_crm_activity_associated_opportunity_id',
					'value' => $opportunity_id,
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	?>
	<div class="crm-activity-timeline">
		<!-- Quick Add Activity Form -->
		<div class="activity-quick-add" style="display: none;">
			<form id="quick-add-activity-form" class="activity-form">
				<input type="hidden" name="opportunity_id" value="<?php echo esc_attr( $opportunity_id ); ?>">
				<?php wp_nonce_field( 'formapress_add_activity', 'activity_nonce' ); ?>

				<div class="activity-form-row">
					<div class="activity-form-field">
						<label><?php esc_html_e( 'Type', 'formapress-crm' ); ?></label>
						<select name="activity_type" required>
							<option value="call">📞 <?php esc_html_e( 'Appel téléphonique', 'formapress-crm' ); ?></option>
							<option value="email">📧 <?php esc_html_e( 'Email', 'formapress-crm' ); ?></option>
							<option value="meeting">🤝 <?php esc_html_e( 'Réunion', 'formapress-crm' ); ?></option>
							<option value="task">✅ <?php esc_html_e( 'Tâche', 'formapress-crm' ); ?></option>
							<option value="note">📝 <?php esc_html_e( 'Note', 'formapress-crm' ); ?></option>
							<option value="document">📄 <?php esc_html_e( 'Document', 'formapress-crm' ); ?></option>
						</select>
					</div>
					<div class="activity-form-field">
						<label><?php esc_html_e( 'Date & heure', 'formapress-crm' ); ?></label>
						<input type="datetime-local" name="activity_date" value="<?php echo esc_attr( current_time( 'Y-m-d\TH:i' ) ); ?>" required>
					</div>
				</div>

				<div class="activity-form-row">
					<div class="activity-form-field">
						<label><?php esc_html_e( 'Statut', 'formapress-crm' ); ?></label>
						<select name="activity_status" required>
							<option value="completed">✅ <?php esc_html_e( 'Terminé', 'formapress-crm' ); ?></option>
							<option value="planned">⏳ <?php esc_html_e( 'Planifié', 'formapress-crm' ); ?></option>
						</select>
					</div>
					<div class="activity-form-field">
						<label><?php esc_html_e( 'Durée (minutes)', 'formapress-crm' ); ?></label>
						<input type="number" name="activity_duration" placeholder="30" min="0">
					</div>
				</div>

				<div class="activity-form-field">
					<label><?php esc_html_e( 'Notes', 'formapress-crm' ); ?></label>
					<textarea name="activity_notes" rows="3" placeholder="<?php esc_attr_e( 'Détails de l\'activité...', 'formapress-crm' ); ?>"></textarea>
				</div>

				<div class="activity-form-actions">
					<button type="submit" class="button button-primary">
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e( 'Ajouter l\'activité', 'formapress-crm' ); ?>
					</button>
					<button type="button" class="button button-secondary cancel-add-activity">
						<?php esc_html_e( 'Annuler', 'formapress-crm' ); ?>
					</button>
				</div>
			</form>
		</div>

		<!-- Timeline Header -->
		<div class="timeline-header">
			<button type="button" class="button button-secondary toggle-add-activity">
				<span class="dashicons dashicons-plus"></span>
				<?php esc_html_e( 'Ajouter une activité', 'formapress-crm' ); ?>
			</button>
			<?php if ( ! empty( $activities ) ) : ?>
				<div class="timeline-filters">
					<button type="button" class="timeline-filter active" data-filter="all">
						<?php esc_html_e( 'Toutes', 'formapress-crm' ); ?> (<?php echo count( $activities ); ?>)
					</button>
					<button type="button" class="timeline-filter" data-filter="completed">
						<?php esc_html_e( 'Terminées', 'formapress-crm' ); ?>
					</button>
					<button type="button" class="timeline-filter" data-filter="planned">
						<?php esc_html_e( 'Planifiées', 'formapress-crm' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>

		<!-- Timeline List -->
		<div class="timeline-list">
			<?php if ( empty( $activities ) ) : ?>
				<div class="timeline-empty">
					<span class="dashicons dashicons-info"></span>
					<p><?php esc_html_e( 'Aucune activité enregistrée pour cette opportunité.', 'formapress-crm' ); ?></p>
					<p><small><?php esc_html_e( 'Commencez à logger vos interactions pour suivre l\'évolution de cette opportunité.', 'formapress-crm' ); ?></small></p>
				</div>
			<?php else : ?>
				<?php foreach ( $activities as $activity ) : ?>
					<?php
					$activity_type     = get_post_meta( $activity->ID, '_crm_activity_type', true );
					$activity_date     = get_post_meta( $activity->ID, '_crm_activity_date', true ) ?: $activity->post_date;
					$activity_status   = get_post_meta( $activity->ID, '_crm_activity_status', true );
					$activity_duration = get_post_meta( $activity->ID, '_crm_activity_duration', true );
					$template_id       = get_post_meta( $activity->ID, '_crm_activity_template_id', true );

					// Icon mapping.
					$icons = array(
						'call'     => 'phone',
						'email'    => 'email',
						'meeting'  => 'groups',
						'task'     => 'yes',
						'note'     => 'edit',
						'document' => 'media-document',
					);
					$icon  = $icons[ $activity_type ] ?? 'admin-generic';
					?>
					<div class="timeline-item" data-status="<?php echo esc_attr( $activity_status ); ?>">
						<div class="timeline-marker <?php echo esc_attr( $activity_status ); ?>">
							<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
						</div>
						<div class="timeline-content">
							<div class="timeline-header-row">
								<h4 class="timeline-title">
									<?php echo esc_html( $activity->post_title ); ?>
									<?php if ( 'planned' === $activity_status ) : ?>
										<span class="activity-badge planned">⏳ <?php esc_html_e( 'Planifié', 'formapress-crm' ); ?></span>
									<?php else : ?>
										<span class="activity-badge completed">✅ <?php esc_html_e( 'Terminé', 'formapress-crm' ); ?></span>
									<?php endif; ?>
								</h4>
								<div class="timeline-actions">
									<a href="<?php echo esc_url( get_edit_post_link( $activity->ID ) ); ?>" class="timeline-action-btn" title="<?php esc_attr_e( 'Modifier', 'formapress-crm' ); ?>">
										<span class="dashicons dashicons-edit"></span>
									</a>
								</div>
							</div>
							<div class="timeline-meta">
								<span class="timeline-date">
									<?php echo esc_html( date_i18n( 'd/m/Y à H:i', strtotime( $activity_date ) ) ); ?>
								</span>
								<?php if ( $activity_duration ) : ?>
									<span class="timeline-duration">
										• <?php echo esc_html( $activity_duration ); ?> min
									</span>
								<?php endif; ?>
							</div>
							<?php if ( $activity->post_content ) : ?>
								<div class="timeline-description">
									<?php echo wp_kses_post( wpautop( $activity->post_content ) ); ?>
								</div>
							<?php endif; ?>
							<?php if ( $template_id ) : ?>
								<div class="timeline-template-link">
									<span class="dashicons dashicons-admin-page"></span>
									<?php esc_html_e( 'Modèle:', 'formapress-crm' ); ?>
									<a href="<?php echo esc_url( get_edit_post_link( $template_id ) ); ?>">
										<?php echo esc_html( get_the_title( $template_id ) ); ?>
									</a>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * AJAX handler for adding a new activity.
 */
function formapress_crm_ajax_add_activity() {
	check_ajax_referer( 'formapress_add_activity', 'activity_nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission refusée.', 'formapress-crm' ) ) );
	}

	$opportunity_id    = isset( $_POST['opportunity_id'] ) ? absint( $_POST['opportunity_id'] ) : 0;
	$activity_type     = isset( $_POST['activity_type'] ) ? sanitize_text_field( wp_unslash( $_POST['activity_type'] ) ) : '';
	$activity_date     = isset( $_POST['activity_date'] ) ? sanitize_text_field( wp_unslash( $_POST['activity_date'] ) ) : '';
	$activity_status   = isset( $_POST['activity_status'] ) ? sanitize_text_field( wp_unslash( $_POST['activity_status'] ) ) : 'completed';
	$activity_notes    = isset( $_POST['activity_notes'] ) ? wp_kses_post( wp_unslash( $_POST['activity_notes'] ) ) : '';
	$activity_duration = isset( $_POST['activity_duration'] ) ? absint( $_POST['activity_duration'] ) : 0;

	if ( ! $opportunity_id || ! $activity_type ) {
		wp_send_json_error( array( 'message' => __( 'Données invalides.', 'formapress-crm' ) ) );
	}

	// Create activity title.
	$type_labels    = array(
		'call'     => __( 'Appel téléphonique', 'formapress-crm' ),
		'email'    => __( 'Email', 'formapress-crm' ),
		'meeting'  => __( 'Réunion', 'formapress-crm' ),
		'task'     => __( 'Tâche', 'formapress-crm' ),
		'note'     => __( 'Note', 'formapress-crm' ),
		'document' => __( 'Document', 'formapress-crm' ),
	);
	$type_label     = $type_labels[ $activity_type ] ?? $activity_type;
	$activity_title = $type_label;

	// Create activity post.
	$activity_id = wp_insert_post(
		array(
			'post_type'    => 'crm_activity',
			'post_title'   => $activity_title,
			'post_content' => $activity_notes,
			'post_status'  => 'publish',
		)
	);

	if ( is_wp_error( $activity_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Erreur lors de la création de l\'activité.', 'formapress-crm' ) ) );
	}

	// Save meta.
	update_post_meta( $activity_id, '_crm_activity_type', $activity_type );
	update_post_meta( $activity_id, '_crm_activity_date', $activity_date );
	update_post_meta( $activity_id, '_crm_activity_status', $activity_status );
	update_post_meta( $activity_id, '_crm_activity_associated_opportunity_id', $opportunity_id );
	update_post_meta( $activity_id, '_crm_activity_is_automated', false );

	if ( $activity_duration > 0 ) {
		update_post_meta( $activity_id, '_crm_activity_duration', $activity_duration );
	}

	wp_send_json_success(
		array(
			'message'     => __( 'Activité ajoutée avec succès.', 'formapress-crm' ),
			'activity_id' => $activity_id,
		)
	);
}
add_action( 'wp_ajax_formapress_add_activity', 'formapress_crm_ajax_add_activity' );

/**
 * AJAX handler: Get contacts for a specific company (v2 only, no legacy support).
 * Used by both opportunity and invoice editors.
 */
function formapress_crm_ajax_get_company_contacts() {
	// Verify nonce - accept either opportunity or invoice editor nonce.
	$nonce_valid = false;
	if ( isset( $_POST['nonce'] ) ) {
		$nonce_valid = wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'formapress_opportunity_editor' )
			|| wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'formapress_invoice_editor' );
	}

	if ( ! $nonce_valid ) {
		wp_send_json_error( array( 'message' => 'Nonce verification failed' ) );
	}

	$company_id = isset( $_POST['company_id'] ) ? absint( $_POST['company_id'] ) : 0;

	if ( ! $company_id ) {
		wp_send_json_error( array( 'message' => 'Invalid company ID' ) );
	}

	// Debug: Log the company ID.
	error_log( 'FormaPress: Getting contacts for company ID: ' . $company_id );

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

	// Debug: Log contacts found.
	error_log( 'FormaPress: Found ' . count( $contacts ) . ' contacts for company ' . $company_id );

	foreach ( $contacts as $contact ) {
		$results[] = array(
			'id'   => $contact->ID,
			'text' => $contact->post_title,
		);
	}

	// Debug: Log results.
	error_log( 'FormaPress: Returning ' . count( $results ) . ' contacts' );

	wp_send_json_success( array( 'contacts' => $results ) );
}
add_action( 'wp_ajax_formapress_crm_get_company_contacts', 'formapress_crm_ajax_get_company_contacts' );
