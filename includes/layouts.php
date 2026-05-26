<?php

namespace Acf_Fcp_Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the "Section Picker" toggle and a per-layout metadata panel inside
 * the ACF field group editor for every flexible content field.
 *
 * Each layout can have:
 *  - title       (falls back to the layout's own label when empty)
 *  - description
 *  - thumbnail   (WP media attachment URL or any external URL)
 *
 * Storage: wp_options keyed `plugpanda_acf_fcp_plugin_meta_{field_key}` — kept separate from
 * ACF's own field serialisation to avoid conflicts.
 */
class Layout_Settings {

	private static ?self $instance = null;

	public static function init(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
	}

	private function __construct() {
		add_filter( 'acf/load_field/type=flexible_content',   [ $this, 'default_enabled' ] );
		add_action( 'acf/render_field_settings/type=flexible_content', [ $this, 'render_settings' ] );
		add_filter( 'acf/update_field/type=flexible_content', [ $this, 'save_layout_meta' ] );
		add_action( 'wp_ajax_plugpanda_acf_fcp_plugin_autosave_meta', [ $this, 'ajax_autosave_meta' ] );
	}

	/**
	 * Defaults plugpanda_acf_fcp_plugin_enabled to 1 for fields that have never been
	 * explicitly saved with this plugin active — i.e. brand-new fields and any
	 * pre-existing fields created before the plugin was installed.
	 *
	 * Once a field is saved the value is persisted in ACF's own storage, so
	 * isset() stays true and this default no longer applies.
	 *
	 * @param array $field ACF field array.
	 * @return array
	 */
	public function default_enabled( array $field ): array {
		if ( ! isset( $field['plugpanda_acf_fcp_plugin_enabled'] ) ) {
			$field['plugpanda_acf_fcp_plugin_enabled'] = 1;
		}

		// Embed stored metadata into the field array so it is included in JSON
		// exports and carried across when a field group is duplicated. On import
		// or duplication, acf/update_field receives this data under the new key
		// and save_layout_meta() persists it to wp_options for the new key.
		if ( ! empty( $field['key'] ) ) {
			$field['plugpanda_acf_fcp_layout_meta'] = self::get_field_meta( $field['key'] );
		}

		return $field;
	}

	/* -----------------------------------------------------------------------
	   Field-group editor: render
	   --------------------------------------------------------------------- */

	/**
	 * Renders the toggle + per-layout metadata panel in the field settings area.
	 *
	 * @param array $field ACF field array.
	 */
	public function render_settings( array $field ): void {

		// Default to enabled when the field has never been saved with this plugin
		// (acf/load_field does not fire for brand-new unsaved fields).
		if ( ! isset( $field['plugpanda_acf_fcp_plugin_enabled'] ) ) {
			$field['plugpanda_acf_fcp_plugin_enabled'] = 1;
		}

		$enabled = ! empty( $field['plugpanda_acf_fcp_plugin_enabled'] );
		$stored  = self::get_field_meta( $field['key'] );
		$layouts = (array) ( $field['layouts'] ?? [] );

		echo '<div class="plugpanda-acf-fcp-plugin-wrapper">';
		echo '<div class="plugpanda-acf-fcp-plugin-wrapper__header">';

		// Toggle — ACF saves this automatically as part of the field array.
		acf_render_field_setting( $field, [
			'label' => '',
			'type'  => 'true_false',
			'name'  => 'plugpanda_acf_fcp_plugin_enabled',
			'ui'    => 1,
		] );

		echo '<div class="plugpanda-acf-fcp-plugin-wrapper__title">' . esc_html__( 'FLEXIBLE CONTENT PICKER', 'acf-flexible-content-picker' ) . '</div>';
		echo '<span class="plugpanda-acf-fcp-plugin-wrapper__help" aria-label="' . esc_attr__( 'Replaces the ACF Flexible Content dropdown with a modal to preview, select, reorder, and add multiple layouts at once.', 'acf-flexible-content-picker' ) . '">?<span class="plugpanda-acf-fcp-plugin-wrapper__tooltip">' . esc_html__( 'Replaces the ACF Flexible Content dropdown with a modal to preview, select, reorder, and add multiple layouts at once.', 'acf-flexible-content-picker' ) . '</span></span>';

		if ( ! License::is_pro() ) {
			printf(
				'<a href="%s" class="plugpanda-acf-fcp-plugin-wrapper__upgrade-btn">%s</a>',
				esc_url( admin_url( 'options-general.php?page=plugpanda-acf-fcp-plugin' ) ),
				esc_html__( 'Activate Pro', 'acf-flexible-content-picker' )
			);
		}

		echo '</div>';

		echo '<div class="acf-field plugpanda-acf-fcp-plugin js-plugpanda-acf-fcp-plugin" data-field-key="' . esc_attr( $field['key'] ) . '"' . ( $enabled ? '' : ' style="display:none;"' ) . '>';

		echo '<div class="plugpanda-acf-fcp-plugin__header js-plugpanda-acf-fcp-plugin__toggle" aria-expanded="false">';
		echo '<div class="plugpanda-acf-fcp-plugin__header-info">';
		echo '<span class="plugpanda-acf-fcp-plugin__title">' . esc_html__( 'Layout Previews', 'acf-flexible-content-picker' ) . '</span>';
		echo '<span class="plugpanda-acf-fcp-plugin__desc">' . esc_html__( 'Set a thumbnail, title, and description for each layout card.', 'acf-flexible-content-picker' ) . '</span>';
		echo '</div>';
		echo '<span class="plugpanda-acf-fcp-plugin__chevron" aria-hidden="true"></span>';
		echo '</div>';

		echo '<div class="plugpanda-acf-fcp-plugin__body" style="display:none;">';
		echo '<div class="acf-input">';

		if ( empty( $layouts ) ) {
			echo '<p class="description" style="grid-column:1/-1">' . esc_html__( 'Add layouts to this field, then save the field group to configure their modal metadata.', 'acf-flexible-content-picker' ) . '</p>';
		} else {
			foreach ( $layouts as $layout ) {
				$this->render_layout_row( $field['key'], $layout, $stored );
			}
		}

		echo '</div>';
		echo '</div>';
		echo '</div>';
		echo '</div>'; // .plugpanda-acf-fcp-plugin-wrapper
	}

	/**
	 * Renders a single layout's metadata row.
	 *
	 * @param string $field_key   ACF field key.
	 * @param array  $layout      Layout definition array.
	 * @param array  $stored_meta Stored metadata keyed by layout name.
	 */
	private function render_layout_row( string $field_key, array $layout, array $stored_meta ): void {
		$layout_name  = sanitize_key( $layout['name'] ?? '' );
		$layout_label = $layout['label'] ?? $layout_name;
		$meta         = $stored_meta[ $layout_name ] ?? [];
		$fk           = esc_attr( $field_key );
		$ln           = esc_attr( $layout_name );
		$thumb        = esc_attr( $meta['thumbnail'] ?? '' ); // raw value for the editable text input
		$thumb_src    = esc_url( $meta['thumbnail'] ?? '' );  // URL-safe value for the <img src>

		echo '<div class="plugpanda-acf-fcp-plugin__row">';

		echo '<div class="plugpanda-acf-fcp-plugin__row-head">';
		echo '<span class="plugpanda-acf-fcp-plugin__row-label">' . esc_html( $layout_label ) . '</span>';
		echo '</div>';

		echo '<div class="plugpanda-acf-fcp-plugin__row-fields">';

		printf(
			'<div class="plugpanda-acf-fcp-plugin__field">
				<input type="text"
					name="plugpanda_acf_fcp_plugin_layout_meta[%s][%s][title]"
					value="%s"
					placeholder="%s"
					class="widefat">
			</div>',
			$fk, $ln,
			esc_attr( $meta['title'] ?? '' ),
			esc_attr( $layout_label )
		);

		printf(
			'<div class="plugpanda-acf-fcp-plugin__field">
				<input type="text"
					name="plugpanda_acf_fcp_plugin_layout_meta[%s][%s][description]"
					value="%s"
					placeholder="%s"
					class="widefat">
			</div>',
			$fk, $ln,
			esc_attr( $meta['description'] ?? '' ),
			esc_attr__( 'Short Description', 'acf-flexible-content-picker' )
		);

		printf(
			'<div class="plugpanda-acf-fcp-plugin__field plugpanda-acf-fcp-plugin__field--thumb">
				<div class="plugpanda-acf-fcp-plugin__thumb-box">
					<img src="%s" class="plugpanda-acf-fcp-plugin__thumb-preview" alt=""%s>
				</div>
				<div class="plugpanda-acf-fcp-plugin__thumb-controls">
					<input type="text"
						name="plugpanda_acf_fcp_plugin_layout_meta[%s][%s][thumbnail]"
						value="%s"
						placeholder="URL"
						class="widefat plugpanda-acf-fcp-plugin__thumb-url">
					<div class="plugpanda-acf-fcp-plugin__thumb-or">OR</div>
					<button type="button"
						class="button plugpanda-acf-fcp-plugin__media-pick"
						data-input-name="plugpanda_acf_fcp_plugin_layout_meta[%s][%s][thumbnail]">%s</button>
				</div>
			</div>',
			$thumb_src,
			( $thumb_src ? '' : ' style="display:none;"' ),
			$fk, $ln, $thumb,
			$fk, $ln,
			esc_html__( 'UPLOAD', 'acf-flexible-content-picker' )
		);

		echo '</div>';
		echo '</div>';
	}

	/* -----------------------------------------------------------------------
	   AJAX: autosave a single layout meta field
	   --------------------------------------------------------------------- */

	public function ajax_autosave_meta(): void {
		check_ajax_referer( 'plugpanda_acf_fcp_plugin_autosave', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'forbidden', 403 );
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$field_key   = sanitize_key( wp_unslash( $_POST['field_key']   ?? '' ) );
		$layout_name = sanitize_key( wp_unslash( $_POST['layout_name'] ?? '' ) );
		$field_type  = sanitize_key( wp_unslash( $_POST['field_type']  ?? '' ) );
		$raw_value   = wp_unslash( $_POST['value'] ?? '' );
		// phpcs:enable

		$allowed_types = [ 'title', 'description', 'thumbnail' ];
		if ( ! $field_key || ! $layout_name || ! in_array( $field_type, $allowed_types, true ) ) {
			wp_send_json_error( 'invalid_params' );
		}

		if ( 'thumbnail' === $field_type ) {
			$value = esc_url_raw( $raw_value );
		} elseif ( 'description' === $field_type ) {
			$value = sanitize_textarea_field( $raw_value );
		} else {
			$value = sanitize_text_field( $raw_value );
		}

		$meta = self::get_field_meta( $field_key );
		if ( ! isset( $meta[ $layout_name ] ) ) {
			$meta[ $layout_name ] = [ 'title' => '', 'description' => '', 'thumbnail' => '' ];
		}
		$meta[ $layout_name ][ $field_type ] = $value;

		update_option( 'plugpanda_acf_fcp_plugin_meta_' . $field_key, $meta, false );

		wp_send_json_success();
	}

	/* -----------------------------------------------------------------------
	   Field-group editor: save
	   --------------------------------------------------------------------- */

	/**
	 * Persists per-layout metadata when ACF saves the field group.
	 *
	 * Fires via `acf/update_field/type=flexible_content`.  At this point the
	 * field group save request is already nonce-verified by ACF itself.
	 *
	 * @param array $field ACF field array (already processed by ACF).
	 * @return array Unchanged field array.
	 */
	public function save_layout_meta( array $field ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_data = $_POST['plugpanda_acf_fcp_plugin_layout_meta'][ $field['key'] ] ?? null;

		if ( is_array( $post_data ) ) {
			// Normal path: user saved the field group editor manually.
			$raw = $post_data;
		} elseif ( ! empty( $field['plugpanda_acf_fcp_layout_meta'] ) && is_array( $field['plugpanda_acf_fcp_layout_meta'] ) ) {
			// Import / duplication path: metadata was embedded in the field array
			// by acf/load_field on the source field and is now available on the
			// new field key. Persist it under the new key.
			$raw = $field['plugpanda_acf_fcp_layout_meta'];
		} else {
			return $field;
		}

		$clean = [];

		foreach ( $raw as $layout_name => $values ) {
			if ( ! is_array( $values ) ) {
				continue;
			}
			$layout_name = sanitize_key( $layout_name );
			if ( ! $layout_name ) {
				continue;
			}
			$clean[ $layout_name ] = [
				'title'       => sanitize_text_field( $values['title'] ?? '' ),
				'description' => sanitize_textarea_field( $values['description'] ?? '' ),
				'thumbnail'   => esc_url_raw( $values['thumbnail'] ?? '' ),
			];
		}

		update_option( 'plugpanda_acf_fcp_plugin_meta_' . $field['key'], $clean, false );

		return $field;
	}

	/* -----------------------------------------------------------------------
	   Public API
	   --------------------------------------------------------------------- */

	/**
	 * Returns stored per-layout metadata for a given flexible content field.
	 *
	 * @param  string $field_key ACF field key.
	 * @return array<string, array>  Keyed by layout name.
	 */
	public static function get_field_meta( string $field_key ): array {
		return (array) get_option( 'plugpanda_acf_fcp_plugin_meta_' . $field_key, [] );
	}
}
