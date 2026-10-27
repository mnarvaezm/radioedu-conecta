<?php
/**
 * Controlador REST para el directorio de centros y radios escolares.
 *
 * @package RadioEdu\Conecta\Rest
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Rest;

use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Taxonomies\TaxonomiesRegistrar;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Endpoints REST para búsqueda facetada y listado de centros de radio escolar.
 */
class CentrosController extends WP_REST_Controller {

	/**
	 * Namespace de la REST API de RadioEdu Conecta.
	 */
	protected $namespace = 'radioedu-conecta/v1';

	/**
	 * Base de la ruta de centros.
	 */
	protected $rest_base = 'centros';

	/**
	 * Registra las rutas REST en WordPress.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
					'args'                => $this->get_collection_params(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	/**
	 * Comprobación de permisos para lectura del directorio.
	 *
	 * El directorio de emisoras de Traspasando Fronteras es información pública
	 * orientada a que centros escolares de todo el mundo puedan descubrirse mutuamente.
	 * Por tanto, el endpoint de lectura es público, garantizando que nunca se exponen
	 * correos electrónicos ni datos sensibles de menores.
	 *
	 * @param WP_REST_Request $request Objeto de la petición.
	 * @return bool True siempre que sea lectura pública.
	 */
	public function get_items_permissions_check( $request ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		return true;
	}

	/**
	 * Recupera los centros aplicando filtros facetados y paginación.
	 *
	 * @param WP_REST_Request $request Objeto de la petición.
	 * @return WP_REST_Response|WP_Error Respuesta REST con los centros.
	 */
	public function get_items( $request ) {
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );
		$search   = (string) $request->get_param( 'search' );
		$orden    = (string) $request->get_param( 'orden' );

		$args = [
			'post_type'      => CentroPostType::POST_TYPE,
			'post_status'    => 'publish',
			'paged'          => max( 1, $page ),
			'posts_per_page' => min( 100, max( 1, $per_page ) ),
		];

		// Búsqueda por texto libre.
		if ( '' !== trim( $search ) ) {
			$args['s'] = sanitize_text_field( $search );
		}

		// Ordenación.
		switch ( $orden ) {
			case 'alfa':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;
			case 'recientes':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
			case 'afines':
			default:
				$args['orderby'] = 'menu_order title';
				$args['order']   = 'ASC';
				break;
		}

		// Filtros por Taxonomías (tax_query).
		$tax_queries = [];

		$filtro_etapa = $request->get_param( 'etapa' );
		if ( ! empty( $filtro_etapa ) ) {
			$tax_queries[] = [
				'taxonomy' => TaxonomiesRegistrar::TAX_ETAPA,
				'field'    => 'slug',
				'terms'    => sanitize_text_field( (string) $filtro_etapa ),
			];
		}

		$filtro_idioma = $request->get_param( 'idioma' );
		if ( ! empty( $filtro_idioma ) ) {
			$tax_queries[] = [
				'taxonomy' => TaxonomiesRegistrar::TAX_IDIOMA,
				'field'    => 'slug',
				'terms'    => sanitize_text_field( (string) $filtro_idioma ),
			];
		}

		$filtro_tematica = $request->get_param( 'tematica' );
		if ( ! empty( $filtro_tematica ) ) {
			$tax_queries[] = [
				'taxonomy' => TaxonomiesRegistrar::TAX_TEMATICA,
				'field'    => 'slug',
				'terms'    => sanitize_text_field( (string) $filtro_tematica ),
			];
		}

		$filtro_formato = $request->get_param( 'formato' );
		if ( ! empty( $filtro_formato ) ) {
			$tax_queries[] = [
				'taxonomy' => TaxonomiesRegistrar::TAX_FORMATO,
				'field'    => 'slug',
				'terms'    => sanitize_text_field( (string) $filtro_formato ),
			];
		}

