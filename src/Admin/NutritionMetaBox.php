<?php
namespace PizzaTier\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * PizzaTier — Nutrition & Ingredients meta box.
 *
 * Adds a native "Nutrition & Ingredients" meta box to the edit/new screens for
 * the edible layer CPTs (toppings, crusts, sauces, cheeses, drizzles). Provides
 * a per-layer Ingredients list plus the nutrition / dietary fields the plugin
 * already consumes elsewhere (Content Hub columns, builder data-attributes).
 *
 * Storage
 * -------
 * All values are written to the plugin's canonical `_pizzatier_{key}` post-meta
 * keys — the same keys the Layer Builder Wizard writes and that Content Hub reads
 * via its `read_meta()` helper. Nothing here depends on ACF/SCF being installed.
 *
 *  - _pizzatier_ingredients      (string, newline-separated — one ingredient per line)
 *  - _pizzatier_serving_size     (string, e.g. "1 slice")
 *  - _pizzatier_calories         (int)
 *  - _pizzatier_spice_level      (mild|medium|hot|extra_hot)
 *  - _pizzatier_thickness        (string, crusts only)
 *  - _pizzatier_fat              (float, grams)
 *  - _pizzatier_carbs            (float, grams)
 *  - _pizzatier_protein          (float, grams)
 *  - _pizzatier_sodium           (float, mg)
 *  - _pizzatier_allergens        (string, free text)
 *  - _pizzatier_is_vegetarian / _is_vegan / _is_gluten_free / _is_dairy_free ('1' | '')
 *
 * These are plain form fields submitted with the post-editor form and saved on
 * `save_post`; no JavaScript or AJAX is involved.
 */
class NutritionMetaBox {

	/**
	 * Which fields show for each CPT slug. 'ingredients' + dietary flags are
	 * shown for every edible type; the rest are type-specific.
	 */
	private const TYPE_FIELDS = [
		'toppings' => [ 'ingredients', 'serving_size', 'calories', 'macros', 'spice_level', 'dietary' ],
		'crusts'   => [ 'ingredients', 'serving_size', 'calories', 'macros', 'thickness',   'dietary' ],
		'sauces'   => [ 'ingredients', 'serving_size', 'calories', 'macros', 'spice_level', 'dietary' ],
		'cheeses'  => [ 'ingredients', 'serving_size', 'calories', 'macros',                'dietary' ],
		'drizzles' => [ 'ingredients', 'serving_size', 'calories', 'macros', 'spice_level', 'dietary' ],
	];

	/**
	 * Per-serving macronutrient fields: meta suffix => [ label, unit, step ].
	 *
	 * These arrived with the PizzaTier merge, where they lived in a second
	 * nutrition meta box on the same five post types, stored under
	 * their own prefix. Two boxes asking for overlapping nutrition data on one
	 * edit screen was the clearest duplication the merge left behind. The
	 * two field sets barely overlapped — only calories appeared in both — so
	 * the merged box is the union, stored on this plugin's canonical
	 * `_pizzatier_*` keys alongside the rest.
	 */
	private const MACROS = [
		'fat'     => [ 'Fat',     'g',  '0.1' ],
		'carbs'   => [ 'Carbs',   'g',  '0.1' ],
		'protein' => [ 'Protein', 'g',  '0.1' ],
		'sodium'  => [ 'Sodium',  'mg', '1'   ],
	];

	/** Dietary flag keys + labels. */
	private const DIETARY = [
		'is_vegetarian'  => 'Vegetarian',
		'is_vegan'       => 'Vegan',
		'is_gluten_free' => 'Gluten-free',
		'is_dairy_free'  => 'Dairy-free',
	];

	/**
	 * Nutrition values for one layer post, for the front end.
	 *
	 * @since 2.0.0
	 *
	 * @param int $post_id Layer post ID.
	 * @return array<string,string> Non-empty values only.
	 */
	public static function get( int $post_id ): array {
		$keys = array_merge( [ 'calories', 'allergens' ], array_keys( self::MACROS ) );

		$out = [];
		foreach ( $keys as $key ) {
			$val = get_post_meta( $post_id, '_pizzatier_' . $key, true );
			if ( '' !== $val && false !== $val && null !== $val ) {
				$out[ $key ] = (string) $val;
			}
		}

		return $out;
	}

