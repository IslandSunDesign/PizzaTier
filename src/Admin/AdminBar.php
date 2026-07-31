<?php
namespace PizzaTier\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * PizzaTier Admin Bar
 *
 * Top-bar structure:
 *   🍕 PizzaTier  ───────────────────────────────────────── root
 *   ├─ 🏠 Dashboard
 *   │
 *   ├─ ── CONTENT ──
 *   ├─ 🍕 Toppings     [All]  [+ New]
 *   ├─ ⬤  Crusts       [All]  [+ New]
 *   ├─ 🥫 Sauces       [All]  [+ New]
 *   ├─ 🧀 Cheeses      [All]  [+ New]
 *   ├─ 💧 Drizzles     [All]  [+ New]
 *   ├─ ✂  Cuts         [All]  [+ New]
 *   ├─ 📏 Sizes        [All]  [+ New]
 *   │
 *   ├─ ── TOOLS ──
 *   ├─ 📋 Setup Guide
 *   ├─ </> Shortcode Generator
 *   ├─ 🎨 Template
 *   ├─ ⚙  Settings
 *   └─ ❓ Help
 */
class AdminBar {

	/** CPT definitions */
	private const CPTS = [
		'toppings' => [ 'label' => 'Toppings', 'singular' => 'Topping',  'emoji' => '🍕', 'icon' => 'dashicons-tag'              ],
		'crusts'   => [ 'label' => 'Crusts',   'singular' => 'Crust',    'emoji' => '⬤',  'icon' => 'dashicons-admin-page'       ],
		'sauces'   => [ 'label' => 'Sauces',   'singular' => 'Sauce',    'emoji' => '🥫', 'icon' => 'dashicons-portfolio'        ],
		'cheeses'  => [ 'label' => 'Cheeses',  'singular' => 'Cheese',   'emoji' => '🧀', 'icon' => 'dashicons-star-filled'      ],
		'drizzles' => [ 'label' => 'Drizzles', 'singular' => 'Drizzle',  'emoji' => '💧', 'icon' => 'dashicons-admin-customizer' ],
		'cuts'     => [ 'label' => 'Cuts',     'singular' => 'Cut',      'emoji' => '✂',  'icon' => 'dashicons-image-crop'       ],
		'sizes'    => [ 'label' => 'Sizes',    'singular' => 'Size',     'emoji' => '📏', 'icon' => 'dashicons-editor-expand'    ],

	];

	public function register( \WP_Admin_Bar $bar ): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		$hub       = admin_url( 'admin.php?page=pizzatier-content' );
		$dashboard = admin_url( 'admin.php?page=pizzatier' );

		// ── Root ─────────────────────────────────────────────────────────
		$bar->add_menu( [
			'id'    => 'pizzatier',
			'title' => '<span class="ab-icon pzlab-root-icon" aria-hidden="true">🍕</span>'
			         . '<span class="ab-label">PizzaTier</span>',
			'href'  => $dashboard,
			'meta'  => [ 'title' => __( 'PizzaTier Dashboard', 'pizzatier' ) ],
		] );

		// ── Dashboard link ───────────────────────────────────────────────
		$bar->add_menu( [
			'parent' => 'pizzatier',
			'id'     => 'pizzatier-dashboard',
			'title'  => esc_html__( 'Dashboard', 'pizzatier' ),
			'href'   => $dashboard,
			'meta'   => [ 'title' => __( 'PizzaTier Dashboard', 'pizzatier' ) ],
		] );

		// ── CONTENT group separator ──────────────────────────────────────
		$bar->add_menu( [
			'parent' => 'pizzatier',
			'id'     => 'pizzatier-grp-content',
			'title'  => '<span class="pzlab-group-label">' . esc_html__( 'Content', 'pizzatier' ) . '</span>',
			'href'   => $hub,
			'meta'   => [ 'class' => 'pzlab-group-header' ],
		] );