		$filtro_ubicacion = $request->get_param( 'ubicacion' );
		if ( ! empty( $filtro_ubicacion ) ) {
			$tax_queries[] = [
				'taxonomy' => TaxonomiesRegistrar::TAX_LOCALIZACION,
				'field'    => 'slug',
				'terms'    => sanitize_text_field( (string) $filtro_ubicacion ),
			];
		}

		if ( ! empty( $tax_queries ) ) {
			$tax_queries['relation'] = 'AND';
			$args['tax_query']       = $tax_queries;
		}

		$query = new WP_Query( $args );
		$items = [];

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id = get_the_ID();
				if ( ! $post_id ) {
					continue;
				}

				$item = $this->prepare_item_for_response( $post_id, $request );
				if ( ! is_wp_error( $item ) ) {
					$items[] = $item;
				}
			}
			wp_reset_postdata();
		}

		$response = rest_ensure_response(
			[
				'centros'     => $items,
				'total'       => (int) $query->found_posts,
				'total_pages' => (int) $query->max_num_pages,
				'page'        => $page,
				'per_page'    => $per_page,
			]
		);

		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );

		return $response;
	}

	/**
	 * Prepara los datos del centro escolar para la respuesta REST.
	 *
	 * @param int             $post_id ID del centro.
	 * @param WP_REST_Request $request Petición REST.
	 * @return array<string, mixed>
	 */
	public function prepare_item_for_response( $post_id, $request ): array {
		$title      = get_the_title( $post_id );
		$logo_id    = (int) get_post_meta( $post_id, '_radioedu_logo_id', true );
		$logo_url   = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : (string) get_post_meta( $post_id, '_radioedu_logo_url', true );
		$thumb_url  = get_the_post_thumbnail_url( $post_id, 'medium_large' ) ?: (string) get_post_meta( $post_id, '_radioedu_cover_url', true );

		// Fallback automático para centros de ejemplo si no tienen imagen personalizada asignada.
		$post_name = (string) get_post_field( 'post_name', $post_id );
		if ( empty( $thumb_url ) ) {
			$demo_covers = [
				'sierra-de-gata' => 'header-sierra-gata.jpg',
				'montjuic'       => 'header-montjuic.jpg',
				'la-mar'         => 'header-la-mar.jpg',
				'san-jose'       => 'header-san-jose.jpg',
				'valle-de-tena'  => 'header-valle-tena.jpg',
				'san-patricio'   => 'header-san-patricio.jpg',
			];
			foreach ( $demo_covers as $slug_part => $img_file ) {
				if ( false !== strpos( $post_name, $slug_part ) && file_exists( RADIOEDU_CONECTA_DIR . 'assets/images/demo/' . $img_file ) ) {
					$thumb_url = RADIOEDU_CONECTA_URL . 'assets/images/demo/' . $img_file;
					break;
				}
			}
		}

		if ( empty( $logo_url ) ) {
			$demo_logos = [
				'sierra-de-gata' => 'logo-sierra-gata.jpg',
				'montjuic'       => 'logo-montjuic.jpg',
				'la-mar'         => 'logo-la-mar.jpg',
				'san-jose'       => 'logo-san-jose.jpg',
				'valle-de-tena'  => 'logo-valle-tena.jpg',
				'san-patricio'   => 'logo-san-patricio.jpg',
			];
			foreach ( $demo_logos as $slug_part => $img_file ) {
				if ( false !== strpos( $post_name, $slug_part ) && file_exists( RADIOEDU_CONECTA_DIR . 'assets/images/demo/' . $img_file ) ) {
					$logo_url = RADIOEDU_CONECTA_URL . 'assets/images/demo/' . $img_file;
					break;
				}
			}
		}
		$bandera    = (string) get_post_meta( $post_id, '_radioedu_bandera', true );
		$lat        = (string) get_post_meta( $post_id, '_radioedu_lat', true );
		$lng        = (string) get_post_meta( $post_id, '_radioedu_lng', true );
		$podcast    = (string) get_post_meta( $post_id, '_radioedu_podcast_url', true );
		$web        = (string) get_post_meta( $post_id, '_radioedu_web_centro', true );
		$edades     = (string) get_post_meta( $post_id, '_radioedu_edades', true );
		$disponible = '0' !== get_post_meta( $post_id, '_radioedu_disponible_colaborar', true );

		// Extraer taxonomías organizadas por etiquetas para la tarjeta.
		$tags = [];

		$formatos = get_the_terms( $post_id, TaxonomiesRegistrar::TAX_FORMATO );
		if ( ! empty( $formatos ) && ! is_wp_error( $formatos ) ) {
			foreach ( $formatos as $term ) {
				$tags[] = [
					'name'     => $term->name,
					'slug'     => $term->slug,
					'type'     => 'formato',
					'color'    => 'mint', // Verde menta suave como en el mockup.
				];
			}
		}

		$tematicas = get_the_terms( $post_id, TaxonomiesRegistrar::TAX_TEMATICA );
		if ( ! empty( $tematicas ) && ! is_wp_error( $tematicas ) ) {
			foreach ( $tematicas as $term ) {
				$tags[] = [
					'name'     => $term->name,
					'slug'     => $term->slug,
					'type'     => 'tematica',
					'color'    => 'coral', // Coral/Rosa suave como en el mockup.
				];
			}
		}

		$etapas = get_the_terms( $post_id, TaxonomiesRegistrar::TAX_ETAPA );
		if ( ! empty( $etapas ) && ! is_wp_error( $etapas ) ) {
			foreach ( $etapas as $term ) {
				$tags[] = [
					'name'     => $term->name,
					'slug'     => $term->slug,
					'type'     => 'etapa',
					'color'    => 'blue', // Azul suave.
				];
			}
		}

		// Ubicación textual (extraída de la taxonomía o post_excerpt).
		$ubicacion_texto = '';
		$localizaciones  = get_the_terms( $post_id, TaxonomiesRegistrar::TAX_LOCALIZACION );
		if ( ! empty( $localizaciones ) && ! is_wp_error( $localizaciones ) ) {
			$loc_names = wp_list_pluck( $localizaciones, 'name' );
			$ubicacion_texto = implode( ', ', array_reverse( $loc_names ) );
		}

		return [
			'id'              => $post_id,
			'nombre'          => $title,
			'descripcion'     => get_the_excerpt( $post_id ),
			'foto_cabecera'   => $thumb_url,
			'logo_radio'      => $logo_url,
			'bandera'         => $bandera ?: 'es-ex',
			'ubicacion'       => $ubicacion_texto,
			'lat'             => $lat,
			'lng'             => $lng,
			'podcast_url'     => $podcast,
			'web_centro'      => $web,
			'edades'          => $edades,
			'disponible'      => $disponible,
			'tags'            => $tags,
			'enlace_perfil'   => get_permalink( $post_id ),
		];
	}

	/**
	 * Parámetros permitidos para la colección.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_collection_params(): array {
		return [
			'page'      => [
				'description'       => __( 'Número de página de resultados.', 'radioedu-conecta' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'per_page'  => [
				'description'       => __( 'Número de centros por página.', 'radioedu-conecta' ),
				'type'              => 'integer',
				'default'           => 24,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'search'    => [
				'description'       => __( 'Término de búsqueda por nombre o palabra clave.', 'radioedu-conecta' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'orden'     => [
				'description'       => __( 'Criterio de ordenación.', 'radioedu-conecta' ),
				'type'              => 'string',
				'enum'              => [ 'afines', 'recientes', 'alfa' ],
				'default'           => 'afines',
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'etapa'     => [
				'description'       => __( 'Slug de etapa educativa.', 'radioedu-conecta' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'idioma'    => [
				'description'       => __( 'Slug de idioma.', 'radioedu-conecta' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'tematica'  => [
				'description'       => __( 'Slug de temática.', 'radioedu-conecta' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'formato'   => [
				'description'       => __( 'Slug de formato de programa.', 'radioedu-conecta' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'ubicacion' => [
				'description'       => __( 'Slug de ubicación geográfica.', 'radioedu-conecta' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}
}
