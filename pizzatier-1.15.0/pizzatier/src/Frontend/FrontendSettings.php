<?php
namespace PizzaTier\Frontend;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * FrontendSettings — reads the new Settings page options and applies them
 * to the front-end: inline CSS variables, localised JS data, and wp_head hooks.
 *
 * Hooked in Plugin::register_services():
 *   wp_enqueue_scripts  → inject_inline_styles()   (inline <style> after scripts enqueued)
 *   wp_enqueue_scripts  → apply_performance()       (lazy-load / preload / defer flags)
 */
class FrontendSettings {

	// ─── Helpers ──────────────────────────────────────────────────────────────

	private static function g( string $key, string $default = '' ): string {
		return (string) get_option( $key, $default );
	}

	private static function gb( string $key, string $default = 'no' ): bool {
		return self::g( $key, $default ) === 'yes';
	}

	private static function gi( string $key, int $default = 0 ): int {
		return (int) get_option( $key, $default );
	}

	// ─── Main entry points ────────────────────────────────────────────────────

	/**
	 * Inject inline CSS into the pizzatier stylesheet handle.
	 *
	 * Carries two things:
	 *   1. Layer inset CSS — Crust Padding, Sauce Padding, Cheese Distance and
	 *      Cheese Padding from the Settings page, applied to the layer nodes
	 *      every template renders ([data-layer-id="layer-*"] divs created by
	 *      each template's PizzaStack, plus Scaffold's .sc-layer--* <img>s).
	 *   2. Template-generated CSS from the active template's
	 *      pztp-template-css.php file.
	 *
	 * Note: the old Typography / Colour Palette / Spacing / Topping Display
	 * CSS generation was removed in 1.5.0 along with those settings sections.
	 */
	public function inject_inline_styles(): void {

		// ── Layer inset CSS (Crust / Sauce / Cheese settings) ───────────
		$layer_css = $this->build_layer_inset_css();

		// ── Template-specific generated CSS (from pztp-template-css.php) ─
		$template_generated_css = '';
		$loader = new \PizzaTier\Template\TemplateLoader();
		$active_slug = $loader->get_active_slug();
		$css_file = $loader->get_template_file( 'pztp-template-css.php', $active_slug );
		if ( file_exists( $css_file ) ) {
			include_once $css_file;
			$fn = 'pizzatier_template_' . sanitize_key( $active_slug ) . '_generated_css';
			if ( function_exists( $fn ) ) {
				$template_generated_css = (string) $fn();
			}
		}

		$all_css = $layer_css . $template_generated_css;
		if ( $all_css === '' ) { return; }

		wp_add_inline_style( 'pizzatier-css', $all_css );
	}

	/**
	 * Build the inset CSS that makes the Crust / Sauce & Cheese padding
	 * settings actually apply to the rendered pizza layers.
	 *
	 * Geometry (all values are px insets from the stage edge):
	 *   crust   = Crust Padding
	 *   sauce   = Crust Padding + Sauce Padding   (sauce sits inside crust)
	 *   cheese  = Cheese Distance from Edge       (absolute, per its label)
	 *   topping = cheese inset + Cheese Padding   (toppings sit inside cheese)
	 *
	 * Every template's layer engine creates absolutely-positioned nodes with
	 * data-layer-id="layer-crust|layer-sauce|layer-cheese|layer-topping-*";
	 * Scaffold uses static <img class="sc-layer sc-layer--*"> slots instead.
	 * Both are targeted. width/height are set via calc() because replaced
	 * elements (Scaffold's <img>s) don't stretch between offsets the way
	 * non-replaced divs do.
	 *
	 * @return string CSS, empty when all paddings are 0.
	 */
	private function build_layer_inset_css(): string {
		$px = static function ( string $key ): int {
			return max( 0, (int) preg_replace( '/[^0-9]/', '', (string) get_option( $key, '0' ) ) );
		};

		$crust_pad   = $px( 'pizzatier_setting_crust_padding' );
		$sauce_pad   = $px( 'pizzatier_setting_sauce_padding' );
		$cheese_dist = $px( 'pizzatier_cheese_setting_cheesedistance' );
		$cheese_pad  = $px( 'pizzatier_setting_cheese_padding' );

		$insets = [
			'crust'   => $crust_pad,
			'sauce'   => $crust_pad + $sauce_pad,
			'cheese'  => $cheese_dist,
			'topping' => $cheese_dist + $cheese_pad,
		];

		$css = '';
		foreach ( $insets as $layer => $n ) {
			if ( $n <= 0 ) { continue; }
			$sel = ( $layer === 'topping' )
				? '[data-layer-id^="layer-topping"],.sc-layer--topping'
				: '[data-layer-id="layer-' . $layer . '"],.sc-layer--' . $layer;
			$css .= $sel . '{'
				.  'inset:' . $n . 'px!important;'
				.  'width:calc(100% - ' . ( 2 * $n ) . 'px)!important;'
				.  'height:calc(100% - ' . ( 2 * $n ) . 'px)!important;'
				.  '}';
		}
		return $css;
	}

