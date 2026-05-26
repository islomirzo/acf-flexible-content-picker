<?php

namespace Acf_Fcp_Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Self-hosted plugin updates via the GitHub Releases API.
 *
 * Injection happens on the READ filter (site_transient_update_plugins) rather
 * than the write filter, so our update shows whenever WordPress displays the
 * update UI — independent of whether core's own wp_update_plugins() check runs.
 */
class Updater {

	private const API_URL       = 'https://api.github.com/repos/islomirzo/acf-flexible-content-picker/releases/latest';
	private const TRANSIENT_KEY = 'plugpanda_acf_fcp_update_info';
	private const CACHE_TTL      = 21600; // 6h — under WP's 12h auto-check so releases are picked up automatically.
	private const PLUGIN_SLUG   = 'acf-flexible-content-picker';

	// Static compatibility metadata — bump in code when it changes.
	private const REQUIRES     = '6.0';
	private const REQUIRES_PHP = '7.4';
	private const TESTED       = '7.0';

	private static ?self $instance = null;

	public static function init(): void {
		self::$instance ??= new self();
	}

	private function __construct() {
		add_filter( 'site_transient_update_plugins', [ $this, 'inject_update' ] );
		add_filter( 'plugins_api',                   [ $this, 'plugin_info' ], 10, 3 );
		add_filter( 'upgrader_source_selection',     [ $this, 'normalize_source_dir' ], 10, 4 );
		add_action( 'upgrader_process_complete',     [ $this, 'purge_cache' ], 10, 2 );
		add_filter( 'plugin_row_meta',               [ $this, 'row_meta' ], 10, 2 );

		// "Check Again" sends ?force-check=1 — bust our cache early so the next read fetches fresh.
		foreach ( [ 'load-update-core.php', 'load-update.php', 'load-plugins.php' ] as $hook ) {
			add_action( $hook, [ $this, 'maybe_force_refresh' ], 1 );
		}
	}

	/**
	 * Replace the default "View details" row-meta link with "Visit plugin site"
	 * pointing to the Gumroad product page.
	 */
	public function row_meta( array $meta, string $file ): array {
		if ( plugin_basename( PLUGPANDA_ACF_FCP_PLUGIN_FILE ) !== $file ) {
			return $meta;
		}

		$url = 'https://plugpandas.gumroad.com/l/acf-flexible-content-picker';

		// Drop the "View details" thickbox link and any existing site link to avoid duplicates.
		$meta = array_filter( $meta, static function ( $item ) use ( $url ) {
			return false === strpos( $item, 'plugin-information' ) && false === strpos( $item, $url );
		} );

		$meta[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( $url ),
			esc_html__( 'Visit plugin site', 'acf-flexible-content-picker' )
		);

		return array_values( $meta );
	}

