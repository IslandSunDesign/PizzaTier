<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

/**
 * PizzaTier Uninstall
 *
 * Removes all plugin options, CPT posts (and their postmeta), and
 * transients from the database.
 */

// ── Core plugin options (mirrors Settings::OPTIONS) ────────────────────
$pizzatier_option_keys = [
	// Template
	'pizzatier_setting_global_template',
	// Pizza display
	'pizzatier_setting_pizza_size_max',
	'pizzatier_setting_pizza_size_min',
	'pizzatier_setting_pizza_border',
	'pizzatier_setting_pizza_border_color',
	'pizzatier_setting_global_color',
	// Pizza shape
	'pizzatier_setting_pizza_shape',
	'pizzatier_setting_pizza_aspect',
	'pizzatier_setting_pizza_radius',
	// Layer animation
	'pizzatier_setting_layer_anim',
	'pizzatier_setting_layer_anim_speed',
	// Layer defaults
	'pizzatier_setting_crust_defaultcrust',
	'pizzatier_setting_sauce_defaultsauce',
	'pizzatier_setting_cheese_defaultcheese',
	'pizzatier_setting_drizzle_defaultdrizzle',
	'pizzatier_setting_cut_defaultcut',
	// Crust
	'pizzatier_setting_crust_aspectratio',
	'pizzatier_setting_crust_padding',
	// Sauce
	'pizzatier_setting_sauce_padding',
	// Cheese
	'pizzatier_cheese_setting_cheesedistance',
	'pizzatier_setting_cheese_padding',
	// Toppings
	'pizzatier_setting_topping_maxtoppings',
	'pizzatier_setting_topping_fractions',
	// Display features
	'pizzatier_setting_show_thumbnails',
	'pizzatier_setting_element_style_layers',
	'pizzatier_setting_element_style_toppings',
	'pizzatier_setting_element_style_topping_choice_menu',
	// Branding
	'pizzatier_setting_branding_altlogo',
	'pizzatier_setting_branding_logo_width',
	'pizzatier_setting_branding_logo_height',
	'pizzatier_setting_branding_logo_alt',
	'pizzatier_setting_branding_tagline',
	'pizzatier_setting_branding_primary_color',
	'pizzatier_setting_branding_secondary_color',
	'pizzatier_setting_branding_footer_text',
	'pizzatier_setting_branding_menu_title',
	'pizzatier_setting_branding_header_custom_content',
	// Plugin settings
	'pizzatier_setting_settings_demonotice',
	'pizzatier_setting_global_help_content',
	'pizzatier_setting_disable_content_hub',
	'pizzatier_setting_require_complete_data',
	// Builder Layout & Behaviour
	'pizzatier_setting_layout_mode',
	'pizzatier_setting_layout_builder_width',
	'pizzatier_setting_layout_mobile_bp',
	'pizzatier_setting_layout_mobile',
	'pizzatier_setting_layout_step_by_step',
	'pizzatier_setting_layout_auto_advance',
	'pizzatier_setting_layout_tab_order',
	'pizzatier_setting_layout_hide_empty',
	'pizzatier_setting_layout_keyboard_nav',
	'pizzatier_setting_layout_sticky_header',
	// Pricing & Cart
	'pizzatier_setting_price_display_mode',
	'pizzatier_setting_price_base',
	'pizzatier_setting_price_currency_pos',
	'pizzatier_setting_price_update_anim',
	// Typography
	'pizzatier_setting_typo_font_family',
	'pizzatier_setting_typo_google_font',
	'pizzatier_setting_typo_base_size',
	'pizzatier_setting_typo_heading_fw',
	'pizzatier_setting_typo_label_size',
	'pizzatier_setting_typo_price_size',
	'pizzatier_setting_typo_btn_fw',
	'pizzatier_setting_typo_letter_sp',
	'pizzatier_setting_typo_text_transform',
	// Global Colour Palette
	'pizzatier_setting_color_bg',
	'pizzatier_setting_color_menu_bg',
	'pizzatier_setting_color_card_bg',
	'pizzatier_setting_color_card_border',
	'pizzatier_setting_color_selected',
	'pizzatier_setting_color_tab_bg',
	'pizzatier_setting_color_tab_active',
	'pizzatier_setting_color_tab_text',
	'pizzatier_setting_color_btn_bg',
	'pizzatier_setting_color_btn_text',
	'pizzatier_setting_color_btn2_bg',
	'pizzatier_setting_color_body_text',
	'pizzatier_setting_color_muted_text',
	'pizzatier_setting_color_error',
	'pizzatier_setting_color_success',
	// Spacing & Borders
	'pizzatier_setting_spacing_outer_pad',
	'pizzatier_setting_spacing_grid_gap',
	'pizzatier_setting_spacing_card_pad',
	'pizzatier_setting_spacing_card_radius',
	'pizzatier_setting_spacing_card_border',
	'pizzatier_setting_spacing_btn_radius',
	'pizzatier_setting_spacing_tab_height',
	'pizzatier_setting_spacing_shadow',
	'pizzatier_setting_spacing_shadow_css',
	'pizzatier_setting_spacing_divider',
	// Topping Display
	'pizzatier_setting_topping_thumb_size',
	'pizzatier_setting_topping_thumb_custom',
	'pizzatier_setting_topping_cols_desktop',
	'pizzatier_setting_topping_cols_mobile',
	'pizzatier_setting_topping_placement',
	'pizzatier_setting_topping_vis_size',
	'pizzatier_setting_topping_vis_opacity',
	'pizzatier_setting_topping_show_badge',
	'pizzatier_setting_topping_group_cats',
	'pizzatier_setting_topping_sort',
	// Accessibility & Performance
	'pizzatier_setting_a11y_reduce_motion',
	'pizzatier_setting_a11y_high_contrast',
	'pizzatier_setting_a11y_focus_ring',
	'pizzatier_setting_a11y_aria_lang',
	'pizzatier_setting_perf_lazy_load',
	'pizzatier_setting_perf_preload_assets',
	'pizzatier_setting_perf_img_format',
	'pizzatier_setting_perf_cache',
	// Customer Experience
	'pizzatier_setting_cx_show_summary',
	'pizzatier_setting_cx_toast_style',
	'pizzatier_setting_cx_toast_duration',
	'pizzatier_setting_cx_text_added',
	'pizzatier_setting_cx_text_removed',
	'pizzatier_setting_cx_text_max_toppings',
	'pizzatier_setting_cx_show_start_over',
	'pizzatier_setting_cx_start_over_label',
	'pizzatier_setting_cx_special_instructions',
	'pizzatier_setting_cx_special_instr_placeholder',
	'pizzatier_setting_cx_special_instr_max',
	'pizzatier_setting_cx_review_modal',
	// Advanced & Developer
	'pizzatier_setting_adv_custom_css',
	'pizzatier_setting_adv_custom_js',
	'pizzatier_setting_adv_debug_mode',
	'pizzatier_setting_adv_disable_css',
	'pizzatier_setting_adv_rest_cache_ttl',
	'pizzatier_setting_adv_log_level',

	// ── Plainlist template settings ─────────────────────────────
	'plainlist_setting_layout_mode',
	'plainlist_setting_accent_color',
	'plainlist_setting_bg_color',
	'plainlist_setting_section_header_color',
	'plainlist_setting_item_text_color',
	'plainlist_setting_divider_color',
	'plainlist_setting_font_family',
	'plainlist_setting_base_font_size',
	'plainlist_setting_heading_size',
	'plainlist_setting_heading_weight',
	'plainlist_setting_text_transform',
	'plainlist_setting_check_style',
	'plainlist_setting_check_size',
	'plainlist_setting_max_width',
	'plainlist_setting_section_gap',
	'plainlist_setting_item_gap',
	'plainlist_setting_columns',
	'plainlist_setting_show_dividers',
	'plainlist_setting_show_section_icons',
	'plainlist_setting_show_prices',
	'plainlist_setting_show_item_count',
	'plainlist_setting_show_summary',
	'plainlist_setting_show_reset',
	'plainlist_setting_step_btn_label_next',
	'plainlist_setting_step_btn_label_prev',
	'plainlist_setting_step_show_progress',
	'plainlist_setting_step_require_selection',
	'plainlist_setting_intro_text',
	'plainlist_setting_footer_note',
	'plainlist_setting_summary_heading',
	'plainlist_setting_reset_label',
	'plainlist_setting_list_style',
	'plainlist_setting_selected_style',
	'plainlist_setting_row_padding',
	'plainlist_setting_label_weight',
	'plainlist_setting_cart_btn_text',
	'plainlist_setting_cart_btn_style',
	'plainlist_setting_cart_btn_size',
	'plainlist_setting_cart_btn_bg',
	'plainlist_setting_cart_btn_text_color',
	'plainlist_setting_cart_btn_radius',
	'plainlist_setting_cart_btn_full_width',

	// ── Rustic template settings ─────────────────────────────────
	'rustic_setting_bg_color',
	'rustic_setting_surface_color',
	'rustic_setting_card_bg_color',
	'rustic_setting_accent_color',
	'rustic_setting_gold_color',
	'rustic_setting_text_color',
	'rustic_setting_muted_text_color',
	'rustic_setting_font_serif',
	'rustic_setting_font_size',
	'rustic_setting_preview_col_width',
	'rustic_setting_pizza_canvas_size',
	'rustic_setting_card_radius',
	'rustic_setting_cards_per_row',
	'rustic_setting_show_step_labels',
	'rustic_setting_show_step_icons',
	'rustic_setting_stepnav_bg',
	'rustic_setting_stepnav_active_color',
	'rustic_setting_preview_bg',
	'rustic_setting_show_badge',
	'rustic_setting_badge_top_text',
	'rustic_setting_badge_main_text',
	'rustic_setting_badge_bottom_text',
	'rustic_setting_pizza_canvas_bg',
	'rustic_setting_show_grain_texture',
	'rustic_setting_show_wood_grain',
	'rustic_setting_show_lined_paper',
	'rustic_setting_show_corner_fold',
	'rustic_setting_uppercase_btns',
	'rustic_setting_btn_style',
	'rustic_setting_card_hover_lift',
	'rustic_setting_order_title',
	'rustic_setting_order_tagline',
	'rustic_setting_add_label',
	'rustic_setting_remove_label',
	'rustic_setting_choose_label',
	'rustic_setting_reset_label',

	// ── Metro template settings ───────────────────────────────────
	'metro_setting_accent_color',
	'metro_setting_background_color',
	'metro_setting_ui_bg_color',
	'metro_setting_container_bg_image',
	'metro_setting_card_bg_color',
	'metro_setting_card_text_color',
	'metro_setting_title_color',
	'metro_setting_border_color',
	'metro_setting_show_borders',
	'metro_setting_heading_font',
	'metro_setting_base_font_size',
	'metro_setting_card_border_radius',
	'metro_setting_card_columns',
	'metro_setting_card_style',
	'metro_setting_footer_note',
	'metro_setting_hero_tagline',
	'metro_setting_layout_mode',
	'metro_setting_section_gap',
	'metro_setting_show_ingredient_count',
	'metro_setting_show_ingredient_prices',
	'metro_setting_show_summary_bar',
	'metro_setting_sticky_visualizer',
	'metro_setting_tab_style',
	'metro_setting_visualizer_size',

	// ── PocketPie template settings ───────────────────────────────
	'pocketpie_setting_default_layout',
	'pocketpie_setting_widget_max_width',
	'pocketpie_setting_pizza_size_cq',
	'pocketpie_setting_pizza_size_ld',
	'pocketpie_setting_pizza_size_sd',
	'pocketpie_setting_pizza_size_sp',
	'pocketpie_setting_theme',
	'pocketpie_setting_color_accent',
	'pocketpie_setting_color_accent2',
	'pocketpie_setting_color_bg',
	'pocketpie_setting_color_bg2',
	'pocketpie_setting_color_bg3',
	'pocketpie_setting_color_border',
	'pocketpie_setting_color_muted',
	'pocketpie_setting_color_success',
	'pocketpie_setting_color_text',
	'pocketpie_setting_font_family',
	'pocketpie_setting_font_custom',
	'pocketpie_setting_font_base_size',
	'pocketpie_setting_chip_cols',
	'pocketpie_setting_chip_hover_anim',
	'pocketpie_setting_chip_radius',
	'pocketpie_setting_chip_show_name',
	'pocketpie_setting_chip_thumb_size',
	'pocketpie_setting_close_on_backdrop',
	'pocketpie_setting_corner_quad_aspect',
	'pocketpie_setting_coverage_reveal',
	'pocketpie_setting_coverage_style',
	'pocketpie_setting_cq_corner_bl',
	'pocketpie_setting_cq_corner_br',
	'pocketpie_setting_cq_corner_tl',
	'pocketpie_setting_cq_corner_tr',
	'pocketpie_setting_cq_panel_max_height',
	'pocketpie_setting_cq_panel_width',
	'pocketpie_setting_cq_trigger_size',
	'pocketpie_setting_custom_css',
	'pocketpie_setting_grain_overlay',
	'pocketpie_setting_label_transform',
	'pocketpie_setting_ld_deck_thumb_width',
	'pocketpie_setting_ld_preview_height',
	'pocketpie_setting_ld_show_sel_label',
	'pocketpie_setting_modal_anim',
	'pocketpie_setting_modal_backdrop',
	'pocketpie_setting_review_btn_label',
	'pocketpie_setting_sd_drawer_max_height',
	'pocketpie_setting_sd_pill_position',
	'pocketpie_setting_sd_pill_style',
	'pocketpie_setting_sd_swipe_close',
	'pocketpie_setting_show_reset',
	'pocketpie_setting_show_review_btn',
	'pocketpie_setting_show_summary_pizza',
	'pocketpie_setting_sp_sheet_max_height',
	'pocketpie_setting_sp_show_step_dots',
	'pocketpie_setting_sp_step_label',
	'pocketpie_setting_sp_swipe_close',
	'pocketpie_setting_summary_show_empty_rows',
	'pocketpie_setting_summary_title',
	'pocketpie_setting_toppings_cols',
	'pocketpie_setting_transition_speed',

	// ── NightPie template settings ────────────────────────────────
	'nightpie_setting_accent_color',
	'nightpie_setting_bg_color',
	'nightpie_setting_surface_color',
	'nightpie_setting_surface_2_color',
	'nightpie_setting_text_color',
	'nightpie_setting_text_muted_color',
	'nightpie_setting_card_border',
	'nightpie_setting_card_border_color',
	'nightpie_setting_font_family',
	'nightpie_setting_base_font_size',
	'nightpie_setting_corner_radius',
	'nightpie_setting_sticky_preview',
	'nightpie_setting_accent_glow',

	// ── Scaffold template settings ────────────────────────────────
	'scaffold_setting_accent_color',
	'scaffold_setting_anim_speed',
	'scaffold_setting_base_font_size',
	'scaffold_setting_bg_color',
	'scaffold_setting_border_color',
	'scaffold_setting_builder_width',
	'scaffold_setting_card_cols',
	'scaffold_setting_card_radius',
	'scaffold_setting_cta_show_icon',
	'scaffold_setting_cta_text',
	'scaffold_setting_custom_css',
	'scaffold_setting_font_custom',
	'scaffold_setting_font_family',
	'scaffold_setting_show_labels',
	'scaffold_setting_summary_title',
	'scaffold_setting_tab_style',
	'scaffold_setting_text_color',
	'scaffold_setting_thumb_size',

	// ── Preview URL (Template page) ───────────────────────────────
	'pizzatier_template_preview_url',

	// ── Plugin state / UI flags ────────────────────────────────────
	'pizzatier_setup_done',
	// Cart & pricing settings, stored as one array option rather than as
	// discrete keys, which is why the loop above never reached it.
	'pizzatier_options',
	// Schema version written by the upgrade routine.
	'pizzatier_db_version',
	'pizzatier_builder_viewed',
	'pizzatier_setting_dark_mode',
	'pizzatier_wizard_done',
];

