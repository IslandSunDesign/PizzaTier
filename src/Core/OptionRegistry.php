<?php
namespace PizzaTier\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Canonical registry of every WordPress option this plugin owns.
 *
 * Before 2.0.4 the option list existed in three hand-maintained copies —
 * Settings::OPTIONS (used by export/import), uninstall.php, and each
 * template's pztp-template-options.php. They had drifted badly: the export
 * whitelist knew about 98 keys while the plugin actually owned 300+, so a
 * site migration silently dropped the settings for six of the eight
 * templates, the whole native-ordering configuration, and most of the
 * global layout / typography / branding groups.
 *
 * This class is now the single source of truth. Template keys are
 * discovered by reading the same pztp-template-options.php files the
 * Templates screen already uses to render and save those fields, so a
 * template can never again ship a setting that migration doesn't know
 * about.
 *
 * Loadable from uninstall.php, where no PIZZATIER_* constants are defined
 * and the autoloader has not run — every path is derived from __DIR__ and
 * every WordPress dependency is function_exists()-guarded. The ABSPATH
 * guard above is safe in that context: uninstall_plugin() includes
 * uninstall.php from inside a fully loaded WordPress, so ABSPATH is always
 * defined by the time this file is required.
 *
 * @package PizzaTier\Core
 */
class OptionRegistry {

