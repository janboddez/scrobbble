( function ( blocks, element, blockEditor, components, i18n ) {
	const el            = element.createElement;
	const interpolate   = element.createInterpolateElement;
	const useBlockProps = blockEditor.useBlockProps;
	const TextControl   = components.TextControl;
	const __            = i18n.__;
	const sprintf       = i18n.sprintf;

	blocks.registerBlockType( 'scrobbble/now-playing', {
		edit: ( props ) => {
			const title = props.attributes.title ?? '';
			const url   = props.attributes.url ?? '';

			return el( 'div', useBlockProps(),
				el( blockEditor.BlockControls ),
				el( blockEditor.InspectorControls, { key: 'inspector' },
					el( components.PanelBody, {
							title: __( 'Heading' ),
							initialOpen: true,
						},
						el( TextControl, {
							label: __( 'Title', 'indieblocks' ),
							value: title,
							onChange: ( value ) => { props.setAttributes( { title: value } ) },
						} ),
						el( TextControl, {
							label: __( '“About” URL', 'indieblocks' ),
							value: url,
							onChange: ( value ) => { props.setAttributes( { url: value } ) },
						} ),
					)
				),
				el( 'div', {},
					'' !== url
						? el( 'small', {},
							interpolate( sprintf( '<span>%s</span> <a><abbr>[?]</abbr></a>', ( '' !== title ? title : __( 'Now Playing', 'scrobbble' ) ) ), {
								span: el( 'span' ),
								a: el( 'a', { href: url, target: '_blank', rel: 'noopener noreferrer' } ),
								abbr: el( 'abbr', { title: __( 'What is this?', 'scrobbble' ) } ),
							} )
						)
						: el( 'small', {},
							el( 'span', {}, ( '' !== title ? title : __( 'Now Playing', 'scrobbble' ) ) )
						),
					el( 'span' , {},
						interpolate( __( 'Title – Artist <span>(Album)</span>', 'scrobbble' ), {
							span: el( 'span', { className: 'screen-reader-text' } ),
						} )
					)
				)
			);
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.i18n );
