<?php
/**
 * Formapress CRM Admin Pages
 *
 * @package FormapressCRM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Adds the Formapress CRM admin menu and submenus.
 */
function formapress_crm_admin_menu() {

	$icon = zform_get_plugin_icon();

	// Main CRM menu - points to Pipeline (Kanban view).
	add_menu_page(
		'CRM',
		'CRM',
		'edit_posts',
		'formapress-crm-pipeline',
		'formapress_crm_pipeline_page_html',
		$icon,
		24
	);

	// Dashboard submenu.
	add_submenu_page(
		'formapress-crm-pipeline',
		'Tableau de bord',
		'Tableau de bord',
		'manage_options',
		'formapress-crm-dashboard',
		'formapress_crm_dashboard_page_html'
	);

	// Pipeline submenu (will show as first item).
	add_submenu_page(
		'formapress-crm-pipeline',
		'Pipeline commercial',
		'Pipeline commercial',
		'edit_posts',
		'formapress-crm-pipeline',
		'formapress_crm_pipeline_page_html'
	);

	// Migration Tools submenu.
	add_submenu_page(
		'formapress-crm-pipeline',
		'Outils de migration',
		'Outils de migration',
		'manage_options',
		'formapress-crm-migration',
		'formapress_crm_migration_page_html'
	);

	// Note: Attributes configuration is in unified Settings page (options-general.php -> Forma-Press -> CRM tab)
	// Note: Opportunities and Invoices CPTs automatically appear here
	// because they have 'show_in_menu' => 'formapress-crm-pipeline'.
}
add_action( 'admin_menu', 'formapress_crm_admin_menu' );

/**
 * Displays the HTML for the CRM Dashboard page.
 */
function formapress_crm_dashboard_page_html() {
	?>
	<div class="wrap">
		<h1>Tableau de bord CRM</h1>
		<p>Bienvenue dans le CRM Formapress. Les statistiques et rapports seront disponibles ici.</p>
	</div>
	<?php
}

/**
 * Displays the Kanban pipeline board for opportunities.
 */
