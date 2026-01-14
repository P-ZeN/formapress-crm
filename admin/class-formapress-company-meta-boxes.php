<?php
/**
 * Company Meta Boxes
 *
 * Handles meta boxes for crm_company post type.
 * Displays company attributes based on schema.
 *
 * @package FormaPress_CRM
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Company Meta Boxes Class
 */
class FormaPress_Company_Meta_Boxes {

	/**
	 * Hook into WordPress
	 */
	public static function init() {
		add_action( 'add_meta_boxes_crm_company', array( __CLASS__, 'register_meta_boxes' ), 20 );
		add_action( 'save_post_crm_company', array( __CLASS__, 'save_meta_boxes' ), 10, 2 );
	}

	/**
	 * Register meta boxes for crm_company
	 *
	 * @param WP_Post $post Current post object.
	 */
	public static function register_meta_boxes( $post ) {
		// Company Information meta box.
		add_meta_box(
			'company_information',
			__( 'Informations Entreprise', 'formapress-crm' ),
			array( __CLASS__, 'render_information_meta_box' ),
			'crm_company',
			'normal',
			'high'
		);
	}

	/**
	 * Render Company Information meta box
	 *
	 * Displays all company attribute fields from schema.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public static function render_information_meta_box( $post ) {
		// Add nonce for security.
		wp_nonce_field( 'formapress_company_meta_box', 'formapress_company_meta_box_nonce' );

		// Get company attribute schema.
		$schema = get_option( 'crm_company_attributes', array() );

		if ( empty( $schema ) ) {
			echo '<p>' . esc_html__( 'No company attributes schema found.', 'formapress-crm' ) . '</p>';
			return;
		}

		// Sort by order.
		uasort(
			$schema,
			function ( $a, $b ) {
				$order_a = isset( $a['order'] ) ? (int) $a['order'] : 999;
				$order_b = isset( $b['order'] ) ? (int) $b['order'] : 999;
				return $order_a - $order_b;
			}
		);

		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ( $schema as $field_key => $field_config ) {
			$field_name  = $field_config['name'] ?? $field_key;
			$field_type  = $field_config['type'] ?? 'text';
			$required    = ! empty( $field_config['required'] ) ? ' *' : '';
			$locked      = ! empty( $field_config['locked'] ) ? ' <em>(v1 core field)</em>' : '';
			$meta_key    = 'crm_company_attributes_' . sanitize_key( $field_key );
			$field_value = get_post_meta( $post->ID, $meta_key, true );

			echo '<tr>';
			echo '<th scope="row"><label for="' . esc_attr( $meta_key ) . '">' . esc_html( $field_name . $required ) . $locked . '</label></th>';
			echo '<td>';

			switch ( $field_type ) {
				case 'textarea':
					echo '<textarea id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '" rows="3" class="large-text">' . esc_textarea( $field_value ) . '</textarea>';
					break;

				case 'select':
					$options = isset( $field_config['options'] ) ? $field_config['options'] : '';
					echo '<select id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '" class="regular-text">';
					echo '<option value="">' . esc_html__( '— Select —', 'formapress-crm' ) . '</option>';

					if ( ! empty( $options ) ) {
						$options_array = explode( "\n", $options );
						foreach ( $options_array as $option ) {
							$parts    = explode( '|', $option );
							$value    = isset( $parts[0] ) ? trim( $parts[0] ) : '';
							$label    = isset( $parts[1] ) ? trim( $parts[1] ) : $value;
							$selected = selected( $field_value, $value, false );
							echo '<option value="' . esc_attr( $value ) . '" ' . $selected . '>' . esc_html( $label ) . '</option>';
						}
					}
					echo '</select>';
					break;

				case 'email':
					echo '<input type="email" id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( $field_value ) . '" class="regular-text" />';
					break;

				case 'url':
					echo '<input type="url" id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( $field_value ) . '" class="regular-text" />';
					break;

				case 'tel':
					echo '<input type="tel" id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( $field_value ) . '" class="regular-text" />';
					break;

				case 'number':
					echo '<input type="number" id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( $field_value ) . '" class="regular-text" />';
					break;

				default: // text.
					echo '<input type="text" id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( $field_value ) . '" class="regular-text" />';
					break;
			}

			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Save company meta box data
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_meta_boxes( $post_id, $post ) {
		// Check nonce.
		if ( ! isset( $_POST['formapress_company_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['formapress_company_meta_box_nonce'], 'formapress_company_meta_box' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Get schema.
		$schema = get_option( 'crm_company_attributes', array() );

		if ( empty( $schema ) ) {
			return;
		}

		// Save all attribute fields.
		foreach ( $schema as $field_key => $field_config ) {
			$meta_key = 'crm_company_attributes_' . sanitize_key( $field_key );

			if ( isset( $_POST[ $meta_key ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $meta_key ] ) );
				update_post_meta( $post_id, $meta_key, $value );
			} else {
				// Checkbox or empty value - delete meta.
				delete_post_meta( $post_id, $meta_key );
			}
		}
	}
}

FormaPress_Company_Meta_Boxes::init();
