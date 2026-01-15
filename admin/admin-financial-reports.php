<?php
/**
 * Financial Reports Admin Page
 *
 * Provides analytics, charts, and export functionality for invoice data.
 *
 * @package FormaPress_CRM
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Debug: Log that file is loaded.
error_log( 'FormaPress CRM: admin-financial-reports.php loaded' );

/**
 * Register financial reports admin page
 */
function formapress_crm_register_financial_reports_page() {
	// Debug: Log menu registration attempt.
	error_log( 'FormaPress CRM: Attempting to register financial reports menu' );

	$hook = add_submenu_page(
		'formapress-crm-dashboard',
		__( 'Rapports Financiers', 'formapress-crm' ),
		__( 'Rapports Financiers', 'formapress-crm' ),
		'edit_posts',
		'formapress-financial-reports',
		'formapress_crm_render_financial_reports_page'
	);

	// Debug: Log result.
	error_log( 'FormaPress CRM: Financial reports menu hook: ' . ( $hook ? $hook : 'FAILED' ) );
}
add_action( 'admin_menu', 'formapress_crm_register_financial_reports_page', 100 );

/**
 * Enqueue scripts and styles for financial reports page
 */
function formapress_crm_enqueue_financial_reports_assets( $hook ) {
	// Debug: Log the hook value.
	error_log( 'FormaPress CRM: Enqueue hook check - received: ' . $hook );

	// Only load on our reports page.
	if ( 'crm_page_formapress-financial-reports' !== $hook ) {
		error_log( 'FormaPress CRM: Hook does not match, skipping asset enqueue' );
		return;
	}

	error_log( 'FormaPress CRM: Hook matches! Enqueueing financial reports assets' );

	// Enqueue Chart.js from CDN.
	wp_enqueue_script(
		'chartjs',
		'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
		array(),
		'4.4.1',
		true
	);

	// Enqueue date range picker (jQuery UI already in WordPress core).
	wp_enqueue_script( 'jquery-ui-datepicker' );
	wp_enqueue_style( 'wp-jquery-ui-dialog' );

	// Enqueue custom reports JS.
	wp_enqueue_script(
		'formapress-financial-reports',
		FORMAPRESS_CRM_PLUGIN_URL . 'assets/js/financial-reports.js',
		array( 'jquery', 'chartjs', 'jquery-ui-datepicker' ),
		FORMAPRESS_CRM_VERSION,
		true
	);

	// Localize script with AJAX URL and nonce.
	wp_localize_script(
		'formapress-financial-reports',
		'formapressReports',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'formapress_reports_nonce' ),
		)
	);

	// Enqueue custom reports CSS.
	wp_enqueue_style(
		'formapress-financial-reports',
		FORMAPRESS_CRM_PLUGIN_URL . 'assets/css/financial-reports.css',
		array(),
		FORMAPRESS_CRM_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'formapress_crm_enqueue_financial_reports_assets' );

/**
 * Render financial reports page
 */
