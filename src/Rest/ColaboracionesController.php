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
		// 1. Crear propuesta (POST).
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
	 * Permisos para enviar una propuesta de colaboración:
	 * Requiere usuario autenticado con la capacidad 'propose_radioedu_colaboracion'.
	 */
	public function create_item_permissions_check( $request ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		return is_user_logged_in() && current_user_can( 'propose_radioedu_colaboracion' );
	}

	/**
	 * Permisos para consultar las colaboraciones propias.
	 */
	public function get_items_permissions_check( $request ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInExtendedClass
		return is_user_logged_in();
	}

	/**
	 * Permisos para responder a una propuesta:
	 * Requiere que el usuario sea el destinatario o administrador.
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
				"SELECT centro_receptor_id FROM {$table_name} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);

		if ( ! $colab ) {
			return false;
		}

		// Comprobar si el usuario actual puede editar el centro receptor.
		return current_user_can( 'edit_post', (int) $colab->centro_receptor_id );
	}

	/**
	 * Crea un registro de propuesta de colaboración en la base de datos.
	 *
	 * @param WP_REST_Request $request Objeto de la petición.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$user_id = get_current_user_id();

		// Protección Rate Limiting: máximo 10 propuestas por hora por usuario.
		$transient_key = 'radioedu_rate_' . $user_id;
		$rate_count    = (int) get_transient( $transient_key );

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

		// Validar que ambos centros existen y están publicados.
		if ( 'publish' !== get_post_status( $centro_emisor_id ) || 'publish' !== get_post_status( $centro_receptor_id ) ) {
			return new WP_Error(
				'radioedu_invalid_center',
				__( 'Uno de los centros especificados no es válido o no está publicado.', 'radioedu-conecta' ),
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

		// Actualizar contador del rate limiting (1 hora de expiración).
		set_transient( $transient_key, $rate_count + 1, HOUR_IN_SECONDS );

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

		global $wpdb;
		$table_name = ColaboracionesTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
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