	/**
	 * Core (non-template) option keys.
	 *
	 * Includes keys from retired settings groups. They cost nothing on export
	 * (an unset option is skipped) and mean uninstall still cleans up rows
	 * left behind by earlier versions.
	 */
	private const CORE = [
		'pizzatier_cheese_setting_cheesedistance',
		'pizzatier_setting_a11y_aria_lang',
		'pizzatier_setting_a11y_focus_ring',
		'pizzatier_setting_a11y_high_contrast',
		'pizzatier_setting_a11y_reduce_motion',
		'pizzatier_setting_adv_custom_css',
		'pizzatier_setting_adv_custom_js',
		'pizzatier_setting_adv_debug_mode',
		'pizzatier_setting_adv_disable_css',
		'pizzatier_setting_adv_log_level',
		'pizzatier_setting_adv_rest_api_enabled',
		'pizzatier_setting_adv_rest_cache_ttl',
		'pizzatier_setting_branding_altlogo',
		'pizzatier_setting_branding_footer_text',
		'pizzatier_setting_branding_header_custom_content',
		'pizzatier_setting_branding_logo_alt',
		'pizzatier_setting_branding_logo_height',
		'pizzatier_setting_branding_logo_width',
		'pizzatier_setting_branding_menu_title',
		'pizzatier_setting_branding_primary_color',
		'pizzatier_setting_branding_secondary_color',
		'pizzatier_setting_branding_tagline',
		'pizzatier_setting_cheese_defaultcheese',
		'pizzatier_setting_cheese_padding',
		'pizzatier_setting_color_bg',
		'pizzatier_setting_color_body_text',
		'pizzatier_setting_color_btn2_bg',
		'pizzatier_setting_color_btn_bg',
		'pizzatier_setting_color_btn_text',
		'pizzatier_setting_color_card_bg',
		'pizzatier_setting_color_card_border',
		'pizzatier_setting_color_error',
		'pizzatier_setting_color_menu_bg',
		'pizzatier_setting_color_muted_text',
		'pizzatier_setting_color_selected',
		'pizzatier_setting_color_success',
		'pizzatier_setting_color_tab_active',
		'pizzatier_setting_color_tab_bg',
		'pizzatier_setting_color_tab_text',
		'pizzatier_setting_crust_aspectratio',
		'pizzatier_setting_crust_defaultcrust',
		'pizzatier_setting_crust_padding',
		'pizzatier_setting_cut_defaultcut',
		'pizzatier_setting_cx_review_modal',
		'pizzatier_setting_cx_show_start_over',
		'pizzatier_setting_cx_show_summary',
		'pizzatier_setting_cx_special_instr_max',
		'pizzatier_setting_cx_special_instr_placeholder',
		'pizzatier_setting_cx_special_instructions',
		'pizzatier_setting_cx_start_over_label',
		'pizzatier_setting_cx_text_added',
		'pizzatier_setting_cx_text_max_toppings',
		'pizzatier_setting_cx_text_removed',
		'pizzatier_setting_cx_toast_duration',
		'pizzatier_setting_cx_toast_style',
		'pizzatier_setting_dark_mode',
		'pizzatier_setting_delete_orders_on_uninstall',
		'pizzatier_setting_disable_content_hub',
		'pizzatier_setting_drizzle_defaultdrizzle',
		'pizzatier_setting_element_style_layers',
		'pizzatier_setting_element_style_topping_choice_menu',
		'pizzatier_setting_element_style_toppings',
		'pizzatier_setting_global_color',
		'pizzatier_setting_global_help_content',
		// The active template. Without this the destination keeps whatever
		// template it was already on and none of the imported template design
		// settings are visible.
		'pizzatier_setting_global_template',
		'pizzatier_setting_layer_anim',
		'pizzatier_setting_layer_anim_speed',
		'pizzatier_setting_layout_auto_advance',
		'pizzatier_setting_layout_builder_width',
		'pizzatier_setting_layout_hide_empty',
		'pizzatier_setting_layout_keyboard_nav',
		'pizzatier_setting_layout_mobile',
		'pizzatier_setting_layout_mobile_bp',
		'pizzatier_setting_layout_mode',
		'pizzatier_setting_layout_step_by_step',
		'pizzatier_setting_layout_sticky_header',
		'pizzatier_setting_layout_tab_order',
		'pizzatier_setting_perf_cache',
		'pizzatier_setting_perf_img_format',
		'pizzatier_setting_perf_lazy_load',
		'pizzatier_setting_perf_preload_assets',
		'pizzatier_setting_pizza_aspect',
		'pizzatier_setting_pizza_border',
		'pizzatier_setting_pizza_border_color',
		'pizzatier_setting_pizza_radius',
		'pizzatier_setting_pizza_shape',
		'pizzatier_setting_pizza_size_max',
		'pizzatier_setting_pizza_size_min',
		'pizzatier_setting_price_base',
		'pizzatier_setting_price_currency_pos',
		'pizzatier_setting_price_display_mode',
		'pizzatier_setting_price_update_anim',
		'pizzatier_setting_require_complete_data',
		'pizzatier_setting_sauce_defaultsauce',
		'pizzatier_setting_sauce_padding',
		'pizzatier_setting_settings_demonotice',
		'pizzatier_setting_show_thumbnails',
		'pizzatier_setting_spacing_btn_radius',
		'pizzatier_setting_spacing_card_border',
		'pizzatier_setting_spacing_card_pad',
		'pizzatier_setting_spacing_card_radius',
		'pizzatier_setting_spacing_divider',
		'pizzatier_setting_spacing_grid_gap',
		'pizzatier_setting_spacing_outer_pad',
		'pizzatier_setting_spacing_shadow',
		'pizzatier_setting_spacing_shadow_css',
		'pizzatier_setting_spacing_tab_height',
		'pizzatier_setting_topping_cols_desktop',
		'pizzatier_setting_topping_cols_mobile',
		'pizzatier_setting_topping_fractions',
		'pizzatier_setting_topping_group_cats',
		'pizzatier_setting_topping_maxtoppings',
		'pizzatier_setting_topping_placement',
		'pizzatier_setting_topping_show_badge',
		'pizzatier_setting_topping_sort',
		'pizzatier_setting_topping_thumb_custom',
		'pizzatier_setting_topping_thumb_size',
		'pizzatier_setting_topping_vis_opacity',
		'pizzatier_setting_topping_vis_size',
		'pizzatier_setting_typo_base_size',
		'pizzatier_setting_typo_btn_fw',
		'pizzatier_setting_typo_font_family',
		'pizzatier_setting_typo_google_font',
		'pizzatier_setting_typo_heading_fw',
		'pizzatier_setting_typo_label_size',
		'pizzatier_setting_typo_letter_sp',
		'pizzatier_setting_typo_price_size',
		'pizzatier_setting_typo_text_transform',
	];

