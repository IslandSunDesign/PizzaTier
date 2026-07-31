<?php
/**
 * Checkout Bar Layout Registry.
 *
 * Defines the set of available layouts for the "Order Now" checkout bar and
 * resolves which one should render for the current request. Layouts are
 * decoupled from templates: any layout can be used with any PizzaTier
 * template, and each layout picks up the template's palette via the
 * .pztc-checkout-bar--{template} CSS hook.
 *
 * Resolution order (first hit wins):
 *   1. Child-theme override at {stylesheet}/pizzatier/checkout-bar.php
 *   2. Legacy per-template checkout-bar.php inside the active template folder
 *      (used when the global layout setting is empty / "legacy")
 *   3. Global layout selected in Settings (pizzatier_commerce_checkout_bar_layout)
 *   4. Built-in fallback: the "classic-horizontal" layout partial
 *
 * Adding a new layout is a two-step process:
 *   - add an entry to self::get_layouts()
 *   - drop a matching PHP partial into src/Commerce/CheckoutBar/Layouts/
 *
 * @package PizzaTier\Commerce\CheckoutBar
 */

namespace PizzaTier\Commerce\CheckoutBar;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class LayoutRegistry {

	/** Setting key for the selected global layout slug. */
	public const SETTING_KEY = 'checkout_bar_layout';

	/** Slug indicating "use the per-template legacy checkout-bar.php". */
	public const LEGACY_SLUG = 'legacy';

	/** Slug for the built-in default layout. */
	public const DEFAULT_SLUG = 'classic-horizontal';

	/**
	 * Return the list of available layouts.
	 *
	 * Shape: [ slug => [ 'label' => string, 'description' => string,
	 *                    'file'  => string, 'preview' => string (SVG markup) ] ]
	 * File paths are absolute. The 'preview' SVG is a schematic sketch of the
	 * layout used by the settings picker — it's intentionally minimal so it
	 * shows structure at a glance, not pixel-accurate colours.
	 *
	 * A filter lets third parties register custom layouts.
	 */
	public static function get_layouts(): array {
		$dir = self::layouts_dir();

		$layouts = [
			self::LEGACY_SLUG => [
				'label'       => __( 'Use each template\'s built-in bar (legacy)', 'pizzatier' ),
				'description' => __( 'Render the checkout bar file shipped with the currently active PizzaTier template. Use this to preserve the look you have today.', 'pizzatier' ),
				'file'        => '', // signals "fall through to per-template file"
				'preview'     => self::preview_legacy(),
			],
			'classic-horizontal' => [
				'label'       => __( 'Classic horizontal', 'pizzatier' ),
				'description' => __( 'Single-row bar: size label + price + quantity + CTA, left-to-right. Matches current default behaviour.', 'pizzatier' ),
				'file'        => $dir . 'classic-horizontal.php',
				'preview'     => self::preview_classic_horizontal(),
			],
			'stacked-compact' => [
				'label'       => __( 'Stacked compact', 'pizzatier' ),
				'description' => __( 'Size / price stacked above a full-width CTA. Space-efficient for narrow columns and mobile.', 'pizzatier' ),
				'file'        => $dir . 'stacked-compact.php',
				'preview'     => self::preview_stacked_compact(),
			],
			'split-card' => [
				'label'       => __( 'Split card', 'pizzatier' ),
				'description' => __( 'Elevated card with a left-aligned price block and a right-aligned CTA+quantity cluster. Subtle shadow.', 'pizzatier' ),
				'file'        => $dir . 'split-card.php',
				'preview'     => self::preview_split_card(),
			],
			'sticky-bottom' => [
				'label'       => __( 'Sticky bottom', 'pizzatier' ),
				'description' => __( 'Fixed to the bottom of the viewport; slides up once the customer picks a size. Great on mobile product pages.', 'pizzatier' ),
				'file'        => $dir . 'sticky-bottom.php',
				'preview'     => self::preview_sticky_bottom(),
			],
			'minimal-inline' => [
				'label'       => __( 'Minimal inline', 'pizzatier' ),
				'description' => __( 'Small unboxed row that sits flush with surrounding content. Good for minimalist templates.', 'pizzatier' ),
				'file'        => $dir . 'minimal-inline.php',
				'preview'     => self::preview_minimal_inline(),
			],
			'hero-cta' => [
				'label'       => __( 'Hero CTA', 'pizzatier' ),
				'description' => __( 'Large price up top, oversized CTA beneath. Dominant look for landing-page style embeds.', 'pizzatier' ),
				'file'        => $dir . 'hero-cta.php',
				'preview'     => self::preview_hero_cta(),
			],
		];

		/**
		 * Filter the registered checkout-bar layouts.
		 *
		 * @param array $layouts  See return-type contract in get_layouts() docblock.
		 */
		return (array) apply_filters( 'pizzatier_commerce_checkout_bar_layouts', $layouts );
	}

