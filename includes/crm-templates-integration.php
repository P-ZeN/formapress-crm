<?php
/**
 * CRM Templates Integration
 *
 * Extends ZQPM template system with CRM-specific taxonomy terms and interfaces.
 *
 * @package FormapressCRM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Add CRM-specific terms to zqpm_steps taxonomy.
 */
function formapress_crm_setup_template_taxonomy_terms() {
	// Check if taxonomy exists.
	if ( ! taxonomy_exists( 'zqpm_steps' ) ) {
		return;
	}

	$crm_terms = array(
		'crm_opportunity_new'                  => 'CRM - Nouveau prospect',
		'crm_opportunity_qualified'            => 'CRM - Prospect qualifié',
		'crm_opportunity_proposal_in_progress' => 'CRM - Proposition en préparation',
		'crm_opportunity_proposal'             => 'CRM - Proposition envoyée',
		'crm_opportunity_negotiation'          => 'CRM - En négociation',
		'crm_opportunity_won'                  => 'CRM - Opportunité gagnée',
		'crm_opportunity_lost'                 => 'CRM - Opportunité perdue',
		'crm_opportunity_followup'             => 'CRM - Relance générale',
		'crm_person_welcome'                   => 'CRM - Bienvenue nouveau contact',
		'crm_invoice_sent'                     => 'CRM - Facture envoyée',
		'crm_invoice_reminder'                 => 'CRM - Relance facture',
	);

	foreach ( $crm_terms as $slug => $name ) {
		// Check if term already exists.
		$term_exists = term_exists( $slug, 'zqpm_steps' );
		if ( ! $term_exists ) {
			wp_insert_term(
				$name,
				'zqpm_steps',
				array(
					'slug' => $slug,
				)
			);
		}
	}
}
add_action( 'init', 'formapress_crm_setup_template_taxonomy_terms', 20 );

/**
 * Register CRM communication center submenu.
 */
function formapress_crm_register_communication_menu() {
	add_submenu_page(
		'formapress-crm-dashboard',
		'Centre de communication',
		'Communication',
		'edit_posts',
		'formapress-crm-communication',
		'formapress_crm_communication_page_html',
		5 // Priority 5 to appear after Pipeline.
	);
}
add_action( 'admin_menu', 'formapress_crm_register_communication_menu', 20 );

/**
 * Lock down zqpm_steps selector on document templates (no term creation).
 *
 * Replaces the default taxonomy metabox with a single-select dropdown of existing terms.
 */
