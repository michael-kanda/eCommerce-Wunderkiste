/**
 * WooCommerce Cart Info Box – Block Editor Script
 *
 * Vanilla JS (kein Build-Step). Nutzt globale wp.* APIs und
 * ServerSideRender für eine Live-Preview im Block-Editor.
 */
( function( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element ) {
		return;
	}

	var el               = wp.element.createElement;
	var registerBlock    = wp.blocks.registerBlockType;
	var ServerSideRender = wp.serverSideRender;
	var useBlockProps    = wp.blockEditor && wp.blockEditor.useBlockProps;
	var __               = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function( s ) { return s; };

	registerBlock( 'woo-cart-info-box/info-box', {
		edit: function() {
			var blockProps = useBlockProps ? useBlockProps() : {};

			var preview;
			if ( ServerSideRender ) {
				preview = el( ServerSideRender, {
					block: 'woo-cart-info-box/info-box',
					EmptyResponsePlaceholder: function() {
						return el(
							'div',
							{
								style: {
									padding: '1em',
									border: '1px dashed #c3c4c7',
									background: '#f6f7f7',
									color: '#50575e',
									textAlign: 'center',
									borderRadius: '4px'
								}
							},
							__( 'Warenkorb Info-Box ist deaktiviert oder leer. Konfiguriere sie unter WooCommerce → Warenkorb Info-Box.', 'ecommerce-wunderkiste' )
						);
					}
				} );
			} else {
				preview = el(
					'div',
					{
						style: {
							padding: '1em',
							border: '1px dashed #c3c4c7',
							background: '#f6f7f7',
							color: '#50575e',
							textAlign: 'center',
							borderRadius: '4px'
						}
					},
					__( 'Warenkorb Info-Box (Vorschau im Frontend sichtbar)', 'ecommerce-wunderkiste' )
				);
			}

			return el( 'div', blockProps, preview );
		},
		save: function() {
			// Dynamischer Block – Ausgabe erfolgt server-seitig.
			return null;
		}
	} );
} )( window.wp );
