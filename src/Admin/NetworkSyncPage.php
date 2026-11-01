<?php
/**
 * Network admin page of the network sync.
 *
 * @package RadioEdu\Conecta\Admin
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Admin;

use RadioEdu\Conecta\Network\NetworkSync;

defined( 'ABSPATH' ) || exit;

/**
 * Network Admin → Settings → RadioEdu Conecta: the button that creates or updates the fichas
 * of every network site. The work happens in batches through the network-sync REST endpoint.
 */
final class NetworkSyncPage {

	/**
	 * Script handle of the page.
	 */
	public const SCRIPT_HANDLE = 'radioedu-conecta-network-sync';

	/**
	 * Page slug.
	 */
	public const PAGE_SLUG = 'radioedu-conecta-network';

	/**
	 * Hook suffix of the page, to load its script only there.
	 *
	 * @var string
	 */
	private static string $hook_suffix = '';

	/**
	 * Registers the hooks.
	 *
	 * @since 1.1
	 */
	public static function register(): void {
		add_action( 'network_admin_menu', [ self::class, 'add_page' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
	}

	/**
	 * Adds the page under the network Settings menu.
	 *
	 * @since 1.1
	 */
	public static function add_page(): void {
		self::$hook_suffix = (string) add_submenu_page(
			'settings.php',
			__( 'RadioEdu Conecta: fichas de la red', 'radioedu-conecta' ),
			__( 'RadioEdu Conecta', 'radioedu-conecta' ),
			'manage_network_options',
			self::PAGE_SLUG,
			[ self::class, 'render_page' ]
		);
	}

	/**
	 * Enqueues the page script, only on this page.
	 *
	 * @since 1.1
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( '' === self::$hook_suffix || self::$hook_suffix !== $hook_suffix ) {
			return;
		}

		// Only the compiled bundle can run; without a build the button does nothing, it is not fatal.
		$asset_file = RADIOEDU_CONECTA_DIR . 'build/network-sync.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			RADIOEDU_CONECTA_URL . 'build/network-sync.js',
			$asset['dependencies'],
			$asset['version'],
			[
				'in_footer' => true,
				'strategy'  => 'defer',
			]
		);
		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.radioeduConectaNetworkSync = ' . wp_json_encode( [ 'path' => '/radioedu-conecta/v1/network-sync' ] ) . ';',
			'before'
		);
		wp_set_script_translations( self::SCRIPT_HANDLE, 'radioedu-conecta', RADIOEDU_CONECTA_DIR . 'languages' );
	}

	/**
	 * Renders the page.
	 *
	 * @since 1.1
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'radioedu-conecta' ) );
		}

		$total = NetworkSync::count_source_sites();
		$rows  = [
			[ __( 'Nombre del sitio', 'radioedu-conecta' ), __( 'Nombre del centro', 'radioedu-conecta' ), __( 'Sí', 'radioedu-conecta' ) ],
			[ __( 'URL del sitio', 'radioedu-conecta' ), __( 'Web oficial', 'radioedu-conecta' ), __( 'Sí', 'radioedu-conecta' ) ],
			[ __( 'Icono del sitio', 'radioedu-conecta' ), __( 'Logo', 'radioedu-conecta' ), __( 'Sí', 'radioedu-conecta' ) ],
			[ __( 'Descripción corta', 'radioedu-conecta' ), __( 'Descripción', 'radioedu-conecta' ), __( 'Solo al crearla', 'radioedu-conecta' ) ],
			[ __( 'Email de administración', 'radioedu-conecta' ), __( 'Email del coordinador/a', 'radioedu-conecta' ), __( 'Solo al crearla', 'radioedu-conecta' ) ],
			[ __( 'Administrador del sitio', 'radioedu-conecta' ), __( 'Autor de la ficha', 'radioedu-conecta' ), __( 'Solo al crearla', 'radioedu-conecta' ) ],
		];
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of network sites (the main site excluded). */
						_n(
							'Crea en el directorio del sitio principal una ficha de radio para el %d sitio de la red, o actualiza la que ya tiene.',
							'Crea en el directorio del sitio principal una ficha de radio por cada uno de los %d sitios de la red, o actualiza la que ya tienen.',
							$total,
							'radioedu-conecta'
						),
						$total
					)
				);
				?>
			</p>

			<table class="widefat striped" style="max-width: 900px;">
				<caption class="screen-reader-text"><?php esc_html_e( 'Datos que la sincronización toma de cada sitio', 'radioedu-conecta' ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Del sitio', 'radioedu-conecta' ); ?></th>
						<th scope="col"><?php esc_html_e( 'A la ficha', 'radioedu-conecta' ); ?></th>
						<th scope="col"><?php esc_html_e( '¿Se actualiza al volver a sincronizar?', 'radioedu-conecta' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row[0] ); ?></td>
							<td><?php echo esc_html( $row[1] ); ?></td>
							<td><?php echo esc_html( $row[2] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p class="description" style="max-width: 900px;">
				<?php esc_html_e( 'Lo que el coordinador complete después (coordenadas, bandera, etapas, idiomas, temas y podcast) no se modifica nunca. Si ya existe una ficha creada a mano con la misma web, se reutiliza. Las fichas en la papelera no se tocan. Las fichas de sitios que ya no existen no se borran: aparecen en el resumen.', 'radioedu-conecta' ); ?>
			</p>

			<p>
				<label for="radioedu-network-sync-grant-author">
					<input type="checkbox" id="radioedu-network-sync-grant-author" <?php checked( NetworkSync::get_grant_author_role() ); ?> />
					<?php esc_html_e( 'Dar el rol Autor en el sitio principal a los autores de las fichas que no lo tengan, ni tengan otro superior, para que puedan editar su ficha.', 'radioedu-conecta' ); ?>
				</label>
			</p>

			<p>
				<button type="button" class="button button-primary" id="radioedu-network-sync-start" <?php disabled( 0 === $total ); ?>>
					<?php esc_html_e( 'Sincronizar fichas con los sitios de la red', 'radioedu-conecta' ); ?>
				</button>
			</p>
			<noscript>
				<p><?php esc_html_e( 'La sincronización necesita JavaScript.', 'radioedu-conecta' ); ?></p>
			</noscript>

			<div id="radioedu-network-sync-progress" hidden>
				<label for="radioedu-network-sync-bar"><?php esc_html_e( 'Progreso de la sincronización', 'radioedu-conecta' ); ?></label>
				<progress id="radioedu-network-sync-bar" max="<?php echo esc_attr( (string) max( 1, $total ) ); ?>" value="0"></progress>
				<p id="radioedu-network-sync-status" role="status"></p>
			</div>
			<div id="radioedu-network-sync-result"></div>
		</div>
		<?php
	}
}
