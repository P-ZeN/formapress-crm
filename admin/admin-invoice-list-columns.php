<?php
/**
 * FormaPress CRM - Invoice List Table Customizations
 *
 * Custom columns, filters, and bulk actions for invoice admin list.
 *
 * @package FormaPress_CRM
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add custom columns to invoice list table.
 *
 * @param array $columns Existing columns.
 * @return array Modified columns.
 */
function formapress_crm_invoice_columns( $columns ) {
	// Remove default columns we'll replace.
	unset( $columns['date'] );

	// Build new column structure.
	$new_columns = array(
		'cb'             => $columns['cb'],
		'title'          => $columns['title'],
		'invoice_number' => __( 'N° Facture', 'formapress-crm' ),
		'client'         => __( 'Client', 'formapress-crm' ),
		'amount'         => __( 'Montant', 'formapress-crm' ),
		'status'         => __( 'Statut', 'formapress-crm' ),
		'balance'        => __( 'Solde', 'formapress-crm' ),
		'pdf_status'     => __( 'PDF', 'formapress-crm' ),
		'email_status'   => __( 'Email', 'formapress-crm' ),
		'invoice_date'   => __( 'Date', 'formapress-crm' ),
	);

	return $new_columns;
}
add_filter( 'manage_crm_invoice_posts_columns', 'formapress_crm_invoice_columns' );

/**
 * Populate custom columns with data.
 *
 * @param string $column  Column name.
 * @param int    $post_id Post ID.
 */