	/* ── Preview SVG factories ──────────────────────────────────────────────
	 * Each returns a self-contained <svg> string sized 160×80, using
	 * currentColor + an --accent inline var so it inherits the picker card's
	 * palette. Shapes-only — think of them as wireframes. */

	private static function preview_legacy(): string {
		return '<svg viewBox="0 0 160 80" xmlns="http://www.w3.org/2000/svg" class="pztc-layout-preview__svg" aria-hidden="true">'
			. '<rect x="8" y="8" width="144" height="64" rx="6" fill="none" stroke="currentColor" stroke-width="1.2" stroke-dasharray="3 3" opacity="0.5"/>'
			. '<text x="80" y="40" text-anchor="middle" dominant-baseline="central" font-family="sans-serif" font-size="9" fill="currentColor" opacity="0.7">template default</text>'
			. '<text x="80" y="54" text-anchor="middle" dominant-baseline="central" font-family="sans-serif" font-size="7" fill="currentColor" opacity="0.45">(no change)</text>'
			. '</svg>';
	}

	private static function preview_classic_horizontal(): string {
		return '<svg viewBox="0 0 160 80" xmlns="http://www.w3.org/2000/svg" class="pztc-layout-preview__svg" aria-hidden="true">'
			. '<rect x="6" y="26" width="148" height="28" rx="5" fill="currentColor" opacity="0.06"/>'
			. '<rect x="12" y="34" width="28" height="5" rx="1" fill="currentColor" opacity="0.35"/>'
			. '<rect x="12" y="43" width="18" height="7" rx="1" fill="currentColor" opacity="0.85"/>'
			. '<rect x="62" y="36" width="32" height="12" rx="6" fill="currentColor" opacity="0.15"/>'
			. '<rect x="104" y="34" width="44" height="16" rx="4" fill="var(--pztc-picker-accent, #ff4d4d)"/>'
			. '</svg>';
	}

	private static function preview_stacked_compact(): string {
		return '<svg viewBox="0 0 160 80" xmlns="http://www.w3.org/2000/svg" class="pztc-layout-preview__svg" aria-hidden="true">'
			. '<rect x="8" y="10" width="144" height="60" rx="6" fill="currentColor" opacity="0.06"/>'
			. '<rect x="16" y="18" width="28" height="4" rx="1" fill="currentColor" opacity="0.35"/>'
			. '<rect x="118" y="16" width="26" height="8" rx="1" fill="currentColor" opacity="0.85"/>'
			. '<rect x="52" y="34" width="56" height="10" rx="5" fill="currentColor" opacity="0.15"/>'
			. '<rect x="16" y="52" width="128" height="12" rx="3" fill="var(--pztc-picker-accent, #ff4d4d)"/>'
			. '</svg>';
	}

	private static function preview_split_card(): string {
		return '<svg viewBox="0 0 160 80" xmlns="http://www.w3.org/2000/svg" class="pztc-layout-preview__svg" aria-hidden="true">'
			. '<rect x="6" y="14" width="148" height="52" rx="8" fill="currentColor" opacity="0.08"/>'
			. '<rect x="6" y="14" width="148" height="52" rx="8" fill="none" stroke="currentColor" stroke-width="1" opacity="0.2"/>'
			. '<rect x="16" y="26" width="22" height="4" rx="1" fill="currentColor" opacity="0.35"/>'
			. '<rect x="16" y="36" width="38" height="12" rx="1" fill="currentColor" opacity="0.85"/>'
			. '<rect x="70" y="32" width="34" height="14" rx="7" fill="currentColor" opacity="0.15"/>'
			. '<rect x="110" y="30" width="36" height="20" rx="10" fill="var(--pztc-picker-accent, #ff4d4d)"/>'
			. '</svg>';
	}

	private static function preview_sticky_bottom(): string {
		return '<svg viewBox="0 0 160 80" xmlns="http://www.w3.org/2000/svg" class="pztc-layout-preview__svg" aria-hidden="true">'
			. '<rect x="8" y="6" width="144" height="44" rx="3" fill="none" stroke="currentColor" stroke-width="1" stroke-dasharray="2 2" opacity="0.35"/>'
			. '<text x="80" y="28" text-anchor="middle" dominant-baseline="central" font-family="sans-serif" font-size="8" fill="currentColor" opacity="0.5">page content</text>'
			. '<rect x="0" y="54" width="160" height="26" fill="currentColor" opacity="0.08"/>'
			. '<rect x="0" y="54" width="160" height="1" fill="currentColor" opacity="0.25"/>'
			. '<rect x="12" y="62" width="30" height="10" rx="1" fill="currentColor" opacity="0.85"/>'
			. '<rect x="108" y="60" width="40" height="14" rx="7" fill="var(--pztc-picker-accent, #ff4d4d)"/>'
			. '</svg>';
	}