	/**
	 * Performance-related enqueue hooks:
	 * - Disable all plugin CSS if requested
	 * - Add preload hints for builder CSS
	 * - Lazy-load flag is applied via CSS (native loading="lazy" is already on img tags)
	 */
	public function apply_performance(): void {
		// Disable all plugin CSS
		if ( self::gb( 'pizzatier_setting_adv_disable_css' ) ) {
			wp_dequeue_style( 'pizzatier-css' );
			wp_dequeue_style( 'pizzatier-bootstrap-grid' );
		}

		// Preload hint for critical CSS
		if ( self::gb( 'pizzatier_setting_perf_preload_assets' ) ) {
			add_action( 'wp_head', function () {
				$css_url = PIZZATIER_ASSETS_URL . 'css/pizzatier.css';
				echo '<link rel="preload" href="' . esc_url( $css_url ) . '" as="style">' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			}, 1 );
		}

		// Lazy-load topping images — add a body class, JS/CSS handle the rest
		if ( self::gb( 'pizzatier_setting_perf_lazy_load', 'yes' ) ) {
			add_filter( 'body_class', function ( $classes ) {
				$classes[] = 'pzl-lazy-load';
				return $classes;
			} );
		}

		// Reduce motion body class
		if ( self::gb( 'pizzatier_setting_a11y_reduce_motion' ) ) {
			add_filter( 'body_class', function ( $classes ) {
				$classes[] = 'pzl-reduce-motion';
				return $classes;
			} );
		}

		// High contrast body class
		if ( self::gb( 'pizzatier_setting_a11y_high_contrast' ) ) {
			add_filter( 'body_class', function ( $classes ) {
				$classes[] = 'pzl-high-contrast';
				return $classes;
			} );
		}

		// Debug mode body class
		if ( self::gb( 'pizzatier_setting_adv_debug_mode' ) ) {
			add_filter( 'body_class', function ( $classes ) {
				$classes[] = 'pzl-debug-mode';
				return $classes;
			} );
		}
	}

