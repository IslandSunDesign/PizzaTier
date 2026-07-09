<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Metro template — customization settings.
 *
 * Each entry renders as a field in Settings → Template Settings.
 * Supported types: text, text_wide, number, color, select, toggle, textarea, radio, range
 *
 * For 'color' type, set 'default' to the hex you want the revert button to restore.
 */
return [

	// ── Colours ──────────────────────────────────────────────────────
	[
		'key'     => 'metro_setting_accent_color',
		'type'    => 'color',
		'label'   => 'Accent Color',
		'desc'    => 'Primary action color used for buttons, active states, and highlights.',
		'default' => '#e63946',
	],
	[
		'key'     => 'metro_setting_background_color',
		'type'    => 'color',
		'label'   => 'Page Background Color',
		'desc'    => 'The outermost background that frames the builder. Shows around (and beneath) the UI container panel.',
		'default' => '#f7f7f5',
	],
	[
		'key'     => 'metro_setting_ui_bg_color',
		'type'    => 'color',
		'label'   => 'UI Container Background',
		'desc'    => 'Background of the builder panel itself — the pizza hero and every ingredient section. This is the large surface the cards sit on.',
		'default' => '#ffffff',
	],
	[
		'key'         => 'metro_setting_container_bg_image',
		'type'        => 'image',
		'label'       => 'Container Background Image',
		'desc'        => 'Optional image shown behind the entire builder container, layered over the Page Background Color. Displayed centered and scaled to cover. Leave empty for a solid colour.',
		'default'     => '',
		'placeholder' => 'https://example.com/background.jpg',
	],
	[
		'key'     => 'metro_setting_card_bg_color',
		'type'    => 'color',
		'label'   => 'Card Background Color',
		'desc'    => 'Background of each full ingredient card (image area included).',
		'default' => '#ffffff',
	],
	[
		'key'     => 'metro_setting_card_text_color',
		'type'    => 'color',
		'label'   => 'Card Text Color',
		'desc'    => 'Color of the ingredient name shown on each card.',
		'default' => '#1a1a1a',
	],
	[
		'key'     => 'metro_setting_title_color',
		'type'    => 'color',
		'label'   => 'Section Title Color',
		'desc'    => 'Color of section headings (Crust, Sauce, Toppings…) and the hero tagline.',
		'default' => '#1a1a1a',
	],
	[
		'key'     => 'metro_setting_border_color',
		'type'    => 'color',
		'label'   => 'Border Color',
		'desc'    => 'Color of the borders/dividers on cards, panels, tabs, and the summary bar. The hover border tone is derived from this automatically.',
		'default' => '#e4e4e0',
	],
	[
		'key'          => 'metro_setting_show_borders',
		'type'         => 'toggle',
		'label'        => 'Card & Panel Borders',
		'desc'         => 'Draw borders around cards, panels, and dividers. Turn off for a flat, borderless look (backgrounds and shadows still apply).',
		'default'      => 'yes',
		'toggle_label' => 'Show borders',
	],

	// ── Typography ────────────────────────────────────────────────────
	[
		'key'     => 'metro_setting_heading_font',
		'type'    => 'select',
		'label'   => 'Heading Font',
		'desc'    => 'Font stack used for section headings in the builder.',
		'default' => 'system',
		'options' => [
			'system'     => 'System UI (default)',
			'inter'      => 'Inter',
			'poppins'    => 'Poppins',
			'montserrat' => 'Montserrat',
			'playfair'   => 'Playfair Display (serif)',
		],
	],
	[
		'key'     => 'metro_setting_base_font_size',
		'type'    => 'range',
		'label'   => 'Base Font Size (px)',
		'desc'    => 'Adjusts the base text size inside the builder.',
		'default' => '14',
		'min'     => 12,
		'max'     => 20,
		'step'    => 1,
	],

	// ── Layout ────────────────────────────────────────────────────────
	[
		'key'     => 'metro_setting_layout_mode',
		'type'    => 'radio',
		'label'   => 'Layout Mode',
		'desc'    => 'Controls how the pizza visualizer and ingredient panels are arranged.',
		'default' => 'centered',
		'options' => [
			'centered'     => 'Centered hero (pizza top, ingredients below)',
			'side-by-side' => 'Side by side (pizza left, panels right)',
			'fullwidth'    => 'Full-width panels, sticky visualizer',
		],
	],
	[
		'key'     => 'metro_setting_card_columns',
		'type'    => 'select',
		'label'   => 'Ingredient Card Columns',
		'desc'    => 'Number of columns in the ingredient grid.',
		'default' => '3',
		'options' => [
			'2'    => '2 columns',
			'3'    => '3 columns (default)',
			'4'    => '4 columns',
			'auto' => 'Auto (responsive)',
		],
	],
	[
		'key'     => 'metro_setting_visualizer_size',
		'type'    => 'number',
		'label'   => 'Visualizer Max Width (px)',
		'desc'    => 'Maximum pixel width of the pizza canvas. Leave 0 for template default.',
		'default' => '0',
		'min'     => 0,
		'max'     => 800,
		'step'    => 10,
	],

	// ── Card Style ────────────────────────────────────────────────────
	[
		'key'     => 'metro_setting_card_style',
		'type'    => 'select',
		'label'   => 'Ingredient Card Style',
		'desc'    => 'Visual presentation style for ingredient selection cards.',
		'default' => 'standard',
		'options' => [
			'standard'    => 'Standard — image top, name + button below',
			'compact'     => 'Compact — small image, horizontal layout',
			'minimal'     => 'Minimal — name only, no image shown',
			'large-image' => 'Large Image — taller photo, name overlaid',
			'pill'        => 'Pill — round thumb, horizontal chip layout',
		],
	],

	// ── Tab Bar Style ─────────────────────────────────────────────────
	[
		'key'     => 'metro_setting_tab_style',
		'type'    => 'select',
		'label'   => 'Section Tab Bar Style',
		'desc'    => 'Visual style of the builder\'s section navigation tabs.',
		'default' => 'scrollbar',
		'options' => [
			'scrollbar'  => 'Scroll Bar — icon + label, horizontal scroll',
			'icons-only' => 'Icons Only — compact icon strip',
			'pills'      => 'Pills — rounded pill buttons',
			'underline'  => 'Underline — minimal underline tabs',
			'sidebar'    => 'Sidebar — vertical left-side nav',
		],
	],

	// ── Features ─────────────────────────────────────────────────────
	[
		'key'          => 'metro_setting_show_summary_bar',
		'type'         => 'toggle',
		'label'        => 'Show Running Summary Bar',
		'desc'         => 'Sticky bar at the bottom showing selected ingredients and running total.',
		'default'      => 'yes',
		'toggle_label' => 'Show summary bar',
	],
	[
		'key'          => 'metro_setting_sticky_visualizer',
		'type'         => 'toggle',
		'label'        => 'Sticky Pizza Visualizer',
		'desc'         => 'Keep the pizza canvas fixed in view as the user scrolls through ingredients.',
		'default'      => 'no',
		'toggle_label' => 'Enable sticky canvas',
	],
	[
		'key'          => 'metro_setting_show_ingredient_count',
		'type'         => 'toggle',
		'label'        => 'Show Topping Count Badge',
		'desc'         => 'Display how many toppings have been selected vs. the maximum.',
		'default'      => 'yes',
		'toggle_label' => 'Show topping counter',
	],

	// ── Branding ─────────────────────────────────────────────────────
	[
		'key'         => 'metro_setting_hero_tagline',
		'type'        => 'text_wide',
		'label'       => 'Hero Tagline',
		'desc'        => 'Short tagline displayed above the pizza visualizer in centered layout.',
		'default'     => '',
		'placeholder' => 'e.g. Build your perfect pizza',
	],
	[
		'key'     => 'metro_setting_footer_note',
		'type'    => 'textarea',
		'label'   => 'Builder Footer Note',
		'desc'    => 'Optional note or allergy disclaimer shown below the builder. Supports basic HTML.',
		'default' => '',
		'rows'    => 2,
	],

	// ── Spacing ───────────────────────────────────────────────────────
	[
		'key'     => 'metro_setting_section_gap',
		'type'    => 'range',
		'label'   => 'Section Gap (px)',
		'desc'    => 'Vertical spacing between ingredient sections.',
		'default' => '24',
		'min'     => 8,
		'max'     => 60,
		'step'    => 4,
	],
	[
		'key'     => 'metro_setting_card_border_radius',
		'type'    => 'range',
		'label'   => 'Card Corner Radius (px)',
		'desc'    => 'Border radius for ingredient selection cards.',
		'default' => '14',
		'min'     => 0,
		'max'     => 24,
		'step'    => 1,
	],
];