function formapress_crm_pipeline_page_html() {
	// Pipeline stages in French.
	$stages = array(
		'new'         => 'Nouveau prospect',
		'qualified'   => 'Qualifié',
		'proposal'    => 'Proposition envoyée',
		'negotiation' => 'En négociation',
		'won'         => 'Gagné',
		'lost'        => 'Perdu',
	);

	// Get all opportunities.
	$opportunities = get_posts(
		array(
			'post_type'      => 'crm_opportunity',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	// Group opportunities by stage.
	$opportunities_by_stage = array();
	foreach ( $stages as $stage_key => $stage_label ) {
		$opportunities_by_stage[ $stage_key ] = array();
	}

	foreach ( $opportunities as $opp ) {
		$stage = get_post_meta( $opp->ID, '_crm_opportunity_stage', true );
		if ( empty( $stage ) ) {
			$stage = 'new'; // Default.
		}
		if ( isset( $opportunities_by_stage[ $stage ] ) ) {
			$opportunities_by_stage[ $stage ][] = $opp;
		}
	}

	?>
	<div class="wrap">
		<h1>Pipeline commercial</h1>

		<div class="crm-pipeline-actions" style="margin: 20px 0;">
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=crm_opportunity' ) ); ?>" class="button button-primary">
				+ Nouvelle opportunité
			</a>
			<button class="button crm-quick-add-trigger">
				+ Ajouter un contact
			</button>
		</div>

		<div class="crm-kanban-board">
			<?php foreach ( $stages as $stage_key => $stage_label ) : ?>
				<div class="kanban-column" data-stage="<?php echo esc_attr( $stage_key ); ?>">
					<div class="column-header">
						<div class="column-title"><?php echo esc_html( $stage_label ); ?></div>
						<span class="column-count"><?php echo count( $opportunities_by_stage[ $stage_key ] ); ?></span>
					</div>
					<div class="kanban-cards" data-stage="<?php echo esc_attr( $stage_key ); ?>">
						<?php
						if ( ! empty( $opportunities_by_stage[ $stage_key ] ) ) :
							foreach ( $opportunities_by_stage[ $stage_key ] as $opp ) :
								$value        = get_post_meta( $opp->ID, '_crm_opportunity_value', true );
								$close_date   = get_post_meta( $opp->ID, '_crm_opportunity_close_date', true );
								$person_id    = get_post_meta( $opp->ID, '_crm_associated_person_id', true );
								$company_id   = get_post_meta( $opp->ID, '_crm_associated_company_id', true );
								$person_name  = $person_id ? get_the_title( $person_id ) : '';
								$company_name = $company_id ? get_the_title( $company_id ) : '';
								?>
								<div class="kanban-card" data-opportunity-id="<?php echo esc_attr( $opp->ID ); ?>" draggable="true">
									<div class="card-title">
										<a href="<?php echo esc_url( get_edit_post_link( $opp->ID ) ); ?>">
											<?php echo esc_html( $opp->post_title ); ?>
										</a>
									</div>
									<?php if ( $company_name ) : ?>
										<div class="card-company">
											<span class="dashicons dashicons-building"></span>
											<?php echo esc_html( $company_name ); ?>
										</div>
									<?php endif; ?>
									<?php if ( $person_name ) : ?>
										<div class="card-contact">
											<span class="dashicons dashicons-admin-users"></span>
											<?php echo esc_html( $person_name ); ?>
										</div>
									<?php endif; ?>
									<?php if ( $value ) : ?>
										<div class="card-value">
											<?php echo number_format( (float) $value, 2, ',', ' ' ); ?> €
										</div>
									<?php endif; ?>
									<?php if ( $close_date ) : ?>
										<div class="card-footer">
											<div class="card-date">
												<span class="dashicons dashicons-calendar-alt"></span>
												<?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $close_date ) ) ); ?>
											</div>
										</div>
									<?php endif; ?>
								</div>
								<?php
							endforeach;
						else :
							?>
							<p class="kanban-empty">Aucune opportunité dans cette étape</p>
							<?php
						endif;
						?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Quick Add Contact Modal -->
		<div class="crm-quick-add-modal">
			<div class="modal-content">
				<div class="modal-header">
					<h2><?php esc_html_e( 'Quick Add Contact', 'formapress-crm' ); ?></h2>
					<button class="modal-close" aria-label="<?php esc_attr_e( 'Close', 'formapress-crm' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<form class="crm-quick-add-form">
					<?php wp_nonce_field( 'crm_quick_add_person', 'crm_quick_add_nonce' ); ?>
					<div class="modal-body">
						<div class="form-row">
							<div class="form-group">
								<label for="quick_add_first_name">
									<?php esc_html_e( 'First Name', 'formapress-crm' ); ?>
									<span class="required">*</span>
								</label>
								<input
									type="text"
									id="quick_add_first_name"
									name="person_first_name"
									required
									placeholder="<?php esc_attr_e( 'John', 'formapress-crm' ); ?>"
									autocomplete="off"
								/>
							</div>
							<div class="form-group">
								<label for="quick_add_last_name">
									<?php esc_html_e( 'Last Name', 'formapress-crm' ); ?>
									<span class="required">*</span>
								</label>
								<input
									type="text"
									id="quick_add_last_name"
									name="person_last_name"
									required
									placeholder="<?php esc_attr_e( 'Doe', 'formapress-crm' ); ?>"
									autocomplete="off"
								/>
							</div>
						</div>
						<div class="form-group">
							<label for="quick_add_email">
								<?php esc_html_e( 'Email', 'formapress-crm' ); ?>
								<span class="required">*</span>
							</label>
							<input
								type="email"
								id="quick_add_email"
								name="person_email"
								required
								placeholder="<?php esc_attr_e( 'john@example.com', 'formapress-crm' ); ?>"
								autocomplete="off"
							/>
						</div>
						<div class="form-group">
							<label for="quick_add_phone">
								<?php esc_html_e( 'Phone', 'formapress-crm' ); ?>
							</label>
							<input
								type="tel"
								id="quick_add_phone"
								name="person_phone"
								placeholder="<?php esc_attr_e( '+33 6 12 34 56 78', 'formapress-crm' ); ?>"
								autocomplete="off"
							/>
						</div>
						<div class="form-group">
							<label for="quick_add_job_title">
								<?php esc_html_e( 'Job Title', 'formapress-crm' ); ?>
							</label>
							<input
								type="text"
								id="quick_add_job_title"
								name="person_job_title"
								placeholder="<?php esc_attr_e( 'Training Manager', 'formapress-crm' ); ?>"
								autocomplete="off"
							/>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="button btn-cancel">
							<?php esc_html_e( 'Cancel', 'formapress-crm' ); ?>
						</button>
						<button type="submit" class="button button-primary btn-save">
							<?php esc_html_e( 'Save Contact', 'formapress-crm' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<?php
}


