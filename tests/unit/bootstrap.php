<?php
/**
 * PHPUnit bootstrap file for Brain Monkey integration.
 *
 * @package VMFA\Tests
 */

declare(strict_types=1);

// Composer autoloader.
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// Define WordPress constants used by the plugin.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', '/tmp/wordpress/wp-content/plugins' );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

// Plugin constants.
if ( ! defined( 'VMFA_VERSION' ) ) {
	define( 'VMFA_VERSION', '1.0.0' );
}
if ( ! defined( 'VMFA_FILE' ) ) {
	define( 'VMFA_FILE', dirname( __DIR__, 2 ) . '/vmfa.php' );
}
if ( ! defined( 'VMFA_PATH' ) ) {
	define( 'VMFA_PATH', dirname( __DIR__, 2 ) . '/' );
}
if ( ! defined( 'VMFA_URL' ) ) {
	define( 'VMFA_URL', 'https://example.com/wp-content/plugins/vmfa/' );
}

// Minimal WP_Error stub for unit tests.
if ( ! class_exists( 'WP_Error' ) ) {
	// phpcs:ignore
	class WP_Error {
		public $errors = [];
		public $error_data = [];

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( $code ) {
				$this->errors[ $code ][] = $message;
				if ( $data ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		public function get_error_code() {
			$codes = array_keys( $this->errors );
			return $codes[ 0 ] ?? '';
		}

		public function get_error_message( $code = '' ) {
			if ( ! $code ) {
				$code = $this->get_error_code();
			}
			return $this->errors[ $code ][ 0 ] ?? '';
		}

		public function has_errors() {
			return ! empty( $this->errors );
		}
	}
}

// Load plugin source files.
require_once dirname( __DIR__, 2 ) . '/src/AddonCatalog.php';
require_once dirname( __DIR__, 2 ) . '/src/AddonManager.php';
