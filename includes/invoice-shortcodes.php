<?php
/**
 * FormaPress CRM - Invoice Shortcodes
 *
 * Shortcodes for invoice document generation within ZQPM templates.
 * Pattern: [zinvoice_*]
 *
 * @package FormaPress_CRM
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Process invoice shortcodes in ZQPM template content.
 *
 * This hooks into the ZQPM shortcode processor to handle [zinvoice_*] shortcodes.
 *
 * @param string $content Template content.
 * @param string $context Context (unused).
 * @param array  $data    Data array (must contain 'invoice_id').
 * @return string Processed content with shortcodes replaced.
 */
function formapress_crm_process_invoice_shortcodes( $content, $context, $data ) {
	// Only process if invoice_id is provided.
	if ( ! isset( $data['invoice_id'] ) || empty( $data['invoice_id'] ) ) {
		return $content;
	}

	$invoice_id = intval( $data['invoice_id'] );

	// Get invoice post.
	$invoice = get_post( $invoice_id );
	if ( ! $invoice || 'crm_invoice' !== $invoice->post_type ) {
		return $content;
	}

	// Get all invoice meta.
	$invoice_number   = get_post_meta( $invoice_id, '_crm_invoice_number', true );
	$invoice_date     = get_post_meta( $invoice_id, '_crm_invoice_date', true );
	$invoice_due_date = get_post_meta( $invoice_id, '_crm_invoice_due_date', true );
	$amount           = get_post_meta( $invoice_id, '_crm_invoice_amount', true );
	$payment_status   = get_post_meta( $invoice_id, '_crm_invoice_payment_status', true );
	$funding_source   = get_post_meta( $invoice_id, '_crm_invoice_funding_source', true );
	$opco_name        = get_post_meta( $invoice_id, '_crm_invoice_opco_name', true );
	$agreement_number = get_post_meta( $invoice_id, '_crm_invoice_agreement_number', true );
	$company_id       = get_post_meta( $invoice_id, '_crm_invoice_company_id', true );
	$person_id        = get_post_meta( $invoice_id, '_crm_invoice_person_id', true );
	$zqpm_id          = get_post_meta( $invoice_id, '_crm_invoice_zqpm_id', true );
	$payments         = get_post_meta( $invoice_id, '_crm_invoice_payments', true );

	// Calculate balance.
	$total_paid = 0;
	if ( $payments && is_array( $payments ) ) {
		foreach ( $payments as $payment ) {
			$total_paid += floatval( $payment['amount'] );
		}
	}
	$balance = floatval( $amount ) - $total_paid;

	// Tax calculations (20% TVA default).
	$tva_rate   = 20;
	$amount_ht  = floatval( $amount );
	$amount_tva = $amount_ht * ( $tva_rate / 100 );
	$amount_ttc = $amount_ht + $amount_tva;

	// Status translations.
	$status_labels = array(
		'pending'   => __( 'En attente', 'formapress-crm' ),
		'partial'   => __( 'Paiement partiel', 'formapress-crm' ),
		'paid'      => __( 'Payée', 'formapress-crm' ),
		'cancelled' => __( 'Annulée', 'formapress-crm' ),
	);
	$status_label  = isset( $status_labels[ $payment_status ] ) ? $status_labels[ $payment_status ] : $payment_status;

	// Build shortcode replacements array.
	$replacements = array(
		// Invoice details.
		'[zinvoice_number]'            => $invoice_number,
		'[zinvoice_date]'              => $invoice_date ? date_i18n( 'd/m/Y', strtotime( $invoice_date ) ) : '',
		'[zinvoice_due_date]'          => $invoice_due_date ? date_i18n( 'd/m/Y', strtotime( $invoice_due_date ) ) : '',
		'[zinvoice_amount_ht]'         => number_format( $amount_ht, 2, ',', ' ' ) . ' €',
		'[zinvoice_amount_ttc]'        => number_format( $amount_ttc, 2, ',', ' ' ) . ' €',
		'[zinvoice_tva]'               => number_format( $amount_tva, 2, ',', ' ' ) . ' €',
		'[zinvoice_tva_rate]'          => $tva_rate . ' %',
		'[zinvoice_status]'            => $status_label,
		'[zinvoice_balance]'           => number_format( $balance, 2, ',', ' ' ) . ' €',
		'[zinvoice_payment_terms]'     => formapress_crm_get_payment_terms_text( $invoice_due_date ),

		// Company information.
		'[zinvoice_company_name]'      => formapress_crm_get_company_name( $company_id ),
		'[zinvoice_company_address]'   => formapress_crm_get_company_address( $company_id ),
		'[zinvoice_company_siret]'     => formapress_crm_get_company_siret( $company_id ),
		'[zinvoice_company_contact]'   => formapress_crm_get_company_contact( $company_id ),

		// Person information (individual client).
		'[zinvoice_person_name]'       => formapress_crm_get_person_name( $person_id ),
		'[zinvoice_person_email]'      => formapress_crm_get_person_email( $person_id ),
		'[zinvoice_person_phone]'      => formapress_crm_get_person_phone( $person_id ),

		// Training details (from ZQPM).
		'[zinvoice_formation_title]'   => formapress_crm_get_formation_title( $zqpm_id ),
		'[zinvoice_session_dates]'     => formapress_crm_get_session_dates( $zqpm_id ),
		'[zinvoice_session_location]'  => formapress_crm_get_session_location( $zqpm_id ),
		'[zinvoice_session_duration]'  => formapress_crm_get_session_duration( $zqpm_id ),
		'[zinvoice_trainee_count]'     => formapress_crm_get_trainee_count( $zqpm_id ),

		// OPCO information (if applicable).
		'[zinvoice_opco_name]'         => $opco_name,
		'[zinvoice_agreement_number]'  => $agreement_number,

		// Payment history table.
		'[zinvoice_payment_history]'   => formapress_crm_get_payment_history_table( $payments ),
		'[zinvoice_last_payment_date]' => formapress_crm_get_last_payment_date( $payments ),
		'[zinvoice_payment_method]'    => formapress_crm_get_last_payment_method( $payments ),

		// Line items table.
		'[zinvoice_line_items]'        => formapress_crm_get_invoice_line_items( $zqpm_id, $company_id, floatval( $amount ) ),
	);

	// Replace shortcodes.
	$content = str_replace( array_keys( $replacements ), array_values( $replacements ), $content );

	return $content;
}
add_filter( 'zqpm_process_shortcodes', 'formapress_crm_process_invoice_shortcodes', 10, 3 );

