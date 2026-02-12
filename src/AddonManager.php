<?php
/**
 * Add-on Manager admin page.
 *
 * @package VMFA
 * @since 0.1.0
 */

declare(strict_types=1);

namespace VMFA;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the Add-on Manager page and handles actions.
 */
final class AddonManager {

	public const PAGE_SLUG = 'vmfa-addons';

	private const NONCE_ACTION = 'vmfa_addon_action';
	private const NONCE_FIELD  = 'vmfa_addon_nonce';
	private const ACTION_FIELD = 'vmfa_addon_action';
	private const SLUG_FIELD   = 'vmfa_addon_slug';

	/**
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu_page' ] );
		add_action( 'admin_init', [ self::class, 'handle_actions' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	/**
	 * Add the Add-on Manager page under Media.
	 *
	 * @return void
	 */
	public static function add_menu_page(): void {
		add_submenu_page(
			'upload.php',
			__( 'Virtual Media Folders Add-ons', 'vmfa' ),
			__( 'Add-on Manager', 'vmfa' ),
			'manage_options',
			self::PAGE_SLUG,
			[ self::class, 'render_page' ]
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'media_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'vmfa-admin',
			VMFA_URL . 'assets/css/admin.css',
			[],
			VMFA_VERSION
		);
	}

	/**
	 * Handle add-on actions submitted from the manager page.
	 *
	 * @return void
	 */
	public static function handle_actions(): void {
		if ( empty( $_POST[ self::ACTION_FIELD ] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage add-ons.', 'vmfa' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$action = sanitize_key( wp_unslash( $_POST[ self::ACTION_FIELD ] ) );
		$slug   = isset( $_POST[ self::SLUG_FIELD ] ) ? sanitize_key( wp_unslash( $_POST[ self::SLUG_FIELD ] ) ) : '';

		if ( 'check_updates' === $action ) {
			self::clear_release_cache();
			self::redirect_with_notice( __( 'Update checks refreshed.', 'vmfa' ), 'success' );
			return; // redirect_with_notice calls exit, but return for static analysis.
		}

		$addon = AddonCatalog::get( $slug );
		if ( ! $addon ) {
			self::redirect_with_notice( __( 'Unknown add-on.', 'vmfa' ), 'error' );
			return;
		}

		switch ( $action ) {
			case 'install':
				$result = self::install_addon( $addon );
				$label = __( 'installed', 'vmfa' );
				break;
			case 'activate':
				$result = self::activate_addon( $addon );
				$label = __( 'activated', 'vmfa' );
				break;
			case 'update':
				$result = self::update_addon( $addon );
				$label = __( 'updated', 'vmfa' );
				break;
			case 'deactivate':
				$result = self::deactivate_addon( $addon );
				$label = __( 'deactivated', 'vmfa' );
				break;
			case 'delete':
				$result = self::delete_addon( $addon );
				$label = __( 'deleted', 'vmfa' );
				break;
			default:
				self::redirect_with_notice( __( 'Unsupported action.', 'vmfa' ), 'error' );
				return;
		}

		if ( is_wp_error( $result ) ) {
			self::redirect_with_notice( $result->get_error_message(), 'error' );
		}

		/* translators: 1: Add-on title, 2: Action label. */
		self::redirect_with_notice( sprintf( __( '%1$s %2$s successfully.', 'vmfa' ), $addon[ 'title' ], $label ), 'success' );
	}

	/**
	 * Render the add-on manager page.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$catalog = AddonCatalog::all();

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		?>
		<div class="wrap vmfa-addon-manager">
			<h1><?php echo esc_html__( 'Virtual Media Folders Add-ons', 'vmfa' ); ?></h1>
			<?php self::render_notice(); ?>
			<p><?php echo esc_html__( 'Install and manage add-ons that extend Virtual Media Folders.', 'vmfa' ); ?></p>
			<form method="post" style="margin: 12px 0 20px;">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::ACTION_FIELD ); ?>" value="check_updates" />
				<button type="submit" class="button">
					<?php echo esc_html__( 'Check updates now', 'vmfa' ); ?>
				</button>
			</form>

			<div class="vmfa-grid">
				<?php foreach ( $catalog as $addon ) : ?>
					<?php
					$status            = self::get_status( $addon );
					$latest_version    = self::get_latest_release_version( $addon[ 'slug' ], $addon[ 'repo_url' ] );
					$normalized_latest = $latest_version ? self::normalize_version( $latest_version ) : '';
					$update_available  = $status[ 'installed' ] && $normalized_latest && $status[ 'version' ]
						? version_compare( $status[ 'version' ], $normalized_latest, '<' )
						: false;
					?>
					<div class="vmfa-card">
						<div class="vmfa-card-top">
							<div class="vmfa-card-header">
								<h3><?php echo esc_html( $addon[ 'title' ] ); ?></h3>
								<ul class="vmfa-card-actions">
									<?php self::render_action_buttons( $addon, $status, $update_available ); ?>
								</ul>
							</div>
							<div class="vmfa-card-desc">
								<p><?php echo wp_kses_post( $addon[ 'description' ] ); ?></p>
								<p>
									<?php
									printf(
										/* translators: 1: Current status label. */
										esc_html__( 'Status: %1$s', 'vmfa' ),
										esc_html( $status[ 'label' ] )
									);
									?>
								</p>
								<p>
									<?php
									printf(
										/* translators: 1: Installed version or dash, 2: Latest version or dash. */
										esc_html__( 'Installed: %1$s | Latest: %2$s', 'vmfa' ),
										esc_html( $status[ 'version' ] ?: '-' ),
										esc_html( $normalized_latest ?: '-' )
									);
									?>
								</p>
							</div>
						</div>
						<div class="vmfa-card-bottom">
							<a href="<?php echo esc_url( $addon[ 'repo_url' ] ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html__( 'View on GitHub', 'vmfa' ); ?>
							</a>
							<?php if ( $update_available ) : ?>
								<span class="vmfa-update-badge">
									<?php echo esc_html__( 'Update available', 'vmfa' ); ?>
								</span>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render action buttons for an add-on.
	 *
	 * @param array<string, string> $addon Add-on metadata.
	 * @param array<string, mixed>  $status Status data.
	 * @param bool                 $update_available Whether an update is available.
	 * @return void
	 */
	private static function render_action_buttons( array $addon, array $status, bool $update_available ): void {
		if ( ! $status[ 'installed' ] ) {
			self::render_action_form( $addon, 'install', __( 'Install', 'vmfa' ), 'button' );
			return;
		}

		if ( $status[ 'active' ] ) {
			self::render_action_form( $addon, 'deactivate', __( 'Deactivate', 'vmfa' ), 'button' );
		} else {
			self::render_action_form( $addon, 'activate', __( 'Activate', 'vmfa' ), 'button-primary' );
		}

		if ( $update_available ) {
			self::render_action_form( $addon, 'update', __( 'Update', 'vmfa' ), 'button' );
		}

		self::render_action_form( $addon, 'delete', __( 'Delete', 'vmfa' ), 'button-link-delete', __( 'Are you sure you want to delete this add-on? This cannot be undone.', 'vmfa' ) );
	}

	/**
	 * Render a single action form button.
	 *
	 * @param array<string, string> $addon Add-on metadata.
	 * @param string                $action Action slug.
	 * @param string                $label Button label.
	 * @param string                $class Button class.
	 * @param string                $confirm_message Confirmation message.
	 * @return void
	 */
	private static function render_action_form( array $addon, string $action, string $label, string $class, string $confirm_message = '' ): void {
		$confirm_attr = '';
		if ( $confirm_message !== '' ) {
			$confirm_attr = ' onclick="return confirm(\'' . esc_js( $confirm_message ) . '\');"';
		}

		?>
		<li>
			<form method="post">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::ACTION_FIELD ); ?>"
					value="<?php echo esc_attr( $action ); ?>" />
				<input type="hidden" name="<?php echo esc_attr( self::SLUG_FIELD ); ?>"
					value="<?php echo esc_attr( $addon[ 'slug' ] ); ?>" />
				<button type="submit" class="<?php echo esc_attr( $class ); ?>" <?php
					 echo $confirm_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above via esc_js.
			 		?>>
					<?php echo esc_html( $label ); ?>
				</button>
			</form>
		</li>
		<?php
	}

