<?php
/**
 * Registro de las taxonomías facetadas para RadioEdu Conecta.
 *
 * @package RadioEdu\Conecta\Taxonomies
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Taxonomies;

use RadioEdu\Conecta\PostTypes\CentroPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Registra las taxonomías que sustentan los filtros del directorio.
 */
class TaxonomiesRegistrar {

	public const TAX_ETAPA        = 'radioedu_etapa';
	public const TAX_IDIOMA       = 'radioedu_idioma';
	public const TAX_TEMATICA     = 'radioedu_tematica';
	public const TAX_FORMATO      = 'radioedu_formato';
	public const TAX_LOCALIZACION = 'radioedu_localizacion';

	/**
	 * Capacidades comunes para las taxonomías del directorio:
	 * Los coordinadores (author) pueden asignar términos a su centro,
	 * mientras que la gestión del vocabulario requiere 'manage_radioedu'.
	 *
	 * @return array<string, string>
	 */
	private static function get_taxonomy_capabilities(): array {
		return [
			'manage_terms' => 'manage_radioedu',
			'edit_terms'   => 'manage_radioedu',
			'delete_terms' => 'manage_radioedu',
			'assign_terms' => 'edit_radioedu_centros',
		];
	}

	/**
	 * Registra todas las taxonomías en WordPress.
	 */
	public static function register_all(): void {
		self::register_etapa();
		self::register_idioma();
		self::register_tematica();
		self::register_formato();
		self::register_localizacion();
	}

	/**
	 * Taxonomía: Etapa educativa (Infantil, Primaria, ESO, Bachillerato, FP...).
	 */
	private static function register_etapa(): void {
		$labels = [
			'name'              => _x( 'Etapas Educativas', 'taxonomy general name', 'radioedu-conecta' ),
			'singular_name'     => _x( 'Etapa Educativa', 'taxonomy singular name', 'radioedu-conecta' ),
			'search_items'      => __( 'Buscar etapas educativas', 'radioedu-conecta' ),
			'all_items'         => __( 'Todas las etapas', 'radioedu-conecta' ),
			'parent_item'       => __( 'Etapa superior', 'radioedu-conecta' ),
			'parent_item_colon' => __( 'Etapa superior:', 'radioedu-conecta' ),
			'edit_item'         => __( 'Editar etapa', 'radioedu-conecta' ),
			'update_item'       => __( 'Actualizar etapa', 'radioedu-conecta' ),
			'add_new_item'      => __( 'Añadir nueva etapa educativa', 'radioedu-conecta' ),
			'new_item_name'     => __( 'Nombre de nueva etapa', 'radioedu-conecta' ),
			'menu_name'         => __( 'Etapas Educativas', 'radioedu-conecta' ),
		];

		register_taxonomy(
			self::TAX_ETAPA,
			[ CentroPostType::POST_TYPE ],
			[
				'hierarchical'      => true,
				'labels'            => $labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => [ 'slug' => 'etapa-educativa' ],
				'show_in_rest'      => true,
				'rest_base'         => 'etapas',
				'capabilities'      => self::get_taxonomy_capabilities(),
			]
		);
	}

	/**
	 * Taxonomía: Idiomas (Español, Inglés, Portugués, Francés...).
	 */
	private static function register_idioma(): void {
		$labels = [
			'name'          => _x( 'Idiomas', 'taxonomy general name', 'radioedu-conecta' ),
			'singular_name' => _x( 'Idioma', 'taxonomy singular name', 'radioedu-conecta' ),
			'search_items'  => __( 'Buscar idiomas', 'radioedu-conecta' ),
			'all_items'     => __( 'Todos los idiomas', 'radioedu-conecta' ),
			'edit_item'     => __( 'Editar idioma', 'radioedu-conecta' ),
			'update_item'   => __( 'Actualizar idioma', 'radioedu-conecta' ),
			'add_new_item'  => __( 'Añadir nuevo idioma', 'radioedu-conecta' ),
			'new_item_name' => __( 'Nombre del nuevo idioma', 'radioedu-conecta' ),
			'menu_name'     => __( 'Idiomas', 'radioedu-conecta' ),
		];

		register_taxonomy(
			self::TAX_IDIOMA,
			[ CentroPostType::POST_TYPE ],
			[
				'hierarchical'      => false,
				'labels'            => $labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => [ 'slug' => 'idioma-radio' ],
				'show_in_rest'      => true,
				'rest_base'         => 'idiomas',
				'capabilities'      => self::get_taxonomy_capabilities(),
			]
		);
	}