/**
 * Get payment terms text.
 *
 * @param string $due_date Due date (Y-m-d format).
 * @return string Payment terms text.
 */
function formapress_crm_get_payment_terms_text( $due_date ) {
	if ( empty( $due_date ) ) {
		return __( 'Payable à réception', 'formapress-crm' );
	}

	return sprintf(
		__( 'Payable avant le %s', 'formapress-crm' ),
		date_i18n( 'd/m/Y', strtotime( $due_date ) )
	);
}

/**
 * Get company name.
 *
 * @param int $company_id Company post ID.
 * @return string Company name.
 */
function formapress_crm_get_company_name( $company_id ) {
	if ( ! $company_id ) {
		return '';
	}

	$company = get_post( $company_id );
	if ( ! $company ) {
		return '';
	}

	$raison_sociale = get_post_meta( $company_id, 'crm_company_attributes_raison-sociale', true );
	return ! empty( $raison_sociale ) ? $raison_sociale : $company->post_title;
}

/**
 * Get company address (formatted).
 *
 * @param int $company_id Company post ID.
 * @return string Formatted address.
 */
function formapress_crm_get_company_address( $company_id ) {
	if ( ! $company_id ) {
		return '';
	}

	$adresse = get_post_meta( $company_id, 'crm_company_attributes_adresse', true );
	$cp      = get_post_meta( $company_id, 'crm_company_attributes_cp', true );
	$ville   = get_post_meta( $company_id, 'crm_company_attributes_ville', true );

	$address_parts = array_filter( array( $adresse, $cp, $ville ) );

	return implode( "\n", $address_parts );
}

/**
 * Get company SIRET.
 *
 * @param int $company_id Company post ID.
 * @return string SIRET number.
 */
function formapress_crm_get_company_siret( $company_id ) {
	if ( ! $company_id ) {
		return '';
	}

	return get_post_meta( $company_id, 'crm_company_attributes_siret', true );
}

/**
 * Get company contact person name.
 *
 * @param int $company_id Company post ID.
 * @return string Contact name.
 */
function formapress_crm_get_company_contact( $company_id ) {
	if ( ! $company_id ) {
		return '';
	}

	// Get referent from company (v2 system uses crm_person with type=referent).
	$referents = get_posts(
		array(
			'post_type'      => 'crm_person',
			'posts_per_page' => 1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'person_type',
					'field'    => 'slug',
					'terms'    => 'company_contact',
				),
			),
			'meta_query'     => array(
				array(
					'key'     => '_crm_company_id',
					'value'   => $company_id,
					'compare' => '=',
				),
			),
		)
	);

	if ( ! empty( $referents ) ) {
		return $referents[0]->post_title;
	}

	return '';
}

