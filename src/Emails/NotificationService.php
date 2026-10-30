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
	 * Cabeceras comunes de los correos (texto plano).
	 */
	private const HEADERS = [ 'Content-Type: text/plain; charset=UTF-8' ];

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
	 * Devuelve el título de un centro como texto plano (sin entidades HTML) para los correos.
	 *
	 * @param int $centro_id ID del centro.
	 * @return string
	 */
	private static function get_plain_title( int $centro_id ): string {
		return html_entity_decode( wp_strip_all_tags( get_the_title( $centro_id ) ), ENT_QUOTES, 'UTF-8' );
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

		$colab = ColaboracionesTable::get_by_id( $colaboracion_id );
		if ( null === $colab ) {
			return false;
		}

		$receptor_id     = (int) $colab->centro_receptor_id;
		$centro_emisor   = self::get_plain_title( (int) $colab->centro_emisor_id );
		$centro_receptor = self::get_plain_title( $receptor_id );

		$email_dest = self::get_center_contact_email( $receptor_id );
		if ( '' === $email_dest ) {
			return false;
		}

		$panel_url = admin_url( 'edit.php?post_type=radioedu_centro&page=radioedu-conecta-colaboraciones' );

		$subject = sprintf(
			/* translators: %s: Name of the sending school. */
			__( '[RadioEdu Traspasando Fronteras] ¡Nueva propuesta de match de %s!', 'radioedu-conecta' ),
			$centro_emisor
		);

		$lines = [
			__( 'Hola,', 'radioedu-conecta' ),
			'',
			sprintf(
				/* translators: 1: Name of the sending school, 2: Name of the receiving school. */
				__( 'El centro escolar "%1$s" ha seleccionado vuestra emisora ("%2$s") para proponer un proyecto de colaboración en la red Traspasando Fronteras.', 'radioedu-conecta' ),
				$centro_emisor,
				$centro_receptor
			),
			'',
			__( 'Idea del proyecto propuesta:', 'radioedu-conecta' ),
			'"' . (string) $colab->idea_proyecto . '"',
			'',
			sprintf(
				/* translators: %s: Course or ages of the participating students. */
				__( 'Cursos/Edades: %s', 'radioedu-conecta' ),
				'' !== (string) $colab->curso_edades ? (string) $colab->curso_edades : __( 'No especificado', 'radioedu-conecta' )
			),
			sprintf(
				/* translators: %s: Approximate project dates. */
				__( 'Fechas previstas: %s', 'radioedu-conecta' ),
				'' !== (string) $colab->fechas_aprox ? (string) $colab->fechas_aprox : __( 'Por acordar', 'radioedu-conecta' )
			),
			'',
			__( 'Puedes consultar todos los detalles y responder a la propuesta accediendo al panel de RadioEdu:', 'radioedu-conecta' ),
			$panel_url,
			'',
			'--',
			__( 'RadioEdu Conecta - Red de Radios Escolares de Extremadura y del Mundo', 'radioedu-conecta' ),
		];

		return wp_mail( $email_dest, $subject, implode( "\n", $lines ), self::HEADERS );
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

		$colab = ColaboracionesTable::get_by_id( $colaboracion_id );
		if ( null === $colab ) {
			return false;
		}

		$emisor_id       = (int) $colab->centro_emisor_id;
		$receptor_id     = (int) $colab->centro_receptor_id;
		$centro_emisor   = self::get_plain_title( $emisor_id );
		$centro_receptor = self::get_plain_title( $receptor_id );

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
			/* translators: 1: Name of the receiving school, 2: New status (accepted/declined). */
			__( '[RadioEdu Traspasando Fronteras] Tu propuesta a %1$s ha sido %2$s', 'radioedu-conecta' ),
			$centro_receptor,
			$estado_legible
		);

		$respuesta = (string) ( $colab->respuesta_mensaje ?? '' );

		$lines = [
			__( 'Hola,', 'radioedu-conecta' ),
			'',
			sprintf(
				/* translators: 1: Name of the sending school, 2: Name of the receiving school, 3: New status. */
				__( 'Te informamos de que la propuesta de colaboración enviada por "%1$s" al centro "%2$s" ha sido marcada como: %3$s.', 'radioedu-conecta' ),
				$centro_emisor,
				$centro_receptor,
				$estado_legible
			),
			'',
			__( 'Mensaje o indicaciones del centro:', 'radioedu-conecta' ),
			'"' . ( '' !== $respuesta ? $respuesta : __( 'Sin mensaje adicional.', 'radioedu-conecta' ) ) . '"',
		];

		// Los datos de contacto del coordinador receptor sólo se revelan cuando la propuesta es aceptada.
		if ( 'aceptada' === $nuevo_estado ) {
			$coord_receptor_email  = self::get_center_contact_email( $receptor_id );
			$coord_receptor_nombre = (string) get_post_meta( $receptor_id, '_radioedu_coordinador_nombre', true );
			if ( '' !== $coord_receptor_email ) {
				$lines[] = '';
				$lines[] = sprintf(
					/* translators: 1: Coordinator name of the receiving school, 2: Coordinator email of the receiving school. */
					__( 'Contacto de coordinación de la emisora: %1$s <%2$s>', 'radioedu-conecta' ),
					'' !== $coord_receptor_nombre ? $coord_receptor_nombre : $centro_receptor,
					$coord_receptor_email
				);
			}
		}

		$lines[] = '';
		$lines[] = __( 'Puedes acceder a la plataforma para coordinar los siguientes pasos con la emisora.', 'radioedu-conecta' );
		$lines[] = '';
		$lines[] = '--';
		$lines[] = __( 'RadioEdu Conecta - Traspasando Fronteras', 'radioedu-conecta' );

		return wp_mail( $email_dest, $subject, implode( "\n", $lines ), self::HEADERS );
	}
}
