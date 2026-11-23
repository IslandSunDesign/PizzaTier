<?php
/**
 * "Extra Portions" box on the topping edit screen.
 *
 * This is where the admin chooses, per topping, between:
 *   - Thickness: reuse this topping, set how many portions are allowed and
 *     what each added portion costs;
 *   - Separate layers: link topping layers they added (one per level).
 *
 * @package PizzaTier\Multiplier
 */

namespace PizzaTier\Multiplier\Admin;

use PizzaTier\Multiplier\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ToppingMetaBox {

	const NONCE = 'pizzatier_multiplier_meta';

	public function register(): void {
		add_action( 'add_meta_boxes_' . Config::TOPPING_CPT, [ $this, 'add' ] );
		add_action( 'save_post_' . Config::TOPPING_CPT, [ $this, 'save' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
	}

	public function add(): void {
		add_meta_box(
			'pztm-extra-portions',
			__( 'Extra Portions (double, triple…)', 'pizzatier' ),
			[ $this, 'render' ],
			Config::TOPPING_CPT,
			'normal',
			'default'
		);
	}

	/**
	 * @param string $hook Admin page hook.
	 */
	public function assets( $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Config::TOPPING_CPT !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'pizzatier-multiplier-admin', PIZZATIER_PLUGIN_URL . 'assets/css/admin/topping-multiplier.css', [], PIZZATIER_VERSION );
		wp_enqueue_script( 'pizzatier-multiplier-admin', PIZZATIER_PLUGIN_URL . 'assets/js/admin/topping-multiplier.js', [], PIZZATIER_VERSION, true );
	}

	/**
	 * @param \WP_Post $post Topping.
	 */
	public function render( $post ): void {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );

		$mode    = (string) get_post_meta( $post->ID, Config::META_MODE, true );
		$max     = (int) get_post_meta( $post->ID, Config::META_MAX, true );
		$pricing = (string) get_post_meta( $post->ID, Config::META_PRICING, true );
		$value   = (string) get_post_meta( $post->ID, Config::META_PRICE_VALUE, true );
		$levels  = get_post_meta( $post->ID, Config::META_LEVEL_LAYERS, true );
		$levels  = is_array( $levels ) ? $levels : [];
		$only    = '1' === (string) get_post_meta( $post->ID, Config::META_PORTION_ONLY, true );

		$g          = Config::all();
		$g_methods  = Config::methods();
		$g_pricing  = Config::pricing_modes();
		$g_mode_lbl = $g_methods[ $g['default_mode'] ] ?? '';
		$g_price    = $g_pricing[ $g['pricing'] ] ?? '';
		if ( 'percent' === $g['pricing'] ) {
			$g_price .= ' (' . $g['percent'] . '%)';
		} elseif ( 'fixed' === $g['pricing'] ) {
			$g_price .= ' (' . $g['fixed'] . ')';
		}

		$others        = get_posts(
			[
				'post_type'      => Config::TOPPING_CPT,
				'post_status'    => [ 'publish', 'draft', 'private' ],
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			]
		);
		// The topping being edited cannot be its own linked layer. Filtered
		// here rather than with `exclude`, which Plugin Check flags.
		$others = array_values(
			array_filter(
				$others,
				static function ( $other ) use ( $post ) {
					return (int) $other->ID !== (int) $post->ID;
				}
			)
		);
		$new_url       = admin_url( 'post-new.php?post_type=' . Config::TOPPING_CPT );
		$default_max   = Config::default_max(); // 0 = no limit.
		$effective_max = $max > 0 ? $max : ( $default_max > 0 ? $default_max : Config::ceiling() );
		// Rows for linked layers: a level left on "Same as previous" repeats
		// the one above, so a handful of rows covers any number of portions.
		$saved_levels  = array_map( 'intval', array_keys( $levels ) );
		$row_max       = min( Config::ceiling(), max( 8, $saved_levels ? max( $saved_levels ) : 0 ) );
		$default_text  = $default_max > 0 ? (string) $default_max : __( 'no limit', 'pizzatier' );
		?>
		<div class="pztm-box" data-pztm-box data-default-max="<?php echo esc_attr( (string) $default_max ); ?>" data-default-mode="<?php echo esc_attr( (string) $g['default_mode'] ); ?>">
			<p class="pztm-row">
				<label for="pztm-mode"><strong><?php esc_html_e( 'How customers add more of this topping', 'pizzatier' ); ?></strong></label><br>
				<select id="pztm-mode" name="pizzatier_mult[mode]" data-pztm-mode>
					<option value="" <?php selected( $mode, '' ); ?>>
						<?php
						/* translators: %s: the global default method or pricing rule. */
						echo esc_html( sprintf( __( 'Use the default — %s', 'pizzatier' ), $g_mode_lbl ) );
						?>
					</option>
					<?php foreach ( $g_methods as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $mode, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<p class="pztm-row" data-pztm-when="thickness layers">
				<label for="pztm-max"><strong><?php esc_html_e( 'Maximum portions', 'pizzatier' ); ?></strong></label><br>
				<input id="pztm-max" type="number" class="small-text" min="0" max="<?php echo esc_attr( (string) Config::ceiling() ); ?>" step="1" name="pizzatier_mult[max]" value="<?php echo $max > 0 ? esc_attr( (string) $max ) : ''; ?>" placeholder="<?php echo esc_attr( $default_max > 0 ? (string) $default_max : __( 'No limit', 'pizzatier' ) ); ?>" data-pztm-max>
				<span class="description">
					<?php
					/* translators: %s: the store default maximum, a number or "no limit". */
					echo esc_html( sprintf( __( 'Including the first portion (2 = double, 3 = triple). Leave empty for the store default (%s).', 'pizzatier' ), $default_text ) );
					?>
				</span>
			</p>

			<div class="pztm-row" data-pztm-when="thickness layers">
				<strong><?php esc_html_e( 'Each added portion or layer costs', 'pizzatier' ); ?></strong>
				<fieldset class="pztm-pricing" data-pztm-pricing>
					<label class="pztm-radio"><input type="radio" name="pizzatier_mult[pricing]" value="" <?php checked( $pricing, '' ); ?>>
						<?php
						/* translators: %s: the global default method or pricing rule. */
						echo esc_html( sprintf( __( 'Use the default — %s', 'pizzatier' ), $g_price ) );
						?>
					</label><br>
					<?php foreach ( $g_pricing as $key => $label ) : ?>
						<label class="pztm-radio"><input type="radio" name="pizzatier_mult[pricing]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $pricing, $key ); ?>> <?php echo esc_html( $label ); ?></label>
						<?php if ( 'percent' === $key || 'fixed' === $key ) : ?>
							<span class="pztm-inline" data-pztm-show="<?php echo esc_attr( $key ); ?>">
								<input type="number" min="0" step="<?php echo 'percent' === $key ? '1' : '0.01'; ?>" class="small-text" name="pizzatier_mult[value_<?php echo esc_attr( $key ); ?>]" value="<?php echo $key === $pricing ? esc_attr( $value ) : ''; ?>">
								<?php echo 'percent' === $key ? '%' : esc_html( function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '' ); ?>
							</span>
						<?php endif; ?>
						<br>
					<?php endforeach; ?>
				</fieldset>
				<p class="description"><?php esc_html_e( '“Full topping” uses this topping’s price grid (or the product grid) for every added portion, at the same coverage as the topping. With separate layers, a level that has its own linked layer below is charged from that layer’s price grid instead.', 'pizzatier' ); ?></p>
			</div>

			<div class="pztm-row" data-pztm-when="layers">
				<strong><?php esc_html_e( 'Linked layers (optional)', 'pizzatier' ); ?></strong>
				<p class="description">
					<?php esc_html_e( 'Customers tap “Add another layer” and each layer is listed separately. By default every added layer is another copy of this topping. To use different artwork or prices, pick a topping layer for a level: it is shown and charged from its own image and price grid. A level left on “Same as previous” repeats the layer above it.', 'pizzatier' ); ?>
					<a href="<?php echo esc_url( $new_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Add a new topping layer', 'pizzatier' ); ?> ↗</a>
				</p>
				<table class="pztm-levels">
					<?php for ( $n = 2; $n <= $row_max; $n++ ) : ?>
						<tr data-pztm-level="<?php echo esc_attr( (string) $n ); ?>" <?php echo $n > $effective_max ? 'hidden' : ''; ?>>
							<th scope="row"><label for="pztm-level-<?php echo esc_attr( (string) $n ); ?>"><?php /* translators: %d: layer number (2, 3 …). */ echo esc_html( sprintf( __( 'Layer %d', 'pizzatier' ), $n ) ); ?></label></th>
							<td>
								<select id="pztm-level-<?php echo esc_attr( (string) $n ); ?>" name="pizzatier_mult[levels][<?php echo esc_attr( (string) $n ); ?>]">
									<option value="0"><?php echo esc_html( 2 === $n ? __( '— Copy of this topping —', 'pizzatier' ) : __( '— Same as previous —', 'pizzatier' ) ); ?></option>
									<?php foreach ( $others as $other ) : ?>
										<option value="<?php echo esc_attr( (string) $other->ID ); ?>" <?php selected( (int) ( $levels[ $n ] ?? 0 ), (int) $other->ID ); ?>>
											<?php echo esc_html( get_the_title( $other ) . ( 'publish' !== $other->post_status ? ' (' . $other->post_status . ')' : '' ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endfor; ?>
				</table>
				<p class="description"><?php esc_html_e( 'Larger amounts repeat the last layer chosen above.', 'pizzatier' ); ?></p>
			</div>

			<hr>
			<p class="pztm-row">
				<label><input type="checkbox" name="pizzatier_mult[portion_only]" value="1" <?php checked( $only ); ?>> <strong><?php esc_html_e( 'This is an extra-portion layer', 'pizzatier' ); ?></strong></label><br>
				<span class="description"><?php esc_html_e( 'Hide this layer from the topping menus — it only appears when a customer adds another portion of the topping it is linked to.', 'pizzatier' ); ?></span>
			</p>
		</div>
		<?php
	}

	/**
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save( $post_id, $post ): void {
		if ( ! isset( $_POST[ self::NONCE . '_nonce' ] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is sanitised individually below.
		$in = isset( $_POST['pizzatier_mult'] ) && is_array( $_POST['pizzatier_mult'] ) ? wp_unslash( $_POST['pizzatier_mult'] ) : [];

		$mode = isset( $in['mode'] ) ? sanitize_key( $in['mode'] ) : '';
		$mode = isset( Config::methods()[ $mode ] ) ? $mode : '';
		$this->put( $post_id, Config::META_MODE, $mode );

		$max = isset( $in['max'] ) && '' !== $in['max'] ? absint( $in['max'] ) : 0;
		$max = $max > 0 ? max( 1, min( Config::ceiling(), $max ) ) : 0;
		$this->put( $post_id, Config::META_MAX, $max > 0 ? (string) $max : '' );

		$pricing = isset( $in['pricing'] ) ? sanitize_key( $in['pricing'] ) : '';
		$pricing = isset( Config::pricing_modes()[ $pricing ] ) ? $pricing : '';
		$this->put( $post_id, Config::META_PRICING, $pricing );

		$value = '';
		if ( 'percent' === $pricing || 'fixed' === $pricing ) {
			$raw   = isset( $in[ 'value_' . $pricing ] ) ? (string) $in[ 'value_' . $pricing ] : '';
			$value = '' === $raw ? '0' : (string) max( 0, (float) $raw );
		}
		$this->put( $post_id, Config::META_PRICE_VALUE, $value );

		$levels = [];
		if ( isset( $in['levels'] ) && is_array( $in['levels'] ) ) {
			foreach ( $in['levels'] as $level => $pid ) {
				$level = absint( $level );
				$pid   = absint( $pid );
				if ( $level >= 2 && $level <= Config::ceiling() && $pid > 0 && $pid !== (int) $post_id && Config::TOPPING_CPT === get_post_type( $pid ) ) {
					$levels[ $level ] = $pid;
				}
			}
		}
		if ( $levels ) {
			update_post_meta( $post_id, Config::META_LEVEL_LAYERS, $levels );
		} else {
			delete_post_meta( $post_id, Config::META_LEVEL_LAYERS );
		}

		$this->put( $post_id, Config::META_PORTION_ONLY, empty( $in['portion_only'] ) ? '' : '1' );

		Config::flush();
	}

	private function put( int $post_id, string $key, string $value ): void {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}
}
