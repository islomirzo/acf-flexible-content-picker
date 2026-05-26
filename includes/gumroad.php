<?php

namespace Acf_Fcp_Plugin\License;

defined( 'ABSPATH' ) || exit;

/**
 * Contract for a license verification provider.
 *
 * Implement this interface to support a new store (e.g. Envato, Paddle).
 * The License class calls these methods; it has no knowledge of any specific API.
 */
interface License_Provider {

	/** Verify a license key without incrementing the use count. Null if unreachable. */
	public function verify( string $key ): ?array;

	/** Verify a license key and increment the use count (first activation only). Null if unreachable. */
	public function increment( string $key ): ?array;

	/** True when the response body indicates an active, valid license. */
	public function is_valid( array $body ): bool;

	/** Current activation use count from a response body. */
	public function get_uses( array $body ): int;

	/** Maximum number of site activations allowed per key. */
	public function get_site_limit(): int;
}

/**
 * Gumroad license verification provider.
 *
 * Handles all communication with the Gumroad Licenses API.
 * To add another store, implement License_Provider in a new class.
 */
class Gumroad_Provider implements License_Provider {

	private const API_URL    = 'https://api.gumroad.com/v2/licenses/verify';
	private const PRODUCT_ID = 'WbP-uOk87yLOv6-MxMQ7NA==';
	private const SITE_LIMIT = 3;

	public function verify( string $key ): ?array {
		return $this->call( $key, false );
	}

	public function increment( string $key ): ?array {
		return $this->call( $key, true );
	}

	/**
	 * Returns true when the Gumroad response indicates an active, valid purchase.
	 */
	public function is_valid( array $body ): bool {
		if ( empty( $body['success'] ) ) {
			return false;
		}

		$purchase = $body['purchase'] ?? [];

		// Refunded or charged-back purchases are no longer valid.
		if ( ! empty( $purchase['refunded'] ) || ! empty( $purchase['chargebacked'] ) ) {
			return false;
		}

		// A dispute invalidates the purchase unless the seller won it.
		if ( ! empty( $purchase['disputed'] ) && empty( $purchase['dispute_won'] ) ) {
			return false;
		}

		// Cancelled, failed, or ended subscriptions are no longer valid.
		if ( ! empty( $purchase['subscription_cancelled_at'] )
			|| ! empty( $purchase['subscription_failed_at'] )
			|| ! empty( $purchase['subscription_ended_at'] ) ) {
			return false;
		}

		return true;
	}

	public function get_uses( array $body ): int {
		return (int) ( $body['uses'] ?? 0 );
	}

	public function get_site_limit(): int {
		return self::SITE_LIMIT;
	}

	/**
	 * Posts to the Gumroad Licenses API. Null when the server is unreachable.
	 */
	private function call( string $key, bool $increment ): ?array {
		$response = wp_remote_post(
			self::API_URL,
			[
				'timeout' => 15,
				'body'    => [
					'product_id'           => self::PRODUCT_ID,
					'license_key'          => $key,
					'increment_uses_count' => $increment ? 'true' : 'false',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		if ( wp_remote_retrieve_response_code( $response ) >= 500 ) {
			return null;
		}

		return json_decode( wp_remote_retrieve_body( $response ), true ) ?: [];
	}
}
