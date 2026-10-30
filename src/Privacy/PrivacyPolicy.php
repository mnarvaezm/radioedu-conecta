<?php
/**
 * Integración con las herramientas de Privacidad y RGPD del Core de WordPress.
 *
 * @package RadioEdu\Conecta\Privacy
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Privacy;

use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\PostTypes\CentroPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Registra el texto de política de privacidad, exportadores y borradores de datos personales (RGPD).
 */
class PrivacyPolicy {

	/** Number of records processed by each privacy request. */
	private const BATCH_SIZE = 100;

	/**
	 * Registra los hooks de privacidad de WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_init', [ self::class, 'add_privacy_policy_content' ] );
		add_filter( 'wp_privacy_personal_data_exporters', [ self::class, 'register_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ self::class, 'register_eraser' ] );
	}

	/**
	 * Añade el texto sugerido para la Política de Privacidad del sitio.
	 */
	public static function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<h2>%s</h2><p>%s</p><p>%s</p><p>%s</p><p>%s</p>',
			esc_html__( 'RadioEdu Conecta (Traspasando Fronteras)', 'radioedu-conecta' ),
			esc_html__( 'Este sitio utiliza el plugin RadioEdu Conecta para gestionar el directorio de radios escolares. Los datos de contacto del docente coordinador (nombre y correo electrónico profesional) se almacenan de forma interna y nunca se exponen públicamente en el directorio web ni en las respuestas públicas de la API REST.', 'radioedu-conecta' ),
			esc_html__( 'Las propuestas de colaboración guardan la cuenta de usuario que las envía, los centros implicados y el texto del proyecto. El correo de contacto del centro receptor solo se comparte con el centro emisor cuando la propuesta es aceptada.', 'radioedu-conecta' ),
			esc_html__( 'Al activar voluntariamente la vista de mapa interactivo, el navegador del usuario se conecta con los servidores de OpenStreetMap Foundation para descargar la cartografía.', 'radioedu-conecta' ),
			esc_html__( 'Los centros marcados como favoritos y la aceptación del mapa se guardan únicamente en el almacenamiento local del navegador del visitante y no se envían al servidor.', 'radioedu-conecta' )
		);

		wp_add_privacy_policy_content( 'RadioEdu Conecta', wp_kses_post( $content ) );
	}

	/**
	 * Registra el exportador de datos personales de coordinadores.
	 *
	 * @param array<string, array<string, mixed>> $exporters Exportadores registrados.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_exporter( array $exporters ): array {
		$exporters['radioedu-conecta'] = [
			'exporter_friendly_name' => __( 'RadioEdu Conecta (Coordinadores y Propuestas)', 'radioedu-conecta' ),
			'callback'               => [ self::class, 'export_personal_data' ],
		];
		return $exporters;
	}

	/**
	 * Registra el borrador de datos personales de coordinadores.
	 *
	 * @param array<string, array<string, mixed>> $erasers Borradores registrados.
	 * @return array<string, array<string, mixed>>
	 */
	public static function register_eraser( array $erasers ): array {
		$erasers['radioedu-conecta'] = [
			'eraser_friendly_name' => __( 'RadioEdu Conecta (Coordinadores y Propuestas)', 'radioedu-conecta' ),
			'callback'             => [ self::class, 'erase_personal_data' ],
		];
		return $erasers;
	}

	/**
	 * Exporta los datos personales asociados a un correo electrónico.
	 *
	 * @param string $email_address Dirección de correo del usuario.
	 * @param int    $page          Página de lotes.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public static function export_personal_data( string $email_address, int $page = 1 ): array {
		$data_to_export = [];
		$email_address  = sanitize_email( $email_address );
		if ( ! is_email( $email_address ) ) {
			return [
				'data' => [],
				'done' => true,
			];
		}
		$page         = max( 1, $page );
		$colabs_count = 0;

		// 1. Centros donde el email figura como coordinador.
		$centros = get_posts(
			[
				'post_type'      => CentroPostType::POST_TYPE,
				'post_status'    => array_keys( get_post_stati() ),
				'posts_per_page' => self::BATCH_SIZE,
				'paged'          => $page,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Core privacy requests require a bounded lookup by contact email.
				'meta_query'     => [
					[
						'key'   => '_radioedu_coordinador_email',
						'value' => $email_address,
					],
				],
			]
		);

		foreach ( $centros as $centro ) {
			$coord_nombre     = (string) get_post_meta( $centro->ID, '_radioedu_coordinador_nombre', true );
			$data_to_export[] = [
				'group_id'    => 'radioedu_centros',
				'group_label' => __( 'RadioEdu Conecta - Coordinación de Centros', 'radioedu-conecta' ),
				'item_id'     => 'radioedu-centro-' . $centro->ID,
				'data'        => [
					[
						'name'  => __( 'Centro escolar', 'radioedu-conecta' ),
						'value' => $centro->post_title,
					],
					[
						'name'  => __( 'Nombre del coordinador', 'radioedu-conecta' ),
						'value' => $coord_nombre,
					],
					[
						'name'  => __( 'Email de coordinación', 'radioedu-conecta' ),
						'value' => $email_address,
					],
				],
			];
		}

		// 2. Propuestas de colaboración enviadas por el usuario con ese email.
		$user = get_user_by( 'email', $email_address );
		if ( $user ) {
			global $wpdb;
			$table_name = ColaboracionesTable::get_table_name();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Paginated export from the plugin table; personal data is not cached.
			$colabs = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE usuario_emisor_id = %d ORDER BY id ASC LIMIT %d OFFSET %d',
					$table_name,
					(int) $user->ID,
					self::BATCH_SIZE,
					( $page - 1 ) * self::BATCH_SIZE
				)
			);

			if ( is_array( $colabs ) ) {
				$colabs_count = count( $colabs );
				foreach ( $colabs as $colab ) {
					$data_to_export[] = [
						'group_id'    => 'radioedu_colaboraciones',
						'group_label' => __( 'RadioEdu Conecta - Propuestas de Colaboración', 'radioedu-conecta' ),
						'item_id'     => 'radioedu-colab-' . $colab->id,
						'data'        => [
							[
								'name'  => __( 'Centro emisor', 'radioedu-conecta' ),
								'value' => get_the_title( (int) $colab->centro_emisor_id ),
							],
							[
								'name'  => __( 'Centro receptor', 'radioedu-conecta' ),
								'value' => get_the_title( (int) $colab->centro_receptor_id ),
							],
							[
								'name'  => __( 'Cursos / Edades', 'radioedu-conecta' ),
								'value' => (string) $colab->curso_edades,
							],
							[
								'name'  => __( 'Idiomas', 'radioedu-conecta' ),
								'value' => (string) $colab->idiomas,
							],
							[
								'name'  => __( 'Fechas aproximadas', 'radioedu-conecta' ),
								'value' => (string) $colab->fechas_aprox,
							],
							[
								'name'  => __( 'Respuesta', 'radioedu-conecta' ),
								'value' => (string) $colab->respuesta_mensaje,
							],
							[
								'name'  => __( 'Fecha', 'radioedu-conecta' ),
								'value' => (string) $colab->created_at,
							],
							[
								'name'  => __( 'Idea de proyecto', 'radioedu-conecta' ),
								'value' => (string) $colab->idea_proyecto,
							],
							[
								'name'  => __( 'Estado', 'radioedu-conecta' ),
								'value' => (string) $colab->estado,
							],
						],
					];
				}
			}
		}

		return [
			'data' => $data_to_export,
			'done' => count( $centros ) < self::BATCH_SIZE && $colabs_count < self::BATCH_SIZE,
		];
	}

	/**
	 * Elimina o anonimiza los datos personales asociados a un correo electrónico.
	 *
	 * @param string $email_address Dirección de correo del usuario.
	 * @param int    $page          Página de lotes.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public static function erase_personal_data( string $email_address, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Always process the first remaining batch because erasure changes the query result.
		$items_removed  = false;
		$items_retained = false;
		$messages       = [];
		$email_address  = sanitize_email( $email_address );
		if ( ! is_email( $email_address ) ) {
			return [
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => [],
				'done'           => true,
			];
		}
		$colabs_count    = 0;
		$centros_removed = 0;

		$centros = get_posts(
			[
				'post_type'      => CentroPostType::POST_TYPE,
				'post_status'    => array_keys( get_post_stati() ),
				'posts_per_page' => self::BATCH_SIZE,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Bounded lookup for the core personal-data eraser.
				'meta_query'     => [
					[
						'key'   => '_radioedu_coordinador_email',
						'value' => $email_address,
					],
				],
			]
		);

		foreach ( $centros as $centro ) {
			// Remove the lookup key last, so a failed name deletion remains retriable.
			delete_post_meta( $centro->ID, '_radioedu_coordinador_nombre' );
			if ( ! metadata_exists( 'post', $centro->ID, '_radioedu_coordinador_nombre' ) && delete_post_meta( $centro->ID, '_radioedu_coordinador_email' ) ) {
				$items_removed = true;
				++$centros_removed;
			} else {
				$items_retained = true;
			}
		}

		$user = get_user_by( 'email', $email_address );
		if ( $user ) {
			global $wpdb;
			$table_name = ColaboracionesTable::get_table_name();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded erasure of requester-authored text and account linkage in the custom table.
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE %i SET usuario_emisor_id = 0, idea_proyecto = '', curso_edades = '', idiomas = '', fechas_aprox = '' WHERE usuario_emisor_id = %d ORDER BY id ASC LIMIT %d",
					$table_name,
					(int) $user->ID,
					self::BATCH_SIZE
				)
			);

			$colabs_count = (int) $updated;
			if ( false === $updated ) {
				$items_retained = true;
			}
			if ( $updated > 0 ) {
				$items_removed  = true;
				$messages[]     = __( 'Se han eliminado los textos enviados y la vinculación con la cuenta. Los centros, las fechas, el estado y las respuestas de otros participantes se conservan como historial compartido; revise esas respuestas si contienen datos personales de la persona solicitante.', 'radioedu-conecta' );
				$items_retained = true;
			}
		}

		// Every batch re-reads the first remaining rows, so a batch that removed nothing would be
		// returned again forever: stop and report the retained items instead of looping.
		$has_more_batches = count( $centros ) >= self::BATCH_SIZE || $colabs_count >= self::BATCH_SIZE;
		$made_progress    = $centros_removed > 0 || $colabs_count > 0;

		return [
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $items_retained && empty( $messages ) ? [ __( 'No se han podido eliminar todos los datos. Reintente la solicitud de borrado.', 'radioedu-conecta' ) ] : $messages,
			'done'           => ! $has_more_batches || ! $made_progress,
		];
	}
}
