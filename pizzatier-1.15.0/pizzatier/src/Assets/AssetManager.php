<?php
namespace PizzaTier\Assets;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AssetManager {

	/**
	 * Template slugs requested by shortcode instances on this page load.
	 * Populated via require_template() before wp_enqueue_scripts fires.
	 *
	 * @var string[]
	 */
	private static array $required_templates = [];

	/**
	 * Register a template slug as required for this page.
	 * Called by BuilderShortcode during render so that enqueue_frontend()
	 * knows which template assets to load beyond the global default.
	 */
	public static function require_template( string $slug ): void {
		if ( $slug && ! in_array( $slug, self::$required_templates, true ) ) {
			self::$required_templates[] = $slug;
		}
	}

	public function enqueue_frontend(): void {
		$v = PIZZATIER_VERSION;

		wp_enqueue_style( 'pizzatier-css',            PIZZATIER_ASSETS_URL . 'css/pizzatier.css',            [], $v );
		wp_enqueue_style( 'pizzatier-bootstrap-grid', PIZZATIER_ASSETS_URL . 'css/bootstrap-grid-system.css', [], $v );
		wp_enqueue_script( 'pizzatier-js',            PIZZATIER_ASSETS_URL . 'js/pizzatier-main.js',         [ 'jquery' ], $v, true );

		$loader = new \PizzaTier\Template\TemplateLoader();

		// Always enqueue the globally active template.
		$active_slug = $loader->get_active_slug();
		$slugs_to_load = array_unique( array_merge( [ $active_slug ], self::$required_templates ) );

		foreach ( $slugs_to_load as $slug ) {
			// Load the template's custom PHP file (hooks, helpers) exactly once per page load.
			$loader->load_template_custom( $slug );

			if ( file_exists( $loader->get_template_file( 'template.css', $slug ) ) ) {
				wp_enqueue_style( 'pizzatier-template-' . $slug, $loader->get_template_url( 'template.css', $slug ), [ 'pizzatier-css' ], $v );
			}
			if ( file_exists( $loader->get_template_file( 'custom.js', $slug ) ) ) {
				wp_enqueue_script( 'pizzatier-template-' . $slug, $loader->get_template_url( 'custom.js', $slug ), [ 'jquery', 'pizzatier-js' ], $v, true );
			}
		}
	}

	/**
	 * Enqueue styles in the block editor so server-side-rendered previews
	 * look correct inside the editor iframe/canvas.
	 */
	public function enqueue_block_editor(): void {
		$v      = PIZZATIER_VERSION;
		$loader = new \PizzaTier\Template\TemplateLoader();
		$slug   = $loader->get_active_slug();

		wp_enqueue_style( 'pizzatier-css', PIZZATIER_ASSETS_URL . 'css/pizzatier.css', [], $v );

		if ( file_exists( $loader->get_template_file( 'template.css', $slug ) ) ) {
			wp_enqueue_style(
				'pizzatier-template-' . $slug,
				$loader->get_template_url( 'template.css', $slug ),
				[ 'pizzatier-css' ],
				$v
			);
		}
	}

