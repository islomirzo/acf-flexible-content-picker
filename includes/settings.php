<?php

namespace Acf_Fcp_Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin settings page under Settings > ACF Flexible Content Picker.
 * Handles AJAX license activation and deactivation.
 */
class Settings_Page {

	private static ?self $instance = null;

	public static function init(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
	}

	/** Option that controls whether all data is wiped on uninstall. */
	const OPTION_DELETE_DATA = 'plugpanda_acf_fcp_delete_data_on_uninstall';

	private function __construct() {
		add_action( 'admin_init',            [ $this, 'register_settings' ] );
		add_action( 'admin_menu',            [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'wp_ajax_plugpanda_acf_fcp_activate_license',   [ $this, 'ajax_activate' ] );
		add_action( 'wp_ajax_plugpanda_acf_fcp_deactivate_license', [ $this, 'ajax_deactivate' ] );
		add_action( 'wp_ajax_plugpanda_acf_fcp_check_update',       [ $this, 'ajax_check_update' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( PLUGPANDA_ACF_FCP_PLUGIN_FILE ), [ $this, 'action_links' ] );
	}

	/**
	 * Registers the uninstall-data option with the WordPress Settings API so
	 * options.php handles saving, nonce verification, and the redirect for free.
	 */
	public function register_settings(): void {
		register_setting(
			'plugpanda_acf_fcp_plugin_settings',
			self::OPTION_DELETE_DATA,
			[
				'type'              => 'boolean',
				'sanitize_callback' => static fn( $v ) => $v ? '1' : '0',
				'default'           => '0',
			]
		);
	}

	public function action_links( array $links ): array {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=plugpanda-acf-fcp-plugin' ) ),
			esc_html__( 'Settings', 'acf-flexible-content-picker' )
		);
		array_unshift( $links, $settings );
		return $links;
	}

	public function register_menu(): void {
		add_options_page(
			__( 'ACF Flexible Content Picker', 'acf-flexible-content-picker' ),
			__( 'ACF Flexible Content Picker', 'acf-flexible-content-picker' ),
			'manage_options',
			'plugpanda-acf-fcp-plugin',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue( string $hook ): void {
		if ( 'settings_page_plugpanda-acf-fcp-plugin' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'plugpanda-acf-fcp-plugin-settings',
			PLUGPANDA_ACF_FCP_PLUGIN_URL . 'assets/css/settings.css',
			[],
			PLUGPANDA_ACF_FCP_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'plugpanda-acf-fcp-plugin-settings',
			PLUGPANDA_ACF_FCP_PLUGIN_URL . 'assets/js/settings.js',
			[ 'jquery' ],
			PLUGPANDA_ACF_FCP_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'plugpanda-acf-fcp-plugin-settings',
			'AcfFcpSettings',
			[
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'plugpanda_acf_fcp_license' ),
				'isPro'     => License::is_pro(),
				'freeLimit' => License::FREE_LIMIT,
				'i18n'      => [
					'activating'   => __( 'Activating…', 'acf-flexible-content-picker' ),
					'deactivating' => __( 'Deactivating…', 'acf-flexible-content-picker' ),
					'checking'     => __( 'Checking…', 'acf-flexible-content-picker' ),
				],
			]
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$is_pro = License::is_pro();
		?>
		<div class="wrap plugpanda-acf-fcp-settings">
			<?php /* Invisible <h1>: WP's common.js moves admin notices after the first <h1> inside
			         .wrap — placing it here keeps notices above our styled header. */ ?>
			<h1 class="plugpanda-acf-fcp-settings__notice-anchor"><?php esc_html_e( 'ACF Flexible Content Picker', 'acf-flexible-content-picker' ); ?></h1>

			<div class="plugpanda-acf-fcp-settings__header">
				<div class="plugpanda-acf-fcp-settings__title">ACF Flexible Content Picker</div>
				<span class="plugpanda-acf-fcp-settings__version">v<?php echo esc_html( PLUGPANDA_ACF_FCP_PLUGIN_VERSION ); ?></span>
			</div>
			<p class="plugpanda-acf-fcp-settings__tagline"><?php esc_html_e( 'Replaces the ACF Flexible Content dropdown with a modal to preview, select, reorder, and add multiple layouts at once.', 'acf-flexible-content-picker' ); ?></p>

			<div class="plugpanda-acf-fcp-settings__body">
				<div class="plugpanda-acf-fcp-settings__col">
					<?php
					$this->render_how_to_use_card();
					$this->render_update_card();
					?>
				</div>
				<div class="plugpanda-acf-fcp-settings__col">
					<?php
					$this->render_plan_card( $is_pro );
					$this->render_license_card( $is_pro );
					$this->render_uninstall_card();
					?>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_how_to_use_card(): void {
		?>
		<div class="plugpanda-acf-fcp-settings__card">
			<div class="plugpanda-acf-fcp-settings__card-header">
				<h2><?php esc_html_e( 'How to Use', 'acf-flexible-content-picker' ); ?></h2>
			</div>
			<div class="plugpanda-acf-fcp-settings__card-body">
				<ol class="plugpanda-acf-fcp-settings__steps">
					<li class="plugpanda-acf-fcp-settings__step">
						<span class="plugpanda-acf-fcp-settings__step-num">1</span>
						<div class="plugpanda-acf-fcp-settings__step-body">
							<strong><?php esc_html_e( 'Configure your layouts', 'acf-flexible-content-picker' ); ?></strong>
							<p><?php esc_html_e( 'Open any Flexible Content field in the ACF field group editor and set an image, title, and description for each layout.', 'acf-flexible-content-picker' ); ?></p>
						</div>
					</li>
					<li class="plugpanda-acf-fcp-settings__step">
						<span class="plugpanda-acf-fcp-settings__step-num">2</span>
						<div class="plugpanda-acf-fcp-settings__step-body">
							<strong><?php esc_html_e( 'Use the modal on your content', 'acf-flexible-content-picker' ); ?></strong>
							<p><?php esc_html_e( 'When editing a post or page, click "Add Layout" to open the picker modal — preview thumbnails, select multiple layouts at once, and reorder them as you wish.', 'acf-flexible-content-picker' ); ?></p>
						</div>
					</li>
				</ol>
			</div>
		</div>
		<?php
	}

	/**
	 * Always-visible card: shows the installed version and a "Check for Updates"
	 * button. The check runs over AJAX (ajax_check_update); when a newer release
	 * is found, settings.js reveals the "Update Now" button.
	 */
	private function render_update_card(): void {
		$basename = plugin_basename( PLUGPANDA_ACF_FCP_PLUGIN_FILE );
		$updates  = get_site_transient( 'update_plugins' );
		$update   = is_object( $updates ) ? ( $updates->response[ $basename ] ?? null ) : null;
		$new_ver  = ( $update && ! empty( $update->new_version ) ) ? $update->new_version : '';

		$update_url = wp_nonce_url(
			self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . $basename ),
			'upgrade-plugin_' . $basename
		);
		?>
		<div class="plugpanda-acf-fcp-settings__card">
			<div class="plugpanda-acf-fcp-settings__card-header">
				<h2><?php esc_html_e( 'Updates', 'acf-flexible-content-picker' ); ?></h2>
			</div>
			<div class="plugpanda-acf-fcp-settings__card-body">
				<p class="plugpanda-acf-fcp-settings__update-text">
					<?php printf(
						/* translators: %s: installed version */
						esc_html__( 'Current installed version: %s', 'acf-flexible-content-picker' ),
						'<strong>' . esc_html( PLUGPANDA_ACF_FCP_PLUGIN_VERSION ) . '</strong>'
					); ?>
				</p>
				<div id="plugpanda-acf-fcp-update-notice" class="plugpanda-acf-fcp-settings__notice<?php echo $new_ver ? ' plugpanda-acf-fcp-settings__notice--info' : ''; ?>"<?php echo $new_ver ? '' : ' style="display:none;"'; ?>>
					<?php if ( $new_ver ) {
						printf(
							/* translators: %s: new version */
							esc_html__( 'Version %s is available.', 'acf-flexible-content-picker' ),
							esc_html( $new_ver )
						);
					} ?>
				</div>
				<div class="plugpanda-acf-fcp-settings__update-actions">
					<button type="button" id="plugpanda-acf-fcp-check-update" class="button">
						<?php esc_html_e( 'Check for Updates', 'acf-flexible-content-picker' ); ?>
					</button>
					<a href="<?php echo esc_url( $update_url ); ?>" id="plugpanda-acf-fcp-update-now" class="button button-primary"<?php echo $new_ver ? '' : ' style="display:none;"'; ?>>
						<?php esc_html_e( 'Update Now', 'acf-flexible-content-picker' ); ?>
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_plan_card( bool $is_pro ): void {
		?>
		<div class="plugpanda-acf-fcp-settings__card">
			<div class="plugpanda-acf-fcp-settings__card-header">
				<h2><?php esc_html_e( 'Your Plan', 'acf-flexible-content-picker' ); ?></h2>
			</div>
			<div class="plugpanda-acf-fcp-settings__card-body plugpanda-acf-fcp-settings__plan">
				<?php if ( $is_pro ) : ?>
					<span class="plugpanda-acf-fcp-settings__badge plugpanda-acf-fcp-settings__badge--pro">PRO</span>
					<p><?php esc_html_e( 'You have an active Pro license. All features are unlocked.', 'acf-flexible-content-picker' ); ?></p>
				<?php else : ?>
					<span class="plugpanda-acf-fcp-settings__badge plugpanda-acf-fcp-settings__badge--free">FREE</span>
					<p><?php printf(
						/* translators: %d: free layout limit */
						esc_html__( 'You are on the free plan (up to %d layouts per field). Upgrade to Pro for unlimited layouts.', 'acf-flexible-content-picker' ),
						License::FREE_LIMIT
					); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private function render_license_card( bool $is_pro ): void {
		$key = License::get_key();
		?>
		<div class="plugpanda-acf-fcp-settings__card">
			<div class="plugpanda-acf-fcp-settings__card-header">
				<h2><?php esc_html_e( 'License Key', 'acf-flexible-content-picker' ); ?></h2>
			</div>
			<div class="plugpanda-acf-fcp-settings__card-body">
				<div id="plugpanda-acf-fcp-license-notice" class="plugpanda-acf-fcp-settings__notice" style="display:none;"></div>
				<div class="plugpanda-acf-fcp-settings__license-row">
					<input
						type="text"
						id="plugpanda-acf-fcp-license-key"
						class="regular-text plugpanda-acf-fcp-settings__license-input"
						value="<?php echo esc_attr( $is_pro ? License::mask_key( $key ) : '' ); ?>"
						placeholder="<?php esc_attr_e( 'XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX', 'acf-flexible-content-picker' ); ?>"
						autocomplete="off"
						<?php echo $is_pro ? 'readonly' : ''; ?>
					>
					<?php if ( $is_pro ) : ?>
						<button type="button" id="plugpanda-acf-fcp-deactivate" class="button plugpanda-acf-fcp-settings__btn-deactivate">
							<?php esc_html_e( 'Deactivate', 'acf-flexible-content-picker' ); ?>
						</button>
					<?php else : ?>
						<button type="button" id="plugpanda-acf-fcp-activate" class="button button-primary">
							<?php esc_html_e( 'Activate License', 'acf-flexible-content-picker' ); ?>
						</button>
					<?php endif; ?>
				</div>
				<p class="description"<?php echo $is_pro ? ' style="display:none;"' : ''; ?>>
					<?php printf(
						wp_kses(
							/* translators: %s: purchase URL */
							__( 'Don\'t have a license key? <a href="%s" target="_blank" rel="noopener">Purchase Pro →</a>', 'acf-flexible-content-picker' ),
							[ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ]
						),
						'https://gmnuriddin.gumroad.com/l/plugpanda-acf-fcp-pro'
					); ?>
				</p>
			</div>
		</div>
		<?php
	}

	private function render_uninstall_card(): void {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'plugpanda_acf_fcp_plugin_settings' ); ?>
			<div class="plugpanda-acf-fcp-settings__card">
				<div class="plugpanda-acf-fcp-settings__card-header">
					<h2><?php esc_html_e( 'Manage Data', 'acf-flexible-content-picker' ); ?></h2>
				</div>
				<div class="plugpanda-acf-fcp-settings__card-body">
					<div class="plugpanda-acf-fcp-settings__data-row">
						<div class="plugpanda-acf-fcp-settings__toggle-row">
							<label class="plugpanda-acf-fcp-settings__toggle">
								<input
									type="checkbox"
									name="<?php echo esc_attr( self::OPTION_DELETE_DATA ); ?>"
									value="1"
									<?php checked( '1', get_option( self::OPTION_DELETE_DATA, '0' ) ); ?>
								>
								<span class="plugpanda-acf-fcp-settings__toggle-track"></span>
							</label>
							<span class="plugpanda-acf-fcp-settings__toggle-label"><?php esc_html_e( 'Delete all plugin data when uninstalling', 'acf-flexible-content-picker' ); ?></span>
						</div>
						<p class="plugpanda-acf-fcp-settings__data-hint">
							<?php esc_html_e( 'When enabled, all plugin data saved in this website will be permanently deleted when you uninstall this plugin.', 'acf-flexible-content-picker' ); ?>
						</p>
					</div>
					<div class="plugpanda-acf-fcp-settings__data-footer">
						<?php submit_button( __( 'Save', 'acf-flexible-content-picker' ), 'secondary', 'submit', false ); ?>
					</div>
				</div>
			</div>
		</form>
		<?php
	}

	public function ajax_activate(): void {
		check_ajax_referer( 'plugpanda_acf_fcp_license', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'acf-flexible-content-picker' ) ], 403 );
		}

		$key    = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );
		$result = License::activate( $key );

		if ( $result['success'] ) {
			wp_send_json_success( [
				'message'    => $result['message'],
				'masked_key' => $result['masked_key'] ?? '',
			] );
		} else {
			wp_send_json_error( [ 'message' => $result['message'] ] );
		}
	}

