/* global window */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';
	var el = element.createElement;
	var Fragment = element.Fragment;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;

	blocks.registerBlockType( 'onepaycx/button', {
		apiVersion: 2,
		title: __( '1pay.cx Pay Button', '1pay-cx-payments' ),
		description: __( 'A "Pay by card or crypto" button that opens your 1pay.cx checkout.', '1pay-cx-payments' ),
		icon: 'money-alt',
		category: 'widgets',
		attributes: {
			amount: { type: 'string', default: '' },
			label: { type: 'string', default: 'Pay by card or crypto' },
			reference: { type: 'string', default: '' },
		},
		edit: function ( props ) {
			var a = props.attributes;
			var set = function ( key ) {
				return function ( value ) {
					var patch = {};
					patch[ key ] = value;
					props.setAttributes( patch );
				};
			};
			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Pay button', '1pay-cx-payments' ) },
						el( TextControl, {
							label: __( 'Amount (leave blank so the buyer enters it)', '1pay-cx-payments' ),
							value: a.amount,
							onChange: set( 'amount' ),
						} ),
						el( TextControl, {
							label: __( 'Button label', '1pay-cx-payments' ),
							value: a.label,
							onChange: set( 'label' ),
						} ),
						el( TextControl, {
							label: __( 'Reference (optional)', '1pay-cx-payments' ),
							value: a.reference,
							onChange: set( 'reference' ),
						} )
					)
				),
				el(
					'button',
					{ className: 'onepaycx-btn', type: 'button', disabled: true },
					a.label || __( 'Pay by card or crypto', '1pay-cx-payments' )
				)
			);
		},
		save: function () {
			return null; // dynamic — rendered server-side
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n );
