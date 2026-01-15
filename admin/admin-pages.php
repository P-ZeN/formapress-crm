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

	// Main CRM menu - points to Dashboard.
	add_menu_page(
		'CRM',
		'CRM',
		'edit_posts',
		'formapress-crm-dashboard',
		'formapress_crm_dashboard_page_html',
		$icon,
		24
	);

	// Dashboard submenu - must be added explicitly to show as first item.
	add_submenu_page(
		'formapress-crm-dashboard',
		'Tableau de bord',
		'Tableau de bord',
		'edit_posts',
		'formapress-crm-dashboard',
		'formapress_crm_dashboard_page_html',
		1 // Priority 1 to appear first.
	);

	// Migration Tools submenu.
	add_submenu_page(
		'formapress-crm-dashboard',
		'Outils de migration',
		'Outils de migration',
		'manage_options',
		'formapress-crm-migration',
		'formapress_crm_migration_page_html'
	);

	// Note: Attributes configuration is in unified Settings page (options-general.php -> Forma-Press -> CRM tab)
	// Note: Opportunities and Invoices CPTs automatically appear here
	// because they have 'show_in_menu' => 'formapress-crm-dashboard'.
}
add_action( 'admin_menu', 'formapress_crm_admin_menu' );

/**
 * Reorder CRM submenu items to ensure Dashboard appears first.
 */
function formapress_crm_reorder_submenu() {
	global $submenu;

	if ( ! isset( $submenu['formapress-crm-dashboard'] ) ) {
		return;
	}

	// Find and move dashboard to first position.
	$dashboard_item = null;
	$dashboard_key  = null;

	foreach ( $submenu['formapress-crm-dashboard'] as $key => $item ) {
		if ( $item[2] === 'formapress-crm-dashboard' ) {
			$dashboard_item = $item;
			$dashboard_key  = $key;
			break;
		}
	}

	if ( $dashboard_item && $dashboard_key !== 0 ) {
		// Remove dashboard from its current position.
		unset( $submenu['formapress-crm-dashboard'][ $dashboard_key ] );
		// Add it at the beginning.
		array_unshift( $submenu['formapress-crm-dashboard'], $dashboard_item );
	}
}
add_action( 'admin_menu', 'formapress_crm_reorder_submenu', 999 );

/**
 * Displays the HTML for the CRM Dashboard page.
 */
