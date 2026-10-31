<?php
/**
 * REST endpoint of the network sync.
 *
 * @package RadioEdu\Conecta\Rest
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Rest;

use RadioEdu\Conecta\Network\NetworkSync;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * `POST /radioedu-conecta/v1/network-sync`: syncs one batch of network sites into fichas.
 *
 * Only on the main site of a multisite network, and only for users who can manage the network.
 * The network admin page calls it in a loop, passing the `next_offset` of each response.
 */
class NetworkSyncController extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'radioedu-conecta/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'network-sync';

	/**
	 * Registers the route.
	 *
	 * @since 1.1
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
					'args'                => [
						'offset'            => [
							'description'       => __( 'Número de sitios ya procesados.', 'radioedu-conecta' ),
							'type'              => 'integer',
							'required'          => false,
							'default'           => 0,
							'minimum'           => 0,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						],
						'grant_author_role' => [
							'description'       => __( 'Si los autores de las fichas reciben el rol Autor en el sitio principal. Si se omite, se usa la última elección.', 'radioedu-conecta' ),
							'type'              => 'boolean',
							'required'          => false,
							'sanitize_callback' => 'rest_sanitize_boolean',
							'validate_callback' => 'rest_validate_request_arg',
						],
					],
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	/**
	 * Allows the sync only to network administrators, on the main site of a network.
	 *
	 * @since 1.1
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error True when allowed.
	 */
	public function create_item_permissions_check( $request ): bool|WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		if ( ! is_multisite() || ! is_main_site() ) {
			return new WP_Error( 'radioedu_conecta_not_main_site', __( 'La sincronización de red solo funciona en el sitio principal de una red multisitio.', 'radioedu-conecta' ), [ 'status' => 404 ] );
		}
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'No tienes permiso para sincronizar las fichas de la red.', 'radioedu-conecta' ), [ 'status' => rest_authorization_required_code() ] );
		}

		return true;
	}

	/**
	 * Syncs one batch of sites.
	 *
	 * The first batch (offset 0) stores the "give the Author role" choice for the next sync.
	 *
	 * @since 1.1
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response Batch result.
	 */
	public function create_item( $request ): WP_REST_Response {
		$offset = (int) $request->get_param( 'offset' );
		$grant  = $request->has_param( 'grant_author_role' ) ? (bool) $request->get_param( 'grant_author_role' ) : NetworkSync::get_grant_author_role();
		if ( 0 === $offset ) {
			NetworkSync::set_grant_author_role( $grant );
		}

		return rest_ensure_response( NetworkSync::sync_batch( $offset, $grant ) );
	}

	/**
	 * Schema of a batch result.
	 *
	 * @since 1.1
	 *
	 * @return array<string, mixed> JSON schema.
	 */
	public function get_item_schema(): array {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$count = [
			'type'     => 'integer',
			'readonly' => true,
		];

		$this->schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'radioedu-network-sync',
			'type'       => 'object',
			'properties' => [
				'processed'     => $count + [ 'description' => __( 'Sitios procesados en este lote.', 'radioedu-conecta' ) ],
				'total'         => $count + [ 'description' => __( 'Sitios de la red que se sincronizan.', 'radioedu-conecta' ) ],
				'next_offset'   => $count + [ 'description' => __( 'Valor de offset del siguiente lote.', 'radioedu-conecta' ) ],
				'done'          => [
					'type'        => 'boolean',
					'readonly'    => true,
					'description' => __( 'Si este es el último lote.', 'radioedu-conecta' ),
				],
				'created'       => $count + [ 'description' => __( 'Fichas creadas.', 'radioedu-conecta' ) ],
				'updated'       => $count + [ 'description' => __( 'Fichas actualizadas.', 'radioedu-conecta' ) ],
				'reused'        => $count + [ 'description' => __( 'Fichas creadas a mano reutilizadas.', 'radioedu-conecta' ) ],
				'skipped'       => $count + [ 'description' => __( 'Fichas omitidas porque están en la papelera.', 'radioedu-conecta' ) ],
				'authors_added' => $count + [ 'description' => __( 'Autores de fichas que han recibido el rol Autor en el sitio principal.', 'radioedu-conecta' ) ],
				'errors'        => [
					'type'     => 'array',
					'readonly' => true,
					'items'    => [
						'type'       => 'object',
						'properties' => [
							'blog_id' => [ 'type' => 'integer' ],
							'message' => [ 'type' => 'string' ],
						],
					],
				],
				'missing'       => [
					'type'        => 'array',
					'readonly'    => true,
					'description' => __( 'Fichas cuyo sitio de origen ya no existe (solo en el último lote).', 'radioedu-conecta' ),
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'id'       => [ 'type' => 'integer' ],
							'title'    => [ 'type' => 'string' ],
							'blog_id'  => [ 'type' => 'integer' ],
							'edit_url' => [
								'type'   => 'string',
								'format' => 'uri',
							],
						],
					],
				],
			],
		];

		return $this->add_additional_fields_schema( $this->schema );
	}
}
