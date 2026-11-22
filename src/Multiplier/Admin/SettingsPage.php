<?php
/**
 * PizzaTier → Topping Multiplier settings screen.
 *
 * @package PizzaTier\Multiplier
 */

namespace PizzaTier\Multiplier\Admin;

use PizzaTier\Multiplier\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsPage {

	const SLUG  = 'pizzatier-topping-multiplier';
	const GROUP = 'pizzatier_multiplier_group';

	public function register(): void {
		add_action( 'admin_init', [ $this, 'register_setting' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
	}


	public function register_setting(): void {
		register_setting(
			self::GROUP,
			Config::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => Config::defaults(),
			]
		);
	}

	/**
	 * @param string $hook Admin page hook.
	 */
	public function assets( $hook ): void {
		if ( false === strpos( (string) $hook, self::SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'pizzatier-multiplier-admin', PIZZATIER_PLUGIN_URL . 'assets/css/admin/topping-multiplier.css', [], PIZZATIER_VERSION );
		wp_enqueue_script( 'pizzatier-multiplier-admin', PIZZATIER_PLUGIN_URL . 'assets/js/admin/topping-multiplier.js', [], PIZZATIER_VERSION, true );
	}

	/**
	 * @param mixed $in Raw option.
	 * @return array
	 */
	public function sanitize( $in ): array {
		$d   = Config::defaults();
		$in  = is_array( $in ) ? $in : [];
		$out = [];

		$out['enabled']             = empty( $in['enabled'] ) ? 0 : 1;
		$out['count_toward_max']    = empty( $in['count_toward_max'] ) ? 0 : 1;
		$out['hide_portion_layers'] = empty( $in['hide_portion_layers'] ) ? 0 : 1;
		$out['order_note']          = empty( $in['order_note'] ) ? 0 : 1;

		$mode                = isset( $in['default_mode'] ) ? sanitize_key( $in['default_mode'] ) : $d['default_mode'];
		$out['default_mode'] = isset( Config::methods()[ $mode ] ) ? $mode : $d['default_mode'];

		// Empty (or 0) = no limit; otherwise at least "double".
		$raw_max            = trim( (string) ( $in['default_max'] ?? '' ) );
		$max                = '' === $raw_max ? 0 : absint( $raw_max );
		$out['default_max'] = $max > 0 ? max( 2, min( Config::ceiling(), $max ) ) : 0;

		$pricing        = isset( $in['pricing'] ) ? sanitize_key( $in['pricing'] ) : $d['pricing'];
		$out['pricing'] = isset( Config::pricing_modes()[ $pricing ] ) ? $pricing : 'full';
		$out['percent'] = max( 0, min( 1000, (float) ( $in['percent'] ?? $d['percent'] ) ) );
		$out['fixed']   = max( 0, (float) ( $in['fixed'] ?? $d['fixed'] ) );

		$visual        = isset( $in['visual'] ) ? sanitize_key( $in['visual'] ) : $d['visual'];
		$out['visual'] = in_array( $visual, [ 'scatter', 'stack', 'none' ], true ) ? $visual : 'scatter';

		foreach ( [ 'level_labels', 'control_label', 'base_label', 'extra_label', 'layers_label', 'add_layer_label', 'base_label_layers', 'layer_label', 'extra_label_layers' ] as $key ) {
			$val         = isset( $in[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $in[ $key ] ) ) : '';
			$out[ $key ] = ( '' === $val && 'extra_label_layers' !== $key ) ? $d[ $key ] : $val;
		}

		Config::flush();
		return $out;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s   = Config::all();
		$opt = Config::OPTION;
		?>
		<div class="wrap pztm-wrap">
			<h1><?php esc_html_e( 'Topping Multiplier', 'pizzatier' ); ?></h1>
			<p class="pztm-lede"><?php esc_html_e( 'Let customers double, triple or add more of the same topping. Every added portion appears on the pizza preview, is listed on the order, and is charged by the rules below.', 'pizzatier' ); ?></p>

			<div class="pztm-methods">
				<div class="pztm-method">
					<h2><?php esc_html_e( 'Thickness', 'pizzatier' ); ?></h2>
					<p><?php esc_html_e( 'Uses the existing topping. Set how many portions it allows on the topping’s edit screen (or use the default below). Each added portion re-layers the topping’s own image and is priced by the pricing rule.', 'pizzatier' ); ?></p>
				</div>
				<div class="pztm-method">
					<h2><?php esc_html_e( 'Separate layers', 'pizzatier' ); ?></h2>
					<p><?php esc_html_e( 'Add a new topping layer for each extra portion (for example “Pepperoni — Extra Layer” with denser artwork and its own price grid), then link it on the base topping’s edit screen. Each added portion shows and charges that layer.', 'pizzatier' ); ?></p>
				</div>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2 class="title"><?php esc_html_e( 'General', 'pizzatier' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable', 'pizzatier' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?>> <?php esc_html_e( 'Allow multiple portions of the same topping', 'pizzatier' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="pztm-default-mode"><?php esc_html_e( 'Default method', 'pizzatier' ); ?></label></th>
						<td>
							<select id="pztm-default-mode" name="<?php echo esc_attr( $opt ); ?>[default_mode]">
								<?php foreach ( Config::methods() as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['default_mode'], $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Used by every topping that does not choose its own method. Choose “Off” to switch multiples on topping by topping instead. Thickness shows a Regular / Double / Triple stepper on the topping and in its coverage dialog. Separate layers shows an “Add another layer” button, and each layer is listed as its own entry the customer can remove.', 'pizzatier' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pztm-default-max"><?php esc_html_e( 'Maximum portions per topping', 'pizzatier' ); ?></label></th>
						<td>
							<input id="pztm-default-max" type="number" min="0" max="<?php echo esc_attr( (string) Config::ceiling() ); ?>" step="1" class="small-text" name="<?php echo esc_attr( $opt ); ?>[default_max]" value="<?php echo (int) $s['default_max'] >= 2 ? esc_attr( (string) (int) $s['default_max'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'No limit', 'pizzatier' ); ?>">
							<p class="description">
								<?php esc_html_e( 'Leave empty for no limit. Otherwise the most portions a customer can add, including the first: 2 = up to double, 3 = up to triple, 10 = up to ten. Each topping can set its own limit, and added portions also count toward the topping limit when that option is on.', 'pizzatier' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Pricing for added portions', 'pizzatier' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Applies to Thickness, and to separate layers that have no linked topping layer (a linked layer is charged from its own price grid). All PizzaTier pricing rules — pricing engine, size multipliers, free toppings, bulk discounts and rounding — apply to added portions just like any other topping.', 'pizzatier' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Each added portion costs', 'pizzatier' ); ?></th>
						<td>
							<fieldset class="pztm-pricing" data-pztm-pricing>
								<?php foreach ( Config::pricing_modes() as $key => $label ) : ?>
									<label class="pztm-radio"><input type="radio" name="<?php echo esc_attr( $opt ); ?>[pricing]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['pricing'], $key ); ?>> <?php echo esc_html( $label ); ?></label>
									<?php if ( 'percent' === $key ) : ?>
										<span class="pztm-inline" data-pztm-show="percent"><input type="number" min="0" max="1000" step="1" class="small-text" name="<?php echo esc_attr( $opt ); ?>[percent]" value="<?php echo esc_attr( (string) $s['percent'] ); ?>"> %</span>
									<?php elseif ( 'fixed' === $key ) : ?>
										<span class="pztm-inline" data-pztm-show="fixed"><input type="number" min="0" step="0.01" class="small-text" name="<?php echo esc_attr( $opt ); ?>[fixed]" value="<?php echo esc_attr( (string) $s['fixed'] ); ?>"> <?php echo esc_html( function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '' ); ?></span>
									<?php endif; ?>
									<br>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Wording — Thickness', 'pizzatier' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="pztm-levels"><?php esc_html_e( 'Portion names', 'pizzatier' ); ?></label></th>
						<td>
							<input id="pztm-levels" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[level_labels]" value="<?php echo esc_attr( (string) $s['level_labels'] ); ?>">
							<p class="description"><?php esc_html_e( 'Comma-separated, starting with one portion. Shown on the thickness control; larger amounts show as 7×, 8× …', 'pizzatier' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pztm-control"><?php esc_html_e( 'Control label', 'pizzatier' ); ?></label></th>
						<td><input id="pztm-control" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[control_label]" value="<?php echo esc_attr( (string) $s['control_label'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="pztm-base"><?php esc_html_e( 'First portion line', 'pizzatier' ); ?></label></th>
						<td>
							<input id="pztm-base" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[base_label]" value="<?php echo esc_attr( (string) $s['base_label'] ); ?>">
							<p class="description"><?php esc_html_e( 'How the topping is listed when more than one portion is ordered. Placeholders: {name}, {level}.', 'pizzatier' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pztm-extra"><?php esc_html_e( 'Added portion line', 'pizzatier' ); ?></label></th>
						<td>
							<input id="pztm-extra" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[extra_label]" value="<?php echo esc_attr( (string) $s['extra_label'] ); ?>">
							<p class="description"><?php esc_html_e( 'How each added portion is listed on the price breakdown, cart and order. Placeholders: {name}, {n}, {ordinal}, {level}.', 'pizzatier' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Wording — Separate layers', 'pizzatier' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="pztm-layers-label"><?php esc_html_e( 'Control label', 'pizzatier' ); ?></label></th>
						<td><input id="pztm-layers-label" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[layers_label]" value="<?php echo esc_attr( (string) $s['layers_label'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="pztm-add-layer"><?php esc_html_e( 'Add button', 'pizzatier' ); ?></label></th>
						<td><input id="pztm-add-layer" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[add_layer_label]" value="<?php echo esc_attr( (string) $s['add_layer_label'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="pztm-base-layers"><?php esc_html_e( 'Topping line', 'pizzatier' ); ?></label></th>
						<td>
							<input id="pztm-base-layers" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[base_label_layers]" value="<?php echo esc_attr( (string) $s['base_label_layers'] ); ?>">
							<p class="description"><?php esc_html_e( 'How the topping itself is listed on the price breakdown, cart and order. Placeholders: {name}, {n} (layers in all), {level}.', 'pizzatier' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pztm-layer-line"><?php esc_html_e( 'Added layer line', 'pizzatier' ); ?></label></th>
						<td>
							<input id="pztm-layer-line" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[layer_label]" value="<?php echo esc_attr( (string) $s['layer_label'] ); ?>">
							<p class="description"><?php esc_html_e( 'Each added layer, in the topping list and on the breakdown, cart and order. Placeholders: {name}, {n}, {ordinal}.', 'pizzatier' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pztm-extra-layers"><?php esc_html_e( 'Linked layer line', 'pizzatier' ); ?></label></th>
						<td>
							<input id="pztm-extra-layers" type="text" class="regular-text" name="<?php echo esc_attr( $opt ); ?>[extra_label_layers]" value="<?php echo esc_attr( (string) $s['extra_label_layers'] ); ?>">
							<p class="description"><?php esc_html_e( 'Used instead when a topping layer is linked for that level. Placeholders: {layer} (the linked layer’s title), {name}, {n}, {ordinal}. Leave empty to use the added layer line.', 'pizzatier' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Display & limits', 'pizzatier' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Pizza preview', 'pizzatier' ); ?></th>
						<td>
							<?php
							$visuals = [
								'scatter' => __( 'Scatter — each added portion is a rotated copy, so the topping looks denser', 'pizzatier' ),
								'stack'   => __( 'Stack — exact copies on top of each other', 'pizzatier' ),
								'none'    => __( 'None — price and order only, preview unchanged', 'pizzatier' ),
							];
							foreach ( $visuals as $key => $label ) :
								?>
								<label class="pztm-radio"><input type="radio" name="<?php echo esc_attr( $opt ); ?>[visual]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['visual'], $key ); ?>> <?php echo esc_html( $label ); ?></label><br>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Topping limit', 'pizzatier' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[count_toward_max]" value="1" <?php checked( ! empty( $s['count_toward_max'] ) ); ?>> <?php esc_html_e( 'Added portions count toward the maximum number of toppings', 'pizzatier' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Linked layers', 'pizzatier' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[hide_portion_layers]" value="1" <?php checked( ! empty( $s['hide_portion_layers'] ) ); ?>> <?php esc_html_e( 'Hide layers linked as an added portion from the topping menus', 'pizzatier' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Kitchen note', 'pizzatier' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[order_note]" value="1" <?php checked( ! empty( $s['order_note'] ) ); ?>> <?php esc_html_e( 'Add an “Extra portions: …” line to Pizza Orders item notes', 'pizzatier' ); ?></label></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<?php $this->render_overview(); ?>
		</div>
		<?php
	}

	/**
	 * Read-only table of every topping and the rule that applies to it.
	 */
	private function render_overview(): void {
		$posts = get_posts(
			[
				'post_type'      => Config::TOPPING_CPT,
				'post_status'    => [ 'publish', 'draft', 'private' ],
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			]
		);
		if ( empty( $posts ) ) {
			return;
		}
		$hidden  = Config::portion_only_ids();
		$pricing = Config::pricing_modes();
		?>
		<h2 class="title"><?php esc_html_e( 'Toppings at a glance', 'pizzatier' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Change a topping’s rule on its edit screen, in the “Extra Portions” box.', 'pizzatier' ); ?></p>
		<?php
		$store_limit = (int) get_option( 'pizzatier_setting_topping_maxtoppings', 0 );
		if ( function_exists( 'pizzatier_get_option' ) && (int) pizzatier_get_option( 'max_toppings', 0 ) > 0 ) {
			$store_limit = (int) pizzatier_get_option( 'max_toppings', 0 );
		}
		if ( Config::get( 'count_toward_max' ) && $store_limit > 0 && $store_limit < 99 ) :
			?>
			<p class="description"><strong>
				<?php
				/* translators: %d: maximum toppings per pizza. */
				echo esc_html( sprintf( __( 'Added portions and layers also count toward the store’s limit of %d toppings per pizza, so a customer can never go past that in total.', 'pizzatier' ), $store_limit ) );
				?>
			</strong></p>
		<?php endif; ?>
		<table class="widefat striped pztm-overview">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Topping', 'pizzatier' ); ?></th>
					<th><?php esc_html_e( 'Method', 'pizzatier' ); ?></th>
					<th><?php esc_html_e( 'Up to', 'pizzatier' ); ?></th>
					<th><?php esc_html_e( 'Added portion price', 'pizzatier' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $posts as $post ) :
				$conf = Config::resolve_post( $post );
				$own  = (string) get_post_meta( $post->ID, Config::META_MODE, true );
				?>
				<tr>
					<td>
						<a href="<?php echo esc_url( (string) get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a>
						<?php if ( 'publish' !== $post->post_status ) : ?>
							<span class="pztm-muted">— <?php echo esc_html( $post->post_status ); ?></span>
						<?php endif; ?>
					</td>
					<?php if ( in_array( (int) $post->ID, $hidden, true ) ) : ?>
						<td colspan="3"><span class="pztm-muted"><?php esc_html_e( 'Extra-portion layer (hidden from menus)', 'pizzatier' ); ?></span></td>
					<?php elseif ( null === $conf ) : ?>
						<td>
							<?php esc_html_e( 'Off', 'pizzatier' ); ?>
							<?php if ( '' === $own ) : ?>
								<span class="pztm-muted">(<?php esc_html_e( 'default', 'pizzatier' ); ?>)</span>
							<?php endif; ?>
						</td>
						<td>1</td>
						<td>—</td>
					<?php else : ?>
						<td>
							<?php echo esc_html( 'layers' === $conf['method'] ? __( 'Separate layers', 'pizzatier' ) : __( 'Thickness', 'pizzatier' ) ); ?>
							<?php if ( '' === $own ) : ?>
								<span class="pztm-muted">(<?php esc_html_e( 'default', 'pizzatier' ); ?>)</span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( (int) $conf['max'] >= Config::ceiling() ? __( 'No limit', 'pizzatier' ) : Config::level_label( (int) $conf['max'] ) ); ?>
							<span class="pztm-muted">(<?php echo esc_html( (int) get_post_meta( $post->ID, Config::META_MAX, true ) > 0 ? __( 'this topping’s limit', 'pizzatier' ) : __( 'store default', 'pizzatier' ) ); ?>)</span>
						</td>
						<td>
							<?php
							if ( 'layers' === $conf['method'] && ! empty( $conf['levels'] ) ) {
								$names = [];
								foreach ( $conf['levels'] as $level => $pid ) {
									$names[] = Config::level_label( (int) $level ) . ': ' . get_the_title( $pid );
								}
								echo esc_html( __( 'Linked layer grid', 'pizzatier' ) . ' — ' . implode( ', ', $names ) );
							} elseif ( 'percent' === $conf['pricing'] ) {
								echo esc_html( rtrim( rtrim( number_format( (float) $conf['value'], 2 ), '0' ), '.' ) . '% ' . __( 'of the topping price', 'pizzatier' ) );
							} elseif ( 'fixed' === $conf['pricing'] ) {
								echo esc_html( function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( (float) $conf['value'] ) ) : number_format( (float) $conf['value'], 2 ) );
							} else {
								echo esc_html( $pricing[ $conf['pricing'] ] ?? '' );
							}
							?>
						</td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