function formapress_crm_dashboard_page_html() {
	// Get opportunity stats.
	$stages = formapress_crm_get_opportunity_stages();
	$stats  = array();

	foreach ( $stages as $stage_key => $stage_label ) {
		$count       = wp_count_posts( 'crm_opportunity' );
		$stage_count = 0;

		$opportunities       = get_posts(
			array(
				'post_type'      => 'crm_opportunity',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					array(
						'key'     => '_crm_opportunity_stage',
						'value'   => $stage_key,
						'compare' => '=',
					),
				),
			)
		);
		$stats[ $stage_key ] = count( $opportunities );
	}

	// Calculate total opportunity value.
	$all_opportunities = get_posts(
		array(
			'post_type'      => 'crm_opportunity',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		)
	);

	$total_value = 0;
	$won_value   = 0;

	foreach ( $all_opportunities as $opp ) {
		$value = get_post_meta( $opp->ID, '_crm_opportunity_value', true );
		$stage = get_post_meta( $opp->ID, '_crm_opportunity_stage', true );

		if ( $value ) {
			$total_value += floatval( $value );
			if ( 'won' === $stage ) {
				$won_value += floatval( $value );
			}
		}
	}

	// Get person counts.
	$person_count  = wp_count_posts( 'crm_person' )->publish;
	$company_count = wp_count_posts( 'zqpm_entreprise' )->publish;

	// Get invoice statistics for current year.
	$current_year = gmdate( 'Y' );
	$year_start   = $current_year . '-01-01';
	$year_end     = $current_year . '-12-31';

	$all_invoices = get_posts(
		array(
			'post_type'      => 'crm_invoice',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => '_crm_invoice_date',
					'value'   => array( $year_start, $year_end ),
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				),
			),
		)
	);

	$invoice_total   = 0;
	$invoice_paid    = 0;
	$invoice_pending = 0;
	$invoice_overdue = 0;
	$today           = gmdate( 'Y-m-d' );

	foreach ( $all_invoices as $invoice ) {
		$amount         = floatval( get_post_meta( $invoice->ID, '_crm_invoice_amount', true ) );
		$payment_status = get_post_meta( $invoice->ID, '_crm_invoice_payment_status', true );
		$payment_date   = get_post_meta( $invoice->ID, '_crm_invoice_payment_date', true );

		$invoice_total += $amount;

		if ( 'paid' === $payment_status ) {
			$invoice_paid += $amount;
		} elseif ( 'partial' === $payment_status ) {
			$invoice_pending += $amount;
		} else {
			$invoice_pending += $amount;
			// Check if overdue (invoice date + 30 days default).
			$invoice_date = get_post_meta( $invoice->ID, '_crm_invoice_date', true );
			if ( $invoice_date && strtotime( $invoice_date . ' +30 days' ) < strtotime( $today ) ) {
				$invoice_overdue += $amount;
			}
		}
	}

	$invoice_count = count( $all_invoices );

	?>
	<div class="wrap">
		<h1>Tableau de bord CRM</h1>

		<div class="crm-dashboard-actions" style="margin: 20px 0;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=formapress-crm-edit-opportunity' ) ); ?>" class="button button-primary button-hero">
				+ Nouvelle opportunité
			</a>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=crm_invoice' ) ); ?>" class="button button-hero">
				+ Nouvelle facture
			</a>
		</div>

		<!-- Stats Grid -->
		<div class="crm-dashboard-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0;">
			<!-- Total Opportunities -->
			<div class="crm-stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
				<div style="font-size: 14px; color: #646970; margin-bottom: 8px;">Total opportunités</div>
				<div style="font-size: 32px; font-weight: 600; color: #1d2327;"><?php echo esc_html( count( $all_opportunities ) ); ?></div>
			</div>

			<!-- Won Opportunities -->
			<div class="crm-stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
				<div style="font-size: 14px; color: #646970; margin-bottom: 8px;">Opportunités gagnées</div>
				<div style="font-size: 32px; font-weight: 600; color: #00a32a;"><?php echo esc_html( $stats['won'] ?? 0 ); ?></div>
			</div>

			<!-- Total Value -->
			<div class="crm-stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
				<div style="font-size: 14px; color: #646970; margin-bottom: 8px;">Valeur totale pipeline</div>
				<div style="font-size: 32px; font-weight: 600; color: var(--zform_color_orange, #f57d20);"><?php echo esc_html( number_format( $total_value, 0, ',', ' ' ) ); ?> €</div>
			</div>

			<!-- Won Value -->
			<div class="crm-stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
				<div style="font-size: 14px; color: #646970; margin-bottom: 8px;">Chiffre d'affaires gagné</div>
				<div style="font-size: 32px; font-weight: 600; color: #00a32a;"><?php echo esc_html( number_format( $won_value, 0, ',', ' ' ) ); ?> €</div>
			</div>

			<!-- Persons -->
			<div class="crm-stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
				<div style="font-size: 14px; color: #646970; margin-bottom: 8px;">Personnes</div>
				<div style="font-size: 32px; font-weight: 600; color: #1d2327;"><?php echo esc_html( $person_count ); ?></div>
			</div>

			<!-- Companies -->
			<div class="crm-stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
				<div style="font-size: 14px; color: #646970; margin-bottom: 8px;">Entreprises</div>
				<div style="font-size: 32px; font-weight: 600; color: #1d2327;"><?php echo esc_html( $company_count ); ?></div>
			</div>
		</div>

		<!-- Invoice Statistics Section -->
		<div style="margin-top: 30px;">
			<h2 style="margin-bottom: 20px;">📊 Facturation <?php echo esc_html( $current_year ); ?></h2>
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
				<!-- Total Invoiced -->
				<div class="crm-stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
					<div style="font-size: 14px; color: #646970; margin-bottom: 8px;">Total facturé</div>
					<div style="font-size: 32px; font-weight: 600; color: var(--zform_color_orange, #f57d20);"><?php echo esc_html( number_format( $invoice_total, 0, ',', ' ' ) ); ?> €</div>
					<div style="font-size: 12px; color: #646970; margin-top: 8px;"><?php echo esc_html( $invoice_count ); ?> facture<?php echo $invoice_count > 1 ? 's' : ''; ?></div>
				</div>

				<!-- Amount Paid -->
				<div class="crm-stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
					<div style="font-size: 14px; color: #646970; margin-bottom: 8px;">Montant encaissé</div>
					<div style="font-size: 32px; font-weight: 600; color: #00a32a;"><?php echo esc_html( number_format( $invoice_paid, 0, ',', ' ' ) ); ?> €</div>
					<div style="font-size: 12px; color: #00a32a; margin-top: 8px;">
						<?php echo $invoice_total > 0 ? esc_html( round( ( $invoice_paid / $invoice_total ) * 100 ) ) : 0; ?>% du total
					</div>
				</div>

				<!-- Amount Pending -->
				<div class="crm-stat-card" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
					<div style="font-size: 14px; color: #646970; margin-bottom: 8px;">Montant en attente</div>
					<div style="font-size: 32px; font-weight: 600; color: #f0b323;"><?php echo esc_html( number_format( $invoice_pending, 0, ',', ' ' ) ); ?> €</div>
					<div style="font-size: 12px; color: #646970; margin-top: 8px;">
						<?php echo $invoice_total > 0 ? esc_html( round( ( $invoice_pending / $invoice_total ) * 100 ) ) : 0; ?>% du total
					</div>
				</div>

				<!-- Amount Overdue -->
				<?php if ( $invoice_overdue > 0 ) : ?>
					<div class="crm-stat-card" style="background: #fcf0f1; border: 1px solid #d63638; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04);">
						<div style="font-size: 14px; color: #d63638; margin-bottom: 8px; font-weight: 600;">⚠️ Montant en retard</div>
						<div style="font-size: 32px; font-weight: 600; color: #d63638;"><?php echo esc_html( number_format( $invoice_overdue, 0, ',', ' ' ) ); ?> €</div>
						<div style="font-size: 12px; color: #d63638; margin-top: 8px;">À relancer</div>
					</div>
				<?php endif; ?>
			</div>

			<!-- Quick Links -->
			<div style="margin-top: 20px;">
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=crm_invoice' ) ); ?>" class="button">
					Voir toutes les factures
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=crm_invoice&payment_status=pending' ) ); ?>" class="button">
					Factures en attente
				</a>
				<?php if ( $invoice_overdue > 0 ) : ?>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=crm_invoice&payment_status=overdue' ) ); ?>" class="button" style="color: #d63638; border-color: #d63638;">
						⚠️ Factures en retard
					</a>
				<?php endif; ?>
			</div>
		</div>

		<!-- Pipeline Kanban Board - Two Row Layout -->
		<div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04); margin-top: 20px;">
			<h2 style="margin-top: 0;">Pipeline commercial</h2>

			<?php
			// Get all opportunities.
			$all_opps_for_kanban = get_posts(
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

			foreach ( $all_opps_for_kanban as $opp ) {
				$stage = get_post_meta( $opp->ID, '_crm_opportunity_stage', true );
				if ( empty( $stage ) ) {
					$stage = 'new'; // Default.
				}
				if ( isset( $opportunities_by_stage[ $stage ] ) ) {
					$opportunities_by_stage[ $stage ][] = $opp;
				}
			}

			// Split stages into two rows (4 stages on first row, 3 on second).
			$stage_keys = array_keys( $stages );
			$first_row  = array_slice( $stage_keys, 0, 4, true );
			$second_row = array_slice( $stage_keys, 4, 3, true );
			?>

			<style>
				.crm-kanban-board.dashboard-variant {
					display: flex;
					flex-direction: column;
					gap: 20px;
					padding: 0;
					min-height: auto;
				}
				.crm-kanban-board.dashboard-variant .kanban-row {
					display: grid;
					gap: 15px;
				}
				.crm-kanban-board.dashboard-variant .kanban-row.row-4 {
					grid-template-columns: repeat(4, 1fr);
				}
				.crm-kanban-board.dashboard-variant .kanban-row.row-3 {
					grid-template-columns: repeat(3, 1fr);
				}
				.crm-kanban-board.dashboard-variant .kanban-column {
					min-width: 0;
				}
				.crm-kanban-board.dashboard-variant .kanban-cards {
					max-height: 400px;
					overflow-y: auto;
				}
			</style>

			<div class="crm-kanban-board dashboard-variant">
				<!-- First row: 4 stages -->
				<div class="kanban-row row-4">
					<?php foreach ( $first_row as $stage_key ) : ?>
						<div class="kanban-column" data-stage="<?php echo esc_attr( $stage_key ); ?>">
							<div class="column-header">
								<div class="column-title"><?php echo esc_html( $stages[ $stage_key ] ); ?></div>
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
												<a href="<?php echo esc_url( admin_url( 'admin.php?page=formapress-crm-edit-opportunity&id=' . $opp->ID ) ); ?>">
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
									<p class="kanban-empty">Aucune opportunité</p>
									<?php
								endif;
								?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<!-- Second row: 3 stages -->
				<div class="kanban-row row-3">
					<?php foreach ( $second_row as $stage_key ) : ?>
						<div class="kanban-column" data-stage="<?php echo esc_attr( $stage_key ); ?>">
							<div class="column-header">
								<div class="column-title"><?php echo esc_html( $stages[ $stage_key ] ); ?></div>
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
												<a href="<?php echo esc_url( admin_url( 'admin.php?page=formapress-crm-edit-opportunity&id=' . $opp->ID ) ); ?>">
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
									<p class="kanban-empty">Aucune opportunité</p>
									<?php
								endif;
								?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Get opportunity stages.
 *
 * @return array Stages array.
 */
function formapress_crm_get_opportunity_stages() {
	return array(
		'new'                  => 'Nouveau prospect',
		'qualified'            => 'Qualifié',
		'proposal_in_progress' => 'Proposition en préparation',
		'proposal'             => 'Proposition envoyée',
		'negotiation'          => 'En négociation',
		'won'                  => 'Gagné',
		'lost'                 => 'Perdu',
	);
}

/**
 * Register dashboard widgets.
 */
function formapress_crm_add_dashboard_widgets() {
	wp_add_dashboard_widget(
		'formapress_crm_pipeline_widget',
		'Pipeline commercial FormaPress',
		'formapress_crm_dashboard_pipeline_widget'
	);
}
add_action( 'wp_dashboard_setup', 'formapress_crm_add_dashboard_widgets' );

/**
 * Display pipeline widget on WordPress dashboard.
 * Shows all opportunity stages in a two-row layout.
 */
function formapress_crm_dashboard_pipeline_widget() {
	$stages = formapress_crm_get_opportunity_stages();
	$stats  = array();

	// Get count for each stage.
	foreach ( $stages as $stage_key => $stage_label ) {
		$opportunities       = get_posts(
			array(
				'post_type'      => 'crm_opportunity',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					array(
						'key'     => '_crm_opportunity_stage',
						'value'   => $stage_key,
						'compare' => '=',
					),
				),
			)
		);
		$stats[ $stage_key ] = count( $opportunities );
	}

	// Calculate total opportunity value.
	$all_opportunities = get_posts(
		array(
			'post_type'      => 'crm_opportunity',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		)
	);

	$total_value = 0;
	$won_value   = 0;

	foreach ( $all_opportunities as $opp ) {
		$value = get_post_meta( $opp->ID, '_crm_opportunity_value', true );
		$stage = get_post_meta( $opp->ID, '_crm_opportunity_stage', true );

		if ( $value ) {
			$total_value += floatval( $value );
			if ( 'won' === $stage ) {
				$won_value += floatval( $value );
			}
		}
	}

	// Split stages into two rows (4 stages on first row, 3 on second).
	$stage_keys = array_keys( $stages );
	$first_row  = array_slice( $stage_keys, 0, 4, true );
	$second_row = array_slice( $stage_keys, 4, 3, true );
	?>
	<style>
		.formapress-pipeline-widget {
			margin: -12px -12px 0 -12px;
		}
		.formapress-pipeline-row {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
			gap: 8px;
			margin-bottom: 12px;
			padding: 12px;
			background: #f6f7f7;
		}
		.formapress-pipeline-stage {
			text-align: center;
			padding: 12px 8px;
			background: #fff;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			transition: all 0.2s ease;
		}
		.formapress-pipeline-stage:hover {
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
			transform: translateY(-2px);
		}
		.formapress-pipeline-stage.stage-won {
			border-color: #00a32a;
			background: #f0f9f3;
		}
		.formapress-pipeline-stage.stage-lost {
			border-color: #d63638;
			background: #fcf0f1;
		}
		.pipeline-stage-count {
			font-size: 24px;
			font-weight: 600;
			color: #1d2327;
			margin-bottom: 4px;
		}
		.stage-won .pipeline-stage-count {
			color: #00a32a;
		}
		.stage-lost .pipeline-stage-count {
			color: #d63638;
		}
		.pipeline-stage-label {
			font-size: 11px;
			color: #646970;
			line-height: 1.3;
		}
		.formapress-pipeline-summary {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 12px;
			padding: 12px;
			background: #fff;
			border-top: 1px solid #dcdcde;
		}
		.pipeline-summary-item {
			text-align: center;
		}
		.pipeline-summary-value {
			font-size: 20px;
			font-weight: 600;
			color: var(--zform_color_orange, #f57d20);
			margin-bottom: 2px;
		}
		.pipeline-summary-label {
			font-size: 11px;
			color: #646970;
		}
		.formapress-pipeline-actions {
			padding: 12px;
			text-align: center;
			background: #fff;
			border-top: 1px solid #dcdcde;
		}
	</style>

	<div class="formapress-pipeline-widget">
		<!-- First row: 4 stages -->
		<div class="formapress-pipeline-row">
			<?php foreach ( $first_row as $stage_key ) : ?>
				<div class="formapress-pipeline-stage stage-<?php echo esc_attr( $stage_key ); ?>">
					<div class="pipeline-stage-count"><?php echo esc_html( $stats[ $stage_key ] ?? 0 ); ?></div>
					<div class="pipeline-stage-label"><?php echo esc_html( $stages[ $stage_key ] ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Second row: 3 stages -->
		<div class="formapress-pipeline-row">
			<?php foreach ( $second_row as $stage_key ) : ?>
				<div class="formapress-pipeline-stage stage-<?php echo esc_attr( $stage_key ); ?>">
					<div class="pipeline-stage-count"><?php echo esc_html( $stats[ $stage_key ] ?? 0 ); ?></div>
					<div class="pipeline-stage-label"><?php echo esc_html( $stages[ $stage_key ] ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Summary stats -->
		<div class="formapress-pipeline-summary">
			<div class="pipeline-summary-item">
				<div class="pipeline-summary-value"><?php echo esc_html( number_format( $total_value, 0, ',', ' ' ) ); ?> €</div>
				<div class="pipeline-summary-label">Valeur totale</div>
			</div>
			<div class="pipeline-summary-item">
				<div class="pipeline-summary-value" style="color: #00a32a;"><?php echo esc_html( number_format( $won_value, 0, ',', ' ' ) ); ?> €</div>
				<div class="pipeline-summary-label">CA gagné</div>
			</div>
		</div>

		<!-- Action buttons -->
		<div class="formapress-pipeline-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=formapress-crm-pipeline' ) ); ?>" class="button button-small">Voir le pipeline détaillé</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=formapress-crm-edit-opportunity' ) ); ?>" class="button button-primary button-small">+ Nouvelle opportunité</a>
		</div>
	</div>
	<?php
}

/**
 * Displays the Kanban pipeline board for opportunities.
 */
function formapress_crm_pipeline_page_html() {
	// Pipeline stages in French.
	$stages = array(
		'new'                  => 'Nouveau prospect',
		'qualified'            => 'Qualifié',
		'proposal_in_progress' => 'Proposition en préparation',
		'proposal'             => 'Proposition envoyée',
		'negotiation'          => 'En négociation',
		'won'                  => 'Gagné',
		'lost'                 => 'Perdu',
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
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=formapress-crm-edit-opportunity' ) ); ?>" class="button button-primary">
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
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=formapress-crm-edit-opportunity&id=' . $opp->ID ) ); ?>">
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
	echo esc_html_e( 'Formapress CRM - Migration Tools', 'formapress-crm' ); ?></h1>

		<!-- V2 Attribute System Migration (Week 4) -->
		<div class="postbox" style="margin-top: 20px; padding: 20px; background: #f0f8ff; border: 2px solid #0073aa;">
			<h2 style="margin-top: 0; color: #0073aa;">
				<span class="dashicons dashicons-update" style="font-size: 24px; margin-right: 10px;"></span>
				<?php esc_html_e( 'V2 Attribute System Migration (Recommended)', 'formapress-crm' ); ?>
			</h2>
			<p style="font-size: 14px;">
				<?php esc_html_e( 'This unified migration tool converts v1 data (direct meta keys) to the new v2 attribute system with immutable locked fields. Run this AFTER initial v1→v2 CPT migration.', 'formapress-crm' ); ?>
			</p>
			<p style="font-size: 13px; color: #666;">
				<strong><?php esc_html_e( 'What it does:', 'formapress-crm' ); ?></strong><br>
				• Companies: Migrates raison-sociale, siret, adresse, cp, ville, site to attribute system<br>
				• Referents: Migrates civilite, nom, prenom, poste, tel, mail to attribute system<br>
				• Funders: Migrates type-financeur to attribute system<br>
				• Ensures backward compatibility with v1 locked fields
			</p>

			<div style="margin: 20px 0;">
				<h3><?php esc_html_e( 'Migration Status', 'formapress-crm' ); ?></h3>
				<div id="v2-migration-status" style="background: white; padding: 15px; border: 1px solid #ccc; border-radius: 4px;">
					<em><?php esc_html_e( 'Loading status...', 'formapress-crm' ); ?></em>
				</div>
			</div>

			<div style="margin: 20px 0;">
				<button id="v2-migrate-companies" class="button button-primary" style="margin-right: 10px;">
					<?php esc_html_e( 'Migrate Companies', 'formapress-crm' ); ?>
				</button>
				<button id="v2-migrate-referents" class="button button-primary" style="margin-right: 10px;">
					<?php esc_html_e( 'Migrate Referents', 'formapress-crm' ); ?>
				</button>
				<button id="v2-migrate-funders" class="button button-primary" style="margin-right: 10px;">
					<?php esc_html_e( 'Migrate Funders', 'formapress-crm' ); ?>
				</button>
				<button id="v2-migrate-all" class="button button-primary" style="background: #00a32a; border-color: #00a32a;">
					<?php esc_html_e( 'Migrate All', 'formapress-crm' ); ?>
				</button>
			</div>

			<div style="margin: 20px 0;">
				<label style="display: inline-block; margin-right: 20px;">
					<input type="checkbox" id="v2-migration-dry-run" checked>
					<?php esc_html_e( 'Dry Run (preview only)', 'formapress-crm' ); ?>
				</label>
				<label style="display: inline-block;">
					<input type="checkbox" id="v2-migration-force">
					<?php esc_html_e( 'Force (re-migrate already completed)', 'formapress-crm' ); ?>
				</label>
			</div>

			<div id="v2-migration-log" style="margin-top: 20px; max-height: 400px; overflow-y: auto; background: white; padding: 15px; border: 1px solid #ccc; border-radius: 4px; font-family: monospace; font-size: 12px; display: none;">
			</div>
		</div>

		<hr style="margin: 40px 0;">

		<h2><?php esc_html_e( 'Legacy Migration Tools', 'formapress-crm' ); ?></h2>
		<p style="color: #666;"><em><?php esc_html_e( 'These tools are kept for reference. Use V2 Attribute Migration above for new migrations.', 'formapress-crm' ); ?></em></p>

		<div id="migration-status"></div>

		<h3><?php esc_html_e( 'Migrate zqpmReferent to crm_person', 'formapress-crm' ); ?></h3>
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

			// V2 Attribute Migration JavaScript
			var v2MigrationNonce = '<?php echo esc_js( wp_create_nonce( 'formapress_crm_v2_migration_nonce' ) ); ?>';

			// Load status on page load
			loadV2MigrationStatus();

			function loadV2MigrationStatus() {
				$.post(ajaxurl, {
					action: 'formapress_crm_get_v2_migration_status',
					nonce: v2MigrationNonce
				}, function(response) {
					if (response.success && response.data.status) {
						var html = '<table class="widefat" style="width: 100%;"><thead><tr><th>Entity Type</th><th>Total</th><th>Migrated</th><th>Errors</th><th>Status</th><th>Last Run</th></tr></thead><tbody>';

						$.each(response.data.status, function(type, info) {
							var status = info.completed ? '<span style="color: green;">✓ Completed</span>' : '<span style="color: #999;">Not Started</span>';
							var lastRun = info.last_run || 'Never';
							html += '<tr>';
							html += '<td><strong>' + type.charAt(0).toUpperCase() + type.slice(1) + '</strong></td>';
							html += '<td>' + info.total + '</td>';
							html += '<td>' + info.migrated + '</td>';
							html += '<td>' + (info.errors || 0) + '</td>';
							html += '<td>' + status + '</td>';
							html += '<td>' + lastRun + '</td>';
							html += '</tr>';
						});

						html += '</tbody></table>';
						$('#v2-migration-status').html(html);
					}
				});
			}

			function runV2Migration(entityType) {
				var dryRun = $('#v2-migration-dry-run').is(':checked');
				var force = $('#v2-migration-force').is(':checked');
				var $log = $('#v2-migration-log');

				$log.show().html('<em>Processing ' + entityType + '...</em>');
				$('.button').prop('disabled', true);

				$.post(ajaxurl, {
					action: 'formapress_crm_v2_attr_migration',
					nonce: v2MigrationNonce,
					entity_type: entityType,
					dry_run: dryRun ? 'true' : 'false',
					force: force ? 'true' : 'false'
				}, function(response) {
					$('.button').prop('disabled', false);

					if (response.success) {
						var html = '<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; margin-bottom: 10px; border-radius: 4px;">';
						html += '<strong>✓ Success!</strong><br>';

						if (response.data.results) {
							// Multiple entity types
							$.each(response.data.results, function(type, result) {
								html += '<br><strong>' + type.toUpperCase() + ':</strong> ';
								html += result.migrated + ' migrated, ' + result.skipped + ' skipped, ' + result.errors + ' errors';
							});
						} else {
							// Single entity type
							html += 'Total: ' + response.data.total + ' | ';
							html += 'Migrated: ' + response.data.migrated + ' | ';
							html += 'Skipped: ' + response.data.skipped + ' | ';
							html += 'Errors: ' + response.data.errors;
						}
						html += '</div>';

						// Show log
						var logs = response.data.log || (response.data.results && response.data.results.companies ? response.data.results.companies.log : []);
						if (logs && logs.length > 0) {
							html += '<div style="margin-top: 10px;"><strong>Migration Log:</strong></div>';
							html += '<ul style="margin: 5px 0; padding-left: 20px;">';
							$.each(logs, function(i, line) {
								var color = line.indexOf('ERROR') >= 0 ? 'red' : (line.indexOf('WARNING') >= 0 ? 'orange' : 'black');
								html += '<li style="color: ' + color + ';">' + line + '</li>';
							});
							html += '</ul>';
						}

						$log.html(html);
						loadV2MigrationStatus();
					} else {
						$log.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px;"><strong>✗ Error:</strong> ' + (response.data.message || 'Unknown error') + '</div>');
					}
				}).fail(function() {
					$('.button').prop('disabled', false);
					$log.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px;"><strong>✗ Error:</strong> AJAX request failed</div>');
				});
			}

			$('#v2-migrate-companies').on('click', function() { runV2Migration('companies'); });
			$('#v2-migrate-referents').on('click', function() { runV2Migration('referents'); });
			$('#v2-migrate-funders').on('click', function() { runV2Migration('funders'); });
			$('#v2-migrate-all').on('click', function() { runV2Migration('all'); });
		});
	</script>
	<?php
}

/**
 * Deprecation notice helper for legacy migration UI
 */
function formapress_crm_show_deprecation_notice() {
	?>
	<div class="notice notice-warning" style="margin: 20px 0;">
		<p><strong><?php esc_html_e( 'Deprecated:', 'formapress-crm' ); ?></strong>
		<?php esc_html_e( 'These legacy migration tools use outdated patterns. For new migrations, use WP-CLI commands which properly save data to the attribute system:', 'formapress-crm' ); ?></p>
		<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px;">
wp formapress migrate instructors
wp formapress migrate companies  # Also extracts referents
wp formapress migrate trainees
wp formapress migrate all        # Run everything</pre>
		<p><?php esc_html_e( 'If you already migrated data with these legacy tools, it may be using direct meta keys instead of the attribute system. Contact support for assistance.', 'formapress-crm' ); ?></p>
	</div>
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
	$valid_stages = array( 'new', 'qualified', 'proposal_in_progress', 'proposal', 'negotiation', 'won', 'lost' );
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
 * Note: formapress_crm_sanitize_attributes() has been moved to zFormations base plugin
 * since CRM attributes are now defined in zFormations (admin/admin-options.php).
 * The function is wrapped in function_exists() check to avoid conflicts.
 */
