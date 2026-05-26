<?php
/**
 * Runs on plugin uninstall.
 *
 * User data is only removed when the user has explicitly opted in via
 * Settings → ACF FCP → "Delete all plugin data when uninstalling".
 * Internal activation markers are always cleared so a genuine reinstall
 * counts as a fresh first activation.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Internal activation markers (not user data) — always cleared, so deleting
// and reinstalling the plugin triggers the first-activation redirect again.
delete_option( 'plugpanda_acf_fcp_plugin_first_activated' );
delete_option( 'plugpanda_acf_fcp_plugin_activation_redirect' );

// Everything below is user data — only removed when the user opted in.
if ( '1' !== get_option( 'plugpanda_acf_fcp_delete_data_on_uninstall' ) ) {
	return;
}

global $wpdb;

// Remove all per-layout metadata options (plugpanda_acf_fcp_plugin_meta_{field_key}).
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'plugpanda_acf_fcp_plugin_meta_' ) . '%'
	)
);

// Remove license data.
delete_option( 'plugpanda_acf_fcp_license_key' );
delete_option( 'plugpanda_acf_fcp_license_status' );
delete_transient( 'plugpanda_acf_fcp_license_valid' );

// Remove plugin settings (including this option itself).
delete_option( 'plugpanda_acf_fcp_delete_data_on_uninstall' );
