<?php
/**
 * Block markup helpers for content the plugin creates.
 *
 * @package RadioEdu\Conecta\Support
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Builds serialized block markup, so created content opens as blocks in the block editor.
 */
final class BlockMarkup {

	/**
	 * Builds block markup with one Paragraph block per text.
	 *
	 * Plain text would open as a Classic block in the block editor.
	 *
	 * @since 1.1
	 *
	 * @param string ...$texts Plain-text paragraphs.
	 * @return string Serialized core/paragraph blocks.
	 */
	public static function paragraphs( string ...$texts ): string {
		$blocks = array_map(
			static function ( string $text ): string {
				$html = '<p>' . esc_html( $text ) . '</p>';

				return serialize_block(
					[
						'blockName'    => 'core/paragraph',
						'attrs'        => [],
						'innerBlocks'  => [],
						'innerHTML'    => $html,
						'innerContent' => [ $html ],
					]
				);
			},
			$texts
		);

		return implode( "\n\n", $blocks );
	}
}
