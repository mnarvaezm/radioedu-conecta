/**
 * Scripts para los metaboxes de RadioEdu Conecta en el panel de administración.
 */
( function () {
	'use strict';

	function init() {
		const i18n = window.radioeduAdminI18n || {};
		const logoIdInput = document.getElementById( 'radioedu_logo_id' );
		const previewBox = document.getElementById( 'radioedu-logo-preview' );
		const removeBtn = document.getElementById( 'radioedu_logo_remove' );
		const uploadBtn = document.getElementById( 'radioedu_logo_button' );
		let fileFrame = null;

		if ( ! uploadBtn || ! logoIdInput || ! previewBox || ! removeBtn ) {
			return;
		}

		function clearPreview() {
			while ( previewBox.firstChild ) {
				previewBox.removeChild( previewBox.firstChild );
			}
		}

		uploadBtn.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			if ( fileFrame ) {
				fileFrame.open();
				return;
			}

			fileFrame = wp.media( {
				title: i18n.selectLogo || '',
				button: {
					text: i18n.useLogo || '',
				},
				multiple: false,
				library: {
					type: 'image',
				},
			} );

			fileFrame.on( 'select', function () {
				const attachment = fileFrame
					.state()
					.get( 'selection' )
					.first()
					.toJSON();
				const previewUrl =
					attachment.sizes && attachment.sizes.thumbnail
						? attachment.sizes.thumbnail.url
						: attachment.url;

				logoIdInput.value = String( attachment.id );

				clearPreview();
				const img = document.createElement( 'img' );
				img.src = previewUrl;
				img.alt = i18n.logoAlt || '';
				img.style.maxWidth = '120px';
				img.style.height = 'auto';
				img.style.borderRadius = '50%';
				img.style.border = '2px solid #00838f';
				previewBox.appendChild( img );
				removeBtn.style.display = '';
			} );

			fileFrame.open();
		} );

		removeBtn.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			logoIdInput.value = '';
			clearPreview();

			const notice = document.createElement( 'span' );
			notice.className = 'description';
			notice.textContent = i18n.noLogo || '';
			previewBox.appendChild( notice );
			removeBtn.style.display = 'none';
			uploadBtn.focus();
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
