( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {

		/* Wallet top-up preset amount buttons */
		var amountField = document.getElementById( 'arankia_topup_amount' );
		document.querySelectorAll( '.wallet-preset-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				document.querySelectorAll( '.wallet-preset-btn' ).forEach( function ( b ) {
					b.classList.remove( 'is-selected' );
				} );
				btn.classList.add( 'is-selected' );
				if ( amountField ) {
					amountField.value = btn.getAttribute( 'data-amount' );
				}
			} );
		} );

		/* Keep the "current" nav item highlighted even for custom endpoints */
		var path = window.location.pathname.replace( /\/$/, '' );
		document.querySelectorAll( '.arankia-account-nav a' ).forEach( function ( link ) {
			var linkPath = link.pathname.replace( /\/$/, '' );
			if ( linkPath && path.indexOf( linkPath ) === 0 ) {
				link.closest( 'li' ).classList.add( 'is-active' );
			}
		} );

	} );
}() );