function formapress_crm_invoice_custom_column( $column, $post_id ) {
	switch ( $column ) {
		case 'invoice_number':
			$invoice_number = get_post_meta( $post_id, '_crm_invoice_number', true );
			if ( $invoice_number ) {
				printf(
					'<strong><a href="%s">%s</a></strong>',
					esc_url( admin_url( 'admin.php?page=formapress-crm-edit-invoice&id=' . $post_id ) ),
					esc_html( $invoice_number )
				);
			} else {
				echo '—';
			}
			break;

		case 'client':
			$company_id = get_post_meta( $post_id, '_crm_invoice_company_id', true );
			$person_id  = get_post_meta( $post_id, '_crm_invoice_person_id', true );

			if ( $company_id ) {
				$company = get_post( $company_id );
				if ( $company ) {
					$raison_sociale = get_post_meta( $company_id, 'crm_company_attributes_raison-sociale', true );
					if ( empty( $raison_sociale ) ) {
						$raison_sociale = $company->post_title;
					}
					printf(
						'<a href="%s">🏢 %s</a>',
						esc_url( get_edit_post_link( $company_id ) ),
						esc_html( $raison_sociale )
					);
				} else {
					echo '—';
				}
			} elseif ( $person_id ) {
				$person = get_post( $person_id );
				if ( $person ) {
					printf(
						'<a href="%s">👤 %s</a>',
						esc_url( get_edit_post_link( $person_id ) ),
						esc_html( $person->post_title )
					);
				} else {
					echo '—';
				}
			} else {
				echo '—';
			}
			break;

		case 'amount':
			$amount = get_post_meta( $post_id, '_crm_invoice_amount', true );
			if ( $amount ) {
				echo '<strong>' . esc_html( number_format( floatval( $amount ), 2, ',', ' ' ) ) . ' €</strong>';
			} else {
				echo '—';
			}
			break;

		case 'status':
			$payment_status = get_post_meta( $post_id, '_crm_invoice_payment_status', true );
			$invoice_date   = get_post_meta( $post_id, '_crm_invoice_date', true );

			// Check if overdue (30+ days past invoice date and not paid).
			$is_overdue = false;
			if ( $invoice_date && 'paid' !== $payment_status && 'cancelled' !== $payment_status ) {
				$days_since = floor( ( time() - strtotime( $invoice_date ) ) / DAY_IN_SECONDS );
				$is_overdue = $days_since > 30;
			}

			$status_labels = array(
				'pending'   => __( 'En attente', 'formapress-crm' ),
				'partial'   => __( 'Partiel', 'formapress-crm' ),
				'paid'      => __( 'Payée', 'formapress-crm' ),
				'cancelled' => __( 'Annulée', 'formapress-crm' ),
			);

			$status_label = isset( $status_labels[ $payment_status ] ) ? $status_labels[ $payment_status ] : $payment_status;

			// Add overdue indicator.
			if ( $is_overdue ) {
				echo '<span class="invoice-status-badge status-overdue">⚠️ ' . esc_html__( 'En retard', 'formapress-crm' ) . '</span>';
			} else {
				$status_class = 'status-' . sanitize_html_class( $payment_status );
				echo '<span class="invoice-status-badge ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span>';
			}
			break;

		case 'balance':
			$amount   = floatval( get_post_meta( $post_id, '_crm_invoice_amount', true ) );
			$payments = get_post_meta( $post_id, '_crm_invoice_payments', true );

			$total_paid = 0;
			if ( $payments && is_array( $payments ) ) {
				foreach ( $payments as $payment ) {
					$total_paid += floatval( $payment['amount'] );
				}
			}

			$balance = $amount - $total_paid;

			if ( $balance > 0 ) {
				echo '<span style="color: #d63638; font-weight: 600;">' . esc_html( number_format( $balance, 2, ',', ' ' ) ) . ' €</span>';
			} elseif ( 0 === $balance ) {
				echo '<span style="color: #00a32a;">0,00 €</span>';
			} else {
				echo '<span>' . esc_html( number_format( $balance, 2, ',', ' ' ) ) . ' €</span>';
			}
			break;

		case 'pdf_status':
			// Check if PDF exists.
			$pdf_info = function_exists( 'formapress_crm_get_invoice_pdf_info' ) ? formapress_crm_get_invoice_pdf_info( $post_id ) : false;

			if ( $pdf_info && $pdf_info['exists'] ) {
				printf(
					'<a href="%s" target="_blank" title="%s"><span class="dashicons dashicons-media-document" style="color: #00a32a;"></span></a>',
					esc_url( $pdf_info['url'] ),
					esc_attr__( 'Télécharger le PDF', 'formapress-crm' )
				);
			} else {
				echo '<span class="dashicons dashicons-minus" style="color: #dba617;" title="' . esc_attr__( 'Aucun PDF', 'formapress-crm' ) . '"></span>';
			}
			break;

		case 'email_status':
			// Check if invoice has been sent.
			$email_history = function_exists( 'formapress_crm_get_invoice_email_history' ) ? formapress_crm_get_invoice_email_history( $post_id ) : array();
			$email_count   = count( $email_history );

			if ( $email_count > 0 ) {
				$last_sent = $email_history[ $email_count - 1 ];
				printf(
					'<span class="dashicons dashicons-email" style="color: #00a32a;" title="%s"></span> <span style="font-size: 11px; color: #50575e;">%d</span>',
					esc_attr( sprintf( __( 'Dernier envoi: %s', 'formapress-crm' ), date_i18n( 'd/m/Y H:i', strtotime( $last_sent['date'] ) ) ) ),
					$email_count
				);
			} else {
				echo '<span class="dashicons dashicons-minus" style="color: #dba617;" title="' . esc_attr__( 'Jamais envoyée', 'formapress-crm' ) . '"></span>';
			}
			break;

		case 'invoice_date':
			$invoice_date = get_post_meta( $post_id, '_crm_invoice_date', true );
			if ( $invoice_date ) {
				echo esc_html( date_i18n( 'd/m/Y', strtotime( $invoice_date ) ) );
			} else {
				echo '—';
			}
			break;
	}
}
add_action( 'manage_crm_invoice_posts_custom_column', 'formapress_crm_invoice_custom_column', 10, 2 );

/**
 * Make columns sortable.
 *
 * @param array $columns Sortable columns.
 * @return array Modified sortable columns.
 */
function formapress_crm_invoice_sortable_columns( $columns ) {
	$columns['invoice_number'] = 'invoice_number';
	$columns['amount']         = 'amount';
	$columns['invoice_date']   = 'invoice_date';
	$columns['status']         = 'status';

	return $columns;
}
add_filter( 'manage_edit-crm_invoice_sortable_columns', 'formapress_crm_invoice_sortable_columns' );

/**
 * Handle custom column sorting.
 *
 * @param WP_Query $query Current query.
 */
function formapress_crm_invoice_sortable_orderby( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$orderby = $query->get( 'orderby' );

	switch ( $orderby ) {
		case 'invoice_number':
			$query->set( 'meta_key', '_crm_invoice_number' );
			$query->set( 'orderby', 'meta_value' );
			break;

		case 'amount':
			$query->set( 'meta_key', '_crm_invoice_amount' );
			$query->set( 'orderby', 'meta_value_num' );
			break;

		case 'invoice_date':
			$query->set( 'meta_key', '_crm_invoice_date' );
			$query->set( 'orderby', 'meta_value' );
			break;

		case 'status':
			$query->set( 'meta_key', '_crm_invoice_payment_status' );
			$query->set( 'orderby', 'meta_value' );
			break;
	}
}
add_action( 'pre_get_posts', 'formapress_crm_invoice_sortable_orderby' );