	/**
	 * Enqueue admin assets — shared tabs CSS/JS plus page-specific scripts.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin( string $hook ): void {
		$v = PIZZATIER_VERSION;

		// Combined admin-screen stylesheet. Loaded on every admin page because the
		// sidebar-menu rules (styled via AdminMenu) appear on all screens; the
		// remaining feature rules are scoped and inert elsewhere. Replaces ~2,100
		// lines of formerly-inline <style> across the admin classes.
		wp_enqueue_style( 'pizzatier-admin', PIZZATIER_ASSETS_URL . 'css/admin/pizzatier-admin.css', [], $v );

		if ( false === strpos( $hook, 'pizzatier' ) ) { return; }

		$base = PIZZATIER_ASSETS_URL . 'js/admin/';

		// Shared admin styles (also a dependency for inline column CSS and the
		// settings-page stylesheet below).
		wp_enqueue_style( 'pizzatier-admin-tabs', PIZZATIER_ASSETS_URL . 'css/admin-tabs.css', [], $v );

		// Dashboard
		if ( false !== strpos( $hook, 'pizzatier_page_pizzatier' ) || 'toplevel_page_pizzatier' === $hook ) {
			wp_enqueue_script(
				'pizzatier-admin-home',
				$base . 'admin-home.js',
				[ 'jquery' ],
				$v,
				true
			);
		}

		// Setup Guide tab JS — also used by the Layer-by-Layer Setup section on the Help page
		if ( false !== strpos( $hook, 'pizzatier-setup' ) || false !== strpos( $hook, 'pizzatier-help' ) ) {
			wp_enqueue_script(
				'pizzatier-setup-guide',
				$base . 'setup-guide.js',
				[],
				$v,
				true
			);
		}

		// Content Hub
		if ( false !== strpos( $hook, 'pizzatier-content' ) ) {
			wp_enqueue_script(
				'pizzatier-content-hub',
				$base . 'content-hub.js',
				[],
				$v,
				true
			);

			// Custom list-table column widths. Attached to the already-enqueued
			// admin stylesheet via wp_add_inline_style (runs on
			// admin_enqueue_scripts, before <head> is printed) rather than an
			// inline <style> echoed during page render.
			wp_add_inline_style(
				'pizzatier-admin-tabs',
				'.column-pzl_thumb{width:52px;}'
				. '.column-pzl_sort_order,.column-pzl_id{width:64px;}'
				. '.column-pzl_dietary{width:130px;}'
				. '.column-pzl_diameter_inches,.column-pzl_spice_level,.column-pzl_thickness,.column-pzl_calories{width:90px;}'
			);

			// Build CPT data array for the JS. Keep this list in sync with
			// ContentHub::CPTS so AJAX tab switches can update the header.
			$cpt_slugs = [ 'toppings', 'crusts', 'sauces', 'cheeses', 'drizzles', 'cuts', 'sizes', 'presets' ];
			$cpt_meta  = [
				'toppings' => [ 'label' => 'Toppings', 'singular' => 'Topping',  'icon' => 'dashicons-carrot',          'color' => '#f0b849', 'desc' => 'Layer images placed on top of cheese.' ],
				'crusts'   => [ 'label' => 'Crusts',   'singular' => 'Crust',    'icon' => 'dashicons-admin-generic',    'color' => '#c8956c', 'desc' => 'The base canvas for the pizza stack.' ],
				'sauces'   => [ 'label' => 'Sauces',   'singular' => 'Sauce',    'icon' => 'dashicons-food',             'color' => '#d63638', 'desc' => 'Applied on top of the crust.' ],
				'cheeses'  => [ 'label' => 'Cheeses',  'singular' => 'Cheese',   'icon' => 'dashicons-category',         'color' => '#dba633', 'desc' => 'Sits between sauce and toppings.' ],
				'drizzles' => [ 'label' => 'Drizzles', 'singular' => 'Drizzle',  'icon' => 'dashicons-admin-customizer', 'color' => '#00a32a', 'desc' => 'Finishing touches above toppings.' ],
				'cuts'     => [ 'label' => 'Cuts',     'singular' => 'Cut',      'icon' => 'dashicons-editor-table',     'color' => '#2271b1', 'desc' => 'Slicing overlays.' ],
				'sizes'    => [ 'label' => 'Sizes',    'singular' => 'Size',     'icon' => 'dashicons-image-rotate',     'color' => '#8c5af8', 'desc' => 'Dimension options with pricing metadata.' ],
				'presets'  => [ 'label' => 'Presets',  'singular' => 'Preset',   'icon' => 'dashicons-food',             'color' => '#e8692a', 'desc' => 'Pre-configured pizza combinations customers can start from.' ],
			];

			$js_cpt_data = [];
			foreach ( $cpt_slugs as $s ) {
				$m = $cpt_meta[ $s ];
				$js_cpt_data[ $s ] = [
					'label'     => $m['label'],
					'singular'  => $m['singular'],
					'icon'      => $m['icon'],
					'color'     => $m['color'],
					'desc'      => $m['desc'],
					'addUrl'    => admin_url( 'post-new.php?post_type=pizzatier_' . $s ),
					'wpListUrl' => admin_url( 'edit.php?post_type=pizzatier_' . $s ),
				];
			}

			$active_slug = isset( $_GET['pl_cpt'] ) ? sanitize_key( $_GET['pl_cpt'] ) : 'toppings'; // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! array_key_exists( $active_slug, $js_cpt_data ) ) {
				$active_slug = 'toppings';
			}

			// Resolve the persisted view mode so the JS starts in sync with the
			// server-rendered panel (otherwise the List/Grid toggle can no-op).
			$hub_view = (string) get_user_meta( get_current_user_id(), 'pizzatier_hub_view', true );
			$hub_view = ( $hub_view === 'grid' ) ? 'grid' : 'list';

			wp_localize_script(
				'pizzatier-content-hub',
				'pizzatierContentHub',
				[
					'nonce'   => wp_create_nonce( 'pizzatier_content_nonce' ),
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'cptData' => $js_cpt_data,
					'active'  => $active_slug,
					'view'    => $hub_view,
				]
			);
		}

		// Shortcode Generator
		if ( false !== strpos( $hook, 'pizzatier-shortcodes' ) ) {
			wp_enqueue_script(
				'pizzatier-shortcode-generator',
				$base . 'shortcode-generator.js',
				[],
				$v,
				true
			);
			// Pass CPT items for the layer slug select
			$q = [ 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ];
			$cpt_items = [];
			foreach ( [ 'topping', 'crust', 'sauce', 'cheese', 'drizzle', 'cut' ] as $type ) {
				$posts = get_posts( array_merge( $q, [ 'post_type' => 'pizzatier_' . $type . 's' ] ) );
				$cpt_items[ $type ] = array_map( fn( $p ) => [
					'slug'  => sanitize_title( $p->post_title ),
					'title' => $p->post_title,
				], $posts );
			}
			wp_localize_script( 'pizzatier-shortcode-generator', 'pizzatierSCG', [ 'cptItems' => $cpt_items ] );
		}

		// Settings
		if ( false !== strpos( $hook, 'pizzatier-settings' ) ) {
			wp_enqueue_media(); // Required for the logo image picker
			wp_enqueue_style(
				'pizzatier-settings-page',
				PIZZATIER_ASSETS_URL . 'css/settings-page.css',
				[ 'pizzatier-admin-tabs' ],
				$v
			);
			wp_enqueue_script(
				'pizzatier-settings',
				$base . 'settings.js',
				[],
				$v,
				true
			);
			wp_enqueue_script(
				'pizzatier-settings-page',
				PIZZATIER_ASSETS_URL . 'js/admin/settings-page.js',
				[ 'jquery', 'pizzatier-settings' ],
				$v,
				true
			);
		}

		// Template Choice
		if ( false !== strpos( $hook, 'pizzatier-template' ) ) {
			wp_enqueue_media();
			wp_enqueue_script(
				'pizzatier-template-choice',
				$base . 'template-choice.js',
				[],
				$v,
				true
			);
		}

		// Settings Wizard
		if ( false !== strpos( $hook, 'pizzatier-wizard' ) ) {
			wp_enqueue_script(
				'pizzatier-settings-wizard',
				$base . 'settings-wizard.js',
				[],
				$v,
				true
			);
		}

		// Layer Builder Wizard
		if ( false !== strpos( $hook, 'pizzatier-layer-wizard' ) ) {
			wp_enqueue_script(
				'pizzatier-layer-builder-wizard',
				$base . 'layer-builder-wizard.js',
				[ 'jquery' ],
				$v,
				true
			);
			wp_localize_script(
				'pizzatier-layer-builder-wizard',
				'pizzatierLBW',
				[
					'nonce'      => wp_create_nonce( 'pizzatier_wizard_save' ),
					'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
					'limUrl'     => admin_url( 'admin.php?page=pizzatier-layer-maker' ),
					'layerTypes' => \PizzaTier\Admin\LayerBuilderWizard::LAYER_TYPES,
					'i18n'       => [
						'detailsFor'           => __( 'Details for your', 'pizzatier' ),
						'uploadLayerImage'     => __( 'Upload Layer Image', 'pizzatier' ),
						'chooseLayerImage'     => __( 'Choose Layer Image', 'pizzatier' ),
						'useThisImage'         => __( 'Use this image', 'pizzatier' ),
						'noImageSelected'      => __( 'No image selected', 'pizzatier' ),
						'name'                 => __( 'Name', 'pizzatier' ),
						'slug'                 => __( 'Slug', 'pizzatier' ),
						'description'          => __( 'Description', 'pizzatier' ),
						'priceModifier'        => __( 'Price modifier', 'pizzatier' ),
						'thickness'            => __( 'Thickness', 'pizzatier' ),
						'calories'             => __( 'Calories', 'pizzatier' ),
						'diameter'             => __( 'Diameter', 'pizzatier' ),
						'spiceLevel'           => __( 'Spice level', 'pizzatier' ),
						'vegetarian'           => __( 'Vegetarian', 'pizzatier' ),
						'vegan'                => __( 'Vegan', 'pizzatier' ),
						'glutenFree'           => __( 'Gluten-Free', 'pizzatier' ),
						'dairyFree'            => __( 'Dairy-Free', 'pizzatier' ),
						'dietary'              => __( 'Dietary', 'pizzatier' ),
						'image'                => __( 'Image', 'pizzatier' ),
						'imageNoneCanAddLater' => __( 'None (can be added later)', 'pizzatier' ),
						'errorSavingLayer'     => __( 'Error saving layer:', 'pizzatier' ),
						'unknownError'         => __( 'Unknown error.', 'pizzatier' ),
						'networkError'         => __( 'Network error. Please try again.', 'pizzatier' ),
						'wasSaved'             => __( 'was saved!', 'pizzatier' ),
						'successDesc'          => __( 'Your new layer has been created. Use the shortcode below to include it on any page.', 'pizzatier' ),
						'copy'                 => __( 'Copy', 'pizzatier' ),
						'copied'               => __( 'Copied!', 'pizzatier' ),
						'editLayer'            => __( 'Edit Layer', 'pizzatier' ),
						'all'                  => __( 'All', 'pizzatier' ),
						'buildAnotherLayer'    => __( 'Build Another Layer', 'pizzatier' ),
					],
				]
			);
		}

		// Layer Image Maker (full-page tool)
		if ( false !== strpos( $hook, 'pizzatier-layer-maker' ) ) {
			wp_enqueue_script(
				'pizzatier-layer-image-maker',
				$base . 'layer-image-maker.js',
				[],
				$v,
				true
			);
			wp_localize_script(
				'pizzatier-layer-image-maker',
				'plimConfig',
				[
					'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
					'nonce'       => wp_create_nonce( 'pizzatier_layer_image_maker' ),
					'aspectRatio' => preg_replace( '/\s+/', '', get_option( 'pizzatier_setting_pizza_aspect', '4 / 3' ) ),
				]
			);
		}

		// Layer Image MetaBox — CPT post-edit screens
		if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			$screen = get_current_screen();
			if ( $screen && strpos( $screen->post_type ?? '', 'pizzatier_' ) === 0 ) {
				wp_enqueue_script(
					'pizzatier-layer-image-metabox',
					$base . 'layer-image-metabox.js',
					[],
					$v,
					true
				);
			}
		}
	}
}