	/**
	 * Get add-on status.
	 *
	 * @param array<string, string> $addon Add-on metadata.
	 * @return array<string, mixed>
	 */
	private static function get_status( array $addon ): array {
		self::require_plugin_functions();

		$plugin_file = $addon[ 'plugin_file' ];
		$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

		$installed = file_exists( $plugin_path );
		$active    = false;
		$version   = '';

		if ( $installed ) {
			$active = is_plugin_active( $plugin_file );

			$plugin_data = get_plugin_data( $plugin_path, false, false );
			$version     = $plugin_data[ 'Version' ] ?? '';
		}

		$label = $installed
			? ( $active ? __( 'Active', 'vmfa' ) : __( 'Installed', 'vmfa' ) )
			: __( 'Not installed', 'vmfa' );

		return [
			'installed' => $installed,
			'active'    => $active,
			'version'   => $version,
			'label'     => $label,
		];
	}

	/**
	 * Install an add-on.
	 *
	 * @param array<string, string> $addon Add-on metadata.
	 * @return true|\WP_Error
	 */
	private static function install_addon( array $addon ) {
		if ( self::get_status( $addon )[ 'installed' ] ) {
			return new \WP_Error( 'vmfa_addon_installed', __( 'Add-on is already installed.', 'vmfa' ) );
		}

		return self::run_zip_install( $addon[ 'zip_url' ], $addon[ 'plugin_file' ], false );
	}

	/**
	 * Update an add-on.
	 *
	 * @param array<string, string> $addon Add-on metadata.
	 * @return true|\WP_Error
	 */
	private static function update_addon( array $addon ) {
		return self::run_zip_install( $addon[ 'zip_url' ], $addon[ 'plugin_file' ], true );
	}

