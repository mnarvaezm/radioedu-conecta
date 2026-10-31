/**
 * Sidebar panels of the centro block editor.
 *
 * They replace the classic meta box in the block editor. The fields are registered post meta
 * that the REST API exposes in the edit context only (see CentroMeta), so the editor saves
 * them together with the centro.
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';
import { useDispatch, useRegistry, useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { store as preferencesStore } from '@wordpress/preferences';
import { MediaUpload, MediaUploadCheck } from '@wordpress/block-editor';
import {
	BaseControl,
	Button,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

const PLUGIN_NAME = 'radioedu-conecta-centro';
const MAIN_PANEL = 'radioedu-emisora';
const settings = window.radioeduConectaCentroPanel || {};

/**
 * Returns the edited meta of the current centro and a setter for one key.
 *
 * @return {Array} Meta values and a `( key, value )` setter.
 */
function useCentroMeta() {
	const meta = useSelect(
		( select ) => select( editorStore ).getEditedPostAttribute( 'meta' ),
		[]
	);
	const { editPost } = useDispatch( editorStore );

	return [
		meta || {},
		( key, value ) => editPost( { meta: { [ key ]: value } } ),
	];
}

/**
 * Opens the main panel the first time each user edits a centro.
 *
 * Plugin panels start closed; afterwards the editor remembers whether the user closed it.
 * The state is read from the registry inside the effect, so running it twice (React strict
 * mode with SCRIPT_DEBUG) does not toggle the panel closed again.
 *
 * @param {boolean} isCentro Whether the edited post is a centro.
 */
function useOpenMainPanelOnce( isCentro ) {
	const registry = useRegistry();

	useEffect( () => {
		const preferences = registry.select( preferencesStore );
		if (
			! isCentro ||
			preferences.get( 'radioedu-conecta', 'centroPanelOpened' )
		) {
			return;
		}
		const panelName = `${ PLUGIN_NAME }/${ MAIN_PANEL }`;
		if (
			! registry.select( editorStore ).isEditorPanelOpened( panelName )
		) {
			registry
				.dispatch( editorStore )
				.toggleEditorPanelOpened( panelName );
		}
		registry
			.dispatch( preferencesStore )
			.set( 'radioedu-conecta', 'centroPanelOpened', true );
	}, [ isCentro, registry ] );
}

/**
 * Logo picker: an image of the media library.
 *
 * @param {Object}               props          Component props.
 * @param {number}               props.logoId   Attachment ID, 0 when there is no logo.
 * @param {(id: number) => void} props.onChange Receives the new attachment ID.
 */
function LogoField( { logoId, onChange } ) {
	const uploadButton = useRef();
	const image = useSelect(
		( select ) =>
			logoId
				? select( coreStore ).getEntityRecord(
						'postType',
						'attachment',
						logoId
					)
				: null,
		[ logoId ]
	);
	const previewUrl = logoId
		? image?.media_details?.sizes?.thumbnail?.source_url ||
			image?.source_url
		: settings.fallbackLogoUrl;

	return (
		<div className="radioedu-centro-panel__logo">
			{ /* No <label for>: it would replace the buttons' own text as their accessible name (WCAG 2.5.3). */ }
			<BaseControl.VisualLabel>
				{ __( 'Logo de la Radio Escolar', 'radioedu-conecta' ) }
			</BaseControl.VisualLabel>
			<div className="radioedu-logo-preview-box">
				{ previewUrl && (
					<img
						src={ previewUrl }
						alt={ __( 'Logo de la radio', 'radioedu-conecta' ) }
					/>
				) }
				{ ! logoId && ! previewUrl && (
					<span className="description">
						{ __(
							'No hay logo asignado. Se mostrará un icono por defecto.',
							'radioedu-conecta'
						) }
					</span>
				) }
			</div>
			<div className="radioedu-centro-panel__logo-actions">
				<MediaUploadCheck
					fallback={
						<p className="description">
							{ __(
								'Necesitas permiso para subir archivos para cambiar el logo.',
								'radioedu-conecta'
							) }
						</p>
					}
				>
					<MediaUpload
						title={ __(
							'Seleccionar logo de la radio',
							'radioedu-conecta'
						) }
						allowedTypes={ [ 'image' ] }
						value={ logoId || undefined }
						onSelect={ ( media ) => onChange( media.id ) }
						render={ ( { open } ) => (
							<Button
								ref={ uploadButton }
								variant="secondary"
								onClick={ open }
							>
								{ __(
									'Subir / Elegir Logo',
									'radioedu-conecta'
								) }
							</Button>
						) }
					/>
				</MediaUploadCheck>
				{ !! logoId && (
					<Button
						variant="link"
						isDestructive
						onClick={ () => {
							onChange( 0 );
							uploadButton.current?.focus();
						} }
					>
						{ __( 'Eliminar logo', 'radioedu-conecta' ) }
					</Button>
				) }
			</div>
			<p className="radioedu-centro-panel__help">
				{ __(
					'Imagen circular distintiva de la emisora escolar (recomendado 300x300 px formato PNG o JPG).',
					'radioedu-conecta'
				) }
			</p>
		</div>
	);
}