	/**
	 * Localise JS data to the builder scripts on the frontend.
	 * Passes Customer Experience strings, toast settings, max-topping warning, etc.
	 * Attached to wp_enqueue_scripts (runs after enqueue_frontend).
	 */
	public function localise_js_data(): void {
		// Only localise if a pizzatier JS handle is actually enqueued
		if ( ! wp_script_is( 'pizzatier-js', 'enqueued' ) ) { return; }

		$data = [
			// Customer Experience strings
			'toastStyle'        => self::g( 'pizzatier_setting_cx_toast_style',       'bottom-right' ),
			'toastDuration'     => self::gi( 'pizzatier_setting_cx_toast_duration',   2000 ),
			'textAdded'         => self::g( 'pizzatier_setting_cx_text_added',        'Added to your pizza!' ),
			'textRemoved'       => self::g( 'pizzatier_setting_cx_text_removed',      'Removed from your pizza.' ),
			'textMaxToppings'   => self::g( 'pizzatier_setting_cx_text_max_toppings', 'You\'ve reached the maximum number of toppings.' ),
			'showStartOver'     => self::gb( 'pizzatier_setting_cx_show_start_over', 'yes' ) ? 'yes' : 'no',
			'startOverLabel'    => self::g( 'pizzatier_setting_cx_start_over_label',  'Start Over' ),
			'showSummaryPanel'  => self::gb( 'pizzatier_setting_cx_show_summary' ) ? 'yes' : 'no',
			'showReviewModal'   => self::gb( 'pizzatier_setting_cx_review_modal' ) ? 'yes' : 'no',
			'showSpecialInstr'  => self::gb( 'pizzatier_setting_cx_special_instructions' ) ? 'yes' : 'no',
			'specialInstrPlaceholder' => self::g( 'pizzatier_setting_cx_special_instr_placeholder', 'Any special requests? (optional)' ),
			'specialInstrMaxLen'=> self::gi( 'pizzatier_setting_cx_special_instr_max', 300 ),
			// Cart/WooCommerce — defaults here, filterable so PizzaTierPro can override.
			// Pro hooks into 'pizzatier_js_cart_data' to supply live values.
			'addToCartLabel'    => (string) apply_filters( 'pizzatier_cart_btn_text',       'Add to Cart' ),
			'showCartBtn'       => apply_filters( 'pizzatier_show_cart_btn',        false ) ? 'yes' : 'no',
			'requireCrust'      => apply_filters( 'pizzatier_require_crust',         false ) ? 'yes' : 'no',
			'requireSauce'      => apply_filters( 'pizzatier_require_sauce',         false ) ? 'yes' : 'no',
			// Topping display
			'toppingPlacement'  => self::g( 'pizzatier_setting_topping_placement', 'scattered' ),
			'toppingVisSize'    => self::gi( 'pizzatier_setting_topping_vis_size', 20 ),
			'toppingShowBadge'  => self::gb( 'pizzatier_setting_topping_show_badge' ) ? 'yes' : 'no',
			// Builder behaviour
			'stepByStep'        => self::gb( 'pizzatier_setting_layout_step_by_step' ) ? 'yes' : 'no',
			'autoAdvance'       => self::gb( 'pizzatier_setting_layout_auto_advance' ) ? 'yes' : 'no',
			// Advanced
			'debugMode'         => self::gb( 'pizzatier_setting_adv_debug_mode' ) ? 'yes' : 'no',
			'logLevel'          => self::g( 'pizzatier_setting_adv_log_level', 'off' ),
			// Enabled topping coverage fractions
			'enabledFractions'  => function_exists( 'pz_get_enabled_fractions' ) ? pz_get_enabled_fractions() : [ 'whole', 'half-left', 'half-right', 'quarter-top-left', 'quarter-top-right', 'quarter-bottom-left', 'quarter-bottom-right' ],
		];

		wp_localize_script( 'pizzatier-js', 'pizzatierSettings', $data );

		// Also attach to all template script handles so they pick up settings regardless of load order
		$template_handles = [
			'pizzatier-template-commandcenter',
			'pizzatier-template-metro',
			'pizzatier-template-nightpie',
			'pizzatier-template-rustic',
			'pizzatier-template-pocketpie',
			'pizzatier-template-scaffold',
			'pizzatier-template-plainlist',
		];
		foreach ( $template_handles as $handle ) {
			if ( wp_script_is( $handle, 'enqueued' ) ) {
				wp_localize_script( $handle, 'pizzatierSettings', $data );
			}
		}

		// Fornaia / rustic template — pass copy label overrides to JS
		if ( wp_script_is( 'pizzatier-template-rustic', 'enqueued' ) ) {
			$rustic_labels = [
				'addLabel'     => (string) get_option( 'rustic_setting_add_label',    'Add' ),
				'removeLabel'  => (string) get_option( 'rustic_setting_remove_label', 'Remove' ),
				'chooseLabel'  => (string) get_option( 'rustic_setting_choose_label', 'Choose' ),
				'resetLabel'   => (string) get_option( 'rustic_setting_reset_label',  'Reset' ),
			];
			wp_localize_script( 'pizzatier-template-rustic', 'pizzatierRusticSettings', $rustic_labels );
		}
	}

