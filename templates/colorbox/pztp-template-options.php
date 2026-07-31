<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Colorbox template — customization settings.
 *
 * Each entry renders as a field on the Templates page → Template Settings panel.
 * Supported types: text, text_wide, number, color, select, toggle, textarea, radio, range
 *
 * Colorbox uses one color per ingredient category for its signature
 * "colorful tiles" look. Each category color is configurable below.
 *
 * For 'color' type, set 'default' to the hex you want the revert button to restore.
 */
return [

	// ── Base colours ─────────────────────────────────────────────────
	[
		'key'     => 'colorbox_setting_accent_color',
		'type'    => 'color',
		'label'   => 'Accent Color',
		'desc'    => 'Primary action color used for primary buttons, the active pill tab, and selected states.',
		'default' => '#ff4d4d',
	],
	[
		'key'     => 'colorbox_setting_bg_color',
		'type'    => 'color',
		'label'   => 'Background Color',
		'desc'    => 'Outermost backdrop behind the builder — shows as a thin matte frame around the container panel.',
		'default' => '#f6f7fb',
	],
	[
		'key'     => 'colorbox_setting_container_bg',
		'type'    => 'color',
		'label'   => 'Container Background',
		'desc'    => 'Background of the full builder container — the panel that wraps the pizza preview and the builder tabs.',
		'default' => '#f3e2c7',
	],
	[
		'key'     => 'colorbox_setting_surface_color',
		'type'    => 'color',
		'label'   => 'Card Surface Color',
		'desc'    => 'Background of selection cards and the pizza preview surround.',
		'default' => '#ffffff',
	],
	[
		'key'     => 'colorbox_setting_text_color',
		'type'    => 'color',
		'label'   => 'Text Color',
		'desc'    => 'Primary text color.',
		'default' => '#161822',
	],
	[
		'key'     => 'colorbox_setting_text_muted_color',
		'type'    => 'color',
		'label'   => 'Muted Text Color',
		'desc'    => 'Secondary text color for descriptions and helper text.',
		'default' => '#5b5f73',
	],

	// ── Category tile colours (Colorbox signature) ───────────────────
	[
		'key'     => 'colorbox_setting_cat_size_color',
		'type'    => 'color',
		'label'   => 'Sizes Tile Color',
		'desc'    => 'Background tint for the Sizes category tiles.',
		'default' => '#4a90e2',
	],
	[
		'key'     => 'colorbox_setting_cat_crust_color',
		'type'    => 'color',
		'label'   => 'Crust Tile Color',
		'desc'    => 'Background tint for the Crust category tiles.',
		'default' => '#f5a623',
	],
	[
		'key'     => 'colorbox_setting_cat_sauce_color',
		'type'    => 'color',
		'label'   => 'Sauce Tile Color',
		'desc'    => 'Background tint for the Sauce category tiles.',
		'default' => '#d0021b',
	],
	[
		'key'     => 'colorbox_setting_cat_cheese_color',
		'type'    => 'color',
		'label'   => 'Cheese Tile Color',
		'desc'    => 'Background tint for the Cheese category tiles.',
		'default' => '#f8e71c',
	],
	[
		'key'     => 'colorbox_setting_cat_toppings_color',
		'type'    => 'color',
		'label'   => 'Toppings Tile Color',
		'desc'    => 'Background tint for the Toppings category tiles.',
		'default' => '#7ed321',
	],
	[
		'key'     => 'colorbox_setting_cat_drizzle_color',
		'type'    => 'color',
		'label'   => 'Drizzle Tile Color',
		'desc'    => 'Background tint for the Drizzle category tiles.',
		'default' => '#bd10e0',
	],
	[
		'key'     => 'colorbox_setting_cat_cuts_color',
		'type'    => 'color',
		'label'   => 'Cuts Tile Color',
		'desc'    => 'Background tint for the Cuts category tiles.',
		'default' => '#50e3c2',
	],

	// ── Typography ────────────────────────────────────────────────────
	[
		'key'     => 'colorbox_setting_font_family',
		'type'    => 'select',
		'label'   => 'Font Family',
		'desc'    => 'Font stack used throughout the builder.',
		'default' => 'system',
		'options' => [
			'system'     => 'System UI (default)',
			'inter'      => 'Inter',
			'poppins'    => 'Poppins',
			'montserrat' => 'Montserrat',
			'roboto'     => 'Roboto',
		],
	],
	[
		'key'     => 'colorbox_setting_base_font_size',
		'type'    => 'range',
		'label'   => 'Base Font Size (px)',
		'desc'    => 'Base text size inside the builder.',
		'default' => '15',
		'min'     => 12,
		'max'     => 20,
		'step'    => 1,
		'unit'    => 'px',
	],

	// ── Geometry ──────────────────────────────────────────────────────
	[
		'key'     => 'colorbox_setting_corner_radius',
		'type'    => 'range',
		'label'   => 'Corner Radius (px)',
		'desc'    => 'Corner radius applied to cards and tiles. Colorbox is designed for a soft, rounded look — keep this 14px or higher for the intended feel.',
		'default' => '18',
		'min'     => 0,
		'max'     => 28,
		'step'    => 1,
		'unit'    => 'px',
	],

	// ── Behavior ──────────────────────────────────────────────────────
	[
		'key'          => 'colorbox_setting_colorful_tiles',
		'type'         => 'toggle',
		'label'        => 'Colorful Category Tiles',
		'desc'         => 'Apply per-category colors to ingredient tiles for the signature Colorbox look. Turn off to use a single neutral tile color across all categories.',
		'default'      => 'yes',
		'toggle_label' => 'Use category colors on tiles',
	],
];
