<?php
/**
 * Orquestador principal del plugin RadioEdu Conecta.
 *
 * @package RadioEdu\Conecta
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta;

use RadioEdu\Conecta\Admin\CentroEditorPanel;
use RadioEdu\Conecta\Admin\CentroMetaBoxes;
use RadioEdu\Conecta\Admin\ColaboracionesAdminPage;
use RadioEdu\Conecta\Admin\DemoDataImporter;
use RadioEdu\Conecta\Admin\Settings;
use RadioEdu\Conecta\Capabilities\RoleCapabilities;
use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\Frontend\DirectorioShortcode;
use RadioEdu\Conecta\PostTypes\CentroMeta;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Privacy\PrivacyPolicy;
use RadioEdu\Conecta\Rest\CentrosController;
use RadioEdu\Conecta\Rest\ColaboracionesController;
use RadioEdu\Conecta\Taxonomies\TaxonomiesRegistrar;
use WP_Site;

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
		// Provision every site before frontend or REST use, including large networks.
		add_action( 'init', [ $this, 'maybe_update_db_schema' ], 1 );
		// 1. Traducciones: WordPress las carga bajo demanda (just-in-time) desde los paquetes de idioma.

		// 2. Registrar CPT y Taxonomías en init.
		add_action( 'init', [ CentroPostType::class, 'register' ], 5 );
		add_action( 'init', [ CentroMeta::class, 'register' ], 5 );
		add_filter( 'rest_prepare_' . CentroPostType::POST_TYPE, [ CentroMeta::class, 'hide_private_meta' ], 10, 2 );
		add_filter( 'rest_pre_insert_' . CentroPostType::POST_TYPE, [ CentroMeta::class, 'validate_rest_logo' ], 10, 2 );
		add_action( 'init', [ TaxonomiesRegistrar::class, 'register_all' ], 5 );
		// Core registers its icon collection on init priority 0, before the plugin's collection.
		add_action( 'init', [ Icons::class, 'register' ] );

		// 3. Registrar herramientas de Privacidad / RGPD.
		PrivacyPolicy::register();

		// 4. Registrar Meta Boxes, Gestión de Colaboraciones y Ajustes del panel de administración.
		if ( is_admin() ) {
			CentroEditorPanel::register();
			CentroMetaBoxes::register();
			ColaboracionesAdminPage::register();
			Settings::register();
			DemoDataImporter::register();
		}

		// 5. Registrar endpoints de la REST API.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// 6. Registrar Shortcodes y Assets del Front-end.
		DirectorioShortcode::register();

		// 7. Ciclo de vida Multisite: aprovisionar sitios nuevos y borrar la tabla propia al eliminar un sitio.
		if ( is_multisite() ) {
			add_action( 'wp_initialize_site', [ $this, 'on_initialize_site' ], 20, 1 );
			add_filter( 'wpmu_drop_tables', [ ColaboracionesTable::class, 'add_to_dropped_tables' ] );
		}
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
	 * Ejecuta migraciones idempotentes de la base de datos y capacidades si la versión ha cambiado.
	 */
	public function maybe_update_db_schema(): void {
		$installed_ver = get_option( 'radioedu_conecta_db_version', '0' );
		if ( version_compare( (string) $installed_ver, ColaboracionesTable::DB_VERSION, '<' ) ) {
			RoleCapabilities::add_capabilities();
			ColaboracionesTable::create_table();
		}
	}

	/**
	 * Aprovisiona un nuevo sitio creado en una red Multisite.
	 *
	 * @param WP_Site $new_site Objeto del nuevo sitio.
	 */
	public function on_initialize_site( WP_Site $new_site ): void {
		// Sólo si el plugin está activado en red; si está activo en un único sitio no debe tocar los nuevos.
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( RADIOEDU_CONECTA_BASENAME ) ) {
			return;
		}

		switch_to_blog( (int) $new_site->blog_id );
		try {
			self::activate_single_site();
		} finally {
			restore_current_blog();
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
					'number' => 500,
				]
			);

			// Los sitios que superen el lote se aprovisionan de forma diferida en maybe_update_db_schema().
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				try {
					self::activate_single_site();
				} finally {
					restore_current_blog();
				}
			}
		} else {
			self::activate_single_site();
		}
	}

	/**
	 * Activación para un sitio individual.
	 *
	 * Demo content is imported only by an explicit administrator request.
	 */
	private static function activate_single_site(): void {
		// Crear tabla de colaboraciones con dbDelta.
		ColaboracionesTable::create_table();

		// Asignar capacidades personalizadas a roles.
		RoleCapabilities::add_capabilities();

		// Registrar tipos de contenido y taxonomías antes del flush.
		CentroPostType::register();
		TaxonomiesRegistrar::register_all();

		self::reset_rewrite_rules();
	}

	/**
	 * Regenera las reglas de reescritura del sitio actual.
	 *
	 * Dentro de switch_to_blog() el objeto global $wp_rewrite conserva la estructura de enlaces
	 * del sitio original, por lo que se borra la opción para que cada sitio la regenere en su
	 * siguiente carga en lugar de escribir reglas incorrectas.
	 */
	private static function reset_rewrite_rules(): void {
		if ( is_multisite() && ms_is_switched() ) {
			delete_option( 'rewrite_rules' );
			return;
		}

		flush_rewrite_rules( false );
	}

	/**
	 * Lógica ejecutada en la desactivación del plugin.
	 *
	 * Durante esta petición el CPT sigue registrado, así que un flush mantendría sus reglas:
	 * se borra la opción para que WordPress las regenere ya sin el plugin en la siguiente carga.
	 * Desactivar no elimina datos (eso se hace exclusivamente en uninstall.php).
	 *
	 * @param bool $network_wide Si la desactivación es a nivel de red multisite.
	 */
	public static function deactivate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$offset = 0;
			do {
				$site_ids = get_sites(
					[
						'fields'  => 'ids',
						'number'  => 100,
						'offset'  => $offset,
						'orderby' => 'id',
						'order'   => 'ASC',
					]
				);
				foreach ( $site_ids as $site_id ) {
					switch_to_blog( (int) $site_id );
					try {
						delete_option( 'rewrite_rules' );
					} finally {
						restore_current_blog();
					}
				}
				$offset  += 100;
				$has_more = count( $site_ids ) === 100;
			} while ( $has_more );
		} else {
			delete_option( 'rewrite_rules' );
		}
	}
}