/**
 * Panels of the centro fields.
 *
 * @return {?Element} The panels, or null outside the centro editor.
 */
function CentroPanels() {
	const postType = useSelect(
		( select ) => select( editorStore ).getCurrentPostType(),
		[]
	);
	const [ meta, setMeta ] = useCentroMeta();
	const isCentro = postType === settings.postType;
	useOpenMainPanelOnce( isCentro );

	if ( ! isCentro ) {
		return null;
	}

	const text = ( key ) => meta[ key ] ?? '';
	const textField = ( key ) => ( {
		value: text( key ),
		onChange: ( value ) => setMeta( key, value ),
	} );

	return (
		<>
			<PluginDocumentSettingPanel
				name={ MAIN_PANEL }
				title={ __( 'Datos de la emisora', 'radioedu-conecta' ) }
				className="radioedu-centro-panel"
			>
				<LogoField
					logoId={ Number( meta._radioedu_logo_id ) || 0 }
					onChange={ ( id ) => setMeta( '_radioedu_logo_id', id ) }
				/>
				<SelectControl
					label={ __( 'Bandera / Región', 'radioedu-conecta' ) }
					options={ settings.flags || [] }
					help={ __(
						'Determina la bandera mostrada en la tarjeta de resultados y filtros geográficos.',
						'radioedu-conecta'
					) }
					{ ...textField( '_radioedu_bandera' ) }
				/>
				<TextControl
					label={ __(
						'Cursos / Edades del Alumnado',
						'radioedu-conecta'
					) }
					placeholder={ __(
						'Ej: 10–14 años (5º Primaria a 2º ESO)',
						'radioedu-conecta'
					) }
					help={ __(
						'Informa a otros centros sobre el grupo de edad de los alumnos que participan en la radio.',
						'radioedu-conecta'
					) }
					{ ...textField( '_radioedu_edades' ) }
				/>
				<TextControl
					type="url"
					label={ __(
						'URL de Podcast / Streaming',
						'radioedu-conecta'
					) }
					placeholder="https://"
					help={ __(
						'Enlace donde se pueden escuchar las grabaciones o emisiones del centro escolar.',
						'radioedu-conecta'
					) }
					{ ...textField( '_radioedu_podcast_url' ) }
				/>
				<TextControl
					type="url"
					label={ __( 'Web Oficial del Centro', 'radioedu-conecta' ) }
					placeholder="https://"
					{ ...textField( '_radioedu_web_centro' ) }
				/>
				<ToggleControl
					label={ __(
						'Disponible para recibir propuestas de match y proyectos conjuntos',
						'radioedu-conecta'
					) }
					checked={ '0' !== meta._radioedu_disponible_colaborar }
					onChange={ ( checked ) =>
						setMeta(
							'_radioedu_disponible_colaborar',
							checked ? '1' : '0'
						)
					}
				/>
			</PluginDocumentSettingPanel>

			<PluginDocumentSettingPanel
				name="radioedu-ubicacion"
				title={ __( 'Ubicación en el mapa', 'radioedu-conecta' ) }
				className="radioedu-centro-panel"
			>
				<p className="radioedu-centro-panel__help">
					{ __(
						'Coordenadas decimales para ubicar el centro en el mapa interactivo (ej. Cáceres: 39.4699, -6.3708).',
						'radioedu-conecta'
					) }
				</p>
				<TextControl
					type="number"
					step="any"
					min={ -90 }
					max={ 90 }
					label={ __( 'Latitud', 'radioedu-conecta' ) }
					placeholder="39.4699"
					{ ...textField( '_radioedu_lat' ) }
				/>
				<TextControl
					type="number"
					step="any"
					min={ -180 }
					max={ 180 }
					label={ __( 'Longitud', 'radioedu-conecta' ) }
					placeholder="-6.3708"
					{ ...textField( '_radioedu_lng' ) }
				/>
			</PluginDocumentSettingPanel>

			<PluginDocumentSettingPanel
				name="radioedu-coordinacion"
				title={ __( 'Coordinación', 'radioedu-conecta' ) }
				className="radioedu-centro-panel"
			>
				<TextControl
					label={ __(
						'Coordinador/a Responsable',
						'radioedu-conecta'
					) }
					placeholder={ __(
						'Nombre del docente coordinador',
						'radioedu-conecta'
					) }
					{ ...textField( '_radioedu_coordinador_nombre' ) }
				/>
				<TextControl
					type="email"
					label={ __(
						'Email del coordinador/a',
						'radioedu-conecta'
					) }
					placeholder="coordinador@educarex.es"
					help={
						<>
							<strong>
								{ __(
									'Aviso de Privacidad (RGPD):',
									'radioedu-conecta'
								) }
							</strong>{ ' ' }
							{ __(
								'El correo NO se publicará en el directorio público. Se emplea exclusivamente para avisos internos del sistema cuando otro centro envíe una propuesta de colaboración.',
								'radioedu-conecta'
							) }
						</>
					}
					{ ...textField( '_radioedu_coordinador_email' ) }
				/>
			</PluginDocumentSettingPanel>
		</>
	);
}

registerPlugin( PLUGIN_NAME, { render: CentroPanels } );