		// ── CPT items (All + +New sub-links) ─────────────────────────────
		foreach ( self::CPTS as $slug => $meta ) {
			$cpt      = 'pizzatier_' . $slug;
			$list_url = add_query_arg( 'pl_cpt', $slug, $hub );
			$new_url  = admin_url( 'post-new.php?post_type=' . $cpt );

			// Parent row — links to "All" in ContentHub
			$bar->add_menu( [
				'parent' => 'pizzatier',
				'id'     => 'pizzatier-cpt-' . $slug,
				'title'  => esc_html( $meta['label'] ),
				'href'   => $list_url,
				'meta'   => [ 'class' => 'pzlab-cpt-row', 'title' => sprintf( /* translators: %s = content type label. */ __( 'Manage %s', 'pizzatier' ), $meta['label'] ) ],
			] );

			// Sub-link: All
			$bar->add_menu( [
				'parent' => 'pizzatier-cpt-' . $slug,
				'id'     => 'pizzatier-cpt-' . $slug . '-all',
				'title'  => sprintf( /* translators: %s = content type label. */ esc_html__( 'All %s', 'pizzatier' ), esc_html( $meta['label'] ) ),
				'href'   => $list_url,
				'meta'   => [ 'title' => sprintf( /* translators: %s = content type label. */ __( 'View all %s', 'pizzatier' ), $meta['label'] ) ],
			] );

			// Sub-link: Add New
			$bar->add_menu( [
				'parent' => 'pizzatier-cpt-' . $slug,
				'id'     => 'pizzatier-cpt-' . $slug . '-new',
				'title'  => sprintf( /* translators: %s = content type name. */ esc_html__( 'Add New %s', 'pizzatier' ), esc_html( $meta['singular'] ) ),
				'href'   => $new_url,
				'meta'   => [ 'title' => sprintf( /* translators: %s = content type name. */ __( 'Add a new %s', 'pizzatier' ), $meta['singular'] ) ],
			] );
		}

		// ── TOOLS group separator ────────────────────────────────────────
		$bar->add_menu( [
			'parent' => 'pizzatier',
			'id'     => 'pizzatier-grp-tools',
			'title'  => '<span class="pzlab-group-label">' . esc_html__( 'Tools', 'pizzatier' ) . '</span>',
			'href'   => '#',
			'meta'   => [ 'class' => 'pzlab-group-header' ],
		] );

		// ── Tool items ───────────────────────────────────────────────────
		$tools = [
			[
				'id'    => 'pizzatier-setup',
				'icon'  => 'dashicons-welcome-learn-more',
				'label' => __( 'Setup Guide', 'pizzatier' ),
				'href'  => admin_url( 'admin.php?page=pizzatier-setup' ),
				'tip'   => __( 'Step-by-step onboarding guide', 'pizzatier' ),
			],
			[
				'id'    => 'pizzatier-shortcodes',
				'icon'  => 'dashicons-editor-code',
				'label' => __( 'Shortcode Generator', 'pizzatier' ),
				'href'  => admin_url( 'admin.php?page=pizzatier-shortcodes' ),
				'tip'   => __( 'Build shortcodes with a visual UI', 'pizzatier' ),
			],
			[
				'id'    => 'pizzatier-template',
				'icon'  => 'dashicons-admin-appearance',
				'label' => __( 'Template', 'pizzatier' ),
				'href'  => admin_url( 'admin.php?page=pizzatier-template' ),
				'tip'   => __( 'Switch or preview templates', 'pizzatier' ),
			],
			[
				'id'    => 'pizzatier-settings',
				'icon'  => 'dashicons-admin-settings',
				'label' => __( 'Settings', 'pizzatier' ),
				'href'  => admin_url( 'admin.php?page=pizzatier-settings' ),
				'tip'   => __( 'Global plugin settings', 'pizzatier' ),
			],
			[
				'id'    => 'pizzatier-help',
				'icon'  => 'dashicons-editor-help',
				'label' => __( 'Help & Reference', 'pizzatier' ),
				'href'  => admin_url( 'admin.php?page=pizzatier-help' ),
				'tip'   => __( 'Full documentation and developer reference', 'pizzatier' ),
			],
		];

		foreach ( $tools as $tool ) {
			$bar->add_menu( [
				'parent' => 'pizzatier',
				'id'     => $tool['id'],
				'title'  => esc_html( $tool['label'] ),
				'href'   => $tool['href'],
				'meta'   => [ 'title' => $tool['tip'] ],
			] );
		}

