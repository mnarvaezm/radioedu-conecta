<?php
/**
 * PHPUnit bootstrap file for RadioEdu Conecta.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

// Load Composer autoloader.
if ( file_exists( dirname( __DIR__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __DIR__ ) . '/vendor/autoload.php';
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	// Load WordPress test suite.
	require_once "{$_tests_dir}/includes/functions.php";

	/**
	 * Manually load the plugin being tested.
	 */
	function _radioedu_conecta_manually_load_plugin(): void {
		require dirname( __DIR__ ) . '/radioedu-conecta.php';
	}

	tests_add_filter( 'muplugins_loaded', '_radioedu_conecta_manually_load_plugin' );

	require "{$_tests_dir}/includes/bootstrap.php";
} else {
	// Standalone fallback stubs when running unit tests without the full WP test library.
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	}
	if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
		define( 'HOUR_IN_SECONDS', 3600 );
	}
}
