<?php
/**
 * Tests for the centro editor sidebar panels and the classic meta box fallback.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Admin\CentroEditorPanel;
use RadioEdu\Conecta\Admin\CentroMetaBoxes;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use WP_Screen;
use WP_UnitTestCase;

/**
 * Verifies that the block editor gets the panels and the classic editor keeps the meta box.
 */
class CentroEditorPanelTest extends WP_UnitTestCase {

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';
	}

	public function set_up(): void {
		parent::set_up();
		CentroPostType::register();
	}

	public function tear_down(): void {
		wp_dequeue_script( CentroEditorPanel::SCRIPT_HANDLE );
		wp_deregister_script( CentroEditorPanel::SCRIPT_HANDLE );
		wp_dequeue_style( 'radioedu-conecta-admin-css' );
		$GLOBALS['current_screen'] = null;
		unset( $GLOBALS['wp_meta_boxes'], $GLOBALS['post'] );
		parent::tear_down();
	}

	private function use_screen( string $post_type, bool $block_editor ): void {
		$screen = WP_Screen::get( $post_type );
		$screen->is_block_editor( $block_editor );
		$screen->set_current_screen();
	}

	public function test_panels_load_in_the_centro_block_editor(): void {
		$GLOBALS['post'] = get_post( self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE ] ) );
		$this->use_screen( CentroPostType::POST_TYPE, true );

		CentroEditorPanel::enqueue_assets();

		$this->assertTrue( wp_script_is( CentroEditorPanel::SCRIPT_HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'radioedu-conecta-admin-css', 'enqueued' ) );
		$inline = (string) wp_scripts()->get_inline_script_data( CentroEditorPanel::SCRIPT_HANDLE, 'before' );
		$this->assertStringContainsString( 'window.radioeduConectaCentroPanel = ', $inline );
		$this->assertStringContainsString( '"es-ex"', $inline );
	}

	public function test_panels_do_not_load_elsewhere(): void {
		$this->use_screen( 'post', true );
		CentroEditorPanel::enqueue_assets();
		$this->assertFalse( wp_script_is( CentroEditorPanel::SCRIPT_HANDLE, 'enqueued' ) );

		// Classic editor: the meta box is used instead.
		$this->use_screen( CentroPostType::POST_TYPE, false );
		CentroEditorPanel::enqueue_assets();
		$this->assertFalse( wp_script_is( CentroEditorPanel::SCRIPT_HANDLE, 'enqueued' ) );
	}

	public function test_script_data_lists_the_flag_whitelist_and_the_demo_logo(): void {
		$centro = self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE ] );
		update_post_meta( $centro, '_radioedu_logo_url', 'https://example.org/demo-logo.png' );
		$GLOBALS['post'] = get_post( $centro );

		$data = CentroEditorPanel::get_script_data();

		$this->assertSame( CentroPostType::POST_TYPE, $data['postType'] );
		$this->assertSame( '', $data['flags'][0]['value'] );
		$this->assertSame(
			array_keys( CentroMetaBoxes::get_available_flags() ),
			array_column( array_slice( $data['flags'], 1 ), 'value' )
		);
		$this->assertSame( 'https://example.org/demo-logo.png', $data['fallbackLogoUrl'] );
	}

	public function test_meta_box_is_kept_for_the_classic_editor_only(): void {
		CentroMetaBoxes::add_meta_boxes();

		$box = $GLOBALS['wp_meta_boxes'][ CentroPostType::POST_TYPE ]['normal']['high']['radioedu_centro_detalles'] ?? null;
		$this->assertIsArray( $box );
		$this->assertTrue( $box['args']['__back_compat_meta_box'] );
	}
}
