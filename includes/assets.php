<?php

namespace Acf_Fcp_Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueues admin assets via ACF's own hooks so they load on every context
 * where ACF renders fields (post editor, options pages, terms, users, etc.).
 */
class Assets {

	private static ?self $instance = null;

	private ACF_Integration $integration;

	public static function init( ACF_Integration $integration ): void {
		if ( null === self::$instance ) {
			self::$instance = new self( $integration );
		}
	}

	private function __construct( ACF_Integration $integration ) {
		$this->integration = $integration;

		add_action( 'acf/input/admin_enqueue_scripts',       [ $this, 'enqueue_input' ] );
		add_action( 'acf/field_group/admin_enqueue_scripts', [ $this, 'enqueue_field_group' ] );
		add_action( 'admin_footer', [ $this, 'print_field_data' ] );
	}

	/** Enqueues modal assets on every ACF input screen; skips the field-group editor. */
	public function enqueue_input(): void {
		$screen = get_current_screen();
		if ( $screen && 'acf-field-group' === $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'plugpanda-acf-fcp-plugin-modal',
			PLUGPANDA_ACF_FCP_PLUGIN_URL . 'assets/css/modal.css',
			[],
			PLUGPANDA_ACF_FCP_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'plugpanda-acf-fcp-plugin-modal',
			PLUGPANDA_ACF_FCP_PLUGIN_URL . 'assets/js/modal.js',
			[ 'acf-input' ],   // ACF PRO registers acf-input on edit screens
			PLUGPANDA_ACF_FCP_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'plugpanda-acf-fcp-plugin-modal',
			'AcfFcpPlugin',
			[
				'isPro'     => License::is_pro(),
				'freeLimit' => License::FREE_LIMIT,
				'isWp7'     => version_compare( get_bloginfo( 'version' ), '7.0', '>=' ),
				'settingsUrl' => admin_url( 'options-general.php?page=plugpanda-acf-fcp-plugin' ),
				'i18n' => [
					'modal_title'        => esc_html__( 'Add Layout', 'acf-flexible-content-picker' ),
					'search_placeholder' => esc_html__( 'Search layouts…', 'acf-flexible-content-picker' ),
					'all_categories'     => esc_html__( 'All', 'acf-flexible-content-picker' ),
					'no_results'         => esc_html__( 'No layouts match your search.', 'acf-flexible-content-picker' ),
					'close'              => esc_html__( 'Close', 'acf-flexible-content-picker' ),
					'pro_locked'         => esc_html__( 'Upgrade to Pro to unlock all layouts.', 'acf-flexible-content-picker' ),
					'upgrade'            => esc_html__( 'Activate Pro →', 'acf-flexible-content-picker' ),
				],
			]
		);
	}

	/** Enqueues assets for the ACF field group editor (fired only on that screen). */
	public function enqueue_field_group(): void {
		wp_enqueue_media();

		wp_enqueue_style(
			'plugpanda-acf-fcp-plugin-field',
			PLUGPANDA_ACF_FCP_PLUGIN_URL . 'assets/css/field.css',
			[],
			PLUGPANDA_ACF_FCP_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'plugpanda-acf-fcp-plugin-field',
			PLUGPANDA_ACF_FCP_PLUGIN_URL . 'assets/js/field.js',
			[ 'jquery', 'media-upload' ],
			PLUGPANDA_ACF_FCP_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'plugpanda-acf-fcp-plugin-field',
			'AcfFcpPluginFS',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'plugpanda_acf_fcp_plugin_autosave' ),
				'i18n'    => [
					'upload' => __( 'UPLOAD', 'acf-flexible-content-picker' ),
				],
			]
		);
	}

	/**
	 * Injects collected field data in admin_footer after ACF has rendered all fields.
	 * Uses wp_add_inline_script so AcfFcpPlugin (created by wp_localize_script) is
	 * already on the page before this runs.
	 */
	public function print_field_data(): void {
		$data = $this->integration->get_fields_data();
		if ( empty( $data ) ) {
			return;
		}

		wp_add_inline_script(
			'plugpanda-acf-fcp-plugin-modal',
			'(function(){if(typeof AcfFcpPlugin==="undefined"){AcfFcpPlugin={};}AcfFcpPlugin.fields=' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP ) . ';})()',
			'after'
		);
	}
}
