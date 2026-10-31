<?php
/**
 * Registered post meta of the radioedu_centro post type.
 *
 * @package RadioEdu\Conecta\PostTypes
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\PostTypes;

use RadioEdu\Conecta\Admin\CentroMetaBoxes;

defined( 'ABSPATH' ) || exit;

/**
 * Declares every `_radioedu_*` meta key with its type and sanitizer.
 *
 * The sanitizers run on every write (meta box, demo importer, WP-CLI, imports or other
 * plugins), not only on the plugin's own forms. The keys are protected (leading
 * underscore) and their auth callback always denies, so they can only be changed through
 * the plugin's validated meta box and never through the REST API, XML-RPC or the Custom
 * Fields panel. None is exposed in REST: the public data is served by the plugin's own
 * /radioedu-conecta/v1/centros endpoint and the coordinator's contact data is private.
 */
class CentroMeta {

	/**
	 * Registers the meta keys.
	 *
	 * @since 1.1
	 */
	public static function register(): void {
		foreach ( self::get_definitions() as $meta_key => $definition ) {
			register_post_meta(
				CentroPostType::POST_TYPE,
				$meta_key,
				[
					'type'              => $definition['type'],
					'description'       => $definition['description'],
					'single'            => true,
					'sanitize_callback' => $definition['sanitize'],
					'auth_callback'     => '__return_false',
					'show_in_rest'      => false,
				]
			);
		}
	}

