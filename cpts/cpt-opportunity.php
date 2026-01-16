<?php
/**
 * Register crm_opportunity Custom Post Type.
 *
 * @package FormapressCRM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register crm_opportunity CPT.
 */
function formapress_crm_register_opportunity_cpt() {
	$labels = array(
		'name'                  => 'Opportunités',
		'singular_name'         => 'Opportunité',
		'menu_name'             => 'Opportunités',
		'name_admin_bar'        => 'Opportunité',
		'add_new'               => 'Ajouter',
		'add_new_item'          => 'Ajouter une opportunité',
		'new_item'              => 'Nouvelle opportunité',
		'edit_item'             => 'Modifier l\'opportunité',
		'view_item'             => 'Voir l\'opportunité',
		'all_items'             => 'Opportunités',
		'search_items'          => 'Rechercher des opportunités',
		'parent_item_colon'     => 'Opportunités parentes :',
		'not_found'             => 'Aucune opportunité trouvée.',
		'not_found_in_trash'    => 'Aucune opportunité trouvée dans la corbeille.',
		'featured_image'        => 'Image de l\'opportunité',
		'set_featured_image'    => 'Définir l\'image de l\'opportunité',
		'remove_featured_image' => 'Retirer l\'image de l\'opportunité',
		'use_featured_image'    => 'Utiliser comme image de l\'opportunité',
		'archives'              => 'Archives des opportunités',
		'insert_into_item'      => 'Insérer dans l\'opportunité',
		'uploaded_to_this_item' => 'Téléchargé pour cette opportunité',
		'filter_items_list'     => 'Filtrer la liste des opportunités',
		'items_list_navigation' => 'Navigation de la liste des opportunités',
		'items_list'            => 'Liste des opportunités',
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => 'formapress-crm-dashboard', // Show under the main CRM menu.
		'query_var'          => true,
		'menu_position'      => 10, // Position after dashboard and pipeline.
		'rewrite'            => array( 'slug' => 'crm-opportunity' ),
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => null,
		'supports'           => array( 'title', 'editor', 'custom-fields', 'thumbnail' ),
		'show_in_rest'       => true, // Enable Gutenberg editor and REST API access.
		'menu_icon'          => 'dashicons-chart-line',
	);

	register_post_type( 'crm_opportunity', $args );
}
add_action( 'init', 'formapress_crm_register_opportunity_cpt' );

/**
 * Adds meta boxes for the crm_opportunity CPT.
 */
