<?php
/**
 * Register crm_invoice Custom Post Type for financial tracking.
 *
 * @package FormapressCRM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register crm_invoice CPT.
 */
function formapress_crm_register_invoice_cpt() {
	$labels = array(
		'name'                  => 'Factures',
		'singular_name'         => 'Facture',
		'menu_name'             => 'Factures',
		'name_admin_bar'        => 'Facture',
		'add_new'               => 'Ajouter',
		'add_new_item'          => 'Ajouter une facture',
		'new_item'              => 'Nouvelle facture',
		'edit_item'             => 'Modifier la facture',
		'view_item'             => 'Voir la facture',
		'all_items'             => 'Factures',
		'search_items'          => 'Rechercher des factures',
		'parent_item_colon'     => 'Factures parentes :',
		'not_found'             => 'Aucune facture trouvée.',
		'not_found_in_trash'    => 'Aucune facture trouvée dans la corbeille.',
		'featured_image'        => 'Image de la facture',
		'set_featured_image'    => 'Définir l\'image de la facture',
		'remove_featured_image' => 'Retirer l\'image de la facture',
		'use_featured_image'    => 'Utiliser comme image de la facture',
		'archives'              => 'Archives des factures',
		'insert_into_item'      => 'Insérer dans la facture',
		'uploaded_to_this_item' => 'Téléchargé pour cette facture',
		'filter_items_list'     => 'Filtrer la liste des factures',
		'items_list_navigation' => 'Navigation de la liste des factures',
		'items_list'            => 'Liste des factures',
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => 'formapress-crm-dashboard', // Show under the main CRM menu.
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'crm-invoice' ),
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => null,
		'supports'           => array( 'title', 'editor', 'custom-fields' ),
		'show_in_rest'       => true,
		'menu_icon'          => 'dashicons-money-alt',
	);

	register_post_type( 'crm_invoice', $args );
}
add_action( 'init', 'formapress_crm_register_invoice_cpt' );

/**
 * Adds meta boxes for the crm_invoice CPT.
 */
