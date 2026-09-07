( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {

		/* ---------------- Shelf arrows ---------------- */
		document.querySelectorAll( '[data-shelf-next]' ).forEach( function ( btn ) {
			var track = document.querySelector( '[data-shelf="' + btn.getAttribute( 'data-shelf-next' ) + '"]' );
			if ( track ) {
				btn.addEventListener( 'click', function () { track.scrollBy( { left: -260, behavior: 'smooth' } ); } );
			}
		} );
		document.querySelectorAll( '[data-shelf-prev]' ).forEach( function ( btn ) {
			var track = document.querySelector( '[data-shelf="' + btn.getAttribute( 'data-shelf-prev' ) + '"]' );
			if ( track ) {
				btn.addEventListener( 'click', function () { track.scrollBy( { left: 260, behavior: 'smooth' } ); } );
			}
		} );

		/* ---------------- Deal countdown ---------------- */
		var countdown = document.querySelector( '[data-countdown]' );
		if ( countdown ) {
			var target = parseInt( countdown.getAttribute( 'data-countdown' ), 10 ) * 1000;
			var faDigits = '۰۱۲۳۴۵۶۷۸۹';
			var toFa = function ( n ) {
				return String( n ).padStart( 2, '0' ).replace( /[0-9]/g, function ( d ) { return faDigits[ d ]; } );
			};
			var hEl = countdown.querySelector( '[data-cd-h]' );
			var mEl = countdown.querySelector( '[data-cd-m]' );
			var sEl = countdown.querySelector( '[data-cd-s]' );

			var tick = function () {
				var diff = Math.max( 0, target - Date.now() );
				var h = Math.floor( diff / 3600000 );
				var m = Math.floor( ( diff % 3600000 ) / 60000 );
				var s = Math.floor( ( diff % 60000 ) / 1000 );
				if ( hEl ) { hEl.textContent = toFa( h ); }
				if ( mEl ) { mEl.textContent = toFa( m ); }
				if ( sEl ) { sEl.textContent = toFa( s ); }
			};
			tick();
			setInterval( tick, 1000 );
		}

		/* ---------------- Newsletter submit ---------------- */
		var clubForm = document.querySelector( '[data-club-form]' );
		if ( clubForm && 'undefined' !== typeof arankiaVars ) {
			clubForm.addEventListener( 'submit', function ( e ) {
				e.preventDefault();

				var button = clubForm.querySelector( 'button' );
				var status = clubForm.querySelector( '[data-club-status]' );
				var email  = clubForm.querySelector( 'input[type="email"]' ).value;
				var originalLabel = button.textContent;

				button.disabled = true;

				var body = new URLSearchParams();
				body.set( 'action', 'arankia_newsletter_subscribe' );
				body.set( 'nonce', arankiaVars.newsletterNonce || '' );
				body.set( 'email', email );

				fetch( arankiaVars.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: body.toString(),
				} )
					.then( function ( res ) { return res.json(); } )
					.then( function ( json ) {
						button.disabled = false;
						if ( status ) {
							status.textContent = json && json.data ? json.data.message : '';
						}
						if ( json && json.success ) {
							clubForm.reset();
						}
					} )
					.catch( function () {
						button.disabled = false;
						if ( status ) {
							status.textContent = arankiaVars.i18n && arankiaVars.i18n.error ? arankiaVars.i18n.error : '';
						}
					} );
			} );
		}

	} );
}() );