function formapress_crm_add_opportunity_meta_boxes() {
	add_meta_box(
		'formapress_crm_opportunity_details_meta_box',
		'Détails de l\'opportunité',
		'formapress_crm_opportunity_details_meta_box_html',
		'crm_opportunity',
		'normal',
		'high'
	);
	add_meta_box(
		'formapress_crm_opportunity_associations_meta_box',
		'Entités associées',
		'formapress_crm_opportunity_associations_meta_box_html',
		'crm_opportunity',
		'side',
		'default'
	);
	add_meta_box(
		'formapress_crm_opportunity_zqpm_link_meta_box',
		'Suivi ZQPM',
		'formapress_crm_opportunity_zqpm_link_meta_box_html',
		'crm_opportunity',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_crm_opportunity', 'formapress_crm_add_opportunity_meta_boxes' );

/**
 * Renders the HTML for the Opportunity Details meta box.
 *
 * @param WP_Post $post The current post object.
 */
function formapress_crm_opportunity_details_meta_box_html( $post ) {
	wp_nonce_field( 'formapress_crm_save_opportunity_meta_data', 'formapress_crm_opportunity_meta_nonce' );

	$stage      = get_post_meta( $post->ID, '_crm_opportunity_stage', true );
	$value      = get_post_meta( $post->ID, '_crm_opportunity_value', true );
	$close_date = get_post_meta( $post->ID, '_crm_opportunity_close_date', true );

	// Define pipeline stages for Kanban board.
	$stages = array(
		'new'                  => 'Nouveau prospect',
		'qualified'            => 'Qualifié',
		'proposal_in_progress' => 'Proposition en préparation',
		'proposal'             => 'Proposition envoyée',
		'negotiation'          => 'En négociation',
		'won'                  => 'Gagné',
		'lost'                 => 'Perdu',
	);

	// Default to 'new' if not set.
	if ( empty( $stage ) ) {
		$stage = 'new';
	}

	?>
	<p>
		<label for="crm_opportunity_stage">Étape du pipeline : <strong style="color: #d63638;">*</strong></label><br />
		<select id="crm_opportunity_stage" name="crm_opportunity_stage" class="widefat" required>
			<?php foreach ( $stages as $stage_key => $stage_label ) : ?>
				<option value="<?php echo esc_attr( $stage_key ); ?>" <?php selected( $stage, $stage_key ); ?>>
					<?php echo esc_html( $stage_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<small>Position actuelle dans le pipeline commercial (glisser-déposer les cartes dans la vue Kanban)</small>
	</p>
	<p>
		<label for="crm_opportunity_value">Montant estimé (€) :</label><br />
		<input type="number" step="0.01" id="crm_opportunity_value" name="crm_opportunity_value" value="<?php echo esc_attr( $value ); ?>" class="widefat" placeholder="0.00" />
	</p>
	<p>
		<label for="crm_opportunity_close_date">Date de clôture prévue :</label><br />
		<input type="date" id="crm_opportunity_close_date" name="crm_opportunity_close_date" value="<?php echo esc_attr( $close_date ); ?>" class="widefat" />
	</p>
	<?php
}

/**
 * Renders the HTML for the Associated Entities meta box.
 *
 * @param WP_Post $post The current post object.
 */
function formapress_crm_opportunity_associations_meta_box_html( $post ) {
	// Nonce is in the details meta box.
	$person_id  = get_post_meta( $post->ID, '_crm_associated_person_id', true );
	$company_id = get_post_meta( $post->ID, '_crm_associated_company_id', true );

	?>
	<p>
		<label for="crm_associated_person_id">Personne associée (ID crm_person) :</label><br />
		<input type="number" id="crm_associated_person_id" name="crm_associated_person_id" value="<?php echo esc_attr( $person_id ); ?>" class="widefat" />
		<small>Entrez l'ID du post crm_person.</small>
	</p>
	<p>
		<label for="crm_associated_company_id">Entreprise associée (ID zqpm_entreprise) :</label><br />
		<input type="number" id="crm_associated_company_id" name="crm_associated_company_id" value="<?php echo esc_attr( $company_id ); ?>" class="widefat" />
		<small>Entrez l'ID du post zqpm_entreprise.</small>
	</p>
	<?php
	// Amélioration future : Utiliser des menus déroulants avec les personnes/entreprises existantes.
}

/**
 * Renders the HTML for the ZQPM Link meta box.
 *
 * @param WP_Post $post The current post object.
 */
function formapress_crm_opportunity_zqpm_link_meta_box_html( $post ) {
	$stage        = get_post_meta( $post->ID, '_crm_opportunity_stage', true );
	$zqpm_id      = get_post_meta( $post->ID, '_crm_opportunity_zqpm_id', true );
	$company_id   = get_post_meta( $post->ID, '_crm_associated_company_id', true );
	$person_id    = get_post_meta( $post->ID, '_crm_associated_person_id', true );
	$formation_id = get_post_meta( $post->ID, '_crm_associated_formation_id', true );

	// Only show conversion option for won opportunities.
	if ( 'won' !== $stage ) {
		?>
		<p style="color: #646970; font-size: 13px; line-height: 1.5;">
			<span class="dashicons dashicons-info" style="color: #72aee6;"></span>
			<em>Une fois cette opportunité marquée comme <strong>"Gagné"</strong>, vous pourrez créer ou lier un Suivi de session.</em>
		</p>
		<?php
		return;
	}

	// Check if already linked to a ZQPM.
	if ( $zqpm_id && get_post_type( $zqpm_id ) === 'zqpm' ) {
		$zqpm_title = get_the_title( $zqpm_id );
		if ( empty( $zqpm_title ) ) {
			$zqpm_title = 'Suivi de session #' . $zqpm_id;
		}

		// Get ZQPM meta for display.
		$session_id   = get_post_meta( $zqpm_id, 'zqpm_session_id', true );
		$session_info = '';
		if ( $session_id && class_exists( 'zSession' ) ) {
			$session = new zSession( $session_id );
			if ( $session->formation_id ) {
				$formation = get_post( $session->formation_id );
				if ( $formation ) {
					$session_info = ' - ' . esc_html( $formation->post_title );
				}
			}
		}
		?>
		<div style="background: #f0f6fc; border: 1px solid #c3e7ff; border-radius: 4px; padding: 12px; margin-bottom: 12px;">
			<p style="margin: 0 0 8px 0; color: #00a32a; font-weight: 600;">
				<span class="dashicons dashicons-yes-alt" style="font-size: 16px;"></span>
				Suivi de session lié
			</p>
			<p style="margin: 0 0 10px 0; font-size: 13px; color: #2c3338;">
				<strong><?php echo esc_html( $zqpm_title ); ?></strong>
				<?php echo esc_html( $session_info ); ?>
			</p>
			<a href="<?php echo esc_url( get_edit_post_link( $zqpm_id ) ); ?>" class="button button-primary" style="width: 100%; text-align: center;" target="_blank">
				<span class="dashicons dashicons-external" style="font-size: 16px; vertical-align: middle;"></span>
				Voir le Suivi de session
			</a>
		</div>
		<details style="margin-top: 12px;">
			<summary style="cursor: pointer; color: #2271b1; font-size: 12px;">
				<span class="dashicons dashicons-admin-links" style="font-size: 14px; vertical-align: middle;"></span>
				Modifier le lien
			</summary>
			<div style="margin-top: 8px; padding: 10px; background: #f6f7f7; border-radius: 4px;">
				<label for="crm_opportunity_zqpm_id" style="display: block; margin-bottom: 4px; font-size: 12px; font-weight: 500;">ID du Suivi de session :</label>
				<input type="number" id="crm_opportunity_zqpm_id" name="crm_opportunity_zqpm_id" value="<?php echo esc_attr( $zqpm_id ); ?>" class="widefat" />
				<small style="display: block; margin-top: 4px; color: #646970;">
					Videz le champ pour dissocier ou entrez un nouvel ID.
				</small>
			</div>
		</details>
		<?php
	} else {
		// Build URL with prefilled params.
		$create_url = add_query_arg(
			array(
				'post_type'        => 'zqpm',
				'from_opportunity' => $post->ID,
				'company_id'       => $company_id,
				'person_id'        => $person_id,
				'formation_id'     => $formation_id,
			),
			admin_url( 'post-new.php' )
		);

		// Get available ZQPMs for linking.
		$zqpm_args = array(
			'post_type'      => 'zqpm',
			'posts_per_page' => 100,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// Filter by formation if set.
		if ( ! empty( $formation_id ) ) {
			$zqpm_args['meta_query'] = array(
				array(
					'key'     => 'zqpm_formation_id',
					'value'   => $formation_id,
					'compare' => '=',
				),
			);
		}

		$zqpms = get_posts( $zqpm_args );

		?>
		<div style="background: #f9fafb; border: 1px solid #dcdcde; border-radius: 4px; padding: 12px; margin-bottom: 12px;">
			<p style="margin: 0 0 10px 0; color: #2c3338; font-size: 13px;">
				<span class="dashicons dashicons-calendar-alt" style="color: var(--zform_color_orange, #ff8c00);"></span>
				<strong>Opportunité gagnée !</strong>
			</p>
			<button type="button" id="formapress-create-zqpm-btn" class="button button-primary" style="width: 100%; text-align: center; margin-bottom: 8px;" data-opportunity-id="<?php echo esc_attr( $post->ID ); ?>" data-company-id="<?php echo esc_attr( $company_id ); ?>" data-person-id="<?php echo esc_attr( $person_id ); ?>" data-formation-id="<?php echo esc_attr( $formation_id ); ?>">
				<span class="dashicons dashicons-plus-alt" style="font-size: 16px; vertical-align: middle;"></span>
				Créer un Suivi de session
			</button>
			<p style="margin: 8px 0; text-align: center; color: #646970; font-size: 12px;">ou</p>
		</div>

		<div style="background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 12px;">
			<label for="crm_opportunity_zqpm_id" style="display: block; margin-bottom: 6px; font-weight: 500; font-size: 13px;">
				<span class="dashicons dashicons-admin-links" style="font-size: 14px; vertical-align: middle;"></span>
				Lier à un Suivi de session existant
			</label>
			<select id="crm_opportunity_zqpm_id" name="crm_opportunity_zqpm_id" class="widefat">
				<option value="">— Sélectionner —</option>
				<?php foreach ( $zqpms as $zqpm ) : ?>
					<?php
					$zqpm_title = get_the_title( $zqpm->ID );
					if ( empty( $zqpm_title ) ) {
						$zqpm_title = 'Suivi #' . $zqpm->ID;
					}
					$session_id   = get_post_meta( $zqpm->ID, 'zqpm_session_id', true );
					$session_date = '';
					if ( $session_id ) {
						$session_date = ' - ' . get_the_date( 'd/m/Y', $session_id );
					}
					?>
					<option value="<?php echo esc_attr( $zqpm->ID ); ?>">
						<?php echo esc_html( $zqpm_title . $session_date ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php if ( ! empty( $formation_id ) ) : ?>
				<small style="display: block; margin-top: 6px; color: #646970;">
					Affiche les Suivis de session pour la formation sélectionnée.
				</small>
			<?php else : ?>
				<small style="display: block; margin-top: 6px; color: #d63638;">
					⚠️ Aucune formation définie. Affichage de tous les Suivis de session.
				</small>
			<?php endif; ?>
		</div>

		<!-- Modal for creating ZQPM -->
		<div id="formapress-create-zqpm-modal" style="display: none;">
			<div class="formapress-modal-overlay"></div>
			<div class="formapress-modal-content">
				<div class="formapress-modal-header">
					<h2>Créer un Suivi de session</h2>
					<button type="button" class="formapress-modal-close">&times;</button>
				</div>
				<div class="formapress-modal-body">
					<p style="margin-bottom: 16px; color: #646970;">
						Les informations de l'opportunité seront utilisées pour créer le Suivi de session.
					</p>

					<div style="background: #f0f6fc; border-left: 3px solid var(--zform_color_orange, #ff8c00); padding: 12px; margin-bottom: 16px;">
						<p style="margin: 0 0 8px 0; font-weight: 600;">Données pré-remplies :</p>
						<ul style="margin: 0; padding-left: 20px;">
							<?php if ( $company_id ) : ?>
								<li>Entreprise : <strong><?php echo esc_html( get_the_title( $company_id ) ); ?></strong></li>
							<?php endif; ?>
							<?php if ( $person_id ) : ?>
								<li>Contact : <strong><?php echo esc_html( get_the_title( $person_id ) ); ?></strong></li>
							<?php endif; ?>
							<?php if ( $formation_id ) : ?>
								<li>Formation : <strong><?php echo esc_html( get_the_title( $formation_id ) ); ?></strong></li>
							<?php endif; ?>
						</ul>
					</div>

					<div style="margin-bottom: 16px;">
						<label for="formapress-session-select" style="display: block; margin-bottom: 6px; font-weight: 500;">
							Session <span style="color: #d63638;">*</span>
						</label>
						<select id="formapress-session-select" class="widefat" required style="padding: 8px;">
							<option value="">Chargement des sessions...</option>
						</select>
						<small style="display: block; margin-top: 4px; color: #646970;">
							La session est obligatoire pour créer un Suivi de session.
						</small>
					</div>

					<div id="formapress-zqpm-error" style="display: none; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 16px;"></div>
				</div>
				<div class="formapress-modal-footer">
					<button type="button" class="button button-secondary formapress-modal-close">Annuler</button>
					<button type="button" id="formapress-create-zqpm-submit" class="button button-primary">
						<span class="dashicons dashicons-yes" style="vertical-align: middle;"></span>
						Créer le Suivi de session
					</button>
				</div>
			</div>
		</div>
		<?php
	}
}

/**
 * Saves the meta data for the crm_opportunity CPT.
 *
 * @param int $post_id The ID of the post being saved.
 */
function formapress_crm_save_opportunity_meta_data( $post_id ) {
	if ( ! isset( $_POST['formapress_crm_opportunity_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['formapress_crm_opportunity_meta_nonce'] ) ), 'formapress_crm_save_opportunity_meta_data' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Sanitize and save Opportunity Details.
	$fields_to_save = array(
		'crm_opportunity_stage'      => '_crm_opportunity_stage',
		'crm_opportunity_value'      => '_crm_opportunity_value',
		'crm_opportunity_close_date' => '_crm_opportunity_close_date',
		'crm_associated_person_id'   => '_crm_associated_person_id',
		'crm_associated_company_id'  => '_crm_associated_company_id',
		'crm_opportunity_zqpm_id'    => '_crm_opportunity_zqpm_id',
	);

	foreach ( $fields_to_save as $post_key => $meta_key ) {
		if ( isset( $_POST[ $post_key ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
			if ( in_array( $meta_key, array( '_crm_opportunity_value', '_crm_associated_person_id', '_crm_associated_company_id', '_crm_opportunity_zqpm_id' ), true ) ) {
				// For numeric values, ensure they are properly formatted or cast.
				// Value can be float, IDs are int.
				if ( '_crm_opportunity_value' === $meta_key ) {
					$value = floatval( $value );
				} else {
					$value = intval( $value );
				}
			}
			// Save the value, or delete if empty for ID fields.
			if ( in_array( $meta_key, array( '_crm_associated_person_id', '_crm_associated_company_id', '_crm_opportunity_zqpm_id' ), true ) && empty( $value ) ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $value );
			}
		}
	}
}
add_action( 'save_post_crm_opportunity', 'formapress_crm_save_opportunity_meta_data' );
