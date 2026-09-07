( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {

		/* ---------------- Toggle elements (mobile menu / search modal) ---------------- */
		document.querySelectorAll( '[data-toggle]' ).forEach( function ( trigger ) {
			trigger.addEventListener( 'click', function () {
				var targetId = trigger.getAttribute( 'data-toggle' );
				var target   = document.getElementById( targetId );
				if ( ! target ) {
					return;
				}

				if ( 'mobile-menu' === targetId ) {
					target.classList.toggle( 'is-open' );
					var overlay = document.querySelector( '.mobile-menu-overlay' );
					if ( overlay ) {
						overlay.classList.toggle( 'is-open' );
					}
					var expanded = target.classList.contains( 'is-open' );
					document.querySelectorAll( '.mobile-menu-toggle' ).forEach( function ( btn ) {
						btn.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
					} );
				} else {
					target.classList.toggle( 'is-open' );
				}
			} );
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				document.querySelectorAll( '.is-open' ).forEach( function ( el ) {
					el.classList.remove( 'is-open' );
				} );
			}
		} );

		/* ---------------- Back to top ---------------- */
		var backToTop = document.getElementById( 'back-to-top' );
		if ( backToTop ) {
			window.addEventListener( 'scroll', function () {
				backToTop.classList.toggle( 'is-visible', window.scrollY > 500 );
			} );
			backToTop.addEventListener( 'click', function () {
				window.scrollTo( { top: 0, behavior: 'smooth' } );
			} );
		}

		/* ---------------- Sticky mobile add-to-cart bar (single product) ---------------- */
		var summary = document.querySelector( '.single-product-summary, div.product .summary' );
		var stickyBar = document.querySelector( '.sticky-add-to-cart' );
		if ( summary && stickyBar ) {
			var addToCartForm = document.querySelector( 'form.cart' );
			var observer = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					stickyBar.classList.toggle( 'is-visible', ! entry.isIntersecting );
				} );
			}, { threshold: 0 } );
			if ( addToCartForm ) {
				observer.observe( addToCartForm );
			}
		}

	} );
}() );