function formapress_crm_add_invoice_meta_boxes() {
	add_meta_box(
		'formapress_crm_invoice_details_meta_box',
		'Détails financiers',
		'formapress_crm_invoice_details_meta_box_html',
		'crm_invoice',
		'normal',
		'high'
	);
	add_meta_box(
		'formapress_crm_invoice_associations_meta_box',
		'Liens',
		'formapress_crm_invoice_associations_meta_box_html',
		'crm_invoice',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes_crm_invoice', 'formapress_crm_add_invoice_meta_boxes' );

/**
 * Renders the HTML for the Invoice Details meta box.
 *
 * @param WP_Post $post The current post object.
 */
function formapress_crm_invoice_details_meta_box_html( $post ) {
	wp_nonce_field( 'formapress_crm_save_invoice_meta_data', 'formapress_crm_invoice_meta_nonce' );

	$amount         = get_post_meta( $post->ID, '_crm_invoice_amount', true );
	$payment_status = get_post_meta( $post->ID, '_crm_invoice_payment_status', true );
	$funding_source = get_post_meta( $post->ID, '_crm_invoice_funding_source', true );
	$invoice_date   = get_post_meta( $post->ID, '_crm_invoice_date', true );
	$payment_date   = get_post_meta( $post->ID, '_crm_invoice_payment_date', true );
	$opco_name      = get_post_meta( $post->ID, '_crm_invoice_opco_name', true );
	$agreement_num  = get_post_meta( $post->ID, '_crm_invoice_agreement_number', true );

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

	?>
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="crm_invoice_amount">Montant (€) <strong style="color: #d63638;">*</strong></label>
			</th>
			<td>
				<input type="number" step="0.01" id="crm_invoice_amount" name="crm_invoice_amount" value="<?php echo esc_attr( $amount ); ?>" class="regular-text" placeholder="0.00" required />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="crm_invoice_payment_status">Statut de paiement</label>
			</th>
			<td>
				<select id="crm_invoice_payment_status" name="crm_invoice_payment_status" class="regular-text">
					<option value="">— Sélectionner —</option>
					<?php foreach ( $payment_statuses as $status_key => $status_label ) : ?>
						<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $payment_status, $status_key ); ?>>
							<?php echo esc_html( $status_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="crm_invoice_funding_source">Source de financement</label>
			</th>
			<td>
				<select id="crm_invoice_funding_source" name="crm_invoice_funding_source" class="regular-text">
					<option value="">— Sélectionner —</option>
					<?php foreach ( $funding_sources as $source_key => $source_label ) : ?>
						<option value="<?php echo esc_attr( $source_key ); ?>" <?php selected( $funding_source, $source_key ); ?>>
							<?php echo esc_html( $source_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="crm_invoice_date">Date de facture</label>
			</th>
			<td>
				<input type="date" id="crm_invoice_date" name="crm_invoice_date" value="<?php echo esc_attr( $invoice_date ); ?>" class="regular-text" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="crm_invoice_payment_date">Date de paiement</label>
			</th>
			<td>
				<input type="date" id="crm_invoice_payment_date" name="crm_invoice_payment_date" value="<?php echo esc_attr( $payment_date ); ?>" class="regular-text" />
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="crm_invoice_opco_name">Nom OPCO</label>
			</th>
			<td>
				<input type="text" id="crm_invoice_opco_name" name="crm_invoice_opco_name" value="<?php echo esc_attr( $opco_name ); ?>" class="regular-text" placeholder="Ex: OPCO EP, AFDAS..." />
				<p class="description">Si la source de financement est OPCO</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="crm_invoice_agreement_number">Numéro d'accord/convention</label>
			</th>
			<td>
				<input type="text" id="crm_invoice_agreement_number" name="crm_invoice_agreement_number" value="<?php echo esc_attr( $agreement_num ); ?>" class="regular-text" />
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Renders the HTML for the Invoice Associations meta box.
 *
 * @param WP_Post $post The current post object.
 */
function formapress_crm_invoice_associations_meta_box_html( $post ) {
	$zqpm_id    = get_post_meta( $post->ID, '_crm_invoice_zqpm_id', true );
	$company_id = get_post_meta( $post->ID, '_crm_invoice_company_id', true );
	$person_id  = get_post_meta( $post->ID, '_crm_invoice_person_id', true );

	// Get ZQPMs for dropdown.
	$zqpms = get_posts(
		array(
			'post_type'      => 'zqpm',
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	// Get companies for dropdown.
	$companies = get_posts(
		array(
			'post_type'      => 'zqpm_entreprise',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	// Get persons for dropdown.
	$persons = get_posts(
		array(
			'post_type'      => 'crm_person',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	?>
	<p>
		<label for="crm_invoice_zqpm_id"><strong>Suivi de session associé</strong></label><br />
		<select id="crm_invoice_zqpm_id" name="crm_invoice_zqpm_id" class="widefat" style="max-width: 100%;">
			<option value="">— Aucun —</option>
			<?php foreach ( $zqpms as $zqpm ) : ?>
				<?php
				$zqpm_title = $zqpm->post_title;
				if ( function_exists( 'zqpm_construct_new_title' ) ) {
					$zqpm_title = zqpm_construct_new_title( $zqpm->post_title, $zqpm->ID );
				}
				?>
				<option value="<?php echo esc_attr( $zqpm->ID ); ?>" <?php selected( $zqpm_id, $zqpm->ID ); ?>>
					<?php echo esc_html( $zqpm_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<small>Session de formation liée</small>
	</p>

	<p>
		<label for="crm_invoice_company_id"><strong>Entreprise</strong></label><br />
		<select id="crm_invoice_company_id" name="crm_invoice_company_id" class="widefat">
			<option value="">— Aucune —</option>
			<?php foreach ( $companies as $company ) : ?>
				<option value="<?php echo esc_attr( $company->ID ); ?>" <?php selected( $company_id, $company->ID ); ?>>
					<?php echo esc_html( $company->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>

	<p>
		<label for="crm_invoice_person_id"><strong>Contact principal</strong></label><br />
		<select id="crm_invoice_person_id" name="crm_invoice_person_id" class="widefat">
			<option value="">— Aucun —</option>
			<?php foreach ( $persons as $person ) : ?>
				<option value="<?php echo esc_attr( $person->ID ); ?>" <?php selected( $person_id, $person->ID ); ?>>
					<?php echo esc_html( $person->post_title ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<?php
}

/**
 * Saves the meta data for the crm_invoice CPT.
 *
 * @param int $post_id The ID of the post being saved.
 */
function formapress_crm_save_invoice_meta_data( $post_id ) {
	if ( ! isset( $_POST['formapress_crm_invoice_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['formapress_crm_invoice_meta_nonce'] ) ), 'formapress_crm_save_invoice_meta_data' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Sanitize and save Invoice Details.
	$fields_to_save = array(
		'crm_invoice_amount'           => '_crm_invoice_amount',
		'crm_invoice_payment_status'   => '_crm_invoice_payment_status',
		'crm_invoice_funding_source'   => '_crm_invoice_funding_source',
		'crm_invoice_date'             => '_crm_invoice_date',
		'crm_invoice_payment_date'     => '_crm_invoice_payment_date',
		'crm_invoice_opco_name'        => '_crm_invoice_opco_name',
		'crm_invoice_agreement_number' => '_crm_invoice_agreement_number',
		'crm_invoice_zqpm_id'          => '_crm_invoice_zqpm_id',
		'crm_invoice_company_id'       => '_crm_invoice_company_id',
		'crm_invoice_person_id'        => '_crm_invoice_person_id',
	);

	foreach ( $fields_to_save as $post_key => $meta_key ) {
		if ( isset( $_POST[ $post_key ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );

			// Handle numeric fields.
			if ( '_crm_invoice_amount' === $meta_key ) {
				$value = floatval( $value );
			} elseif ( in_array( $meta_key, array( '_crm_invoice_zqpm_id', '_crm_invoice_company_id', '_crm_invoice_person_id' ), true ) ) {
				$value = intval( $value );
			}

			// Save or delete if empty.
			if ( empty( $value ) && '_crm_invoice_amount' !== $meta_key ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $value );
			}
		}
	}
}
add_action( 'save_post_crm_invoice', 'formapress_crm_save_invoice_meta_data' );
