<?php
/**
 * Custom Person Editor Page
 *
 * Provides a modern, clean interface for editing persons (crm_person CPT)
 * with better UX than the default WordPress editor.
 *
 * @package FormapressCRM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Redirect person edit screen to custom editor.
 */
function formapress_crm_redirect_person_editor() {
	global $pagenow, $typenow;

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect only, no data modification.
	// Check if we're on the person edit screen.
	if ( 'post.php' === $pagenow && isset( $_GET['action'] ) && 'edit' === $_GET['action'] && isset( $_GET['post'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = absint( $_GET['post'] );
		if ( $post_id ) {
			$post_type = get_post_type( $post_id );
			if ( 'crm_person' === $post_type ) {
				wp_safe_redirect( admin_url( 'admin.php?page=formapress-crm-edit-person&id=' . $post_id ) );
				exit;
			}
		}
	}

	// Also check via $typenow for cases where post type is set but post ID might not be loaded yet.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Redirect only, no data modification.
	if ( 'post.php' === $pagenow && 'crm_person' === $typenow && isset( $_GET['action'] ) && 'edit' === $_GET['action'] ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( $post_id ) {
			wp_safe_redirect( admin_url( 'admin.php?page=formapress-crm-edit-person&id=' . $post_id ) );
			exit;
		}
	}

	// Redirect new person screen.
	if ( 'post-new.php' === $pagenow && 'crm_person' === $typenow ) {
		wp_safe_redirect( admin_url( 'admin.php?page=formapress-crm-edit-person' ) );
		exit;
	}
}
add_action( 'admin_init', 'formapress_crm_redirect_person_editor' );

/**
 * Register custom person editor page.
 */
function formapress_crm_register_person_editor() {
	add_submenu_page(
		null, // Hidden from menu (accessed via redirect).
		'Modifier la personne',
		'Modifier la personne',
		'edit_posts',
		'formapress-crm-edit-person',
		'formapress_crm_render_person_editor'
	);
}
add_action( 'admin_menu', 'formapress_crm_register_person_editor' );

/**
 * Enqueue scripts for person editor.
 */
function formapress_crm_enqueue_person_editor_scripts( $hook ) {
	if ( 'admin_page_formapress-crm-edit-person' !== $hook ) {
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

	// Enqueue custom person editor script.
	wp_enqueue_script(
		'formapress-person-editor',
		plugins_url( '../assets/js/person-editor.js', __FILE__ ),
		array( 'jquery', 'select2' ),
		filemtime( FORMAPRESS_CRM_PLUGIN_DIR . 'assets/js/person-editor.js' ),
		true
	);

	// Enqueue custom person editor styles.
	wp_enqueue_style(
		'formapress-person-editor',
		plugins_url( '../assets/css/person-editor-styles.css', __FILE__ ),
		array(),
		filemtime( FORMAPRESS_CRM_PLUGIN_DIR . 'assets/css/person-editor-styles.css' )
	);

	// Localize script with data.
	wp_localize_script(
		'formapress-person-editor',
		'formapressPersonEditor',
		array(
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'formapress_person_editor' ),
			'post_id'    => isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0,
			'ajax_delay' => 300, // Debounce delay for search.
		)
	);
}
add_action( 'admin_enqueue_scripts', 'formapress_crm_enqueue_person_editor_scripts' );


/**
 * Render the custom person editor page.
 */
function formapress_crm_render_person_editor() {
	$post_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	$is_new  = empty( $post_id );

	// If editing, verify post exists and is a person.
	if ( ! $is_new ) {
		$post = get_post( $post_id );
		if ( ! $post || 'crm_person' !== $post->post_type ) {
			wp_die( __( 'Personne non trouvée.', 'formapress-crm' ) );
		}
	}

	// Get person types to determine which schemas to load.
	$person_types = $is_new ? array() : wp_get_object_terms( $post_id, 'person_type', array( 'fields' => 'slugs' ) );
	if ( is_wp_error( $person_types ) ) {
		$person_types = array();
	}

	// Load core identity fields from the first available person type schema.
	// Priority order: trainee, instructor, company_contact, prospect, funder_contact.
	$schema_priority = array( 'trainee', 'instructor', 'company_contact', 'prospect', 'funder_contact' );
	$primary_schema  = null;
	$primary_type    = null;

	foreach ( $schema_priority as $type ) {
		if ( in_array( $type, $person_types, true ) || $is_new ) {
			if ( class_exists( 'ZForm_Attributes_Core' ) ) {
				$schema = ZForm_Attributes_Core::get_attributes_schema( $type );
				if ( ! empty( $schema ) ) {
					$primary_schema = $schema;
					$primary_type   = $type;
					break;
				}
			}
		}
	}

	// If no schema found, default to trainee schema for new persons.
	if ( $is_new && null === $primary_schema && class_exists( 'ZForm_Attributes_Core' ) ) {
		$primary_schema = ZForm_Attributes_Core::get_attributes_schema( 'trainee' );
		$primary_type   = 'trainee';
	}

	// Meta prefix for reading values.
	$meta_prefix = $primary_type ? 'crm_person_' . $primary_type . '_attributes_' : 'crm_person_trainee_attributes_';

	// Read core identity fields from attributes.
	$civilite = $is_new ? '' : get_post_meta( $post_id, $meta_prefix . 'civilite', true );
	$prenom   = $is_new ? '' : get_post_meta( $post_id, $meta_prefix . 'prenom', true );
	if ( empty( $prenom ) ) {
		$prenom = get_post_meta( $post_id, $meta_prefix . 'firstname', true );
	}
	$nom = $is_new ? '' : get_post_meta( $post_id, $meta_prefix . 'nom', true );
	if ( empty( $nom ) ) {
		$nom = get_post_meta( $post_id, $meta_prefix . 'name', true );
	}
	$email = $is_new ? '' : get_post_meta( $post_id, $meta_prefix . 'email', true );
	if ( empty( $email ) ) {
		$email = get_post_meta( $post_id, $meta_prefix . 'mail', true );
	}
	$telephone = $is_new ? '' : get_post_meta( $post_id, $meta_prefix . 'telephone', true );
	if ( empty( $telephone ) ) {
		$telephone = get_post_meta( $post_id, $meta_prefix . 'tel', true );
	}
	$adresse = $is_new ? '' : get_post_meta( $post_id, $meta_prefix . 'adresse', true );
	$cp      = $is_new ? '' : get_post_meta( $post_id, $meta_prefix . 'cp', true );
	$ville   = $is_new ? '' : get_post_meta( $post_id, $meta_prefix . 'ville', true );

	// Get civilité options from schema (fallback to trainee schema if not in primary).
	$civilite_options = array();
	$civilite_schema  = null;
	if ( $primary_schema && isset( $primary_schema['civilite'] ) && ! empty( $primary_schema['civilite']['options'] ) ) {
		$civilite_schema = $primary_schema;
	} elseif ( class_exists( 'ZForm_Attributes_Core' ) ) {
		// Fallback: use trainee schema for civilité options (most complete).
		$trainee_schema = ZForm_Attributes_Core::get_attributes_schema( 'trainee' );
		if ( isset( $trainee_schema['civilite'] ) && ! empty( $trainee_schema['civilite']['options'] ) ) {
			$civilite_schema = $trainee_schema;
		}
	}

	if ( $civilite_schema ) {
		$options_raw = $civilite_schema['civilite']['options'];
		// Parse string format: "value|label\nvalue2|label2".
		if ( is_string( $options_raw ) ) {
			$options_lines = array_filter( explode( "\n", $options_raw ) );
			foreach ( $options_lines as $option ) {
				$parts = explode( '|', trim( $option ) );
				$value = isset( $parts[0] ) ? trim( $parts[0] ) : '';
				$label = isset( $parts[1] ) ? trim( $parts[1] ) : $value;
				if ( ! empty( $value ) ) {
					$civilite_options[ $value ] = $label;
				}
			}
		} elseif ( is_array( $options_raw ) ) {
			$civilite_options = $options_raw;
		}
	}

	// Get person types (taxonomy terms).
	$person_types = $is_new ? array() : wp_get_object_terms( $post_id, 'person_type', array( 'fields' => 'slugs' ) );
	if ( is_wp_error( $person_types ) ) {
		$person_types = array();
	}

	// Get company associations (multiple companies possible).
	$company_ids = $is_new ? array() : get_post_meta( $post_id, '_crm_entreprise_ids', true );
	if ( ! is_array( $company_ids ) ) {
		$company_ids = ! empty( $company_ids ) ? array( $company_ids ) : array();
	}

	// Also check for trainee 'societe' attribute (legacy single company link).
	if ( ! $is_new ) {
		$trainee_societe = get_post_meta( $post_id, 'crm_person_trainee_attributes_societe', true );
		if ( ! empty( $trainee_societe ) && ! in_array( (int) $trainee_societe, $company_ids, true ) ) {
			$company_ids[] = (int) $trainee_societe;
		}
	}

	// Get company names for display.
	$companies = array();
	foreach ( $company_ids as $company_id ) {
		$company = get_post( $company_id );
		if ( $company && in_array( $company->post_type, array( 'zqpm_entreprise', 'crm_company' ), true ) ) {
			$companies[] = array(
				'id'   => $company->ID,
				'name' => $company->post_title,
			);
		}
	}

	// Available person types from taxonomy.
	$available_types = get_terms(
		array(
			'taxonomy'   => 'person_type',
			'hide_empty' => false,
		)
	);

	// Person type labels with colors.
	$type_config = array(
		'instructor'      => array(
			'label'   => __( 'Formateur', 'formapress-crm' ),
			'css_var' => '--zform_color_bleu',
			'icon'    => 'dashicons-welcome-learn-more',
		),
		'trainee'         => array(
			'label'   => __( 'Stagiaire', 'formapress-crm' ),
			'css_var' => '--zform_color_orange',
			'icon'    => 'dashicons-welcome-write-blog',
		),
		'company_contact' => array(
			'label'   => __( 'Référent entreprise', 'formapress-crm' ),
			'css_var' => '--zform_color_turquoise',
			'icon'    => 'dashicons-businessman',
		),
		'prospect'        => array(
			'label'   => __( 'Prospect', 'formapress-crm' ),
			'css_var' => '--zform_color_bleu2',
			'icon'    => 'dashicons-star-filled',
		),
		'funder_contact'  => array(
			'label'   => __( 'Financeur', 'formapress-crm' ),
			'css_var' => '--zform_gray',
			'icon'    => 'dashicons-money-alt',
		),
	);

	// Build title for display.
	$display_title = $is_new ? __( 'Nouvelle personne', 'formapress-crm' ) : ( $prenom . ' ' . strtoupper( $nom ) );
	if ( empty( trim( $display_title ) ) && ! $is_new ) {
		$display_title = $email ?: __( 'Personne sans nom', 'formapress-crm' );
	}

	?>
	<div class="wrap formapress-person-editor">
		<?php if ( ! $is_new ) : ?>
			<!-- Prominent Header Banner -->
			<div class="person-header-banner">
				<div class="banner-content">
					<div class="banner-title-section">
						<span class="banner-icon dashicons dashicons-admin-users"></span>
						<h1 class="banner-title"><?php echo esc_html( $display_title ); ?></h1>
					</div>
					<div class="banner-meta">
						<?php if ( ! empty( $person_types ) ) : ?>
							<div class="banner-meta-item">
								<?php foreach ( $person_types as $type_slug ) : ?>
									<?php if ( isset( $type_config[ $type_slug ] ) ) : ?>
										<span class="type-badge" data-type="<?php echo esc_attr( $type_slug ); ?>">
											<span class="dashicons <?php echo esc_attr( $type_config[ $type_slug ]['icon'] ); ?>"></span>
											<?php echo esc_html( $type_config[ $type_slug ]['label'] ); ?>
										</span>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<?php if ( $email ) : ?>
							<div class="banner-meta-item">
								<span class="dashicons dashicons-email"></span>
								<?php echo esc_html( $email ); ?>
							</div>
						<?php endif; ?>
						<?php if ( $telephone ) : ?>
							<div class="banner-meta-item">
								<span class="dashicons dashicons-phone"></span>
								<?php echo esc_html( $telephone ); ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php else : ?>
			<h1><?php esc_html_e( 'Nouvelle personne', 'formapress-crm' ); ?></h1>
		<?php endif; ?>

		<?php
		// Show success message if updated.
		if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Personne enregistrée avec succès.', 'formapress-crm' ); ?></p>
			</div>
			<?php
		}
		?>

		<form id="person-editor-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="formapress_save_person">
			<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
			<?php wp_nonce_field( 'formapress_save_person', 'formapress_person_nonce' ); ?>

			<div class="person-editor-container">
				<div class="person-editor-main">
					<!-- Core Identity Section -->
					<div class="editor-section">
						<h3 class="editor-section-title"><?php esc_html_e( 'Identité', 'formapress-crm' ); ?></h3>

						<div class="editor-field-row">
							<div class="editor-field" style="flex: 0 0 150px;">
								<label for="person-civilite" class="editor-label-small">
									<?php esc_html_e( 'Civilité', 'formapress-crm' ); ?>
								</label>
								<select id="person-civilite" name="person_civilite" class="editor-select">
									<option value=""><?php esc_html_e( '--', 'formapress-crm' ); ?></option>
									<?php foreach ( $civilite_options as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $civilite, $value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="editor-field" style="flex: 1;">
								<label for="person-prenom" class="editor-label-small required">
									<?php esc_html_e( 'Prénom', 'formapress-crm' ); ?>
								</label>
								<input
									type="text"
									id="person-prenom"
									name="person_prenom"
									class="editor-input"
									value="<?php echo esc_attr( $prenom ); ?>"
									required
								>
							</div>

							<div class="editor-field" style="flex: 1;">
								<label for="person-nom" class="editor-label-small required">
									<?php esc_html_e( 'Nom', 'formapress-crm' ); ?>
								</label>
								<input
									type="text"
									id="person-nom"
									name="person_nom"
									class="editor-input"
									value="<?php echo esc_attr( $nom ); ?>"
									required
								>
							</div>
						</div>

						<div class="editor-field-row">
							<div class="editor-field" style="flex: 1;">
								<label for="person-email" class="editor-label-small required">
									<?php esc_html_e( 'Email', 'formapress-crm' ); ?>
								</label>
								<input
									type="email"
									id="person-email"
									name="person_email"
									class="editor-input"
									value="<?php echo esc_attr( $email ); ?>"
									required
								>
							</div>

							<div class="editor-field" style="flex: 1;">
								<label for="person-telephone" class="editor-label-small">
									<?php esc_html_e( 'Téléphone', 'formapress-crm' ); ?>
								</label>
								<input
									type="tel"
									id="person-telephone"
									name="person_telephone"
									class="editor-input"
									value="<?php echo esc_attr( $telephone ); ?>"
								>
							</div>
						</div>
					</div>

					<!-- Address Section -->
					<div class="editor-section">
						<h3 class="editor-section-title"><?php esc_html_e( 'Adresse', 'formapress-crm' ); ?></h3>

						<div class="editor-field">
							<label for="person-adresse" class="editor-label-small">
								<?php esc_html_e( 'Adresse', 'formapress-crm' ); ?>
							</label>
							<input
								type="text"
								id="person-adresse"
								name="person_adresse"
								class="editor-input"
								value="<?php echo esc_attr( $adresse ); ?>"
							>
						</div>

						<div class="editor-field-row">
							<div class="editor-field" style="flex: 0 0 150px;">
								<label for="person-cp" class="editor-label-small">
									<?php esc_html_e( 'Code postal', 'formapress-crm' ); ?>
								</label>
								<input
									type="text"
									id="person-cp"
									name="person_cp"
									class="editor-input"
									value="<?php echo esc_attr( $cp ); ?>"
								>
							</div>

							<div class="editor-field" style="flex: 1;">
								<label for="person-ville" class="editor-label-small">
									<?php esc_html_e( 'Ville', 'formapress-crm' ); ?>
								</label>
								<input
									type="text"
									id="person-ville"
									name="person_ville"
									class="editor-input"
									value="<?php echo esc_attr( $ville ); ?>"
								>
							</div>
						</div>
					</div>

					<!-- Dynamic Attributes by Person Type -->
					<?php if ( ! $is_new && ! empty( $person_types ) ) : ?>
						<?php foreach ( $person_types as $type_slug ) : ?>
							<?php
							// Get attributes schema for this type using ZForm_Attributes_Core.
							if ( class_exists( 'ZForm_Attributes_Core' ) ) {
								$attributes_schema = ZForm_Attributes_Core::get_attributes_schema( $type_slug );

								if ( ! empty( $attributes_schema ) ) :
									// Build meta prefix for reading values.
									$meta_prefix = 'crm_person_' . $type_slug . '_attributes_';
									?>
									<div class="editor-section">
										<h3 class="editor-section-title">
											<?php
											if ( isset( $type_config[ $type_slug ] ) ) {
												echo '<span class="dashicons ' . esc_attr( $type_config[ $type_slug ]['icon'] ) . '"></span> ';
											}
											echo esc_html( sprintf( __( 'Attributs %s', 'formapress-crm' ), $type_config[ $type_slug ]['label'] ?? $type_slug ) );
											?>
										</h3>
										<?php
										// Core fields that are already displayed in Identity section - skip them in attributes.
										$core_fields = array( 'civilite', 'name', 'nom', 'firstname', 'prenom', 'mail', 'email', 'telephone', 'tel', 'adresse', 'cp', 'ville', 'societe' );

										foreach ( $attributes_schema as $field_slug => $field_config ) {
											// Skip core fields already displayed in Identity section.
											if ( in_array( $field_slug, $core_fields, true ) ) {
												continue;
											}

											$value = get_post_meta( $post_id, $meta_prefix . $field_slug, true );
											formapress_crm_render_person_attribute_field( $type_slug, $field_slug, $field_config, $value );
										}
										?>
									</div>
									<?php
								endif;
							}
							?>
						<?php endforeach; ?>
					<?php endif; ?>

					<!-- Activity Timeline Placeholder -->
					<?php if ( ! $is_new ) : ?>
						<div class="editor-section">
							<h3 class="editor-section-title"><?php esc_html_e( '📋 Historique des activités', 'formapress-crm' ); ?></h3>
							<div class="timeline-empty">
								<span class="dashicons dashicons-info"></span>
								<p><?php esc_html_e( 'L\'historique des activités sera disponible prochainement.', 'formapress-crm' ); ?></p>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<!-- Sidebar -->
				<div class="person-editor-sidebar">
					<!-- Save Actions -->
					<div class="editor-panel">
						<div class="editor-panel-actions">
							<button type="submit" class="button button-primary button-large" id="save-person">
								<?php echo $is_new ? __( 'Créer la personne', 'formapress-crm' ) : __( 'Enregistrer', 'formapress-crm' ); ?>
							</button>
							<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=crm_person' ) ); ?>" class="button button-secondary button-large">
								<?php esc_html_e( 'Annuler', 'formapress-crm' ); ?>
							</a>
						</div>
					</div>

					<!-- Person Types -->
					<div class="editor-panel">
						<h3 class="editor-panel-title"><?php esc_html_e( 'Types de personne', 'formapress-crm' ); ?></h3>
						<p class="editor-help-text" style="margin-bottom: 10px; font-size: 12px;">
							<?php esc_html_e( 'Cochez tous les rôles de cette personne dans votre système.', 'formapress-crm' ); ?>
						</p>
						<?php if ( ! empty( $available_types ) ) : ?>
							<?php foreach ( $available_types as $term ) : ?>
								<?php
								$term_config = $type_config[ $term->slug ] ?? array(
									'label' => $term->name,
									'icon'  => 'dashicons-admin-generic',
								);
								?>
								<label class="editor-checkbox person-type-checkbox">
									<input
										type="checkbox"
										name="person_types[]"
										value="<?php echo esc_attr( $term->slug ); ?>"
										<?php checked( in_array( $term->slug, $person_types, true ) ); ?>
									>
									<span class="dashicons <?php echo esc_attr( $term_config['icon'] ); ?>"></span>
									<?php echo esc_html( $term_config['label'] ); ?>
								</label>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

					<!-- Associated Companies -->
					<div class="editor-panel">
						<h3 class="editor-panel-title"><?php esc_html_e( 'Entreprises associées', 'formapress-crm' ); ?></h3>
						<p class="editor-help-text" style="margin-bottom: 10px; font-size: 12px;">
							<?php esc_html_e( 'Recherchez et sélectionnez une ou plusieurs entreprises.', 'formapress-crm' ); ?>
						</p>
						<div class="editor-field">
							<select name="associated_company_ids[]" id="associated-companies" class="crm-entity-select" data-entity-type="company" multiple style="width: 100%;">
								<?php foreach ( $companies as $company ) : ?>
									<option value="<?php echo esc_attr( $company['id'] ); ?>" selected>
										<?php echo esc_html( $company['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>
			</div>
		</form>
	</div>
	<?php
}

/**
 * Render a person attribute field.
 *
 * @param string $type_slug    Person type slug (instructor, trainee, etc.).
 * @param string $field_slug   Field slug.
 * @param array  $field_config Field configuration.
 * @param mixed  $value        Current value.
 */
function formapress_crm_render_person_attribute_field( $type_slug, $field_slug, $field_config, $value ) {
	$field_name  = 'person_attr_' . $type_slug . '_' . $field_slug;
	$field_label = $field_config['label'] ?? $field_config['name'] ?? ucfirst( str_replace( array( '-', '_' ), ' ', $field_slug ) );
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
 * Handle person save.
 */
function formapress_crm_handle_save_person() {
	// Verify nonce.
	if ( ! isset( $_POST['formapress_person_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['formapress_person_nonce'] ) ), 'formapress_save_person' ) ) {
		wp_die( __( 'Vérification de sécurité échouée.', 'formapress-crm' ) );
	}

	// Check permissions.
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( __( 'Permission refusée.', 'formapress-crm' ) );
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	$is_new  = empty( $post_id );

	// Get form data.
	$prenom = isset( $_POST['person_prenom'] ) ? sanitize_text_field( wp_unslash( $_POST['person_prenom'] ) ) : '';
	$nom    = isset( $_POST['person_nom'] ) ? sanitize_text_field( wp_unslash( $_POST['person_nom'] ) ) : '';

	// Build post title: "Prénom NOM".
	$post_title = $prenom . ' ' . strtoupper( $nom );

	// Prepare post data.
	$post_data = array(
		'post_title'  => $post_title,
		'post_type'   => 'crm_person',
		'post_status' => 'publish',
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
		wp_die( __( 'Erreur lors de l\'enregistrement de la personne.', 'formapress-crm' ) );
	}

	// Save person types (taxonomy).
	$person_types = isset( $_POST['person_types'] ) && is_array( $_POST['person_types'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['person_types'] ) ) : array();

	// If no person types selected, default to 'trainee'.
	if ( empty( $person_types ) ) {
		$person_types = array( 'trainee' );
	}

	wp_set_object_terms( $post_id, $person_types, 'person_type' );

	// Save company associations (array of IDs).
	$company_ids = isset( $_POST['associated_company_ids'] ) && is_array( $_POST['associated_company_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['associated_company_ids'] ) ) : array();
	update_post_meta( $post_id, '_crm_entreprise_ids', $company_ids );

	// Gather core identity field values from POST.
	$core_identity = array(
		'civilite'  => isset( $_POST['person_civilite'] ) ? sanitize_text_field( wp_unslash( $_POST['person_civilite'] ) ) : '',
		'prenom'    => isset( $_POST['person_prenom'] ) ? sanitize_text_field( wp_unslash( $_POST['person_prenom'] ) ) : '',
		'nom'       => isset( $_POST['person_nom'] ) ? sanitize_text_field( wp_unslash( $_POST['person_nom'] ) ) : '',
		'email'     => isset( $_POST['person_email'] ) ? sanitize_text_field( wp_unslash( $_POST['person_email'] ) ) : '',
		'telephone' => isset( $_POST['person_telephone'] ) ? sanitize_text_field( wp_unslash( $_POST['person_telephone'] ) ) : '',
		'adresse'   => isset( $_POST['person_adresse'] ) ? sanitize_text_field( wp_unslash( $_POST['person_adresse'] ) ) : '',
		'cp'        => isset( $_POST['person_cp'] ) ? sanitize_text_field( wp_unslash( $_POST['person_cp'] ) ) : '',
		'ville'     => isset( $_POST['person_ville'] ) ? sanitize_text_field( wp_unslash( $_POST['person_ville'] ) ) : '',
	);

	// Core field variants (different schemas use different slugs).
	$field_variants = array(
		'prenom'    => array( 'prenom', 'firstname' ),
		'nom'       => array( 'nom', 'name' ),
		'email'     => array( 'email', 'mail' ),
		'telephone' => array( 'telephone', 'tel' ),
	);

	// Save dynamic attributes for each person type.
	foreach ( $person_types as $type_slug ) {
		if ( class_exists( 'ZForm_Attributes_Core' ) ) {
			$attributes_schema = ZForm_Attributes_Core::get_attributes_schema( $type_slug );
			$meta_prefix       = 'crm_person_' . $type_slug . '_attributes_';

			foreach ( $attributes_schema as $field_slug => $field_config ) {
				$post_key = 'person_attr_' . $type_slug . '_' . $field_slug;
				$meta_key = $meta_prefix . $field_slug;

				// Check if this is a core identity field - sync across all types.
				$value = null;
				foreach ( $core_identity as $core_field => $core_value ) {
					// Check main slug and variants.
					if ( $field_slug === $core_field ) {
						$value = $core_value;
						break;
					} elseif ( isset( $field_variants[ $core_field ] ) && in_array( $field_slug, $field_variants[ $core_field ], true ) ) {
						$value = $core_value;
						break;
					}
				}

				if ( null !== $value ) {
					// Save core field value (synced across all person types).
					update_post_meta( $post_id, $meta_key, $value );
				} elseif ( isset( $_POST[ $post_key ] ) ) {
					// Regular type-specific attribute field.
					$value = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
					update_post_meta( $post_id, $meta_key, $value );
				}
			}
		}
	}

	// Redirect back to editor.
	$redirect_url = add_query_arg(
		array(
			'page'    => 'formapress-crm-edit-person',
			'id'      => $post_id,
			'updated' => '1',
		),
		admin_url( 'admin.php' )
	);

	wp_safe_redirect( $redirect_url );
	exit;
}
add_action( 'admin_post_formapress_save_person', 'formapress_crm_handle_save_person' );

/**
 * AJAX handler for searching companies.
 *
 * Reuses the same AJAX endpoint as opportunities, but filtered for companies.
 */
function formapress_crm_ajax_search_companies_for_person() {
	check_ajax_referer( 'formapress_person_editor', 'nonce' );

	$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

	// Search both v1 (zqpm_entreprise) and v2 (crm_company) post types.
	$query = new WP_Query(
		array(
			'post_type'      => array( 'zqpm_entreprise', 'crm_company' ),
			's'              => $search,
			'posts_per_page' => 20,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		)
	);

	$results = array();
	foreach ( $query->posts as $post ) {
		$results[] = array(
			'id'   => $post->ID,
			'text' => $post->post_title,
		);
	}

	wp_send_json( array( 'results' => $results ) );
}
add_action( 'wp_ajax_formapress_search_companies_for_person', 'formapress_crm_ajax_search_companies_for_person' );
