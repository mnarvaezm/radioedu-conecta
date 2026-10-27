<?php
/**
 * Registro del Custom Post Type para los Centros y Radios Escolares.
 *
 * @package RadioEdu\Conecta\PostTypes
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Gestiona el registro y configuración del tipo de contenido 'radioedu_centro'.
 */
class CentroPostType {

	/**
	 * Slug del CPT.
	 */
	public const POST_TYPE = 'radioedu_centro';

	/**
	 * Registra el tipo de contenido en WordPress.
	 */
	public static function register(): void {
		$labels = [
			'name'                  => _x( 'Centros y Radios', 'Post type general name', 'radioedu-conecta' ),
			'singular_name'         => _x( 'Centro de Radio', 'Post type singular name', 'radioedu-conecta' ),
			'menu_name'             => _x( 'RadioEdu Centros', 'Admin Menu text', 'radioedu-conecta' ),
			'name_admin_bar'        => _x( 'Centro de Radio', 'Add New on Toolbar', 'radioedu-conecta' ),
			'add_new'               => __( 'Añadir nuevo centro', 'radioedu-conecta' ),
			'add_new_item'          => __( 'Añadir nuevo centro de radio', 'radioedu-conecta' ),
			'new_item'              => __( 'Nuevo centro', 'radioedu-conecta' ),
			'edit_item'             => __( 'Editar centro de radio', 'radioedu-conecta' ),
			'view_item'             => __( 'Ver ficha de centro', 'radioedu-conecta' ),
			'all_items'             => __( 'Todos los centros', 'radioedu-conecta' ),
			'search_items'          => __( 'Buscar centros de radio', 'radioedu-conecta' ),
			'parent_item_colon'     => __( 'Centro superior:', 'radioedu-conecta' ),
			'not_found'             => __( 'No se encontraron centros.', 'radioedu-conecta' ),
			'not_found_in_trash'    => __( 'No hay centros en la papelera.', 'radioedu-conecta' ),
			'featured_image'        => _x( 'Foto de cabecera / Fachada del centro', 'Overrides the "Featured Image" phrase', 'radioedu-conecta' ),
			'set_featured_image'    => _x( 'Asignar foto de cabecera', 'Overrides the "Set featured image" phrase', 'radioedu-conecta' ),
			'remove_featured_image' => _x( 'Quitar foto de cabecera', 'Overrides the "Remove featured image" phrase', 'radioedu-conecta' ),
			'use_featured_image'    => _x( 'Usar como foto de cabecera', 'Overrides the "Use as featured image" phrase', 'radioedu-conecta' ),
			'archives'              => _x( 'Directorio de radios escolares', 'The post type archive label', 'radioedu-conecta' ),
			'attributes'            => _x( 'Atributos de centro', 'The post type attributes label', 'radioedu-conecta' ),
			'insert_into_item'      => _x( 'Insertar en la ficha de centro', 'Overrides the "Insert into post" phrase', 'radioedu-conecta' ),
			'uploaded_to_this_item' => _x( 'Subido a este centro', 'Overrides the "Uploaded to this post" phrase', 'radioedu-conecta' ),
			'filter_items_list'     => _x( 'Filtrar lista de centros', 'Screen reader text for the filter links', 'radioedu-conecta' ),
			'items_list_navigation' => _x( 'Navegación de lista de centros', 'Screen reader text for the pagination', 'radioedu-conecta' ),
			'items_list'            => _x( 'Lista de centros', 'Screen reader text for the items list', 'radioedu-conecta' ),
		];

		$args = [
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => [
				'slug'       => 'centro-radioedu',
				'with_front' => false,
			],
			'capability_type'    => [ 'radioedu_centro', 'radioedu_centros' ],
			'map_meta_cap'       => true,
			'has_archive'        => 'centros-radioedu',
			'hierarchical'       => false,
			'menu_position'      => 20,
			'menu_icon'          => 'dashicons-format-audio',
			'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt', 'author' ],
			'show_in_rest'       => true,
			'rest_base'          => 'centros',
		];

		register_post_type( self::POST_TYPE, $args );
	}
}
