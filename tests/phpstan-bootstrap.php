<?php
/**
 * PHPStan bootstrap: declares the plugin constants with their real types.
 *
 * The main plugin file cannot be used as bootstrap because the WordPress stubs
 * return null from plugin_dir_path()/plugin_basename(), which would make every
 * path constant `null` for the analyser.
 *
 * @package RadioEdu\Conecta\Tests
 */

define( 'RADIOEDU_CONECTA_VERSION', '1.0.0' );
define( 'RADIOEDU_CONECTA_MIN_WP', '7.0' );
define( 'RADIOEDU_CONECTA_MIN_PHP', '8.1' );
define( 'RADIOEDU_CONECTA_FILE', dirname( __DIR__ ) . '/radioedu-conecta.php' );
define( 'RADIOEDU_CONECTA_DIR', dirname( __DIR__ ) . '/' );
define( 'RADIOEDU_CONECTA_URL', 'https://example.org/wp-content/plugins/radioedu-conecta/' );
define( 'RADIOEDU_CONECTA_BASENAME', 'radioedu-conecta/radioedu-conecta.php' );
