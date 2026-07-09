<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Fornaia template settings.
 * Returned as an array of field definitions consumed by Settings.php.
 * All keys use the prefix "rustic_setting_" to avoid collisions.
 *
 * Supported types: color, select, toggle, number, range, text, text_wide, textarea, radio
 */
return [

    /* ── Colour Palette ──────────────────────────────────────── */
    [
        'key'     => 'rustic_setting_bg_color',
        'type'    => 'color',
        'label'   => 'Page Background',
        'desc'    => 'Main parchment background of the whole builder.',
        'default' => '#f5ede0',
    ],
    [
        'key'     => 'rustic_setting_surface_color',
        'type'    => 'color',
        'label'   => 'Panel Surface',
        'desc'    => 'Background of the right-side builder column.',
        'default' => '#faf4eb',
    ],
    [
        'key'     => 'rustic_setting_card_bg_color',
        'type'    => 'color',
        'label'   => 'Card Background',
        'desc'    => 'Fill colour of ingredient selection cards.',
        'default' => '#f0e5d0',
    ],
    [
        'key'     => 'rustic_setting_accent_color',
        'type'    => 'color',
        'label'   => 'Accent / Terracotta',
        'desc'    => 'Primary action colour used for buttons, borders, and highlights.',
        'default' => '#b34a00',
    ],
    [
        'key'     => 'rustic_setting_gold_color',
        'type'    => 'color',
        'label'   => 'Badge Gold',
        'desc'    => 'Colour of the vintage oval badge and step-number accents.',
        'default' => '#8b6914',
    ],
    [
        'key'     => 'rustic_setting_text_color',
        'type'    => 'color',
        'label'   => 'Body Text',
        'desc'    => 'Primary text colour for headings and card names.',
        'default' => '#2c1a05',
    ],
    [
        'key'     => 'rustic_setting_muted_text_color',
        'type'    => 'color',
        'label'   => 'Muted Text',
        'desc'    => 'Secondary text colour for hints, labels, and step descriptions.',
        'default' => '#7a5c34',
    ],

    /* ── Typography ──────────────────────────────────────────── */
    [
        'key'     => 'rustic_setting_font_serif',
        'type'    => 'select',
        'label'   => 'Serif / Heading Font',
        'desc'    => 'Font used for panel titles, card names, badge text, and order sheet.',
        'default' => 'Georgia',
        'options' => [
            'Georgia'           => 'Georgia (default)',
            'Palatino Linotype' => 'Palatino',
            'Book Antiqua'      => 'Book Antiqua',
            'Times New Roman'   => 'Times New Roman',
            'Garamond'          => 'Garamond',
        ],
    ],
    [
        'key'     => 'rustic_setting_font_size',
        'type'    => 'select',
        'label'   => 'Base Font Size',
        'desc'    => 'Overall text size for the builder.',
        'default' => '15px',
        'options' => [
            '13px' => 'Small (13px)',
            '14px' => 'Medium-small (14px)',
            '15px' => 'Medium (15px) — default',
            '16px' => 'Large (16px)',
            '17px' => 'Extra-large (17px)',
        ],
    ],

    /* ── Layout ──────────────────────────────────────────────── */
    [
        'key'     => 'rustic_setting_preview_col_width',
        'type'    => 'range',
        'label'   => 'Preview Column Width (px)',
        'desc'    => 'Width of the sticky left pizza-preview column on desktop.',
        'default' => '300',
        'min'     => '220',
        'max'     => '420',
        'step'    => '10',
    ],
    [
        'key'     => 'rustic_setting_pizza_canvas_size',
        'type'    => 'range',
        'label'   => 'Pizza Canvas Diameter (px)',
        'desc'    => 'Maximum size of the circular pizza preview.',
        'default' => '250',
        'min'     => '160',
        'max'     => '360',
        'step'    => '10',
    ],
    [
        'key'     => 'rustic_setting_card_radius',
        'type'    => 'select',
        'label'   => 'Card Corner Radius',
        'desc'    => 'How rounded the ingredient cards appear.',
        'default' => '8px',
        'options' => [
            '0px'  => 'Square (0px)',
            '4px'  => 'Slightly rounded (4px)',
            '8px'  => 'Standard (8px) — default',
            '12px' => 'Rounded (12px)',
            '16px' => 'Very rounded (16px)',
        ],
    ],
    [
        'key'     => 'rustic_setting_cards_per_row',
        'type'    => 'select',
        'label'   => 'Cards Per Row (desktop)',
        'desc'    => 'Minimum card width used to calculate the grid — more columns = narrower cards.',
        'default' => '150',
        'options' => [
            '200' => '2–3 columns (wider cards)',
            '150' => '3–4 columns (default)',
            '120' => '4–5 columns (narrower cards)',
        ],
    ],

    /* ── Step Nav ─────────────────────────────────────────────── */
    [
        'key'          => 'rustic_setting_show_step_labels',
        'type'         => 'toggle',
        'label'        => 'Show Step Labels',
        'desc'         => 'Show the text label beneath each step number in the nav bar.',
        'default'      => 'yes',
        'toggle_label' => 'Show labels',
    ],
    [
        'key'          => 'rustic_setting_show_step_icons',
        'type'         => 'toggle',
        'label'        => 'Show Step Icons',
        'desc'         => 'Show the FontAwesome icon in the step nav (between number and label).',
        'default'      => 'yes',
        'toggle_label' => 'Show icons',
    ],
    [
        'key'     => 'rustic_setting_stepnav_bg',
        'type'    => 'color',
        'label'   => 'Step Nav Background',
        'desc'    => 'Background colour of the step navigation bar.',
        'default' => '#ede0cc',
    ],
    [
        'key'     => 'rustic_setting_stepnav_active_color',
        'type'    => 'color',
        'label'   => 'Active Step Text Color',
        'desc'    => 'Text/icon colour of the currently active step.',
        'default' => '#b34a00',
    ],

    /* ── Pizza Preview Panel ─────────────────────────────────── */
    [
        'key'     => 'rustic_setting_preview_bg',
        'type'    => 'color',
        'label'   => 'Preview Column Background',
        'desc'    => 'Solid background of the left pizza-preview panel (leave blank for default gradient).',
        'default' => '',
    ],
    [
        'key'          => 'rustic_setting_show_badge',
        'type'         => 'toggle',
        'label'        => 'Show Vintage Badge',
        'desc'         => 'Display the decorative oval badge above the pizza preview.',
        'default'      => 'yes',
        'toggle_label' => 'Show badge',
    ],
    [
        'key'     => 'rustic_setting_badge_top_text',
        'type'    => 'text',
        'label'   => 'Badge Top Text',
        'desc'    => 'Small text above the badge main word.',
        'default' => 'Your',
    ],
    [
        'key'     => 'rustic_setting_badge_main_text',
        'type'    => 'text',
        'label'   => 'Badge Main Word',
        'desc'    => 'Large italic word in the centre of the badge.',
        'default' => 'Pizza',
    ],
    [
        'key'     => 'rustic_setting_badge_bottom_text',
        'type'    => 'text',
        'label'   => 'Badge Tagline',
        'desc'    => 'Small text below the main word.',
        'default' => 'Artisanale',
    ],
    [
        'key'     => 'rustic_setting_pizza_canvas_bg',
        'type'    => 'color',
        'label'   => 'Pizza Canvas Base Colour',
        'desc'    => 'Centre highlight of the pizza dough background (visible before layers load).',
        'default' => '#d4a574',
    ],
    [
        'key'          => 'rustic_setting_show_grain_texture',
        'type'         => 'toggle',
        'label'        => 'Paper Grain Texture',
        'desc'         => 'Overlay a subtle CSS noise texture to simulate aged paper.',
        'default'      => 'yes',
        'toggle_label' => 'Enable texture',
    ],
    [
        'key'          => 'rustic_setting_show_wood_grain',
        'type'         => 'toggle',
        'label'        => 'Wood Grain Stripes',
        'desc'         => 'Show faint vertical grain lines in the preview column.',
        'default'      => 'yes',
        'toggle_label' => 'Enable stripes',
    ],

    /* ── Buttons ─────────────────────────────────────────────── */
    [
        'key'     => 'rustic_setting_btn_style',
        'type'    => 'radio',
        'label'   => 'Button Style',
        'desc'    => 'Shape of action buttons throughout the builder.',
        'default' => 'square',
        'options' => [
            'square'  => 'Squared (default)',
            'rounded' => 'Rounded',
            'pill'    => 'Pill',
        ],
    ],
    [
        'key'          => 'rustic_setting_uppercase_btns',
        'type'         => 'toggle',
        'label'        => 'Uppercase Button Labels',
        'desc'         => 'Apply text-transform: uppercase to button text.',
        'default'      => 'yes',
        'toggle_label' => 'Uppercase',
    ],

    /* ── Cards ───────────────────────────────────────────────── */
    [
        'key'          => 'rustic_setting_show_corner_fold',
        'type'         => 'toggle',
        'label'        => 'Corner Fold Effect',
        'desc'         => 'Show a subtle folded-corner effect on ingredient cards.',
        'default'      => 'yes',
        'toggle_label' => 'Show fold',
    ],
    [
        'key'          => 'rustic_setting_card_hover_lift',
        'type'         => 'toggle',
        'label'        => 'Card Hover Lift',
        'desc'         => 'Cards rise slightly on hover (translateY animation).',
        'default'      => 'yes',
        'toggle_label' => 'Enable lift',
    ],

    /* ── Order Summary ───────────────────────────────────────── */
    [
        'key'          => 'rustic_setting_show_lined_paper',
        'type'         => 'toggle',
        'label'        => 'Lined Paper in Order Summary',
        'desc'         => 'Show horizontal ruled lines behind the "Your Order" summary rows.',
        'default'      => 'yes',
        'toggle_label' => 'Show lines',
    ],

    /* ── Copy / Labels ───────────────────────────────────────── */
    [
        'key'     => 'rustic_setting_choose_label',
        'type'    => 'text',
        'label'   => '"Choose" Button Label',
        'desc'    => 'Text on exclusive-select (crust/sauce/cheese) add buttons.',
        'default' => 'Choose',
    ],
    [
        'key'     => 'rustic_setting_add_label',
        'type'    => 'text',
        'label'   => '"Add" Button Label',
        'desc'    => 'Text on topping add buttons.',
        'default' => 'Add',
    ],
    [
        'key'     => 'rustic_setting_remove_label',
        'type'    => 'text',
        'label'   => '"Remove" Button Label',
        'desc'    => 'Text on remove buttons.',
        'default' => 'Remove',
    ],
    [
        'key'     => 'rustic_setting_reset_label',
        'type'    => 'text',
        'label'   => '"Reset" Button Label',
        'desc'    => 'Text on the small reset button below the pizza preview.',
        'default' => 'Reset',
    ],
    [
        'key'     => 'rustic_setting_order_title',
        'type'    => 'text',
        'label'   => 'Order Summary Title',
        'desc'    => 'Heading text on the final "Your Order" summary panel.',
        'default' => 'Your Order',
    ],
    [
        'key'     => 'rustic_setting_order_tagline',
        'type'    => 'text',
        'label'   => 'Order Summary Tagline',
        'desc'    => 'Hint text below the order summary heading.',
        'default' => 'Crafted by your hands, baked by ours.',
    ],
];
