<?php
/**
 * Financial Reports AJAX Handlers
 *
 * Provides data endpoints for the financial reports dashboard.
 *
 * @package FormaPress_CRM
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get summary statistics for date range
 */
function formapress_get_summary_stats() {
	check_ajax_referer( 'formapress_reports_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission refusée.', 'formapress-crm' ) ) );
	}

	$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
	$end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';

	error_log( 'FormaPress Financial Reports: Date range = ' . $start_date . ' to ' . $end_date );

	// Query invoices in date range.
	$args = array(
		'post_type'      => 'crm_invoice',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'draft' ),
		'meta_query'     => array(
			array(
				'key'     => '_crm_invoice_date',
				'value'   => array( $start_date, $end_date ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			),
		),
	);

	$invoices = get_posts( $args );
	error_log( 'FormaPress Financial Reports: Found ' . count( $invoices ) . ' invoices' );

	$total_revenue  = 0;
	$total_count    = count( $invoices );
	$paid_count     = 0;
	$outstanding    = 0;
	$overdue_amount = 0;
	$today          = gmdate( 'Y-m-d' );

	foreach ( $invoices as $invoice ) {
		$amount     = (float) get_post_meta( $invoice->ID, '_crm_invoice_amount', true );
		$status     = get_post_meta( $invoice->ID, '_crm_invoice_payment_status', true );
		$due_date   = get_post_meta( $invoice->ID, '_crm_invoice_due_date', true );
		$payments   = get_post_meta( $invoice->ID, '_crm_invoice_payments', true );
		$payments   = is_array( $payments ) ? $payments : array();
		$total_paid = 0;

		foreach ( $payments as $payment ) {
			$total_paid += (float) ( $payment['amount'] ?? 0 );
		}

		$balance = $amount - $total_paid;

		$total_revenue += $amount;

		if ( 'paid' === $status || $balance <= 0 ) {
			++$paid_count;
		} else {
			$outstanding += $balance;

			// Check if overdue.
			if ( ! empty( $due_date ) && $due_date < $today ) {
				$overdue_amount += $balance;
			}
		}
	}

	wp_send_json_success(
		array(
			'total_revenue' => $total_revenue,
			'total_count'   => $total_count,
			'paid_count'    => $paid_count,
			'outstanding'   => $outstanding,
			'overdue'       => $overdue_amount,
		)
	);
}
add_action( 'wp_ajax_formapress_get_summary_stats', 'formapress_get_summary_stats' );

/**
 * Get revenue data for chart (monthly or quarterly)
 */
function formapress_get_revenue_data() {
	check_ajax_referer( 'formapress_reports_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission refusée.', 'formapress-crm' ) ) );
	}

	$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
	$end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
	$period     = isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash( $_POST['period'] ) ) : 'monthly';

	// Query all invoices in range.
	$args = array(
		'post_type'      => 'crm_invoice',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'draft' ),
		'meta_query'     => array(
			array(
				'key'     => '_crm_invoice_date',
				'value'   => array( $start_date, $end_date ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			),
		),
		'orderby'        => 'meta_value',
		'meta_key'       => '_crm_invoice_date',
		'order'          => 'ASC',
	);

	$invoices = get_posts( $args );

	// Group by period.
	$data = array();
	foreach ( $invoices as $invoice ) {
		$invoice_date = get_post_meta( $invoice->ID, '_crm_invoice_date', true );
		$amount       = (float) get_post_meta( $invoice->ID, '_crm_invoice_amount', true );

		if ( 'monthly' === $period ) {
			$key = gmdate( 'Y-m', strtotime( $invoice_date ) );
		} else {
			// Quarterly.
			$date_obj = new DateTime( $invoice_date );
			$quarter  = ceil( (int) $date_obj->format( 'm' ) / 3 );
			$key      = $date_obj->format( 'Y' ) . '-Q' . $quarter;
		}

		if ( ! isset( $data[ $key ] ) ) {
			$data[ $key ] = 0;
		}
		$data[ $key ] += $amount;
	}

	// Format labels.
	$labels = array();
	$values = array();
	foreach ( $data as $key => $value ) {
		if ( 'monthly' === $period ) {
			$date_obj = DateTime::createFromFormat( 'Y-m', $key );
			$labels[] = $date_obj ? $date_obj->format( 'M Y' ) : $key;
		} else {
			$labels[] = $key;
		}
		$values[] = $value;
	}

	wp_send_json_success(
		array(
			'labels' => $labels,
			'values' => $values,
		)
	);
}
add_action( 'wp_ajax_formapress_get_revenue_data', 'formapress_get_revenue_data' );

