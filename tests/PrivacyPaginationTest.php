<?php
/**
 * Privacy export and erasure must cover every batch, including trashed posts.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Privacy\PrivacyPolicy;
use WP_UnitTestCase;

class PrivacyPaginationTest extends WP_UnitTestCase {

	public function test_export_and_erasure_process_all_batches(): void {
		global $wpdb;
		ColaboracionesTable::create_table();
		$email = 'privacy@example.org';
		$user_id = self::factory()->user->create( [ 'user_email' => $email ] );
		$ids = [];
		for ( $i = 0; $i < 101; ++$i ) {
			$id = self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => 100 === $i ? 'trash' : 'publish' ] );
			$ids[] = $id;
			update_post_meta( $id, '_radioedu_coordinador_email', $email );
			update_post_meta( $id, '_radioedu_coordinador_nombre', 'Private Name' );
			$wpdb->insert( ColaboracionesTable::get_table_name(), [ 'centro_emisor_id' => $id, 'centro_receptor_id' => $id, 'usuario_emisor_id' => $user_id, 'idea_proyecto' => 'Personal project text', 'curso_edades' => 'Personal field', 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ] );
		}
		$first = PrivacyPolicy::export_personal_data( $email, 1 );
		$second = PrivacyPolicy::export_personal_data( $email, 2 );
		$this->assertFalse( $first['done'] );
		$this->assertTrue( $second['done'] );
		$this->assertCount( 200, $first['data'] );
		$this->assertCount( 2, $second['data'] );
		$this->assertCount( 202, array_unique( wp_list_pluck( array_merge( $first['data'], $second['data'] ), 'item_id' ) ) );
		$first_erase = PrivacyPolicy::erase_personal_data( $email, 1 );
		$this->assertFalse( $first_erase['done'] );
		$second_erase = PrivacyPolicy::erase_personal_data( $email, 2 );
		$this->assertTrue( $second_erase['done'] );
		foreach ( $ids as $id ) {
			$this->assertFalse( metadata_exists( 'post', $id, '_radioedu_coordinador_email' ) );
			$this->assertFalse( metadata_exists( 'post', $id, '_radioedu_coordinador_nombre' ) );
		}
		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE usuario_emisor_id = %d', ColaboracionesTable::get_table_name(), $user_id ) ) );
		$this->assertSame( '0', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE idea_proyecto = %s', ColaboracionesTable::get_table_name(), 'Personal project text' ) ) );
	}

	public function test_erasure_finishes_when_a_full_batch_cannot_be_removed(): void {
		$email = 'stuck@example.org';
		for ( $i = 0; $i < 100; ++$i ) {
			$id = self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE ] );
			update_post_meta( $id, '_radioedu_coordinador_email', $email );
			update_post_meta( $id, '_radioedu_coordinador_nombre', 'Private Name' );
		}
		// Simulate a storage failure: every metadata deletion is refused.
		add_filter( 'delete_post_metadata', '__return_false' );
		try {
			$result = PrivacyPolicy::erase_personal_data( $email, 1 );
		} finally {
			remove_filter( 'delete_post_metadata', '__return_false' );
		}
		$this->assertTrue( $result['done'] );
		$this->assertFalse( $result['items_removed'] );
		$this->assertTrue( $result['items_retained'] );
	}

	public function test_empty_email_does_not_erase_unrelated_empty_contact(): void {
		$id = self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE ] );
		update_post_meta( $id, '_radioedu_coordinador_email', '' );
		update_post_meta( $id, '_radioedu_coordinador_nombre', 'Keep this name' );
		$this->assertFalse( PrivacyPolicy::erase_personal_data( '' )['items_removed'] );
		$this->assertSame( 'Keep this name', get_post_meta( $id, '_radioedu_coordinador_nombre', true ) );
	}
}
