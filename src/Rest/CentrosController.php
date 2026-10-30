<?php
/**
 * Controlador REST para el directorio de centros y radios escolares.
 *
 * @package RadioEdu\Conecta\Rest
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Rest;

use RadioEdu\Conecta\Admin\DemoDataImporter;
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
	 *
	 * @var string
	 */
	protected $namespace = 'radioedu-conecta/v1';

	/**
	 * Base de la ruta de centros.
	 *
	 * @var string
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
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$search   = (string) $request->get_param( 'search' );
		$orden    = (string) $request->get_param( 'orden' );
		$afines_a = absint( $request->get_param( 'afines_a' ) );

		$args = [
			'post_type'      => CentroPostType::POST_TYPE,
			'post_status'    => 'publish',
			'paged'          => $page,
			'posts_per_page' => $per_page,
		];

		// Búsqueda por texto libre.
		if ( '' !== trim( $search ) ) {
			$args['s'] = sanitize_text_field( $search );
		}

		// Ordenación base en WP_Query.
		switch ( $orden ) {
			case 'recientes':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
			case 'alfa':
			case 'afines':
			default:
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;
		}

		// Filtros por Taxonomías (tax_query).
		$tax_queries = [];

		$taxonomy_map = [
			'etapa'     => TaxonomiesRegistrar::TAX_ETAPA,
			'idioma'    => TaxonomiesRegistrar::TAX_IDIOMA,
			'tematica'  => TaxonomiesRegistrar::TAX_TEMATICA,
			'formato'   => TaxonomiesRegistrar::TAX_FORMATO,
			'ubicacion' => TaxonomiesRegistrar::TAX_LOCALIZACION,
		];

		foreach ( $taxonomy_map as $param_key => $taxonomy_slug ) {
			$term_slug = sanitize_title( (string) $request->get_param( $param_key ) );
			if ( '' !== $term_slug ) {
				$tax_queries[] = [
					'taxonomy' => $taxonomy_slug,
					'field'    => 'slug',
					'terms'    => $term_slug,
				];
			}
		}

		if ( ! empty( $tax_queries ) ) {
			$tax_queries['relation'] = 'AND';
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Faceted search requires taxonomy filtering; results are paginated.
			$args['tax_query'] = $tax_queries;
		}

		$query = new WP_Query( $args );
		$items = [];

		foreach ( $query->posts as $centro ) {
			if ( $centro instanceof \WP_Post ) {
				$items[] = $this->prepare_centro_data( $centro->ID );
			}
		}

		// Si el orden es 'afines', ordenar priorizando centros disponibles y con mayor coincidencia de etiquetas/taxonomías.
		if ( 'afines' === $orden && count( $items ) > 1 ) {
			$ref_slugs = $this->get_reference_center_term_slugs( $afines_a );
			usort(
				$items,
				static function ( array $a, array $b ) use ( $ref_slugs, $afines_a ): int {
					// Priorizar centros disponibles para colaborar.
					$disp_a = ! empty( $a['disponible'] ) ? 1 : 0;
					$disp_b = ! empty( $b['disponible'] ) ? 1 : 0;
					if ( $disp_a !== $disp_b ) {
						return $disp_b <=> $disp_a;
					}

					// Si hay centro de referencia del usuario, calcular coincidencias de tags y colocar el propio centro al final.
					if ( $afines_a > 0 ) {
						$is_self_a = ( (int) $a['id'] === $afines_a ) ? 1 : 0;
						$is_self_b = ( (int) $b['id'] === $afines_a ) ? 1 : 0;
						if ( $is_self_a !== $is_self_b ) {
							return $is_self_a <=> $is_self_b;
						}
					}

					if ( ! empty( $ref_slugs ) ) {
						$score_a = 0;
						foreach ( $a['tags'] as $tag ) {
							if ( in_array( $tag['slug'], $ref_slugs, true ) ) {
								++$score_a;
							}
						}
						$score_b = 0;
						foreach ( $b['tags'] as $tag ) {
							if ( in_array( $tag['slug'], $ref_slugs, true ) ) {
								++$score_b;
							}
						}
						if ( $score_a !== $score_b ) {
							return $score_b <=> $score_a;
						}
					}

					return strcasecmp( (string) $a['nombre'], (string) $b['nombre'] );
				}
			);
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
	 * Obtiene los slugs de taxonomías del centro de referencia del usuario para calcular afinidad.
	 *
	 * @param int $center_id ID del centro de referencia.
	 * @return array<int, string>
	 */
	private function get_reference_center_term_slugs( int $center_id ): array {
		if ( $center_id <= 0 ) {
			$user_id = get_current_user_id();
			if ( $user_id > 0 ) {
				$managed = ColaboracionesController::get_user_managed_center_ids( $user_id );
				if ( ! empty( $managed ) ) {
					$center_id = $managed[0];
				}
			}
		}

		// Sólo centros publicados: evita revelar términos de borradores o centros privados a través de 'afines_a'.
		if ( $center_id <= 0 || CentroPostType::POST_TYPE !== get_post_type( $center_id ) || 'publish' !== get_post_status( $center_id ) ) {
			return [];
		}

		$taxonomies = [
			TaxonomiesRegistrar::TAX_ETAPA,
			TaxonomiesRegistrar::TAX_IDIOMA,
			TaxonomiesRegistrar::TAX_TEMATICA,
			TaxonomiesRegistrar::TAX_FORMATO,
		];

		$terms = wp_get_object_terms( $center_id, $taxonomies, [ 'fields' => 'slugs' ] );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}

		return array_map( 'strval', $terms );
	}

	/**
	 * Prepara los datos públicos de un centro escolar para la respuesta REST.
	 *
	 * Nunca incluye los metadatos de coordinación (nombre y email del docente).
	 *
	 * @param int $post_id ID del centro.
	 * @return array<string, mixed>
	 */
	private function prepare_centro_data( int $post_id ): array {
		$title     = get_the_title( $post_id );
		$logo_id   = (int) get_post_meta( $post_id, '_radioedu_logo_id', true );
		$logo_url  = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : (string) get_post_meta( $post_id, '_radioedu_logo_url', true );
		$thumb_url = (string) get_the_post_thumbnail_url( $post_id, 'medium_large' );
		if ( '' === $thumb_url ) {
			$thumb_url = (string) get_post_meta( $post_id, '_radioedu_cover_url', true );
		}

		// Fallback automático para centros de ejemplo si no tienen imagen personalizada asignada.
		$post_name = (string) get_post_field( 'post_name', $post_id );
		if ( '' === $thumb_url ) {
			$thumb_url = DemoDataImporter::get_demo_image_url( $post_name, 'header' );
		}
		if ( '' === $logo_url ) {
			$logo_url = DemoDataImporter::get_demo_image_url( $post_name, 'logo' );
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
					'name'  => $term->name,
					'slug'  => $term->slug,
					'type'  => 'formato',
					'color' => 'mint',
				];
			}
		}

		$tematicas = get_the_terms( $post_id, TaxonomiesRegistrar::TAX_TEMATICA );
		if ( ! empty( $tematicas ) && ! is_wp_error( $tematicas ) ) {
			foreach ( $tematicas as $term ) {
				$tags[] = [
					'name'  => $term->name,
					'slug'  => $term->slug,
					'type'  => 'tematica',
					'color' => 'coral',
				];
			}
		}

		$etapas = get_the_terms( $post_id, TaxonomiesRegistrar::TAX_ETAPA );
		if ( ! empty( $etapas ) && ! is_wp_error( $etapas ) ) {
			foreach ( $etapas as $term ) {
				$tags[] = [
					'name'  => $term->name,
					'slug'  => $term->slug,
					'type'  => 'etapa',
					'color' => 'blue',
				];
			}
		}

		// Ubicación textual (extraída de la taxonomía).
		$ubicacion_texto = '';
		$localizaciones  = get_the_terms( $post_id, TaxonomiesRegistrar::TAX_LOCALIZACION );
		if ( ! empty( $localizaciones ) && ! is_wp_error( $localizaciones ) ) {
			$loc_names       = wp_list_pluck( $localizaciones, 'name' );
			$ubicacion_texto = implode( ', ', array_reverse( $loc_names ) );
		}

		return [
			'id'            => $post_id,
			'nombre'        => $title,
			'descripcion'   => get_the_excerpt( $post_id ),
			'foto_cabecera' => esc_url_raw( (string) $thumb_url ),
			'logo_radio'    => esc_url_raw( (string) $logo_url ),
			'bandera'       => $bandera ?: 'es-ex',
			'ubicacion'     => $ubicacion_texto,
			'lat'           => $lat,
			'lng'           => $lng,
			'podcast_url'   => esc_url_raw( $podcast ),
			'web_centro'    => esc_url_raw( $web ),
			'edades'        => $edades,
			'disponible'    => $disponible,
			'tags'          => $tags,
			'enlace_perfil' => esc_url_raw( (string) get_permalink( $post_id ) ),
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
				'maxLength'         => 200,
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
			'afines_a'  => [
				'description'       => __( 'ID del centro de referencia para calcular afinidad.', 'radioedu-conecta' ),
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'etapa'     => [
				'description'       => __( 'Slug de etapa educativa.', 'radioedu-conecta' ),
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_slug_param' ],
				'validate_callback' => 'rest_validate_request_arg',
			],
			'idioma'    => [
				'description'       => __( 'Slug de idioma.', 'radioedu-conecta' ),
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_slug_param' ],
				'validate_callback' => 'rest_validate_request_arg',
			],
			'tematica'  => [
				'description'       => __( 'Slug de temática.', 'radioedu-conecta' ),
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_slug_param' ],
				'validate_callback' => 'rest_validate_request_arg',
			],
			'formato'   => [
				'description'       => __( 'Slug de formato de programa.', 'radioedu-conecta' ),
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_slug_param' ],
				'validate_callback' => 'rest_validate_request_arg',
			],
			'ubicacion' => [
				'description'       => __( 'Slug de ubicación geográfica.', 'radioedu-conecta' ),
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_slug_param' ],
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}

	/**
	 * Sanitiza un parámetro de filtro como slug de término.
	 *
	 * Envoltorio necesario porque la REST API pasa ($value, $request, $param) al callback
	 * y sanitize_title() interpretaría $request como título de reserva.
	 *
	 * @param mixed $value Valor recibido.
	 * @return string Slug sanitizado.
	 */
	public function sanitize_slug_param( $value ): string {
		return is_scalar( $value ) ? sanitize_title( (string) $value ) : '';
	}
}
