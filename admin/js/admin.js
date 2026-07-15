( function ( $ ) {
	'use strict';

	$( function () {
		$( '.asl-color-field' ).wpColorPicker();

		var frame;
		$( '#asl-icon-select' ).on( 'click', function ( e ) {
			e.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: ASL_Admin_Data.i18n.chooseIcon,
				button: { text: ASL_Admin_Data.i18n.useThisIcon },
				multiple: false,
				library: { type: 'image' },
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				$( '#asl-icon-url' ).val( attachment.url );
				$( '#asl-icon-preview' ).attr( 'src', attachment.url ).show();
				$( '#asl-icon-remove' ).show();
			} );

			frame.open();
		} );

		$( '#asl-icon-remove' ).on( 'click', function ( e ) {
			e.preventDefault();
			$( '#asl-icon-url' ).val( '' );
			$( '#asl-icon-preview' ).hide().attr( 'src', '' );
			$( this ).hide();
		} );

		$( '#asl-test-send-btn' ).on( 'click', function () {
			var $btn = $( this );
			var originalText = $btn.text();
			var $result = $( '#asl-test-result' );
			var mobile = $( '#asl-test-mobile' ).val();

			$result.removeClass( 'is-success is-error' ).text( '' );
			$btn.prop( 'disabled', true ).text( ASL_Admin_Data.i18n.sending );

			$.post( ASL_Admin_Data.ajaxUrl, {
				action: 'asl_send_test_sms',
				nonce: ASL_Admin_Data.nonce,
				mobile: mobile,
			} ).done( function ( res ) {
				var success = res && res.success;
				$result.addClass( success ? 'is-success' : 'is-error' ).text( res.data && res.data.message ? res.data.message : '' );
			} ).fail( function () {
				$result.addClass( 'is-error' ).text( '' );
			} ).always( function () {
				$btn.prop( 'disabled', false ).text( originalText );
			} );
		} );
	} );
} )( jQuery );
