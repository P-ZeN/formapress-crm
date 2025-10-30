<?php
/**
 * Financial Data Model - Enhanced with Funding Sources
 *
 * This defines the structure for tracking revenue with granular funding source breakdown
 * Key differentiator vs. competitors: detailed OPCO, CPF, and multi-source tracking
 *
 * @package formapress-crm
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Get funding source schema
 * Defines all possible funding sources for French training sessions
 */
function fp_get_funding_sources_schema() {
	return array(
		'company_direct' => array(
			'label'  => 'Entreprise - Paiement direct',
			'icon'   => '🏢',
			'fields' => array(
				'company_id'     => array(
					'type'      => 'post_select',
					'post_type' => 'zqpm_entreprise',
					'required'  => true,
				),
				'amount'         => array(
					'type'     => 'number',
					'required' => true,
					'min'      => 0,
				),
				'invoice_id'     => array(
					'type'      => 'post_select',
					'post_type' => 'crm_invoice',
					'required'  => false,
				),
				'payment_status' => array(
					'type'    => 'select',
					'options' => array(
						'pending' => 'En attente',
						'partial' => 'Partiellement payé',
						'paid'    => 'Payé',
						'overdue' => 'En retard',
					),
				),
			),
		),

		'opco'           => array(
			'label'  => 'OPCO - Prise en charge',
			'icon'   => '💼',
			'fields' => array(
				'opco_name'        => array(
					'type'     => 'select',
					'options'  => array(
						'afdas'          => 'Afdas',
						'uniformation'   => 'Uniformation',
						'atlas'          => 'Atlas',
						'opco_ep'        => 'OPCO EP (Entreprises de proximité)',
						'constructys'    => 'Constructys',
						'akto'           => 'Akto',
						'ocapiat'        => 'Ocapiat',
						'opco_2i'        => 'OPCO 2i',
						'opco_sante'     => 'OPCO Santé',
						'opcommerce'     => 'OPCO Commerce',
						'opco_mobilites' => 'OPCO Mobilités',
					),
					'required' => true,
				),
				'opco_id'          => array(
					'type'      => 'post_select',
					'post_type' => 'zqpm_financeur',
					'required'  => false,
				),
				'agreement_number' => array(
					'type'        => 'text',
					'placeholder' => 'PC-2024-12345',
					'required'    => true,
				),
				'amount_approved'  => array(
					'type'     => 'number',
					'required' => true,
					'min'      => 0,
				),
				'amount_paid'      => array(
					'type'     => 'number',
					'required' => false,
					'min'      => 0,
				),
				'approval_date'    => array(
					'type'     => 'date',
					'required' => true,
				),
				'payment_date'     => array(
					'type'     => 'date',
					'required' => false,
				),
				'payment_status'   => array(
					'type'    => 'select',
					'options' => array(
						'pending' => 'En attente',
						'partial' => 'Partiellement payé',
						'paid'    => 'Soldé',
					),
				),
				'invoice_id'       => array(
					'type'      => 'post_select',
					'post_type' => 'crm_invoice',
					'required'  => false,
				),
			),
		),

		'cpf'            => array(
			'label'  => 'CPF - Comptes Personnels de Formation',
			'icon'   => '👤',
			'fields' => array(
				'trainee_count'      => array(
					'type'     => 'number',
					'required' => true,
					'min'      => 1,
				),
				'amount_per_trainee' => array(
					'type'     => 'number',
					'required' => true,
					'min'      => 0,
				),
				'total_amount'       => array(
					'type'    => 'calculated',
					'formula' => 'trainee_count * amount_per_trainee',
				),
				'cpf_references'     => array(
					'type'        => 'textarea',
					'placeholder' => 'Un numéro CPF par ligne',
					'required'    => false,
				),
			),
		),

		'pole_emploi'    => array(
			'label'  => 'Pôle Emploi - AIF',
			'icon'   => '🏛️',
			'fields' => array(
				'trainee_count' => array(
					'type'     => 'number',
					'required' => true,
					'min'      => 1,
				),
				'amount'        => array(
					'type'     => 'number',
					'required' => true,
					'min'      => 0,
				),
				'aif_reference' => array(
					'type'        => 'text',
					'placeholder' => 'AIF-2024-789',
					'required'    => false,
				),
			),
		),

		'region'         => array(
			'label'  => 'Conseil Régional',
			'icon'   => '🗺️',
			'fields' => array(
				'region_name'  => array(
					'type'     => 'text',
					'required' => true,
				),
				'program_name' => array(
					'type'        => 'text',
					'placeholder' => 'Nom du dispositif',
					'required'    => false,
				),
				'amount'       => array(
					'type'     => 'number',
					'required' => true,
					'min'      => 0,
				),
				'reference'    => array(
					'type'     => 'text',
					'required' => false,
				),
			),
		),

		'agefiph'        => array(
			'label'  => 'Agefiph',
			'icon'   => '♿',
			'fields' => array(
				'trainee_count' => array(
					'type'     => 'number',
					'required' => true,
					'min'      => 1,
				),
				'amount'        => array(
					'type'     => 'number',
					'required' => true,
					'min'      => 0,
				),
				'reference'     => array(
					'type'     => 'text',
					'required' => false,
				),
			),
		),

		'self_funded'    => array(
			'label'  => 'Auto-financement',
			'icon'   => '💶',
			'fields' => array(
				'trainee_count' => array(
					'type'     => 'number',
					'required' => true,
					'min'      => 1,
				),
				'amount'        => array(
					'type'     => 'number',
					'required' => true,
					'min'      => 0,
				),
			),
		),

		'other'          => array(
			'label'  => 'Autre',
			'icon'   => '📋',
			'fields' => array(
				'source_name' => array(
					'type'     => 'text',
					'required' => true,
				),
				'amount'      => array(
					'type'     => 'number',
					'required' => true,
					'min'      => 0,
				),
				'reference'   => array(
					'type'     => 'text',
					'required' => false,
				),
			),
		),
	);
}

