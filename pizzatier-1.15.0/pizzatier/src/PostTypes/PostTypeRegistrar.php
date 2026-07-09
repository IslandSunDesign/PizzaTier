<?php
namespace PizzaTier\PostTypes;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Registers all 8 PizzaTier custom post types plus the
 * pizzatier_ingredient_group hierarchical taxonomy.
 */
class PostTypeRegistrar {

	/**
	 * CPT definitions: slug suffix → singular / plural / description / icon.
	 * Post type name = "pizzatier_{slug}".
	 */
	private const TYPES = [
		'toppings'      => [ 'Topping',      'Toppings',      'Global toppings available for pizza building.',              'dashicons-carrot'      ],
		'crusts'        => [ 'Crust',         'Crusts',        'Crust options that form the base of each pizza.',           'dashicons-admin-generic' ],
		'sauces'        => [ 'Sauce',         'Sauces',        'Sauce layers applied on top of the crust.',                 'dashicons-food'        ],
		'cheeses'       => [ 'Cheese',        'Cheeses',       'Cheese layers applied on top of the sauce.',                'dashicons-admin-generic' ],
		'drizzles'      => [ 'Drizzle',       'Drizzles',      'Optional finishing drizzle layers.',                        'dashicons-admin-generic' ],
		'cuts'          => [ 'Cut',           'Cuts',          'Pizza slicing / cut style overlays.',                       'dashicons-admin-generic' ],
		'sizes'         => [ 'Size',          'Sizes',         'Pizza size options with dimension and pricing data.',        'dashicons-image-rotate' ],
		'presets'       => [ 'Preset',        'Presets',       'Pre-configured pizza combinations ready to select.',        'dashicons-food'        ],
	];

	/**
	 * CPT slugs that participate in ingredient grouping taxonomy.
	 * Cuts and sizes are excluded — they are structural, not ingredients.
	 */
	private const GROUPABLE_TYPES = [
		'pizzatier_toppings',
		'pizzatier_crusts',
		'pizzatier_sauces',
		'pizzatier_cheeses',
		'pizzatier_drizzles',
	];

	public function register(): void {
		foreach ( self::TYPES as $slug => [ $singular, $plural, $description, $icon ] ) {
			$this->register_type( $slug, $singular, $plural, $description, $icon );
		}
		$this->register_ingredient_group_taxonomy();
		do_action( 'pizzatier_cpt_registered' );
	}

	/**
	 * Register the pizzatier_ingredient_group hierarchical taxonomy.
	 *
	 * Hierarchical (like categories) so admins can create parent groups
	 * (e.g. "Meat", "Vegetable") and optional sub-groups.
	 * Applied to all five ingredient CPTs so one taxonomy covers everything.
	 */
	private function register_ingredient_group_taxonomy(): void {
		$labels = [
			'name'              => _x( 'Ingredient Groups', 'Taxonomy General Name', 'pizzatier' ),
			'singular_name'     => _x( 'Ingredient Group',  'Taxonomy Singular Name', 'pizzatier' ),
			'menu_name'         => __( 'Ingredient Groups', 'pizzatier' ),
			'all_items'         => __( 'All Groups',        'pizzatier' ),
			'parent_item'       => __( 'Parent Group',      'pizzatier' ),
			'parent_item_colon' => __( 'Parent Group:',     'pizzatier' ),
			'new_item_name'     => __( 'New Group Name',    'pizzatier' ),
			'add_new_item'      => __( 'Add New Group',     'pizzatier' ),
			'edit_item'         => __( 'Edit Group',        'pizzatier' ),
			'update_item'       => __( 'Update Group',      'pizzatier' ),
			'view_item'         => __( 'View Group',        'pizzatier' ),
			'search_items'      => __( 'Search Groups',     'pizzatier' ),
			'not_found'         => __( 'Not Found',         'pizzatier' ),
		];

		$args = [
			'labels'            => $labels,
			'hierarchical'      => true,   // Like categories, not tags.
			'public'            => false,   // Not publicly queryable on front-end.
			'show_ui'           => true,
			'show_in_menu'      => false,   // Accessed via each CPT's edit screen.
			'show_in_rest'      => true,    // Available via REST for block editor.
			'show_admin_column' => true,    // Show group column in CPT list tables.
			'rewrite'           => false,
		];

		register_taxonomy( 'pizzatier_ingredient_group', self::GROUPABLE_TYPES, $args );
	}

	private function register_type( string $slug, string $singular, string $plural, string $description, string $icon ): void {
		$post_type = 'pizzatier_' . $slug;

		$labels = [
			'name'                  => $plural,
			'singular_name'         => $singular,
			'menu_name'             => $plural,
			'name_admin_bar'        => $singular,
			/* translators: %s = the post type name. */
			'archives'              => sprintf( __( '%s List', 'pizzatier' ), $plural ),
			/* translators: %s = the post type name. */
			'all_items'             => sprintf( __( 'All %s', 'pizzatier' ), $plural ),
			/* translators: %s = the post type name. */
			'add_new_item'          => sprintf( __( 'Add New %s', 'pizzatier' ), $singular ),
			'add_new'               => __( 'Add New', 'pizzatier' ),
			/* translators: %s = the post type name. */
			'edit_item'             => sprintf( __( 'Edit %s', 'pizzatier' ), $singular ),
			/* translators: %s = the post type name. */
			'update_item'           => sprintf( __( 'Update %s', 'pizzatier' ), $singular ),
			/* translators: %s = the post type name. */
			'view_item'             => sprintf( __( 'View %s', 'pizzatier' ), $singular ),
			/* translators: %s = the post type name. */
			'search_items'          => sprintf( __( 'Search %s', 'pizzatier' ), $plural ),
			'not_found'             => __( 'Not found', 'pizzatier' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'pizzatier' ),
			/* translators: %s = the post type name. */
			'featured_image'        => sprintf( __( '%s Image', 'pizzatier' ), $singular ),
			/* translators: %s = the post type name. */
			'set_featured_image'    => sprintf( __( 'Set %s image', 'pizzatier' ), strtolower( $singular ) ),
			/* translators: %s = the post type name. */
			'remove_featured_image' => sprintf( __( 'Remove %s image', 'pizzatier' ), strtolower( $singular ) ),
		];

		$args = [
			'label'               => $singular,
			'description'         => $description,
			'labels'              => $labels,
			'supports'            => [ 'title', 'editor', 'thumbnail' ],
			'taxonomies'          => [ 'category', 'post_tag' ],
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'menu_icon'           => $icon,
			'menu_position'       => 35,
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'page',
			'show_in_rest'        => true,  // Keep REST access for apps & block editor.
		];

		/**
		 * Filter CPT registration args for a specific type.
		 *
		 * @param array  $args      CPT args array.
		 * @param string $post_type Full post type name (e.g. 'pizzatier_toppings').
		 */
		$args = apply_filters( "pizzatier_cpt_args_{$slug}", $args, $post_type );

		register_post_type( $post_type, $args );
	}
}
