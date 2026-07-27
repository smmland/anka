( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var container = document.getElementById( 'bmg-qr-canvas' );

		if ( ! container || typeof qrcode === 'undefined' ) {
			return;
		}

		var url = container.getAttribute( 'data-url' );

		if ( ! url ) {
			return;
		}

		// typeNumber 0 lets the library auto-pick the smallest QR version that fits the URL.
		var qr = qrcode( 0, 'M' );
		qr.addData( url );
		qr.make();

		var dataUrl = qr.createDataURL( 6, 8 );

		var img = document.createElement( 'img' );
		img.src = dataUrl;
		img.alt = 'QR';
		img.style.border = '1px solid #ddd';
		img.style.imageRendering = 'pixelated';
		container.appendChild( img );

		var downloadBtn = document.getElementById( 'bmg-qr-download' );

		if ( downloadBtn ) {
			downloadBtn.addEventListener( 'click', function () {
				var link = document.createElement( 'a' );
				link.href = dataUrl;
				link.download = 'bank-mellat-pay-link-qr.gif';
				document.body.appendChild( link );
				link.click();
				document.body.removeChild( link );
			} );
		}
	} );
} )();
