<?php
/**
 * BPF Data Capture - Simple Financial Fields
 *
 * Captures data needed for annual "Bilan Pédagogique et Financier" without forcing complexity.
 *
 * Strategy:
 * - Simple by default: Just revenue + costs
 * - Advanced optional: Detailed funding breakdown
 * - Format for official template LATER when we get it
 *
 * @package FormaPress_CRM
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add financial meta box to zform_session CPT
 */
function fp_add_session_financial_metabox() {
	add_meta_box(
		'fp_session_financial',
		'💰 Financial Data (for annual report)',
		'fp_render_session_financial_metabox',
		'zform_session',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'fp_add_session_financial_metabox' );

/**
 * Render simple financial meta box
 */
function fp_render_session_financial_metabox( $post ) {
	// Get current values
	$revenue       = get_post_meta( $post->ID, '_session_revenue', true );
	$costs         = get_post_meta( $post->ID, '_session_costs', true );
	$profit        = $revenue && $costs ? ( $revenue - $costs ) : 0;
	$profit_margin = $revenue > 0 ? round( ( $profit / $revenue ) * 100 ) : 0;

	// Nonce for security
	wp_nonce_field( 'fp_save_session_financial', 'fp_session_financial_nonce' );

	?>
	<div class="fp-financial-simple">
		<p class="description" style="margin-bottom: 15px;">
			💡 <strong>These fields are optional but help generate your annual report automatically.</strong><br>
			Just enter total revenue and costs. You can add detailed breakdown later if needed.
		</p>

		<table class="form-table">
			<tr>
				<th><label for="fp_session_revenue">Revenue (Total)</label></th>
				<td>
					<input type="number"
							id="fp_session_revenue"
							name="fp_session_revenue"
							value="<?php echo esc_attr( $revenue ); ?>"
							step="0.01"
							min="0"
							class="regular-text"
							placeholder="4500"
							style="width: 200px;">
					<span class="description">€ (total received for this session)</span>
				</td>
			</tr>

			<tr>
				<th><label for="fp_session_costs">Costs (Total)</label></th>
				<td>
					<input type="number"
							id="fp_session_costs"
							name="fp_session_costs"
							value="<?php echo esc_attr( $costs ); ?>"
							step="0.01"
							min="0"
							class="regular-text"
							placeholder="1800"
							style="width: 200px;">
					<span class="description">€ (instructor, materials, overhead, etc.)</span>
				</td>
			</tr>

			<?php if ( $revenue && $costs ) : ?>
			<tr>
				<th>Profit Margin</th>
				<td>
					<strong style="color: <?php echo $profit > 0 ? '#46b450' : '#dc3232'; ?>; font-size: 16px;">
						€<?php echo number_format( $profit, 2 ); ?>
						(<?php echo $profit_margin; ?>%)
					</strong>
					<span class="description"> ← Auto-calculated</span>
				</td>
			</tr>
			<?php endif; ?>
		</table>

		<div class="fp-financial-advanced" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
			<details>
				<summary style="cursor: pointer; font-weight: 600;">
					📊 Advanced: Detailed Breakdown (Optional)
				</summary>

				<div style="margin-top: 15px;">
					<p class="description">
						For more detailed annual reports, you can track funding sources (OPCO, CPF, etc.)
						and cost breakdown. <strong>This is completely optional.</strong>
					</p>

					<p>
						<a href="#" class="button" id="fp-open-funding-breakdown">
							Manage Funding Sources
						</a>
						<a href="#" class="button" id="fp-open-cost-breakdown">
							Manage Cost Breakdown
						</a>
					</p>

					<p class="description">
						💡 <strong>Tip:</strong> Start simple with just total revenue/costs.
						Add detailed breakdown later when you need advanced analytics.
					</p>
				</div>
			</details>
		</div>

		<?php if ( ! $revenue && ! $costs ) : ?>
		<div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
			<p style="margin: 0;">
				<strong>⚠️ Missing financial data</strong><br>
				This session won't appear in annual report statistics until you add revenue and costs.
			</p>
		</div>
		<?php endif; ?>
	</div>

	<style>
		.fp-financial-simple input[type="number"] {
			font-size: 16px;
		}
		.fp-financial-advanced summary:hover {
			color: #0073aa;
		}
		.fp-financial-advanced details[open] {
			border-top: 1px solid #ddd;
			padding-top: 15px;
			margin-top: 10px;
		}
	</style>

	<script>
	jQuery(document).ready(function($) {
		// Auto-calculate profit when revenue or costs change
		$('#fp_session_revenue, #fp_session_costs').on('input', function() {
			var revenue = parseFloat($('#fp_session_revenue').val()) || 0;
			var costs = parseFloat($('#fp_session_costs').val()) || 0;
			var profit = revenue - costs;
			var margin = revenue > 0 ? Math.round((profit / revenue) * 100) : 0;

			// Show live preview (could add a preview element)
			console.log('Profit: €' + profit.toFixed(2) + ' (' + margin + '%)');
		});

		// Placeholder for advanced breakdown modals
		$('#fp-open-funding-breakdown, #fp-open-cost-breakdown').on('click', function(e) {
			e.preventDefault();
			alert('Advanced breakdown UI coming in Week 7-8!\n\nFor now, just enter total revenue and costs.');
		});
	});
	</script>
	<?php
}

/**
 * Save financial data
 */
function fp_save_session_financial_data( $post_id ) {
	// Security checks
	if ( ! isset( $_POST['fp_session_financial_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( $_POST['fp_session_financial_nonce'], 'fp_save_session_financial' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Save revenue
	if ( isset( $_POST['fp_session_revenue'] ) ) {
		$revenue = floatval( $_POST['fp_session_revenue'] );
		update_post_meta( $post_id, '_session_revenue', $revenue );
	}

	// Save costs
	if ( isset( $_POST['fp_session_costs'] ) ) {
		$costs = floatval( $_POST['fp_session_costs'] );
		update_post_meta( $post_id, '_session_costs', $costs );
	}

	// Auto-calculate profit
	$revenue = get_post_meta( $post_id, '_session_revenue', true );
	$costs   = get_post_meta( $post_id, '_session_costs', true );
	if ( $revenue && $costs ) {
		$profit = $revenue - $costs;
		update_post_meta( $post_id, '_session_profit', $profit );
	}
}
add_action( 'save_post_zform_session', 'fp_save_session_financial_data' );

/**
 * Get BPF readiness statistics for a year
 */
function fp_get_bpf_readiness_stats( $year = null ) {
	if ( ! $year ) {
		$year = date( 'Y' );
	}

	// Query all sessions for the year
	$args = array(
		'post_type'      => 'zform_session',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'meta_query'     => array(
			array(
				'key'     => '_session_start_date',
				'value'   => array( $year . '-01-01', $year . '-12-31' ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			),
		),
	);

	$sessions            = get_posts( $args );
	$total               = count( $sessions );
	$with_financial_data = 0;
	$missing_sessions    = array();

	foreach ( $sessions as $session ) {
		$revenue = get_post_meta( $session->ID, '_session_revenue', true );
		$costs   = get_post_meta( $session->ID, '_session_costs', true );

		if ( $revenue && $costs ) {
			++$with_financial_data;
		} else {
			$missing_sessions[] = array(
				'id'      => $session->ID,
				'title'   => $session->post_title,
				'date'    => get_post_meta( $session->ID, '_session_start_date', true ),
				'missing' => array(
					'revenue' => ! $revenue,
					'costs'   => ! $costs,
				),
			);
		}
	}

	return array(
		'year'                  => $year,
		'total_sessions'        => $total,
		'sessions_with_data'    => $with_financial_data,
		'sessions_missing_data' => count( $missing_sessions ),
		'percentage_complete'   => $total > 0 ? round( ( $with_financial_data / $total ) * 100 ) : 0,
		'missing_sessions'      => $missing_sessions,
	);
}

/**
 * Get financial summary for BPF
 */
function fp_get_bpf_financial_summary( $year = null ) {
	if ( ! $year ) {
		$year = date( 'Y' );
	}

	// Query all sessions with financial data
	$args = array(
		'post_type'      => 'zform_session',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'     => '_session_start_date',
				'value'   => array( $year . '-01-01', $year . '-12-31' ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			),
			array(
				'key'     => '_session_revenue',
				'compare' => 'EXISTS',
			),
		),
	);

	$sessions = get_posts( $args );

	$total_revenue = 0;
	$total_costs   = 0;
	$session_count = 0;

	foreach ( $sessions as $session ) {
		$revenue = get_post_meta( $session->ID, '_session_revenue', true );
		$costs   = get_post_meta( $session->ID, '_session_costs', true );

		if ( $revenue ) {
			$total_revenue += floatval( $revenue );
			++$session_count;
		}
		if ( $costs ) {
			$total_costs += floatval( $costs );
		}
	}

	$total_profit  = $total_revenue - $total_costs;
	$profit_margin = $total_revenue > 0 ? round( ( $total_profit / $total_revenue ) * 100, 2 ) : 0;

	return array(
		'year'                        => $year,
		'sessions_count'              => $session_count,
		'total_revenue'               => $total_revenue,
		'total_costs'                 => $total_costs,
		'total_profit'                => $total_profit,
		'profit_margin'               => $profit_margin,
		'average_revenue_per_session' => $session_count > 0 ? round( $total_revenue / $session_count, 2 ) : 0,
		'average_profit_per_session'  => $session_count > 0 ? round( $total_profit / $session_count, 2 ) : 0,
	);
}

/**
 * Check if session has complete financial data
 */
function fp_session_has_financial_data( $session_id ) {
	$revenue = get_post_meta( $session_id, '_session_revenue', true );
	$costs   = get_post_meta( $session_id, '_session_costs', true );

	return ! empty( $revenue ) && ! empty( $costs );
}

/**
 * Get sessions missing financial data
 */
function fp_get_sessions_missing_financial_data( $year = null ) {
	$stats = fp_get_bpf_readiness_stats( $year );
	return $stats['missing_sessions'];
}
