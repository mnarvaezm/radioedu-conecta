<?php
/**
 * Panel lateral "Solicitud de match" (drawer/offcanvas) compartido por el directorio y la ficha de centro.
 *
 * Its own interactive region of the store radioedu-conecta/directorio, so the profile can use it
 * without turning the whole page, with the coordinator's description, into one.
 *
 * @package RadioEdu\Conecta
 *
 * @var array<int, array{id: int, nombre: string}> $user_centers_list     Centros gestionados por el usuario actual.
 * @var int                                        $drawer_receptor_id    ID del centro receptor preseleccionado (0 si se elige en una tarjeta).
 * @var string                                     $drawer_receptor_label Nombre y ubicación del centro receptor preseleccionado.
 * @var string                                     $login_url             Login URL that comes back to this page.
 */

declare( strict_types=1 );

use RadioEdu\Conecta\Frontend\DirectoryStore;
use RadioEdu\Conecta\Icons;

defined( 'ABSPATH' ) || exit;
?>
<div class="radioedu-drawer-root" data-wp-interactive="<?php echo esc_attr( DirectoryStore::NAMESPACE ); ?>">
	<div id="radioedu-drawer-overlay" class="radioedu-drawer-overlay" aria-hidden="true" data-wp-class--is-open="state.drawer.isOpen" data-wp-on--click="actions.closeDrawer"></div>

	<aside id="radioedu-match-drawer" class="radioedu-match-drawer" aria-labelledby="radioedu-drawer-title" aria-modal="true" role="dialog" tabindex="-1" hidden data-wp-bind--hidden="!state.drawer.isVisible" data-wp-class--is-open="state.drawer.isOpen" data-wp-on-document--keydown="callbacks.onKeydown">
		<div class="radioedu-drawer-header">
			<div>
				<h3 id="radioedu-drawer-title" class="radioedu-drawer-title"><?php esc_html_e( 'Solicitud de match', 'radioedu-conecta' ); ?></h3>
				<p class="radioedu-drawer-subtitle"><?php esc_html_e( '¡Cuéntanos tu idea y empezad a crear juntos!', 'radioedu-conecta' ); ?></p>
			</div>
			<button type="button" id="radioedu-btn-close-drawer" class="radioedu-btn-close" aria-label="<?php esc_attr_e( 'Cerrar panel', 'radioedu-conecta' ); ?>" data-wp-on--click="actions.closeDrawer"><?php Icons::render( 'radioedu-conecta/close' ); ?></button>
		</div>

		<form id="radioedu-match-form" class="radioedu-drawer-form" data-wp-on--submit="actions.submitProposal" data-wp-on--input="actions.clearInvalid">

			<!-- Tu Centro -->
			<div class="radioedu-form-field">
				<?php if ( ! empty( $user_centers_list ) ) : ?>
					<label for="radioedu-match-emisor" class="radioedu-form-label">
						<?php Icons::render( 'core/home' ); ?> <?php esc_html_e( 'Tu centro', 'radioedu-conecta' ); ?>
					</label>
					<select id="radioedu-match-emisor" name="centro_emisor_id" class="radioedu-form-select" required data-wp-on--invalid="actions.markInvalid">
						<?php foreach ( $user_centers_list as $radioedu_conecta_center ) : ?>
							<option value="<?php echo esc_attr( (string) $radioedu_conecta_center['id'] ); ?>">
								<?php echo esc_html( $radioedu_conecta_center['nombre'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<label for="radioedu-match-emisor-txt" class="radioedu-form-label">
						<?php Icons::render( 'core/home' ); ?> <?php esc_html_e( 'Tu centro', 'radioedu-conecta' ); ?>
					</label>
					<input type="text" id="radioedu-match-emisor-txt" class="radioedu-form-input" value="<?php echo esc_attr( is_user_logged_in() ? __( 'Sin centro asignado como coordinador', 'radioedu-conecta' ) : __( 'Debes iniciar sesión para enviar propuestas', 'radioedu-conecta' ) ); ?>" disabled />
					<input type="hidden" id="radioedu-match-emisor" name="centro_emisor_id" value="" />
				<?php endif; ?>
			</div>

			<!-- Centro con el que colaborar (preseleccionado) -->
			<div class="radioedu-form-field">
				<label for="radioedu-match-receptor-txt" class="radioedu-form-label">
					<?php Icons::render( 'radioedu-conecta/institution' ); ?> <?php esc_html_e( 'Centro con el que quieres colaborar', 'radioedu-conecta' ); ?>
				</label>
				<input type="text" id="radioedu-match-receptor-txt" class="radioedu-form-input" value="<?php echo esc_attr( $drawer_receptor_label ); ?>" readonly data-wp-bind--value="state.drawer.receptorLabel" />
				<input type="hidden" id="radioedu-match-receptor" name="centro_receptor_id" value="<?php echo esc_attr( $drawer_receptor_id > 0 ? (string) $drawer_receptor_id : '' ); ?>" data-wp-bind--value="state.drawer.receptorId" />
			</div>

			<!-- Idea de Proyecto -->
			<div class="radioedu-form-field">
				<label for="radioedu-match-idea" class="radioedu-form-label">
					<?php Icons::render( 'core/tip' ); ?> <?php esc_html_e( 'Idea de proyecto', 'radioedu-conecta' ); ?>
				</label>
				<textarea id="radioedu-match-idea" name="idea_proyecto" class="radioedu-form-textarea" rows="4" maxlength="5000" placeholder="<?php esc_attr_e( 'Ejemplo: Queremos crear un podcast conjunto sobre la vida en entornos rurales, con entrevistas a estudiantes de ambos centros...', 'radioedu-conecta' ); ?>" required data-wp-on--invalid="actions.markInvalid"></textarea>
			</div>

			<!-- Curso / Edades e Idioma en 2 columnas -->
			<div class="radioedu-form-row">
				<div class="radioedu-form-field radioedu-col-6">
					<label for="radioedu-match-edades" class="radioedu-form-label">
						<?php Icons::render( 'core/people' ); ?> <?php esc_html_e( 'Curso o edades', 'radioedu-conecta' ); ?>
					</label>
					<input type="text" id="radioedu-match-edades" name="curso_edades" class="radioedu-form-input" maxlength="100" placeholder="<?php esc_attr_e( 'Ej: 12-14 años (1º-2º ESO)', 'radioedu-conecta' ); ?>" />
				</div>
				<div class="radioedu-form-field radioedu-col-6">
					<label for="radioedu-match-idiomas" class="radioedu-form-label">
						<?php Icons::render( 'core/language' ); ?> <?php esc_html_e( 'Idioma(s)', 'radioedu-conecta' ); ?>
					</label>
					<input type="text" id="radioedu-match-idiomas" name="idiomas" class="radioedu-form-input" maxlength="100" placeholder="<?php esc_attr_e( 'Ej: Español / Inglés', 'radioedu-conecta' ); ?>" />
				</div>
			</div>

			<!-- Fechas aproximadas -->
			<div class="radioedu-form-field">
				<label for="radioedu-match-fechas" class="radioedu-form-label">
					<?php Icons::render( 'core/calendar' ); ?> <?php esc_html_e( 'Fechas aproximadas', 'radioedu-conecta' ); ?>
				</label>
				<input type="text" id="radioedu-match-fechas" name="fechas_aprox" class="radioedu-form-input" maxlength="150" placeholder="<?php esc_attr_e( 'Ej: 2º Trimestre (Enero - Marzo)', 'radioedu-conecta' ); ?>" />
			</div>

			<!-- Feedback de mensaje -->
			<div id="radioedu-drawer-feedback" class="radioedu-drawer-feedback" role="alert" hidden data-wp-bind--hidden="!state.drawer.feedback" data-wp-class--radioedu-feedback-success="state.drawer.isSuccess" data-wp-class--radioedu-feedback-error="!state.drawer.isSuccess">
				<span data-wp-text="state.drawer.feedback"></span>
				<a href="<?php echo esc_url( $login_url ); ?>" hidden data-wp-bind--hidden="!state.drawer.showLogin"><?php esc_html_e( 'Iniciar sesión', 'radioedu-conecta' ); ?></a>
			</div>

			<!-- Botón de envío -->
			<div class="radioedu-form-action">
				<button type="submit" id="radioedu-btn-submit-match" class="radioedu-btn-submit" data-wp-bind--disabled="state.submitDisabled" data-wp-bind--aria-busy="state.drawer.isSubmitting">
					<?php Icons::render( 'radioedu-conecta/send' ); ?> <span class="radioedu-btn-label" data-wp-text="state.submitLabel"><?php esc_html_e( 'Enviar propuesta', 'radioedu-conecta' ); ?></span>
				</button>
			</div>

		</form>

		<!-- Guía en 3 pasos al pie del panel -->
		<footer class="radioedu-drawer-guide">
			<div class="radioedu-guide-step">
				<?php Icons::render( 'core/search' ); ?>
				<strong><?php esc_html_e( '1. Encuentra', 'radioedu-conecta' ); ?></strong>
				<p><?php esc_html_e( 'Busca centros afines a tus intereses', 'radioedu-conecta' ); ?></p>
			</div>
			<div class="radioedu-guide-arrow"><?php Icons::render( 'core/arrow-right' ); ?></div>
			<div class="radioedu-guide-step">
				<?php Icons::render( 'core/people' ); ?>
				<strong><?php esc_html_e( '2. Conecta', 'radioedu-conecta' ); ?></strong>
				<p><?php esc_html_e( 'Envía tu propuesta de match', 'radioedu-conecta' ); ?></p>
			</div>
			<div class="radioedu-guide-arrow"><?php Icons::render( 'core/arrow-right' ); ?></div>
			<div class="radioedu-guide-step">
				<?php Icons::render( 'radioedu-conecta/microphone' ); ?>
				<strong><?php esc_html_e( '3. Crea', 'radioedu-conecta' ); ?></strong>
				<p><?php esc_html_e( '¡Desarrollad juntos vuestro proyecto!', 'radioedu-conecta' ); ?></p>
			</div>
		</footer>

	</aside>
</div>