	/**
	 * Returns the meta key definitions.
	 *
	 * @since 1.1
	 *
	 * @return array<string, array{type: string, description: string, sanitize: callable}>
	 */
	public static function get_definitions(): array {
		return [
			'_radioedu_logo_id'              => [
				'type'        => 'integer',
				'description' => __( 'ID del adjunto de imagen usado como logo de la emisora.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_image_id' ],
			],
			'_radioedu_logo_url'             => [
				'type'        => 'string',
				'description' => __( 'URL del logo de los centros de demostración.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_http_url' ],
			],
			'_radioedu_cover_url'            => [
				'type'        => 'string',
				'description' => __( 'URL de la imagen de cabecera de los centros de demostración.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_http_url' ],
			],
			'_radioedu_bandera'              => [
				'type'        => 'string',
				'description' => __( 'Código de la bandera o región mostrada en el directorio.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_flag' ],
			],
			'_radioedu_lat'                  => [
				'type'        => 'string',
				'description' => __( 'Latitud decimal del centro en el mapa.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_latitude' ],
			],
			'_radioedu_lng'                  => [
				'type'        => 'string',
				'description' => __( 'Longitud decimal del centro en el mapa.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_longitude' ],
			],
			'_radioedu_podcast_url'          => [
				'type'        => 'string',
				'description' => __( 'URL del podcast o de la emisión en directo.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_http_url' ],
			],
			'_radioedu_web_centro'           => [
				'type'        => 'string',
				'description' => __( 'URL de la web oficial del centro.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_http_url' ],
			],
			'_radioedu_edades'               => [
				'type'        => 'string',
				'description' => __( 'Cursos o edades del alumnado que participa en la radio.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_text' ],
			],
			'_radioedu_coordinador_nombre'   => [
				'type'        => 'string',
				'description' => __( 'Nombre del coordinador o coordinadora (dato personal, privado).', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_text' ],
			],
			'_radioedu_coordinador_email'    => [
				'type'        => 'string',
				'description' => __( 'Email del coordinador o coordinadora (dato personal, privado).', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_email' ],
			],
			'_radioedu_disponible_colaborar' => [
				'type'        => 'string',
				'description' => __( 'Disponibilidad para recibir propuestas: "1" disponible, "0" no disponible.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_availability' ],
			],
			'_radioedu_conecta_demo'         => [
				'type'        => 'string',
				'description' => __( 'Marca los contenidos creados por el importador de demostración.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_demo_marker' ],
			],
		];
	}

	/**
	 * Keeps only the ID of an existing image attachment.
	 *
	 * Whether the current user may use that image is checked by the meta box, which knows who
	 * is saving; this callback only guarantees that the stored ID points to an image.
	 *
	 * @since 1.1
	 *
	 * @param mixed $value Raw value.
	 * @return int Attachment ID, or 0 when it is not an image.
	 */
	public static function sanitize_image_id( mixed $value ): int {
		$attachment_id = is_scalar( $value ) ? absint( $value ) : 0;

		return $attachment_id > 0 && wp_attachment_is_image( $attachment_id ) ? $attachment_id : 0;
	}

	/**
	 * Accepts only http(s) URLs.
	 *
	 * @since 1.1
	 *
	 * @param mixed $value Raw value.
	 * @return string Sanitized URL or empty string.
	 */
	public static function sanitize_http_url( mixed $value ): string {
		return is_string( $value ) ? esc_url_raw( $value, [ 'http', 'https' ] ) : '';
	}

	/**
	 * Accepts only a flag code from the whitelist.
	 *
	 * @since 1.1
	 *
	 * @param mixed $value Raw value.
	 * @return string Flag code or empty string.
	 */
	public static function sanitize_flag( mixed $value ): string {
		$flag = is_string( $value ) ? sanitize_key( $value ) : '';

		return array_key_exists( $flag, CentroMetaBoxes::get_available_flags() ) ? $flag : '';
	}

	/**
	 * Normalizes a latitude to the [-90, 90] range.
	 *
	 * @since 1.1
	 *
	 * @param mixed $value Raw value.
	 * @return string Decimal latitude or empty string.
	 */
	public static function sanitize_latitude( mixed $value ): string {
		return self::sanitize_coordinate( $value, 90.0 );
	}

	/**
	 * Normalizes a longitude to the [-180, 180] range.
	 *
	 * @since 1.1
	 *
	 * @param mixed $value Raw value.
	 * @return string Decimal longitude or empty string.
	 */
	public static function sanitize_longitude( mixed $value ): string {
		return self::sanitize_coordinate( $value, 180.0 );
	}

	/**
	 * Sanitizes a single line of plain text.
	 *
	 * @since 1.1
	 *
	 * @param mixed $value Raw value.
	 * @return string Plain text.
	 */
	public static function sanitize_text( mixed $value ): string {
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	/**
	 * Sanitizes an email address.
	 *
	 * @since 1.1
	 *
	 * @param mixed $value Raw value.
	 * @return string Email address or empty string.
	 */
	public static function sanitize_email( mixed $value ): string {
		return is_string( $value ) ? sanitize_email( $value ) : '';
	}

	/**
	 * Stores availability as "1" or "0".
	 *
	 * @since 1.1
	 *
	 * @param mixed $value Raw value.
	 * @return string "1" when available, "0" otherwise.
	 */
	public static function sanitize_availability( mixed $value ): string {
		return is_scalar( $value ) && '1' === (string) $value ? '1' : '0';
	}

	/**
	 * Stores the demo marker as "1" or an empty string.
	 *
	 * @since 1.1
	 *
	 * @param mixed $value Raw value.
	 * @return string "1" or empty string.
	 */
	public static function sanitize_demo_marker( mixed $value ): string {
		return is_scalar( $value ) && '1' === (string) $value ? '1' : '';
	}

	/**
	 * Clamps a decimal coordinate to [-$limit, $limit].
	 *
	 * @param mixed $value Raw value.
	 * @param float $limit Absolute limit of the coordinate.
	 * @return string Decimal coordinate or empty string when the value is not numeric.
	 */
	private static function sanitize_coordinate( mixed $value, float $limit ): string {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value || ! is_numeric( $value ) ) {
			return '';
		}

		return (string) max( -$limit, min( $limit, (float) $value ) );
	}
}
