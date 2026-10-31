<?php
/**
 * Tests for the "Radios por usuario" limit.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Admin\Settings;
use RadioEdu\Conecta\Capabilities\CentroLimit;
use RadioEdu\Conecta\Capabilities\RoleCapabilities;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use WP_REST_Request;
use WP_Screen;
use WP_UnitTestCase;

/**
 * Verifies that coordinators can create centros only up to the configured limit.
 */
class CentroLimitTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		RoleCapabilities::add_capabilities();
		CentroPostType::register();
		CentroLimit::flush_count_cache();
		$GLOBALS['wp_rest_server'] = null;
	}

	public function tear_down(): void {
		wp_set_current_user( 0 );
		$GLOBALS['current_screen'] = null;
		$GLOBALS['wp_rest_server'] = null;
		parent::tear_down();
	}

	private function set_limit( string $limit ): void {
		update_option( Settings::OPTION_KEY, array_merge( Settings::get_default_settings(), [ CentroLimit::SETTING => $limit ] ) );
	}

	private function user( string $role ): int {
		return self::factory()->user->create( [ 'role' => $role ] );
	}

	private function centro( int $author, string $status = 'publish' ): int {
		return self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE, 'post_author' => $author, 'post_status' => $status ] );
	}

	public function test_creating_centros_requires_the_limited_capability(): void {
		$this->assertSame( CentroLimit::CREATE_CAP, get_post_type_object( CentroPostType::POST_TYPE )->cap->create_posts );
		$this->assertSame( '', Settings::get_default_settings()[ CentroLimit::SETTING ] );
	}

	public function test_an_empty_limit_lets_coordinators_create_any_number(): void {
		$this->set_limit( '' );
		$author = $this->user( 'author' );
		foreach ( range( 1, 5 ) as $unused ) {
			$this->centro( $author );
		}

		$this->assertNull( CentroLimit::get_limit() );
		$this->assertTrue( user_can( $author, CentroLimit::CREATE_CAP ) );
		$this->assertFalse( user_can( $this->user( 'subscriber' ), CentroLimit::CREATE_CAP ) );
		$this->assertFalse( user_can( $this->user( 'contributor' ), CentroLimit::CREATE_CAP ) );
	}

	public function test_coordinators_stop_at_the_limit_counting_every_status_but_trash(): void {
		$this->set_limit( '2' );
		$author = $this->user( 'author' );

		$this->centro( $author );
		$this->centro( $author, 'auto-draft' );
		$this->assertTrue( user_can( $author, CentroLimit::CREATE_CAP ), 'Auto-drafts do not count.' );

		$draft = $this->centro( $author, 'draft' );
		$this->assertFalse( user_can( $author, CentroLimit::CREATE_CAP ), 'Drafts count.' );
		$this->assertTrue( CentroLimit::has_reached_limit( $author ) );

		wp_trash_post( $draft );
		$this->assertTrue( user_can( $author, CentroLimit::CREATE_CAP ), 'Trashed centros free a slot.' );
	}

	public function test_only_the_users_own_centros_count(): void {
		$this->set_limit( '1' );
		$author = $this->user( 'author' );
		$this->centro( $this->user( 'author' ) );
		$this->centro( $this->user( 'editor' ) );

		$this->assertTrue( user_can( $author, CentroLimit::CREATE_CAP ) );
	}

	public function test_zero_blocks_coordinators_but_not_editors_or_administrators(): void {
		$this->set_limit( '0' );

		$this->assertSame( 0, CentroLimit::get_limit() );
		$this->assertFalse( user_can( $this->user( 'author' ), CentroLimit::CREATE_CAP ) );
		$this->assertTrue( user_can( $this->user( 'editor' ), CentroLimit::CREATE_CAP ) );
		$this->assertTrue( user_can( $this->user( 'administrator' ), CentroLimit::CREATE_CAP ) );
	}

	public function test_rest_creation_is_rejected_at_the_limit(): void {
		$this->set_limit( '1' );
		$author = $this->user( 'author' );
		$own    = $this->centro( $author );
		wp_set_current_user( $author );

		$request = new WP_REST_Request( 'POST', '/wp/v2/centros' );
		$request->set_body_params( [ 'title' => 'Segunda radio', 'status' => 'draft' ] );
		$this->assertSame( 403, rest_do_request( $request )->get_status() );

		wp_delete_post( $own, true );
		$this->assertSame( 201, rest_do_request( $request )->get_status() );
	}

	public function test_settings_sanitize_the_limit(): void {
		$this->set_limit( '3' );

		foreach ( [ '' => '', '0' => '0', '4' => '4', ' 5 ' => '5', '007' => '7' ] as $input => $expected ) {
			$this->assertSame( $expected, Settings::sanitize_settings( [ CentroLimit::SETTING => (string) $input ] )[ CentroLimit::SETTING ], (string) $input );
		}
		// Invalid values keep the current limit instead of removing it.
		foreach ( [ '-1', 'abc', '2.5', '1e3' ] as $invalid ) {
			$this->assertSame( '3', Settings::sanitize_settings( [ CentroLimit::SETTING => $invalid ] )[ CentroLimit::SETTING ], $invalid );
		}
		$this->assertSame( '', Settings::sanitize_settings( [] )[ CentroLimit::SETTING ] );
	}

	public function test_the_centros_list_explains_the_limit_to_coordinators(): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		$this->set_limit( '1' );
		$author = $this->user( 'author' );
		$this->centro( $author );
		wp_set_current_user( $author );
		WP_Screen::get( 'edit-' . CentroPostType::POST_TYPE )->set_current_screen();

		ob_start();
		CentroLimit::render_limit_notice();
		$notice = (string) ob_get_clean();
		$this->assertStringContainsString( 'Has alcanzado el máximo de 1 radio por usuario', $notice );

		// Not for users without a limit.
		wp_set_current_user( $this->user( 'editor' ) );
		ob_start();
		CentroLimit::render_limit_notice();
		$this->assertSame( '', (string) ob_get_clean() );
	}
}
