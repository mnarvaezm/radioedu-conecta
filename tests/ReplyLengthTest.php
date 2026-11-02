<?php
/**
 * Tests for the length of the reply to a proposal, from the admin screen and from REST.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Admin\ColaboracionesAdminPage;
use RadioEdu\Conecta\Capabilities\RoleCapabilities;
use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use RuntimeException;
use WP_REST_Request;
use WP_UnitTestCase;
use WPDieException;

/**
 * A reply longer than the column is rejected, never cut short, and line breaks count as one.
 */
class ReplyLengthTest extends WP_UnitTestCase {

	/**
	 * ID of the proposal to answer.
	 *
	 * @var int
	 */
	private int $proposal = 0;

	public function set_up(): void {
		parent::set_up();
		RoleCapabilities::add_capabilities();
		ColaboracionesTable::create_table();

		$owner  = self::factory()->user->create( [ 'role' => 'author' ] );
		$center = self::factory()->post->create(
			[
				'post_type'   => CentroPostType::POST_TYPE,
				'post_status' => 'publish',
				'post_author' => $owner,
			]
		);
		global $wpdb;
		$wpdb->insert(
			ColaboracionesTable::get_table_name(),
			[
				'centro_emisor_id'   => 1,
				'centro_receptor_id' => $center,
				'usuario_emisor_id'  => 1,
				'idea_proyecto'      => 'Test',
				'created_at'         => current_time( 'mysql' ),
				'updated_at'         => current_time( 'mysql' ),
			]
		);
		$this->proposal = (int) $wpdb->insert_id;
		wp_set_current_user( $owner );
	}

	public function tear_down(): void {
		$_POST    = [];
		$_REQUEST = [];
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Sends the reply form of the admin screen.
	 *
	 * @param string $reply Reply, as the browser sends it.
	 * @return string Where the handler redirected.
	 */
	private function submit_admin_form( string $reply ): string {
		$_POST    = [
			'colaboracion_id'                      => (string) $this->proposal,
			'estado'                               => 'aceptada',
			'respuesta_mensaje'                    => wp_slash( $reply ),
			ColaboracionesAdminPage::NONCE_NAME    => wp_create_nonce( ColaboracionesAdminPage::NONCE_ACTION ),
		];
		$_REQUEST = $_POST;

		// The handler exits after redirecting: stop it there.
		$stop = static function ( string $location ): string {
			throw new RuntimeException( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test control flow, never printed.
		};
		add_filter( 'wp_redirect', $stop );
		try {
			ColaboracionesAdminPage::handle_respond_match();
		} catch ( RuntimeException $redirect ) {
			return $redirect->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $stop );
		}

		return '';
	}

	public function test_the_admin_screen_rejects_a_reply_that_is_too_long(): void {
		try {
			$this->submit_admin_form( str_repeat( 'a', ColaboracionesTable::MAX_RESPUESTA_LENGTH + 1 ) );
			$this->fail( 'A reply longer than the column must be rejected.' );
		} catch ( WPDieException $error ) {
			$this->assertStringContainsString( 'demasiado largo', $error->getMessage() );
		}

		$row = ColaboracionesTable::get_by_id( $this->proposal );
		$this->assertSame( 'pendiente', $row->estado );
		$this->assertSame( '', (string) $row->respuesta_mensaje );
	}

	public function test_line_breaks_sent_by_a_form_count_as_one_character(): void {
		// 1,001 letters and 999 line breaks fill the 2,000 characters of the field's maxlength,
		// but take 2,999 characters with CRLF.
		$lines   = array_fill( 0, 999, 'a' );
		$lines[] = 'bb';
		$reply   = implode( "\r\n", $lines );
		$this->assertGreaterThan( ColaboracionesTable::MAX_RESPUESTA_LENGTH, mb_strlen( $reply ) );

		$location = $this->submit_admin_form( $reply );

		$this->assertStringContainsString( 'match_updated=aceptada', $location );
		$row = ColaboracionesTable::get_by_id( $this->proposal );
		$this->assertSame( 'aceptada', $row->estado );
		$this->assertSame( implode( "\n", $lines ), (string) $row->respuesta_mensaje );
		$this->assertSame( ColaboracionesTable::MAX_RESPUESTA_LENGTH, mb_strlen( (string) $row->respuesta_mensaje ) );
	}

	public function test_rest_rejects_a_reply_that_is_too_long_with_a_400(): void {
		$request = new WP_REST_Request( 'PATCH', '/radioedu-conecta/v1/colaboraciones/' . $this->proposal );
		$request->set_param( 'estado', 'aceptada' );
		$request->set_param( 'respuesta', str_repeat( 'a', ColaboracionesTable::MAX_RESPUESTA_LENGTH + 1 ) );

		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'pendiente', ColaboracionesTable::get_by_id( $this->proposal )->estado );
	}
}