/**
 * Add filters to invoice list.
 */
function formapress_crm_invoice_filters() {
	global $typenow;

	if ( 'crm_invoice' !== $typenow ) {
		return;
	}

	// Payment status filter.
	$status_filter = isset( $_GET['payment_status'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_status'] ) ) : '';

	$statuses = array(
		''          => __( 'Tous les statuts', 'formapress-crm' ),
		'pending'   => __( 'En attente', 'formapress-crm' ),
		'partial'   => __( 'Paiement partiel', 'formapress-crm' ),
		'paid'      => __( 'Payées', 'formapress-crm' ),
		'cancelled' => __( 'Annulées', 'formapress-crm' ),
		'overdue'   => __( '⚠️ En retard', 'formapress-crm' ),
	);

	echo '<select name="payment_status" id="payment-status-filter">';
	foreach ( $statuses as $value => $label ) {
		printf(
			'<option value="%s" %s>%s</option>',
			esc_attr( $value ),
			selected( $status_filter, $value, false ),
			esc_html( $label )
		);
	}
	echo '</select>';

	// Funding source filter.
	$funding_filter = isset( $_GET['funding_source'] ) ? sanitize_text_field( wp_unslash( $_GET['funding_source'] ) ) : '';

	$funding_sources = array(
		''           => __( 'Toutes les sources', 'formapress-crm' ),
		'company'    => __( 'Entreprise', 'formapress-crm' ),
		'individual' => __( 'Individuel', 'formapress-crm' ),
		'opco'       => __( 'OPCO', 'formapress-crm' ),
		'mixed'      => __( 'Mixte', 'formapress-crm' ),
	);

	echo '<select name="funding_source" id="funding-source-filter">';
	foreach ( $funding_sources as $value => $label ) {
		printf(
			'<option value="%s" %s>%s</option>',
			esc_attr( $value ),
			selected( $funding_filter, $value, false ),
			esc_html( $label )
		);
	}
	echo '</select>';
}
add_action( 'restrict_manage_posts', 'formapress_crm_invoice_filters' );

/**
 * Apply filters to invoice query.
 *
 * @param WP_Query $query Current query.
 */
function formapress_crm_invoice_filter_query( $query ) {
	global $typenow, $pagenow;

	if ( ! is_admin() || 'edit.php' !== $pagenow || 'crm_invoice' !== $typenow || ! $query->is_main_query() ) {
		return;
	}

	$meta_query = array();

	// Payment status filter.
	if ( isset( $_GET['payment_status'] ) && '' !== $_GET['payment_status'] ) {
		$payment_status = sanitize_text_field( wp_unslash( $_GET['payment_status'] ) );

		if ( 'overdue' === $payment_status ) {
			// Special handling for overdue - need date comparison.
			$thirty_days_ago = date( 'Y-m-d', strtotime( '-30 days' ) );

			$meta_query[] = array(
				'key'     => '_crm_invoice_date',
				'value'   => $thirty_days_ago,
				'compare' => '<',
				'type'    => 'DATE',
			);

			$meta_query[] = array(
				'key'     => '_crm_invoice_payment_status',
				'value'   => array( 'pending', 'partial' ),
				'compare' => 'IN',
			);
		} else {
			$meta_query[] = array(
				'key'     => '_crm_invoice_payment_status',
				'value'   => $payment_status,
				'compare' => '=',
			);
		}
	}

	// Funding source filter.
	if ( isset( $_GET['funding_source'] ) && '' !== $_GET['funding_source'] ) {
		$funding_source = sanitize_text_field( wp_unslash( $_GET['funding_source'] ) );

		$meta_query[] = array(
			'key'     => '_crm_invoice_funding_source',
			'value'   => $funding_source,
			'compare' => '=',
		);
	}

	if ( ! empty( $meta_query ) ) {
		$meta_query['relation'] = 'AND';
		$query->set( 'meta_query', $meta_query );
	}
}
add_action( 'pre_get_posts', 'formapress_crm_invoice_filter_query' );

/**
 * Add bulk actions to invoice list.
 *
 * @param array $actions Existing bulk actions.
 * @return array Modified bulk actions.
 */
function formapress_crm_invoice_bulk_actions( $actions ) {
	$actions['mark_paid']  = __( 'Marquer comme payées', 'formapress-crm' );
	$actions['export_csv'] = __( 'Exporter en CSV', 'formapress-crm' );

	return $actions;
}
add_filter( 'bulk_actions-edit-crm_invoice', 'formapress_crm_invoice_bulk_actions' );

/**
 * Handle bulk actions.
 *
 * @param string $redirect_to Redirect URL.
 * @param string $doaction     Action being taken.
 * @param array  $post_ids     Post IDs.
 * @return string Modified redirect URL.
 */
function formapress_crm_invoice_handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
	if ( 'mark_paid' === $doaction ) {
		foreach ( $post_ids as $post_id ) {
			update_post_meta( $post_id, '_crm_invoice_payment_status', 'paid' );
			update_post_meta( $post_id, '_crm_invoice_payment_date', current_time( 'Y-m-d' ) );
		}

		$redirect_to = add_query_arg( 'bulk_marked_paid', count( $post_ids ), $redirect_to );
	}

	if ( 'export_csv' === $doaction ) {
		formapress_crm_export_invoices_csv( $post_ids );
		exit;
	}

	return $redirect_to;
}
add_filter( 'handle_bulk_actions-edit-crm_invoice', 'formapress_crm_invoice_handle_bulk_actions', 10, 3 );

/**
 * Display admin notice after bulk action.
 */
function formapress_crm_invoice_bulk_action_notices() {
	if ( isset( $_GET['bulk_marked_paid'] ) ) {
		$count = intval( $_GET['bulk_marked_paid'] );
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			sprintf(
				/* translators: %d: number of invoices marked as paid */
				esc_html( _n( '%d facture marquée comme payée.', '%d factures marquées comme payées.', $count, 'formapress-crm' ) ),
				$count
			)
		);
	}
}
add_action( 'admin_notices', 'formapress_crm_invoice_bulk_action_notices' );

