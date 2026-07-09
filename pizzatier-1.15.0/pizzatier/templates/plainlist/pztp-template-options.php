<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Plainlist template — customization settings.
 *
 * Each entry renders as a field in Settings → Template Settings.
 * Supported types: text, text_wide, number, color, select, toggle, textarea, radio, range
 */
return [

	// ── Layout Mode ──────────────────────────────────────────────────
	[
		'key'     => 'plainlist_setting_layout_mode',
		'type'    => 'radio',
		'label'   => 'Layout Mode',
		'desc'    => 'Step-by-step shows one section at a time with Prev/Next navigation. Single list shows all sections on one scrollable page.',
		'default' => 'single-list',
		'options' => [
			'single-list' => 'Single list — all sections visible, one scrollable page',
			'step-by-step' => 'Step by step — one section at a time, wizard style',
		],
	],

	// ── Colours ──────────────────────────────────────────────────────
	[
		'key'     => 'plainlist_setting_accent_color',
		'type'    => 'color',
		'label'   => 'Accent Color',
		'desc'    => 'Color for checked state, active steps, and buttons.',
		'default' => '#1a1a1a',
	],
	[
		'key'     => 'plainlist_setting_bg_color',
		'type'    => 'color',
		'label'   => 'Background Color',
		'desc'    => 'Background of the whole builder container.',
		'default' => '#ffffff',
	],
	[
		'key'     => 'plainlist_setting_section_header_color',
		'type'    => 'color',
		'label'   => 'Section Header Color',
		'desc'    => 'Color of section titles (Crust, Sauce, etc.).',
		'default' => '#111111',
	],
	[
		'key'     => 'plainlist_setting_item_text_color',
		'type'    => 'color',
		'label'   => 'Item Text Color',
		'desc'    => 'Color of the item label text in lists.',
		'default' => '#333333',
	],
	[
		'key'     => 'plainlist_setting_divider_color',
		'type'    => 'color',
		'label'   => 'Divider Line Color',
		'desc'    => 'Color of the horizontal rule between sections.',
		'default' => '#e0e0e0',
	],

	// ── Typography ────────────────────────────────────────────────────
	[
		'key'     => 'plainlist_setting_font_family',
		'type'    => 'select',
		'label'   => 'Font Family',
		'desc'    => 'Font stack used throughout the builder.',
		'default' => 'system',
		'options' => [
			'system'     => 'System UI (default)',
			'georgia'    => 'Georgia (serif)',
			'inter'      => 'Inter',
			'roboto'     => 'Roboto',
			'mono'       => 'Monospace',
			'courier'    => 'Courier New',
		],
	],
	[
		'key'     => 'plainlist_setting_base_font_size',
		'type'    => 'range',
		'label'   => 'Base Font Size (px)',
		'desc'    => 'Base text size for items and labels inside the builder.',
		'default' => '15',
		'min'     => 12,
		'max'     => 22,
		'step'    => 1,
	],
	[
		'key'     => 'plainlist_setting_heading_size',
		'type'    => 'range',
		'label'   => 'Section Heading Size (px)',
		'desc'    => 'Font size for section title headings.',
		'default' => '18',
		'min'     => 13,
		'max'     => 32,
		'step'    => 1,
	],
	[
		'key'     => 'plainlist_setting_heading_weight',
		'type'    => 'select',
		'label'   => 'Section Heading Weight',
		'desc'    => 'Font weight of section headings.',
		'default' => '700',
		'options' => [
			'400' => 'Normal',
			'600' => 'Semibold',
			'700' => 'Bold',
			'900' => 'Black',
		],
	],
	[
		'key'     => 'plainlist_setting_text_transform',
		'type'    => 'select',
		'label'   => 'Heading Text Transform',
		'desc'    => 'Capitalization style for section headings.',
		'default' => 'none',
		'options' => [
			'none'       => 'None (as written)',
			'uppercase'  => 'UPPERCASE',
			'lowercase'  => 'lowercase',
			'capitalize' => 'Capitalize Each Word',
		],
	],

	// ── Checkbox Style ────────────────────────────────────────────────
	[
		'key'     => 'plainlist_setting_check_style',
		'type'    => 'select',
		'label'   => 'Checkbox Style',
		'desc'    => 'Visual style of the selection checkbox/marker.',
		'default' => 'checkbox',
		'options' => [
			'checkbox'   => 'Square checkbox',
			'radio'      => 'Round radio button',
			'bullet'     => 'Solid bullet dot',
			'dash'       => 'Dash prefix',
			'arrow'      => 'Arrow →',
			'star'       => 'Star ★',
			'circle-dot' => 'Circle with dot',
		],
	],
	[
		'key'     => 'plainlist_setting_check_size',
		'type'    => 'range',
		'label'   => 'Checkbox Size (px)',
		'desc'    => 'Size of the checkbox/marker element.',
		'default' => '18',
		'min'     => 12,
		'max'     => 28,
		'step'    => 2,
	],

	// ── List Item Style ──────────────────────────────────────────────
	[
		'key'     => 'plainlist_setting_list_style',
		'type'    => 'select',
		'label'   => 'List Row Style',
		'desc'    => 'Visual treatment of each item row in the list.',
		'default' => 'plain',
		'options' => [
			'plain'     => 'Plain — text rows, no boxes',
			'bordered'  => 'Bordered — thin divider under each row',
			'striped'   => 'Striped — alternating row background',
			'card'      => 'Cards — each row in its own bordered box',
			'underline' => 'Underline — heavier rule under each row',
		],
	],
	[
		'key'     => 'plainlist_setting_selected_style',
		'type'    => 'select',
		'label'   => 'Selected Item Style',
		'desc'    => 'How a chosen item is emphasised in the list.',
		'default' => 'accent',
		'options' => [
			'accent'  => 'Accent text — colour + bold label (default)',
			'filled'  => 'Filled — accent background highlight on the row',
			'leftbar' => 'Left bar — accent bar on the row’s left edge',
			'bold'    => 'Bold only — weight change, no colour shift',
		],
	],
	[
		'key'     => 'plainlist_setting_row_padding',
		'type'    => 'range',
		'label'   => 'Row Padding (px)',
		'desc'    => 'Vertical padding inside each item row. Larger values give a roomier, more tappable list.',
		'default' => '4',
		'min'     => 0,
		'max'     => 20,
		'step'    => 1,
		'unit'    => 'px',
	],
	[
		'key'     => 'plainlist_setting_label_weight',
		'type'    => 'select',
		'label'   => 'Item Label Weight',
		'desc'    => 'Font weight of the (unselected) item labels.',
		'default' => '400',
		'options' => [
			'400' => 'Normal',
			'500' => 'Medium',
			'600' => 'Semibold',
			'700' => 'Bold',
		],
	],

	// ── Spacing & Layout ─────────────────────────────────────────────
	[
		'key'     => 'plainlist_setting_max_width',
		'type'    => 'number',
		'label'   => 'Max Content Width (px)',
		'desc'    => 'Maximum width of the builder content area. 0 = full width.',
		'default' => '680',
		'min'     => 0,
		'max'     => 1400,
		'step'    => 10,
	],
	[
		'key'     => 'plainlist_setting_section_gap',
		'type'    => 'range',
		'label'   => 'Section Gap (px)',
		'desc'    => 'Vertical space between each section.',
		'default' => '32',
		'min'     => 8,
		'max'     => 80,
		'step'    => 4,
	],
	[
		'key'     => 'plainlist_setting_item_gap',
		'type'    => 'range',
		'label'   => 'Item Row Gap (px)',
		'desc'    => 'Vertical spacing between individual items in a list.',
		'default' => '10',
		'min'     => 2,
		'max'     => 32,
		'step'    => 2,
	],
	[
		'key'     => 'plainlist_setting_columns',
		'type'    => 'select',
		'label'   => 'Item Columns',
		'desc'    => 'How many columns to use for items within each section.',
		'default' => '1',
		'options' => [
			'1' => '1 column (single column list)',
			'2' => '2 columns',
			'3' => '3 columns',
			'auto' => 'Auto (responsive)',
		],
	],

	// ── Section Dividers ─────────────────────────────────────────────
	[
		'key'          => 'plainlist_setting_show_dividers',
		'type'         => 'toggle',
		'label'        => 'Show Section Dividers',
		'desc'         => 'Display a horizontal rule between each ingredient section.',
		'default'      => 'yes',
		'toggle_label' => 'Show dividers',
	],
	[
		'key'          => 'plainlist_setting_show_section_icons',
		'type'         => 'toggle',
		'label'        => 'Show Section Icons',
		'desc'         => 'Show a FontAwesome icon alongside each section title.',
		'default'      => 'yes',
		'toggle_label' => 'Show icons',
	],
	[
		'key'          => 'plainlist_setting_show_item_count',
		'type'         => 'toggle',
		'label'        => 'Show Item Count in Section Heading',
		'desc'         => 'Show how many items are available in each section heading.',
		'default'      => 'no',
		'toggle_label' => 'Show count badge',
	],
	[
		'key'          => 'plainlist_setting_show_summary',
		'type'         => 'toggle',
		'label'        => 'Show Selection Summary',
		'desc'         => 'Show a running summary of selected items below the builder.',
		'default'      => 'yes',
		'toggle_label' => 'Show summary',
	],
	[
		'key'          => 'plainlist_setting_show_reset',
		'type'         => 'toggle',
		'label'        => 'Show Reset Button',
		'desc'         => 'Show a button to clear all selections.',
		'default'      => 'yes',
		'toggle_label' => 'Show reset button',
	],

	// ── Step Mode ─────────────────────────────────────────────────────
	[
		'key'     => 'plainlist_setting_step_btn_label_next',
		'type'    => 'text',
		'label'   => 'Step Mode — Next Button Label',
		'desc'    => 'Text for the "Next" button in step-by-step mode.',
		'default' => 'Next →',
	],
	[
		'key'     => 'plainlist_setting_step_btn_label_prev',
		'type'    => 'text',
		'label'   => 'Step Mode — Prev Button Label',
		'desc'    => 'Text for the "Previous" button in step-by-step mode.',
		'default' => '← Back',
	],
	[
		'key'          => 'plainlist_setting_step_show_progress',
		'type'         => 'toggle',
		'label'        => 'Step Mode — Show Progress Indicator',
		'desc'         => 'Show "Step 2 of 6" progress text in step-by-step mode.',
		'default'      => 'yes',
		'toggle_label' => 'Show step progress',
	],
	[
		'key'          => 'plainlist_setting_step_require_selection',
		'type'         => 'toggle',
		'label'        => 'Step Mode — Require Selection to Advance',
		'desc'         => 'Prevent moving to next step until an item is chosen (for exclusive sections).',
		'default'      => 'no',
		'toggle_label' => 'Require selection before Next',
	],

	// ── Text Customization ────────────────────────────────────────────
	[
		'key'         => 'plainlist_setting_intro_text',
		'type'        => 'text_wide',
		'label'       => 'Intro / Instructions Text',
		'desc'        => 'Optional text shown at the very top of the builder.',
		'default'     => '',
		'placeholder' => 'e.g. Choose your ingredients below.',
	],
	[
		'key'     => 'plainlist_setting_footer_note',
		'type'    => 'textarea',
		'label'   => 'Footer Note',
		'desc'    => 'Optional note or disclaimer shown below the builder. Supports basic HTML.',
		'default' => '',
		'rows'    => 2,
	],
	[
		'key'         => 'plainlist_setting_summary_heading',
		'type'        => 'text',
		'label'       => 'Summary Heading Text',
		'desc'        => 'Heading shown above the selection summary.',
		'default'     => 'Your Selection',
		'placeholder' => 'e.g. Your Pizza',
	],
	[
		'key'         => 'plainlist_setting_reset_label',
		'type'        => 'text',
		'label'       => 'Reset Button Label',
		'desc'        => 'Text for the reset / clear all button.',
		'default'     => 'Clear all',
		'placeholder' => 'e.g. Start over',
	],

	// ── Add-to-Cart Button (PizzaTierPro) ───────────────────────────
	// These style the WooCommerce checkout bar that PizzaTierPro renders
	// for this template. They have no visible effect unless PizzaTierPro
	// + WooCommerce are active.
	[
		'key'         => 'plainlist_setting_cart_btn_text',
		'type'        => 'text',
		'label'       => 'Add-to-Cart — Button Text',
		'desc'        => 'CTA label shown on the checkout bar. Requires PizzaTierPro + WooCommerce.',
		'default'     => 'Add to Cart',
		'placeholder' => 'e.g. Add to Order',
	],
	[
		'key'     => 'plainlist_setting_cart_btn_style',
		'type'    => 'select',
		'label'   => 'Add-to-Cart — Button Style',
		'desc'    => 'Overall visual style of the CTA button.',
		'default' => 'solid',
		'options' => [
			'solid'   => 'Solid — filled background',
			'outline' => 'Outline — border only, fills on hover',
			'link'    => 'Text link — minimal, underlined',
		],
	],
	[
		'key'     => 'plainlist_setting_cart_btn_size',
		'type'    => 'select',
		'label'   => 'Add-to-Cart — Button Size',
		'desc'    => 'Padding and font size of the CTA button.',
		'default' => 'medium',
		'options' => [
			'small'  => 'Small',
			'medium' => 'Medium',
			'large'  => 'Large',
		],
	],
	[
		'key'     => 'plainlist_setting_cart_btn_bg',
		'type'    => 'color',
		'label'   => 'Add-to-Cart — Button Color',
		'desc'    => 'Solid background colour (and the border/text colour for the Outline and Text-link styles).',
		'default' => '#1a1a1a',
	],
	[
		'key'     => 'plainlist_setting_cart_btn_text_color',
		'type'    => 'color',
		'label'   => 'Add-to-Cart — Button Text Color',
		'desc'    => 'Label colour on the Solid button (and the hover-fill text colour for Outline).',
		'default' => '#ffffff',
	],
	[
		'key'     => 'plainlist_setting_cart_btn_radius',
		'type'    => 'range',
		'label'   => 'Add-to-Cart — Corner Radius (px)',
		'desc'    => 'Roundness of the button corners. 0 = square.',
		'default' => '4',
		'min'     => 0,
		'max'     => 32,
		'step'    => 1,
		'unit'    => 'px',
	],
	[
		'key'          => 'plainlist_setting_cart_btn_full_width',
		'type'         => 'toggle',
		'label'        => 'Add-to-Cart — Full Width',
		'desc'         => 'Stretch the CTA button across the full width of the checkout bar.',
		'default'      => 'no',
		'toggle_label' => 'Make button full width',
	],
];