foreach ( $pizzatier_option_keys as $pizzatier_opt ) {
	delete_option( $pizzatier_opt );
}

// ── Options discovered from the canonical registry ─────────────────────
// The hard-coded list above is retained so uninstall still works if the
// registry file is missing, but it had drifted out of step with the plugin:
// 34 template settings (Colorbox and Command Center in particular) were left
// behind in wp_options, and the ordering settings were never removed at all.
// OptionRegistry reads each template's own pztp-template-options.php, so the
// two can no longer disagree.
$pizzatier_registry = __DIR__ . '/src/Core/OptionRegistry.php';
if ( file_exists( $pizzatier_registry ) ) {
	require_once $pizzatier_registry;

	if ( class_exists( '\PizzaTier\Core\OptionRegistry' ) ) {
		foreach ( \PizzaTier\Core\OptionRegistry::uninstall_keys() as $pizzatier_opt ) {
			delete_option( $pizzatier_opt );
		}
	}
}

// ── Delete all CPT posts and their postmeta ────────────────────────────
$pizzatier_cpt_slugs = [
	'pizzatier_toppings',
	'pizzatier_crusts',
	'pizzatier_sauces',
	'pizzatier_cheeses',
	'pizzatier_drizzles',
	'pizzatier_cuts',
	'pizzatier_sizes',
	'pizzatier_presets',
];

