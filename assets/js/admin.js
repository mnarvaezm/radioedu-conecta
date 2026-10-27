/**
 * Scripts para los metaboxes de RadioEdu Conecta en el panel de administración.
 */
/* global jQuery, wp, radioeduAdminI18n */
( function( $ ) {
	'use strict';

	$( function() {
		var fileFrame;
		var $logoIdInput = $( '#radioedu_logo_id' );
		var $previewBox  = $( '#radioedu-logo-preview' );
		var $removeBtn   = $( '#radioedu_logo_remove' );
		var $uploadBtn   = $( '#radioedu_logo_button' );

		if ( ! $uploadBtn.length ) {
			return;
		}

		$uploadBtn.on( 'click', function( e ) {
			e.preventDefault();

			if ( fileFrame ) {
				fileFrame.open();
				return;
			}

			fileFrame = wp.media( {
				title: radioeduAdminI18n.selectLogo || 'Seleccionar logo de la radio',
				button: {
					text: radioeduAdminI18n.useLogo || 'Usar este logo'
				},
				multiple: false,
				library: {
					type: 'image'
				}
			} );

			fileFrame.on( 'select', function() {
				var attachment = fileFrame.state().get( 'selection' ).first().toJSON();
				$logoIdInput.val( attachment.id );

				var previewUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;

				$previewBox.empty();
				var $img = $( '<img>', {
					src: previewUrl,
					alt: 'Logo de la radio',
					css: {
						maxWidth: '120px',
						height: 'auto',
						borderRadius: '50%',
						border: '2px solid #00838f'
					}
				} );
				$previewBox.append( $img );
				$removeBtn.show();
			} );

			fileFrame.open();
		} );

		$removeBtn.on( 'click', function( e ) {
			e.preventDefault();
			$logoIdInput.val( '' );
			$previewBox.empty();
			var $span = $( '<span>', {
				class: 'description',
				text: 'No hay logo asignado. Se mostrará un icono por defecto.'
			} );
			$previewBox.append( $span );
			$removeBtn.hide();
		} );
	} );
} )( jQuery );
