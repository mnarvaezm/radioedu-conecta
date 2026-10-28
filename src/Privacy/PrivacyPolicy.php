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
			'<h2>%s</h2><p>%s</p><p>%s</p>',
			esc_html__( 'RadioEdu Conecta (Traspasando Fronteras)', 'radioedu-conecta' ),
			esc_html__( 'Este sitio utiliza el plugin RadioEdu Conecta para gestionar el directorio de radios escolares. Los datos de contacto del docente coordinador (nombre y correo electrónico profesional) se almacenan de forma interna y nunca se exponen públicamente en el directorio web ni en las respuestas públicas de la API REST.', 'radioedu-conecta' ),
			esc_html__( 'Al activar voluntariamente la vista de mapa interactivo, el navegador del usuario se conecta con los servidores de OpenStreetMap Foundation para descargar la cartografía.', 'radioedu-conecta' )
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
	public static function export_personal_data( string $email_address, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$data_to_export = [];

		// 1. Centros donde el email figura como coordinador.
		$centros = get_posts(
			[
				'post_type'      => CentroPostType::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 100,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
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

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$colabs = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM %i WHERE usuario_emisor_id = %d LIMIT 100',
					$table_name,
					(int) $user->ID
				)
			);

			if ( is_array( $colabs ) ) {
				foreach ( $colabs as $colab ) {
					$data_to_export[] = [
						'group_id'    => 'radioedu_colaboraciones',
						'group_label' => __( 'RadioEdu Conecta - Propuestas de Colaboración', 'radioedu-conecta' ),
						'item_id'     => 'radioedu-colab-' . $colab->id,
						'data'        => [
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
			'done' => true,
		];
	}

	/**
	 * Elimina o anonimiza los datos personales asociados a un correo electrónico.
	 *
	 * @param string $email_address Dirección de correo del usuario.
	 * @param int    $page          Página de lotes.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public static function erase_personal_data( string $email_address, int $page = 1 ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$items_removed = false;

		$centros = get_posts(
			[
				'post_type'      => CentroPostType::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 100,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => [
					[
						'key'   => '_radioedu_coordinador_email',
						'value' => $email_address,
					],
				],
			]
		);

		foreach ( $centros as $centro ) {
			delete_post_meta( $centro->ID, '_radioedu_coordinador_email' );
			delete_post_meta( $centro->ID, '_radioedu_coordinador_nombre' );
			$items_removed = true;
		}

		$user = get_user_by( 'email', $email_address );
		if ( $user ) {
			global $wpdb;
			$table_name = ColaboracionesTable::get_table_name();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$updated = $wpdb->update(
				$table_name,
				[ 'usuario_emisor_id' => 0 ],
				[ 'usuario_emisor_id' => (int) $user->ID ],
				[ '%d' ],
				[ '%d' ]
			);

			if ( $updated > 0 ) {
				$items_removed = true;
			}
		}

		return [
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => [],
			'done'           => true,
		];
	}
}