/**
 * Displays the HTML for the Migration Tools page.
 */
function formapress_crm_migration_page_html() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Formapress CRM - Migration Tools', 'formapress-crm' ); ?></h1>

		<div id="migration-status"></div>

		<h2><?php esc_html_e( 'Migrate zqpmReferent to crm_person', 'formapress-crm' ); ?></h2>
		<p>
			<?php esc_html_e( 'This tool will migrate existing \'zqpmReferent\' data (stored in zqpm_entreprise post meta) to new \'crm_person\' posts.', 'formapress-crm' ); ?>
			<br>
			<?php esc_html_e( 'It is recommended to backup your database before running this process.', 'formapress-crm' ); ?>
		</p>
		<p>
			<button id="run-referent-migration" class="button button-primary"><?php esc_html_e( 'Start Referent Migration', 'formapress-crm' ); ?></button>
		</p>
		<div id="referent-migration-progress" style="display:none;">
			<p><?php esc_html_e( 'Processing...', 'formapress-crm' ); ?></p>
			<div id="referent-migration-log"></div>
		</div>

	<h2><?php esc_html_e( 'Import Tools', 'formapress-crm' ); ?></h2>
	<p>
		<?php esc_html_e( 'Use the tools below to import instructors and trainees into the CRM.', 'formapress-crm' ); ?>
	</p>

	<button id="formapress-crm-import-instructors" class="button button-primary">
		<?php esc_html_e( 'Import Instructors', 'formapress-crm' ); ?>
	</button>
	<button id="formapress-crm-import-trainees" class="button button-secondary">
		<?php esc_html_e( 'Import Trainees', 'formapress-crm' ); ?>
	</button>

	<div id="formapress-crm-import-log" style="margin-top:2em;"></div>

	<h2 style="margin-top:3em;"><?php esc_html_e( 'Data Quality Tools', 'formapress-crm' ); ?></h2>
	<p>
		<?php esc_html_e( 'Re-import all registrations with complete field data. This updates existing persons with missing fields (firstname, lastname, address, city, postal code, company, message) and creates persons for any registrations not yet imported.', 'formapress-crm' ); ?>
	</p>
	<p><strong><?php esc_html_e( 'Note:', 'formapress-crm' ); ?></strong> <?php esc_html_e( 'This process will NOT overwrite existing data. It only fills in missing fields.', 'formapress-crm' ); ?></p>

	<button id="formapress-crm-reimport-registrations" class="button button-primary" style="background: #2271b1;">
		<?php esc_html_e( 'Re-import All Registrations (Fix Missing Fields)', 'formapress-crm' ); ?>
	</button>

	<div id="formapress-crm-reimport-log" style="margin-top:2em;"></div>

	<h3 style="margin-top:2em;"><?php esc_html_e( 'Company Association Sync', 'formapress-crm' ); ?></h3>
	<p>
		<?php esc_html_e( 'Fix company associations by matching company names from imported data to existing company posts. Creates new company posts for unknown company names.', 'formapress-crm' ); ?>
	</p>

	<button id="formapress-crm-sync-companies" class="button button-primary" style="background: #00a32a;">
		<?php esc_html_e( 'Sync Company Associations', 'formapress-crm' ); ?>
	</button>

	<div id="formapress-crm-sync-companies-log" style="margin-top:2em;"></div>
	</div>

	<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#run-referent-migration').on('click', function() {
				$(this).prop('disabled', true);
				$('#referent-migration-progress').show();
				$('#referent-migration-log').html(''); // Clear previous log.
				$('#migration-status').html('');

				var data = {
					'action': 'formapress_crm_migrate_referents',
					'_ajax_nonce': '<?php echo esc_js( wp_create_nonce( 'formapress_crm_migrate_referents_nonce' ) ); ?>'
				};

				$.post(ajaxurl, data, function(response) {
					if(response.success) {
						$('#migration-status').html('<div class="updated"><p>' + response.data.message + '</p></div>');
						$('#referent-migration-log').html(response.data.log.join("<br>"));
					} else {
						$('#migration-status').html('<div class="error"><p>' + response.data.message + '</p></div>');
						if(response.data.log) {
							$('#referent-migration-log').html(response.data.log.join("<br>"));
						}
					}
					$('#run-referent-migration').prop('disabled', false);
					$('#referent-migration-progress p').text('<?php esc_html_e( 'Process Complete.', 'formapress-crm' ); ?>');
				}).fail(function() {
					$('#migration-status').html('<div class="error"><p><?php esc_html_e( 'An error occurred during the AJAX request.', 'formapress-crm' ); ?></p></div>');
					$('#run-referent-migration').prop('disabled', false);
					$('#referent-migration-progress p').text('<?php esc_html_e( 'Process Failed.', 'formapress-crm' ); ?>');
				});
			});

			function showLog(log) {
				var html = '<ul>';
				for (var i = 0; i < log.length; i++) {
					html += '<li>' + log[i] + '</li>';
				}
				html += '</ul>';
				$('#formapress-crm-import-log').html(html);
			}

			$('#formapress-crm-import-instructors').on('click', function(e) {
				e.preventDefault();
				$('#formapress-crm-import-log').html('<em><?php echo esc_js( __( 'Importing instructors...', 'formapress-crm' ) ); ?></em>');
				$.post(ajaxurl, {
					action: 'formapress_crm_import_instructors',
					_ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'formapress_crm_import_instructors_nonce' ) ); ?>'
				}, function(response) {
					if (response.success) {
						showLog(response.data.log);
					} else {
						$('#formapress-crm-import-log').html('<span style="color:red;">' + (response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'An error occurred.', 'formapress-crm' ) ); ?>') + '</span>');
					}
				});
			});

			$('#formapress-crm-import-trainees').on('click', function(e) {
				e.preventDefault();
				$('#formapress-crm-import-log').html('<em><?php echo esc_js( __( 'Importing trainees...', 'formapress-crm' ) ); ?></em>');
				$.post(ajaxurl, {
					action: 'formapress_crm_import_trainees',
					_ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'formapress_crm_import_trainees_nonce' ) ); ?>'
				}, function(response) {
					if (response.success) {
						showLog(response.data.log);
					} else {
						$('#formapress-crm-import-log').html('<span style="color:red;">' + (response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'An error occurred.', 'formapress-crm' ) ); ?>') + '</span>');
					}
				});
			});

			$('#formapress-crm-reimport-registrations').on('click', function(e) {
				e.preventDefault();
				var $button = $(this);
				$button.prop('disabled', true).text('<?php echo esc_js( __( 'Processing... (may take 1-2 minutes)', 'formapress-crm' ) ); ?>');
				$('#formapress-crm-reimport-log').html('<em><?php echo esc_js( __( 'Re-importing registrations with all fields...', 'formapress-crm' ) ); ?></em>');
				$.post(ajaxurl, {
					action: 'formapress_crm_reimport_registrations',
					_ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'formapress_crm_reimport_registrations_nonce' ) ); ?>'
				}, function(response) {
					$button.prop('disabled', false).text('<?php echo esc_js( __( 'Re-import All Registrations (Fix Missing Fields)', 'formapress-crm' ) ); ?>');
					if (response.success) {
						var html = '<div class="notice notice-success"><p><strong>' + response.data.message + '</strong></p></div>';
						html += '<ul style="list-style: disc; margin-left: 2em;">';
						response.data.log.forEach(function(line) {
							html += '<li>' + line + '</li>';
						});
						html += '</ul>';
						$('#formapress-crm-reimport-log').html(html);
					} else {
						$('#formapress-crm-reimport-log').html('<div class="notice notice-error"><p>' + (response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'An error occurred.', 'formapress-crm' ) ); ?>') + '</p></div>');
					}
				}).fail(function() {
					$button.prop('disabled', false).text('<?php echo esc_js( __( 'Re-import All Registrations (Fix Missing Fields)', 'formapress-crm' ) ); ?>');
					$('#formapress-crm-reimport-log').html('<div class="notice notice-error"><p><?php echo esc_js( __( 'AJAX request failed. Please try again.', 'formapress-crm' ) ); ?></p></div>');
				});
			});

			// Sync company associations button
			$('#formapress-crm-sync-companies').on('click', function(e) {
				e.preventDefault();
				var $button = $(this);
				$button.prop('disabled', true).text('<?php echo esc_js( __( 'Processing...', 'formapress-crm' ) ); ?>');
				$('#formapress-crm-sync-companies-log').html('<em><?php echo esc_js( __( 'Syncing company associations...', 'formapress-crm' ) ); ?></em>');
				$.post(ajaxurl, {
					action: 'formapress_crm_sync_company_associations',
					_ajax_nonce: '<?php echo esc_js( wp_create_nonce( 'formapress_crm_sync_companies_nonce' ) ); ?>'
				}, function(response) {
					$button.prop('disabled', false).text('<?php echo esc_js( __( 'Sync Company Associations', 'formapress-crm' ) ); ?>');
					if (response.success) {
						var html = '<div class="notice notice-success"><p><strong><?php echo esc_js( __( 'Company Sync Complete!', 'formapress-crm' ) ); ?></strong></p>';
						html += '<p><?php echo esc_js( __( 'Fixed:', 'formapress-crm' ) ); ?> ' + response.data.fixed_count + '</p>';
						html += '<p><?php echo esc_js( __( 'Created Companies:', 'formapress-crm' ) ); ?> ' + response.data.created_count + '</p>';
						html += '<p><?php echo esc_js( __( 'Skipped:', 'formapress-crm' ) ); ?> ' + response.data.skipped_count + '</p>';
						html += '<p><strong><?php echo esc_js( __( 'Log:', 'formapress-crm' ) ); ?></strong></p>';
						html += '<ul style="list-style: disc; margin-left: 2em;">';
						response.data.log.forEach(function(line) {
							html += '<li>' + line + '</li>';
						});
						html += '</ul></div>';
						$('#formapress-crm-sync-companies-log').html(html);
					} else {
						$('#formapress-crm-sync-companies-log').html('<div class="notice notice-error"><p>' + (response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'An error occurred.', 'formapress-crm' ) ); ?>') + '</p></div>');
					}
				}).fail(function() {
					$button.prop('disabled', false).text('<?php echo esc_js( __( 'Sync Company Associations', 'formapress-crm' ) ); ?>');
					$('#formapress-crm-sync-companies-log').html('<div class="notice notice-error"><p><?php echo esc_js( __( 'AJAX request failed. Please try again.', 'formapress-crm' ) ); ?></p></div>');
				});
			});
		});
	</script>
	<?php
}