foreach ( $pizzatier_cpt_slugs as $pizzatier_post_type ) {
	$pizzatier_posts = get_posts( [
		'post_type'      => $pizzatier_post_type,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	] );

	foreach ( $pizzatier_posts as $pizzatier_post_id ) {
		wp_delete_post( (int) $pizzatier_post_id, true ); // true = force-delete, skip trash
	}
}

// ── Pizza orders ──────────────────────────────────────────────────────
// Orders are customer transaction records, so they are NOT removed by
// default — a site may still need them for accounting or dispute history
// after the plugin is gone. Deletion is strictly opt-in via
// Settings → Orders ("Delete order history when uninstalling").
if ( get_option( 'pizzatier_setting_delete_orders_on_uninstall', 'no' ) === 'yes' ) {
	// The pzt-* order statuses are registered by the plugin, which is not
	// loaded during uninstall. A get_posts() call with 'any' would therefore
	// resolve to an empty status list and match nothing, so query directly.
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$pizzatier_order_ids = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'pizzatier_order'"
	);

	foreach ( $pizzatier_order_ids as $pizzatier_order_id ) {
		wp_delete_post( (int) $pizzatier_order_id, true );
	}

	// Private customer notes are part of the same store-records set, so they
	// follow the same opt-in rather than lingering as orphaned user meta.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key = '_pzt_customer_private_notes'"
	);

	delete_option( 'pizzatier_order_sequence' );
}

