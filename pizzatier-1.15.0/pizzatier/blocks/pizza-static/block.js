/**
 * PizzaTier — Pizza Static Block
 *
 * Pure vanilla JS / wp.element / wp.components — no JSX, no NPM required.
 * Renders server-side via BlockRegistrar::render_static().
 */
( function ( blocks, element, components, blockEditor, i18n ) {
    'use strict';

    var el          = element.createElement;
    var __          = i18n.__;

    var registerBlockType = blocks.registerBlockType;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps     = blockEditor.useBlockProps;
    var ServerSideRender  = wp.serverSideRender;

    var PanelBody   = components.PanelBody;
    var TextControl = components.TextControl;
    var Spinner     = components.Spinner;
    var Placeholder = components.Placeholder;
    var Notice      = components.Notice;

    registerBlockType( 'pizzatier/pizza-static', {

        edit: function ( props ) {
            var attributes    = props.attributes;
            var setAttributes = props.setAttributes;

            /* Detect whether using preset or manual mode */
            var usingPreset = attributes.preset && attributes.preset.trim() !== '';

            var blockProps = useBlockProps( {
                className: 'pizzatier-static-block-wrap',
                style: { minHeight: '80px' }
            } );

            var inspectorPanel = element.createElement(
                InspectorControls,
                null,

                /* ── Preset mode ── */
                el( PanelBody, { title: __( 'Preset', 'pizzatier' ), initialOpen: true },

                    el( TextControl, {
                        label: __( 'Preset slug', 'pizzatier' ),
                        help:  __( 'Enter a Pizza Preset slug to load all layers automatically. Leave blank to set layers manually below.', 'pizzatier' ),
                        value: attributes.preset,
                        onChange: function ( v ) { setAttributes( { preset: v } ); }
                    } )
                ),

                /* ── Manual layer slugs ── */
                el( PanelBody, {
                    title: __( 'Manual Layers', 'pizzatier' ),
                    initialOpen: ! usingPreset
                },
                    usingPreset
                        ? el( Notice, {
                            status: 'info',
                            isDismissible: false,
                            style: { margin: '0 0 8px' }
                          }, __( 'Preset is active — manual layer slugs are ignored.', 'pizzatier' ) )
                        : null,

                    el( TextControl, {
                        label:    __( 'Crust slug', 'pizzatier' ),
                        help:     __( 'e.g. "thin-crust"', 'pizzatier' ),
                        value:    attributes.crust,
                        disabled: usingPreset,
                        onChange: function ( v ) { setAttributes( { crust: v } ); }
                    } ),

                    el( TextControl, {
                        label:    __( 'Sauce slug', 'pizzatier' ),
                        value:    attributes.sauce,
                        disabled: usingPreset,
                        onChange: function ( v ) { setAttributes( { sauce: v } ); }
                    } ),

                    el( TextControl, {
                        label:    __( 'Cheese slug', 'pizzatier' ),
                        value:    attributes.cheese,
                        disabled: usingPreset,
                        onChange: function ( v ) { setAttributes( { cheese: v } ); }
                    } ),

                    el( TextControl, {
                        label:    __( 'Toppings', 'pizzatier' ),
                        help:     __( 'Comma-separated topping slugs, e.g. "pepperoni,mushrooms"', 'pizzatier' ),
                        value:    attributes.toppings,
                        disabled: usingPreset,
                        onChange: function ( v ) { setAttributes( { toppings: v } ); }
                    } ),

                    el( TextControl, {
                        label:    __( 'Drizzle slug', 'pizzatier' ),
                        value:    attributes.drizzle,
                        disabled: usingPreset,
                        onChange: function ( v ) { setAttributes( { drizzle: v } ); }
                    } ),

                    el( TextControl, {
                        label:    __( 'Cut slug', 'pizzatier' ),
                        help:     __( 'e.g. "8-slices"', 'pizzatier' ),
                        value:    attributes.cut,
                        disabled: usingPreset,
                        onChange: function ( v ) { setAttributes( { cut: v } ); }
                    } )
                )
            );

            /* Determine whether any attribute is set to show a preview */
            var hasAnyAttr = usingPreset
                || attributes.crust || attributes.sauce || attributes.cheese
                || attributes.toppings || attributes.drizzle || attributes.cut;

            var preview;
            if ( hasAnyAttr ) {
                preview = el( ServerSideRender, {
                    block:      'pizzatier/pizza-static',
                    attributes: attributes,
                    EmptyResponsePlaceholder: function () {
                        return el( Placeholder, { icon: 'food', label: __( 'Pizza Static', 'pizzatier' ) },
                            el( Spinner )
                        );
                    },
                    ErrorResponsePlaceholder: function ( p ) {
                        return el( Placeholder, { icon: 'warning', label: __( 'Pizza Static — Error', 'pizzatier' ) },
                            el( 'p', null, p.response && p.response.message
                                ? p.response.message
                                : __( 'Could not render preview.', 'pizzatier' ) )
                        );
                    }
                } );
            } else {
                preview = el( Placeholder, {
                    icon:  'food',
                    label: __( 'Pizza Static', 'pizzatier' ),
                }, el( 'p', { style: { margin: 0 } },
                    __( 'Enter a preset slug or layer slugs in the block settings panel →', 'pizzatier' )
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
