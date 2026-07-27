( function ( wp, wc ) {
	'use strict';

	if ( ! wc || ! wc.wcBlocksRegistry || ! wc.wcSettings ) {
		return;
	}

	var registerPaymentMethod = wc.wcBlocksRegistry.registerPaymentMethod;
	var getSetting            = wc.wcSettings.getSetting;
	var el                    = wp.element.createElement;
	var decodeEntities        = wp.htmlEntities.decodeEntities;
	var __                    = wp.i18n.__;

	var settings     = getSetting( 'bank_mellat_data', {} );
	var defaultLabel = __( 'بانک ملت (به‌پرداخت)', 'bank-mellat-gateway' );
	var label        = settings.title ? decodeEntities( settings.title ) : defaultLabel;

	var Content = function () {
		var description = settings.description ? decodeEntities( settings.description ) : '';
		return el( 'div', { className: 'bmg-blocks-description' }, description );
	};

	var Label = function () {
		var children = [ el( 'span', { key: 'bmg-label-text' }, label ) ];

		if ( settings.icon ) {
			children.push(
				el( 'img', {
					key: 'bmg-label-icon',
					src: settings.icon,
					alt: label,
					width: 40,
					height: 40,
					style: { marginInlineStart: '8px', width: '40px', height: '40px', objectFit: 'contain', verticalAlign: 'middle' },
				} )
			);
		}

		return el(
			'span',
			{ style: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', width: '100%' } },
			children
		);
	};

	registerPaymentMethod( {
		name: 'bank_mellat',
		label: el( Label, null ),
		content: el( Content, null ),
		edit: el( Content, null ),
		canMakePayment: function () {
			return true;
		},
		ariaLabel: label,
		supports: {
			features: settings.supports || [ 'products' ],
		},
	} );
} )( window.wp, window.wc );