/**
 * Get funding breakdown for a session
 *
 * @param int $session_id Session post ID
 * @return array Funding breakdown
 */
function fp_get_session_funding_breakdown( $session_id ) {
	$breakdown = get_post_meta( $session_id, '_session_revenue_breakdown', true );

	if ( empty( $breakdown ) || ! is_array( $breakdown ) ) {
		return array();
	}

	return $breakdown;
}

/**
 * Save funding breakdown for a session
 *
 * @param int   $session_id Session post ID
 * @param array $breakdown Funding breakdown data
 * @return bool Success
 */
function fp_save_session_funding_breakdown( $session_id, $breakdown ) {
	// Validate total matches
	$total_from_sources = fp_calculate_total_from_sources( $breakdown );
	$declared_total     = get_post_meta( $session_id, '_session_total_revenue', true );

	if ( abs( $total_from_sources - floatval( $declared_total ) ) > 0.01 ) {
		// Totals don't match - return error
		return new WP_Error(
			'funding_total_mismatch',
			sprintf(
				'Le total des sources (€%s) ne correspond pas au CA total (€%s)',
				number_format( $total_from_sources, 2 ),
				number_format( $declared_total, 2 )
			)
		);
	}

	return update_post_meta( $session_id, '_session_revenue_breakdown', $breakdown );
}

/**
 * Calculate total revenue from funding sources
 *
 * @param array $breakdown Funding breakdown
 * @return float Total amount
 */
function fp_calculate_total_from_sources( $breakdown ) {
	$total = 0;

	foreach ( $breakdown as $source_type => $source_data ) {
		switch ( $source_type ) {
			case 'company_direct':
			case 'opco':
			case 'pole_emploi':
			case 'region':
			case 'agefiph':
			case 'self_funded':
			case 'other':
				$total += isset( $source_data['amount'] ) ? floatval( $source_data['amount'] ) : 0;
				break;

			case 'cpf':
				if ( isset( $source_data['trainee_count'] ) && isset( $source_data['amount_per_trainee'] ) ) {
					$total += floatval( $source_data['trainee_count'] ) * floatval( $source_data['amount_per_trainee'] );
				}
				break;
		}
	}

	return $total;
}

/**
 * Get funding statistics for bilan generation
 *
 * @param int $year Year to analyze
 * @return array Funding statistics
 */