/**
 * Get person name.
 *
 * @param int $person_id Person post ID.
 * @return string Person name.
 */
function formapress_crm_get_person_name( $person_id ) {
	if ( ! $person_id ) {
		return '';
	}

	$person = get_post( $person_id );
	return $person ? $person->post_title : '';
}

/**
 * Get person email.
 *
 * @param int $person_id Person post ID.
 * @return string Email address.
 */
function formapress_crm_get_person_email( $person_id ) {
	if ( ! $person_id ) {
		return '';
	}

	return get_post_meta( $person_id, '_crm_email', true );
}

/**
 * Get person phone.
 *
 * @param int $person_id Person post ID.
 * @return string Phone number.
 */
function formapress_crm_get_person_phone( $person_id ) {
	if ( ! $person_id ) {
		return '';
	}

	return get_post_meta( $person_id, '_crm_telephone', true );
}

/**
 * Get formation title from ZQPM.
 *
 * @param int $zqpm_id ZQPM post ID.
 * @return string Formation title.
 */
function formapress_crm_get_formation_title( $zqpm_id ) {
	if ( ! $zqpm_id ) {
		return '';
	}

	$session_id = get_post_meta( $zqpm_id, 'zqpm_session_id', true );
	if ( ! $session_id ) {
		return '';
	}

	if ( class_exists( 'zSession' ) ) {
		$session = new zSession( $session_id );
		if ( $session && isset( $session->formation_id ) ) {
			$formation = get_post( $session->formation_id );
			return $formation ? $formation->post_title : '';
		}
	}

	return '';
}

/**
 * Get session dates (formatted range).
 *
 * @param int $zqpm_id ZQPM post ID.
 * @return string Date range.
 */
function formapress_crm_get_session_dates( $zqpm_id ) {
	if ( ! $zqpm_id ) {
		return '';
	}

	$session_id = get_post_meta( $zqpm_id, 'zqpm_session_id', true );
	if ( ! $session_id ) {
		return '';
	}

	if ( class_exists( 'zSession' ) ) {
		$session = new zSession( $session_id );
		if ( $session && ! empty( $session->dates_list ) ) {
			$first_date = reset( $session->dates_list );
			$last_date  = end( $session->dates_list );

			$start = isset( $first_date->post_name ) ? date_i18n( 'd/m/Y', strtotime( $first_date->post_name ) ) : '';
			$end   = isset( $last_date->post_name ) ? date_i18n( 'd/m/Y', strtotime( $last_date->post_name ) ) : '';

			if ( $start === $end ) {
				return $start;
			}

			return sprintf( '%s au %s', $start, $end );
		}
	}

	return '';
}

/**
 * Get session location.
 *
 * @param int $zqpm_id ZQPM post ID.
 * @return string Location.
 */
function formapress_crm_get_session_location( $zqpm_id ) {
	if ( ! $zqpm_id ) {
		return '';
	}

	$session_id = get_post_meta( $zqpm_id, 'zqpm_session_id', true );
	if ( ! $session_id ) {
		return '';
	}

	if ( class_exists( 'zSession' ) ) {
		$session = new zSession( $session_id );
		if ( $session && isset( $session->lieu ) ) {
			return $session->lieu;
		}
	}

	return '';
}

/**
 * Get session duration (hours/days).
 *
 * @param int $zqpm_id ZQPM post ID.
 * @return string Duration text.
 */