	public function maybe_force_refresh(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['force-check'] ) ) {
			delete_transient( self::TRANSIENT_KEY );
		}
	}

	/**
	 * Latest release info, cached 6h. Null on any failure.
	 */
	private function fetch_remote(): ?object {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get( self::API_URL, [ 'timeout' => 10 ] );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$release = json_decode( wp_remote_retrieve_body( $response ) );
		$data    = ( $release && ! empty( $release->tag_name ) ) ? $this->parse_release( $release ) : null;
		if ( $data ) {
			set_transient( self::TRANSIENT_KEY, $data, self::CACHE_TTL );
		}

		return $data;
	}

	/**
	 * Map a GitHub release to the fields we need. Null if no version or zip asset.
	 */
	private function parse_release( object $release ): ?object {
		$version = ltrim( $release->tag_name, 'v' );
		if ( '' === $version ) {
			return null;
		}

		$zip_url = '';
		foreach ( $release->assets ?? [] as $asset ) {
			if ( '.zip' === substr( $asset->name ?? '', -4 ) ) {
				$zip_url = $asset->browser_download_url ?? '';
				break;
			}
		}
		if ( '' === $zip_url ) {
			return null;
		}

		$data               = new \stdClass();
		$data->version      = $version;
		$data->download_url = $zip_url;
		$data->last_updated = empty( $release->published_at ) ? '' : gmdate( 'Y-m-d', strtotime( $release->published_at ) );
		$data->sections     = [ 'changelog' => $this->markdown_to_html( $release->body ?? '' ) ];

		return $data;
	}

	/**
	 * Convert the Markdown subset GitHub release notes use to HTML for WP's modal.
	 */
	private function markdown_to_html( string $markdown ): string {
		if ( '' === $markdown ) {
			return '';
		}

		$html = htmlspecialchars( $markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		$html = preg_replace( '/^#{1,6}\s+(.+)$/m', '<h4>$1</h4>', $html ); // Headings.
		$html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html ); // Bold.
		$html = preg_replace( '/^[-*]\s+(.+)$/m', '<li>$1</li>', $html ); // List items.
		$html = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html );
		$html = preg_replace( '/\n{3,}/', "\n\n", $html );

		return nl2br( trim( $html ) );
	}

	/**
	 * Inject our update into the plugin update transient on every read.
	 */
	public function inject_update( mixed $transient ): mixed {
		if ( ! is_object( $transient ) ) {
			$transient = new \stdClass();
		}

		$remote = $this->fetch_remote();
		if ( ! $remote ) {
			return $transient;
		}

		$basename  = plugin_basename( PLUGPANDA_ACF_FCP_PLUGIN_FILE );
		$installed = $transient->checked[ $basename ] ?? PLUGPANDA_ACF_FCP_PLUGIN_VERSION;

		// Newer release → "response" bucket (shows the notice); otherwise "no_update".
		[ $bucket, $other ] = version_compare( $remote->version, $installed, '>' )
			? [ 'response', 'no_update' ]
			: [ 'no_update', 'response' ];

		if ( ! isset( $transient->$bucket ) || ! is_array( $transient->$bucket ) ) {
			$transient->$bucket = [];
		}
		$transient->$bucket[ $basename ] = $this->build_update_object( $basename, $remote );
		unset( $transient->$other[ $basename ] );

		return $transient;
	}

	/**
	 * The object WordPress stores in response / no_update.
	 */
	private function build_update_object( string $basename, object $remote ): \stdClass {
		$obj               = new \stdClass();
		$obj->id           = self::API_URL;
		$obj->slug         = self::PLUGIN_SLUG;
		$obj->plugin       = $basename;
		$obj->new_version  = $remote->version;
		$obj->url          = 'https://plugpandas.gumroad.com/l/acf-flexible-content-picker';
		$obj->package      = $remote->download_url;
		$obj->tested       = self::TESTED;
		$obj->requires     = self::REQUIRES;
		$obj->requires_php = self::REQUIRES_PHP;
		$obj->icons        = [];
		$obj->banners      = [];
		$obj->banners_rtl  = [];

		return $obj;
	}

	/**
	 * Rename the unzipped folder to the plugin slug so WP updates in place.
	 */
	public function normalize_source_dir( mixed $source, mixed $remote_source, mixed $upgrader, mixed $hook_extra = [] ): mixed {
		if ( ! is_array( $hook_extra ) || ( $hook_extra['plugin'] ?? '' ) !== plugin_basename( PLUGPANDA_ACF_FCP_PLUGIN_FILE ) ) {
			return $source;
		}

		global $wp_filesystem;
		$desired = trailingslashit( $remote_source ) . self::PLUGIN_SLUG;

		if ( untrailingslashit( $source ) === untrailingslashit( $desired ) ) {
			return $source;
		}

		return ( $wp_filesystem instanceof \WP_Filesystem_Base && $wp_filesystem->move( $source, $desired ) )
			? trailingslashit( $desired )
			: $source;
	}

	/**
	 * Power the "View details" modal on the Plugins screen.
	 */
	public function plugin_info( mixed $result, string $action, object $args ): mixed {
		if ( 'plugin_information' !== $action || self::PLUGIN_SLUG !== ( $args->slug ?? '' ) ) {
			return $result;
		}

		$remote = $this->fetch_remote();
		if ( ! $remote ) {
			return $result;
		}

		$info                = new \stdClass();
		$info->name          = 'ACF Flexible Content Picker';
		$info->slug          = self::PLUGIN_SLUG;
		$info->version       = $remote->version;
		$info->author        = '<a href="https://plugpandas.gumroad.com">PlugPanda</a>';
		$info->requires      = self::REQUIRES;
		$info->requires_php  = self::REQUIRES_PHP;
		$info->tested        = self::TESTED;
		$info->last_updated  = $remote->last_updated;
		$info->sections      = $remote->sections;
		$info->download_link = $remote->download_url;

		return $info;
	}

	/**
	 * Clear our cache after the plugin updates so the next read reflects the new version.
	 */
	public function purge_cache( mixed $upgrader, array $hook_extra ): void {
		$plugins = $hook_extra['plugins'] ?? [ $hook_extra['plugin'] ?? '' ];

		if ( in_array( plugin_basename( PLUGPANDA_ACF_FCP_PLUGIN_FILE ), $plugins, true ) ) {
			delete_transient( self::TRANSIENT_KEY );
		}
	}
}