// ── Delete any plugin transients ──────────────────────────────────────
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_pizzatier_%'
	    OR option_name LIKE '_transient_timeout_pizzatier_%'"
);

// ── Clean up legacy pricing post meta (1.1.x and earlier) ─────────────
// The 1.2.0 release moved all pricing into PizzaTier. These keys
// are no longer written, but may exist on layer CPT posts if the site
// previously used the old free-plugin Price Modifier field or the
// dead "Pricing Grid (CSV)" meta box. The CPT delete loop above
// removes them along with their parent posts on a clean uninstall,
// but in case those posts were preserved by other code paths we
// also nuke the keys directly.
// phpcs:disable WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_pizzatier_price'" );
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (
	'topping_cost_csv','crust_cost_csv','sauce_cost_csv','cheese_cost_csv','drizzle_cost_csv'
)" );
// Nutrition & ingredients meta written by the Nutrition meta box / Layer Builder Wizard.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (
	'_pizzatier_ingredients','_pizzatier_serving_size','_pizzatier_calories',
	'_pizzatier_spice_level','_pizzatier_thickness','_pizzatier_diameter_inches',
	'_pizzatier_is_vegetarian','_pizzatier_is_vegan','_pizzatier_is_gluten_free','_pizzatier_is_dairy_free'
)" );
// phpcs:enable WordPress.DB.DirectDatabaseQuery

