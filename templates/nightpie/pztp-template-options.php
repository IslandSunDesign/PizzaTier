<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * NightPie template — customization settings.
 *
 * Each entry renders as a field on the Templates page → Template Settings panel.
 * Supported types: text, text_wide, number, color, select, toggle, textarea, radio, range
 *
 * For 'color' type, set 'default' to the hex you want the revert button to restore.
 */
return [

	// ── Colours ──────────────────────────────────────────────────────
	[
		'key'     => 'nightpie_setting_accent_color',
		'type'    => 'color',
		'label'   => 'Accent Color',
		'desc'    => 'Primary action color used for selected items, the active tab indicator, and primary buttons.',
		'default' => '#ff5722',
	],
	[
		'key'     => 'nightpie_setting_bg_color',
		'type'    => 'color',
		'label'   => 'Background Color',
		'desc'    => 'Outer dark background of the builder container.',
		'default' => '#0e0e12',
	],
	[
		'key'     => 'nightpie_setting_surface_color',
		'type'    => 'color',
		'label'   => 'Surface Color',
		'desc'    => 'Background of cards, the tab panel, and the pizza preview surround.',
		'default' => '#18181f',
	],
	[
		'key'     => 'nightpie_setting_surface_2_color',
		'type'    => 'color',
		'label'   => 'Raised Surface Color',
		'desc'    => 'Background of inputs, secondary panels, and inactive selectable cards.',
		'default' => '#22222c',
	],
	[
		'key'     => 'nightpie_setting_text_color',
		'type'    => 'color',
		'label'   => 'Text Color',
		'desc'    => 'Primary text color.',
		'default' => '#f0f0f4',
	],
	[
		'key'     => 'nightpie_setting_text_muted_color',
		'type'    => 'color',
		'label'   => 'Muted Text Color',
		'desc'    => 'Secondary text color for descriptions and helper text.',
		'default' => '#888898',
	],

	// ── Item Cards ────────────────────────────────────────────────────
	[
		'key'          => 'nightpie_setting_card_border',
		'type'         => 'toggle',
		'label'        => 'Item Card Border',
		'desc'         => 'Draw a border around each selectable ingredient card. Off by default — cards sit borderless (transparent) on their surface. Selected and hovered cards keep their accent outline either way.',
		'default'      => 'no',
		'toggle_label' => 'Show a border around item cards',
	],
	[
		'key'     => 'nightpie_setting_card_border_color',
		'type'    => 'color',
		'label'   => 'Item Card Border Color',
		'desc'    => 'Border color used when "Item Card Border" is enabled above.',
		'default' => '#2e2e3a',
	],

	// ── Typography ────────────────────────────────────────────────────
	[
		'key'     => 'nightpie_setting_font_family',
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
		'key'     => 'nightpie_setting_base_font_size',
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
		'key'     => 'nightpie_setting_corner_radius',
		'type'    => 'range',
		'label'   => 'Corner Radius (px)',
		'desc'    => 'Corner radius applied to cards, inputs, and panels. NightPie uses a "bubble" style — keep this generous (14–20px) for the intended look.',
		'default' => '16',
		'min'     => 0,
		'max'     => 28,
		'step'    => 1,
		'unit'    => 'px',
	],

	// ── Behavior ──────────────────────────────────────────────────────
	[
		'key'          => 'nightpie_setting_sticky_preview',
		'type'         => 'toggle',
		'label'        => 'Sticky Pizza Preview',
		'desc'         => 'Keep the pizza preview pinned to the top of the viewport as the customer scrolls through tabs on desktop. Disable for a static, side-by-side layout.',
		'default'      => 'yes',
		'toggle_label' => 'Enable sticky preview on desktop',
	],
	[
		'key'          => 'nightpie_setting_accent_glow',
		'type'         => 'toggle',
		'label'        => 'Accent Glow Effect',
		'desc'         => 'Adds a soft glow around the active tab indicator and selected items. Turn off for a flatter look.',
		'default'      => 'yes',
		'toggle_label' => 'Enable accent glow',
	],
];
