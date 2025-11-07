<?php
/**
 * FormaPress Person Meta Boxes
 *
 * Manages admin meta boxes for crm_person CPT using v2 Person Manager API.
 *
 * @package FormaPress_CRM
 * @subpackage Admin
 * @since 2.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class FormaPress_Person_Meta_Boxes {

	/**
	 * Initialize meta boxes
	 */
	public static function init() {
		// Remove default taxonomy metaboxes before our custom ones are added.
		add_action( 'admin_menu', array( __CLASS__, 'remove_default_metaboxes' ), 999 );
		add_action( 'add_meta_boxes_crm_person', array( __CLASS__, 'register_meta_boxes' ), 20 );
		add_action( 'save_post_crm_person', array( __CLASS__, 'save_meta_boxes' ), 10, 2 );
	}

	/**
	 * Remove default taxonomy metaboxes
	 */
	public static function remove_default_metaboxes() {
		// Try all possible metabox IDs that WordPress might use for person_type taxonomy.
		remove_meta_box( 'tagsdiv-person_type', 'crm_person', 'side' );
		remove_meta_box( 'tagsdiv-person_type', 'crm_person', 'normal' );
		remove_meta_box( 'person_typediv', 'crm_person', 'side' );
		remove_meta_box( 'person_typediv', 'crm_person', 'normal' );
	}

	/**
	 * Register all meta boxes for crm_person
	 */
	public static function register_meta_boxes() {
		// Company Association
		add_meta_box(
			'formapress_person_company',
			__( 'Company Association', 'formapress-crm' ),
			array( __CLASS__, 'render_company_meta_box' ),
			'crm_person',
			'side',
			'default'
		);

		// Person Types
		add_meta_box(
			'formapress_person_types',
			__( 'Person Types', 'formapress-crm' ),
			array( __CLASS__, 'render_types_meta_box' ),
			'crm_person',
			'side',
			'default'
		);

		// All Fields (dynamic based on schema - includes core + custom fields as user configured)
		add_meta_box(
			'formapress_person_fields',
			__( 'Person Information', 'formapress-crm' ),
			array( __CLASS__, 'render_fields_meta_box' ),
			'crm_person',
			'normal',
			'high'
		);

		// V1 Legacy Links (for migrated records)
		add_meta_box(
			'formapress_person_v1_links',
			__( 'V1 Legacy Links', 'formapress-crm' ),
			array( __CLASS__, 'render_v1_links_meta_box' ),
			'crm_person',
			'side',
			'default'
		);
	}

	/**
	 * Render Person Details meta box
	 *
	 * @param WP_Post $post Current post object
	 */
	public static function render_details_meta_box( $post ) {
		wp_nonce_field( 'formapress_person_meta', 'formapress_person_meta_nonce' );

		$person_data = FormaPress_Person_Manager::get_person( $post->ID );

		?>
		<table class="form-table">
			<tr>
				<th><label for="crm_civilite"><?php _e( 'Civility', 'formapress-crm' ); ?></label></th>
				<td>
					<select id="crm_civilite" name="crm_civilite" class="regular-text">
						<option value=""><?php _e( '-- Select --', 'formapress-crm' ); ?></option>
						<option value="M." <?php selected( $person_data['civilite'] ?? '', 'M.' ); ?>>M.</option>
						<option value="Mme" <?php selected( $person_data['civilite'] ?? '', 'Mme' ); ?>>Mme</option>
						<option value="Mlle" <?php selected( $person_data['civilite'] ?? '', 'Mlle' ); ?>>Mlle</option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="crm_prenom"><?php _e( 'First Name', 'formapress-crm' ); ?> <span class="required">*</span></label></th>
				<td>
					<input type="text" id="crm_prenom" name="crm_prenom" value="<?php echo esc_attr( $person_data['prenom'] ?? '' ); ?>" class="regular-text" required />
				</td>
			</tr>
			<tr>
				<th><label for="crm_nom"><?php _e( 'Last Name', 'formapress-crm' ); ?> <span class="required">*</span></label></th>
				<td>
					<input type="text" id="crm_nom" name="crm_nom" value="<?php echo esc_attr( $person_data['nom'] ?? '' ); ?>" class="regular-text" required />
				</td>
			</tr>
			<tr>
				<th><label for="crm_email"><?php _e( 'Email', 'formapress-crm' ); ?> <span class="required">*</span></label></th>
				<td>
					<input type="email" id="crm_email" name="crm_email" value="<?php echo esc_attr( $person_data['email'] ?? '' ); ?>" class="regular-text" required />
					<p class="description"><?php _e( 'Email must be unique across all persons.', 'formapress-crm' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="crm_telephone"><?php _e( 'Phone', 'formapress-crm' ); ?></label></th>
				<td>
					<input type="tel" id="crm_telephone" name="crm_telephone" value="<?php echo esc_attr( $person_data['telephone'] ?? '' ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th><label for="crm_adresse"><?php _e( 'Address', 'formapress-crm' ); ?></label></th>
				<td>
					<textarea id="crm_adresse" name="crm_adresse" rows="3" class="large-text"><?php echo esc_textarea( $person_data['adresse'] ?? '' ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th><label for="crm_code_postal"><?php _e( 'Postal Code', 'formapress-crm' ); ?></label></th>
				<td>
					<input type="text" id="crm_code_postal" name="crm_code_postal" value="<?php echo esc_attr( $person_data['code_postal'] ?? '' ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th><label for="crm_ville"><?php _e( 'City', 'formapress-crm' ); ?></label></th>
				<td>
					<input type="text" id="crm_ville" name="crm_ville" value="<?php echo esc_attr( $person_data['ville'] ?? '' ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th><label for="crm_fonction"><?php _e( 'Job Title / Function', 'formapress-crm' ); ?></label></th>
				<td>
					<input type="text" id="crm_fonction" name="crm_fonction" value="<?php echo esc_attr( $person_data['fonction'] ?? '' ); ?>" class="regular-text" />
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render Company Association meta box
	 *
	 * @param WP_Post $post Current post object
	 */
	public static function render_company_meta_box( $post ) {
		$person_data = FormaPress_Person_Manager::get_person( $post->ID );
		$company_id  = $person_data['company_id'] ?? '';

		// Get all companies
		$companies = get_posts(
			array(
				'post_type'      => 'crm_company',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		?>
		<p>
			<label for="crm_company_id"><?php _e( 'Select Company', 'formapress-crm' ); ?>:</label>
			<select id="crm_company_id" name="crm_company_id" class="widefat">
				<option value=""><?php _e( '-- No Company --', 'formapress-crm' ); ?></option>
				<?php foreach ( $companies as $company ) : ?>
					<option value="<?php echo esc_attr( $company->ID ); ?>" <?php selected( $company_id, $company->ID ); ?>>
						<?php echo esc_html( $company->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<?php if ( ! empty( $company_id ) ) : ?>
			<p>
				<a href="<?php echo esc_url( get_edit_post_link( $company_id ) ); ?>" class="button button-small">
					<?php _e( 'Edit Company', 'formapress-crm' ); ?>
				</a>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render Person Types meta box
	 *
	 * @param WP_Post $post Current post object
	 */
	public static function render_types_meta_box( $post ) {
		$person_types = FormaPress_Person_Manager::get_person_types( $post->ID );

		// Get all available person types
		$available_types = get_terms(
			array(
				'taxonomy'   => 'person_type',
				'hide_empty' => false,
			)
		);

		?>
		<div class="person-types-checklist">
			<?php foreach ( $available_types as $type ) : ?>
				<label style="display: block; margin-bottom: 8px;">
					<input
						type="checkbox"
						name="crm_person_types[]"
						value="<?php echo esc_attr( $type->slug ); ?>"
						<?php checked( in_array( $type->slug, $person_types ) ); ?>
					/>
					<?php echo esc_html( $type->name ); ?>
				</label>
			<?php endforeach; ?>
		</div>
		<p class="description"><?php _e( 'A person can have multiple types.', 'formapress-crm' ); ?></p>
		<?php
	}

	/**
	 * Render All Fields meta box
	 * Shows ALL fields from schema as user configured them (no filtering)
	 *
	 * @param WP_Post $post Current post object.
	 */
	public static function render_fields_meta_box( $post ) {
		wp_nonce_field( 'formapress_person_meta', 'formapress_person_meta_nonce' );

		$person_types = FormaPress_Person_Manager::get_person_types( $post->ID );

		if ( empty( $person_types ) ) {
			echo '<p>' . esc_html__( 'Select at least one person type to display fields.', 'formapress-crm' ) . '</p>';
			return;
		}

		// Render ALL fields for each person type.
		foreach ( $person_types as $type ) {
			// Get FULL schema (don't filter out core fields).
			$schema = ZForm_Attributes_Core::get_attributes_schema( $type );

			if ( empty( $schema ) ) {
				continue;
			}

			// Sort by order property.
			uasort(
				$schema,
				function ( $a, $b ) {
					$order_a = isset( $a['order'] ) ? (int) $a['order'] : 999;
					$order_b = isset( $b['order'] ) ? (int) $b['order'] : 999;
					return $order_a <=> $order_b;
				}
			);

			$type_term = get_term_by( 'slug', $type, 'person_type' );
			echo '<h3>' . esc_html( $type_term->name ) . ' ' . esc_html__( 'Fields', 'formapress-crm' ) . '</h3>';

			echo '<table class="form-table"><tbody>';
			$odd = false;

			foreach ( $schema as $field_slug => $field_config ) {
				// Skip helptext fields in edit context.
				if ( isset( $field_config['type'] ) && 'helptext' === $field_config['type'] ) {
					continue;
				}

				// ALL fields stored as attributes: crm_person_{type}_attributs_{slug}.
				$meta_key    = 'crm_person_' . $type . '_attributs_' . $field_slug;
				$field_value = get_post_meta( $post->ID, $meta_key, true );

				// All fields use same naming pattern.
				$field_name = 'crm_' . $type . '_attributs[' . $field_slug . ']';
				$field_id   = 'crm_' . $type . '_' . $field_slug;

				// Render field using core system.
				echo '<tr' . ( $odd ? ' class="alternate"' : '' ) . '>';
				echo '<th scope="row"><label for="' . esc_attr( $field_id ) . '">' . esc_html( $field_config['name'] ) . '</label></th>';
				echo '<td>';
				ZForm_Attributes_Core::render_field( $field_slug, $field_config, $field_value, $field_name, $field_id, $odd );
				echo '</td>';
				echo '</tr>';

				$odd = ! $odd;
			}

			echo '</tbody></table>';
		}
	}

	/**
	 * Render a single custom field
	 *
	 * @param string $type Person type slug
	 * @param string $field_slug Field slug
	 * @param array  $field_config Field configuration
	 * @param mixed  $value Current field value
	 */
	private static function render_custom_field( $type, $field_slug, $field_config, $value ) {
		$field_name = 'crm_custom_' . $type . '_' . $field_slug;
		$field_id   = $field_name;
		$field_type = $field_config['type'] ?? 'text';
		$required   = ! empty( $field_config['required'] );

		switch ( $field_type ) {
			case 'textarea':
				?>
				<textarea
					id="<?php echo esc_attr( $field_id ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					rows="4"
					class="large-text"
					<?php echo $required ? 'required' : ''; ?>
				><?php echo esc_textarea( $value ); ?></textarea>
				<?php
				break;

			case 'wysiwyg':
				wp_editor(
					$value,
					$field_id,
					array(
						'textarea_name' => $field_name,
						'textarea_rows' => 10,
						'media_buttons' => false,
						'teeny'         => true,
					)
				);
				break;

			case 'select':
				?>
				<select
					id="<?php echo esc_attr( $field_id ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					class="regular-text"
					<?php echo $required ? 'required' : ''; ?>
				>
					<option value=""><?php _e( '-- Select --', 'formapress-crm' ); ?></option>
					<?php if ( ! empty( $field_config['choices'] ) ) : ?>
						<?php foreach ( $field_config['choices'] as $choice_value => $choice_label ) : ?>
							<option value="<?php echo esc_attr( $choice_value ); ?>" <?php selected( $value, $choice_value ); ?>>
								<?php echo esc_html( $choice_label ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
				<?php
				break;

			case 'radio':
				?>
				<div class="radio-group">
					<?php if ( ! empty( $field_config['choices'] ) ) : ?>
						<?php foreach ( $field_config['choices'] as $choice_value => $choice_label ) : ?>
							<label style="display: inline-block; margin-right: 15px;">
								<input
									type="radio"
									name="<?php echo esc_attr( $field_name ); ?>"
									value="<?php echo esc_attr( $choice_value ); ?>"
									<?php checked( $value, $choice_value ); ?>
									<?php echo $required ? 'required' : ''; ?>
								/>
								<?php echo esc_html( $choice_label ); ?>
							</label>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				<?php
				break;

			case 'checkbox':
				?>
				<label>
					<input
						type="checkbox"
						id="<?php echo esc_attr( $field_id ); ?>"
						name="<?php echo esc_attr( $field_name ); ?>"
						value="1"
						<?php checked( $value, '1' ); ?>
					/>
					<?php echo ! empty( $field_config['checkbox_label'] ) ? esc_html( $field_config['checkbox_label'] ) : __( 'Yes', 'formapress-crm' ); ?>
				</label>
				<?php
				break;

			case 'date':
				?>
				<input
					type="date"
					id="<?php echo esc_attr( $field_id ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					class="regular-text"
					<?php echo $required ? 'required' : ''; ?>
				/>
				<?php
				break;

			case 'email':
				?>
				<input
					type="email"
					id="<?php echo esc_attr( $field_id ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					class="regular-text"
					<?php echo $required ? 'required' : ''; ?>
				/>
				<?php
				break;

			case 'tel':
				?>
				<input
					type="tel"
					id="<?php echo esc_attr( $field_id ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					class="regular-text"
					<?php echo $required ? 'required' : ''; ?>
				/>
				<?php
				break;

			case 'url':
				?>
				<input
					type="url"
					id="<?php echo esc_attr( $field_id ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					class="regular-text"
					<?php echo $required ? 'required' : ''; ?>
				/>
				<?php
				break;

			case 'number':
				?>
				<input
					type="number"
					id="<?php echo esc_attr( $field_id ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					class="regular-text"
					step="any"
					<?php echo $required ? 'required' : ''; ?>
				/>
				<?php
				break;

			case 'file':
				?>
				<input
					type="text"
					id="<?php echo esc_attr( $field_id ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					class="regular-text"
					<?php echo $required ? 'required' : ''; ?>
				/>
				<button type="button" class="button crm-upload-file" data-target="<?php echo esc_attr( $field_id ); ?>">
					<?php _e( 'Upload File', 'formapress-crm' ); ?>
				</button>
				<?php if ( $value ) : ?>
					<p><a href="<?php echo esc_url( $value ); ?>" target="_blank"><?php _e( 'View File', 'formapress-crm' ); ?></a></p>
				<?php endif; ?>
				<?php
				break;

			case 'text':
			default:
				?>
				<input
					type="text"
					id="<?php echo esc_attr( $field_id ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					class="regular-text"
					<?php echo $required ? 'required' : ''; ?>
				/>
				<?php
				break;
		}
	}

	/**
	 * Save meta boxes data
	 *
	 * @param int     $post_id Post ID
	 * @param WP_Post $post Post object
	 */
	/**
	 * Save meta box data
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 */
	public static function save_meta_boxes( $post_id, $post ) {
		// Check nonce.
		if ( ! isset( $_POST['formapress_person_meta_nonce'] ) ||
			! wp_verify_nonce( wp_unslash( $_POST['formapress_person_meta_nonce'] ), 'formapress_person_meta' ) ) {
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

		// Get person types.
		$person_types = isset( $_POST['crm_person_types'] ) && is_array( $_POST['crm_person_types'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['crm_person_types'] ) )
			: FormaPress_Person_Manager::get_person_types( $post_id );

		// Save ALL fields as attributes for each person type.
		foreach ( $person_types as $type ) {
			$field_name_key = 'crm_' . $type . '_attributs';

			if ( isset( $_POST[ $field_name_key ] ) && is_array( $_POST[ $field_name_key ] ) ) {
				$attributes = array();

				foreach ( wp_unslash( $_POST[ $field_name_key ] ) as $field_slug => $value ) {
					if ( is_array( $value ) ) {
						$attributes[ $field_slug ] = array_map( 'sanitize_text_field', $value );
					} else {
						$attributes[ $field_slug ] = sanitize_text_field( $value );
					}
				}

				// Save using core attributes system.
				if ( ! empty( $attributes ) ) {
					ZForm_Attributes_Core::save_attributes(
						$post_id,
						$type,
						$attributes,
						'crm_person_' . $type . '_attributs_'
					);
				}
			}
		}

		// Update _crm_email index for find_by_email() lookups.
		$email_field = null;
		foreach ( $person_types as $type ) {
			$field_name_key = 'crm_' . $type . '_attributs';
			if ( isset( $_POST[ $field_name_key ]['mail'] ) ) {
				$email_field = sanitize_email( wp_unslash( $_POST[ $field_name_key ]['mail'] ) );
				break;
			} elseif ( isset( $_POST[ $field_name_key ]['email'] ) ) {
				$email_field = sanitize_email( wp_unslash( $_POST[ $field_name_key ]['email'] ) );
				break;
			}
		}
		if ( $email_field ) {
			update_post_meta( $post_id, '_crm_email', $email_field );
		}
	}


	public static function render_v1_links_meta_box( $post ) {
		// Check for v1 migration links.
		$v1_zqpm_ids         = get_post_meta( $post->ID, '_crm_v1_zqpm_id', false );
		$v1_registration_ids = get_post_meta( $post->ID, '_crm_v1_registration_id', false );
		$v1_formation_ids    = get_post_meta( $post->ID, '_crm_v1_formation_id', false );
		$v1_session_ids      = get_post_meta( $post->ID, '_crm_v1_session_id', false );
		$v1_entreprise_ids   = get_post_meta( $post->ID, '_crm_v1_entreprise_id', false );
		$v1_instructor_id    = get_post_meta( $post->ID, '_crm_v1_instructor_id', true );
		$v1_referent_index   = get_post_meta( $post->ID, '_crm_v1_referent_index', true );

		$has_v1_data = ! empty( $v1_zqpm_ids ) || ! empty( $v1_registration_ids ) || ! empty( $v1_formation_ids ) ||
						! empty( $v1_session_ids ) || ! empty( $v1_entreprise_ids ) ||
						! empty( $v1_instructor_id ) || ! empty( $v1_referent_index );

		if ( ! $has_v1_data ) {
			echo '<p><em>' . esc_html__( 'No v1 legacy data for this person.', 'formapress-crm' ) . '</em></p>';
			return;
		}

		echo '<div class="formapress-v1-links">';
		echo '<p><small>' . esc_html__( 'This person was migrated from v1. Below are links to original records:', 'formapress-crm' ) . '</small></p>';

		// ZQPM links (most important - show first with titles).
		if ( ! empty( $v1_zqpm_ids ) ) {
			echo '<div style="background: #f0f0f1; padding: 10px; margin-bottom: 10px; border-left: 4px solid #2271b1;">';
			echo '<p style="margin: 0 0 8px 0;"><strong>' . esc_html__( 'Qualiopi Process Manager (ZQPM):', 'formapress-crm' ) . '</strong></p>';

			foreach ( $v1_zqpm_ids as $zqpm_id ) {
				if ( empty( $zqpm_id ) ) {
					continue;
				}

				// The zqpm_id IS the post ID directly.
				$zqpm_post = get_post( $zqpm_id );

				if ( $zqpm_post && 'zqpm' === $zqpm_post->post_type ) {
					// Get the constructed title using v1 logic.
					$zqpm_session_id = get_post_meta( $zqpm_post->ID, 'zqpm_session_id', true );
					$title           = '';

					if ( ! empty( $zqpm_session_id ) && class_exists( 'zSession' ) ) {
						$session   = new zSession( $zqpm_session_id );
						$formation = get_post( $session->formation_id );
						if ( $formation ) {
							$title = $formation->post_title . ' - ' . $session->title . ' - ' . $session->lieu;
						}
					}

					if ( empty( $title ) ) {
						$title = __( 'ZQPM Record', 'formapress-crm' ) . ' #' . $zqpm_id;
					}

					echo '<p style="margin: 4px 0;">';
					echo '<a href="' . esc_url( admin_url( 'post.php?post=' . $zqpm_post->ID . '&action=edit' ) ) . '" target="_blank" style="text-decoration: none;">';
					echo '📋 ' . esc_html( $title );
					echo '</a>';
					echo ' <small style="color: #646970;">(ID: ' . esc_html( $zqpm_id ) . ')</small>';
					echo '</p>';
				} else {
					echo '<p style="margin: 4px 0; color: #d63638;">⚠️ ' . esc_html__( 'ZQPM record not found', 'formapress-crm' ) . ' (ID: ' . esc_html( $zqpm_id ) . ')</p>';
				}
			}
			echo '</div>';
		}       if ( ! empty( $v1_instructor_id ) ) {
			echo '<p><strong>' . esc_html__( 'Instructor ID:', 'formapress-crm' ) . '</strong> ';
			echo '<a href="' . esc_url( admin_url( 'post.php?post=' . $v1_instructor_id . '&action=edit' ) ) . '" target="_blank">';
			echo esc_html( $v1_instructor_id );
			echo '</a></p>';
		}

		if ( ! empty( $v1_referent_index ) ) {
			echo '<p><strong>' . esc_html__( 'Referent Index:', 'formapress-crm' ) . '</strong> ' . esc_html( $v1_referent_index ) . '</p>';
		}

		if ( ! empty( $v1_entreprise_ids ) ) {
			echo '<p><strong>' . esc_html__( 'Company IDs:', 'formapress-crm' ) . '</strong><br>';
			foreach ( $v1_entreprise_ids as $entreprise_id ) {
				$company = get_post( $entreprise_id );
				if ( $company ) {
					echo '<a href="' . esc_url( admin_url( 'post.php?post=' . $entreprise_id . '&action=edit' ) ) . '" target="_blank">';
					echo esc_html( $company->post_title ) . ' (' . esc_html( $entreprise_id ) . ')';
					echo '</a><br>';
				} else {
					echo esc_html( $entreprise_id ) . '<br>';
				}
			}
			echo '</p>';
		}

		if ( ! empty( $v1_session_ids ) ) {
			echo '<p><strong>' . esc_html__( 'Session IDs:', 'formapress-crm' ) . '</strong><br>';
			echo esc_html( implode( ', ', $v1_session_ids ) );
			echo '</p>';
		}

		if ( ! empty( $v1_formation_ids ) ) {
			echo '<p><strong>' . esc_html__( 'Formation IDs:', 'formapress-crm' ) . '</strong><br>';
			foreach ( $v1_formation_ids as $formation_id ) {
				$formation = get_post( $formation_id );
				if ( $formation ) {
					echo '<a href="' . esc_url( admin_url( 'post.php?post=' . $formation_id . '&action=edit' ) ) . '" target="_blank">';
					echo esc_html( $formation->post_title ) . ' (' . esc_html( $formation_id ) . ')';
					echo '</a><br>';
				} else {
					echo esc_html( $formation_id ) . '<br>';
				}
			}
			echo '</p>';
		}

		if ( ! empty( $v1_registration_ids ) ) {
			echo '<p><strong>' . esc_html__( 'Registration IDs:', 'formapress-crm' ) . '</strong><br>';
			echo esc_html( implode( ', ', $v1_registration_ids ) );
			echo ' <small>(' . esc_html__( 'from custom table', 'formapress-crm' ) . ')</small>';
			echo '</p>';
		}

		echo '</div>';
	}
}

// Initialize
FormaPress_Person_Meta_Boxes::init();
