( function () {
	'use strict';

	if ( 'undefined' === typeof arankiaVars ) {
		return;
	}

	function updateWishlistCountBadges( count ) {
		document.querySelectorAll( '.wishlist-count' ).forEach( function ( el ) {
			el.textContent = count;
		} );
	}

	function toggleWishlistButton( button, isActive ) {
		button.classList.toggle( 'is-active', isActive );
		button.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
	}

	document.addEventListener( 'click', function ( e ) {
		var button = e.target.closest( '.wishlist-btn' );
		if ( ! button ) {
			return;
		}
		e.preventDefault();

		if ( button.classList.contains( 'is-loading' ) ) {
			return;
		}
		button.classList.add( 'is-loading' );

		var productId = button.getAttribute( 'data-product-id' );
		var body      = new URLSearchParams();
		body.set( 'action', 'arankia_toggle_wishlist' );
		body.set( 'nonce', arankiaVars.wishlistNonce );
		body.set( 'product_id', productId );

		fetch( arankiaVars.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} )
			.then( function ( res ) { return res.json(); } )
			.then( function ( json ) {
				button.classList.remove( 'is-loading' );
				if ( json && json.success ) {
					toggleWishlistButton( button, json.data.added );
					updateWishlistCountBadges( json.data.count );
				}
			} )
			.catch( function () {
				button.classList.remove( 'is-loading' );
			} );
	} );

}() );