/**
 * Export invoices to CSV.
 *
 * @param array $post_ids Invoice post IDs.
 */
function formapress_crm_export_invoices_csv( $post_ids ) {
	// Set headers for CSV download.
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=factures-' . date( 'Y-m-d' ) . '.csv' );

	$output = fopen( 'php://output', 'w' );

	// Add BOM for Excel UTF-8 compatibility.
	fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

	// CSV header.
	fputcsv(
		$output,
		array(
			'N° Facture',
			'Date',
			'Client',
			'Montant HT',
			'Statut',
			'Solde',
			'Source de financement',
		),
		';'
	);

	// CSV data.
	foreach ( $post_ids as $post_id ) {
		$invoice_number = get_post_meta( $post_id, '_crm_invoice_number', true );
		$invoice_date   = get_post_meta( $post_id, '_crm_invoice_date', true );
		$amount         = get_post_meta( $post_id, '_crm_invoice_amount', true );
		$payment_status = get_post_meta( $post_id, '_crm_invoice_payment_status', true );
		$funding_source = get_post_meta( $post_id, '_crm_invoice_funding_source', true );
		$company_id     = get_post_meta( $post_id, '_crm_invoice_company_id', true );
		$person_id      = get_post_meta( $post_id, '_crm_invoice_person_id', true );

		// Get client name.
		$client_name = '';
		if ( $company_id ) {
			$company     = get_post( $company_id );
			$client_name = $company ? $company->post_title : '';
		} elseif ( $person_id ) {
			$person      = get_post( $person_id );
			$client_name = $person ? $person->post_title : '';
		}

		// Calculate balance.
		$payments   = get_post_meta( $post_id, '_crm_invoice_payments', true );
		$total_paid = 0;
		if ( $payments && is_array( $payments ) ) {
			foreach ( $payments as $payment ) {
				$total_paid += floatval( $payment['amount'] );
			}
		}
		$balance = floatval( $amount ) - $total_paid;

		// Status translation.
		$status_labels = array(
			'pending'   => 'En attente',
			'partial'   => 'Paiement partiel',
			'paid'      => 'Payée',
			'cancelled' => 'Annulée',
		);
		$status_label  = isset( $status_labels[ $payment_status ] ) ? $status_labels[ $payment_status ] : $payment_status;

		fputcsv(
			$output,
			array(
				$invoice_number,
				$invoice_date ? date_i18n( 'd/m/Y', strtotime( $invoice_date ) ) : '',
				$client_name,
				number_format( floatval( $amount ), 2, ',', '' ),
				$status_label,
				number_format( $balance, 2, ',', '' ),
				$funding_source,
			),
			';'
		);
	}

	fclose( $output );
}