function formapress_crm_get_session_duration( $zqpm_id ) {
	if ( ! $zqpm_id ) {
		return '';
	}

	$session_id = get_post_meta( $zqpm_id, 'zqpm_session_id', true );
	if ( ! $session_id ) {
		return '';
	}

	if ( class_exists( 'zSession' ) ) {
		$session = new zSession( $session_id );
		if ( $session && ! empty( $session->dates_list ) ) {
			$jours  = count( $session->dates_list );
			$heures = 0;

			$zqpm_duree_demijournee_session = get_post_meta( $zqpm_id, 'zqpm_duree_demijournee_session', true );
			$zqpm_duree_demijournee_session = ! empty( $zqpm_duree_demijournee_session ) ? $zqpm_duree_demijournee_session : get_option( 'zqpm_hours_per_day', 3.5 );

			foreach ( $session->dates_list as $jour ) {
				if ( isset( $jour->morning ) && 'on' === $jour->morning ) {
					$duration = isset( $jour->morning_duration ) && ! empty( $jour->morning_duration ) ? $jour->morning_duration : $zqpm_duree_demijournee_session;
					$heures   = $heures + $duration;
				}
				if ( isset( $jour->afternoon ) && 'on' === $jour->afternoon ) {
					$duration = isset( $jour->afternoon_duration ) && ! empty( $jour->afternoon_duration ) ? $jour->afternoon_duration : $zqpm_duree_demijournee_session;
					$heures   = $heures + $duration;
				}
			}

			return sprintf(
				'%d %s (%s heures)',
				$jours,
				_n( 'jour', 'jours', $jours, 'formapress-crm' ),
				number_format( $heures, 1, ',', ' ' )
			);
		}
	}

	return '';
}

/**
 * Get trainee count for session.
 *
 * @param int $zqpm_id ZQPM post ID.
 * @return string Trainee count.
 */
function formapress_crm_get_trainee_count( $zqpm_id ) {
	if ( ! $zqpm_id ) {
		return '0';
	}

	$session_id = get_post_meta( $zqpm_id, 'zqpm_session_id', true );
	if ( ! $session_id ) {
		return '0';
	}

	$trainees = get_posts(
		array(
			'post_type'      => 'crm_person',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'tax_query'      => array(
				array(
					'taxonomy' => 'person_type',
					'field'    => 'slug',
					'terms'    => 'trainee',
				),
			),
			'meta_query'     => array(
				array(
					'key'     => '_crm_session_id',
					'value'   => $session_id,
					'compare' => '=',
				),
			),
			'fields'         => 'ids',
		)
	);

	return (string) count( $trainees );
}

/**
 * Get payment history table HTML.
 *
 * @param array $payments Array of payment records.
 * @return string HTML table.
 */
function formapress_crm_get_payment_history_table( $payments ) {
	if ( empty( $payments ) || ! is_array( $payments ) ) {
		return '<p>' . __( 'Aucun paiement enregistré.', 'formapress-crm' ) . '</p>';
	}

	$html  = '<table class="invoice-payment-history">';
	$html .= '<thead><tr>';
	$html .= '<th>' . __( 'Date', 'formapress-crm' ) . '</th>';
	$html .= '<th>' . __( 'Montant', 'formapress-crm' ) . '</th>';
	$html .= '<th>' . __( 'Méthode', 'formapress-crm' ) . '</th>';
	$html .= '<th>' . __( 'Référence', 'formapress-crm' ) . '</th>';
	$html .= '</tr></thead>';
	$html .= '<tbody>';

	foreach ( $payments as $payment ) {
		$html .= '<tr>';
		$html .= '<td>' . date_i18n( 'd/m/Y', strtotime( $payment['date'] ) ) . '</td>';
		$html .= '<td>' . number_format( floatval( $payment['amount'] ), 2, ',', ' ' ) . ' €</td>';
		$html .= '<td>' . formapress_crm_translate_payment_method( $payment['method'] ) . '</td>';
		$html .= '<td>' . esc_html( $payment['reference'] ) . '</td>';
		$html .= '</tr>';
	}

	$html .= '</tbody></table>';

	return $html;
}

/**
 * Get last payment date.
 *
 * @param array $payments Array of payment records.
 * @return string Last payment date.
 */
function formapress_crm_get_last_payment_date( $payments ) {
	if ( empty( $payments ) || ! is_array( $payments ) ) {
		return '';
	}

	$last_payment = end( $payments );
	return date_i18n( 'd/m/Y', strtotime( $last_payment['date'] ) );
}

/**
 * Get last payment method.
 *
 * @param array $payments Array of payment records.
 * @return string Last payment method.
 */
function formapress_crm_get_last_payment_method( $payments ) {
	if ( empty( $payments ) || ! is_array( $payments ) ) {
		return '';
	}

	$last_payment = end( $payments );
	return formapress_crm_translate_payment_method( $last_payment['method'] );
}

/**
 * Translate payment method.
 *
 * @param string $method Payment method key.
 * @return string Translated payment method.
 */
function formapress_crm_translate_payment_method( $method ) {
	$methods = array(
		'bank_transfer' => __( 'Virement bancaire', 'formapress-crm' ),
		'check'         => __( 'Chèque', 'formapress-crm' ),
		'card'          => __( 'Carte bancaire', 'formapress-crm' ),
		'cash'          => __( 'Espèces', 'formapress-crm' ),
		'other'         => __( 'Autre', 'formapress-crm' ),
	);

	return isset( $methods[ $method ] ) ? $methods[ $method ] : $method;
}

