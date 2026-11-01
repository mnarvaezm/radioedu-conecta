<?php
/**
 * Replies to proposals belong to whoever wrote them: export and erase them for that person.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Capabilities\RoleCapabilities;
use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\Emails\NotificationService;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Privacy\PrivacyPolicy;
use RadioEdu\Conecta\Rest\ColaboracionesController;
use WP_UnitTestCase;

class PrivacyRepliesTest extends WP_UnitTestCase {

	private int $sender;
	private int $receiver;
	private int $receiver_centro;

	public function set_up(): void {
		parent::set_up();
		RoleCapabilities::add_capabilities();
		ColaboracionesTable::create_table();
		$this->sender          = self::factory()->user->create( [ 'role' => 'author', 'user_email' => 'sender@example.org' ] );
		$this->receiver        = self::factory()->user->create( [ 'role' => 'author', 'user_email' => 'receiver@example.org' ] );
		$this->receiver_centro = $this->centro( $this->receiver );
	}

	public function tear_down(): void {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function centro( int $author ): int {
		return self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => 'publish', 'post_author' => $author ] );
	}

	private function proposal( int $receptor, array $fields = [] ): int {
		global $wpdb;
		$wpdb->insert(
			ColaboracionesTable::get_table_name(),
			array_merge(
				[
					'centro_emisor_id'   => $this->centro( $this->sender ),
					'centro_receptor_id' => $receptor,
					'usuario_emisor_id'  => $this->sender,
					'idea_proyecto'      => 'Sender idea',
					'created_at'         => current_time( 'mysql' ),
					'updated_at'         => current_time( 'mysql' ),
				],
				$fields
			)
		);
		return (int) $wpdb->insert_id;
	}

	public function test_the_reply_is_exported_and_erased_for_the_person_who_wrote_it(): void {
		$id = $this->proposal( $this->receiver_centro );
		wp_set_current_user( $this->receiver );
		$this->assertSame( NotificationService::SENT, ColaboracionesController::respond_to_proposal( $id, 'aceptada', 'Receiver personal reply' ) );
		$this->assertSame( $this->receiver, (int) ColaboracionesTable::get_by_id( $id )->usuario_respuesta_id );

		$export = PrivacyPolicy::export_personal_data( 'receiver@example.org' );
		$this->assertStringContainsString( 'Receiver personal reply', wp_json_encode( $export['data'] ) );

		$erase = PrivacyPolicy::erase_personal_data( 'receiver@example.org' );
		$row   = ColaboracionesTable::get_by_id( $id );
		$this->assertSame( '', (string) $row->respuesta_mensaje );
		$this->assertSame( 0, (int) $row->usuario_respuesta_id );
		$this->assertSame( 'Sender idea', $row->idea_proyecto, 'The sender text is not the receiver data.' );
		$this->assertSame( 'aceptada', $row->estado );
		$this->assertTrue( $erase['items_removed'] );
		$this->assertFalse( $erase['items_retained'] );
		$this->assertTrue( $erase['done'] );
	}

	public function test_replies_without_an_author_are_reported_as_retained(): void {
		// Written before 1.1, so nothing records who answered.
		$by_author = $this->proposal( $this->receiver_centro, [ 'estado' => 'rechazada', 'respuesta_mensaje' => 'Legacy reply' ] );
		$by_email  = $this->centro( $this->sender );
		update_post_meta( $by_email, '_radioedu_coordinador_email', 'receiver@example.org' );
		$this->proposal( $by_email, [ 'estado' => 'aceptada', 'respuesta_mensaje' => 'Legacy reply by email' ] );
		$this->proposal( $this->centro( $this->sender ), [ 'estado' => 'aceptada', 'respuesta_mensaje' => 'Someone else' ] );

		$erase = PrivacyPolicy::erase_personal_data( 'receiver@example.org' );
		$this->assertTrue( $erase['items_retained'] );
		$this->assertCount( 1, $erase['messages'] );
		$this->assertStringContainsString( '2', $erase['messages'][0] );
		$this->assertSame( 'Legacy reply', ColaboracionesTable::get_by_id( $by_author )->respuesta_mensaje, 'Not attributed to the current coordinator.' );
		$this->assertTrue( $erase['done'] );

		// The message is given once, not on every batch of the same request.
		$this->assertSame( [], PrivacyPolicy::erase_personal_data( 'receiver@example.org', 2 )['messages'] );
	}
}
