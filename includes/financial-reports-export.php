<?php
/**
 * Financial Reports Export Functions
 *
 * Handles Excel/CSV export and BPF generation.
 *
 * @package FormaPress_CRM
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export invoices to CSV/Excel
 */
function formapress_export_excel() {
	check_ajax_referer( 'formapress_reports_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'Permission refusée.', 'formapress-crm' ) );
	}

	$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
	$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';

	// Query invoices.
	$args = array(
		'post_type'      => 'crm_invoice',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'draft' ),
		'meta_query'     => array(
			array(
				'key'     => 'invoice_date',
				'value'   => array( $start_date, $end_date ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			),
		),
		'orderby'        => 'meta_value',
		'meta_key'       => 'invoice_date',
		'order'          => 'ASC',
	);

	$invoices = get_posts( $args );

	// Set headers for CSV download.
	$filename = 'factures_' . gmdate( 'Y-m-d' ) . '.csv';
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	// Open output stream.
	$output = fopen( 'php://output', 'w' );

	// Add BOM for proper UTF-8 encoding in Excel.
	fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

	// Write headers.
	fputcsv(
		$output,
		array(
			'Numéro',
			'Date',
			'Échéance',
			'Client',
			'Titre',
			'Montant',
			'Payé',
			'Solde',
			'Statut',
			'Session',
		),
		';'
	);

	// Write data rows.
	foreach ( $invoices as $invoice ) {
		$invoice_number = get_post_meta( $invoice->ID, '_crm_invoice_number', true );
		$invoice_date   = get_post_meta( $invoice->ID, '_crm_invoice_date', true );
		$due_date       = get_post_meta( $invoice->ID, '_crm_invoice_due_date', true );
		$amount         = (float) get_post_meta( $invoice->ID, '_crm_invoice_amount', true );
		$status         = get_post_meta( $invoice->ID, '_crm_invoice_payment_status', true );
		$session_id     = get_post_meta( $invoice->ID, '_crm_invoice_zqpm_session_id', true );
		$company_id     = get_post_meta( $invoice->ID, '_crm_invoice_company_id', true );

		// Get company name.
		$company_name = '';
		if ( $company_id ) {
			$company      = get_post( $company_id );
			$company_name = $company ? $company->post_title : '';
		}

		// Calculate payments.
		$payments   = get_post_meta( $invoice->ID, '_crm_invoice_payments', true );
		$payments   = is_array( $payments ) ? $payments : array();
		$total_paid = 0;
		foreach ( $payments as $payment ) {
			$total_paid += (float) ( $payment['amount'] ?? 0 );
		}
		$balance = $amount - $total_paid;

		// Get session info.
		$session_name = '';
		if ( $session_id && class_exists( 'zSession' ) ) {
			$session      = new zSession( $session_id );
			$formation    = get_post( $session->formation_id );
			$session_name = $formation ? $formation->post_title : '';
		}

		// Format status label.
		$status_labels = array(
			'draft'    => 'Brouillon',
			'pending'  => 'En attente',
			'paid'     => 'Payée',
			'overdue'  => 'En retard',
			'partial'  => 'Payée partiellement',
			'canceled' => 'Annulée',
		);
		$status_label  = $status_labels[ $status ] ?? $status;

		fputcsv(
			$output,
			array(
				$invoice_number,
				$invoice_date,
				$due_date,
				$company_name,
				$invoice->post_title,
				number_format( $amount, 2, ',', ' ' ) . ' €',
				number_format( $total_paid, 2, ',', ' ' ) . ' €',
				number_format( $balance, 2, ',', ' ' ) . ' €',
				$status_label,
				$session_name,
			),
			';'
		);
	}

	fclose( $output );
	exit;
}
add_action( 'wp_ajax_formapress_export_excel', 'formapress_export_excel' );

/**
 * Export Bilan Pédagogique et Financier (BPF)
 */