/**
 * AJAX handler for updating opportunity stage (Kanban drag-drop).
 */
function formapress_crm_update_opportunity_stage() {
	check_ajax_referer( 'formapress_crm_admin_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => 'Permission refusée' ) );
	}

	$opportunity_id = isset( $_POST['opportunity_id'] ) ? intval( $_POST['opportunity_id'] ) : 0;
	$new_stage      = isset( $_POST['stage'] ) ? sanitize_text_field( wp_unslash( $_POST['stage'] ) ) : '';

	if ( ! $opportunity_id || ! $new_stage ) {
		wp_send_json_error( array( 'message' => 'Données invalides' ) );
	}

	// Validate stage.
	$valid_stages = array( 'new', 'qualified', 'proposal', 'negotiation', 'won', 'lost' );
	if ( ! in_array( $new_stage, $valid_stages, true ) ) {
		wp_send_json_error( array( 'message' => 'Étape invalide' ) );
	}

	// Verify this is an opportunity post.
	if ( 'crm_opportunity' !== get_post_type( $opportunity_id ) ) {
		wp_send_json_error( array( 'message' => 'Type de post invalide' ) );
	}

	// Update the opportunity stage.
	update_post_meta( $opportunity_id, '_crm_opportunity_stage', $new_stage );

	wp_send_json_success(
		array(
			'message' => 'Étape mise à jour avec succès',
			'stage'   => $new_stage,
		)
	);
}
add_action( 'wp_ajax_formapress_crm_update_opportunity_stage', 'formapress_crm_update_opportunity_stage' );