	/**
	 * Template-prefixed keys that no current pztp-template-options.php
	 * declares — settings retired from a template in an earlier release.
	 * Retained so uninstall removes them and an old export still restores.
	 */
	private const LEGACY_TEMPLATE = [
		'metro_setting_show_ingredient_prices',
		'plainlist_setting_show_prices',
		'pocketpie_setting_coverage_reveal',
		'pocketpie_setting_coverage_style',
		'pocketpie_setting_cq_panel_max_height',
		'pocketpie_setting_cq_panel_width',
		'pocketpie_setting_custom_css',
		'pocketpie_setting_grain_overlay',
		'pocketpie_setting_show_summary_pizza',
		'pocketpie_setting_summary_show_empty_rows',
		'scaffold_setting_custom_css',
	];

	/** Install-state keys: progress flags, counters, schema version. */
	private const STATE = [
		'pizzatier_setup_done',
		'pizzatier_wizard_done',
		'pizzatier_builder_viewed',
		'pizzatier_db_version',
		'pizzatier_order_sequence',
		'pizzatier_template_preview_url',
		'pizzatier_route_change_notice',
	];

	/** Prefix for the native ordering feature's options. */
	private const ORDERS_PREFIX = 'pizzatier_setting_orders_';

	/**
	 * Fallback list of ordering setting keys, used when OrderSettings is not
	 * loadable (i.e. from uninstall.php). Kept in sync with
	 * OrderSettings::defaults(); verified by all_keys() at runtime, which
	 * prefers the live class.
	 */
	private const ORDERS_FALLBACK = [
		'enabled', 'bar_mode', 'button_label',
		'route', 'cart_product_id',
		'require_name', 'require_phone', 'require_email',
		'login_required', 'fulfillment',
		'notes_enabled', 'note_placeholder', 'note_maxlength',
		'quantity_enabled', 'max_quantity', 'size_enabled',
		'request_time', 'rate_limit',
		'notify_admin', 'admin_email', 'webhook_url', 'webhook_secret',
		'confirm_message', 'initial_status',
		'retention_months',
	];

	/** The array option holding the Cart &amp; Pricing screen's settings. */
	public const COMMERCE_OPTION = 'pizzatier_options';

	/**
	 * Options that must not travel with a site export.
	 *
	 * Every other setting describes how the builder behaves and means the same
	 * thing wherever it lands. These two do not:
	 *
	 *   • `webhook_secret` is a shared credential. An export is a file that gets
	 *     emailed, dropped in a support ticket and left in a downloads folder;
	 *     a secret that rides along in it has stopped being a secret, and the
	 *     destination site should be issued its own.
	 *   • `cart_product_id` is a post ID. On the destination it points at
	 *     whatever product happens to hold that ID — quite possibly a real
	 *     product that is not a pizza — so importing it is worse than importing
	 *     nothing and letting the site choose.
	 *
	 * They are still exported nowhere and imported nowhere, but remain in
	 * all_keys() so uninstall still deletes them.
	 *
	 * @var string[]
	 */
	private const NON_PORTABLE = [
		'pizzatier_setting_orders_webhook_secret',
		'pizzatier_setting_orders_cart_product_id',
	];

	/**
	 * Options stored as arrays. Casting these to string on import is what
	 * turned the fulfillment list into the literal word "Array" before 2.0.4.
	 *
	 * @var array<string, string[]> key =&gt; allowed values ([] = free-form array)
	 */
	private const ARRAY_OPTIONS = [
		'pizzatier_setting_topping_fractions' => [
			'whole',
			'half-left', 'half-right',
			'quarter-top-left', 'quarter-top-right',
			'quarter-bottom-left', 'quarter-bottom-right',
		],
		'pizzatier_setting_orders_fulfillment' => [ 'pickup', 'delivery' ],
	];

	/** @var array<string, array>|null Per-request cache of template field defs. */
	private static $template_fields = null;

	/* ═══════════════════════════════════════════════════════════════════
	   KEY LISTS
	   ═══════════════════════════════════════════════════════════════════ */