		// ── Tool sub-links: Shortcode Generator actions ──────────────────
		$sc_types = [
			[ 'label' => __( 'Builder shortcode', 'pizzatier' ),  'hash' => '#builder' ],
			[ 'label' => __( 'Static shortcode', 'pizzatier' ),   'hash' => '#static'  ],
			[ 'label' => __( 'Layer image shortcode', 'pizzatier' ), 'hash' => '#layer' ],
		];
		foreach ( $sc_types as $sc ) {
			$bar->add_menu( [
				'parent' => 'pizzatier-shortcodes',
				'id'     => 'pizzatier-sc-' . sanitize_title( $sc['label'] ),
				'title'  => esc_html( $sc['label'] ),
				'href'   => admin_url( 'admin.php?page=pizzatier-shortcodes' ) . $sc['hash'],
			] );
		}

		// ── Settings sub-links ───────────────────────────────────────────
		$settings_sections = [
			[ 'label' => __( 'Default Layers', 'pizzatier' ),  'id' => 'pset-body-default-layers' ],
			[ 'label' => __( 'Pizza Shape', 'pizzatier' ),     'id' => 'pset-body-pizza-shape'    ],
			[ 'label' => __( 'Crust', 'pizzatier' ),           'id' => 'pset-body-crust-options'  ],
			[ 'label' => __( 'Sauce & Cheese', 'pizzatier' ),  'id' => 'pset-body-sauce-cheese'   ],
			[ 'label' => __( 'Plugin Settings', 'pizzatier' ), 'id' => 'pset-body-plugin-settings'],
		];
		foreach ( $settings_sections as $sec ) {
			$bar->add_menu( [
				'parent' => 'pizzatier-settings',
				'id'     => 'pizzatier-settings-' . $sec['id'],
				'title'  => esc_html( $sec['label'] ),
				'href'   => admin_url( 'admin.php?page=pizzatier-settings' ) . '#' . $sec['id'],
			] );
		}

		// ── Help sub-links (section navigation) ──────────────────────────
		$help_sections = [
			[ 'key' => 'quickstart', 'label' => __( 'Quickstart Guide', 'pizzatier' )       ],
			[ 'key' => 'content',    'label' => __( 'Managing Content', 'pizzatier' )       ],
			[ 'key' => 'layers',     'label' => __( 'Layer Type Reference', 'pizzatier' )   ],
			[ 'key' => 'shortcodes', 'label' => __( 'Shortcode Reference', 'pizzatier' )    ],
			[ 'key' => 'shapes',     'label' => __( 'Shape & Animation', 'pizzatier' )      ],
			[ 'key' => 'templates',  'label' => __( 'Template System', 'pizzatier' )        ],
			[ 'key' => 'faq',        'label' => __( 'FAQ', 'pizzatier' )                    ],
			[ 'key' => 'developer',  'label' => __( 'Developer Reference', 'pizzatier' )    ],
		];
		foreach ( $help_sections as $sec ) {
			$bar->add_menu( [
				'parent' => 'pizzatier-help',
				'id'     => 'pizzatier-help-' . $sec['key'],
				'title'  => esc_html( $sec['label'] ),
				'href'   => add_query_arg( 'section', $sec['key'], admin_url( 'admin.php?page=pizzatier-help' ) ),
			] );
		}

		// ── View Demo (if configured) ────────────────────────────────────
		if ( get_option( 'pizzatier_setting_settings_demonotice', '' ) ) {
			$bar->add_menu( [
				'parent' => 'pizzatier',
				'id'     => 'pizzatier-view-demo',
				'title'  => esc_html__( 'View Demo', 'pizzatier' ),
				'href'   => home_url( '/?pizzatier_demo=1' ),
				'meta'   => [ 'target' => '_blank', 'title' => __( 'Open front-end demo in new tab', 'pizzatier' ) ],
			] );
		}

		// ── WooCommerce products ────────────────────────────────────────
		// Was PizzaTier's only admin-bar contribution, added through the
		// hook below from a nine-line class. Folded in directly with the merge.
		if ( class_exists( 'WooCommerce' ) ) {
			$bar->add_menu( [
				'parent' => 'pizzatier',
				'id'     => 'pizzatier-wc-products',
				'title'  => __( 'WC Products', 'pizzatier' ),
				'href'   => admin_url( 'edit.php?post_type=product' ),
			] );
		}

