<?php
/**
 * Plugin Name:       RadioEdu Conecta
 * Plugin URI:        https://radioedu.educarex.es/conecta
 * Description:       Directorio interactivo y sistema colaborativo ("Traspasando Fronteras") para la red de radios escolares de Extremadura y del mundo.
 * Version:           1.0.0
 * Requires at least: 7.1
 * Requires PHP:      8.1
 * Author:            RadioEdu - Consejería de Educación, Ciencia y Formación Profesional (Junta de Extremadura)
 * Author URI:        https://radioedu.educarex.es
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       radioedu-conecta
 * Domain Path:       /languages
 *
 * @package RadioEdu\Conecta
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'RADIOEDU_CONECTA_VERSION', '1.0.0' );
define( 'RADIOEDU_CONECTA_MIN_WP', '7.1' );
define( 'RADIOEDU_CONECTA_MIN_PHP', '8.1' );
define( 'RADIOEDU_CONECTA_FILE', __FILE__ );
define( 'RADIOEDU_CONECTA_DIR', plugin_dir_path( __FILE__ ) );
define( 'RADIOEDU_CONECTA_URL', plugin_dir_url( __FILE__ ) );
define( 'RADIOEDU_CONECTA_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader resolution.
 * Loads Composer autoloader if present, otherwise provides a PSR-4 fallback.
 */
if ( file_exists( RADIOEDU_CONECTA_DIR . 'vendor/autoload.php' ) ) {
	require_once RADIOEDU_CONECTA_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix   = 'RadioEdu\\Conecta\\';
			$base_dir = RADIOEDU_CONECTA_DIR . 'src/';

			$len = strlen( $prefix );
			if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
				return;
			}

			$relative_class = substr( $class_name, $len );
			$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);
}

/**
 * Checks platform requirements (PHP & WP version).
 *
 * @return bool True if requirements are satisfied, false otherwise.
 */
function radioedu_conecta_check_requirements(): bool {
	global $wp_version;

	if ( version_compare( PHP_VERSION, RADIOEDU_CONECTA_MIN_PHP, '<' ) ) {
		add_action(
			'admin_notices',
			static function (): void {
				radioedu_conecta_requirements_notice(
					sprintf(
						/* translators: 1: Required PHP version, 2: Current PHP version */
						__( 'RadioEdu Conecta requires PHP version %1$s or higher. Your server is running PHP %2$s.', 'radioedu-conecta' ),
						RADIOEDU_CONECTA_MIN_PHP,
						PHP_VERSION
					)
				);
			}
		);
		return false;
	}

	if ( version_compare( $wp_version, RADIOEDU_CONECTA_MIN_WP, '<' ) ) {
		add_action(
			'admin_notices',
			static function (): void {
				global $wp_version;
				radioedu_conecta_requirements_notice(
					sprintf(
						/* translators: 1: Required WordPress version, 2: Current WordPress version */
						__( 'RadioEdu Conecta requires WordPress %1$s or higher. You are using WordPress %2$s.', 'radioedu-conecta' ),
						RADIOEDU_CONECTA_MIN_WP,
						$wp_version
					)
				);
			}
		);
		return false;
	}

	return true;
}

/**
 * Prints an unmet-requirement error notice.
 *
 * This check is precisely what runs on outdated sites, so it falls back to the classic
 * markup on WordPress versions older than 6.4, which lack wp_admin_notice().
 *
 * @param string $message Plain-text message.
 */
function radioedu_conecta_requirements_notice( string $message ): void {
	if ( function_exists( 'wp_admin_notice' ) ) {
		wp_admin_notice( esc_html( $message ), [ 'type' => 'error' ] );
		return;
	}

	printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
}

/**
 * Plugin activation hook handler.
 *
 * @param bool $network_wide Whether the plugin is activated across the whole network.
 */
function radioedu_conecta_activate( bool $network_wide = false ): void {
	if ( ! radioedu_conecta_check_requirements() ) {
		return;
	}

	RadioEdu\Conecta\Plugin::activate( $network_wide );
}
register_activation_hook( __FILE__, 'radioedu_conecta_activate' );

/**
 * Plugin deactivation hook handler.
 *
 * @param bool $network_wide Whether the plugin is deactivated across the whole network.
 */
function radioedu_conecta_deactivate( bool $network_wide = false ): void {
	RadioEdu\Conecta\Plugin::deactivate( $network_wide );
}
register_deactivation_hook( __FILE__, 'radioedu_conecta_deactivate' );

/**
 * Bootstrap the plugin on plugins_loaded.
 */
function radioedu_conecta_bootstrap(): void {
	if ( ! radioedu_conecta_check_requirements() ) {
		return;
	}

	// Initialize the main plugin orchestrator.
	RadioEdu\Conecta\Plugin::instance()->init();
}
add_action( 'plugins_loaded', 'radioedu_conecta_bootstrap' );

/**
 * Add settings link to plugins list table.
 *
 * @param array<string, string> $links Current plugin row action links.
 * @return array<string, string> Updated links array.
 */
function radioedu_conecta_action_links( array $links ): array {
	if ( ! current_user_can( 'manage_radioedu' ) ) {
		return $links;
	}

	$settings_url  = admin_url( 'edit.php?post_type=radioedu_centro&page=radioedu-conecta-settings' );
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( $settings_url ),
		esc_html__( 'Ajustes', 'radioedu-conecta' )
	);

	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . RADIOEDU_CONECTA_BASENAME, 'radioedu_conecta_action_links' );