	/**
	 * Every option key the plugin persists as configuration.
	 *
	 * Excludes install-state keys (see state_keys()) and the commerce array
	 * option, which the Cart &amp; Pricing migration handles on its own.
	 *
	 * @return string[]
	 */
	public static function all_keys(): array {
		$keys = array_merge(
			self::CORE,
			self::LEGACY_TEMPLATE,
			self::order_keys(),
			array_keys( self::template_fields() )
		);

		/**
		 * Filter the list of option keys treated as PizzaTier configuration.
		 *
		 * Add-ons that store their own options can join the export, import and
		 * uninstall passes by appending their keys here.
		 *
		 * @param string[] $keys Option names.
		 */
		if ( function_exists( 'apply_filters' ) ) {
			$keys = apply_filters( 'pizzatier_option_keys', $keys );
		}

		return array_values( array_unique( array_filter( (array) $keys, 'is_string' ) ) );
	}

	/**
	 * Whether an option is safe to carry between sites.
	 *
	 * @since 2.1.0
	 */
	public static function is_portable( string $key ): bool {
		$portable = ! in_array( $key, self::NON_PORTABLE, true );

		/**
		 * Filter whether one option travels with a site export.
		 *
		 * @since 2.1.0
		 *
		 * @param bool   $portable Whether the key may be exported and imported.
		 * @param string $key      Option name.
		 */
		if ( function_exists( 'apply_filters' ) ) {
			$portable = (bool) apply_filters( 'pizzatier_option_is_portable', $portable, $key );
		}

		return $portable;
	}

	/**
	 * Every configuration key minus the ones that must not leave this site.
	 *
	 * This is what export and import walk. Uninstall keeps using all_keys(),
	 * because a key being unsafe to copy has no bearing on whether it should be
	 * cleaned up.
	 *
	 * @since 2.1.0
	 *
	 * @return string[]
	 */
	public static function portable_keys(): array {
		return array_values( array_filter( self::all_keys(), [ __CLASS__, 'is_portable' ] ) );
	}

	/**
	 * Install-state keys — setup progress, counters, schema version.
	 *
	 * @return string[]
	 */
	public static function state_keys(): array {
		return self::STATE;
	}

	/**
	 * Everything uninstall should delete: configuration + state + the
	 * commerce array option.
	 *
	 * @return string[]
	 */
	public static function uninstall_keys(): array {
		return array_values( array_unique( array_merge(
			self::all_keys(),
			self::state_keys(),
			[ self::COMMERCE_OPTION ]
		) ) );
	}

	/**
	 * Ordering-feature option keys, fully prefixed.
	 *
	 * Prefers OrderSettings::all_defaults() so the list cannot drift; falls
	 * back to a static copy when that class is unavailable (uninstall).
	 *
	 * @return string[]
	 */
	public static function order_keys(): array {
		$suffixes = self::ORDERS_FALLBACK;

		if ( class_exists( '\\PizzaTier\\Orders\\OrderSettings' ) ) {
			$defaults = \PizzaTier\Orders\OrderSettings::all_defaults();
			if ( is_array( $defaults ) && $defaults ) {
				$suffixes = array_unique( array_merge( $suffixes, array_keys( $defaults ) ) );
			}
		}

		$out = [];
		foreach ( $suffixes as $suffix ) {
			$out[] = self::ORDERS_PREFIX . $suffix;
		}
		return $out;
	}

	/* ═══════════════════════════════════════════════════════════════════
	   TEMPLATE DISCOVERY
	   ═══════════════════════════════════════════════════════════════════ */

	/**
	 * Every template setting declared by any installed template, as
	 * key =&gt; field definition (type, default, label...).
	 *
	 * Scans the bundled templates/ directory and, when a theme is loaded,
	 * the active theme's pzttemplates/ overrides — the same two locations
	 * the Templates screen reads when rendering and saving these fields.
	 *
	 * Only keys containing "_setting_" are accepted, mirroring the guard in
	 * TemplateChoice::save_template_settings(). That stops a malformed or
	 * hostile template-options file from getting a core option such as
	 * siteurl or admin_email into the export — or, far worse, into the list
	 * uninstall deletes.
	 *
	 * @return array<string, array>
	 */
	public static function template_fields(): array {
		if ( null !== self::$template_fields ) {
			return self::$template_fields;
		}

		$fields = [];

		foreach ( self::template_option_files() as $file ) {
			$defs = include $file;
			if ( ! is_array( $defs ) ) {
				continue;
			}
			foreach ( $defs as $def ) {
				if ( ! is_array( $def ) || empty( $def['key'] ) ) {
					continue;
				}
				$key = (string) $def['key'];
				if ( function_exists( 'sanitize_key' ) ) {
					$key = sanitize_key( $key );
				}
				if ( '' === $key || strpos( $key, '_setting_' ) === false ) {
					continue;
				}
				// First definition wins, so a theme override cannot silently
				// change the declared type of a bundled template's setting.
				if ( ! isset( $fields[ $key ] ) ) {
					$fields[ $key ] = $def;
				}
			}
		}

		self::$template_fields = $fields;
		return $fields;
	}

