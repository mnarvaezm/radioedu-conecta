<?php
/**
 * Warns the administrator when the proposals table could not be created or updated.
 *
 * @package RadioEdu\Conecta
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Admin;

use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\PostTypes\CentroPostType;

defined( 'ABSPATH' ) || exit;

/**
 * Tells the administrator that the proposals table is not up to date.
 *
 * When the database refuses the DDL (no CREATE or ALTER privilege, for example), the plugin keeps
 * the old schema version and retries every few minutes (Plugin::maybe_update_db_schema()). Before,
 * only the debug log said so. Now an error notice on the plugin screens and a Site Health test say
 * it to whoever can manage the site.
 *
 * @since 1.2
 */
final class SchemaHealth {

	/**
	 * Name of the Site Health test.
	 *
	 * @since 1.2
	 *
	 * @var string
	 */
	public const TEST = 'radioedu_conecta_schema';

	/**
	 * Hooks the notice and the Site Health test.
	 *
	 * @since 1.2
	 */
	public static function register(): void {
		add_action( 'admin_notices', [ self::class, 'render_notice' ] );
		add_filter( 'site_status_tests', [ self::class, 'add_site_health_test' ] );
	}

	/**
	 * Whether the schema version saved is older than the one the plugin needs.
	 *
	 * Reads the option only, so it is cheap on every admin screen; the version is saved only once
	 * the table has every column and index (ColaboracionesTable::create_table()).
	 *
	 * @since 1.2
	 *
	 * @return bool True while the update has not succeeded.
	 */
	public static function is_outdated(): bool {
		return version_compare( (string) get_option( 'radioedu_conecta_db_version', '0' ), ColaboracionesTable::DB_VERSION, '<' );
	}

	/**
	 * Prints the error notice on the plugin screens, for users who can manage the site.
	 *
	 * @since 1.2
	 */
	public static function render_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( null === $screen || CentroPostType::POST_TYPE !== $screen->post_type || ! current_user_can( 'manage_options' ) || ! self::is_outdated() ) {
			return;
		}

		wp_admin_notice(
			esc_html( self::problem() ),
			[
				'type'        => 'error',
				'dismissible' => true,
			]
		);
	}

	/**
	 * Adds the Site Health test of the proposals table.
	 *
	 * @since 1.2
	 *
	 * @param array<string, mixed> $tests Site Health tests.
	 * @return array<string, mixed> The tests, with this one among the direct ones.
	 */
	public static function add_site_health_test( array $tests ): array {
		$tests['direct'][ self::TEST ] = [
			'label' => __( 'Tabla de propuestas de RadioEdu Conecta', 'radioedu-conecta' ),
			'test'  => [ self::class, 'site_health_test' ],
		];

		return $tests;
	}

	/**
	 * Checks the columns and indexes of the proposals table for Site Health.
	 *
	 * @since 1.2
	 *
	 * @return array<string, mixed> Site Health result.
	 */
	public static function site_health_test(): array {
		$current = ColaboracionesTable::has_current_schema();

		return [
			'label'       => $current
				? __( 'La tabla de propuestas de RadioEdu Conecta está al día', 'radioedu-conecta' )
				: __( 'La tabla de propuestas de RadioEdu Conecta no está al día', 'radioedu-conecta' ),
			'status'      => $current ? 'good' : 'critical',
			'badge'       => [
				'label' => 'RadioEdu Conecta',
				'color' => 'blue',
			],
			'description' => '<p>' . esc_html(
				$current
					? __( 'La tabla de propuestas de colaboración tiene todas las columnas e índices que usa el plugin.', 'radioedu-conecta' )
					: self::problem()
			) . '</p>',
			'actions'     => '',
			'test'        => self::TEST,
		];
	}

	/**
	 * What fails and what to do, as plain text.
	 *
	 * @since 1.2
	 *
	 * @return string Message.
	 */
	private static function problem(): string {
		return __( 'RadioEdu Conecta no ha podido crear o actualizar la tabla de propuestas de colaboración en la base de datos, y puede que las propuestas no se guarden. Comprueba que el usuario de la base de datos tiene permiso para crear y modificar tablas (CREATE y ALTER). El plugin lo vuelve a intentar cada 5 minutos.', 'radioedu-conecta' );
	}
}
