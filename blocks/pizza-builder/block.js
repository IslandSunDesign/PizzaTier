/**
 * PizzaTier — Pizza Builder Block
 *
 * Pure vanilla JS / wp.element / wp.components — no JSX, no NPM required.
 * Registered via block.json; rendered server-side by BlockRegistrar::render_builder().
 */
( function ( blocks, element, components, blockEditor, i18n ) {
    'use strict';

    var el               = element.createElement;
    var __               = i18n.__;
    var registerBlockType = blocks.registerBlockType;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps     = blockEditor.useBlockProps;
    var ServerSideRender  = wp.serverSideRender;

    var PanelBody       = components.PanelBody;
    var TextControl     = components.TextControl;
    var SelectControl   = components.SelectControl;
    var CheckboxControl = components.CheckboxControl;
    var Spinner         = components.Spinner;
    var Placeholder     = components.Placeholder;

    /* ── Shape options ─────────────────────────────────────────── */
    var SHAPE_OPTIONS = [
        { value: '',          label: __( '— Site default —',                'pizzatier' ) },
        { value: 'round',     label: __( 'Round (circle)',                   'pizzatier' ) },
        { value: 'square',    label: __( 'Square (rounded corners)',         'pizzatier' ) },
        { value: 'rectangle', label: __( 'Rectangle / custom ratio',        'pizzatier' ) },
        { value: 'custom',    label: __( 'Custom (aspect ratio + radius)',   'pizzatier' ) },
    ];

    /* ── Animation options ─────────────────────────────────────── */
    var ANIM_OPTIONS = [
        { value: '',         label: __( '— Site default —',      'pizzatier' ) },
        { value: 'fade',     label: __( 'Fade In',               'pizzatier' ) },
        { value: 'scale-in', label: __( 'Scale In (pop)',        'pizzatier' ) },
        { value: 'slide-up', label: __( 'Slide Up',              'pizzatier' ) },
        { value: 'flip-in',  label: __( 'Flip In (3-D rotate)',  'pizzatier' ) },
        { value: 'drop-in',  label: __( 'Drop In (from above)',  'pizzatier' ) },
        { value: 'instant',  label: __( 'Instant (no animation)','pizzatier' ) },
    ];

    /* ── Tab options ───────────────────────────────────────────── */
    var ALL_TABS = [
        { value: 'crust',     label: __( 'Crust',     'pizzatier' ) },
        { value: 'sauce',     label: __( 'Sauce',     'pizzatier' ) },
        { value: 'cheese',    label: __( 'Cheese',    'pizzatier' ) },
        { value: 'toppings',  label: __( 'Toppings',  'pizzatier' ) },
        { value: 'drizzle',   label: __( 'Drizzle',   'pizzatier' ) },
        { value: 'slicing',   label: __( 'Slicing',   'pizzatier' ) },
        { value: 'yourpizza', label: __( 'Your Pizza','pizzatier' ) },
    ];

    /* ── Helpers: comma list ↔ Set ─────────────────────────────── */
    function listToSet( str ) {
        return new Set( ( str || '' ).split( ',' ).map( function ( s ) { return s.trim(); } ).filter( Boolean ) );
    }
    function setToList( set ) {
        return Array.from( set ).join( ',' );
    }

    /* ══════════════════════════════════════════════════════════════
       BLOCK REGISTRATION
       ══════════════════════════════════════════════════════════════ */
    registerBlockType( 'pizzatier/pizza-builder', {

        edit: function ( props ) {
            var attributes    = props.attributes;
            var setAttributes = props.setAttributes;

            var blockProps = useBlockProps( {
                className: 'pizzatier-block-wrap',
                style: { minHeight: '120px' }
            } );

            /* Hidden-tabs checkbox state */
            var hiddenSet = listToSet( attributes.hideTabs );
            function toggleTab( tabValue, checked ) {
                var next = new Set( hiddenSet );
                if ( checked ) { next.delete( tabValue ); } else { next.add( tabValue ); }
                setAttributes( { hideTabs: setToList( next ) } );
            }

            var currentShape = attributes.pizzaShape || '';

            /* ── Inspector panels ───────────────────────────────── */
            var inspectorPanel = el( InspectorControls, null,

                /* 1. Builder Settings */
                el( PanelBody, { title: __( 'Builder Settings', 'pizzatier' ), initialOpen: true },

                    el( TextControl, {
                        label:    __( 'Instance ID', 'pizzatier' ),
                        help:     __( 'Leave blank to auto-generate. Set explicitly when placing two builders on the same page.', 'pizzatier' ),
                        value:    attributes.instanceId,
                        onChange: function ( v ) { setAttributes( { instanceId: v } ); }
                    } ),

                    el( TextControl, {
                        label:    __( 'Template slug', 'pizzatier' ),
                        help:     __( 'Override the active template for this block only, e.g. "nightpie".', 'pizzatier' ),
                        value:    attributes.template,
                        onChange: function ( v ) { setAttributes( { template: v } ); }
                    } ),

                    el( TextControl, {
                        label:    __( 'Max toppings', 'pizzatier' ),
                        help:     __( 'Override the global max toppings limit for this builder.', 'pizzatier' ),
                        type:     'number',
                        value:    attributes.maxToppings,
                        onChange: function ( v ) { setAttributes( { maxToppings: v } ); }
                    } )
                ),

                /* 2. Pizza Shape */
                el( PanelBody, { title: __( 'Pizza Shape', 'pizzatier' ), initialOpen: false },

                    el( SelectControl, {
                        label:    __( 'Shape', 'pizzatier' ),
                        help:     __( 'Overrides the site-wide shape for this block only.', 'pizzatier' ),
                        value:    attributes.pizzaShape,
                        options:  SHAPE_OPTIONS,
                        onChange: function ( v ) { setAttributes( { pizzaShape: v } ); }
                    } ),

                    ( currentShape === 'rectangle' || currentShape === 'custom' )
                        ? el( TextControl, {
                            label:    __( 'Aspect ratio', 'pizzatier' ),
                            help:     __( 'CSS aspect-ratio value, e.g. "4 / 3" or "16 / 9".', 'pizzatier' ),
                            value:    attributes.pizzaAspect,
                            onChange: function ( v ) { setAttributes( { pizzaAspect: v } ); }
                          } )
                        : null,

                    currentShape === 'custom'
                        ? el( TextControl, {
                            label:    __( 'Border radius', 'pizzatier' ),
                            help:     __( 'CSS border-radius, e.g. "12px" or "50%".', 'pizzatier' ),
                            value:    attributes.pizzaRadius,
                            onChange: function ( v ) { setAttributes( { pizzaRadius: v } ); }
                          } )
                        : null
                ),

                /* 3. Layer Animation */
                el( PanelBody, { title: __( 'Layer Animation', 'pizzatier' ), initialOpen: false },

                    el( SelectControl, {
                        label:    __( 'Animation style', 'pizzatier' ),
                        help:     __( 'Animation when a layer is added to the pizza. Overrides the site-wide setting for this block.', 'pizzatier' ),
                        value:    attributes.layerAnim,
                        options:  ANIM_OPTIONS,
                        onChange: function ( v ) { setAttributes( { layerAnim: v } ); }
                    } )
                ),

                /* 4. Default Layers */
                el( PanelBody, { title: __( 'Default Layers', 'pizzatier' ), initialOpen: false },

                    el( TextControl, {
                        label:    __( 'Default crust slug', 'pizzatier' ),
                        help:     __( 'e.g. "thin-crust" — pre-selects on load.', 'pizzatier' ),
                        value:    attributes.defaultCrust,
                        onChange: function ( v ) { setAttributes( { defaultCrust: v } ); }
                    } ),

                    el( TextControl, {
                        label:    __( 'Default sauce slug', 'pizzatier' ),
                        value:    attributes.defaultSauce,
                        onChange: function ( v ) { setAttributes( { defaultSauce: v } ); }
                    } ),

                    el( TextControl, {
                        label:    __( 'Default cheese slug', 'pizzatier' ),
                        value:    attributes.defaultCheese,
                        onChange: function ( v ) { setAttributes( { defaultCheese: v } ); }
                    } )
                ),

                /* 5. Visible Tabs */
                el( PanelBody, { title: __( 'Visible Tabs', 'pizzatier' ), initialOpen: false },

                    el( 'p', { style: { margin: '0 0 8px', fontSize: '12px', color: '#757575' } },
                        __( 'Uncheck tabs to hide them from the builder.', 'pizzatier' )
                    ),

                    ALL_TABS.map( function ( tab ) {
                        return el( CheckboxControl, {
                            key:      tab.value,
                            label:    tab.label,
                            checked:  ! hiddenSet.has( tab.value ),
                            onChange: function ( checked ) { toggleTab( tab.value, checked ); }
                        } );
                    } )
                )
            );

            /* ── Server-side live preview ──────────────────────── */
            /* render_builder() returns a branded static preview via REST  */
            return el( 'div', blockProps,
                inspectorPanel,
                el( ServerSideRender, {
                    block:      'pizzatier/pizza-builder',
                    attributes: attributes,
                    LoadingResponsePlaceholder: function () {
                        /* Shown while the REST request is in flight */
                        return el( 'div', {
                            style: {
                                background: 'linear-gradient(135deg,#1a1a2e 0%,#2d1b0e 100%)',
                                border: '2px solid #ff6b35',
                                borderRadius: '6px',
                                padding: '14px 16px',
                                display: 'flex',
                                alignItems: 'center',
                                gap: '10px',
                                minHeight: '80px'
                            }
                        },
                            el( Spinner, { style: { margin: 0 } } ),
                            el( 'span', { style: { color: '#ff8c42', fontSize: '13px' } },
                                __( 'Loading PizzaTier preview…', 'pizzatier' )
                            )
                        );
                    },
                    EmptyResponsePlaceholder: function () {
                        return el( Placeholder, {
                            icon:  el( 'svg', {
                                xmlns: 'http://www.w3.org/2000/svg',
                                viewBox: '0 0 20 20',
                                width: '24', height: '24', fill: '#ff6b35'
                            },
                                el( 'path', { d: 'M10 1C5.03 1 1 5.03 1 10s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9zM10 2.6c3.37 0 6.27 2.08 7.52 5.06L10 10.1 2.48 7.66C3.73 4.68 6.63 2.6 10 2.6zM2.6 10c0-.38.03-.75.09-1.11L10 11.7l7.31-2.81c.06.36.09.73.09 1.11 0 4.08-3.32 7.4-7.4 7.4S2.6 14.08 2.6 10z' } )
                            ),
                            label: __( 'Pizza Builder', 'pizzatier' ),
                            instructions: __( 'Configure this block using the sidebar panels. The builder will appear on the front end.', 'pizzatier' )
                        } );
                    },
                    ErrorResponsePlaceholder: function ( p ) {
                        return el( Placeholder, {
                            icon:  'warning',
                            label: __( 'Pizza Builder — Preview Error', 'pizzatier' ),
                        }, el( 'p', null,
                            p.response && p.response.message
                                ? p.response.message
                                : __( 'Could not render preview.', 'pizzatier' )
                        ) );
                    }
                } )
            );
        },

        save: function () {
            /* Dynamic block — rendered server-side */
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
