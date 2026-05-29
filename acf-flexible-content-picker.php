<?php
/**
 * Plugin Name:       ACF Flexible Content Picker
 * Plugin URI:        https://plugpandas.gumroad.com/l/acf-flexible-content-picker
 * Description:       Replaces the ACF Flexible Content dropdown with a modal to preview, select, reorder, and add multiple layouts at once.
 * Version:           1.0.3
 * Requires at least: 6.0
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * Author:            PlugPanda
 * Author URI:        https://plugpandas.gumroad.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acf-flexible-content-picker
 * Domain Path:       /languages
 *
 * Copyright (C) 2026 PlugPanda. All rights reserved.
 */

defined( 'ABSPATH' ) || exit;

define( 'PLUGPANDA_ACF_FCP_PLUGIN_VERSION', '1.0.3' );
define( 'PLUGPANDA_ACF_FCP_PLUGIN_MIN_PHP', '7.4' );
define( 'PLUGPANDA_ACF_FCP_PLUGIN_FILE', __FILE__ );
define( 'PLUGPANDA_ACF_FCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLUGPANDA_ACF_FCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Guard: wrong PHP version — bail silently (WP will show its own notice).
 */
if ( version_compare( PHP_VERSION, PLUGPANDA_ACF_FCP_PLUGIN_MIN_PHP, '<' ) ) {
	return;
}

require_once PLUGPANDA_ACF_FCP_PLUGIN_DIR . 'includes/plugin.php';

/**
 * Returns the single plugin instance.
 */
function plugpanda_acf_fcp_plugin(): Acf_Fcp_Plugin\Plugin {
	return Acf_Fcp_Plugin\Plugin::instance();
}

plugpanda_acf_fcp_plugin();

/**
 * On activation, set a redirect flag only on the very first activation.
 * Subsequent deactivate/reactivate cycles leave the flag unset so no redirect occurs.
 */
register_activation_hook( __FILE__, static function (): void {
	// plugpanda_acf_fcp_plugin_first_activated persists across deactivate/reactivate
	// cycles but is cleared on uninstall, so a genuine reinstall redirects again.
	// If it already exists, this is a re-activation — skip the redirect.
	if ( get_option( 'plugpanda_acf_fcp_plugin_first_activated' ) ) {
		return;
	}
	add_option( 'plugpanda_acf_fcp_plugin_first_activated', '1' );
	add_option( 'plugpanda_acf_fcp_plugin_activation_redirect', '1' );
} );

/**
 * Consume the redirect flag on the first real admin page load after activation.
 * Skipped during bulk activation (multiple plugins at once).
 */
add_action( 'admin_init', static function (): void {
	if ( '1' !== get_option( 'plugpanda_acf_fcp_plugin_activation_redirect' ) ) {
		return;
	}

	// admin_init also fires on AJAX, cron, and REST requests. Wait for a genuine
	// admin page load so a background request doesn't silently consume the flag.
	if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	// Consume the one-time flag now that we're on a real admin request.
	delete_option( 'plugpanda_acf_fcp_plugin_activation_redirect' );

	// Don't redirect during bulk activation, or for users who can't open the page.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['activate-multi'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	wp_safe_redirect( admin_url( 'options-general.php?page=plugpanda-acf-fcp-plugin' ) );
	exit;
} );
