<?php
/**
 * Registered post meta of the radioedu_centro post type.
 *
 * @package RadioEdu\Conecta\PostTypes
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\PostTypes;

use RadioEdu\Conecta\Admin\CentroMetaBoxes;
use stdClass;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Declares every `_radioedu_*` meta key with its type and sanitizer.
 *
 * The sanitizers run on every write (editor panel, classic meta box, demo importer, WP-CLI,
 * imports or other plugins). The keys are protected (leading underscore):
 *
 * - The editable keys are shown in the core REST API only in the `edit` context, so the block
 *   editor panel can read and save them, and only users who can edit the centro may change
 *   them. They never appear in public (`view`) responses, and hide_private_meta() removes them
 *   from `edit` responses for centros the user cannot edit.
 * - The keys written only by the demo importer stay locked: never in REST, never editable.
 *
 * Public data is served by the plugin's own /radioedu-conecta/v1/centros endpoint.
 */
class CentroMeta {

	/**
	 * Registers the meta keys.
	 *
	 * @since 1.1
	 */
	public static function register(): void {
		foreach ( self::get_definitions() as $meta_key => $definition ) {
			// The schema only fixes the type: the editor re-sends every key on save, so stored
			// values are cleaned by the sanitizers instead of rejected by enums or formats.
			$show_in_rest = $definition['editable'] ? [
				'schema' => [
					'type'    => $definition['type'],
					'context' => [ 'edit' ],
				],
			] : false;

			$args = [
				'type'              => $definition['type'],
				'description'       => $definition['description'],
				'single'            => true,
				'sanitize_callback' => $definition['sanitize'],
				'auth_callback'     => $definition['editable'] ? [ self::class, 'can_edit_meta' ] : '__return_false',
				'show_in_rest'      => $show_in_rest,
			];
			if ( '_radioedu_disponible_colaborar' === $meta_key ) {
				// Centros are available unless their coordinator opts out.
				$args['default'] = '1';
			}

			register_post_meta( CentroPostType::POST_TYPE, $meta_key, $args );
		}
	}

