<?php
/**
 * Servicio de notificaciones por correo electrónico para RadioEdu Conecta.
 *
 * @package RadioEdu\Conecta\Emails
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Emails;

use RadioEdu\Conecta\Admin\Settings;
use RadioEdu\Conecta\Database\ColaboracionesTable;

defined( 'ABSPATH' ) || exit;

/**
 * Gestiona el envío seguro de emails sobre propuestas de match y cambios de estado.
 */
class NotificationService {

	/**
	 * Envía notificación al coordinador del centro receptor cuando recibe una propuesta de match.
	 *
	 * @param int $colaboracion_id ID del registro de la colaboración.
	 * @return bool True si se envió correctamente o las notificaciones están desactivadas.
	 */
	public static function send_proposal_email( int $colaboracion_id ): bool {
		// Comprobar si las notificaciones están activadas en los ajustes.
		if ( '1' !== Settings::get( 'notify_on_proposal', '1' ) ) {
			return true;
		}

		global $wpdb;
		$table_name = ColaboracionesTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$colab = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$colaboracion_id
			)
		);

		if ( ! $colab ) {
			return false;
		}

		$centro_emisor   = get_the_title( (int) $colab->centro_emisor_id );
		$receptor_id     = (int) $colab->centro_receptor_id;
		$centro_receptor = get_the_title( $receptor_id );

		// Obtener email del coordinador receptor.
		$email_dest = (string) get_post_meta( $receptor_id, '_radioedu_coordinador_email', true );
		if ( ! is_email( $email_dest ) ) {
			// Si no hay email específico en el post meta, obtener el del autor del post.
			$post_author_id = (int) get_post_field( 'post_author', $receptor_id );
			$author_email   = (string) get_the_author_meta( 'user_email', $post_author_id );
			if ( is_email( $author_email ) ) {
				$email_dest = $author_email;
			} else {
				return false;
			}
		}

		$subject = sprintf(
			/* translators: 1: Nombre del centro emisor */
			__( '[RadioEdu Traspasando Fronteras] ¡Nueva propuesta de match de %1$s!', 'radioedu-conecta' ),
			$centro_emisor
		);

		$message = sprintf(
			/* translators: 1: Centro receptor, 2: Centro emisor, 3: Idea de proyecto, 4: Edades, 5: Fechas */
			__( "Hola,\n\nEl centro escolar \"%2$s\" ha seleccionado vuestra emisora (\"%1$s\") para proponer un proyecto de colaboración en la red Traspasando Fronteras.\n\nIdea del proyecto propuesta:\n\"%3$s\"\n\nCursos/Edades: %4$s\nFechas previstas: %5$s\n\nPuedes consultar todos los detalles y responder a la propuesta accediendo al panel de RadioEdu en vuestro portal.\n\n--\nRadioEdu Conecta - Red de Radios Escolares de Extremadura y del Mundo", 'radioedu-conecta' ),
			$centro_receptor,
			$centro_emisor,
			$colab->idea_proyecto,
			$colab->curso_edades ?: __( 'No especificado', 'radioedu-conecta' ),
			$colab->fechas_aprox ?: __( 'Por acordar', 'radioedu-conecta' )
		);

		$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

		return wp_mail( $email_dest, $subject, $message, $headers );
	}

	/**
	 * Envía notificación al coordinador emisor cuando su propuesta cambia de estado (aceptada/rechazada).
	 *
	 * @param int    $colaboracion_id ID de la colaboración.
	 * @param string $nuevo_estado    Nuevo estado ('aceptada' o 'rechazada').
	 * @return bool True si se envió o está desactivado.
	 */
	public static function send_status_update_email( int $colaboracion_id, string $nuevo_estado ): bool {
		if ( '1' !== Settings::get( 'notify_on_status', '1' ) ) {
			return true;
		}

		global $wpdb;
		$table_name = ColaboracionesTable::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$colab = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$colaboracion_id
			)
		);

		if ( ! $colab ) {
			return false;
		}

		$centro_emisor   = get_the_title( (int) $colab->centro_emisor_id );
		$centro_receptor = get_the_title( (int) $colab->centro_receptor_id );

		// Obtener email del usuario emisor.
		$user_emisor = get_user_by( 'id', (int) $colab->usuario_emisor_id );
		if ( ! $user_emisor || ! is_email( $user_emisor->user_email ) ) {
			return false;
		}

		$estado_legible = 'aceptada' === $nuevo_estado
			? __( 'ACEPTADA', 'radioedu-conecta' )
			: __( 'DECLINADA', 'radioedu-conecta' );

		$subject = sprintf(
			/* translators: 1: Centro receptor, 2: Estado legible */
			__( '[RadioEdu Traspasando Fronteras] Tu propuesta a %1$s ha sido %2$s', 'radioedu-conecta' ),
			$centro_receptor,
			$estado_legible
		);

		$message = sprintf(
			/* translators: 1: Centro emisor, 2: Centro receptor, 3: Estado, 4: Respuesta */
			__( "Hola,\n\nTe informamos de que la propuesta de colaboración enviada por \"%1$s\" al centro \"%2$s\" ha sido marcada como: %3$s.\n\nMensaje o indicaciones del centro:\n\"%4$s\"\n\nPuedes acceder a la plataforma para coordinar los siguientes pasos con la emisora.\n\n--\nRadioEdu Conecta - Traspasando Fronteras", 'radioedu-conecta' ),
			$centro_emisor,
			$centro_receptor,
			$estado_legible,
			$colab->respuesta_mensaje ?: __( 'Sin mensaje adicional.', 'radioedu-conecta' )
		);

		$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

		return wp_mail( $user_emisor->user_email, $subject, $message, $headers );
	}
}
