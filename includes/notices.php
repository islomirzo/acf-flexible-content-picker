<?php

namespace Acf_Fcp_Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders admin notices when plugin dependencies are not met.
 */
class Admin_Notices {

	public static function init(): void {
		add_action( 'admin_notices', [ self::class, 'render' ] );
	}

	public static function render(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$acf_pro_active = class_exists( 'ACF' ) && defined( 'ACF_PRO' );
		$acf_version_ok = $acf_pro_active && ( ! defined( 'ACF_VERSION' ) || version_compare( ACF_VERSION, '6.0', '>=' ) );

		if ( $acf_pro_active && $acf_version_ok ) {
			return;
		}

		$message = $acf_pro_active
			? sprintf(
				/* translators: 1: plugin name, 2: required version */
				esc_html__( '%1$s requires Advanced Custom Fields PRO %2$s or later.', 'acf-flexible-content-picker' ),
				'<strong>ACF Flexible Content Picker</strong>',
				'6.0'
			)
			: sprintf(
				/* translators: 1: plugin name, 2: link open tag, 3: link close tag */
				esc_html__( '%1$s requires %2$sAdvanced Custom Fields PRO%3$s to be installed and activated.', 'acf-flexible-content-picker' ),
				'<strong>ACF Flexible Content Picker</strong>',
				'<a href="https://www.advancedcustomfields.com/pro/" target="_blank" rel="noopener noreferrer">',
				'</a>'
			);

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			wp_kses(
				$message,
				[
					'strong' => [],
					'a'      => [ 'href' => [], 'target' => [], 'rel' => [] ],
				]
			)
		);
	}
}