	/**
	 * Returns the meta key definitions.
	 *
	 * @since 1.1
	 *
	 * @return array<string, array{type: string, description: string, sanitize: callable, editable: bool}>
	 */
	public static function get_definitions(): array {
		return [
			'_radioedu_logo_id'              => [
				'type'        => 'integer',
				'description' => __( 'ID del adjunto de imagen usado como logo de la emisora.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_image_id' ],
				'editable'    => true,
			],
			'_radioedu_logo_url'             => [
				'type'        => 'string',
				'description' => __( 'URL del logo de los centros de demostración.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_http_url' ],
				'editable'    => false,
			],
			'_radioedu_cover_url'            => [
				'type'        => 'string',
				'description' => __( 'URL de la imagen de cabecera de los centros de demostración.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_http_url' ],
				'editable'    => false,
			],
			'_radioedu_bandera'              => [
				'type'        => 'string',
				'description' => __( 'Código de la bandera o región mostrada en el directorio.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_flag' ],
				'editable'    => true,
			],
			'_radioedu_lat'                  => [
				'type'        => 'string',
				'description' => __( 'Latitud decimal del centro en el mapa.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_latitude' ],
				'editable'    => true,
			],
			'_radioedu_lng'                  => [
				'type'        => 'string',
				'description' => __( 'Longitud decimal del centro en el mapa.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_longitude' ],
				'editable'    => true,
			],
			'_radioedu_podcast_url'          => [
				'type'        => 'string',
				'description' => __( 'URL del podcast o de la emisión en directo.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_http_url' ],
				'editable'    => true,
			],
			'_radioedu_web_centro'           => [
				'type'        => 'string',
				'description' => __( 'URL de la web oficial del centro.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_http_url' ],
				'editable'    => true,
			],
			'_radioedu_edades'               => [
				'type'        => 'string',
				'description' => __( 'Cursos o edades del alumnado que participa en la radio.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_text' ],
				'editable'    => true,
			],
			'_radioedu_coordinador_nombre'   => [
				'type'        => 'string',
				'description' => __( 'Nombre del coordinador o coordinadora (dato personal, privado).', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_text' ],
				'editable'    => true,
			],
			'_radioedu_coordinador_email'    => [
				'type'        => 'string',
				'description' => __( 'Email del coordinador o coordinadora (dato personal, privado).', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_email' ],
				'editable'    => true,
			],
			'_radioedu_disponible_colaborar' => [
				'type'        => 'string',
				'description' => __( 'Disponibilidad para recibir propuestas: "1" disponible, "0" no disponible.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_availability' ],
				'editable'    => true,
			],
			'_radioedu_conecta_demo'         => [
				'type'        => 'string',
				'description' => __( 'Marca los contenidos creados por el importador de demostración.', 'radioedu-conecta' ),
				'sanitize'    => [ self::class, 'sanitize_demo_marker' ],
				'editable'    => false,
			],
		];
	}

	/**
	 * Grants meta edits to users who can edit the centro.
	 *
	 * Auth callback of the editable keys, used by the REST API and map_meta_cap(). XML-RPC
	 * stays closed: it would skip the logo check that the REST API and the meta box apply.
	 *
	 * @since 1.1
	 *
	 * @param bool   $allowed  Whether the user can edit the key, before this callback.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Centro ID.
	 * @param int    $user_id  User ID.
	 * @return bool Whether the user can edit the centro.
	 */
	public static function can_edit_meta( bool $allowed, string $meta_key, int $post_id, int $user_id ): bool {
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return false;
		}

		return user_can( $user_id, 'edit_post', $post_id );
	}

	/**
	 * Removes the plugin meta from REST responses of centros the user cannot edit.
	 *
	 * The keys are already limited to the `edit` context, but a coordinator who can edit any
	 * centro may request a whole collection with context=edit; this keeps the other centros'
	 * coordinator data private.
	 *
	 * @since 1.1
	 *
	 * @param WP_REST_Response $response Response of one centro.
	 * @param WP_Post          $post     Centro.
	 * @return WP_REST_Response Response without the plugin meta when the user cannot edit the centro.
	 */
	public static function hide_private_meta( WP_REST_Response $response, WP_Post $post ): WP_REST_Response {
		$data = $response->get_data();
		if ( ! is_array( $data ) || ! isset( $data['meta'] ) || ! is_array( $data['meta'] ) || current_user_can( 'edit_post', $post->ID ) ) {
			return $response;
		}

		$data['meta'] = array_diff_key( $data['meta'], self::get_definitions() );
		$response->set_data( $data );

		return $response;
	}

	/**
	 * Rejects a new REST logo that is not an image the user can see.
	 *
	 * Runs before the centro is saved, so the editor shows the error instead of silently
	 * dropping the logo. The editor re-sends every meta key on save, so an unchanged logo (set
	 * by another user) is not checked again.
	 *
	 * @since 1.1
	 *
	 * @param stdClass|WP_Error $prepared_post Centro prepared for the database.
	 * @param WP_REST_Request   $request       Request.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return stdClass|WP_Error The prepared centro, or an error for an invalid logo.
	 */
	public static function validate_rest_logo( stdClass|WP_Error $prepared_post, WP_REST_Request $request ): stdClass|WP_Error {
		$meta = $request->get_param( 'meta' );
		if ( is_wp_error( $prepared_post ) || ! is_array( $meta ) || ! isset( $meta['_radioedu_logo_id'] ) || ! is_numeric( $meta['_radioedu_logo_id'] ) ) {
			return $prepared_post;
		}

		$logo_id = absint( $meta['_radioedu_logo_id'] );
		$stored  = isset( $prepared_post->ID ) ? (int) get_post_meta( (int) $prepared_post->ID, '_radioedu_logo_id', true ) : 0;
		if ( 0 === $logo_id || $stored === $logo_id || self::is_usable_image( $logo_id ) ) {
			return $prepared_post;
		}

		return new WP_Error(
			'radioedu_conecta_invalid_logo',
			__( 'El logo debe ser una imagen de la biblioteca de medios a la que tengas acceso.', 'radioedu-conecta' ),
			[ 'status' => 400 ]
		);
	}

	/**
	 * Keeps only the ID of an existing image attachment.
	 *
	 * Whether the current user may use that image is checked where a new logo is chosen
	 * (validate_rest_logo() and the classic meta box): the REST API also sanitizes unchanged
	 * values to compare them, which must not drop a logo chosen by another user.
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
	 * An empty value is the registered default, available, as everywhere else in the plugin.
	 *
	 * @since 1.1
	 *
	 * @param mixed $value Raw value.
	 * @return string "1" when available, "0" otherwise.
	 */
	public static function sanitize_availability( mixed $value ): string {
		return is_scalar( $value ) && in_array( (string) $value, [ '1', '' ], true ) ? '1' : '0';
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
	 * Checks that an attachment is an image the current user can read.
	 *
	 * The read_post check (not edit_post) lets authors reuse library images uploaded by others
	 * while rejecting attachments of private posts they cannot see.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool Whether the attachment can be used as a logo.
	 */
	private static function is_usable_image( int $attachment_id ): bool {
		return wp_attachment_is_image( $attachment_id ) && current_user_can( 'read_post', $attachment_id );
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