/**
 * AJAX handler for quick-adding a contact (Person CPT).
 */
function formapress_crm_quick_add_person() {
	check_ajax_referer( 'formapress_crm_admin_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied', 'formapress-crm' ) ) );
	}

	$first_name       = isset( $_POST['person_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['person_first_name'] ) ) : '';
	$last_name        = isset( $_POST['person_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['person_last_name'] ) ) : '';
	$person_email     = isset( $_POST['person_email'] ) ? sanitize_email( wp_unslash( $_POST['person_email'] ) ) : '';
	$person_phone     = isset( $_POST['person_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['person_phone'] ) ) : '';
	$person_job_title = isset( $_POST['person_job_title'] ) ? sanitize_text_field( wp_unslash( $_POST['person_job_title'] ) ) : '';

	// Validate required fields.
	if ( empty( $first_name ) || empty( $last_name ) || empty( $person_email ) ) {
		wp_send_json_error( array( 'message' => __( 'First name, last name and email are required', 'formapress-crm' ) ) );
	}

	// Validate email format.
	if ( ! is_email( $person_email ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid email address', 'formapress-crm' ) ) );
	}

	// Check if person with this email already exists.
	$existing_person = get_posts(
		array(
			'post_type'   => 'crm_person',
			'meta_key'    => '_crm_email',
			'meta_value'  => $person_email,
			'numberposts' => 1,
		)
	);

	if ( ! empty( $existing_person ) ) {
		wp_send_json_error(
			array(
				'message'   => __( 'A contact with this email already exists', 'formapress-crm' ),
				'person_id' => $existing_person[0]->ID,
			)
		);
	}

	// Create the person post.
	$full_name = trim( $first_name . ' ' . $last_name );
	$person_id = wp_insert_post(
		array(
			'post_type'   => 'crm_person',
			'post_title'  => $full_name,
			'post_status' => 'publish',
		)
	);

	if ( is_wp_error( $person_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Error creating contact', 'formapress-crm' ) ) );
	}

	// Save meta fields.
	update_post_meta( $person_id, '_crm_first_name', $first_name );
	update_post_meta( $person_id, '_crm_last_name', $last_name );
	update_post_meta( $person_id, '_crm_email', $person_email );
	if ( ! empty( $person_phone ) ) {
		update_post_meta( $person_id, '_crm_phone', $person_phone );
	}
	if ( ! empty( $person_job_title ) ) {
		update_post_meta( $person_id, '_crm_job_title', $person_job_title );
	}

	wp_send_json_success(
		array(
			'message'   => __( 'Contact created successfully', 'formapress-crm' ),
			'person_id' => $person_id,
			'edit_url'  => get_edit_post_link( $person_id ),
		)
	);
}
add_action( 'wp_ajax_crm_quick_add', 'formapress_crm_quick_add_person' );

/**
 * Displays the HTML for the CRM Attributes page (tabbed interface).
 */
function formapress_crm_attributes_page_html() {
	// Check user capabilities.
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'formapress-crm' ) );
	}

	// Get active tab from URL or default to referent.
	$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'referent';
	$valid_tabs = array( 'referent', 'prospect', 'funder', 'company', 'opportunity' );
	if ( ! in_array( $active_tab, $valid_tabs, true ) ) {
		$active_tab = 'referent';
	}

	// Handle form submission.
	if ( isset( $_POST['submit'] ) && isset( $_POST['option_page'] ) ) {
		$option_page = sanitize_text_field( wp_unslash( $_POST['option_page'] ) );
		check_admin_referer( $option_page . '-options' );

		$option_name = '';
		switch ( $active_tab ) {
			case 'referent':
				$option_name = 'crm_person_referent_attributes';
				break;
			case 'prospect':
				$option_name = 'crm_person_prospect_attributes';
				break;
			case 'funder':
				$option_name = 'crm_person_funder_attributes';
				break;
			case 'company':
				$option_name = 'crm_company_attributes';
				break;
			case 'opportunity':
				$option_name = 'crm_opportunity_attributes';
				break;
		}

		if ( $option_name && isset( $_POST[ $option_name ] ) ) {
			// Sanitize and save the option.
			$raw_data  = wp_unslash( $_POST[ $option_name ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$new_value = formapress_crm_sanitize_attributes( $raw_data );
			update_option( $option_name, $new_value );
			add_settings_error( 'formapress_crm_attributes', 'settings_updated', __( 'Settings saved.', 'formapress-crm' ), 'updated' );
		}
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'CRM Attributes', 'formapress-crm' ); ?></h1>
		<p><?php esc_html_e( 'Manage dynamic attributes for different entity types. These fields appear in editors and are accessible via shortcodes.', 'formapress-crm' ); ?></p>

		<?php settings_errors( 'formapress_crm_attributes' ); ?>

		<h2 class="nav-tab-wrapper">
			<a href="?page=formapress-crm-attributes&tab=referent" class="nav-tab <?php echo 'referent' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Referents', 'formapress-crm' ); ?>
			</a>
			<a href="?page=formapress-crm-attributes&tab=prospect" class="nav-tab <?php echo 'prospect' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Prospects', 'formapress-crm' ); ?>
			</a>
			<a href="?page=formapress-crm-attributes&tab=funder" class="nav-tab <?php echo 'funder' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Funders', 'formapress-crm' ); ?>
			</a>
			<a href="?page=formapress-crm-attributes&tab=company" class="nav-tab <?php echo 'company' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Companies', 'formapress-crm' ); ?>
			</a>
			<a href="?page=formapress-crm-attributes&tab=opportunity" class="nav-tab <?php echo 'opportunity' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php esc_html_e( 'Opportunities', 'formapress-crm' ); ?>
			</a>
		</h2>

		<form method="post" action="">
			<?php
			// Output nonce for the current tab.
			$option_group = 'formapress_crm_attributes_' . $active_tab;
			settings_fields( $option_group );
			wp_nonce_field( $option_group . '-options' );
			?>
			<input type="hidden" name="option_page" value="<?php echo esc_attr( $option_group ); ?>" />

			<?php
			// Include the appropriate tab content file.
			switch ( $active_tab ) {
				case 'referent':
					require_once FORMAPRESS_CRM_PLUGIN_DIR . 'admin/options-referent-attributes.php';
					break;
				case 'prospect':
					require_once FORMAPRESS_CRM_PLUGIN_DIR . 'admin/options-prospect-attributes.php';
					break;
				case 'funder':
					require_once FORMAPRESS_CRM_PLUGIN_DIR . 'admin/options-funder-attributes.php';
					break;
				case 'company':
					require_once FORMAPRESS_CRM_PLUGIN_DIR . 'admin/options-company-attributes.php';
					break;
				case 'opportunity':
					require_once FORMAPRESS_CRM_PLUGIN_DIR . 'admin/options-opportunity-attributes.php';
					break;
			}
			?>
		</form>
	</div>
	<?php
}

/**
 * Sanitize attributes array - convert numeric keys to slugged keys.
 *
 * @param array $new_value Updated value.
 * @return array Sanitized array.
 */
function formapress_crm_sanitize_attributes( $new_value ) {
	$new_array = array();

	foreach ( $new_value as $key => $value ) {
		if ( ! empty( $value['name'] ) ) {
			if ( is_numeric( $key ) ) {
				// Convert numeric key to slugged key based on field name.
				$new_array[ sanitize_title( $value['name'] ) ] = $value;
			} else {
				$new_array[ $key ] = $value;
			}
		}
	}
	$new_value = $new_array;

	// Sort by order.
	uasort(
		$new_value,
		function ( $a, $b ) {
			$a_order = isset( $a['order'] ) ? $a['order'] : 0;
			$b_order = isset( $b['order'] ) ? $b['order'] : 0;
			return $a_order > $b_order;
		}
	);

	return $new_value;
}
