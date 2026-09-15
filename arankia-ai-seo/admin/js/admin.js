( function ( $ ) {
	'use strict';

	function post( action, data ) {
		return $.post( AAS_Admin_Data.ajaxUrl, $.extend( {}, data, {
			action: action,
			nonce: AAS_Admin_Data.nonce,
		} ) );
	}

	function withBusy( $btn, callback ) {
		var originalText = $btn.text();
		$btn.prop( 'disabled', true ).data( 'original-text', originalText ).text( AAS_Admin_Data.i18n.working );

		callback().always( function () {
			$btn.prop( 'disabled', false ).text( $btn.data( 'original-text' ) || originalText );
		} );
	}

	$( function () {

		/* ---------------- Dashboard ---------------- */

		$( '#aas-select-all' ).on( 'change', function () {
			$( '.aas-row-checkbox' ).prop( 'checked', $( this ).prop( 'checked' ) );
		} );

		$( '.aas-generate-one' ).on( 'click', function () {
			var $btn       = $( this );
			var productId  = $btn.data( 'product-id' );
			var provider   = $( '#aas-provider-select' ).val();

			withBusy( $btn, function () {
				return post( 'aas_generate', { product_id: productId, provider: provider } ).done( function ( response ) {
					if ( response.success ) {
						window.location.href = response.data.redirect_url;
					} else {
						alert( response.data && response.data.message ? response.data.message : AAS_Admin_Data.i18n.genericError );
					}
				} ).fail( function () {
					alert( AAS_Admin_Data.i18n.genericError );
				} );
			} );
		} );

		$( '#aas-bulk-generate' ).on( 'click', function () {
			var $btn       = $( this );
			var provider   = $( '#aas-provider-select' ).val();
			var productIds = $( '.aas-row-checkbox:checked' ).map( function () {
				return $( this ).val();
			} ).get();

			if ( ! productIds.length ) {
				alert( AAS_Admin_Data.i18n.genericError );
				return;
			}

			withBusy( $btn, function () {
				return post( 'aas_bulk_generate', { product_ids: productIds, provider: provider } ).done( function ( response ) {
					var message = response.data && response.data.message ? response.data.message : AAS_Admin_Data.i18n.genericError;
					$( '.aas-bulk-result' ).text( message );
				} ).fail( function () {
					$( '.aas-bulk-result' ).text( AAS_Admin_Data.i18n.genericError );
				} );
			} );
		} );

		/* ---------------- Review slider ---------------- */

		var $card = $( '.aas-product-card' );

		function reviewContext() {
			return {
				productId: $card.data( 'product-id' ),
				batchId: $card.data( 'batch-id' ),
			};
		}

		$( '.aas-regenerate' ).on( 'click', function () {
			var $btn      = $( this );
			var ctx       = reviewContext();
			var provider  = $card.find( '.aas-provider-select-single' ).val();

			withBusy( $btn, function () {
				return post( 'aas_generate', { product_id: ctx.productId, provider: provider } ).done( function ( response ) {
					if ( response.success ) {
						window.location.reload();
					} else {
						$( '.aas-review-result' ).text( response.data && response.data.message ? response.data.message : AAS_Admin_Data.i18n.genericError );
					}
				} ).fail( function () {
					$( '.aas-review-result' ).text( AAS_Admin_Data.i18n.genericError );
				} );
			} );
		} );

		$( '.aas-apply-selected' ).on( 'click', function () {
			var $btn   = $( this );
			var ctx    = reviewContext();
			var fields = $card.find( '.aas-field-checkbox:checked' ).map( function () {
				return $( this ).val();
			} ).get();

			withBusy( $btn, function () {
				return post( 'aas_apply', { product_id: ctx.productId, batch_id: ctx.batchId, fields: fields } ).done( function ( response ) {
					if ( response.success ) {
						window.location.href = response.data.redirect_url;
					} else {
						$( '.aas-review-result' ).text( response.data && response.data.message ? response.data.message : AAS_Admin_Data.i18n.genericError );
					}
				} ).fail( function () {
					$( '.aas-review-result' ).text( AAS_Admin_Data.i18n.genericError );
				} );
			} );
		} );

		$( '.aas-reject-all' ).on( 'click', function () {
			if ( ! window.confirm( AAS_Admin_Data.i18n.confirmReject ) ) {
				return;
			}

			var $btn = $( this );
			var ctx  = reviewContext();

			withBusy( $btn, function () {
				return post( 'aas_reject_all', { product_id: ctx.productId } ).done( function ( response ) {
					if ( response.success ) {
						window.location.href = response.data.redirect_url;
					} else {
						$( '.aas-review-result' ).text( response.data && response.data.message ? response.data.message : AAS_Admin_Data.i18n.genericError );
					}
				} ).fail( function () {
					$( '.aas-review-result' ).text( AAS_Admin_Data.i18n.genericError );
				} );
			} );
		} );
	} );
}( jQuery ) );
