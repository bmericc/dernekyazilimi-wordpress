/**
 * Dernek Yazılımı blocks: the forms are drawn on the server, the editor
 * shows the same markup.
 */
( function ( wp, data ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! data ) {
		return;
	}

	var el = wp.element.createElement;
	var ServerSideRender = wp.serverSideRender;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var Disabled = wp.components.Disabled;

	function register( name, icon, controls ) {
		wp.blocks.registerBlockType( 'dernekyazilimi/' + name, {
			apiVersion: 3,
			title: data[ name ].title,
			description: data[ name ].description,
			category: 'widgets',
			icon: icon,
			keywords: [ 'dernek', 'dernekyazilimi' ],
			edit: function ( props ) {
				return el(
					'div',
					useBlockProps(),
					controls ? el( InspectorControls, null, el( PanelBody, { title: data.labels.settings }, controls( props ) ) ) : null,
					el( Disabled, null, el( ServerSideRender, { block: 'dernekyazilimi/' + name, attributes: props.attributes } ) )
				);
			},
			save: function () {
				return null;
			},
		} );
	}

	register( 'donate', 'heart', function ( props ) {
		return [
			el( TextControl, {
				key: 'cause',
				label: data.labels.cause,
				value: props.attributes.cause,
				onChange: function ( value ) {
					props.setAttributes( { cause: value.replace( /\D/g, '' ) } );
				},
			} ),
			el( TextControl, {
				key: 'amount',
				label: data.labels.amount,
				value: props.attributes.amount,
				onChange: function ( value ) {
					props.setAttributes( { amount: value.replace( /[^\d.]/g, '' ) } );
				},
			} ),
		];
	} );
	register( 'volunteer', 'groups' );
	register( 'membership', 'id' );
}( window.wp, window.dernekyazilimiBlocks ) );
