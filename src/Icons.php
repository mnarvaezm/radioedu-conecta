<?php
/**
 * SVG icons rendered through the WordPress Icons API (WordPress 7.1+).
 *
 * @package RadioEdu\Conecta
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin icon collection and prints icons from it or from the core collection.
 *
 * Core icons (`core/*`) are reused whenever WordPress ships a suitable one; the plugin only
 * registers the few it lacks. Icons inherit the text color through the `radioedu-svg-icon`
 * class, because wp_get_icon() does not add `fill="currentColor"` to the core icons.
 */
final class Icons {

	/**
	 * Slug of the plugin icon collection.
	 */
	public const COLLECTION = 'radioedu-conecta';

	/**
	 * Registers the plugin icon collection and its icons.
	 *
	 * The SVG files are read lazily by the core registry, only when an icon is rendered.
	 *
	 * @since 1.0.0
	 */
	public static function register(): void {
		wp_register_icon_collection(
			self::COLLECTION,
			[
				'label'       => __( 'RadioEdu Conecta', 'radioedu-conecta' ),
				'description' => __( 'Iconos del directorio de radios escolares Traspasando Fronteras.', 'radioedu-conecta' ),
			]
		);

		foreach ( self::get_icon_labels() as $slug => $label ) {
			wp_register_icon(
				self::COLLECTION . '/' . $slug,
				[
					'label'     => $label,
					'file_path' => RADIOEDU_CONECTA_DIR . 'assets/icons/' . $slug . '.svg',
				]
			);
		}
	}

	/**
	 * Returns the labels of the icons registered by the plugin, keyed by slug.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public static function get_icon_labels(): array {
		return [
			'close'       => __( 'Cerrar', 'radioedu-conecta' ),
			'globe'       => __( 'Globo terráqueo', 'radioedu-conecta' ),
			'graduation'  => __( 'Birrete de graduación', 'radioedu-conecta' ),
			'headphones'  => __( 'Auriculares', 'radioedu-conecta' ),
			'institution' => __( 'Centro educativo', 'radioedu-conecta' ),
			'map'         => __( 'Mapa', 'radioedu-conecta' ),
			'microphone'  => __( 'Micrófono', 'radioedu-conecta' ),
			'send'        => __( 'Enviar', 'radioedu-conecta' ),
		];
	}

	/**
	 * Returns the SVG markup of an icon.
	 *
	 * Every icon gets the classes `radioedu-svg-icon` and `radioedu-svg-icon--{slug}`, which the
	 * front-end script uses to clone icons from the server-rendered <template>.
	 *
	 * @since 1.0.0
	 *
	 * @param string                                                 $name Namespaced icon name, e.g. `core/search`.
	 * @param array{size?: int|null, class?: string, label?: string} $args Arguments for wp_get_icon().
	 * @return string SVG markup, or an empty string when the icon is not registered.
	 */
	public static function get( string $name, array $args = [] ): string {
		$slug          = substr( (string) strrchr( '/' . $name, '/' ), 1 );
		$args['class'] = trim( 'radioedu-svg-icon radioedu-svg-icon--' . $slug . ' ' . ( $args['class'] ?? '' ) );

		return wp_get_icon( $name, $args );
	}

	/**
	 * Prints an icon, escaped with the SVG allowlist.
	 *
	 * @since 1.0.0
	 *
	 * @param string                                                 $name Namespaced icon name, e.g. `core/search`.
	 * @param array{size?: int|null, class?: string, label?: string} $args Arguments for wp_get_icon().
	 */
	public static function render( string $name, array $args = [] ): void {
		echo wp_kses( self::get( $name, $args ), self::get_allowed_html() );
	}

	/**
	 * Returns the kses allowlist for the markup produced by wp_get_icon().
	 *
	 * It mirrors the core icon sanitizer plus the accessibility attributes wp_get_icon() adds.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function get_allowed_html(): array {
		return [
			'svg'     => [
				'class'       => true,
				'xmlns'       => true,
				'width'       => true,
				'height'      => true,
				'viewbox'     => true,
				'role'        => true,
				'aria-hidden' => true,
				'aria-label'  => true,
				'focusable'   => true,
			],
			'path'    => [
				'fill'      => true,
				'fill-rule' => true,
				'd'         => true,
				'transform' => true,
			],
			'polygon' => [
				'fill'      => true,
				'fill-rule' => true,
				'points'    => true,
				'transform' => true,
			],
		];
	}
}