// ── Ordering settings ─────────────────────────────────────────────────
// Written with a shared prefix by OrderSettings rather than enumerated, so
// they are removed by prefix rather than by name.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'pizzatier_setting_orders_' ) . '%'
	)
);

// ── Cart & pricing post meta ──────────────────────────────────────────
// Product configuration, price grids, presets and the per-serving nutrition
// fields. The CPT loop above removes meta belonging to PizzaTier's own post
// types, but product configuration lives on WooCommerce products, which are
// not ours to delete — so the keys are removed directly.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (
		'_pizzatier_builder_template',
		'_pizzatier_builder_position',
		'_pizzatier_default_layers',
		'_pizzatier_enabled_layers',
		'_pizzatier_preselected_layers',
		'_pizzatier_pricing_mode',
		'_pizzatier_price_grid',
		'_pizzatier_price_grid_flat',
		'_pizzatier_layer_grid',
		'_pizzatier_preset_layers',
		'_pizzatier_layer_image_id',
		'_pizzatier_fat',
		'_pizzatier_carbs',
		'_pizzatier_protein',
		'_pizzatier_sodium',
		'_pizzatier_allergens'
	)"
);

// ── WooCommerce order line-item meta ──────────────────────────────────
// The pizza build recorded against each order line. Removed only when the
// site opted into deleting order records above; otherwise a store that keeps
// its order history would be left with orders whose contents had vanished.
if ( 'yes' === get_option( 'pizzatier_setting_delete_orders_on_uninstall', 'no' ) ) {
	$pizzatier_itemmeta = $wpdb->prefix . 'woocommerce_order_itemmeta';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $pizzatier_itemmeta ) ) ) {
		// The table name is interpolated from $wpdb->prefix and a string literal
		// rather than from the variable above, so no user-controlled value can
		// reach the statement. MySQL has no placeholder form for identifiers.
		$wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifier built from $wpdb->prefix and a literal, never from input. The compared value is prepared.
				"DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key LIKE %s",
				$wpdb->esc_like( '_pizzatier_' ) . '%'
			)
		);
	}
}

// ── Per-user admin state ──────────────────────────────────────────────
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( 'pizzatier_' ) . '%'
	)
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
