<?php
/**
 * Formapress CRM - Opportunity Attributes Admin Page
 *
 * @package FormapressCRM
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

?>

<h2><?php esc_attr_e( 'Opportunity Attributes', 'formapress-crm' ); ?></h2>
<p><?php esc_attr_e( 'Manage dynamic attributes for opportunities. These fields will appear in the opportunity editor and are accessible via shortcodes like [crm_opportunity_field_name].', 'formapress-crm' ); ?></p>

<?php
$crm_opportunity_attributes = get_option( 'crm_opportunity_attributes', array() );
?>

<table class="widefat" id="opportunity-attrib-table">
	<thead>
		<tr>
			<th class="row-title" style="width:20px"></th>
			<th class="row-title ztooltip_box"><?php esc_attr_e( 'Name', 'formapress-crm' ); ?><br><span
					class="description ztooltip_text"><?php esc_attr_e( 'Name of the field', 'formapress-crm' ); ?></span></th>
			<th class="row-title ztooltip_box"><?php esc_attr_e( 'Type', 'formapress-crm' ); ?><br><span
					class="description ztooltip_text"><?php esc_attr_e( 'Type of the field', 'formapress-crm' ); ?></span></th>
			<th class="row-title ztooltip_box"><?php esc_attr_e( 'Options', 'formapress-crm' ); ?><br><span
					class="description ztooltip_text"><?php esc_attr_e( 'Options of field (for select/radio/checkbox types)', 'formapress-crm' ); ?></span></th>
			<th class="r"></th>
		</tr>
	</thead>
	<tbody>
		<?php
		$i = 0;
		if ( ! empty( $crm_opportunity_attributes ) ) {
			foreach ( $crm_opportunity_attributes as $key => $attribute ) {
				if ( ! empty( $attribute['name'] ) ) {
					$slug       = $key;
					$attr_order = isset( $attribute['order'] ) ? $attribute['order'] : $i;
					?>
		<tr valign="top" class="form-attrib-row <?php echo ( $i % 2 ? '' : 'alternate' ); ?>">
			<td scope="row" class="grab">
				<input type="hidden" class="small-text" name="crm_opportunity_attributes[<?php echo wp_kses_post( $slug ); ?>][order]"
					value="<?php echo wp_kses_post( $attr_order ); ?>" />
				<span class="dashicons dashicons-menu zform-grab"></span>
			</td>

			<td>
				<input type="text" name="crm_opportunity_attributes[<?php echo wp_kses_post( $slug ); ?>][name]"
					value="<?php echo wp_kses_post( $attribute['name'] ); ?>" />
			</td>
			<td>
					<?php $attr_type = $attribute['type']; ?>
				<select name="crm_opportunity_attributes[<?php echo wp_kses_post( $slug ); ?>][type]" class="selector_types">
					<?php zform_attributes_select_options( $attr_type, false ); ?>
				</select>
			</td>
			<td>
					<?php
					$options = isset( $attribute['options'] ) ? $attribute['options'] : '';
					switch ( $attr_type ) {
						case 'checkbox':
							echo '<input type="text" name="crm_opportunity_attributes[' . wp_kses_post( $slug ) . '][options]" placeholder="Label" value="' . wp_kses_post( $options ) . '">';
							break;

						case 'select':
						case 'radio':
							echo '<textarea name="crm_opportunity_attributes[' . wp_kses_post( $slug ) . '][options]" placeholder="Valeur|Label (une option par ligne)">' . wp_kses_post( $options ) . '</textarea>';
							break;

						case 'list':
							echo '<textarea name="crm_opportunity_attributes[' . wp_kses_post( $slug ) . '][options]" placeholder="Options for formatted list">' . wp_kses_post( $options ) . '</textarea>';
							break;

						case 'text':
						case 'number':
						case 'date':
						case 'wyswyg':
						case 'image':
						case 'video':
						case 'download':
						default:
							echo '<input type="hidden" name="crm_opportunity_attributes[' . wp_kses_post( $slug ) . '][options]" value="' . wp_kses_post( $options ) . '"/>';
							break;
					}
					?>
			</td>
			<td>
				<span class="dashicons dashicons-trash zform-trash" style="cursor: pointer"
					onclick="zformDelRow(this)"></span>
			</td>
		</tr>

					<?php
					++$i;
				}
			}
		}
		?>

	</tbody>
</table>

<p><input class="button-secondary add-attr-btn" type="button" data-table="opportunity-attrib-table" data-option="crm_opportunity_attributes"
		value="<?php esc_attr_e( 'Add attribute', 'formapress-crm' ); ?>" /><br></p>
<p><?php submit_button(); ?></p>
