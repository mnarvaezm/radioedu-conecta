<?php
/**
 * Tests for the idempotency key of a new proposal.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Capabilities\RoleCapabilities;
use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\Emails\NotificationService;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * A proposal sent again with the same key is not stored, mailed or counted twice.
 */
class ProposalIdempotencyTest extends WP_UnitTestCase {

	private const KEY = '0123456789abcdef0123456789abcdef';

	/**
	 * Sending user.
	 *
	 * @var int
	 */
	private int $user = 0;

	/**
	 * Station of the sending user.
	 *
	 * @var int
	 */
	private int $sender = 0;

	/**
	 * Receiving station.
	 *
	 * @var int
	 */
	private int $receiver = 0;

	/**
	 * Emails sent during the test.
	 *
	 * @var int
	 */
	private int $mails = 0;

	public function set_up(): void {
		parent::set_up();
		RoleCapabilities::add_capabilities();
		ColaboracionesTable::create_table();
		$this->user     = self::factory()->user->create( [ 'role' => 'author' ] );
		$owner          = self::factory()->user->create( [ 'role' => 'author' ] );
		$this->sender   = $this->centro( $this->user );
		$this->receiver = $this->centro( $owner );
		wp_set_current_user( $this->user );
		add_filter( 'pre_wp_mail', [ $this, 'count_mail' ] );
	}

	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', [ $this, 'count_mail' ] );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Counts an email instead of sending it.
	 *
	 * @return bool Sent.
	 */
	public function count_mail(): bool {
		++$this->mails;
		return true;
	}

	/**
	 * Creates a published station.
	 *
	 * @param int $author Author.
	 * @return int ID.
	 */
	private function centro( int $author ): int {
		return self::factory()->post->create(
			[
				'post_type'   => CentroPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_author' => $author,
			]
		);
	}

	/**
	 * Sends a proposal through the endpoint.
	 *
	 * @param string|null $key  Idempotency key, or null for none.
	 * @param string      $idea Project idea.
	 * @return \WP_REST_Response Response.
	 */
	private function send( ?string $key, string $idea = 'Podcast conjunto' ): \WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/radioedu-conecta/v1/colaboraciones' );
		$request->set_param( 'centro_emisor_id', $this->sender );
		$request->set_param( 'centro_receptor_id', $this->receiver );
		$request->set_param( 'idea_proyecto', $idea );
		if ( null !== $key ) {
			$request->set_param( 'idempotency_key', $key );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Number of stored proposals of the sending station.
	 *
	 * @return int Count.
	 */
	private function stored(): int {
		global $wpdb;
		$table = ColaboracionesTable::get_table_name();

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE centro_emisor_id = %d', $table, $this->sender ) );
	}

	public function test_a_retry_with_the_same_key_returns_the_first_proposal(): void {
		$first = $this->send( self::KEY );
		$this->assertSame( 201, $first->get_status() );

		// The first reply was lost, and the panel sends the same proposal again.
		$retry = $this->send( self::KEY, 'Texto editado tras el error' );

		$this->assertSame( 200, $retry->get_status() );
		$this->assertSame( $first->get_data()['id'], $retry->get_data()['id'] );
		$this->assertTrue( $retry->get_data()['repetida'] );
		$this->assertSame( NotificationService::SENT, $retry->get_data()['notificacion'] );
		$this->assertSame( 1, $this->stored() );
		$this->assertSame( 1, $this->mails );
		$rate = get_transient( 'radioedu_rate_' . $this->user );
		$this->assertSame( 1, $rate['count'] );
	}

	public function test_another_key_or_no_key_stores_another_proposal(): void {
		$this->assertSame( 201, $this->send( self::KEY )->get_status() );
		$this->assertSame( 201, $this->send( 'fedcba9876543210fedcba9876543210' )->get_status() );
		$this->assertSame( 201, $this->send( null )->get_status() );
		$this->assertSame( 201, $this->send( null )->get_status() );

		$this->assertSame( 4, $this->stored() );
	}

	public function test_a_key_does_not_return_the_proposal_of_another_user(): void {
		$this->assertSame( 201, $this->send( self::KEY )->get_status() );

		$this->user   = self::factory()->user->create( [ 'role' => 'author' ] );
		$this->sender = $this->centro( $this->user );
		wp_set_current_user( $this->user );
		$other = $this->send( self::KEY );

		$this->assertSame( 201, $other->get_status() );
		$this->assertFalse( $other->get_data()['repetida'] );
		$this->assertSame( 1, $this->stored() );
	}

	public function test_a_key_outside_the_pattern_is_rejected(): void {
		foreach ( [ 'short', str_repeat( 'a', 65 ), "0123456789abcdef'; DROP" ] as $key ) {
			$response = $this->send( $key );
			$this->assertSame( 400, $response->get_status(), $key );
			$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
		}
		$this->assertSame( 0, $this->stored() );
	}
}
