<?php

namespace Acf_Fcp_Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into ACF to collect flexible content field data and expose
 * developer filters for enriching layout metadata.
 *
 * Collected data is stored in $fields_data and made available to
 * assets.php via get_fields_data() for JS localisation.
 *
 * Developer API
 * -------------
 * Filter: plugpanda_acf_fcp_plugin/layout_meta
 *   Enrich a layout with description, thumbnail and category.
 *
 *   @param array  $meta        Default: [ description => '', thumbnail => '', category => '' ]
 *   @param string $layout_name The layout machine name.
 *   @param string $field_key   The parent flexible content field key.
 *   @return array
 *
 * Example:
 *   add_filter( 'plugpanda_acf_fcp_plugin/layout_meta', function( $meta, $layout_name, $field_key ) {
 *       if ( $layout_name === 'hero' ) {
 *           $meta['description'] = 'Full-width hero section.';
 *           $meta['thumbnail']   = get_template_directory_uri() . '/thumbnails/hero.png';
 *           $meta['category']    = 'Marketing';
 *       }
 *       return $meta;
 *   }, 10, 3 );
 */
class ACF_Integration {

	/** @var array<string, array> Keyed by ACF field key. */
	private array $fields_data = [];

	private static ?self $instance = null;

	public static function init(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
	}

	public static function get_instance(): ?self {
		return self::$instance;
	}

	private function __construct() {
		add_action( 'acf/render_field/type=flexible_content', [ $this, 'collect_field' ] );
	}

	/**
	 * Hooked to acf/render_field/type=flexible_content.
	 * Builds a sanitised, enriched snapshot of the field for JS.
	 *
	 * @param array $field ACF field array.
	 */
	public function collect_field( array $field ): void {
		if ( isset( $this->fields_data[ $field['key'] ] ) ) {
			return;
		}

		if ( empty( $field['plugpanda_acf_fcp_plugin_enabled'] ) ) {
			return;
		}

		$layouts     = [];
		$is_pro      = License::is_pro();
		$stored_meta = Layout_Settings::get_field_meta( $field['key'] );

		foreach ( array_values( (array) ( $field['layouts'] ?? [] ) ) as $idx => $layout ) {
			$layout_name = $layout['name'] ?? '';

			/** @see acf.php class docblock for filter docs */
			$meta = (array) apply_filters(
				'plugpanda_acf_fcp_plugin/layout_meta',
				[ 'description' => '', 'thumbnail' => '', 'category' => '' ],
				$layout_name,
				$field['key']
			);

			// Stored UI values override PHP filter values (non-empty wins).
			$ui = $stored_meta[ sanitize_key( $layout_name ) ] ?? [];

			$label = sanitize_text_field( $layout['label'] ?? '' );
			if ( ! empty( $ui['title'] ) ) {
				$label = sanitize_text_field( $ui['title'] );
			}
			if ( ! empty( $ui['description'] ) ) {
				$meta['description'] = $ui['description'];
			}
			if ( ! empty( $ui['thumbnail'] ) ) {
				$meta['thumbnail'] = $ui['thumbnail'];
			}

			$layouts[] = [
				'name'        => sanitize_key( $layout_name ),
				'label'       => $label,
				'description' => sanitize_text_field( $meta['description'] ?? '' ),
				'thumbnail'   => esc_url_raw( $meta['thumbnail'] ?? '' ),
				'category'    => sanitize_text_field( $meta['category'] ?? '' ),
				'locked'      => ! $is_pro && $idx >= License::FREE_LIMIT,
			];
		}

		$this->fields_data[ $field['key'] ] = [
			'key'     => sanitize_key( $field['key'] ),
			'label'   => sanitize_text_field( $field['label'] ?? '' ),
			'max'     => absint( $field['max'] ?? 0 ),
			'layouts' => $layouts,
		];
	}

	/**
	 * Returns all collected field data, ready for wp_localize_script / wp_add_inline_script.
	 *
	 * @return array<string, array>
	 */
	public function get_fields_data(): array {
		return array_values( $this->fields_data );
	}
}