/**
 * Get aging analysis data
 */
function formapress_get_aging_data() {
	check_ajax_referer( 'formapress_reports_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission refusée.', 'formapress-crm' ) ) );
	}

	// Query all unpaid invoices.
	$args = array(
		'post_type'      => 'crm_invoice',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'draft' ),
		'meta_query'     => array(
			array(
				'key'     => '_crm_invoice_payment_status',
				'value'   => array( 'pending', 'overdue', 'partial' ),
				'compare' => 'IN',
			),
		),
	);

	$invoices = get_posts( $args );
	$today    = gmdate( 'Y-m-d' );

	$buckets = array(
		'current' => 0, // 0-30 days.
		'30-60'   => 0,
		'60-90'   => 0,
		'90+'     => 0,
	);

	foreach ( $invoices as $invoice ) {
		$amount     = (float) get_post_meta( $invoice->ID, '_crm_invoice_amount', true );
		$due_date   = get_post_meta( $invoice->ID, '_crm_invoice_due_date', true );
		$payments   = get_post_meta( $invoice->ID, '_crm_invoice_payments', true );
		$payments   = is_array( $payments ) ? $payments : array();
		$total_paid = 0;

		foreach ( $payments as $payment ) {
			$total_paid += (float) ( $payment['amount'] ?? 0 );
		}

		$balance = $amount - $total_paid;

		if ( $balance <= 0 ) {
			continue;
		}

		if ( empty( $due_date ) ) {
			$buckets['current'] += $balance;
			continue;
		}

		$days_overdue = (int) ( ( strtotime( $today ) - strtotime( $due_date ) ) / DAY_IN_SECONDS );

		if ( $days_overdue <= 30 ) {
			$buckets['current'] += $balance;
		} elseif ( $days_overdue <= 60 ) {
			$buckets['30-60'] += $balance;
		} elseif ( $days_overdue <= 90 ) {
			$buckets['60-90'] += $balance;
		} else {
			$buckets['90+'] += $balance;
		}
	}

	wp_send_json_success(
		array(
			'labels' => array( 'En cours (0-30j)', '30-60 jours', '60-90 jours', '90+ jours' ),
			'values' => array_values( $buckets ),
		)
	);
}
add_action( 'wp_ajax_formapress_get_aging_data', 'formapress_get_aging_data' );

/**
 * Get funding source breakdown
 */
function formapress_get_funding_data() {
	check_ajax_referer( 'formapress_reports_nonce', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission refusée.', 'formapress-crm' ) ) );
	}

	$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
	$end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';

	// Query invoices in date range.
	$args = array(
		'post_type'      => 'crm_invoice',
		'posts_per_page' => -1,
		'post_status'    => array( 'publish', 'draft' ),
		'meta_query'     => array(
			array(
				'key'     => '_crm_invoice_date',
				'value'   => array( $start_date, $end_date ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			),
		),
	);

	$invoices = get_posts( $args );

	// Group by funding source.
	$funding_sources = array();

	foreach ( $invoices as $invoice ) {
		$amount     = (float) get_post_meta( $invoice->ID, '_crm_invoice_amount', true );
		$session_id = get_post_meta( $invoice->ID, '_crm_invoice_zqpm_session_id', true );

		// Try to get funding source from session if available.
		$funding_source = 'Direct';
		if ( $session_id && class_exists( 'zSession' ) ) {
			$session = new zSession( $session_id );
			// Check if session has funding type meta.
			$funding_type = get_post_meta( $session_id, 'zqpm_funding_type', true );
			if ( ! empty( $funding_type ) ) {
				$funding_source = $funding_type;
			}
		}

		// Also check company for funding info.
		$company_id = get_post_meta( $invoice->ID, '_crm_invoice_company_id', true );
		if ( $company_id ) {
			$company_type = get_post_meta( $company_id, 'crm_company_attributes_type', true );
			if ( ! empty( $company_type ) ) {
				$funding_source = $company_type;
			}
		}

		if ( ! isset( $funding_sources[ $funding_source ] ) ) {
			$funding_sources[ $funding_source ] = 0;
		}
		$funding_sources[ $funding_source ] += $amount;
	}

	// If no specific funding sources found, categorize as direct.
	if ( empty( $funding_sources ) ) {
		$funding_sources['Direct'] = 0;
	}

	wp_send_json_success(
		array(
			'labels' => array_keys( $funding_sources ),
			'values' => array_values( $funding_sources ),
		)
	);
}
add_action( 'wp_ajax_formapress_get_funding_data', 'formapress_get_funding_data' );
