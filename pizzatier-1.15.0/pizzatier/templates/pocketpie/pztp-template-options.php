<?php
/**
 * PocketPie Template Options
 *
 * Returned as an array of field definitions consumed by the Settings page.
 * Each entry renders a form field inside the "PocketPie Template Settings" card.
 *
 * Supported types: text | text_wide | number | color | select | toggle | textarea | radio | range
 *
 * All option keys are prefixed "pocketpie_setting_" to keep them scoped
 * to this template. They are stored as individual wp_options entries and
 * read at render-time via get_option( $key, $default ).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

return [

    /* ═══════════════════════════════════════════════════════════
       GROUP: Layout & Default Mode
       ═══════════════════════════════════════════════════════════ */

    [
        'key'     => 'pocketpie_setting_default_layout',
        'type'    => 'select',
        'label'   => 'Default Layout Mode',
        'desc'    => 'Which layout PocketPie uses when the shortcode does not specify a layout= attribute.',
        'default' => 'corner-quad',
        'options' => [
            'corner-quad'  => '⬛ Corner Quad — menus in four corners, pizza centred',
            'layer-deck'   => '🍕 Layer Deck — pizza front-and-centre, thumbnail strip',
            'slide-drawer' => '⬆ Slide Drawer — pizza top, category drawer slides up',
            'stack-panel'  => '📋 Stack Panel — mini pizza + step-by-step bottom sheet',
        ],
    ],

    [
        'key'         => 'pocketpie_setting_widget_max_width',
        'type'        => 'text',
        'label'       => 'Widget Max Width',
        'desc'        => 'Maximum width of the PocketPie builder container, e.g. 420px or 100%. Leave blank for full-width.',
        'default'     => '480px',
        'placeholder' => '480px',
    ],

    [
        'key'     => 'pocketpie_setting_pizza_size_cq',
        'type'    => 'range',
        'label'   => 'Corner Quad — Pizza Size (px)',
        'desc'    => 'Diameter of the centered pizza preview in Corner Quad mode.',
        'default' => '300',
        'min'     => '120',
        'max'     => '480',
        'step'    => '4',
    ],

    [
        'key'     => 'pocketpie_setting_pizza_size_ld',
        'type'    => 'range',
        'label'   => 'Layer Deck — Pizza Zone Height (px)',
        'desc'    => 'Height of the pizza zone in Layer Deck mode. Pizza scales inside this space.',
        'default' => '260',
        'min'     => '160',
        'max'     => '420',
        'step'    => '4',
    ],

    [
        'key'     => 'pocketpie_setting_pizza_size_sd',
        'type'    => 'range',
        'label'   => 'Slide Drawer — Pizza Zone Height (px)',
        'desc'    => 'Height of the pizza zone area in Slide Drawer mode.',
        'default' => '240',
        'min'     => '140',
        'max'     => '360',
        'step'    => '4',
    ],

    [
        'key'     => 'pocketpie_setting_pizza_size_sp',
        'type'    => 'range',
        'label'   => 'Stack Panel — Mini Pizza Size (px)',
        'desc'    => 'Diameter of the small pizza in Stack Panel mode.',
        'default' => '90',
        'min'     => '60',
        'max'     => '160',
        'step'    => '2',
    ],

    /* ═══════════════════════════════════════════════════════════
       GROUP: Corner Quad Layout
       ═══════════════════════════════════════════════════════════ */

    [
        'key'     => 'pocketpie_setting_cq_corner_tl',
        'type'    => 'select',
        'label'   => 'Corner Quad — Top-Left Category',
        'desc'    => 'Which pizza category appears in the top-left corner.',
        'default' => 'crust',
        'options' => [
            'crust'    => 'Crust',
            'sauce'    => 'Sauce',
            'cheese'   => 'Cheese',
            'toppings' => 'Toppings',
            'drizzle'  => 'Drizzle',
            'slicing'  => 'Slicing',
        ],
    ],

    [
        'key'     => 'pocketpie_setting_cq_corner_tr',
        'type'    => 'select',
        'label'   => 'Corner Quad — Top-Right Category',
        'desc'    => 'Which pizza category appears in the top-right corner.',
        'default' => 'sauce',
        'options' => [
            'crust'    => 'Crust',
            'sauce'    => 'Sauce',
            'cheese'   => 'Cheese',
            'toppings' => 'Toppings',
            'drizzle'  => 'Drizzle',
            'slicing'  => 'Slicing',
        ],
    ],

    [
        'key'     => 'pocketpie_setting_cq_corner_bl',
        'type'    => 'select',
        'label'   => 'Corner Quad — Bottom-Left Category',
        'desc'    => 'Which pizza category appears in the bottom-left corner.',
        'default' => 'cheese',
        'options' => [
            'crust'    => 'Crust',
            'sauce'    => 'Sauce',
            'cheese'   => 'Cheese',
            'toppings' => 'Toppings',
            'drizzle'  => 'Drizzle',
            'slicing'  => 'Slicing',
        ],
    ],

    [
        'key'     => 'pocketpie_setting_cq_corner_br',
        'type'    => 'select',
        'label'   => 'Corner Quad — Bottom-Right Category',
        'desc'    => 'Which pizza category appears in the bottom-right corner.',
        'default' => 'toppings',
        'options' => [
            'crust'    => 'Crust',
            'sauce'    => 'Sauce',
            'cheese'   => 'Cheese',
            'toppings' => 'Toppings',
            'drizzle'  => 'Drizzle',
            'slicing'  => 'Slicing',
        ],
    ],

    [
        'key'         => 'pocketpie_setting_cq_trigger_size',
        'type'        => 'text',
        'label'       => 'Corner Quad — Trigger Button Size',
        'desc'        => 'Width of the corner trigger buttons, e.g. 72px.',
        'default'     => '72px',
        'placeholder' => '72px',
    ],

    [
        'key'         => 'pocketpie_setting_corner_quad_aspect',
        'type'        => 'text',
        'label'       => 'Corner Quad — Widget Aspect Ratio',
        'desc'        => 'CSS aspect-ratio for the Corner Quad wrapper, e.g. 1 / 1 for square, 4 / 3 for wider. Leave blank for auto.',
        'default'     => '1 / 1',
        'placeholder' => '1 / 1',
    ],

    /* ═══════════════════════════════════════════════════════════
       GROUP: Layer Deck Layout
       ═══════════════════════════════════════════════════════════ */

    [
        'key'     => 'pocketpie_setting_ld_preview_height',
        'type'    => 'range',
        'label'   => 'Layer Deck — Selected Layer Preview Height (px)',
        'desc'    => 'Height of the large layer-image preview area shown when a category is expanded.',
        'default' => '90',
        'min'     => '60',
        'max'     => '200',
        'step'    => '5',
    ],

    [
        'key'         => 'pocketpie_setting_ld_deck_thumb_width',
        'type'        => 'text',
        'label'       => 'Layer Deck — Thumb Strip Min-Width per Item',
        'desc'        => 'Minimum width for each category button in the deck strip, e.g. 60px.',
        'default'     => '60px',
        'placeholder' => '60px',
    ],

    [
        'key'          => 'pocketpie_setting_ld_show_sel_label',
        'type'         => 'toggle',
        'label'        => 'Layer Deck — Show Selected Label in Strip',
        'desc'         => 'Show a small label beneath each deck thumb indicating what was chosen.',
        'default'      => 'yes',
        'toggle_label' => 'Show selected choice beneath thumb',
    ],

    /* ═══════════════════════════════════════════════════════════
       GROUP: Slide Drawer Layout
       ═══════════════════════════════════════════════════════════ */

    [
        'key'     => 'pocketpie_setting_sd_drawer_max_height',
        'type'    => 'range',
        'label'   => 'Slide Drawer — Drawer Max Height (px)',
        'desc'    => 'Maximum height of the slide-up drawer panel.',
        'default' => '320',
        'min'     => '160',
        'max'     => '520',
        'step'    => '10',
    ],

    [
        'key'     => 'pocketpie_setting_sd_pill_position',
        'type'    => 'select',
        'label'   => 'Slide Drawer — Pill Bar Position',
        'desc'    => 'Where the category pill buttons sit relative to the pizza zone.',
        'default' => 'bottom-overlay',
        'options' => [
            'bottom-overlay' => 'Bottom of pizza zone (overlay with gradient)',
            'below-pizza'    => 'Below pizza zone (separate row)',
            'top-overlay'    => 'Top of pizza zone (overlay)',
        ],
    ],

    [
        'key'     => 'pocketpie_setting_sd_pill_style',
        'type'    => 'select',
        'label'   => 'Slide Drawer — Pill Button Style',
        'desc'    => 'Visual style of the category pill buttons.',
        'default' => 'pill',
        'options' => [
            'pill'   => 'Rounded pill',
            'square' => 'Square / chip',
            'icon'   => 'Icon only (compact)',
            'text'   => 'Text only',
        ],
    ],

    [
        'key'          => 'pocketpie_setting_sd_swipe_close',
        'type'         => 'toggle',
        'label'        => 'Slide Drawer — Swipe-Down to Close',
        'desc'         => 'Allow customers to swipe the drawer downward to dismiss it on touch devices.',
        'default'      => 'yes',
        'toggle_label' => 'Enable swipe-down gesture',
    ],

    /* ═══════════════════════════════════════════════════════════
       GROUP: Stack Panel Layout
       ═══════════════════════════════════════════════════════════ */

    [
        'key'     => 'pocketpie_setting_sp_sheet_max_height',
        'type'    => 'range',
        'label'   => 'Stack Panel — Bottom Sheet Max Height (px)',
        'desc'    => 'Maximum height the bottom sheet expands to.',
        'default' => '380',
        'min'     => '200',
        'max'     => '600',
        'step'    => '10',
    ],

    [
        'key'          => 'pocketpie_setting_sp_show_step_dots',
        'type'         => 'toggle',
        'label'        => 'Stack Panel — Show Progress Dots',
        'desc'         => 'Display progress dots alongside the mini pizza to indicate which steps are complete.',
        'default'      => 'yes',
        'toggle_label' => 'Show progress indicator dots',
    ],

    [
        'key'          => 'pocketpie_setting_sp_step_label',
        'type'         => 'toggle',
        'label'        => 'Stack Panel — Show Active Step Label',
        'desc'         => 'Show a text label indicating the currently open step (e.g. "Choose Crust").',
        'default'      => 'yes',
        'toggle_label' => 'Show active step label',
    ],

    [
        'key'          => 'pocketpie_setting_sp_swipe_close',
        'type'         => 'toggle',
        'label'        => 'Stack Panel — Swipe-Down to Close Sheet',
        'desc'         => 'Allow customers to swipe the sheet downward to dismiss it on touch devices.',
        'default'      => 'yes',
        'toggle_label' => 'Enable swipe-down gesture',
    ],

    /* ═══════════════════════════════════════════════════════════
       GROUP: Colour Scheme
       ═══════════════════════════════════════════════════════════ */

    [
        'key'     => 'pocketpie_setting_theme',
        'type'    => 'select',
        'label'   => 'Colour Theme',
        'desc'    => 'Base colour theme for PocketPie. Select "Custom" to set individual colours below.',
        'default' => 'dark-amber',
        'options' => [
            'dark-amber'  => '🌑 Dark Amber (default — warm dark with orange accent)',
            'light-slate' => '☀ Light Slate (clean white with slate accents)',
            'espresso'    => '☕ Espresso (deep brown with cream)',
            'forest'      => '🌿 Forest (dark green with sage)',
            'ocean'       => '🌊 Ocean (deep navy with teal)',
            'rose'        => '🌹 Rose (off-white with rose/coral)',
            'mono-light'  => '▢ Mono Light (pure white and black)',
            'mono-dark'   => '■ Mono Dark (pure black and white)',
            'custom'      => '✦ Custom (use colour pickers below)',
        ],
    ],

    [
        'key'     => 'pocketpie_setting_color_bg',
        'type'    => 'color',
        'label'   => 'Background Colour',
        'desc'    => 'Main widget background. Applied when theme is set to Custom.',
        'default' => '#1a1008',
    ],

    [
        'key'     => 'pocketpie_setting_color_bg2',
        'type'    => 'color',
        'label'   => 'Panel Background Colour',
        'desc'    => 'Background of panels, cards, and drawers.',
        'default' => '#251808',
    ],

    [
        'key'     => 'pocketpie_setting_color_bg3',
        'type'    => 'color',
        'label'   => 'Elevated Surface Colour',
        'desc'    => 'Background for elevated elements like modals and open overlays.',
        'default' => '#2e1e0a',
    ],

    [
        'key'     => 'pocketpie_setting_color_accent',
        'type'    => 'color',
        'label'   => 'Primary Accent Colour',
        'desc'    => 'Main highlight colour — active tabs, selected borders, focus rings.',
        'default' => '#ff9a1a',
    ],

    [
        'key'     => 'pocketpie_setting_color_accent2',
        'type'    => 'color',
        'label'   => 'Secondary Accent Colour',
        'desc'    => 'Used for close buttons, remove actions, and secondary highlights.',
        'default' => '#e05c28',
    ],

    [
        'key'     => 'pocketpie_setting_color_text',
        'type'    => 'color',
        'label'   => 'Primary Text Colour',
        'desc'    => 'Main body text colour inside the widget.',
        'default' => '#f5e8cc',
    ],

    [
        'key'     => 'pocketpie_setting_color_muted',
        'type'    => 'color',
        'label'   => 'Muted / Secondary Text',
        'desc'    => 'Used for descriptions, hints, and inactive labels.',
        'default' => '#a08060',
    ],

    [
        'key'     => 'pocketpie_setting_color_border',
        'type'    => 'color',
        'label'   => 'Border / Divider Colour',
        'desc'    => 'Subtle dividers and card borders throughout the widget.',
        'default' => '#2e1e0a',
    ],

    [
        'key'     => 'pocketpie_setting_color_success',
        'type'    => 'color',
        'label'   => 'Success / Selected Colour',
        'desc'    => 'Used for checkmarks and confirmed selection highlights.',
        'default' => '#5bcf80',
    ],

    /* ═══════════════════════════════════════════════════════════
       GROUP: Typography
       ═══════════════════════════════════════════════════════════ */

    [
        'key'     => 'pocketpie_setting_font_family',
        'type'    => 'select',
        'label'   => 'Font Family',
        'desc'    => 'Font used throughout the PocketPie widget.',
        'default' => 'georgia',
        'options' => [
            'inherit' => 'Inherit from theme',
            'georgia' => 'Georgia (serif — default PocketPie aesthetic)',
            'system'  => 'System UI sans-serif',
            'mono'    => 'Courier New (monospace)',
            'custom'  => 'Custom (enter name below)',
        ],
    ],

    [
        'key'         => 'pocketpie_setting_font_custom',
        'type'        => 'text',
        'label'       => 'Custom Font Name',
        'desc'        => 'Font name when "Custom" is selected above. Must be loaded by your theme or a plugin, e.g. Nunito.',
        'default'     => '',
        'placeholder' => 'Nunito',
    ],

    [
        'key'         => 'pocketpie_setting_font_base_size',
        'type'        => 'text',
        'label'       => 'Base Font Size',
        'desc'        => 'Root font size for the widget, e.g. 14px.',
        'default'     => '14px',
        'placeholder' => '14px',
    ],

    [
        'key'     => 'pocketpie_setting_label_transform',
        'type'    => 'select',
        'label'   => 'Category Label Text Transform',
        'desc'    => 'Text case applied to category/tab labels.',
        'default' => 'none',
        'options' => [
            'none'       => 'None',
            'uppercase'  => 'UPPERCASE',
            'capitalize' => 'Capitalize',
            'lowercase'  => 'lowercase',
        ],
    ],

    /* ═══════════════════════════════════════════════════════════
       GROUP: Chip / Item Card Style
       ═══════════════════════════════════════════════════════════ */

    [
        'key'         => 'pocketpie_setting_chip_thumb_size',
        'type'        => 'text',
        'label'       => 'Chip Thumbnail Size',
        'desc'        => 'Width and height of the thumbnail image inside each selection chip, e.g. 52px.',
        'default'     => '52px',
        'placeholder' => '52px',
    ],

    [
        'key'         => 'pocketpie_setting_chip_radius',
        'type'        => 'text',
        'label'       => 'Chip Border Radius',
        'desc'        => 'Corner rounding for item chips, e.g. 6px or 50% for fully round.',
        'default'     => '6px',
        'placeholder' => '6px',
    ],

    [
        'key'     => 'pocketpie_setting_chip_cols',
        'type'    => 'select',
        'label'   => 'Chip Grid Columns',
        'desc'    => 'Number of columns in the chip grid inside panels, drawers, and sheets.',
        'default' => 'auto',
        'options' => [
            'auto' => 'Auto (fill available space)',
            '2'    => '2 columns',
            '3'    => '3 columns',
            '4'    => '4 columns',
        ],
    ],

    [
        'key'          => 'pocketpie_setting_chip_show_name',
        'type'         => 'toggle',
        'label'        => 'Show Item Names on Chips',
        'desc'         => 'Display the item name label beneath each thumbnail chip.',
        'default'      => 'yes',
        'toggle_label' => 'Show name labels on chips',
    ],

    [
        'key'     => 'pocketpie_setting_toppings_cols',
        'type'    => 'select',
        'label'   => 'Toppings Chip Columns Override',
        'desc'    => 'Column count specifically for the toppings grid. Overrides the general chip column setting for toppings.',
        'default' => '2',
        'options' => [
            'auto' => 'Same as chip grid (auto)',
            '1'    => '1 column (list style)',
            '2'    => '2 columns',
            '3'    => '3 columns',
        ],
    ],

    /* ═══════════════════════════════════════════════════════════
       GROUP: Modal & Overlay
       ═══════════════════════════════════════════════════════════ */

    [
        'key'     => 'pocketpie_setting_modal_backdrop',
        'type'    => 'select',
        'label'   => 'Modal Backdrop Effect',
        'desc'    => 'Visual treatment of the background when the summary or overflow modal is open.',
        'default' => 'blur',
        'options' => [
            'blur' => 'Blur + darken (recommended)',
            'dark' => 'Darken only',
            'none' => 'No backdrop',
        ],
    ],

    [
        'key'     => 'pocketpie_setting_modal_anim',
        'type'    => 'select',
        'label'   => 'Modal Open Animation',
        'desc'    => 'Animation used when the summary modal opens.',
        'default' => 'scale-fade',
        'options' => [
            'scale-fade' => 'Scale + fade (default)',
            'slide-up'   => 'Slide up from bottom',
            'fade'       => 'Fade only',
            'instant'    => 'Instant (no animation)',
        ],
    ],

    [
        'key'          => 'pocketpie_setting_close_on_backdrop',
        'type'         => 'toggle',
        'label'        => 'Close Modal on Backdrop Click',
        'desc'         => 'Dismiss the modal when the customer clicks the dimmed area outside it.',
        'default'      => 'yes',
        'toggle_label' => 'Click backdrop to close modal',
    ],

    /* ═══════════════════════════════════════════════════════════
       GROUP: Summary (Your Pizza) Panel
       ═══════════════════════════════════════════════════════════ */

    [
        'key'         => 'pocketpie_setting_summary_title',
        'type'        => 'text',
        'label'       => 'Summary Modal Title',
        'desc'        => 'Heading shown at the top of the Your Pizza summary modal.',
        'default'     => 'Your Pizza',
        'placeholder' => 'Your Pizza',
    ],





    /* ═══════════════════════════════════════════════════════════
       GROUP: Controls & Buttons
       ═══════════════════════════════════════════════════════════ */

    [
        'key'          => 'pocketpie_setting_show_reset',
        'type'         => 'toggle',
        'label'        => 'Show Reset Button',
        'desc'         => 'Display a reset/clear button on the pizza visualizer area.',
        'default'      => 'yes',
        'toggle_label' => 'Show reset button',
    ],

    [
        'key'          => 'pocketpie_setting_show_review_btn',
        'type'         => 'toggle',
        'label'        => 'Show "Review" Summary Button',
        'desc'         => 'Show a button in the deck/pill/step bar that opens the Your Pizza summary modal.',
        'default'      => 'yes',
        'toggle_label' => 'Show review/summary shortcut button',
    ],

    [
        'key'         => 'pocketpie_setting_review_btn_label',
        'type'        => 'text',
        'label'       => '"Review" Button Label',
        'desc'        => 'Custom label for the summary/review button in the category navigation strip.',
        'default'     => 'Review',
        'placeholder' => 'Review',
    ],

    /* ═══════════════════════════════════════════════════════════
       GROUP: Animations & Visual Effects
       ═══════════════════════════════════════════════════════════ */

    [
        'key'     => 'pocketpie_setting_transition_speed',
        'type'    => 'select',
        'label'   => 'UI Transition Speed',
        'desc'    => 'Speed of drawer, sheet, and panel open/close animations.',
        'default' => 'normal',
        'options' => [
            'fast'    => 'Fast (180ms)',
            'normal'  => 'Normal (320ms, default)',
            'slow'    => 'Slow (500ms)',
            'instant' => 'Instant (no animation)',
        ],
    ],

    [
        'key'          => 'pocketpie_setting_chip_hover_anim',
        'type'         => 'toggle',
        'label'        => 'Chip Hover Lift Effect',
        'desc'         => 'Apply a subtle translateY lift animation when hovering over item chips.',
        'default'      => 'yes',
        'toggle_label' => 'Enable hover lift on chips',
    ],

];
