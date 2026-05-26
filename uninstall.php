<?php
/**
 * Runs on plugin uninstall.
 *
 * Data is only removed when the user has explicitly opted in via
 * Settings → ACF FCP → "Delete all plugin data when uninstalling".
 * The default behaviour is to keep all data so it is restored on reinstall.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

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
delete_option( 'plugpanda_acf_fcp_plugin_first_activated' );
delete_option( 'plugpanda_acf_fcp_plugin_activation_redirect' );
