<?php
/**
 * Plugin Name: Virtual Media Folders - Add-On Manager
 * Description: Install and manage add-ons that extend Virtual Media Folders.
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.3
 * Author: Per Soderlind
 * Author URI: https://soderlind.no/
 * License: GPL-2.0-or-later
 * Text Domain: vmfa
 * Domain Path: /languages
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VMFA_VERSION', '1.0.0' );
define( 'VMFA_FILE', __FILE__ );
define( 'VMFA_PATH', __DIR__ . '/' );
define( 'VMFA_URL', plugin_dir_url( __FILE__ ) );

require_once VMFA_PATH . 'vendor/autoload.php';
require_once VMFA_PATH . 'src/AddonCatalog.php';
require_once VMFA_PATH . 'src/AddonManager.php';

if ( ! class_exists( \Soderlind\WordPress\GitHubUpdater::class) ) {
	require_once __DIR__ . '/class-github-updater.php';
}
\Soderlind\WordPress\GitHubUpdater::init(
	github_url: 'https://github.com/soderlind/vmfa',
	plugin_file: VMFA_FILE,
	plugin_slug: 'vmfa',
	name_regex: '/vmfa\.zip/',
	branch: 'main',
);

add_action( 'init', static function (): void {
	load_plugin_textdomain( 'vmfa', false, dirname( plugin_basename( VMFA_FILE ) ) . '/languages' );
} );

add_action( 'plugins_loaded', static function (): void {
	if ( is_admin() ) {
		\VMFA\AddonManager::init();
	}
} );
