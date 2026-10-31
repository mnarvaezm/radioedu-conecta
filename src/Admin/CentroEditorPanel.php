<?php
/**
 * Sidebar panels of the centro block editor.
 *
 * @package RadioEdu\Conecta\Admin
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Admin;

use RadioEdu\Conecta\PostTypes\CentroPostType;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the panels that replace the classic meta box in the block editor.
 *
 * The panels edit the registered post meta (see CentroMeta), which the editor saves with the
 * centro through the REST API. The classic meta box is kept only for the classic editor.
 */
final class CentroEditorPanel {

	/**
	 * Script handle of the panels.
	 */
	public const SCRIPT_HANDLE = 'radioedu-conecta-centro-panel';

	/**
	 * Registers the hooks.
	 *
	 * @since 1.1
	 */
	public static function register(): void {
		add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue_assets' ] );
	}

	/**
	 * Enqueues the panels in the block editor of centros only.
	 *
	 * @since 1.1
	 */
	public static function enqueue_assets(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen || CentroPostType::POST_TYPE !== $screen->post_type || ! $screen->is_block_editor() ) {
			return;
		}

		// Only the compiled bundle can run; without a build the panels are skipped, not fatal.
		$asset_file = RADIOEDU_CONECTA_DIR . 'build/centro-panel.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			RADIOEDU_CONECTA_URL . 'build/centro-panel.js',
			$asset['dependencies'],
			$asset['version'],
			[ 'in_footer' => true ]
		);
		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.radioeduConectaCentroPanel = ' . wp_json_encode( self::get_script_data() ) . ';',
			'before'
		);
		wp_set_script_translations( self::SCRIPT_HANDLE, 'radioedu-conecta', RADIOEDU_CONECTA_DIR . 'languages' );
		wp_enqueue_style(
			'radioedu-conecta-admin-css',
			RADIOEDU_CONECTA_URL . 'assets/css/admin.css',
			[],
			RADIOEDU_CONECTA_VERSION
		);
	}

	/**
	 * Returns the data the panels need from the server.
	 *
	 * @since 1.1
	 *
	 * @return array{postType: string, flags: list<array{value: string, label: string}>, fallbackLogoUrl: string}
	 */
	public static function get_script_data(): array {
		$flags = [
			[
				'value' => '',
				'label' => __( '-- Selecciona región o país --', 'radioedu-conecta' ),
			],
		];
		foreach ( CentroMetaBoxes::get_available_flags() as $code => $label ) {
			$flags[] = [
				'value' => (string) $code,
				'label' => $label,
			];
		}

		$post = get_post();

		return [
			'postType'        => CentroPostType::POST_TYPE,
			'flags'           => $flags,
			// Demo centros show a logo URL instead of a library image; it is not exposed in REST.
			'fallbackLogoUrl' => $post instanceof WP_Post ? (string) get_post_meta( $post->ID, '_radioedu_logo_url', true ) : '',
		];
	}
}