	public function register_hooks(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
		add_action( 'save_post', [ $this, 'save' ], 10, 2 );
	}

	/** @return string[] CPT slugs that receive the box. */
	public static function supported_types(): array {
		return array_keys( self::TYPE_FIELDS );
	}

	public function add_meta_boxes(): void {
		foreach ( array_keys( self::TYPE_FIELDS ) as $slug ) {
			add_meta_box(
				'pizzatier_nutrition',
				'<span class="dashicons dashicons-carrot" style="color:#ff6b35;font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:5px;"></span> ' . esc_html__( 'Nutrition & Ingredients', 'pizzatier' ),
				[ $this, 'render' ],
				'pizzatier_' . $slug,
				'normal',
				'default',
				[ 'slug' => $slug ]
			);
		}
	}

	// ── Render ───────────────────────────────────────────────────────────────

	public function render( \WP_Post $post, array $box ): void {
		$slug   = $box['args']['slug'] ?? '';
		$fields = self::TYPE_FIELDS[ $slug ] ?? [];
		if ( ! $fields ) { return; }

		wp_nonce_field( 'pizzatier_nutrition_save', 'pizzatier_nutrition_nonce' );

		$pid = (int) $post->ID;
		$get = function ( string $key ) use ( $pid ) {
			return (string) get_post_meta( $pid, '_pizzatier_' . $key, true );
		};
		?>
		<div class="pzl-nutri" style="max-width:760px;">
			<table class="form-table" role="presentation">
				<tbody>

				<?php if ( in_array( 'ingredients', $fields, true ) ) : ?>
				<tr>
					<th scope="row">
						<label for="pzl-nutri-ingredients"><?php esc_html_e( 'Ingredients', 'pizzatier' ); ?></label>
					</th>
					<td>
						<textarea id="pzl-nutri-ingredients" name="pzl_nutrition[ingredients]" rows="5" class="large-text code" placeholder="<?php esc_attr_e( "One ingredient per line, e.g.\nMozzarella\nTomato\nBasil", 'pizzatier' ); ?>"><?php echo esc_textarea( $get( 'ingredients' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'List each ingredient on its own line. Used for allergen / nutrition display and available to templates.', 'pizzatier' ); ?></p>
					</td>
				</tr>
				<?php endif; ?>

				<?php if ( in_array( 'serving_size', $fields, true ) ) : ?>
				<tr>
					<th scope="row"><label for="pzl-nutri-serving"><?php esc_html_e( 'Serving Size', 'pizzatier' ); ?></label></th>
					<td>
						<input type="text" id="pzl-nutri-serving" name="pzl_nutrition[serving_size]" class="regular-text" value="<?php echo esc_attr( $get( 'serving_size' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. 1 slice', 'pizzatier' ); ?>">
					</td>
				</tr>
				<?php endif; ?>

				<?php if ( in_array( 'calories', $fields, true ) ) : ?>
				<tr>
					<th scope="row"><label for="pzl-nutri-calories"><?php esc_html_e( 'Calories', 'pizzatier' ); ?></label></th>
					<td>
						<input type="number" id="pzl-nutri-calories" name="pzl_nutrition[calories]" class="small-text" min="0" max="100000" step="1" value="<?php echo esc_attr( $get( 'calories' ) ); ?>">
						<span class="description"><?php esc_html_e( 'kcal per serving', 'pizzatier' ); ?></span>
					</td>
				</tr>
				<?php endif; ?>

				<?php if ( in_array( 'macros', $fields, true ) ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Per serving', 'pizzatier' ); ?></th>
					<td>
						<?php foreach ( self::MACROS as $mkey => [ $mlabel, $munit, $mstep ] ) : ?>
							<label for="pzl-nutri-<?php echo esc_attr( $mkey ); ?>" style="display:inline-block;margin:0 16px 6px 0;">
								<span style="display:inline-block;min-width:58px;"><?php echo esc_html( $mlabel ); ?></span>
								<input type="number" id="pzl-nutri-<?php echo esc_attr( $mkey ); ?>"
								       name="pzl_nutrition[<?php echo esc_attr( $mkey ); ?>]"
								       class="small-text" min="0" max="100000" step="<?php echo esc_attr( $mstep ); ?>"
								       value="<?php echo esc_attr( $get( $mkey ) ); ?>">
								<span class="description"><?php echo esc_html( $munit ); ?></span>
							</label>
						<?php endforeach; ?>
						<p class="description" style="margin-top:4px;">
							<?php esc_html_e( 'Leave blank for any value you do not track.', 'pizzatier' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pzl-nutri-allergens"><?php esc_html_e( 'Allergens', 'pizzatier' ); ?></label></th>
					<td>
						<input type="text" id="pzl-nutri-allergens" name="pzl_nutrition[allergens]" class="regular-text"
						       value="<?php echo esc_attr( $get( 'allergens' ) ); ?>"
						       placeholder="<?php esc_attr_e( 'e.g. milk, wheat, soy', 'pizzatier' ); ?>">
					</td>
				</tr>
				<?php endif; ?>

				<?php if ( in_array( 'spice_level', $fields, true ) ) :
					$spice = strtolower( $get( 'spice_level' ) );
					$opts  = [ '' => __( '— None —', 'pizzatier' ), 'mild' => __( 'Mild', 'pizzatier' ), 'medium' => __( 'Medium', 'pizzatier' ), 'hot' => __( 'Hot', 'pizzatier' ), 'extra_hot' => __( 'Extra Hot', 'pizzatier' ) ];
				?>
				<tr>
					<th scope="row"><label for="pzl-nutri-spice"><?php esc_html_e( 'Spice Level', 'pizzatier' ); ?></label></th>
					<td>
						<select id="pzl-nutri-spice" name="pzl_nutrition[spice_level]">
							<?php foreach ( $opts as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>"<?php selected( $spice, $val ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<?php endif; ?>

				<?php if ( in_array( 'thickness', $fields, true ) ) : ?>
				<tr>
					<th scope="row"><label for="pzl-nutri-thickness"><?php esc_html_e( 'Thickness', 'pizzatier' ); ?></label></th>
					<td>
						<input type="text" id="pzl-nutri-thickness" name="pzl_nutrition[thickness]" class="regular-text" value="<?php echo esc_attr( $get( 'thickness' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. thin, regular, deep-dish', 'pizzatier' ); ?>">
					</td>
				</tr>
				<?php endif; ?>

				<?php if ( in_array( 'dietary', $fields, true ) ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Dietary', 'pizzatier' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( self::DIETARY as $key => $label ) :
								$on = in_array( strtolower( $get( $key ) ), [ '1', 'yes', 'true', 'on' ], true );
							?>
							<label style="display:inline-block;margin:0 16px 6px 0;">
								<input type="hidden" name="pzl_nutrition[<?php echo esc_attr( $key ); ?>]" value="0">
								<input type="checkbox" name="pzl_nutrition[<?php echo esc_attr( $key ); ?>]" value="1"<?php checked( $on ); ?>>
								<?php echo esc_html( $label ); ?>
							</label>
							<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>
				<?php endif; ?>

				</tbody>
			</table>
		</div>
		<?php
	}

	// ── Save ─────────────────────────────────────────────────────────────────

	public function save( int $post_id, \WP_Post $post ): void {
		// Standard guard clauses.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( wp_is_post_revision( $post_id ) ) { return; }

		$slug = str_replace( 'pizzatier_', '', (string) $post->post_type );
		if ( ! isset( self::TYPE_FIELDS[ $slug ] ) ) { return; }

		if ( ! isset( $_POST['pizzatier_nutrition_nonce'] )
		     || ! wp_verify_nonce( sanitize_key( $_POST['pizzatier_nutrition_nonce'] ), 'pizzatier_nutrition_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

		$fields = self::TYPE_FIELDS[ $slug ];
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array unslashed here; each field is sanitized individually below (nonce + capability already verified above).
		$in     = ( isset( $_POST['pzl_nutrition'] ) && is_array( $_POST['pzl_nutrition'] ) ) ? wp_unslash( $_POST['pzl_nutrition'] ) : [];

		// Ingredients — sanitize each line, drop blanks, re-join with "\n".
		if ( in_array( 'ingredients', $fields, true ) ) {
			$raw   = isset( $in['ingredients'] ) ? (string) $in['ingredients'] : '';
			$lines = preg_split( '/\r\n|\r|\n/', $raw );
			$clean = [];
			foreach ( (array) $lines as $line ) {
				$line = sanitize_text_field( $line );
				if ( $line !== '' ) { $clean[] = $line; }
			}
			update_post_meta( $post_id, '_pizzatier_ingredients', implode( "\n", $clean ) );
		}

		if ( in_array( 'serving_size', $fields, true ) ) {
			update_post_meta( $post_id, '_pizzatier_serving_size', sanitize_text_field( $in['serving_size'] ?? '' ) );
		}

		if ( in_array( 'calories', $fields, true ) ) {
			$cal = isset( $in['calories'] ) && $in['calories'] !== '' ? max( 0, (int) $in['calories'] ) : '';
			update_post_meta( $post_id, '_pizzatier_calories', $cal === '' ? '' : (string) $cal );
		}

		if ( in_array( 'macros', $fields, true ) ) {
			foreach ( array_keys( self::MACROS ) as $mkey ) {
				$raw_val = isset( $in[ $mkey ] ) ? trim( (string) $in[ $mkey ] ) : '';
				if ( '' === $raw_val ) {
					update_post_meta( $post_id, '_pizzatier_' . $mkey, '' );
					continue;
				}
				$num = max( 0, (float) $raw_val );
				// Store trimmed of trailing zeros so "12.0" round-trips as "12".
				update_post_meta( $post_id, '_pizzatier_' . $mkey, rtrim( rtrim( number_format( $num, 2, '.', '' ), '0' ), '.' ) );
			}
			update_post_meta( $post_id, '_pizzatier_allergens', sanitize_text_field( $in['allergens'] ?? '' ) );
		}

		if ( in_array( 'spice_level', $fields, true ) ) {
			$allowed = [ '', 'mild', 'medium', 'hot', 'extra_hot' ];
			$spice   = sanitize_key( $in['spice_level'] ?? '' );
			if ( ! in_array( $spice, $allowed, true ) ) { $spice = ''; }
			update_post_meta( $post_id, '_pizzatier_spice_level', $spice );
		}

		if ( in_array( 'thickness', $fields, true ) ) {
			update_post_meta( $post_id, '_pizzatier_thickness', sanitize_text_field( $in['thickness'] ?? '' ) );
		}

		if ( in_array( 'dietary', $fields, true ) ) {
			foreach ( array_keys( self::DIETARY ) as $key ) {
				$on = isset( $in[ $key ] ) && in_array( (string) $in[ $key ], [ '1', 'yes', 'true', 'on' ], true );
				update_post_meta( $post_id, '_pizzatier_' . $key, $on ? '1' : '' );
			}
		}
	}

	/**
	 * Read a layer's ingredients as an array of strings.
	 *
	 * Available to templates / shortcodes:
	 *   PizzaTier\Admin\NutritionMetaBox::get_ingredients( $post_id );
	 *
	 * @return string[]
	 */
	public static function get_ingredients( int $post_id ): array {
		$raw = (string) get_post_meta( $post_id, '_pizzatier_ingredients', true );
		if ( $raw === '' ) { return []; }
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$out   = [];
		foreach ( (array) $lines as $line ) {
			$line = trim( (string) $line );
			if ( $line !== '' ) { $out[] = $line; }
		}
		return $out;
	}
}
