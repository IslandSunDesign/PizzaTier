<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Command Center template — customization settings.
 *
 * Each entry renders as a field on the Templates page → Template Settings panel.
 * Supported types: text, text_wide, number, color, select, toggle, textarea, radio, range
 *
 * For 'color' type, set 'default' to the hex you want the revert button to restore.
 */
return [

	// ── Colours ──────────────────────────────────────────────────────
	[
		'key'     => 'commandcenter_setting_accent_color',
		'type'    => 'color',
		'label'   => 'Accent Color',
		'desc'    => 'Primary action color used for the active step indicator, primary buttons, and key highlights.',
		'default' => '#e94560',
	],
	[
		'key'     => 'commandcenter_setting_accent_hover_color',
		'type'    => 'color',
		'label'   => 'Accent Hover Color',
		'desc'    => 'Brighter accent shade used on hover states for primary actions.',
		'default' => '#ff5572',
	],
	[
		'key'     => 'commandcenter_setting_cta_color',
		'type'    => 'color',
		'label'   => 'Add to Cart Button Color',
		'desc'    => 'Color of the Add to Cart button and the live price (PizzaTier). Defaults to the accent color.',
		'default' => '#e94560',
	],
	[
		'key'     => 'commandcenter_setting_step_done_color',
		'type'    => 'color',
		'label'   => 'Completed Step Color',
		'desc'    => 'Color used to mark wizard steps that have been completed.',
		'default' => '#3dd68c',
	],
	[
		'key'     => 'commandcenter_setting_bg_color',
		'type'    => 'color',
		'label'   => 'Page Background',
		'desc'    => 'Outermost background of the builder container.',
		'default' => '#0b1120',
	],
	[
		'key'     => 'commandcenter_setting_surface_color',
		'type'    => 'color',
		'label'   => 'Surface Color',
		'desc'    => 'Background of cards, the wizard header, and the order-summary sidebar.',
		'default' => '#16213e',
	],
	[
		'key'     => 'commandcenter_setting_surface_2_color',
		'type'    => 'color',
		'label'   => 'Raised Surface Color',
		'desc'    => 'Background of inputs, secondary panels, and inactive selectable cards.',
		'default' => '#1e2d4f',
	],
	[
		'key'     => 'commandcenter_setting_text_color',
		'type'    => 'color',
		'label'   => 'Text Color',
		'desc'    => 'Primary text color used throughout the builder.',
		'default' => '#e8eaf6',
	],
	[
		'key'     => 'commandcenter_setting_text_muted_color',
		'type'    => 'color',
		'label'   => 'Muted Text Color',
		'desc'    => 'Secondary text color for descriptions, helper text, and step subtitles.',
		'default' => '#8892b0',
	],

	// ── Typography ────────────────────────────────────────────────────
	[
		'key'     => 'commandcenter_setting_font_family',
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
		'key'     => 'commandcenter_setting_base_font_size',
		'type'    => 'range',
		'label'   => 'Base Font Size (px)',
		'desc'    => 'Base text size inside the builder. Step labels and UI scale from this value.',
		'default' => '15',
		'min'     => 12,
		'max'     => 20,
		'step'    => 1,
		'unit'    => 'px',
	],

	// ── Geometry ──────────────────────────────────────────────────────
	[
		'key'     => 'commandcenter_setting_corner_radius',
		'type'    => 'range',
		'label'   => 'Corner Radius (px)',
		'desc'    => 'Corner radius applied to cards, inputs, and panels.',
		'default' => '12',
		'min'     => 0,
		'max'     => 24,
		'step'    => 1,
		'unit'    => 'px',
	],

	// ── Wizard behaviour ──────────────────────────────────────────────
	[
		'key'          => 'commandcenter_setting_show_step_numbers',
		'type'         => 'toggle',
		'label'        => 'Show Step Numbers',
		'desc'         => 'Display the numbered badge on each step in the wizard header. Turn off for a cleaner, label-only header.',
		'default'      => 'yes',
		'toggle_label' => 'Show numbered step badges',
	],
	[
		'key'          => 'commandcenter_setting_colorful_tabs',
		'type'         => 'toggle',
		'label'        => 'Colorful Step Tabs',
		'desc'         => 'Give each builder step its own color (size, crust, sauce, cheese, toppings, drizzle, slicing). Turn off to use a single accent color for every step.',
		'default'      => 'yes',
		'toggle_label' => 'Use a distinct color per step',
	],
	[
		'key'          => 'commandcenter_setting_show_summary_sidebar',
		'type'         => 'toggle',
		'label'        => 'Show Order Summary Sidebar',
		'desc'         => 'Display the persistent order-summary sidebar to the right of the builder. Turn off to use the full width for the wizard.',
		'default'      => 'yes',
		'toggle_label' => 'Show summary sidebar',
	],
	[
		'key'          => 'commandcenter_setting_accent_glow',
		'type'         => 'toggle',
		'label'        => 'Accent Glow Effect',
		'desc'         => 'Adds a subtle glow around the active step and primary buttons. Turn off for a flatter, more conservative look.',
		'default'      => 'yes',
		'toggle_label' => 'Enable accent glow',
	],

	// ── Checkout (PizzaTier) ──────────────────────────────────────
	[
		'key'         => 'commandcenter_setting_cta_text',
		'type'        => 'text',
		'label'       => 'Add to Cart Button Text',
		'desc'        => 'Label shown on the Add to Cart button in the checkout bar (PizzaTier). Leave blank to use the default "Add to Cart".',
		'default'     => '',
		'placeholder' => 'Add to Cart',
	],
];