function fp_get_funding_statistics( $year ) {
	$sessions = get_posts(
		array(
			'post_type'      => 'zform_session',
			'posts_per_page' => -1,
			'date_query'     => array(
				array( 'year' => $year ),
			),
		)
	);

	$stats = array(
		'total_revenue'         => 0,
		'by_source'             => array(),
		'opco_details'          => array(),
		'cpf_usage'             => array(
			'trainee_count' => 0,
			'total_amount'  => 0,
		),
		'external_funding_rate' => 0,
	);

	foreach ( $sessions as $session ) {
		$breakdown = fp_get_session_funding_breakdown( $session->ID );

		foreach ( $breakdown as $source_type => $source_data ) {
			// Initialize source if not exists
			if ( ! isset( $stats['by_source'][ $source_type ] ) ) {
				$stats['by_source'][ $source_type ] = array(
					'amount'        => 0,
					'session_count' => 0,
					'details'       => array(),
				);
			}

			// Add to totals
			$amount = 0;
			switch ( $source_type ) {
				case 'cpf':
					$amount                               = floatval( $source_data['trainee_count'] ?? 0 ) * floatval( $source_data['amount_per_trainee'] ?? 0 );
					$stats['cpf_usage']['trainee_count'] += intval( $source_data['trainee_count'] ?? 0 );
					$stats['cpf_usage']['total_amount']  += $amount;
					break;

				case 'opco':
					$amount    = floatval( $source_data['amount'] ?? 0 );
					$opco_name = $source_data['opco_name'] ?? 'unknown';

					if ( ! isset( $stats['opco_details'][ $opco_name ] ) ) {
						$stats['opco_details'][ $opco_name ] = array(
							'amount'                => 0,
							'agreement_count'       => 0,
							'average_payment_delay' => 0,
						);
					}

					$stats['opco_details'][ $opco_name ]['amount'] += $amount;
					++$stats['opco_details'][ $opco_name ]['agreement_count'];

					// Calculate payment delay if dates available
					if ( ! empty( $source_data['approval_date'] ) && ! empty( $source_data['payment_date'] ) ) {
						$approval   = strtotime( $source_data['approval_date'] );
						$payment    = strtotime( $source_data['payment_date'] );
						$delay_days = ( $payment - $approval ) / ( 60 * 60 * 24 );
						$stats['opco_details'][ $opco_name ]['payment_delays'][] = $delay_days;
					}
					break;

				default:
					$amount = floatval( $source_data['amount'] ?? 0 );
			}

			$stats['by_source'][ $source_type ]['amount'] += $amount;
			++$stats['by_source'][ $source_type ]['session_count'];
			$stats['total_revenue'] += $amount;
		}
	}

	// Calculate averages and percentages
	foreach ( $stats['by_source'] as $source_type => &$source_stats ) {
		$source_stats['percentage'] = $stats['total_revenue'] > 0
			? ( $source_stats['amount'] / $stats['total_revenue'] ) * 100
			: 0;
	}

	// Calculate average payment delays for OPCOs
	foreach ( $stats['opco_details'] as $opco_name => &$opco_data ) {
		if ( ! empty( $opco_data['payment_delays'] ) ) {
			$opco_data['average_payment_delay'] = array_sum( $opco_data['payment_delays'] ) / count( $opco_data['payment_delays'] );
		}
		unset( $opco_data['payment_delays'] ); // Remove raw data
	}

	// Calculate external funding rate (everything except self-funded)
	$external_funding = $stats['total_revenue'];
	if ( isset( $stats['by_source']['self_funded'] ) ) {
		$external_funding -= $stats['by_source']['self_funded']['amount'];
	}
	$stats['external_funding_rate'] = $stats['total_revenue'] > 0
		? ( $external_funding / $stats['total_revenue'] ) * 100
		: 0;

	return $stats;
}

/**
 * Validate session has complete funding data
 *
 * @param int $session_id Session post ID
 * @return array|true True if valid, array of errors if not
 */
function fp_validate_session_funding( $session_id ) {
	$errors = array();

	$total_revenue = get_post_meta( $session_id, '_session_total_revenue', true );
	$breakdown     = fp_get_session_funding_breakdown( $session_id );

	// Check if funding sources exist
	if ( empty( $breakdown ) ) {
		$errors[] = 'Aucune source de financement définie';
		return $errors;
	}

	// Check if totals match
	$total_from_sources = fp_calculate_total_from_sources( $breakdown );
	if ( abs( $total_from_sources - floatval( $total_revenue ) ) > 0.01 ) {
		$errors[] = sprintf(
			'Total sources (€%s) ≠ CA total (€%s)',
			number_format( $total_from_sources, 2 ),
			number_format( $total_revenue, 2 )
		);
	}

	// Check OPCO agreements have required fields
	if ( isset( $breakdown['opco'] ) ) {
		$opco = $breakdown['opco'];
		if ( empty( $opco['agreement_number'] ) ) {
			$errors[] = 'OPCO: Numéro d\'accord manquant';
		}
		if ( empty( $opco['approval_date'] ) ) {
			$errors[] = 'OPCO: Date d\'accord manquante';
		}
	}

	return empty( $errors ) ? true : $errors;
}