function formapress_export_bpf() {
	check_ajax_referer( 'formapress_reports_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'Permission refusée.', 'formapress-crm' ) );
	}

	$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
	$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';

	// Query invoices.
	$args = array(
		'post_type'      => 'crm_invoice',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'draft' ),
		'meta_query'     => array(
			array(
				'key'     => 'invoice_date',
				'value'   => array( $start_date, $end_date ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			),
		),
	);

	$invoices = get_posts( $args );

	// Aggregate BPF data.
	$total_revenue   = 0;
	$total_hours     = 0;
	$total_trainees  = array();
	$funding_sources = array();
	$formation_types = array();
	$invoice_details = array();

	foreach ( $invoices as $invoice ) {
		$amount     = (float) get_post_meta( $invoice->ID, '_crm_invoice_amount', true );
		$session_id = get_post_meta( $invoice->ID, '_crm_invoice_zqpm_session_id', true );

		$total_revenue += $amount;

		// Get session details.
		if ( $session_id && class_exists( 'zSession' ) ) {
			$session   = new zSession( $session_id );
			$formation = get_post( $session->formation_id );

			// Count training hours.
			$duration = get_post_meta( $session_id, 'zqpm_duree', true );
			if ( ! empty( $duration ) ) {
				$total_hours += (float) $duration;
			}

			// Count unique trainees.
			$registrations = get_post_meta( $session_id, 'zqpm_registrants', true );
			if ( is_array( $registrations ) ) {
				foreach ( $registrations as $reg_id ) {
					$total_trainees[ $reg_id ] = true;
				}
			}

			// Group by formation type.
			if ( $formation ) {
				$formation_name = $formation->post_title;
				if ( ! isset( $formation_types[ $formation_name ] ) ) {
					$formation_types[ $formation_name ] = array(
						'hours'    => 0,
						'revenue'  => 0,
						'trainees' => 0,
					);
				}
				$formation_types[ $formation_name ]['hours']   += (float) $duration;
				$formation_types[ $formation_name ]['revenue'] += $amount;
				if ( is_array( $registrations ) ) {
					$formation_types[ $formation_name ]['trainees'] += count( $registrations );
				}
			}

			// Get funding source.
			$funding_type = get_post_meta( $session_id, 'zqpm_funding_type', true );
			if ( empty( $funding_type ) ) {
				$funding_type = 'Direct';
			}
			if ( ! isset( $funding_sources[ $funding_type ] ) ) {
				$funding_sources[ $funding_type ] = 0;
			}
			$funding_sources[ $funding_type ] += $amount;
		}

		// Store invoice details.
		$invoice_details[] = array(
			'number'  => get_post_meta( $invoice->ID, '_crm_invoice_number', true ),
			'date'    => get_post_meta( $invoice->ID, '_crm_invoice_date', true ),
			'amount'  => $amount,
			'session' => $session_id,
		);
	}

	$total_trainees_count = count( $total_trainees );

	// Generate HTML report.
	ob_start();
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<meta charset="UTF-8">
		<title>Bilan Pédagogique et Financier</title>
		<style>
			body {
				font-family: Arial, sans-serif;
				margin: 40px;
				line-height: 1.6;
				color: #333;
			}
			h1 {
				color: #ff8c00;
				border-bottom: 3px solid #ff8c00;
				padding-bottom: 10px;
			}
			h2 {
				color: #555;
				margin-top: 30px;
				border-bottom: 1px solid #ddd;
				padding-bottom: 5px;
			}
			.summary-box {
				background: #f5f5f5;
				padding: 20px;
				margin: 20px 0;
				border-left: 4px solid #ff8c00;
			}
			.summary-box strong {
				font-size: 18px;
				color: #ff8c00;
			}
			table {
				width: 100%;
				border-collapse: collapse;
				margin: 20px 0;
			}
			th, td {
				padding: 10px;
				border: 1px solid #ddd;
				text-align: left;
			}
			th {
				background: #ff8c00;
				color: white;
				font-weight: bold;
			}
			tr:nth-child(even) {
				background: #f9f9f9;
			}
			.right-align {
				text-align: right;
			}
			.footer {
				margin-top: 40px;
				padding-top: 20px;
				border-top: 1px solid #ddd;
				font-size: 12px;
				color: #999;
			}
		</style>
	</head>
	<body>
		<h1>Bilan Pédagogique et Financier</h1>
		<p><strong>Période :</strong> <?php echo esc_html( $start_date ); ?> au <?php echo esc_html( $end_date ); ?></p>
		<p><strong>Généré le :</strong> <?php echo esc_html( gmdate( 'd/m/Y à H:i' ) ); ?></p>

		<div class="summary-box">
			<p><strong>Chiffre d'affaires total :</strong> <?php echo esc_html( number_format( $total_revenue, 2, ',', ' ' ) ); ?> €</p>
			<p><strong>Nombre d'heures de formation :</strong> <?php echo esc_html( number_format( $total_hours, 1, ',', ' ' ) ); ?> h</p>
			<p><strong>Nombre de stagiaires formés :</strong> <?php echo esc_html( $total_trainees_count ); ?></p>
			<p><strong>Nombre de factures :</strong> <?php echo esc_html( count( $invoices ) ); ?></p>
		</div>

		<h2>Répartition par financeur</h2>
		<table>
			<thead>
				<tr>
					<th>Financeur</th>
					<th class="right-align">Montant</th>
					<th class="right-align">Pourcentage</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $funding_sources as $source => $amount ) : ?>
					<tr>
						<td><?php echo esc_html( $source ); ?></td>
						<td class="right-align"><?php echo esc_html( number_format( $amount, 2, ',', ' ' ) ); ?> €</td>
						<td class="right-align"><?php echo esc_html( number_format( ( $amount / $total_revenue ) * 100, 1, ',', '' ) ); ?>%</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2>Répartition par type de formation</h2>
		<table>
			<thead>
				<tr>
					<th>Formation</th>
					<th class="right-align">Heures</th>
					<th class="right-align">Stagiaires</th>
					<th class="right-align">Chiffre d'affaires</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $formation_types as $name => $data ) : ?>
					<tr>
						<td><?php echo esc_html( $name ); ?></td>
						<td class="right-align"><?php echo esc_html( number_format( $data['hours'], 1, ',', ' ' ) ); ?> h</td>
						<td class="right-align"><?php echo esc_html( $data['trainees'] ); ?></td>
						<td class="right-align"><?php echo esc_html( number_format( $data['revenue'], 2, ',', ' ' ) ); ?> €</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="footer">
			<p>Document généré automatiquement par FormaPress CRM</p>
		</div>
	</body>
	</html>
	<?php
	$html = ob_get_clean();

	// Output as PDF or HTML.
	$filename = 'BPF_' . gmdate( 'Y-m-d' ) . '.html';
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}
add_action( 'wp_ajax_formapress_export_bpf', 'formapress_export_bpf' );