	/**
	 * Taxonomía: Temáticas de interés (Medioambiente, Convivencia, Igualdad, Ciencia/STEAM...).
	 */
	private static function register_tematica(): void {
		$labels = [
			'name'          => _x( 'Temas de Interés', 'taxonomy general name', 'radioedu-conecta' ),
			'singular_name' => _x( 'Tema de Interés', 'taxonomy singular name', 'radioedu-conecta' ),
			'search_items'  => __( 'Buscar temas', 'radioedu-conecta' ),
			'all_items'     => __( 'Todos los temas', 'radioedu-conecta' ),
			'edit_item'     => __( 'Editar tema', 'radioedu-conecta' ),
			'update_item'   => __( 'Actualizar tema', 'radioedu-conecta' ),
			'add_new_item'  => __( 'Añadir nuevo tema', 'radioedu-conecta' ),
			'new_item_name' => __( 'Nombre del nuevo tema', 'radioedu-conecta' ),
			'menu_name'     => __( 'Temas de Interés', 'radioedu-conecta' ),
		];

		register_taxonomy(
			self::TAX_TEMATICA,
			[ CentroPostType::POST_TYPE ],
			[
				'hierarchical'      => false,
				'labels'            => $labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => [ 'slug' => 'tema-radio' ],
				'show_in_rest'      => true,
				'rest_base'         => 'tematicas',
				'capabilities'      => self::get_taxonomy_capabilities(),
			]
		);
	}

	/**
	 * Taxonomía: Formato de programa (Podcast, Entrevista, Magacín, Ficción Sonora...).
	 */
	private static function register_formato(): void {
		$labels = [
			'name'          => _x( 'Formatos de Radio', 'taxonomy general name', 'radioedu-conecta' ),
			'singular_name' => _x( 'Formato de Radio', 'taxonomy singular name', 'radioedu-conecta' ),
			'search_items'  => __( 'Buscar formatos', 'radioedu-conecta' ),
			'all_items'     => __( 'Todos los formatos', 'radioedu-conecta' ),
			'edit_item'     => __( 'Editar formato', 'radioedu-conecta' ),
			'update_item'   => __( 'Actualizar formato', 'radioedu-conecta' ),
			'add_new_item'  => __( 'Añadir nuevo formato', 'radioedu-conecta' ),
			'new_item_name' => __( 'Nombre del nuevo formato', 'radioedu-conecta' ),
			'menu_name'     => __( 'Formatos de Radio', 'radioedu-conecta' ),
		];

		register_taxonomy(
			self::TAX_FORMATO,
			[ CentroPostType::POST_TYPE ],
			[
				'hierarchical'      => false,
				'labels'            => $labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => [ 'slug' => 'formato-radio' ],
				'show_in_rest'      => true,
				'rest_base'         => 'formatos',
				'capabilities'      => self::get_taxonomy_capabilities(),
			]
		);
	}

	/**
	 * Taxonomía: Localización geográfica (País, Región/Comunidad, Provincia).
	 */
	private static function register_localizacion(): void {
		$labels = [
			'name'              => _x( 'Ubicaciones', 'taxonomy general name', 'radioedu-conecta' ),
			'singular_name'     => _x( 'Ubicación', 'taxonomy singular name', 'radioedu-conecta' ),
			'search_items'      => __( 'Buscar ubicaciones', 'radioedu-conecta' ),
			'all_items'         => __( 'Todas las ubicaciones', 'radioedu-conecta' ),
			'parent_item'       => __( 'Ubicación superior', 'radioedu-conecta' ),
			'parent_item_colon' => __( 'Ubicación superior:', 'radioedu-conecta' ),
			'edit_item'         => __( 'Editar ubicación', 'radioedu-conecta' ),
			'update_item'       => __( 'Actualizar ubicación', 'radioedu-conecta' ),
			'add_new_item'      => __( 'Añadir nueva ubicación', 'radioedu-conecta' ),
			'new_item_name'     => __( 'Nombre de la ubicación', 'radioedu-conecta' ),
			'menu_name'         => __( 'Ubicaciones', 'radioedu-conecta' ),
		];

		register_taxonomy(
			self::TAX_LOCALIZACION,
			[ CentroPostType::POST_TYPE ],
			[
				'hierarchical'      => true,
				'labels'            => $labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
				'rewrite'           => [ 'slug' => 'ubicacion' ],
				'show_in_rest'      => true,
				'rest_base'         => 'ubicaciones',
				'capabilities'      => self::get_taxonomy_capabilities(),
			]
		);
	}
}
