<?php
/**
 * Messages about notifications say what really happened to the email.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Admin\ColaboracionesAdminPage;
use RadioEdu\Conecta\Admin\Settings;
use RadioEdu\Conecta\Capabilities\RoleCapabilities;
use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\Emails\NotificationService;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Rest\ColaboracionesController;
use WP_REST_Request;
use WP_UnitTestCase;

class NotificationResultTest extends WP_UnitTestCase {

	private WP_REST_Request $request;

	public function set_up(): void {
		parent::set_up();
		RoleCapabilities::add_capabilities();
		ColaboracionesTable::create_table();
		$coord    = self::factory()->user->create( [ 'role' => 'author' ] );
		$receiver = self::factory()->user->create( [ 'role' => 'author', 'user_email' => 'receiver@example.org' ] );
		wp_set_current_user( $coord );
		$this->request = new WP_REST_Request( 'POST', '/radioedu-conecta/v1/colaboraciones' );
		$this->request->set_param( 'centro_emisor_id', self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => 'publish', 'post_author' => $coord ] ) );
		$this->request->set_param( 'centro_receptor_id', self::factory()->post->create( [ 'post_type' => CentroPostType::POST_TYPE, 'post_status' => 'publish', 'post_author' => $receiver ] ) );
		$this->request->set_param( 'idea_proyecto', 'Podcast conjunto' );
	}

	public function tear_down(): void {
		unset( $_GET['match_updated'], $_GET['match_notice'] );
		remove_filter( 'pre_wp_mail', '__return_false' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function send(): array {
		$response = ( new ColaboracionesController() )->create_item( $this->request );
		$this->assertSame( 201, $response->get_status() );
		return $response->get_data();
	}

	public function test_a_sent_notification_is_announced(): void {
		$data = $this->send();
		$this->assertSame( NotificationService::SENT, $data['notificacion'] );
		$this->assertStringContainsString( 'recibirá una notificación', $data['message'] );
	}

	public function test_disabled_notifications_are_not_promised(): void {
		update_option( Settings::OPTION_KEY, array_merge( Settings::get_default_settings(), [ 'notify_on_proposal' => '0' ] ) );
		$data = $this->send();
		$this->assertSame( NotificationService::DISABLED, $data['notificacion'] );
		$this->assertStringNotContainsString( 'notificación', $data['message'] );
	}

	public function test_a_failed_notification_is_reported(): void {
		add_filter( 'pre_wp_mail', '__return_false' );
		$data = $this->send();
		$this->assertSame( NotificationService::FAILED, $data['notificacion'] );
		$this->assertStringContainsString( 'No se ha podido enviar el aviso por correo', $data['message'] );
	}

	public function test_the_admin_notice_reports_a_failed_notification(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_GET['match_updated'] = 'aceptada';
		$_GET['match_notice']  = NotificationService::FAILED;

		ob_start();
		ColaboracionesAdminPage::render_page();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'no se ha podido enviar el aviso por correo', $html );
	}
}