	/**
	 * Activate an add-on.
	 *
	 * @param array<string, string> $addon Add-on metadata.
	 * @return true|\WP_Error
	 */
	private static function activate_addon( array $addon ) {
		self::require_plugin_functions();

		$result = activate_plugin( $addon[ 'plugin_file' ] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		wp_clean_plugins_cache( true );

		return true;
	}

	/**
	 * Deactivate an add-on.
	 *
	 * @param array<string, string> $addon Add-on metadata.
	 * @return true|\WP_Error
	 */
	private static function deactivate_addon( array $addon ) {
		self::require_plugin_functions();

		deactivate_plugins( $addon[ 'plugin_file' ], true );
		wp_clean_plugins_cache( true );

		return true;
	}

	/**
	 * Delete an add-on.
	 *
	 * @param array<string, string> $addon Add-on metadata.
	 * @return true|\WP_Error
	 */
	private static function delete_addon( array $addon ) {
		self::require_plugin_functions();

		if ( is_plugin_active( $addon[ 'plugin_file' ] ) ) {
			deactivate_plugins( $addon[ 'plugin_file' ], true );
		}

		$result = delete_plugins( [ $addon[ 'plugin_file' ] ] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result ) {
			return new \WP_Error( 'vmfa_addon_delete_failed', __( 'Unable to delete the add-on.', 'vmfa' ) );
		}

		wp_clean_plugins_cache( true );

		return true;
	}

	/**
	 * Install or update a plugin from a zip URL.
	 *
	 * @param string $zip_url Zip download URL.
	 * @param string $plugin_file Plugin file path.
	 * @param bool   $overwrite Whether to overwrite existing files.
	 * @return true|\WP_Error
	 */
	private static function run_zip_install( string $zip_url, string $plugin_file, bool $overwrite ) {
		self::require_plugin_functions();

		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );

		if ( $overwrite ) {
			add_filter( 'upgrader_package_options', static function ( array $options ) use ( $plugin_file ): array {
				$options[ 'clear_destination' ]           = true;
				$options[ 'abort_if_destination_exists' ] = false;

				return $options;
			} );
		}

		$result = $upgrader->install( $zip_url );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $result ) {
			$error = $upgrader->skin->get_errors();
			if ( $error instanceof \WP_Error && $error->has_errors() ) {
				return $error;
			}

			return new \WP_Error( 'vmfa_addon_install_failed', __( 'Unable to install the add-on.', 'vmfa' ) );
		}

		wp_clean_plugins_cache( true );

		return true;
	}

	/**
	 * Fetch the latest GitHub release tag for an add-on.
	 *
	 * @param string $slug Add-on slug.
	 * @param string $repo_url Repository URL.
	 * @return string|null
	 */
	private static function get_latest_release_version( string $slug, string $repo_url ): ?string {
		$transient_key = 'vmfa_addon_release_' . $slug;
		$cached        = get_transient( $transient_key );
		if ( is_string( $cached ) && $cached !== '' ) {
			return $cached;
		}

		$api_url = sprintf( 'https://api.github.com/repos/%s/releases/latest', trim( str_replace( 'https://github.com/', '', $repo_url ), '/' ) );

		$response = wp_remote_get(
			$api_url,
			[
				'timeout' => 10,
				'headers' => [
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Virtual-Media-Folders',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body[ 'tag_name' ] ) ) {
			return null;
		}

		$tag_name = sanitize_text_field( (string) $body[ 'tag_name' ] );
		set_transient( $transient_key, $tag_name, 6 * HOUR_IN_SECONDS );

		return $tag_name;
	}

	/**
	 * Normalize a version string by trimming leading "v".
	 *
	 * @param string $version Version string.
	 * @return string
	 */
	private static function normalize_version( string $version ): string {
		return ltrim( $version, 'vV' );
	}

	/**
	 * Redirect back to the add-on manager with a notice.
	 *
	 * @param string $message Notice message.
	 * @param string $type Notice type.
	 * @return void
	 */
	private static function redirect_with_notice( string $message, string $type ): void {
		$target = add_query_arg(
			[
				'page'                   => self::PAGE_SLUG,
				'vmfa_addon_notice'      => $message,
				'vmfa_addon_notice_type' => $type,
			],
			admin_url( 'upload.php' )
		);

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Render notice if present.
	 *
	 * @return void
	 */
	private static function render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET[ 'vmfa_addon_notice' ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type = isset( $_GET[ 'vmfa_addon_notice_type' ] ) ? sanitize_key( $_GET[ 'vmfa_addon_notice_type' ] ) : 'success';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = sanitize_text_field( wp_unslash( $_GET[ 'vmfa_addon_notice' ] ) );

		$class = 'success' === $type ? 'notice notice-success is-dismissible' : 'notice notice-error is-dismissible';

		printf(
			'<div class="%1$s"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}

	/**
	 * Clear cached release tags for all add-ons.
	 *
	 * @return void
	 */
	private static function clear_release_cache(): void {
		foreach ( AddonCatalog::all() as $addon ) {
			delete_transient( 'vmfa_addon_release_' . $addon[ 'slug' ] );
		}
	}

	/**
	 * Ensure wp-admin/includes/plugin.php is loaded.
	 *
	 * @return void
	 */
	private static function require_plugin_functions(): void {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}
}
