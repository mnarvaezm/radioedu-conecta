<?php
/**
 * Controlador REST para el envío y gestión de propuestas de colaboración (Match).
 *
 * @package RadioEdu\Conecta\Rest
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Rest;

use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\Emails\NotificationService;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Endpoints REST para crear, consultar y responder propuestas de match.
 */
class ColaboracionesController extends WP_REST_Controller {

	protected $namespace = 'radioedu-conecta/v1';
	protected $rest_base = 'colaboraciones';

	/**
	 * Registra las rutas de colaboraciones en WordPress.
	 */
	public function register_routes(): void {
		// 1. Crear propuesta (POST) y consultar propuestas propias (GET).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'create_item_permissions_check' ],
					'args'                => $this->get_endpoint_args_for_create(),
				],
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
			]
		);

		// 2. Responder a propuesta (PATCH /colaboraciones/{id}).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			[
				[
					'methods'             => 'PATCH',
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'update_item_permissions_check' ],
					'args'                => [
						'id'        => [
							'validate_callback' => static fn( $param ) => is_numeric( $param ),
						],
						'estado'    => [
							'required'          => true,
							'type'              => 'string',
							'enum'              => [ 'aceptada', 'rechazada' ],
							'sanitize_callback' => 'sanitize_key',
						],
						'respuesta' => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
					],
				],
			]
		);
	}

	/**
	 * Comprueba si el usuario actual es coordinador o tiene permisos de edición sobre un centro.
	 *
	 * @param int $center_id ID del post radioedu_centro.
	 * @param int $user_id   ID del usuario (0 para usuario actual).
	 * @return bool True si tiene autorización sobre el centro.
	 */
	public static function user_can_manage_center( int $center_id, int $user_id = 0 ): bool {
		if ( $center_id <= 0 || CentroPostType::POST_TYPE !== get_post_type( $center_id ) ) {
			return false;
		}

		if ( current_user_can( 'manage_radioedu' ) || current_user_can( 'edit_post', $center_id ) ) {
			return true;
		}

		$uid = $user_id > 0 ? $user_id : get_current_user_id();
		if ( $uid <= 0 ) {
			return false;
		}

		$post_author = (int) get_post_field( 'post_author', $center_id );
		if ( $post_author === $uid ) {
			return true;
		}

		$user = get_userdata( $uid );
		if ( $user && ! empty( $user->user_email ) ) {
			$coord_email = (string) get_post_meta( $center_id, '_radioedu_coordinador_email', true );
			if ( '' !== $coord_email && strtolower( trim( $coord_email ) ) === strtolower( trim( $user->user_email ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Permisos para enviar una propuesta de colaboración:
	 * Requiere usuario autenticado con la capacidad 'propose_radioedu_colaboracion'.
	 *
	 * @param WP_REST_Request $request Objeto de la petición.
	 * @return bool
	 */
	public function create_item_permissions_check( $request ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		return is_user_logged_in() && current_user_can( 'propose_radioedu_colaboracion' );
	}

	/**
	 * Permisos para consultar las colaboraciones propias.
	 *
	 * @param WP_REST_Request $request Objeto de la petición.
	 * @return bool
	 */
	public function get_items_permissions_check( $request ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		return is_user_logged_in() && ( current_user_can( 'propose_radioedu_colaboracion' ) || current_user_can( 'manage_radioedu' ) );
	}

	/**
	 * Permisos para responder a una propuesta:
	 * Requiere que el usuario sea coordinador/editor del centro destinatario o administrador.
	 *
	 * @param WP_REST_Request $request Objeto de la petición.
	 * @return bool
	 */
	public function update_item_permissions_check( $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( current_user_can( 'manage_radioedu' ) ) {
			return true;
		}

		$id = (int) $request->get_param( 'id' );
		global $wpdb;
		$table_name = ColaboracionesTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$colab = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT centro_receptor_id FROM %i WHERE id = %d',
				$table_name,
				$id
			)
		);

		if ( ! $colab ) {
			return false;
		}

		return self::user_can_manage_center( (int) $colab->centro_receptor_id );
	}

	/**
	 * Obtiene la lista de propuestas de colaboración asociadas al usuario actual (o todas si es administrador).
	 *
	 * @param WP_REST_Request $request Objeto de la petición.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		global $wpdb;
		$table_name = ColaboracionesTable::get_table_name();
		$user_id    = get_current_user_id();

		if ( current_user_can( 'manage_radioedu' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i ORDER BY created_at DESC LIMIT 100',
					$table_name
				)
			);
		} else {
			// Obtener IDs de centros gestionados por el usuario.
			$user_center_ids = self::get_user_managed_center_ids( $user_id );

			if ( empty( $user_center_ids ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM %i WHERE usuario_emisor_id = %d ORDER BY created_at DESC LIMIT 100',
						$table_name,
						$user_id
					)
				);
			} else {
				$placeholders = implode( ',', array_fill( 0, count( $user_center_ids ), '%d' ) );
				$query_args   = array_merge( [ $table_name, $user_id ], $user_center_ids, $user_center_ids );

				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM %i WHERE usuario_emisor_id = %d OR centro_emisor_id IN ({$placeholders}) OR centro_receptor_id IN ({$placeholders}) ORDER BY created_at DESC LIMIT 100",
						...$query_args
					)
				);
				// phpcs:enable
			}
		}

		$items = [];
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$items[] = [
					'id'                     => (int) $row->id,
					'centro_emisor_id'       => (int) $row->centro_emisor_id,
					'centro_emisor_nombre'   => get_the_title( (int) $row->centro_emisor_id ),
					'centro_receptor_id'     => (int) $row->centro_receptor_id,
					'centro_receptor_nombre' => get_the_title( (int) $row->centro_receptor_id ),
					'idea_proyecto'          => (string) $row->idea_proyecto,
					'curso_edades'           => (string) $row->curso_edades,
					'idiomas'                => (string) $row->idiomas,
					'fechas_aprox'           => (string) $row->fechas_aprox,
					'estado'                 => (string) $row->estado,
					'respuesta_mensaje'      => (string) ( $row->respuesta_mensaje ?? '' ),
					'created_at'             => (string) $row->created_at,
					'updated_at'             => (string) $row->updated_at,
				];
			}
		}

		return rest_ensure_response( [ 'colaboraciones' => $items ] );
	}

	/**
	 * Devuelve los IDs de los centros que un usuario coordina o puede editar.
	 *
	 * @param int $user_id ID del usuario.
	 * @return array<int, int> Lista de IDs de centros.
	 */
	public static function get_user_managed_center_ids( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return [];
		}

		if ( user_can( $user_id, 'manage_radioedu' ) || user_can( $user_id, 'edit_others_radioedu_centros' ) ) {
			$all_ids = get_posts(
				[
					'post_type'      => CentroPostType::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 100,
					'fields'         => 'ids',
					'orderby'        => 'title',
					'order'          => 'ASC',
				]
			);
			return array_map( 'intval', $all_ids );
		}

		$by_author = get_posts(
			[
				'post_type'      => CentroPostType::POST_TYPE,
				'post_status'    => 'publish',
				'author'         => $user_id,
				'posts_per_page' => 50,
				'fields'         => 'ids',
			]
		);

		$by_email = [];
		$user     = get_userdata( $user_id );
		if ( $user && ! empty( $user->user_email ) ) {
			$by_email = get_posts(
				[
					'post_type'      => CentroPostType::POST_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 50,
					'fields'         => 'ids',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query'     => [
						[
							'key'   => '_radioedu_coordinador_email',
							'value' => $user->user_email,
						],
					],
				]
			);
		}

		return array_values( array_unique( array_map( 'intval', array_merge( $by_author, $by_email ) ) ) );
	}

	/**
	 * Crea un registro de propuesta de colaboración en la base de datos.
	 *
	 * @param WP_REST_Request $request Objeto de la petición.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$user_id = get_current_user_id();

		// Protección Rate Limiting con ventana fija de 1 hora: máximo 10 propuestas por hora por usuario.
		$transient_key = 'radioedu_rate_' . $user_id;
		$rate_data     = get_transient( $transient_key );
		$now_ts        = time();

		if ( is_array( $rate_data ) && isset( $rate_data['count'], $rate_data['expires'] ) && $rate_data['expires'] > $now_ts ) {
			$rate_count = (int) $rate_data['count'];
			$rate_ttl   = max( 1, (int) $rate_data['expires'] - $now_ts );
		} else {
			$rate_count = 0;
			$rate_ttl   = HOUR_IN_SECONDS;
		}

		if ( $rate_count >= 10 ) {
			return new WP_Error(
				'radioedu_rate_limit',
				__( 'Has alcanzado el límite de propuestas por hora. Por favor, inténtalo más tarde.', 'radioedu-conecta' ),
				[ 'status' => 429 ]
			);
		}

		$centro_emisor_id   = absint( $request->get_param( 'centro_emisor_id' ) );
		$centro_receptor_id = absint( $request->get_param( 'centro_receptor_id' ) );
		$idea_proyecto      = sanitize_textarea_field( (string) $request->get_param( 'idea_proyecto' ) );
		$curso_edades       = sanitize_text_field( (string) $request->get_param( 'curso_edades' ) );
		$idiomas            = sanitize_text_field( (string) $request->get_param( 'idiomas' ) );
		$fechas_aprox       = sanitize_text_field( (string) $request->get_param( 'fechas_aprox' ) );

		if ( $centro_emisor_id === $centro_receptor_id ) {
			return new WP_Error(
				'radioedu_same_center',
				__( 'No puedes enviar una propuesta de colaboración a tu propio centro escolar.', 'radioedu-conecta' ),
				[ 'status' => 400 ]
			);
		}

		if ( '' === trim( $idea_proyecto ) ) {
			return new WP_Error(
				'radioedu_missing_idea',
				__( 'Por favor, describe brevemente la idea del proyecto conjunto.', 'radioedu-conecta' ),
				[ 'status' => 400 ]
			);
		}

		// Validar que ambos posts son realmente del CPT radioedu_centro y están publicados.
		if (
			CentroPostType::POST_TYPE !== get_post_type( $centro_emisor_id ) ||
			CentroPostType::POST_TYPE !== get_post_type( $centro_receptor_id ) ||
			'publish' !== get_post_status( $centro_emisor_id ) ||
			'publish' !== get_post_status( $centro_receptor_id )
		) {
			return new WP_Error(
				'radioedu_invalid_center',
				__( 'Uno de los centros especificados no es válido o no está publicado.', 'radioedu-conecta' ),
				[ 'status' => 400 ]
			);
		}

		// Mitigación IDOR: verificar que el usuario actual es coordinador/autor del centro emisor.
		if ( ! self::user_can_manage_center( $centro_emisor_id, $user_id ) ) {
			return new WP_Error(
				'radioedu_forbidden_center',
				__( 'No tienes permisos para enviar propuestas en nombre del centro emisor seleccionado.', 'radioedu-conecta' ),
				[ 'status' => 403 ]
			);
		}

		// Comprobar que el centro receptor está disponible para colaborar.
		if ( '0' === get_post_meta( $centro_receptor_id, '_radioedu_disponible_colaborar', true ) ) {
			return new WP_Error(
				'radioedu_receptor_unavailable',
				__( 'Este centro escolar no está disponible actualmente para recibir nuevas propuestas de colaboración.', 'radioedu-conecta' ),
				[ 'status' => 400 ]
			);
		}

		global $wpdb;
		$table_name = ColaboracionesTable::get_table_name();
		$now        = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$table_name,
			[
				'centro_emisor_id'   => $centro_emisor_id,
				'centro_receptor_id' => $centro_receptor_id,
				'usuario_emisor_id'  => $user_id,
				'idea_proyecto'      => $idea_proyecto,
				'curso_edades'       => $curso_edades,
				'idiomas'            => $idiomas,
				'fechas_aprox'       => $fechas_aprox,
				'estado'             => 'pendiente',
				'created_at'         => $now,
				'updated_at'         => $now,
			],
			[ '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		if ( false === $inserted ) {
			return new WP_Error(
				'radioedu_db_error',
				__( 'No se pudo registrar la propuesta en la base de datos.', 'radioedu-conecta' ),
				[ 'status' => 500 ]
			);
		}

		$colaboracion_id = (int) $wpdb->insert_id;

		// Actualizar contador del rate limiting conservando la expiración de la ventana actual.
		set_transient(
			$transient_key,
			[
				'count'   => $rate_count + 1,
				'expires' => $now_ts + $rate_ttl,
			],
			$rate_ttl
		);

		// Disparar email de notificación al centro receptor.
		NotificationService::send_proposal_email( $colaboracion_id );

		return new WP_REST_Response(
			[
				'success' => true,
				'message' => __( '¡Propuesta de match enviada con éxito! La emisora receptora recibirá una notificación para comenzar a coordinar el proyecto.', 'radioedu-conecta' ),
				'id'      => $colaboracion_id,
			],
			201
		);
	}

	/**
	 * Actualiza el estado de una propuesta (aceptada / rechazada).
	 *
	 * @param WP_REST_Request $request Objeto de la petición.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		$id        = (int) $request->get_param( 'id' );
		$estado    = sanitize_key( (string) $request->get_param( 'estado' ) );
		$respuesta = sanitize_textarea_field( (string) $request->get_param( 'respuesta' ) );

		if ( ! in_array( $estado, [ 'aceptada', 'rechazada' ], true ) ) {
			return new WP_Error(
				'radioedu_invalid_status',
				__( 'El estado indicado no es válido.', 'radioedu-conecta' ),
				[ 'status' => 400 ]
			);
		}

		global $wpdb;
		$table_name = ColaboracionesTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$table_name,
			[
				'estado'            => $estado,
				'respuesta_mensaje' => $respuesta,
				'updated_at'        => current_time( 'mysql' ),
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		if ( false === $updated ) {
			return new WP_Error(
				'radioedu_update_failed',
				__( 'No se pudo actualizar la propuesta.', 'radioedu-conecta' ),
				[ 'status' => 500 ]
			);
		}

		// Notificar al emisor del cambio de estado.
		NotificationService::send_status_update_email( $id, $estado );

		return new WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Estado de la colaboración actualizado correctamente.', 'radioedu-conecta' ),
				'estado'  => $estado,
			]
		);
	}

	/**
	 * Argumentos de validación para la creación de una propuesta.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_endpoint_args_for_create(): array {
		return [
			'centro_emisor_id'   => [
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'centro_receptor_id' => [
				'required'          => true,
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'idea_proyecto'      => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'curso_edades'       => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'idiomas'            => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'fechas_aprox'       => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}
}
