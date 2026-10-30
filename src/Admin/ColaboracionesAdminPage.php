<?php
/**
 * Pantalla de gestión de propuestas de colaboración (Matches) en el panel de administración.
 *
 * @package RadioEdu\Conecta\Admin
 */

declare( strict_types=1 );

namespace RadioEdu\Conecta\Admin;

use RadioEdu\Conecta\Database\ColaboracionesTable;
use RadioEdu\Conecta\Emails\NotificationService;
use RadioEdu\Conecta\PostTypes\CentroPostType;
use RadioEdu\Conecta\Rest\ColaboracionesController;

defined( 'ABSPATH' ) || exit;

/**
 * Permite a los coordinadores de centro y administradores consultar y responder propuestas de match.
 */
class ColaboracionesAdminPage {

	public const NONCE_ACTION = 'radioedu_respond_match_nonce_action';
	public const NONCE_NAME   = 'radioedu_respond_match_nonce';
	public const PAGE_SLUG    = 'radioedu-conecta-colaboraciones';

	/**
	 * Registra los hooks del submenú y procesamiento de respuestas.
	 */
	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_submenu_page' ] );
		add_action( 'admin_post_radioedu_respond_match', [ self::class, 'handle_respond_match' ] );
	}

	/**
	 * Añade el submenú 'Colaboraciones (Matches)' dentro de 'RadioEdu Centros'.
	 */
	public static function add_submenu_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . CentroPostType::POST_TYPE,
			__( 'Propuestas de Colaboración (Matches)', 'radioedu-conecta' ),
			__( 'Colaboraciones (Matches)', 'radioedu-conecta' ),
			'edit_radioedu_centros',
			self::PAGE_SLUG,
			[ self::class, 'render_page' ]
		);
	}

	/**
	 * Procesa la aceptación o rechazo de una propuesta desde el panel de administración.
	 */
	public static function handle_respond_match(): void {
		if ( ! is_user_logged_in() || ( ! current_user_can( 'edit_radioedu_centros' ) && ! current_user_can( 'manage_radioedu' ) ) ) {
			wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'radioedu-conecta' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$colab_id  = isset( $_POST['colaboracion_id'] ) && is_scalar( $_POST['colaboracion_id'] ) ? absint( wp_unslash( $_POST['colaboracion_id'] ) ) : 0;
		$estado    = isset( $_POST['estado'] ) ? sanitize_key( wp_unslash( $_POST['estado'] ) ) : '';
		$respuesta = isset( $_POST['respuesta_mensaje'] ) ? sanitize_textarea_field( wp_unslash( $_POST['respuesta_mensaje'] ) ) : '';
		$respuesta = mb_substr( $respuesta, 0, ColaboracionesTable::MAX_RESPUESTA_LENGTH );

		if ( $colab_id <= 0 || ! in_array( $estado, ColaboracionesTable::RESPONSE_STATUSES, true ) ) {
			wp_die( esc_html__( 'Parámetros de respuesta no válidos.', 'radioedu-conecta' ), '', [ 'response' => 400 ] );
		}

		$colab = ColaboracionesTable::get_by_id( $colab_id );
		if ( null === $colab || ! ColaboracionesController::user_can_manage_center( (int) $colab->centro_receptor_id ) ) {
			wp_die( esc_html__( 'No tienes permisos para responder a las propuestas de este centro.', 'radioedu-conecta' ), '', [ 'response' => 403 ] );
		}

		$result = ColaboracionesController::respond_to_proposal( $colab_id, $estado, $respuesta );
		if ( null !== $result ) {
			$error_data = $result->get_error_data();
			wp_die( esc_html( $result->get_error_message() ), '', [ 'response' => is_array( $error_data ) ? (int) $error_data['status'] : 500 ] );
		}

		$redirect_url = add_query_arg(
			[
				'post_type'     => CentroPostType::POST_TYPE,
				'page'          => self::PAGE_SLUG,
				'match_updated' => $estado,
			],
			admin_url( 'edit.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Renderiza la página de listado y gestión de propuestas de colaboración.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'edit_radioedu_centros' ) && ! current_user_can( 'manage_radioedu' ) ) {
			wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'radioedu-conecta' ) );
		}

		$user_id = get_current_user_id();
		$rows    = ColaboracionesTable::get_for_user(
			$user_id,
			ColaboracionesController::get_user_managed_center_ids( $user_id ),
			current_user_can( 'manage_radioedu' )
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag used to display a notice after the nonce-protected redirect.
		$updated_status = isset( $_GET['match_updated'] ) ? sanitize_key( wp_unslash( $_GET['match_updated'] ) ) : '';
		if ( ! in_array( $updated_status, ColaboracionesTable::RESPONSE_STATUSES, true ) ) {
			$updated_status = '';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Propuestas de Colaboración (Matches) - Traspasando Fronteras', 'radioedu-conecta' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Consulta las solicitudes de hermanamiento recibidas o enviadas por tu emisora escolar y coordina proyectos conjuntos.', 'radioedu-conecta' ); ?>
			</p>

			<?php if ( '' !== $updated_status ) : ?>
				<div class="notice notice-success is-dismissible" style="margin-top: 15px;">
					<p>
						<?php esc_html_e( 'La respuesta a la propuesta de colaboración se ha guardado correctamente y se ha notificado al centro emisor.', 'radioedu-conecta' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( empty( $rows ) ) : ?>
				<div class="card" style="max-width: 720px; margin-top: 20px; padding: 20px;">
					<p><?php esc_html_e( 'Todavía no hay propuestas de colaboración registradas para tus centros escolares.', 'radioedu-conecta' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
					<thead>
						<tr>
							<th scope="col" style="width: 120px;"><?php esc_html_e( 'Fecha', 'radioedu-conecta' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Centro Emisor', 'radioedu-conecta' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Centro Receptor', 'radioedu-conecta' ); ?></th>
							<th scope="col" style="width: 28%;"><?php esc_html_e( 'Idea del Proyecto y Detalles', 'radioedu-conecta' ); ?></th>
							<th scope="col" style="width: 110px;"><?php esc_html_e( 'Estado', 'radioedu-conecta' ); ?></th>
							<th scope="col" style="width: 26%;"><?php esc_html_e( 'Respuesta / Acciones', 'radioedu-conecta' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$colab_id       = (int) $row->id;
							$emisor_id      = (int) $row->centro_emisor_id;
							$receptor_id    = (int) $row->centro_receptor_id;
							$estado         = (string) $row->estado;
							$can_respond    = ColaboracionesController::user_can_manage_center( $receptor_id );
							$emisor_email   = NotificationService::get_center_contact_email( $emisor_id );
							$receptor_email = NotificationService::get_center_contact_email( $receptor_id );
							?>
							<tr>
								<td><?php echo esc_html( (string) $row->created_at ); ?></td>
								<td>
									<strong><?php echo esc_html( get_the_title( $emisor_id ) ); ?></strong>
									<?php if ( 'aceptada' === $estado && '' !== $emisor_email ) : ?>
										<br /><small><a href="mailto:<?php echo esc_attr( $emisor_email ); ?>"><?php echo esc_html( $emisor_email ); ?></a></small>
									<?php endif; ?>
								</td>
								<td>
									<strong><?php echo esc_html( get_the_title( $receptor_id ) ); ?></strong>
									<?php if ( 'aceptada' === $estado && '' !== $receptor_email ) : ?>
										<br /><small><a href="mailto:<?php echo esc_attr( $receptor_email ); ?>"><?php echo esc_html( $receptor_email ); ?></a></small>
									<?php endif; ?>
								</td>
								<td>
									<p style="margin: 0 0 6px 0;"><?php echo esc_html( (string) $row->idea_proyecto ); ?></p>
									<?php if ( ! empty( $row->curso_edades ) ) : ?>
										<small style="display: block;"><strong><?php esc_html_e( 'Edades:', 'radioedu-conecta' ); ?></strong> <?php echo esc_html( (string) $row->curso_edades ); ?></small>
									<?php endif; ?>
									<?php if ( ! empty( $row->idiomas ) ) : ?>
										<small style="display: block;"><strong><?php esc_html_e( 'Idiomas:', 'radioedu-conecta' ); ?></strong> <?php echo esc_html( (string) $row->idiomas ); ?></small>
									<?php endif; ?>
									<?php if ( ! empty( $row->fechas_aprox ) ) : ?>
										<small style="display: block;"><strong><?php esc_html_e( 'Fechas:', 'radioedu-conecta' ); ?></strong> <?php echo esc_html( (string) $row->fechas_aprox ); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( 'aceptada' === $estado ) : ?>
										<span style="color: #1b5e20; font-weight: 600;">✔ <?php esc_html_e( 'Aceptada', 'radioedu-conecta' ); ?></span>
									<?php elseif ( 'rechazada' === $estado ) : ?>
										<span style="color: #b71c1c; font-weight: 600;">✖ <?php esc_html_e( 'Declinada', 'radioedu-conecta' ); ?></span>
									<?php else : ?>
										<span style="color: #e65100; font-weight: 600;">⏳ <?php esc_html_e( 'Pendiente', 'radioedu-conecta' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( ! empty( $row->respuesta_mensaje ) ) : ?>
										<p style="margin: 0 0 8px 0;"><em>"<?php echo esc_html( (string) $row->respuesta_mensaje ); ?>"</em></p>
									<?php endif; ?>

									<?php if ( $can_respond && 'pendiente' === $estado ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<input type="hidden" name="action" value="radioedu_respond_match" />
											<input type="hidden" name="colaboracion_id" value="<?php echo esc_attr( (string) $colab_id ); ?>" />
											<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
											<label for="respuesta_mensaje_<?php echo esc_attr( (string) $colab_id ); ?>" class="screen-reader-text">
												<?php esc_html_e( 'Mensaje de respuesta', 'radioedu-conecta' ); ?>
											</label>
											<textarea
												id="respuesta_mensaje_<?php echo esc_attr( (string) $colab_id ); ?>"
												name="respuesta_mensaje"
												maxlength="2000"
												rows="2"
												style="width: 100%; margin-bottom: 6px;"
												placeholder="<?php esc_attr_e( 'Escribe un mensaje para el centro emisor...', 'radioedu-conecta' ); ?>"
											></textarea>
											<button type="submit" name="estado" value="aceptada" class="button button-primary button-small">
												<?php esc_html_e( 'Aceptar', 'radioedu-conecta' ); ?>
											</button>
											<button type="submit" name="estado" value="rechazada" class="button button-small" style="margin-left: 4px;">
												<?php esc_html_e( 'Declinar', 'radioedu-conecta' ); ?>
											</button>
										</form>
									<?php elseif ( 'pendiente' === $estado ) : ?>
										<span class="description"><?php esc_html_e( 'En espera de respuesta del centro receptor.', 'radioedu-conecta' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}

