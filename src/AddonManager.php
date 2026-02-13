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
		add_filter( 'plugins_api', [ self::class, 'inject_addon_info' ], 20, 3 );
		add_action( 'wp_ajax_vmfa_addon_details', [ self::class, 'ajax_addon_details' ] );
	}

	/**
	 * Add the Add-on Manager page under Media.
	 *
	 * @return void
	 */
	public static function add_menu_page(): void {
		$menu_title = __( 'Add-on Manager', 'vmfa' );
		$count      = self::get_update_count();

		if ( $count > 0 ) {
			$menu_title .= sprintf(
				' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>',
				$count
			);
		}

		add_submenu_page(
			'upload.php',
			__( 'Virtual Media Folders Add-ons', 'vmfa' ),
			$menu_title,
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
			[ 'thickbox' ],
			VMFA_VERSION
		);

		add_thickbox();
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
							<div class="vmfa-card-bottom-left">
								<?php
								$details_url = add_query_arg(
									[
										'action'    => 'vmfa_addon_details',
										'plugin'    => $addon[ 'slug' ],
										'TB_iframe' => 'true',
										'width'     => '772',
										'height'    => '550',
									],
									admin_url( 'admin-ajax.php' )
								);
								?>
								<a href="<?php echo esc_url( $details_url ); ?>" class="thickbox open-plugin-details-modal">
									<?php echo esc_html__( 'View details', 'vmfa' ); ?>
								</a>
								<?php if ( $update_available ) : ?>
									<span class="vmfa-update-badge">
										<?php echo esc_html__( 'Update available', 'vmfa' ); ?>
									</span>
								<?php endif; ?>
							</div>
							<a href="<?php echo esc_url( $addon[ 'repo_url' ] ); ?>" target="_blank" rel="noopener noreferrer" class="vmfa-github-link" title="<?php echo esc_attr__( 'View on GitHub', 'vmfa' ); ?>">
								<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8z"/></svg>
							</a>
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
	 * AJAX handler for the "View details" Thickbox modal.
	 *
	 * Delegates to WordPress core's install_plugin_information(),
	 * which calls plugins_api() — intercepted by inject_addon_info() —
	 * then renders the native plugin-information iframe.
	 *
	 * @return void
	 */
	public static function ajax_addon_details(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view add-on details.', 'vmfa' ) );
		}

		// Ensure hook_suffix is set so admin_enqueue_scripts callbacks
		// receive a string, not null (prevents crashes in mu-plugins).
		if ( ! isset( $GLOBALS['hook_suffix'] ) ) {
			$GLOBALS['hook_suffix'] = '';
		}

		// Set a screen object so iframe_header() can call get_current_screen().
		if ( ! function_exists( 'set_current_screen' ) ) {
			require_once ABSPATH . 'wp-admin/includes/screen.php';
		}
		set_current_screen();

		// install_plugin_information() uses global $tab to build element IDs
		// like #plugin-information-tabs and #plugin-information-content.
		// Without this, the IDs become #-tabs / #-content and CSS won't match.
		global $tab, $body_id;
		$tab     = 'plugin-information';
		$body_id = 'plugin-information'; // iframe_header() sets <body id="$body_id">; CSS targets #plugin-information .fyi etc.

		if ( ! function_exists( 'install_plugin_information' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		// Inject client-side tab switching so tab clicks don't reload the iframe.
		add_action( 'admin_footer', [ __CLASS__, 'inline_tab_script' ] );

		// install_plugin_information() reads slug from $_REQUEST['plugin'].
		install_plugin_information();

		exit;
	}

	/**
	 * Print inline JS for client-side tab switching in the plugin details modal.
	 *
	 * All section <div>s are already rendered with display:none/block.
	 * This script intercepts tab clicks to toggle visibility instantly
	 * instead of reloading the iframe for each tab.
	 *
	 * @return void
	 */
	public static function inline_tab_script(): void {
		?>
		<script>
		(function(){
			var tabs = document.getElementById('plugin-information-tabs');
			if ( ! tabs ) return;
			tabs.addEventListener( 'click', function( e ) {
				var link = e.target.closest('a');
				if ( ! link ) return;
				e.preventDefault();
				var section = link.getAttribute('name');
				if ( ! section ) return;
				// Update tab active state.
				var allTabs = tabs.querySelectorAll('a');
				for ( var i = 0; i < allTabs.length; i++ ) {
					allTabs[i].className = '';
				}
				link.className = 'current';
				// Toggle section visibility.
				var holder = document.getElementById('section-holder');
				if ( ! holder ) return;
				var sections = holder.querySelectorAll('.section');
				for ( var j = 0; j < sections.length; j++ ) {
					sections[j].style.display = 'none';
				}
				var target = document.getElementById('section-' + section);
				if ( target ) target.style.display = 'block';
			});
		})();
		</script>
		<?php
	}

	/**
	 * Intercept plugins_api for add-on slugs.
	 *
	 * Fetches the add-on's readme.txt from GitHub, parses it with
	 * PucReadmeParser, and returns a stdClass that WordPress core's
	 * install_plugin_information() renders natively.
	 *
	 * @param false|object|array $result The result object or array. Default false.
	 * @param string             $action The type of information being requested.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object
	 */
	public static function inject_addon_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		$slug = $args->slug ?? '';

		$addon = AddonCatalog::get( $slug );
		if ( ! $addon ) {
			return $result;
		}

		$readme = self::fetch_readme( $addon );

		$info               = new \stdClass();
		$info->name         = html_entity_decode( $readme[ 'name' ] ?: $addon[ 'title' ], ENT_QUOTES, 'UTF-8' );
		$info->slug         = $addon[ 'slug' ];
		$info->version      = $readme[ 'stable_tag' ] ?: '';
		$info->requires     = $readme[ 'requires_at_least' ] ?: '';
		$info->tested       = self::normalize_tested( $readme[ 'tested_up_to' ] ?: '' );
		$info->requires_php = $readme[ 'requires_php' ] ?: '';
		$info->author       = '<a href="https://soderlind.no">Per Soderlind</a>';
		$info->homepage     = $addon[ 'repo_url' ];
		$info->external     = true;

		$sections = array_merge( [ 'description' => '' ], $readme[ 'sections' ] ?? [] );
		unset( $sections['screenshots'] );
		$info->sections = $sections;

		return $info;
	}

	/**
	 * Fetch and parse the readme.txt for an add-on.
	 *
	 * @param array<string, string> $addon Add-on metadata.
	 * @return array<string, mixed> Parsed readme data.
	 */
	private static function fetch_readme( array $addon ): array {
		$transient_key = 'vmfa_readme_' . $addon[ 'slug' ];
		$cached        = get_transient( $transient_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get(
			$addon[ 'readme_url' ],
			[
				'timeout'    => 10,
				'user-agent' => 'Virtual-Media-Folders',
			]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return self::empty_readme();
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return self::empty_readme();
		}

		if ( ! class_exists( 'PucReadmeParser', false ) ) {
			$parser_path = VMFA_PATH . 'vendor/yahnis-elsts/plugin-update-checker/vendor/PucReadmeParser.php';
			if ( file_exists( $parser_path ) ) {
				require_once $parser_path;
			}
		}

		if ( ! class_exists( 'PucReadmeParser', false ) ) {
			return self::empty_readme();
		}

		$parser = new \PucReadmeParser();
		$parsed = $parser->parse_readme_contents( $body );

		if ( ! is_array( $parsed ) || empty( $parsed ) ) {
			return self::empty_readme();
		}

		set_transient( $transient_key, $parsed, 6 * HOUR_IN_SECONDS );

		return $parsed;
	}

	/**
	 * Return a minimal empty readme structure.
	 *
	 * @return array<string, mixed>
	 */
	private static function empty_readme(): array {
		return [
			'name'              => '',
			'requires_at_least' => '',
			'tested_up_to'      => '',
			'requires_php'      => '',
			'stable_tag'        => '',
			'short_description' => '',
			'sections'          => [],
		];
	}

	/**
	 * Normalise the "Tested up to" value so patch-level differences
	 * don't trigger a false-positive "not tested" warning.
	 *
	 * WordPress core compares `get_bloginfo('version') <= $tested`
	 * literally, so 6.9.1 > 6.9 ⇒ warning. If the readme says "6.9"
	 * and the site runs 6.9.x, we return the full site version.
	 *
	 * @param string $tested The "Tested up to" value from readme.txt.
	 * @return string Adjusted value.
	 */
	private static function normalize_tested( string $tested ): string {
		if ( '' === $tested ) {
			return '';
		}

		$wp_version = get_bloginfo( 'version' );

		// Already an exact match or higher — nothing to do.
		if ( version_compare( $wp_version, $tested, '<=' ) ) {
			return $tested;
		}

		// Compare only major.minor (first two segments).
		$tested_parts = explode( '.', $tested );
		$wp_parts     = explode( '.', $wp_version );

		$tested_minor = ( $tested_parts[0] ?? '0' ) . '.' . ( $tested_parts[1] ?? '0' );
		$wp_minor     = ( $wp_parts[0] ?? '0' ) . '.' . ( $wp_parts[1] ?? '0' );

		if ( $tested_minor === $wp_minor ) {
			return $wp_version;
		}

		return $tested;
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
	 * Count installed add-ons that have an update available.
	 *
	 * @return int
	 */
	private static function get_update_count(): int {
		self::require_plugin_functions();

		$count = 0;

		foreach ( AddonCatalog::all() as $addon ) {
			$status = self::get_status( $addon );

			if ( ! $status['installed'] || ! $status['version'] ) {
				continue;
			}

			$latest = self::get_latest_release_version( $addon['slug'], $addon['repo_url'] );

			if ( $latest && version_compare( $status['version'], self::normalize_version( $latest ), '<' ) ) {
				++$count;
			}
		}

		return $count;
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
