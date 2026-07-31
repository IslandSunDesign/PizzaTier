<?php
/**
 * Scaffold Template Options
 *
 * Settings rendered in the "Scaffold Template Settings" card on the Settings page.
 * These are utility/convenience settings that don't enforce a design opinion —
 * they expose CSS custom property values and behavioural toggles developers
 * can use as a baseline without fighting opinionated defaults.
 *
 * Supported types: text | number | color | select | toggle | textarea | range
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

return [

    /* ══════════════════════════════════════════════════════════════════
       GROUP: CSS Custom Properties
       These map directly to --sc-* variables injected per instance.
       ══════════════════════════════════════════════════════════════════ */

    [
        'key'     => 'scaffold_setting_accent_color',
        'type'    => 'color',
        'label'   => 'Accent Colour',
        'desc'    => 'Maps to --sc-accent. Used for active tabs, selected card borders, and focus rings.',
        'default' => '#2271b1',
    ],

    [
        'key'     => 'scaffold_setting_bg_color',
        'type'    => 'color',
        'label'   => 'Builder Background',
        'desc'    => 'Maps to --sc-bg. Background of the entire .sc-root container. Set to transparent to inherit from your theme.',
        'default' => '#ffffff',
    ],

    [
        'key'     => 'scaffold_setting_text_color',
        'type'    => 'color',
        'label'   => 'Text Colour',
        'desc'    => 'Maps to --sc-text. Main text colour for labels, tab names, and summary rows.',
        'default' => '#1e1e1e',
    ],

    [
        'key'     => 'scaffold_setting_border_color',
        'type'    => 'color',
        'label'   => 'Border / Divider Colour',
        'desc'    => 'Maps to --sc-border. Used on card outlines, tab bar underline, and summary row dividers.',
        'default' => '#dcdcde',
    ],

    /* ══════════════════════════════════════════════════════════════════
       GROUP: Typography
       ══════════════════════════════════════════════════════════════════ */

    [
        'key'     => 'scaffold_setting_font_family',
        'type'    => 'select',
        'label'   => 'Font Family',
        'desc'    => 'Maps to --sc-font. Selects the font stack used throughout the builder.',
        'default' => 'inherit',
        'options' => [
            'inherit' => 'Inherit from theme',
            'system'  => 'System UI sans-serif',
            'serif'   => 'Georgia / serif',
            'mono'    => 'Courier New / monospace',
            'custom'  => 'Custom (enter name below)',
        ],
    ],

    [
        'key'         => 'scaffold_setting_font_custom',
        'type'        => 'text',
        'label'       => 'Custom Font Name',
        'desc'        => 'Used when Font Family is set to Custom. The font must already be loaded by your theme or a plugin (e.g. Google Fonts).',
        'default'     => '',
        'placeholder' => 'Nunito, Inter, …',
    ],

    [
        'key'         => 'scaffold_setting_base_font_size',
        'type'        => 'text',
        'label'       => 'Base Font Size',
        'desc'        => 'Maps to --sc-font-size. Root em size for the builder widget, e.g. 14px or 1rem.',
        'default'     => '14px',
        'placeholder' => '14px',
    ],

    /* ══════════════════════════════════════════════════════════════════
       GROUP: Layout
       ══════════════════════════════════════════════════════════════════ */

    [
        'key'         => 'scaffold_setting_builder_width',
        'type'        => 'text',
        'label'       => 'Builder Max Width',
        'desc'        => 'Adds a max-width inline style to .sc-root. Leave blank for 100% / fluid. E.g. 640px.',
        'default'     => '',
        'placeholder' => '640px',
    ],

    [
        'key'     => 'scaffold_setting_tab_style',
        'type'    => 'select',
        'label'   => 'Tab Bar Style',
        'desc'    => 'Adds a modifier class to .sc-root that drives the CSS tab style. Only layout-level CSS — no colour changes.',
        'default' => 'underline',
        'options' => [
            'underline' => 'Underline (default)',
            'pill'      => 'Pill / rounded',
            'box'       => 'Box / chip',
        ],
    ],

    /* ══════════════════════════════════════════════════════════════════
       GROUP: Item Cards
       ══════════════════════════════════════════════════════════════════ */

    [
        'key'         => 'scaffold_setting_thumb_size',
        'type'        => 'text',
        'label'       => 'Thumbnail Size',
        'desc'        => 'Maps to --sc-thumb-size. Width and height of item card thumbnails, e.g. 56px.',
        'default'     => '56px',
        'placeholder' => '56px',
    ],

    [
        'key'         => 'scaffold_setting_card_radius',
        'type'        => 'text',
        'label'       => 'Card Border Radius',
        'desc'        => 'Maps to --sc-card-radius. Corner rounding for item cards, e.g. 6px or 0 for square.',
        'default'     => '6px',
        'placeholder' => '6px',
    ],

    [
        'key'     => 'scaffold_setting_card_cols',
        'type'    => 'select',
        'label'   => 'Card Grid Columns',
        'desc'    => 'Maps to --sc-grid-cols. Number of columns in the item card grid.',
        'default' => '3',
        'options' => [
            '2'    => '2 columns',
            '3'    => '3 columns (default)',
            '4'    => '4 columns',
            'auto' => 'Auto-fill (responsive)',
        ],
    ],

    [
        'key'          => 'scaffold_setting_show_labels',
        'type'         => 'toggle',
        'label'        => 'Show Item Name Labels',
        'desc'         => 'Show the item name text beneath each card thumbnail. Disable for icon-only grids.',
        'default'      => 'yes',
        'toggle_label' => 'Show name labels on cards',
    ],

    /* ══════════════════════════════════════════════════════════════════
       GROUP: Animation
       ══════════════════════════════════════════════════════════════════ */

    [
        'key'     => 'scaffold_setting_anim_speed',
        'type'    => 'select',
        'label'   => 'Transition Speed',
        'desc'    => 'Maps to --sc-anim-speed. Speed of hover, selection, and tab-switch CSS transitions.',
        'default' => '200ms',
        'options' => [
            '0ms'   => 'Instant (no animation)',
            '120ms' => 'Fast (120ms)',
            '200ms' => 'Normal (200ms, default)',
            '400ms' => 'Slow (400ms)',
        ],
    ],

    /* ══════════════════════════════════════════════════════════════════
       GROUP: Summary Panel
       ══════════════════════════════════════════════════════════════════ */

    [
        'key'         => 'scaffold_setting_summary_title',
        'type'        => 'text',
        'label'       => 'Summary Panel Heading',
        'desc'        => 'Heading text at the top of the "Your Pizza" summary panel.',
        'default'     => 'Your Pizza',
        'placeholder' => 'Your Pizza',
    ],

    /* ══════════════════════════════════════════════════════════════════
       GROUP: Add to Cart (PizzaTier)
       Controls the checkout-bar CTA rendered by PizzaTier. These have no
       effect unless PizzaTier is active and WooCommerce is in use. Button
       styling lives entirely in this template's template.css
       (.pztc-checkout-bar--scaffold).
       ══════════════════════════════════════════════════════════════════ */

    [
        'key'         => 'scaffold_setting_cta_text',
        'type'        => 'text',
        'label'       => 'Add to Cart Button Text',
        'desc'        => 'Label shown on the Add to Cart button in the checkout bar (PizzaTier). Leave blank to use the default "Add to Cart".',
        'default'     => '',
        'placeholder' => 'Add to Cart',
    ],

    [
        'key'          => 'scaffold_setting_cta_show_icon',
        'type'         => 'toggle',
        'label'        => 'Show Cart Icon',
        'desc'         => 'Show the cart icon to the left of the Add to Cart label. Disable for a text-only button.',
        'default'      => 'yes',
        'toggle_label' => 'Show cart icon on the button',
    ],

];
