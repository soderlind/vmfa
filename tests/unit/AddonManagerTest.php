<?php
/**
 * Tests for AddonManager.
 *
 * @package VMFA\Tests
 */

declare(strict_types=1);

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use VMFA\AddonManager;

beforeEach( function () {
	// Common WP function stubs.
	Functions\stubs(
		[
			'__'                  => static fn( string $text, string $domain = 'default' ): string => $text,
			'esc_html__'          => static fn( string $text, string $domain = 'default' ): string => $text,
			'esc_html'            => static fn( string $text ): string => $text,
			'esc_attr'            => static fn( string $text ): string => $text,
			'esc_url'             => static fn( string $url ): string => $url,
			'esc_js'              => static fn( string $text ): string => $text,
			'wp_kses_post'        => static fn( string $text ): string => $text,
			'sanitize_key'        => static fn( string $key ): string => strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $key ) ),
			'sanitize_text_field' => static fn( string $text ): string => $text,
			'wp_unslash'          => static fn( $value ) => $value,
			'plugin_basename'     => static fn( string $file ): string => basename( dirname( $file ) ) . '/' . basename( $file ),
		]
	);
} );

// --- init() ------------------------------------------------------------------

it( 'registers admin hooks on init', function () {
	Actions\expectAdded( 'admin_menu' )->once();
	Actions\expectAdded( 'admin_init' )->once();
	Actions\expectAdded( 'admin_enqueue_scripts' )->once();

	AddonManager::init();
} );

// --- enqueue_assets() --------------------------------------------------------

it( 'enqueues admin styles on the correct page', function () {
	Functions\expect( 'wp_enqueue_style' )
		->once()
		->with( 'vmfa-admin', \Mockery::type( 'string' ), [], VMFA_VERSION );

	AddonManager::enqueue_assets( 'media_page_vmfa-addons' );
} );

it( 'does not enqueue styles on other pages', function () {
	Functions\expect( 'wp_enqueue_style' )->never();

	AddonManager::enqueue_assets( 'toplevel_page_other' );
} );

// --- add_menu_page() ---------------------------------------------------------

it( 'adds submenu page under upload.php', function () {
	// get_update_count() calls require_plugin_functions(), get_status(), get_latest_release_version().
	Functions\when( 'get_plugins' )->justReturn( [] );
	Functions\when( 'is_plugin_active' )->justReturn( false );
	Functions\when( 'get_plugin_data' )->justReturn( [ 'Version' => '' ] );
	Functions\when( 'get_transient' )->justReturn( false );
	Functions\when( 'wp_remote_get' )->justReturn( new \WP_Error() );
	Functions\when( 'is_wp_error' )->justReturn( true );

	Functions\expect( 'add_submenu_page' )
		->once()
		->with(
			'upload.php',
			'Virtual Media Folders Add-ons',
			\Mockery::type( 'string' ),
			'manage_options',
			'vmfa-addons',
			\Mockery::type( 'array' )
		);

	AddonManager::add_menu_page();
} );

// --- handle_actions() --------------------------------------------------------

it( 'does nothing when no action is posted', function () {
	$_POST = [];

	Functions\expect( 'current_user_can' )->never();
	Functions\expect( 'check_admin_referer' )->never();

	AddonManager::handle_actions();
} );

it( 'dies when user lacks manage_options capability', function () {
	$_POST[ 'vmfa_addon_action' ] = 'install';

	Functions\expect( 'current_user_can' )
		->once()
		->with( 'manage_options' )
		->andReturn( false );

	Functions\expect( 'wp_die' )
		->once()
		->andReturnUsing( function () {
			throw new \RuntimeException( 'wp_die called' );
		} );

	expect( fn() => AddonManager::handle_actions() )
		->toThrow( \RuntimeException::class, 'wp_die called' );
} );

// --- normalize_version() via reflection --------------------------------------

it( 'normalizes version strings by stripping leading v', function () {
	$method = new ReflectionMethod( AddonManager::class, 'normalize_version' );

	expect( $method->invoke( null, 'v1.2.3' ) )->toBe( '1.2.3' );
	expect( $method->invoke( null, 'V2.0.0' ) )->toBe( '2.0.0' );
	expect( $method->invoke( null, '3.1.0' ) )->toBe( '3.1.0' );
	expect( $method->invoke( null, 'vvv1.0' ) )->toBe( '1.0' );
} );

// --- get_latest_release_version() --------------------------------------------

it( 'returns cached version from transient', function () {
	Functions\expect( 'get_transient' )
		->once()
		->with( 'vmfa_addon_release_vmfa-rules-engine' )
		->andReturn( 'v1.5.0' );

	Functions\expect( 'wp_remote_get' )->never();

	$method = new ReflectionMethod( AddonManager::class, 'get_latest_release_version' );
	$result = $method->invoke( null, 'vmfa-rules-engine', 'https://github.com/soderlind/vmfa-rules-engine' );

	expect( $result )->toBe( 'v1.5.0' );
} );