	private static function preview_minimal_inline(): string {
		return '<svg viewBox="0 0 160 80" xmlns="http://www.w3.org/2000/svg" class="pztc-layout-preview__svg" aria-hidden="true">'
			. '<line x1="8" y1="32" x2="152" y2="32" stroke="currentColor" opacity="0.22"/>'
			. '<rect x="12" y="42" width="24" height="4" rx="1" fill="currentColor" opacity="0.35"/>'
			. '<rect x="12" y="50" width="30" height="7" rx="1" fill="currentColor" opacity="0.85"/>'
			. '<rect x="64" y="46" width="30" height="10" rx="5" fill="currentColor" opacity="0.1"/>'
			. '<rect x="108" y="44" width="40" height="14" rx="3" fill="none" stroke="var(--pztc-picker-accent, #ff4d4d)" stroke-width="1.5"/>'
			. '</svg>';
	}

	private static function preview_hero_cta(): string {
		return '<svg viewBox="0 0 160 80" xmlns="http://www.w3.org/2000/svg" class="pztc-layout-preview__svg" aria-hidden="true">'
			. '<rect x="6" y="6" width="148" height="68" rx="8" fill="currentColor" opacity="0.08"/>'
			. '<rect x="6" y="6" width="148" height="68" rx="8" fill="none" stroke="currentColor" stroke-width="1" opacity="0.2"/>'
			. '<rect x="60" y="16" width="40" height="4" rx="1" fill="currentColor" opacity="0.35"/>'
			. '<rect x="50" y="26" width="60" height="14" rx="2" fill="currentColor" opacity="0.9"/>'
			. '<rect x="14" y="50" width="132" height="16" rx="4" fill="var(--pztc-picker-accent, #ff4d4d)"/>'
			. '</svg>';
	}

	/**
	 * Return only the user-selectable options suitable for a dropdown.
	 * Shape: [ slug => label ]
	 */
	public static function get_select_options(): array {
		$out = [];
		foreach ( self::get_layouts() as $slug => $def ) {
			$out[ $slug ] = (string) ( $def['label'] ?? $slug );
		}
		return $out;
	}

	/**
	 * Return the configured global layout slug, with a safe fallback.
	 * Defaults to LEGACY_SLUG so existing installs don't change on upgrade.
	 */
	public static function get_active_slug(): string {
		$slug = (string) pizzatier_get_option( self::SETTING_KEY, self::LEGACY_SLUG );
		if ( ! $slug ) { $slug = self::LEGACY_SLUG; }

		/**
		 * Filter the active layout slug before it is resolved.
		 * Useful for per-request overrides (A/B tests, shortcode atts, etc.).
		 *
		 * @param string $slug
		 */
		$slug = (string) apply_filters( 'pizzatier_commerce_checkout_bar_active_slug', $slug );

		$layouts = self::get_layouts();
		if ( ! isset( $layouts[ $slug ] ) ) {
			$slug = self::DEFAULT_SLUG;
		}
		return $slug;
	}

	/**
	 * Resolve the absolute path to the PHP partial that should render for this request.
	 *
	 * Returns '' when the caller should fall through to the per-template
	 * checkout-bar.php (legacy mode or an unrecognised slug).
	 *
	 * @param string $template_slug  Active PizzaTier template slug, used only
	 *                               for the child-theme override lookup.
	 */
	public static function resolve_partial( string $template_slug = '' ): string {
		// (1) Child-theme override always wins if it exists.
		$override = trailingslashit( get_stylesheet_directory() ) . 'pizzatier/checkout-bar.php';
		if ( file_exists( $override ) ) {
			return $override;
		}

		$slug = self::get_active_slug();

		// (2) Legacy mode — signal the caller to use the template's own file.
		if ( self::LEGACY_SLUG === $slug ) {
			return '';
		}

		$layouts = self::get_layouts();
		$file    = isset( $layouts[ $slug ]['file'] ) ? (string) $layouts[ $slug ]['file'] : '';

		// (3) Selected layout's file, if it actually exists on disk.
		if ( $file && file_exists( $file ) ) {
			return $file;
		}

		// (4) Fall back to the built-in default partial.
		$default = $layouts[ self::DEFAULT_SLUG ]['file'] ?? '';
		if ( $default && file_exists( $default ) ) {
			return $default;
		}

		// (5) All else failed — tell the caller to use the per-template legacy file.
		return '';
	}

	/** Absolute path to the Layouts/ directory, with a trailing slash. */
	private static function layouts_dir(): string {
		return __DIR__ . '/Layouts/';
	}
}
