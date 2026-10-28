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
	 * Obtiene el correo electrónico de contacto de un centro escolar (metadato o autor).
	 *
	 * @param int $centro_id ID del post radioedu_centro.
	 * @return string Email válido o cadena vacía.
	 */
	public static function get_center_contact_email( int $centro_id ): string {
		$email = (string) get_post_meta( $centro_id, '_radioedu_coordinador_email', true );
		if ( is_email( $email ) ) {
			return $email;
		}

		$post_author_id = (int) get_post_field( 'post_author', $centro_id );
		if ( $post_author_id > 0 ) {
			$author_email = (string) get_the_author_meta( 'user_email', $post_author_id );
			if ( is_email( $author_email ) ) {
				return $author_email;
			}
		}

		return '';
	}

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
				'SELECT * FROM %i WHERE id = %d',
				$table_name,
				$colaboracion_id
			)
		);

		if ( ! $colab ) {
			return false;
		}

		$centro_emisor   = get_the_title( (int) $colab->centro_emisor_id );
		$receptor_id     = (int) $colab->centro_receptor_id;
		$centro_receptor = get_the_title( $receptor_id );

		$email_dest = self::get_center_contact_email( $receptor_id );
		if ( '' === $email_dest ) {
			return false;
		}

		$panel_url = admin_url( 'edit.php?post_type=radioedu_centro&page=radioedu-conecta-colaboraciones' );

		$subject = sprintf(
			/* translators: 1: Nombre del centro emisor */
			__( '[RadioEdu Traspasando Fronteras] ¡Nueva propuesta de match de %1$s!', 'radioedu-conecta' ),
			$centro_emisor
		);

		$message = sprintf(
			/* translators: 1: Centro receptor, 2: Centro emisor, 3: Idea de proyecto, 4: Edades, 5: Fechas, 6: URL del panel */
			__( "Hola,\n\nEl centro escolar \"%2$s\" ha seleccionado vuestra emisora (\"%1$s\") para proponer un proyecto de colaboración en la red Traspasando Fronteras.\n\nIdea del proyecto propuesta:\n\"%3$s\"\n\nCursos/Edades: %4$s\nFechas previstas: %5$s\n\nPuedes consultar todos los detalles y responder a la propuesta accediendo al panel de RadioEdu:\n%6$s\n\n--\nRadioEdu Conecta - Red de Radios Escolares de Extremadura y del Mundo", 'radioedu-conecta' ),
			$centro_receptor,
			$centro_emisor,
			$colab->idea_proyecto,
			$colab->curso_edades ?: __( 'No especificado', 'radioedu-conecta' ),
			$colab->fechas_aprox ?: __( 'Por acordar', 'radioedu-conecta' ),
			$panel_url
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
				'SELECT * FROM %i WHERE id = %d',
				$table_name,
				$colaboracion_id
			)
		);

		if ( ! $colab ) {
			return false;
		}

		$emisor_id       = (int) $colab->centro_emisor_id;
		$receptor_id     = (int) $colab->centro_receptor_id;
		$centro_emisor   = get_the_title( $emisor_id );
		$centro_receptor = get_the_title( $receptor_id );

		// Obtener email del centro emisor o del usuario que envió la propuesta.
		$email_dest  = self::get_center_contact_email( $emisor_id );
		$user_emisor = get_user_by( 'id', (int) $colab->usuario_emisor_id );
		if ( '' === $email_dest && $user_emisor && is_email( $user_emisor->user_email ) ) {
			$email_dest = $user_emisor->user_email;
		}

		if ( '' === $email_dest ) {
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

		$contacto_info = '';
		if ( 'aceptada' === $nuevo_estado ) {
			$coord_receptor_email  = self::get_center_contact_email( $receptor_id );
			$coord_receptor_nombre = (string) get_post_meta( $receptor_id, '_radioedu_coordinador_nombre', true );
			if ( '' !== $coord_receptor_email ) {
				$contacto_info = "\n" . sprintf(
					/* translators: 1: Nombre del coordinador receptor, 2: Email del coordinador receptor */
					__( 'Contacto de coordinación de la emisora: %1$s <%2$s>', 'radioedu-conecta' ),
					$coord_receptor_nombre ?: $centro_receptor,
					$coord_receptor_email
				) . "\n";
			}
		}

		$message = sprintf(
			/* translators: 1: Centro emisor, 2: Centro receptor, 3: Estado, 4: Respuesta, 5: Datos de contacto si fue aceptada */
			__( "Hola,\n\nTe informamos de que la propuesta de colaboración enviada por \"%1$s\" al centro \"%2$s\" ha sido marcada como: %3$s.\n\nMensaje o indicaciones del centro:\n\"%4$s\"\n%5$s\nPuedes acceder a la plataforma para coordinar los siguientes pasos con la emisora.\n\n--\nRadioEdu Conecta - Traspasando Fronteras", 'radioedu-conecta' ),
			$centro_emisor,
			$centro_receptor,
			$estado_legible,
			$colab->respuesta_mensaje ?: __( 'Sin mensaje adicional.', 'radioedu-conecta' ),
			$contacto_info
		);

		$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];

		return wp_mail( $email_dest, $subject, $message, $headers );
	}
}