	/**
	 * Forces a fresh release check and reports whether a newer version exists.
	 * The "Update Now" link itself is rendered server-side (render_update_card).
	 */
	public function ajax_check_update(): void {
		check_ajax_referer( 'plugpanda_acf_fcp_license', 'nonce' );

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'acf-flexible-content-picker' ) ], 403 );
		}

		$basename = plugin_basename( PLUGPANDA_ACF_FCP_PLUGIN_FILE );

		// Drop our cached release info so the read below re-fetches from GitHub.
		delete_transient( 'plugpanda_acf_fcp_update_info' );

		$updates = get_site_transient( 'update_plugins' );
		$update  = is_object( $updates ) ? ( $updates->response[ $basename ] ?? null ) : null;

		if ( $update && ! empty( $update->new_version ) ) {
			wp_send_json_success( [
				'update_available' => true,
				'message'          => sprintf(
					/* translators: %s: new version */
					__( 'Version %s is available.', 'acf-flexible-content-picker' ),
					$update->new_version
				),
			] );
		}

		wp_send_json_success( [
			'update_available' => false,
			'message'          => __( 'You’re up to date.', 'acf-flexible-content-picker' ),
		] );
	}

	public function ajax_deactivate(): void {
		check_ajax_referer( 'plugpanda_acf_fcp_license', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'acf-flexible-content-picker' ) ], 403 );
		}

		License::deactivate();
		wp_send_json_success( [ 'message' => __( 'License deactivated.', 'acf-flexible-content-picker' ) ] );
	}
}