/**
 * Get invoice line items table.
 *
 * @param int   $zqpm_id    ZQPM post ID.
 * @param int   $company_id Company post ID.
 * @param float $total      Total amount.
 * @return string HTML table.
 */
function formapress_crm_get_invoice_line_items( $zqpm_id, $company_id, $total ) {
	if ( ! $zqpm_id ) {
		return '';
	}

	$session_id = get_post_meta( $zqpm_id, 'zqpm_session_id', true );
	if ( ! $session_id ) {
		return '';
	}

	$html  = '<table class="invoice-line-items">';
	$html .= '<thead><tr>';
	$html .= '<th>' . __( 'Description', 'formapress-crm' ) . '</th>';
	$html .= '<th>' . __( 'Quantité', 'formapress-crm' ) . '</th>';
	$html .= '<th>' . __( 'Prix unitaire', 'formapress-crm' ) . '</th>';
	$html .= '<th>' . __( 'Total', 'formapress-crm' ) . '</th>';
	$html .= '</tr></thead>';
	$html .= '<tbody>';

	// Get formation and session details.
	$formation_title = formapress_crm_get_formation_title( $zqpm_id );
	$session_dates   = formapress_crm_get_session_dates( $zqpm_id );
	$trainee_count   = formapress_crm_get_trainee_count( $zqpm_id );

	if ( class_exists( 'zSession' ) ) {
		$session = new zSession( $session_id );
		$jours   = $session && ! empty( $session->dates_list ) ? count( $session->dates_list ) : 1;

		// Get pricing information if available.
		if ( $company_id && class_exists( 'zqpmEntreprise' ) ) {
			$entreprise               = new zqpmEntreprise( $company_id );
			$entreprise_infos_session = $entreprise->get_infos_session( $zqpm_id );

			if ( isset( $entreprise_infos_session['mode_tarification'] ) ) {
				switch ( $entreprise_infos_session['mode_tarification'] ) {
					case 0:
						// Tarif horaire.
						$description = sprintf( __( 'Formation : %1$s (%2$s)', 'formapress-crm' ), $formation_title, $session_dates );
						$quantity    = $trainee_count . ' ' . __( 'stagiaires', 'formapress-crm' );
						$unit_price  = number_format( floatval( $total ) / floatval( $trainee_count ), 2, ',', ' ' );
						break;

					case 1:
						// Forfait jour.
						$description = sprintf( __( 'Formation : %1$s (%2$s)', 'formapress-crm' ), $formation_title, $session_dates );
						$quantity    = $jours . ' ' . _n( 'jour', 'jours', $jours, 'formapress-crm' );
						$unit_price  = number_format( floatval( $total ) / $jours, 2, ',', ' ' );
						break;

					case 2:
						// Forfait session.
						$description = sprintf( __( 'Formation : %1$s (%2$s)', 'formapress-crm' ), $formation_title, $session_dates );
						$quantity    = '1';
						$unit_price  = number_format( floatval( $total ), 2, ',', ' ' );
						break;

					default:
						$description = sprintf( __( 'Formation : %1$s (%2$s)', 'formapress-crm' ), $formation_title, $session_dates );
						$quantity    = '1';
						$unit_price  = number_format( floatval( $total ), 2, ',', ' ' );
						break;
				}
			} else {
				// Default line item.
				$description = sprintf( __( 'Formation : %1$s (%2$s)', 'formapress-crm' ), $formation_title, $session_dates );
				$quantity    = '1';
				$unit_price  = number_format( floatval( $total ), 2, ',', ' ' );
			}
		} else {
			// Individual - simple line item.
			$description = sprintf( __( 'Formation : %1$s (%2$s)', 'formapress-crm' ), $formation_title, $session_dates );
			$quantity    = '1';
			$unit_price  = number_format( floatval( $total ), 2, ',', ' ' );
		}

		$html .= '<tr>';
		$html .= '<td>' . esc_html( $description ) . '</td>';
		$html .= '<td>' . esc_html( $quantity ) . '</td>';
		$html .= '<td>' . esc_html( $unit_price ) . ' €</td>';
		$html .= '<td>' . number_format( floatval( $total ), 2, ',', ' ' ) . ' €</td>';
		$html .= '</tr>';
	}

	$html .= '</tbody></table>';

	return $html;
}
