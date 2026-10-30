<?php
/**
 * Tests for custom capabilities and roles assignment.
 *
 * @package RadioEdu\Conecta\Tests
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Tests;

use RadioEdu\Conecta\Capabilities\RoleCapabilities;
use WP_UnitTestCase;

/**
 * Verifies role capability registration and removal across standard WordPress roles.
 */
class CapabilitiesTest extends WP_UnitTestCase {

	/**
	 * Ensure get_all_capabilities returns all 12 custom capabilities.
	 */
	public function test_get_all_capabilities_list(): void {
		$caps = RoleCapabilities::get_all_capabilities();

		$this->assertCount( 12, $caps );
		$this->assertContains( 'manage_radioedu', $caps );
		$this->assertContains( 'edit_radioedu_centros', $caps );
		$this->assertContains( 'edit_others_radioedu_centros', $caps );
		$this->assertContains( 'publish_radioedu_centros', $caps );
		$this->assertContains( 'propose_radioedu_colaboracion', $caps );
	}

	/**
	 * Verify capabilities are properly added to administrator, editor, and author roles.
	 */
	public function test_add_and_remove_capabilities(): void {
		RoleCapabilities::add_capabilities();

		$admin  = get_role( 'administrator' );
		$editor = get_role( 'editor' );
		$author = get_role( 'author' );

		$this->assertNotNull( $admin );
		$this->assertNotNull( $editor );
		$this->assertNotNull( $author );

		// Administrator gets all capabilities including manage_radioedu.
		$this->assertTrue( $admin->has_cap( 'manage_radioedu' ) );
		$this->assertTrue( $admin->has_cap( 'propose_radioedu_colaboracion' ) );

		// Editor gets CPT editorial capabilities and propose_radioedu_colaboracion, but not manage_radioedu.
		$this->assertFalse( $editor->has_cap( 'manage_radioedu' ) );
		$this->assertTrue( $editor->has_cap( 'edit_others_radioedu_centros' ) );
		$this->assertTrue( $editor->has_cap( 'propose_radioedu_colaboracion' ) );

		// Author gets own CPT capabilities and propose_radioedu_colaboracion, but not edit_others_radioedu_centros.
		$this->assertFalse( $author->has_cap( 'manage_radioedu' ) );
		$this->assertFalse( $author->has_cap( 'edit_others_radioedu_centros' ) );
		$this->assertTrue( $author->has_cap( 'edit_radioedu_centros' ) );
		$this->assertTrue( $author->has_cap( 'publish_radioedu_centros' ) );
		$this->assertTrue( $author->has_cap( 'propose_radioedu_colaboracion' ) );

		// Verify clean removal upon uninstallation.
		RoleCapabilities::remove_capabilities();

		$admin_after  = get_role( 'administrator' );
		$author_after = get_role( 'author' );

		$this->assertFalse( $admin_after->has_cap( 'manage_radioedu' ) );
		$this->assertFalse( $author_after->has_cap( 'propose_radioedu_colaboracion' ) );

		// Restore capabilities for subsequent tests.
		RoleCapabilities::add_capabilities();
	}
}