function formapress_crm_lock_document_template_steps_metabox() {
	// Remove default tag-style metabox to prevent "Add new" UI.
	remove_meta_box( 'tagsdiv-zqpm_steps', 'document_template', 'side' );

	add_meta_box(
		'formapress_zqpm_steps_selector',
		__( 'Étape (ZQPM/CRM)', 'formapress-crm' ),
		'formapress_crm_render_zqpm_steps_selector',
		'document_template',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_document_template', 'formapress_crm_lock_document_template_steps_metabox', 20 );

/**
 * Render the locked zqpm_steps selector.
 *
 * @param WP_Post $post Current post.
 */
function formapress_crm_render_zqpm_steps_selector( $post ) {
	wp_nonce_field( 'formapress_crm_save_doc_template_step', 'formapress_crm_doc_step_nonce' );

	$terms = get_terms(
		array(
			'taxonomy'   => 'zqpm_steps',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	$current  = array();
	$selected = wp_get_object_terms( $post->ID, 'zqpm_steps', array( 'fields' => 'ids' ) );
	if ( ! is_wp_error( $selected ) && ! empty( $selected ) ) {
		$current = $selected;
	}

	?>
	<p><?php esc_html_e( 'Sélectionnez une ou plusieurs étapes existantes. La création de nouvelles étapes est désactivée pour assurer la cohérence.', 'formapress-crm' ); ?></p>
	<select name="formapress_zqpm_step[]" class="widefat" multiple size="6">
		<?php foreach ( $terms as $term ) : ?>
			<option value="<?php echo esc_attr( $term->term_id ); ?>" <?php selected( in_array( $term->term_id, $current, true ), true ); ?>>
				<?php echo esc_html( $term->name ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<?php
}

/**
 * Save locked zqpm_steps selection for document templates.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function formapress_crm_save_document_template_steps( $post_id, $post ) {
	// Nonce check.
	if ( ! isset( $_POST['formapress_crm_doc_step_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['formapress_crm_doc_step_nonce'] ) ), 'formapress_crm_save_doc_template_step' ) ) {
		return;
	}

	// Permissions.
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Avoid autosave/quick edit.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( 'document_template' !== $post->post_type ) {
		return;
	}

	$term_ids = isset( $_POST['formapress_zqpm_step'] ) && is_array( $_POST['formapress_zqpm_step'] )
		? array_filter( array_map( 'absint', (array) $_POST['formapress_zqpm_step'] ) )
		: array();

	if ( ! empty( $term_ids ) ) {
		wp_set_object_terms( $post_id, $term_ids, 'zqpm_steps', false );
	} else {
		// Clear taxonomy if none selected.
		wp_set_object_terms( $post_id, array(), 'zqpm_steps', false );
	}
}
add_action( 'save_post_document_template', 'formapress_crm_save_document_template_steps', 10, 2 );

/**
 * Render CRM communication center page.
 */
function formapress_crm_communication_page_html() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation only, no data modification.
	$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'email';
	?>
	<div class="wrap formapress-crm-communication">
		<h1>Centre de communication CRM</h1>
		<p class="description">Gérez vos modèles d'emails et de documents pour automatiser vos communications commerciales.</p>

		<!-- Tabs -->
		<nav class="nav-tab-wrapper wp-clearfix">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=formapress-crm-communication&tab=email' ) ); ?>"
				class="nav-tab <?php echo 'email' === $current_tab ? 'nav-tab-active' : ''; ?>">
				<span class="dashicons dashicons-email"></span> Modèles d'emails
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=formapress-crm-communication&tab=document' ) ); ?>"
				class="nav-tab <?php echo 'document' === $current_tab ? 'nav-tab-active' : ''; ?>">
				<span class="dashicons dashicons-media-document"></span> Modèles de documents
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=formapress-crm-communication&tab=send' ) ); ?>"
				class="nav-tab <?php echo 'send' === $current_tab ? 'nav-tab-active' : ''; ?>">
				<span class="dashicons dashicons-email-alt"></span> Envoyer des communications
			</a>
		</nav>

		<div class="crm-communication-content" style="margin-top: 20px;">
			<?php
			switch ( $current_tab ) {
				case 'email':
					formapress_crm_render_email_templates_tab();
					break;
				case 'document':
					formapress_crm_render_document_templates_tab();
					break;
				case 'send':
					formapress_crm_render_send_tab();
					break;
			}
			?>
		</div>
	</div>
	<?php
}

/**
 * Render email templates tab.
 */
function formapress_crm_render_email_templates_tab() {
	// Get CRM email templates.
	$templates = get_posts(
		array(
			'post_type'      => 'email_template',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'zqpm_steps',
					'field'    => 'slug',
					'terms'    => array(
						'crm_opportunity_new',
						'crm_opportunity_qualified',
						'crm_opportunity_proposal_in_progress',
						'crm_opportunity_proposal',
						'crm_opportunity_negotiation',
						'crm_opportunity_won',
						'crm_opportunity_lost',
						'crm_opportunity_followup',
						'crm_person_welcome',
						'crm_invoice_sent',
						'crm_invoice_reminder',
					),
				),
			),
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	?>
	<div class="crm-templates-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
		<div>
			<h2>Modèles d'emails CRM</h2>
			<p>Ces modèles sont utilisés pour communiquer avec vos prospects, clients et partenaires.</p>
		</div>
		<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=email_template' ) ); ?>" class="button button-primary">
			<span class="dashicons dashicons-plus-alt"></span> Créer un modèle d'email
		</a>
	</div>

	<?php if ( empty( $templates ) ) : ?>
		<div class="notice notice-info">
			<p>
				<strong>Aucun modèle d'email CRM trouvé.</strong><br>
				Créez votre premier modèle d'email et associez-le à une étape CRM (via la taxonomie "Etapes").
			</p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 40%;">Titre du modèle</th>
					<th style="width: 30%;">Étape CRM</th>
					<th style="width: 20%;">Modifié</th>
					<th style="width: 10%;">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $templates as $template ) : ?>
					<?php
					$terms      = wp_get_object_terms( $template->ID, 'zqpm_steps' );
					$step_names = array();
					foreach ( $terms as $term ) {
						if ( strpos( $term->slug, 'crm_' ) === 0 ) {
							$step_names[] = $term->name;
						}
					}
					?>
					<tr>
						<td>
							<strong>
								<a href="<?php echo esc_url( get_edit_post_link( $template->ID ) ); ?>">
									<?php echo esc_html( $template->post_title ); ?>
								</a>
							</strong>
						</td>
						<td><?php echo esc_html( implode( ', ', $step_names ) ); ?></td>
						<td><?php echo esc_html( get_the_modified_date( '', $template ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $template->ID ) ); ?>" class="button button-small">
								Modifier
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<div class="crm-templates-help" style="margin-top: 30px; padding: 20px; background: #f0f6fc; border-left: 4px solid var(--zform_color_orange, #f57d20);">
		<h3>Shortcodes disponibles pour les emails CRM</h3>
		<p>Utilisez ces shortcodes dans vos modèles d'emails pour personnaliser automatiquement le contenu :</p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li><code>[crm_opportunity_title]</code> - Titre de l'opportunité</li>
			<li><code>[crm_opportunity_value]</code> - Montant de l'opportunité</li>
			<li><code>[crm_opportunity_close_date]</code> - Date de clôture prévue</li>
			<li><code>[crm_person_nom]</code> - Nom de la personne</li>
			<li><code>[crm_person_prenom]</code> - Prénom de la personne</li>
			<li><code>[crm_person_email]</code> - Email de la personne</li>
			<li><code>[crm_company_name]</code> - Nom de l'entreprise</li>
			<li><strong>Plus tous les shortcodes ZQPM existants pour les sessions et formations</strong></li>
		</ul>
	</div>
	<?php
}

/**
 * Render document templates tab.
 */
function formapress_crm_render_document_templates_tab() {
	// Get CRM document templates.
	$templates = get_posts(
		array(
			'post_type'      => 'document_template',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'zqpm_steps',
					'field'    => 'slug',
					'terms'    => array(
						'crm_opportunity_new',
						'crm_opportunity_qualified',
						'crm_opportunity_proposal_in_progress',
						'crm_opportunity_proposal',
						'crm_opportunity_negotiation',
						'crm_opportunity_won',
						'crm_opportunity_lost',
						'crm_opportunity_followup',
						'crm_person_welcome',
						'crm_invoice_sent',
						'crm_invoice_reminder',
					),
				),
			),
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	?>
	<div class="crm-templates-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
		<div>
			<h2>Modèles de documents CRM</h2>
			<p>Ces modèles sont utilisés pour générer des devis, propositions commerciales, et autres documents PDF.</p>
		</div>
		<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=document_template' ) ); ?>" class="button button-primary">
			<span class="dashicons dashicons-plus-alt"></span> Créer un modèle de document
		</a>
	</div>

	<?php if ( empty( $templates ) ) : ?>
		<div class="notice notice-info">
			<p>
				<strong>Aucun modèle de document CRM trouvé.</strong><br>
				Créez votre premier modèle de document et associez-le à une étape CRM (via la taxonomie "Etapes").
			</p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 40%;">Titre du modèle</th>
					<th style="width: 30%;">Étape CRM</th>
					<th style="width: 20%;">Modifié</th>
					<th style="width: 10%;">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $templates as $template ) : ?>
					<?php
					$terms      = wp_get_object_terms( $template->ID, 'zqpm_steps' );
					$step_names = array();
					foreach ( $terms as $term ) {
						if ( strpos( $term->slug, 'crm_' ) === 0 ) {
							$step_names[] = $term->name;
						}
					}
					?>
					<tr>
						<td>
							<strong>
								<a href="<?php echo esc_url( get_edit_post_link( $template->ID ) ); ?>">
									<?php echo esc_html( $template->post_title ); ?>
								</a>
							</strong>
						</td>
						<td><?php echo esc_html( implode( ', ', $step_names ) ); ?></td>
						<td><?php echo esc_html( get_the_modified_date( '', $template ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $template->ID ) ); ?>" class="button button-small">
								Modifier
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<div class="crm-templates-help" style="margin-top: 30px; padding: 20px; background: #f0f6fc; border-left: 4px solid var(--zform_color_orange, #f57d20);">
		<h3>Shortcodes disponibles pour les documents CRM</h3>
		<p>Utilisez ces shortcodes dans vos modèles de documents pour personnaliser automatiquement le contenu :</p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li><code>[crm_opportunity_title]</code> - Titre de l'opportunité</li>
			<li><code>[crm_opportunity_value]</code> - Montant de l'opportunité</li>
			<li><code>[crm_opportunity_stage]</code> - Étape actuelle (Nouveau, Qualifié, etc.)</li>
			<li><code>[crm_person_nom]</code> - Nom de la personne</li>
			<li><code>[crm_person_prenom]</code> - Prénom de la personne</li>
			<li><code>[crm_person_email]</code> - Email de la personne</li>
			<li><code>[crm_company_name]</code> - Nom de l'entreprise</li>
			<li><code>[crm_company_address]</code> - Adresse de l'entreprise</li>
			<li><strong>Plus tous les shortcodes ZQPM existants pour les sessions et formations</strong></li>
		</ul>
	</div>
	<?php
}

/**
 * Render send communications tab.
 */
function formapress_crm_render_send_tab() {
	?>
	<div style="background: #fff; padding: 30px; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
		<h2>Envoyer des communications</h2>
		<p class="description" style="margin-bottom: 30px;">
			Envoyez des emails et documents personnalisés à vos contacts, prospects et clients.
		</p>

		<div class="notice notice-info">
			<p>
				<strong>Fonctionnalité en cours de développement</strong><br>
				Cette interface permettra d'envoyer des emails et générer des documents en utilisant vos modèles,
				de manière similaire au système ZQPM que vous connaissez déjà.
			</p>
			<p>
				<strong>Fonctionnalités prévues :</strong>
			</p>
			<ul style="list-style: disc; margin-left: 20px;">
				<li>Sélection de destinataires depuis les opportunités, personnes ou entreprises</li>
				<li>Choix du modèle d'email ou de document</li>
				<li>Mode test (aperçu sans envoi) ou envoi réel</li>
				<li>Génération de PDF avec archivage automatique</li>
				<li>Ajout de pièces jointes</li>
				<li>Envoi individuel ou collecteur (comme ZQPM)</li>
			</ul>
		</div>

		<div style="margin-top: 30px; text-align: center;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=formapress-crm-communication&tab=email' ) ); ?>"
				class="button button-primary button-hero">
				Créer vos modèles d'emails
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=formapress-crm-communication&tab=document' ) ); ?>"
				class="button button-secondary button-hero">
				Créer vos modèles de documents
			</a>
		</div>
	</div>
	<?php
}
