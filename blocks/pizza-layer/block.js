/**
 * PizzaTier — PizzaTier Image Block
 *
 * Pure vanilla JS / wp.element / wp.components — no JSX, no NPM required.
 * Renders server-side via BlockRegistrar::render_layer().
 */
( function ( blocks, element, components, blockEditor, i18n ) {
    'use strict';

    var el          = element.createElement;
    var __          = i18n.__;

    var registerBlockType = blocks.registerBlockType;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps     = blockEditor.useBlockProps;
    var ServerSideRender  = wp.serverSideRender;

    var PanelBody     = components.PanelBody;
    var TextControl   = components.TextControl;
    var SelectControl = components.SelectControl;
    var Spinner       = components.Spinner;
    var Placeholder   = components.Placeholder;

    var LAYER_TYPES = [
        { value: 'crust',   label: __( 'Crust',   'pizzatier' ) },
        { value: 'sauce',   label: __( 'Sauce',   'pizzatier' ) },
        { value: 'cheese',  label: __( 'Cheese',  'pizzatier' ) },
        { value: 'topping', label: __( 'Topping', 'pizzatier' ) },
        { value: 'drizzle', label: __( 'Drizzle', 'pizzatier' ) },
        { value: 'cut',     label: __( 'Cut',     'pizzatier' ) },
    ];

    var IMAGE_FIELDS = [
        { value: 'list',  label: __( 'List image (menu/product photo)', 'pizzatier' ) },
        { value: 'layer', label: __( 'Layer image (transparent stack image)', 'pizzatier' ) },
    ];

    registerBlockType( 'pizzatier/pizza-layer', {

        edit: function ( props ) {
            var attributes    = props.attributes;
            var setAttributes = props.setAttributes;

            var blockProps = useBlockProps( {
                className: 'pizzatier-layer-block-wrap',
                style: { minHeight: '60px' }
            } );

            var inspectorPanel = el(
                InspectorControls,
                null,
                el( PanelBody, { title: __( 'Layer Settings', 'pizzatier' ), initialOpen: true },

                    el( SelectControl, {
                        label:    __( 'Layer type', 'pizzatier' ),
                        value:    attributes.layerType,
                        options:  LAYER_TYPES,
                        onChange: function ( v ) { setAttributes( { layerType: v } ); }
                    } ),

                    el( TextControl, {
                        label: __( 'Slug', 'pizzatier' ),
                        help:  __( 'The post slug of the layer entry, e.g. "thin-crust" or "pepperoni".', 'pizzatier' ),
                        value: attributes.slug,
                        onChange: function ( v ) { setAttributes( { slug: v } ); }
                    } ),

                    el( SelectControl, {
                        label:    __( 'Image field', 'pizzatier' ),
                        value:    attributes.imageField,
                        options:  IMAGE_FIELDS,
                        onChange: function ( v ) { setAttributes( { imageField: v } ); }
                    } ),

                    el( TextControl, {
                        label: __( 'Extra CSS class', 'pizzatier' ),
                        help:  __( 'Optional CSS class(es) added to the <img> tag.', 'pizzatier' ),
                        value: attributes.cssClass,
                        onChange: function ( v ) { setAttributes( { cssClass: v } ); }
                    } )
                )
            );

            var preview;
            if ( attributes.slug && attributes.slug.trim() !== '' ) {
                preview = el( ServerSideRender, {
                    block:      'pizzatier/pizza-layer',
                    attributes: attributes,
                    EmptyResponsePlaceholder: function () {
                        return el( Placeholder, { icon: 'format-image', label: __( 'PizzaTier', 'pizzatier' ) },
                            el( Spinner )
                        );
                    },
                    ErrorResponsePlaceholder: function ( p ) {
                        return el( Placeholder, { icon: 'warning', label: __( 'PizzaTier — Error', 'pizzatier' ) },
                            el( 'p', null, p.response && p.response.message
                                ? p.response.message
                                : __( 'Layer not found. Check the type and slug.', 'pizzatier' ) )
                        );
                    }
                } );
            } else {
                /* No slug yet — show a helpful placeholder */
                var typeLabel = ( LAYER_TYPES.find( function ( t ) { return t.value === attributes.layerType; } ) || LAYER_TYPES[0] ).label;
                preview = el( Placeholder, {
                    icon:  'format-image',
                    label: __( 'PizzaTier Image', 'pizzatier' ),
                }, el( 'p', { style: { margin: 0 } },
                    /* translators: %s = layer type e.g. "Crust" */
                    __( 'Enter a %s slug in the settings panel →', 'pizzatier' ).replace( '%s', typeLabel )
                ) );
            }

            return el( 'div', blockProps, inspectorPanel, preview );
        },

        save: function () {
            return null;
        }
    } );

} )(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
    window.wp.blockEditor,
    window.wp.i18n
);