function formapress_crm_render_financial_reports_page() {
	// Get default date range (current year).
	$current_year = gmdate( 'Y' );
	$start_date   = $current_year . '-01-01';
	$end_date     = gmdate( 'Y-m-d' );

	?>
	<div class="wrap formapress-financial-reports">
		<h1 class="wp-heading-inline">
			<span class="dashicons dashicons-chart-area"></span>
			<?php esc_html_e( 'Rapports Financiers', 'formapress-crm' ); ?>
		</h1>

		<hr class="wp-header-end">

		<!-- Filters Section -->
		<div class="reports-filters">
			<div class="filters-row">
				<!-- Date Range Section -->
				<div class="filter-section date-section">
					<span class="section-label">
						<span class="dashicons dashicons-calendar-alt"></span>
						<?php esc_html_e( 'Période', 'formapress-crm' ); ?>
					</span>
					<div class="filter-group">
						<label for="report-start-date">
							<?php esc_html_e( 'Du', 'formapress-crm' ); ?>
						</label>
						<input
							type="text"
							id="report-start-date"
							class="report-datepicker"
							value="<?php echo esc_attr( $start_date ); ?>"
							readonly
						>
					</div>

					<div class="filter-group">
						<label for="report-end-date">
							<?php esc_html_e( 'Au', 'formapress-crm' ); ?>
						</label>
						<input
							type="text"
							id="report-end-date"
							class="report-datepicker"
							value="<?php echo esc_attr( $end_date ); ?>"
							readonly
						>
					</div>

					<button type="button" id="refresh-reports-btn" class="button button-primary">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Actualiser', 'formapress-crm' ); ?>
					</button>
				</div>

				<!-- Actions Section -->
				<div class="filter-section actions-section">
					<button type="button" id="export-excel-btn" class="button button-secondary">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Exporter Excel', 'formapress-crm' ); ?>
					</button>

					<button type="button" id="export-bpf-btn" class="button button-secondary">
						<span class="dashicons dashicons-media-document"></span>
						<?php esc_html_e( 'Générer BPF', 'formapress-crm' ); ?>
					</button>
				</div>
			</div>
		</div>

		<!-- Summary Cards -->
		<div class="reports-summary">
			<div class="summary-card">
				<div class="summary-icon">
					<span class="dashicons dashicons-money-alt"></span>
				</div>
				<div class="summary-content">
					<h3><?php esc_html_e( 'Chiffre d\'affaires total', 'formapress-crm' ); ?></h3>
					<p class="summary-value" id="total-revenue">
						<span class="spinner is-active"></span>
					</p>
				</div>
			</div>

			<div class="summary-card">
				<div class="summary-icon">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
				<div class="summary-content">
					<h3><?php esc_html_e( 'Factures payées', 'formapress-crm' ); ?></h3>
					<p class="summary-value" id="paid-invoices">
						<span class="spinner is-active"></span>
					</p>
				</div>
			</div>

			<div class="summary-card">
				<div class="summary-icon">
					<span class="dashicons dashicons-clock"></span>
				</div>
				<div class="summary-content">
					<h3><?php esc_html_e( 'Solde impayé', 'formapress-crm' ); ?></h3>
					<p class="summary-value" id="outstanding-balance">
						<span class="spinner is-active"></span>
					</p>
				</div>
			</div>

			<div class="summary-card alert">
				<div class="summary-icon">
					<span class="dashicons dashicons-warning"></span>
				</div>
				<div class="summary-content">
					<h3><?php esc_html_e( 'Impayés en retard', 'formapress-crm' ); ?></h3>
					<p class="summary-value" id="overdue-amount">
						<span class="spinner is-active"></span>
					</p>
				</div>
			</div>
		</div>

		<!-- Charts Section -->
		<div class="reports-charts">
			<!-- Revenue Over Time Chart -->
			<div class="chart-container">
				<div class="chart-header">
					<h2>
						<span class="dashicons dashicons-chart-line"></span>
						<?php esc_html_e( 'Évolution du chiffre d\'affaires', 'formapress-crm' ); ?>
					</h2>
					<div class="chart-controls">
						<button type="button" class="button time-period-btn active" data-period="monthly">
							<?php esc_html_e( 'Mensuel', 'formapress-crm' ); ?>
						</button>
						<button type="button" class="button time-period-btn" data-period="quarterly">
							<?php esc_html_e( 'Trimestriel', 'formapress-crm' ); ?>
						</button>
					</div>
				</div>
				<div class="chart-canvas-wrapper">
					<canvas id="revenue-chart"></canvas>
				</div>
			</div>

			<!-- Two Column Charts -->
			<div class="charts-row">
				<!-- Aging Analysis Chart -->
				<div class="chart-container half-width">
					<div class="chart-header">
						<h2>
							<span class="dashicons dashicons-calendar-alt"></span>
							<?php esc_html_e( 'Ancienneté des créances', 'formapress-crm' ); ?>
						</h2>
					</div>
					<div class="chart-canvas-wrapper">
						<canvas id="aging-chart"></canvas>
					</div>
				</div>

				<!-- Funding Source Chart -->
				<div class="chart-container half-width">
					<div class="chart-header">
						<h2>
							<span class="dashicons dashicons-bank"></span>
							<?php esc_html_e( 'Répartition par financeur', 'formapress-crm' ); ?>
						</h2>
					</div>
					<div class="chart-canvas-wrapper">
						<canvas id="funding-chart"></canvas>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
}
