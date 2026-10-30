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

	/**
	 * Número máximo de propuestas que un usuario puede enviar por hora.
	 */
	private const RATE_LIMIT_PER_HOUR = 10;

	/**
	 * Namespace de la REST API de RadioEdu Conecta.
	 *
	 * @var string
	 */
	protected $namespace = 'radioedu-conecta/v1';

	/**
	 * Base de la ruta de colaboraciones.
	 *
	 * @var string
	 */
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
					'args'                => [],
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
							'description'       => __( 'ID de la propuesta de colaboración.', 'radioedu-conecta' ),
							'type'              => 'integer',
							'minimum'           => 1,
							'required'          => true,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						],
						'estado'    => [
							'description'       => __( 'Nuevo estado de la propuesta.', 'radioedu-conecta' ),
							'type'              => 'string',
							'enum'              => ColaboracionesTable::RESPONSE_STATUSES,
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => 'rest_validate_request_arg',
						],
						'respuesta' => [
							'description'       => __( 'Mensaje de respuesta para el centro emisor.', 'radioedu-conecta' ),
							'type'              => 'string',
							'maxLength'         => ColaboracionesTable::MAX_RESPUESTA_LENGTH,
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
							'validate_callback' => 'rest_validate_request_arg',
						],
					],
				],
			]
		);
	}

	/**
	 * Comprueba si un usuario es coordinador o tiene permisos de edición sobre un centro.
	 *
	 * @param int $center_id ID del post radioedu_centro.
	 * @param int $user_id   ID del usuario (0 para usuario actual).
	 * @return bool True si tiene autorización sobre el centro.
	 */
	public static function user_can_manage_center( int $center_id, int $user_id = 0 ): bool {
		if ( $center_id <= 0 || CentroPostType::POST_TYPE !== get_post_type( $center_id ) ) {
			return false;
		}

		$uid = $user_id > 0 ? $user_id : get_current_user_id();
		if ( $uid <= 0 || ( ! user_can( $uid, 'manage_radioedu' ) && ! user_can( $uid, 'propose_radioedu_colaboracion' ) && ! user_can( $uid, 'edit_radioedu_centros' ) ) ) {
			return false;
		}

		if ( user_can( $uid, 'manage_radioedu' ) || user_can( $uid, 'edit_post', $center_id ) ) {
			return true;
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
		if ( ! is_user_logged_in() || ( ! current_user_can( 'manage_radioedu' ) && ! current_user_can( 'propose_radioedu_colaboracion' ) && ! current_user_can( 'edit_radioedu_centros' ) ) ) {
			return false;
		}

		if ( current_user_can( 'manage_radioedu' ) ) {
			return true;
		}

		$colab = ColaboracionesTable::get_by_id( (int) $request->get_param( 'id' ) );
		if ( null === $colab ) {
			return false;
		}

		return self::user_can_manage_center( (int) $colab->centro_receptor_id );
	}

	/**
	 * Obtiene la lista de propuestas de colaboración asociadas al usuario actual (o todas si es administrador).
	 *
	 * @param WP_REST_Request $request Objeto de la petición.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass -- WordPress requires this argument.
		$user_id = get_current_user_id();
		$rows    = ColaboracionesTable::get_for_user(
			$user_id,
			self::get_user_managed_center_ids( $user_id ),
			current_user_can( 'manage_radioedu' )
		);

		$items = [];
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

		return rest_ensure_response( [ 'colaboraciones' => $items ] );
	}

	/**
	 * Devuelve los IDs de los centros que un usuario coordina o puede editar.
	 *
	 * @param int $user_id ID del usuario.
	 * @return array<int, int> Lista de IDs de centros.
	 */
	public static function get_user_managed_center_ids( int $user_id ): array {
		if ( $user_id <= 0 || ( ! user_can( $user_id, 'manage_radioedu' ) && ! user_can( $user_id, 'propose_radioedu_colaboracion' ) && ! user_can( $user_id, 'edit_radioedu_centros' ) ) ) {
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
					'no_found_rows'  => true,
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
				'no_found_rows'  => true,
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
					'no_found_rows'  => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded lookup (max 50) by coordinator email.
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
	public function create_item( $request ): WP_REST_Response|WP_Error {
		if ( ! $this->create_item_permissions_check( $request ) ) {
			return new WP_Error( 'radioedu_forbidden', __( 'No tienes permisos para realizar esta acción.', 'radioedu-conecta' ), [ 'status' => 403 ] );
		}

		$user_id = get_current_user_id();

		// Protección Rate Limiting con ventana fija de 1 hora por usuario.
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

		if ( $rate_count >= self::RATE_LIMIT_PER_HOUR ) {
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
			'publish' !== get_post_status( $centro_receptor_id ) ||
			'' !== get_post_field( 'post_password', $centro_emisor_id ) ||
			'' !== get_post_field( 'post_password', $centro_receptor_id )
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
		$now = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table write.
		$inserted = $wpdb->insert(
			ColaboracionesTable::get_table_name(),
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
	public function update_item( $request ): WP_REST_Response|WP_Error {
		$id     = absint( $request->get_param( 'id' ) );
		$estado = sanitize_key( (string) $request->get_param( 'estado' ) );
		$result = self::respond_to_proposal(
			$id,
			$estado,
			sanitize_textarea_field( (string) $request->get_param( 'respuesta' ) )
		);

		if ( null !== $result ) {
			return $result;
		}

		return new WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Estado de la colaboración actualizado correctamente.', 'radioedu-conecta' ),
				'estado'  => $estado,
			]
		);
	}

	/**
	 * Acepta o declina una propuesta pendiente y notifica al centro emisor.
	 *
	 * Compartido por la REST API y el panel de administración. La autorización
	 * (capacidad + gestión del centro receptor) debe comprobarse antes de llamar.
	 *
	 * @param int    $id        ID de la colaboración.
	 * @param string $estado    Nuevo estado: 'aceptada' o 'rechazada'.
	 * @param string $respuesta Mensaje de respuesta ya sanitizado.
	 * @return WP_Error|null Null si se resolvió correctamente; WP_Error en caso contrario.
	 */
	public static function respond_to_proposal( int $id, string $estado, string $respuesta ): ?WP_Error {
		if ( ! current_user_can( 'manage_radioedu' ) && ! current_user_can( 'propose_radioedu_colaboracion' ) && ! current_user_can( 'edit_radioedu_centros' ) ) {
			return new WP_Error( 'radioedu_forbidden', __( 'No tienes permisos para realizar esta acción.', 'radioedu-conecta' ), [ 'status' => 403 ] );
		}

		if ( ! in_array( $estado, ColaboracionesTable::RESPONSE_STATUSES, true ) ) {
			return new WP_Error(
				'radioedu_invalid_status',
				__( 'El estado indicado no es válido.', 'radioedu-conecta' ),
				[ 'status' => 400 ]
			);
		}

		$colab = ColaboracionesTable::get_by_id( $id );
		if ( null === $colab ) {
			return new WP_Error(
				'radioedu_not_found',
				__( 'La propuesta de colaboración no existe.', 'radioedu-conecta' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! current_user_can( 'manage_radioedu' ) && ! self::user_can_manage_center( (int) $colab->centro_receptor_id ) ) {
			return new WP_Error( 'radioedu_forbidden_center', __( 'No tienes permisos para responder a las propuestas de este centro.', 'radioedu-conecta' ), [ 'status' => 403 ] );
		}

		$respuesta = sanitize_textarea_field( $respuesta );
		if ( mb_strlen( $respuesta ) > ColaboracionesTable::MAX_RESPUESTA_LENGTH ) {
			return new WP_Error( 'radioedu_invalid_response', __( 'El mensaje de respuesta es demasiado largo.', 'radioedu-conecta' ), [ 'status' => 400 ] );
		}

		if ( 'pendiente' !== $colab->estado || ! ColaboracionesTable::resolve_pending( $id, $estado, $respuesta ) ) {
			return new WP_Error(
				'radioedu_already_resolved',
				__( 'Esta propuesta ya ha sido respondida y no puede modificarse.', 'radioedu-conecta' ),
				[ 'status' => 409 ]
			);
		}

		NotificationService::send_status_update_email( $id, $estado );

		return null;
	}

	/**
	 * Argumentos de validación para la creación de una propuesta.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_endpoint_args_for_create(): array {
		return [
			'centro_emisor_id'   => [
				'description'       => __( 'ID del centro que envía la propuesta.', 'radioedu-conecta' ),
				'required'          => true,
				'type'              => 'integer',
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'centro_receptor_id' => [
				'description'       => __( 'ID del centro que recibe la propuesta.', 'radioedu-conecta' ),
				'required'          => true,
				'type'              => 'integer',
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'idea_proyecto'      => [
				'description'       => __( 'Idea del proyecto conjunto.', 'radioedu-conecta' ),
				'required'          => true,
				'type'              => 'string',
				'minLength'         => 1,
				'maxLength'         => ColaboracionesTable::MAX_IDEA_LENGTH,
				'sanitize_callback' => 'sanitize_textarea_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'curso_edades'       => [
				'description'       => __( 'Curso o edades del alumnado participante.', 'radioedu-conecta' ),
				'type'              => 'string',
				'maxLength'         => ColaboracionesTable::MAX_EDADES_LENGTH,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'idiomas'            => [
				'description'       => __( 'Idiomas de trabajo del proyecto.', 'radioedu-conecta' ),
				'type'              => 'string',
				'maxLength'         => ColaboracionesTable::MAX_IDIOMAS_LENGTH,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
			'fechas_aprox'       => [
				'description'       => __( 'Fechas aproximadas del proyecto.', 'radioedu-conecta' ),
				'type'              => 'string',
				'maxLength'         => ColaboracionesTable::MAX_FECHAS_LENGTH,
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			],
		];
	}
}