it( 'fetches version from GitHub API when no transient', function () {
	Functions\expect( 'get_transient' )
		->once()
		->andReturn( false );

	$mock_response = [
		'response' => [ 'code' => 200 ],
		'body'     => wp_json_encode( [ 'tag_name' => 'v2.0.0' ] ),
	];

	Functions\expect( 'wp_remote_get' )
		->once()
		->andReturn( $mock_response );

	Functions\expect( 'wp_remote_retrieve_response_code' )
		->once()
		->andReturn( 200 );

	Functions\expect( 'wp_remote_retrieve_body' )
		->once()
		->andReturn( json_encode( [ 'tag_name' => 'v2.0.0' ] ) );

	Functions\expect( 'set_transient' )
		->once()
		->with( 'vmfa_addon_release_vmfa-rules-engine', 'v2.0.0', 6 * HOUR_IN_SECONDS );

	$method = new ReflectionMethod( AddonManager::class, 'get_latest_release_version' );
	$result = $method->invoke( null, 'vmfa-rules-engine', 'https://github.com/soderlind/vmfa-rules-engine' );

	expect( $result )->toBe( 'v2.0.0' );
} );

it( 'returns null when GitHub API returns error', function () {
	Functions\expect( 'get_transient' )
		->once()
		->andReturn( false );

	Functions\expect( 'wp_remote_get' )
		->once()
		->andReturn( new \WP_Error( 'timeout', 'Request timed out' ) );

	Functions\when( 'is_wp_error' )->alias( fn( $thing ) => $thing instanceof \WP_Error );

	$method = new ReflectionMethod( AddonManager::class, 'get_latest_release_version' );
	$result = $method->invoke( null, 'vmfa-rules-engine', 'https://github.com/soderlind/vmfa-rules-engine' );

	expect( $result )->toBeNull();
} );

it( 'returns null when GitHub API returns non-200 status', function () {
	Functions\expect( 'get_transient' )
		->once()
		->andReturn( false );

	Functions\expect( 'wp_remote_get' )
		->once()
		->andReturn( [] );

	Functions\when( 'is_wp_error' )->alias( fn( $thing ) => $thing instanceof \WP_Error );

	Functions\expect( 'wp_remote_retrieve_response_code' )
		->once()
		->andReturn( 404 );

	$method = new ReflectionMethod( AddonManager::class, 'get_latest_release_version' );
	$result = $method->invoke( null, 'vmfa-rules-engine', 'https://github.com/soderlind/vmfa-rules-engine' );

	expect( $result )->toBeNull();
} );

// --- clear_release_cache() ---------------------------------------------------

it( 'deletes transients for all add-ons', function () {
	Functions\stubs( [ '__' => static fn( string $text, string $domain = 'default' ): string => $text ] );

	$catalog = \VMFA\AddonCatalog::all();

	Functions\expect( 'delete_transient' )
		->times( count( $catalog ) );

	$method = new ReflectionMethod( AddonManager::class, 'clear_release_cache' );
	$method->invoke( null );
} );

// --- get_status() ------------------------------------------------------------

it( 'returns not installed status when plugin file does not exist', function () {
	Functions\stubs(
		[
			'get_plugin_data'  => static fn() => [ 'Version' => '' ],
			'is_plugin_active' => static fn() => false,
		]
	);

	$addon = \VMFA\AddonCatalog::get( 'vmfa-rules-engine' );

	$method = new ReflectionMethod( AddonManager::class, 'get_status' );
	$result = $method->invoke( null, $addon );

	// The file won't exist at the test WP_PLUGIN_DIR path.
	expect( $result[ 'installed' ] )->toBeFalse();
	expect( $result[ 'active' ] )->toBeFalse();
	expect( $result[ 'version' ] )->toBe( '' );
	expect( $result[ 'label' ] )->toBe( 'Not installed' );
} );

// --- render_notice() ---------------------------------------------------------

it( 'renders nothing when no notice GET param', function () {
	$_GET = [];

	$method = new ReflectionMethod( AddonManager::class, 'render_notice' );

	ob_start();
	$method->invoke( null );
	$output = ob_get_clean();

	expect( $output )->toBe( '' );
} );

it( 'renders success notice from GET params', function () {
	$_GET[ 'vmfa_addon_notice' ]      = 'Something worked.';
	$_GET[ 'vmfa_addon_notice_type' ] = 'success';

	$method = new ReflectionMethod( AddonManager::class, 'render_notice' );

	ob_start();
	$method->invoke( null );
	$output = ob_get_clean();

	expect( $output )->toContain( 'notice-success' );
	expect( $output )->toContain( 'Something worked.' );
} );

it( 'renders error notice from GET params', function () {
	$_GET[ 'vmfa_addon_notice' ]      = 'Something failed.';
	$_GET[ 'vmfa_addon_notice_type' ] = 'error';

	$method = new ReflectionMethod( AddonManager::class, 'render_notice' );

	ob_start();
	$method->invoke( null );
	$output = ob_get_clean();

	expect( $output )->toContain( 'notice-error' );
	expect( $output )->toContain( 'Something failed.' );
} );

// --- PAGE_SLUG constant ------------------------------------------------------

it( 'has the expected page slug constant', function () {
	expect( AddonManager::PAGE_SLUG )->toBe( 'vmfa-addons' );
} );