		// ── Hook for third-party additions ──────────────────────────────
		do_action( 'pizzatier_admin_bar_menu', $bar );
	}

	/**
	 * Enqueue the admin-bar styles through the stylesheet pipeline.
	 *
	 * The toolbar renders on both the front end and admin, so rather than ship
	 * the large combined admin stylesheet to every front-end page we attach this
	 * small block to a dedicated inline-only handle that loads only when the bar
	 * is showing. Hooked (from Plugin) on both admin_enqueue_scripts and
	 * wp_enqueue_scripts.
	 */
	public function enqueue_bar_styles(): void {
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );

		// Inline-only handle (no src) — a registered+enqueued container so the
		// CSS goes out through wp_add_inline_style() instead of a raw <style>.
		if ( ! wp_style_is( 'pizzatier-admin-bar', 'registered' ) ) {
			wp_register_style( 'pizzatier-admin-bar', false, [], PIZZATIER_VERSION ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
		wp_enqueue_style( 'pizzatier-admin-bar' );

		$active_slug = sanitize_html_class( $this->get_current_cpt_slug() );

		$css = sprintf(
			<<<'CSS'
		/* ── PizzaTier Admin Bar ───────────────────────────────────── */

		/* Root emoji icon */
		#wpadminbar #wp-admin-bar-pizzatier > .ab-item .pzlab-root-icon {
			display: inline-block !important;
			font-size: 16px !important;
			line-height: 1 !important;
			margin-right: 4px !important;
			vertical-align: middle !important;
			position: relative;
			top: -1px;
		}

		/* Group headers — visual separators with uppercase label */
		#wpadminbar .pzlab-group-header > .ab-item {
			pointer-events: none !important;
			cursor: default !important;
			padding: 0 !important;
			height: auto !important;
			background: transparent !important;
		}
		#wpadminbar .pzlab-group-header > .ab-item:hover { color: inherit !important; }
		.pzlab-group-label {
			display: block !important;
			font-size: 9.5px !important;
			font-weight: 700 !important;
			letter-spacing: .1em !important;
			text-transform: uppercase !important;
			color: rgba(240,246,252,.28) !important;
			padding: 8px 14px 3px !important;
			border-top: 1px solid rgba(240,246,252,.14) !important;
			margin-top: 4px !important;
		}

		/* Spacer lines between submenu items at section boundaries */
		#wpadminbar #wp-admin-bar-pizzatier-dashboard > .ab-item {
			border-bottom: 1px solid rgba(240,246,252,.07) !important;
			margin-bottom: 2px !important;
		}

		/* CPT rows — flex so the item label and +New sub-links align */
		#wpadminbar .pzlab-cpt-row > .ab-item {
			display: flex !important;
			align-items: center !important;
		}

		/* Highlight currently-active CPT */
		#wpadminbar #wp-admin-bar-pizzatier-cpt-%s > .ab-item {
			color: #ff8c42 !important;
		}

		/* Compact: don't let the dropdown get too tall on mobile */
		@media screen and (max-width: 600px) {
			#wpadminbar #wp-admin-bar-pizzatier .ab-sub-wrapper { max-height: 80vh; overflow-y: auto; }
		}
CSS,
			$active_slug
		);

		wp_add_inline_style( 'pizzatier-admin-bar', $css );
	}

	/** Return the current CPT slug for sidebar/bar highlighting. */
	private function get_current_cpt_slug(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only: derives the active CPT from the admin URL for menu highlighting; value is sanitized and not used to change state.
		global $pagenow;
		if ( ! isset( $pagenow ) ) { return ''; }
		if ( $pagenow === 'admin.php'
			&& isset( $_GET['page'] )
			&& $_GET['page'] === 'pizzatier-content'
			&& isset( $_GET['pl_cpt'] ) ) {
			return sanitize_key( $_GET['pl_cpt'] );
		}
		if ( in_array( $pagenow, [ 'edit.php', 'post-new.php', 'post.php' ], true ) ) {
			$pt = sanitize_key( $_GET['post_type'] ?? get_post_type() ?? '' );
			return str_replace( 'pizzatier_', '', $pt );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return '';
	}
}
