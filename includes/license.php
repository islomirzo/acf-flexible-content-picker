<?php

namespace Acf_Fcp_Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Manages license state (storage, caching, activate/deactivate flow).
 *
 * All API communication is delegated to the active License_Provider.
 * To swap stores (e.g. Gumroad → Envato), replace the provider returned
 * by get_provider() — nothing else needs to change.
 */
class License {

	const OPTION_KEY    = 'plugpanda_acf_fcp_license_key';
	const OPTION_STATUS = 'plugpanda_acf_fcp_license_status';
	const TRANSIENT     = 'plugpanda_acf_fcp_license_valid';
	const FREE_LIMIT    = 8;

	public static function is_pro(): bool {
		$key    = get_option( self::OPTION_KEY, '' );
		$status = get_option( self::OPTION_STATUS, 'inactive' );

		if ( ! $key || 'active' !== $status ) {
			return false;
		}

		$cached = get_transient( self::TRANSIENT );
		if ( false !== $cached ) {
			// Cache holds a site-specific token, not a guessable '1' — so flipping
			// the transient in the database won't unlock Pro.
			return is_string( $cached ) && hash_equals( self::valid_token( $key ), $cached );
		}

		$provider = self::get_provider();
		$body     = $provider->verify( $key );

		if ( null === $body ) {
			// Server unreachable — short grace period only.
			set_transient( self::TRANSIENT, self::valid_token( $key ), 15 * MINUTE_IN_SECONDS );
			return true;
		}

		$valid = $provider->is_valid( $body );
		set_transient( self::TRANSIENT, $valid ? self::valid_token( $key ) : '0', DAY_IN_SECONDS );

		if ( ! $valid ) {
			update_option( self::OPTION_STATUS, 'inactive' );
		}

		return $valid;
	}

	/**
	 * Site-specific proof of a verified license. Bound to the key and the site's
	 * secret salts (wp_salt), so the cached value can't be guessed, hard-coded,
	 * or copied between sites.
	 */
	private static function valid_token( string $key ): string {
		return hash_hmac( 'sha256', 'plugpanda_acf_fcp_pro|' . $key, wp_salt( 'auth' ) );
	}

	public static function get_key(): string {
		return (string) get_option( self::OPTION_KEY, '' );
	}

	public static function get_status(): string {
		return (string) get_option( self::OPTION_STATUS, 'inactive' );
	}

	/**
	 * Masks the middle two segments of the license key.
	 * e.g. 6F0E4C97-B72A4E69-A11BF6C4-AF6517E7 → 6F0E4C97-••••••••-••••••••-AF6517E7
	 */
	public static function mask_key( string $key ): string {
		$parts = explode( '-', $key );
		if ( count( $parts ) === 4 ) {
			$parts[1] = '********';
			$parts[2] = '********';
			return implode( '-', $parts );
		}
		$len = mb_strlen( $key );
		if ( $len <= 16 ) {
			return $key;
		}
		return mb_substr( $key, 0, 8 ) . str_repeat( '*', $len - 16 ) . mb_substr( $key, -8 );
	}

	/**
	 * @return array{ success: bool, message: string, masked_key?: string }
	 */
	public static function activate( string $key ): array {
		$key = sanitize_text_field( trim( $key ) );

		if ( ! $key ) {
			return [
				'success' => false,
				'message' => __( 'Please enter a license key.', 'acf-flexible-content-picker' ),
			];
		}

		$provider = self::get_provider();

		// Verify without incrementing first.
		$body = $provider->verify( $key );

		if ( null === $body ) {
			return [
				'success' => false,
				'message' => __( 'Could not reach the license server. Please try again.', 'acf-flexible-content-picker' ),
			];
		}

		if ( ! $provider->is_valid( $body ) ) {
			return [
				'success' => false,
				'message' => __( 'Invalid license key. Please check and try again.', 'acf-flexible-content-picker' ),
			];
		}

		// Local/dev environments don't count toward the site activation limit.
		$is_same_site = ( self::get_key() === $key );
		$is_local     = self::is_local_environment();

		if ( ! $is_same_site && ! $is_local ) {
			$body = $provider->increment( $key );

			if ( null === $body ) {
				return [
					'success' => false,
					'message' => __( 'Could not reach the license server. Please try again.', 'acf-flexible-content-picker' ),
				];
			}

			$uses = $provider->get_uses( $body );

			if ( $uses > $provider->get_site_limit() ) {
				return [
					'success' => false,
					'message' => sprintf(
						/* translators: %d: site limit */
						__( 'This license key has reached the maximum of %d active websites.', 'acf-flexible-content-picker' ),
						$provider->get_site_limit()
					),
				];
			}
		}

		update_option( self::OPTION_KEY, $key );
		update_option( self::OPTION_STATUS, 'active' );
		set_transient( self::TRANSIENT, self::valid_token( $key ), DAY_IN_SECONDS );

		return [
			'success'    => true,
			'message'    => __( 'License activated successfully. Enjoy Pro!', 'acf-flexible-content-picker' ),
			'masked_key' => self::mask_key( $key ),
		];
	}

	public static function deactivate(): void {
		update_option( self::OPTION_STATUS, 'inactive' );
		delete_transient( self::TRANSIENT );
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Returns true when the current site is a local / development environment.
	 *
	 * Checks WP_ENVIRONMENT_TYPE first (explicit), then falls back to inspecting
	 * the site hostname for well-known local TLDs and loopback addresses.
	 * Local activations are allowed without consuming a site-activation slot.
	 */
	private static function is_local_environment(): bool {
		// Respect the explicit WP environment setting when present.
		if ( function_exists( 'wp_get_environment_type' ) && wp_get_environment_type() === 'local' ) {
			return true;
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST ) ?? '';
		$host = strtolower( (string) $host );

		// Loopback addresses.
		if ( in_array( $host, [ 'localhost', '127.0.0.1', '::1' ], true ) ) {
			return true;
		}

		// Common local development TLDs.
		// substr()/strlen() comparison instead of str_ends_with() to keep the
		// PHP 7.4 floor (str_ends_with() is PHP 8.0+).
		foreach ( [ '.local', '.test', '.dev', '.localhost', '.example', '.invalid' ] as $tld ) {
			if ( substr( $host, -strlen( $tld ) ) === $tld ) {
				return true;
			}
		}

		return false;
	}

	// ── Provider ─────────────────────────────────────────────────────────────

	/**
	 * Returns the active license provider instance.
	 *
	 * Swap this return value to change the store integration.
	 */
	private static function get_provider(): License\License_Provider {
		static $provider = null;
		if ( null === $provider ) {
			$provider = new License\Gumroad_Provider();
		}
		return $provider;
	}
}
