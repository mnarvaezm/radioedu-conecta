<?php
/**
 * Coordinators see the proposals of every centro they manage, however many they have.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Capabilities\RoleCapabilities;
use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Rest\ColaboracionesController;
use WP_REST_Request;
use WP_UnitTestCase;

class ManagedCentersTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		RoleCapabilities::add_capabilities();
		ColaboracionesTable::create_table();
	}

	public function tear_down(): void {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function centro( int $author, string $title = 'Radio', string $status = 'publish' ): int {
		return self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => $status, 'post_author' => $author, 'post_title' => $title ] );
	}

	private function proposal_to( int $receptor ): int {
		global $wpdb;
		$wpdb->insert( ColaboracionesTable::get_table_name(), [ 'centro_emisor_id' => 1, 'centro_receptor_id' => $receptor, 'usuario_emisor_id' => 1, 'idea_proyecto' => 'Idea', 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ] );
		return (int) $wpdb->insert_id;
	}

	private function listed_ids(): array {
		return wp_list_pluck( rest_do_request( new WP_REST_Request( 'GET', '/radioedu-conecta/v1/colaboraciones' ) )->get_data()['colaboraciones'], 'id' );
	}

	public function test_a_coordinator_with_many_centros_sees_all_their_proposals(): void {
		$author  = self::factory()->user->create( [ 'role' => 'author' ] );
		$centros = [];
		for ( $i = 1; $i <= 53; ++$i ) {
			$centros[] = $this->centro( $author, sprintf( 'Radio %02d', $i ) );
		}
		$late = [ $this->proposal_to( $centros[51] ), $this->proposal_to( $centros[52] ) ];

		$this->assertSame( $centros, ColaboracionesController::get_user_managed_center_ids( $author ) );
		wp_set_current_user( $author );
		$this->assertEqualsCanonicalizing( $late, $this->listed_ids() );
	}

	public function test_coordinating_by_email_ignores_case_and_status(): void {
		$coord  = self::factory()->user->create( [ 'role' => 'author', 'user_email' => 'coord@example.org' ] );
		$centro = $this->centro( 0, 'Radio', 'draft' );
		update_post_meta( $centro, '_radioedu_coordinador_email', 'Coord@Example.org' );
		$id = $this->proposal_to( $centro );
		$this->proposal_to( $this->centro( 0 ) );

		wp_set_current_user( $coord );
		$this->assertSame( [ $id ], $this->listed_ids() );
	}

	public function test_editors_see_every_proposal(): void {
		$editor = self::factory()->user->create( [ 'role' => 'editor' ] );
		$id     = $this->proposal_to( $this->centro( 0 ) );

		$this->assertTrue( ColaboracionesController::user_manages_all_centers( $editor ) );
		wp_set_current_user( $editor );
		$this->assertContains( $id, $this->listed_ids() );
	}
}
