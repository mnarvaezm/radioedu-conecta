<?php
/**
 * The proposals listing pages through every proposal instead of stopping at 100.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Admin\ColaboracionesAdminPage;
use RadioEdu\Conecta\Capabilities\RoleCapabilities;
use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use WP_REST_Request;
use WP_UnitTestCase;

class ProposalPaginationTest extends WP_UnitTestCase {

	private int $receiver;

	public function set_up(): void {
		parent::set_up();
		RoleCapabilities::add_capabilities();
		ColaboracionesTable::create_table();
		$coord          = self::factory()->user->create( [ 'role' => 'author' ] );
		$this->receiver = self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => 'publish', 'post_author' => $coord ] );
		wp_set_current_user( $coord );
	}

	public function tear_down(): void {
		unset( $_GET['paged'] );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function proposals( int $count ): void {
		global $wpdb;
		// The same timestamp for every row: the order must still be stable across pages.
		$now = current_time( 'mysql' );
		for ( $i = 1; $i <= $count; ++$i ) {
			$wpdb->insert( ColaboracionesTable::get_table_name(), [ 'centro_emisor_id' => 1, 'centro_receptor_id' => $this->receiver, 'usuario_emisor_id' => 1, 'idea_proyecto' => "Idea $i", 'created_at' => $now, 'updated_at' => $now ] );
		}
	}

	public function test_the_rest_listing_pages_through_every_proposal(): void {
		$this->proposals( 102 );

		$first = rest_do_request( new WP_REST_Request( 'GET', '/radioedu-conecta/v1/colaboraciones' ) );
		$this->assertSame( 200, $first->get_status() );
		$this->assertCount( 100, $first->get_data()['colaboraciones'] );
		$this->assertSame( '102', $first->get_headers()['X-WP-Total'] );
		$this->assertSame( '2', $first->get_headers()['X-WP-TotalPages'] );

		$request = new WP_REST_Request( 'GET', '/radioedu-conecta/v1/colaboraciones' );
		$request->set_param( 'page', 2 );
		$second = rest_do_request( $request );
		$this->assertCount( 2, $second->get_data()['colaboraciones'] );

		$ids = array_merge( wp_list_pluck( $first->get_data()['colaboraciones'], 'id' ), wp_list_pluck( $second->get_data()['colaboraciones'], 'id' ) );
		$this->assertCount( 102, array_unique( $ids ) );
	}

	public function test_the_rest_listing_rejects_an_invalid_page_size(): void {
		$request = new WP_REST_Request( 'GET', '/radioedu-conecta/v1/colaboraciones' );
		$request->set_param( 'per_page', 500 );
		$this->assertSame( 400, rest_do_request( $request )->get_status() );
	}

	public function test_the_admin_listing_has_pages(): void {
		$this->proposals( ColaboracionesAdminPage::PER_PAGE + 3 );
		$_GET['paged'] = '2';

		ob_start();
		ColaboracionesAdminPage::render_page();
		$html = (string) ob_get_clean();

		$this->assertSame( 3, preg_match_all( '/Idea \d+/', $html ) );
		$this->assertStringContainsString( 'tablenav-pages', $html );
		$this->assertStringContainsString( 'paged=1', $html );
	}
}
