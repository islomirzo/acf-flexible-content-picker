<?php

namespace Acf_Fcp_Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class — bootstraps all components after checking dependencies.
 */
final class Plugin {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		require_once PLUGPANDA_ACF_FCP_PLUGIN_DIR . 'includes/updater.php';
		Updater::init();
		add_action( 'plugins_loaded', [ $this, 'boot' ] );
	}

	/**
	 * Boot after all plugins are loaded so ACF PRO availability can be checked.
	 */
	public function boot(): void {
		load_plugin_textdomain(
			'acf-flexible-content-picker',
			false,
			dirname( plugin_basename( PLUGPANDA_ACF_FCP_PLUGIN_FILE ) ) . '/languages'
		);

		if ( ! $this->dependencies_met() ) {
			require_once PLUGPANDA_ACF_FCP_PLUGIN_DIR . 'includes/notices.php';
			Admin_Notices::init();
			return;
		}

		$this->load_components();
	}

	/**
	 * Returns true when ACF PRO >= 6.0 is active.
	 */
	private function dependencies_met(): bool {
		if ( ! class_exists( 'ACF' ) || ! defined( 'ACF_PRO' ) ) {
			return false;
		}

		if ( defined( 'ACF_VERSION' ) && version_compare( ACF_VERSION, '6.0', '<' ) ) {
			return false;
		}

		return true;
	}

	private function load_components(): void {
		require_once PLUGPANDA_ACF_FCP_PLUGIN_DIR . 'includes/gumroad.php';
		require_once PLUGPANDA_ACF_FCP_PLUGIN_DIR . 'includes/license.php';
		require_once PLUGPANDA_ACF_FCP_PLUGIN_DIR . 'includes/settings.php';
		require_once PLUGPANDA_ACF_FCP_PLUGIN_DIR . 'includes/acf.php';
		require_once PLUGPANDA_ACF_FCP_PLUGIN_DIR . 'includes/assets.php';
		require_once PLUGPANDA_ACF_FCP_PLUGIN_DIR . 'includes/layouts.php';

		Settings_Page::init();
		ACF_Integration::init();
		Assets::init( ACF_Integration::get_instance() );
		Layout_Settings::init();
	}
}