	/**
	 * Apply custom tab order from settings to the pizzatier_tab_order filter.
	 * Hooked in Plugin.php.
	 */
	public function apply_tab_order( array $tabs, string $instance_id ): array {
		$custom_order = self::g( 'pizzatier_setting_layout_tab_order', '' );
		if ( ! $custom_order ) { return $tabs; }

		$ordered = array_map( 'trim', explode( ',', $custom_order ) );
		$ordered = array_filter( $ordered ); // remove empties

		// Reorder: start with items listed in settings, then append any unlisted ones
		$result = [];
		foreach ( $ordered as $t ) {
			if ( in_array( $t, $tabs, true ) ) {
				$result[] = $t;
			}
		}
		foreach ( $tabs as $t ) {
			if ( ! in_array( $t, $result, true ) ) {
				$result[] = $t;
			}
		}
		return $result;
	}

	/**
	 * Apply query-time settings when CPT posts are fetched for builder rendering.
	 * Hooked to pizzatier_query_args_toppings filter.
	 *
	 * @param array  $args   WP_Query args
	 * @param string $type   CPT type slug
	 */
	public function apply_sort_filter( array $args, string $type ): array {
		if ( $type !== 'toppings' ) { return $args; }

		$sort_order = self::g( 'pizzatier_setting_topping_sort', 'menu' );

		switch ( $sort_order ) {
			case 'alpha_asc':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;
			case 'alpha_desc':
				$args['orderby'] = 'title';
				$args['order']   = 'DESC';
				break;
			default: // 'menu' = WordPress menu_order
				$args['orderby'] = 'menu_order title';
				$args['order']   = 'ASC';
		}

		return $args;
	}

	/**
	 * Inject a11y + performance inline CSS rules (reduce-motion, high-contrast, etc).
	 * Uses body-class-triggered rules so they don't activate unless the setting is on.
	 */
	public function inject_a11y_css(): void {
		$css = '';

		// Reduce motion — disable all PizzaTier animations
		$css .= '.pzl-reduce-motion .cb-layer-div img,'
			.  '.pzl-reduce-motion .cb-fly-clone,'
			.  '.pzl-reduce-motion .cb-toast{'
			.      'transition:none!important;animation:none!important;opacity:1!important;transform:none!important;'
			.  '}';

		// High contrast
		$css .= '.pzl-high-contrast .cb-root{'
			.      '--cb-border:rgba(0,0,0,0.5);--cb-text:#000000;--cb-bg:#ffffff;--cb-surface:#ffffff;'
			.  '}';
		$css .= '.pzl-high-contrast .cb-card{'
			.      'border-width:2px!important;border-color:#000!important;'
			.  '}';
		$css .= '.pzl-high-contrast .cb-card--selected{'
			.      'outline:3px solid #0000ff!important;'
			.  '}';

		// Debug mode — outline all builder elements
		$css .= '.pzl-debug-mode .cb-root *{'
			.      'outline:1px solid rgba(255,0,0,0.3);'
			.  '}';

		// Lazy load — native loading attr applied to all builder images
		$css .= '.pzl-lazy-load .cb-card__thumb img,'
			.  '.pzl-lazy-load .cb-layer-div img{'
			.      'content-visibility:auto;'
			.  '}';

		wp_add_inline_style( 'pizzatier-css', $css );
	}
}
