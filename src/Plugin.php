<?php
/**
 * Orquestador principal del plugin RadioEdu Conecta.
 *
 * @package RadioEdu\Conecta
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta;

use RadioEdu\Conecta\Admin\CentroMetaBoxes;
use RadioEdu\Conecta\Admin\DemoDataImporter;
use RadioEdu\Conecta\Admin\Settings;
use RadioEdu\Conecta\Capabilities\RoleCapabilities;
use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\Frontend\DirectorioShortcode;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Rest\CentrosController;
use RadioEdu\Conecta\Rest\ColaboracionesController;
use RadioEdu\Conecta\Taxonomies\TaxonomiesRegistrar;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton orquestador que coordina todos los módulos del plugin.
 */
final class Plugin {

	/**
	 * Instancia única (Singleton).
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Obtiene la instancia única.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor privado para forzar el patrón Singleton.
	 */
	private function __construct() {}

	/**
	 * Inicializa los hooks principales del ciclo de vida de WordPress.
	 */
	public function init(): void {
		// 1. Cargar traducciones (i18n).
		add_action( 'init', [ $this, 'load_textdomain' ], 1 );

		// 2. Registrar CPT y Taxonomías en init.
		add_action( 'init', [ CentroPostType::class, 'register' ], 5 );
		add_action( 'init', [ TaxonomiesRegistrar::class, 'register_all' ], 5 );

		// 3. Registrar Meta Boxes y Ajustes del panel de administración.
		if ( is_admin() ) {
			CentroMetaBoxes::register();
			Settings::register();
			DemoDataImporter::register();
			add_action( 'admin_init', [ $this, 'maybe_update_db_schema' ] );
		}

		// 4. Registrar endpoints de la REST API.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// 5. Registrar Shortcodes y Assets del Front-end.
		DirectorioShortcode::register();
	}

	/**
	 * Registra las rutas de la REST API de RadioEdu Conecta.
	 */
	public function register_rest_routes(): void {
		$centros_controller = new CentrosController();
		$centros_controller->register_routes();

		$colaboraciones_controller = new ColaboracionesController();
		$colaboraciones_controller->register_routes();
	}

	/**
	 * Carga el dominio de traducción del plugin.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'radioedu-conecta',
			false,
			dirname( RADIOEDU_CONECTA_BASENAME ) . '/languages'
		);
	}

	/**
	 * Ejecuta migraciones idempotentes de la base de datos si la versión ha cambiado.
	 */
	public function maybe_update_db_schema(): void {
		$installed_ver = get_option( 'radioedu_conecta_db_version', '0' );
		if ( version_compare( (string) $installed_ver, ColaboracionesTable::DB_VERSION, '<' ) ) {
			ColaboracionesTable::create_table();
		}
	}

	/**
	 * Lógica ejecutada en la activación del plugin.
	 *
	 * @param bool $network_wide Si la activación es a nivel de toda la red multisite.
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				[
					'fields' => 'ids',
					'number' => 0,
				]
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_single_site();
				restore_current_blog();
			}
		} else {
			self::activate_single_site();
		}
	}

	/**
	 * Activación para un sitio individual.
	 */
	private static function activate_single_site(): void {
		// Crear tabla de colaboraciones con dbDelta.
		ColaboracionesTable::create_table();

		// Asignar capacidades personalizadas a roles.
		RoleCapabilities::add_capabilities();

		// Registrar tipos de contenido y taxonomías antes del flush.
		CentroPostType::register();
		TaxonomiesRegistrar::register_all();

		// Cargar automáticamente las 6 emisoras de ejemplo con sus imágenes.
		DemoDataImporter::import_demo_centers();

		// Regenerar reglas de reescritura.
		flush_rewrite_rules( false );
	}

	/**
	 * Lógica ejecutada en la desactivación del plugin.
	 *
	 * @param bool $network_wide Si la desactivación es a nivel de red multisite.
	 */
	public static function deactivate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				[
					'fields' => 'ids',
					'number' => 0,
				]
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				flush_rewrite_rules( false );
				restore_current_blog();
			}
		} else {
			flush_rewrite_rules( false );
		}
	}
}
