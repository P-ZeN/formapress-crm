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

	add_menu_page(
		__( 'CRM', 'formapress-crm' ),
		__( 'CRM', 'formapress-crm' ),
		'manage_options', // Capability required.
		'formapress-crm-dashboard',
		'formapress_crm_dashboard_page_html',
		$icon,
		24
	);

	add_submenu_page(
		'formapress-crm-dashboard',
		__( 'Sales Pipeline', 'formapress-crm' ),
		__( 'Pipeline (Kanban)', 'formapress-crm' ),
		'edit_posts',
		'formapress-crm-pipeline',
		'formapress_crm_pipeline_page_html'
	);

	add_submenu_page(
		'formapress-crm-dashboard',
		__( 'Migration Tools', 'formapress-crm' ),
		__( 'Migration Tools', 'formapress-crm' ),
		'manage_options',
		'formapress-crm-migration',
		'formapress_crm_migration_page_html'
	);

	// Add other submenus for settings, reports etc. later.
}
add_action( 'admin_menu', 'formapress_crm_admin_menu' );

/**
 * Displays the HTML for the CRM Dashboard page.
 */
function formapress_crm_dashboard_page_html() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Formapress CRM Dashboard', 'formapress-crm' ); ?></h1>
		<p><?php esc_html_e( 'Welcome to the Formapress CRM. Overview and reports will be available here.', 'formapress-crm' ); ?></p>
	</div>
	<?php
}

/**
 * Displays the Kanban pipeline board for opportunities.
 */
function formapress_crm_pipeline_page_html() {
	// Pipeline stages matching cpt-opportunity.php.
	$stages = array(
		'new'         => __( 'New Lead', 'formapress-crm' ),
		'qualified'   => __( 'Qualified', 'formapress-crm' ),
		'proposal'    => __( 'Proposal Sent', 'formapress-crm' ),
		'negotiation' => __( 'Negotiation', 'formapress-crm' ),
		'won'         => __( 'Won', 'formapress-crm' ),
		'lost'        => __( 'Lost', 'formapress-crm' ),
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
		<h1><?php esc_html_e( 'Sales Pipeline', 'formapress-crm' ); ?></h1>
		
		<div class="crm-pipeline-actions" style="margin: 20px 0;">
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=crm_opportunity' ) ); ?>" class="button button-primary">
				<?php esc_html_e( '+ New Opportunity', 'formapress-crm' ); ?>
			</a>
		</div>

		<div class="crm-kanban-board">
			<?php foreach ( $stages as $stage_key => $stage_label ) : ?>
				<div class="kanban-column" data-stage="<?php echo esc_attr( $stage_key ); ?>">
					<div class="kanban-column-header">
						<h3><?php echo esc_html( $stage_label ); ?></h3>
						<span class="kanban-count"><?php echo count( $opportunities_by_stage[ $stage_key ] ); ?></span>
					</div>
					<div class="kanban-cards" data-stage="<?php echo esc_attr( $stage_key ); ?>">
						<?php
						if ( ! empty( $opportunities_by_stage[ $stage_key ] ) ) :
							foreach ( $opportunities_by_stage[ $stage_key ] as $opp ) :
								$value       = get_post_meta( $opp->ID, '_crm_opportunity_value', true );
								$close_date  = get_post_meta( $opp->ID, '_crm_opportunity_close_date', true );
								$person_id   = get_post_meta( $opp->ID, '_crm_associated_person_id', true );
								$company_id  = get_post_meta( $opp->ID, '_crm_associated_company_id', true );
								$person_name = $person_id ? get_the_title( $person_id ) : '';
								?>
								<div class="kanban-card" data-opportunity-id="<?php echo esc_attr( $opp->ID ); ?>" draggable="true">
									<div class="kanban-card-header">
										<h4>
											<a href="<?php echo esc_url( get_edit_post_link( $opp->ID ) ); ?>">
												<?php echo esc_html( $opp->post_title ); ?>
											</a>
										</h4>
									</div>
									<div class="kanban-card-body">
										<?php if ( $person_name ) : ?>
											<p class="kanban-card-contact">
												<span class="dashicons dashicons-admin-users"></span>
												<?php echo esc_html( $person_name ); ?>
											</p>
										<?php endif; ?>
										<?php if ( $value ) : ?>
											<p class="kanban-card-value">
												<strong><?php echo number_format( (float) $value, 2 ); ?> €</strong>
											</p>
										<?php endif; ?>
										<?php if ( $close_date ) : ?>
											<p class="kanban-card-date">
												<span class="dashicons dashicons-calendar-alt"></span>
												<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $close_date ) ) ); ?>
											</p>
										<?php endif; ?>
									</div>
								</div>
								<?php
							endforeach;
						else :
							?>
							<p class="kanban-empty"><?php esc_html_e( 'No opportunities in this stage', 'formapress-crm' ); ?></p>
							<?php
						endif;
						?>
					</div>
				</div>
			<?php endforeach; ?>
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
		wp_send_json_error( array( 'message' => __( 'Permission denied', 'formapress-crm' ) ) );
	}

	$opportunity_id = isset( $_POST['opportunity_id'] ) ? intval( $_POST['opportunity_id'] ) : 0;
	$new_stage      = isset( $_POST['stage'] ) ? sanitize_text_field( wp_unslash( $_POST['stage'] ) ) : '';

	if ( ! $opportunity_id || ! $new_stage ) {
		wp_send_json_error( array( 'message' => __( 'Invalid data', 'formapress-crm' ) ) );
	}

	// Validate stage.
	$valid_stages = array( 'new', 'qualified', 'proposal', 'negotiation', 'won', 'lost' );
	if ( ! in_array( $new_stage, $valid_stages, true ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid stage', 'formapress-crm' ) ) );
	}

	// Update the opportunity stage.
	update_post_meta( $opportunity_id, '_crm_opportunity_stage', $new_stage );

	wp_send_json_success(
		array(
			'message' => __( 'Stage updated successfully', 'formapress-crm' ),
			'stage'   => $new_stage,
		)
	);
}
add_action( 'wp_ajax_formapress_crm_update_opportunity_stage', 'formapress_crm_update_opportunity_stage' );
