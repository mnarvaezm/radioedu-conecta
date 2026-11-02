<?php
/**
 * Tests for the warnings about a proposals table that could not be updated.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Admin\SchemaHealth;
use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use WP_UnitTestCase;

/**
 * The administrator learns from a notice and from Site Health that the table is not up to date.
 */
class SchemaHealthTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		CentroPostType::register();
		ColaboracionesTable::create_table();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		if ( is_multisite() ) {
			grant_super_admin( get_current_user_id() );
		}
	}

	public function tear_down(): void {
		set_current_screen( 'front' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Prints the notice on a screen.
	 *
	 * @param string $screen Screen ID.
	 * @return string Printed notice.
	 */
	private function notice_on( string $screen ): string {
		set_current_screen( $screen );
		ob_start();
		SchemaHealth::render_notice();
		return (string) ob_get_clean();
	}

	public function test_the_notice_appears_on_the_plugin_screens_while_the_update_fails(): void {
		$this->assertSame( '', $this->notice_on( 'edit-' . CentroPostType::POST_TYPE ) );

		update_option( 'radioedu_conecta_db_version', '1.0.2' );

		$notice = $this->notice_on( 'edit-' . CentroPostType::POST_TYPE );
		$this->assertStringContainsString( 'notice-error', $notice );
		$this->assertStringContainsString( 'is-dismissible', $notice );
		$this->assertStringContainsString( 'CREATE y ALTER', $notice );
		// Only on the plugin screens.
		$this->assertSame( '', $this->notice_on( 'dashboard' ) );
	}

	public function test_the_notice_is_only_for_users_who_can_manage_the_site(): void {
		update_option( 'radioedu_conecta_db_version', '1.0.2' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$this->assertSame( '', $this->notice_on( 'edit-' . CentroPostType::POST_TYPE ) );
	}

	public function test_site_health_checks_the_real_table(): void {
		$tests = SchemaHealth::add_site_health_test( [ 'direct' => [] ] );
		$this->assertArrayHasKey( SchemaHealth::TEST, $tests['direct'] );

		$this->assertSame( 'good', SchemaHealth::site_health_test()['status'] );

		// The table cannot be read, as when it was never created.
		$table  = ColaboracionesTable::get_table_name();
		$refuse = static fn( string $query ): string => str_contains( $query, $table ) && preg_match( '/^\s*SHOW\s+(COLUMNS|INDEX)\b/i', $query ) ? '' : $query;
		add_filter( 'query', $refuse );
		try {
			$result = SchemaHealth::site_health_test();
		} finally {
			remove_filter( 'query', $refuse );
		}

		$this->assertSame( 'critical', $result['status'] );
		$this->assertSame( SchemaHealth::TEST, $result['test'] );
		$this->assertStringContainsString( 'CREATE y ALTER', $result['description'] );
	}
}