	/**
	 * Absolute paths of every pztp-template-options.php available.
	 *
	 * @return string[]
	 */
	private static function template_option_files(): array {
		$dirs = [];

		// Theme overrides first — they take precedence on the Templates screen.
		if ( function_exists( 'get_stylesheet_directory' ) && function_exists( 'did_action' ) && did_action( 'after_setup_theme' ) ) {
			$dirs[] = rtrim( (string) get_stylesheet_directory(), '/\\' ) . '/pzttemplates/';
		}

		$dirs[] = defined( 'PIZZATIER_TEMPLATES_DIR' )
			? PIZZATIER_TEMPLATES_DIR
			: dirname( __DIR__, 2 ) . '/templates/';

		$files = [];
		foreach ( $dirs as $dir ) {
			$found = glob( rtrim( $dir, '/\\' ) . '/*/pztp-template-options.php' );
			if ( is_array( $found ) ) {
				$files = array_merge( $files, $found );
			}
		}

		return $files;
	}

	/**
	 * Template settings that hold an image URL, as key =&gt; field definition.
	 * Used by Site Migration to carry background images across sites.
	 *
	 * @return array<string, array>
	 */
	public static function image_option_keys(): array {
		$out = [];
		foreach ( self::template_fields() as $key => $def ) {
			if ( isset( $def['type'] ) && 'image' === $def['type'] ) {
				$out[ $key ] = $def;
			}
		}
		return $out;
	}

	/* ═══════════════════════════════════════════════════════════════════
	   VALUE HANDLING
	   ═══════════════════════════════════════════════════════════════════ */

	/** Whether an option is stored as an array. */
	public static function is_array_option( string $key ): bool {
		return isset( self::ARRAY_OPTIONS[ $key ] );
	}

	/**
	 * Sanitize a value on its way back in from an import, preserving type.
	 *
	 * Until 2.0.4 every non-array option went through wp_kses_post( (string) $value ),
	 * which turned arrays into the word "Array", booleans into '1' or '',
	 * and an unset option's null into an empty string that then shadowed the
	 * option's default. Each type is now handled on its own terms.
	 *
	 * @param string $key   Option name.
	 * @param mixed  $value Raw decoded value.
	 * @return mixed        Sanitized value, or null when the value should be skipped.
	 */
	public static function sanitize_value( string $key, $value ) {
		// An option that was unset on the source site stays unset here, so the
		// destination keeps using its own default.
		if ( null === $value ) {
			return null;
		}

		// Known array options: intersect against their allowed values.
		if ( isset( self::ARRAY_OPTIONS[ $key ] ) ) {
			$allowed = self::ARRAY_OPTIONS[ $key ];
			$clean   = is_array( $value ) ? array_map( 'sanitize_key', $value ) : [];
			$clean   = array_values( array_intersect( $clean, $allowed ) );

			// 'whole' coverage is always available and must never be dropped.
			if ( 'pizzatier_setting_topping_fractions' === $key && ! in_array( 'whole', $clean, true ) ) {
				array_unshift( $clean, 'whole' );
			}
			return $clean;
		}

		return self::sanitize_scalar( $value );
	}

	/**
	 * Recursively sanitize an arbitrary value while preserving its type.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	private static function sanitize_scalar( $value ) {
		if ( is_array( $value ) ) {
			$out = [];
			foreach ( $value as $k => $v ) {
				$key_clean = is_int( $k ) ? $k : sanitize_text_field( (string) $k );
				$out[ $key_clean ] = self::sanitize_scalar( $v );
			}
			return $out;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		return wp_kses_post( (string) $value );
	}
}
