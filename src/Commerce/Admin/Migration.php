<?php
/**
 * PizzaTier — Site Migration integration.
 *
 * Hooks into the free PizzaTier plugin's Site Migration export/import
 * pipeline to add the cart & pricing data:
 *
 *   • The `pizzatier_options` option (the entire Cart & Pricing screen —
 *     pricing mode, free toppings, fractions, sizes, cart behaviour,
 *     nutrition toggles, layer-group flags, advanced options, etc.).
 *   • The setup-checklist progress (shared `pizzatier_setup_done` option).
 *   • Every WooCommerce product of type "pizza" — captured as a portable
 *     record (title, slug, status, sku, prices, descriptions, taxonomy
 *     terms, plus all `_pizzatier_commerce_*` meta including the price grid). Layer
 *     meta on the standard PizzaTier ingredient CPTs (toppings, crusts,
 *     sauces, cheeses, drizzles, cuts, sizes, presets) is already
 *     captured by the post-meta walk — no duplication here.
 *
 * Import policy mirrors the main importer's:
 *   • Settings overwrite (consistent with how settings are usually treated).
 *   • WC products are create-only by slug — if a product with the same
 *     slug exists on the destination, it's skipped, never overwritten.
 *
 * If Site Migration isn't loaded, the export/import filters simply
 * never fire — this class becomes inert. If WooCommerce isn't active
 * on the destination, WC product import is skipped gracefully and
 * counted in the result summary.
 *
 * @package PizzaTier\Commerce\Admin
 */

namespace PizzaTier\Commerce\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Migration {

	/**
	 * Single namespaced key inside the export payload's `commerce` section.
	 * Versioned so future schema changes can be detected on import.
	 */
	private const PAYLOAD_VERSION = 1;

	public function register(): void {
		// Only fire if PizzaTier\Admin\SiteMigration exists.
		add_filter( 'pizzatier_export_payload', [ $this, 'contribute_to_export' ] );
		add_action( 'pizzatier_import_payload', [ $this, 'consume_import' ], 10, 2 );
	}

	/* ═══════════════════════════════════════════════════════════════════
	   EXPORT — add cart & pricing data under the 'commerce' key
	   ═══════════════════════════════════════════════════════════════════ */

	/**
	 * Filter callback: contribute cart & pricing data to the migration export payload.
	 *
	 * @param array $payload The full export payload, already populated.
	 * @return array
	 */
	public function contribute_to_export( $payload ): array {
		if ( ! is_array( $payload ) ) {
			$payload = [];
		}

		$payload['commerce'] = [
			'plugin_version' => defined( 'PIZZATIER_VERSION' ) ? PIZZATIER_VERSION : 'unknown',
			'payload_version' => self::PAYLOAD_VERSION,
			'settings'       => $this->collect_settings(),
			// Exported as the array it is. Until 2.0.0 this was cast to bool on the
			// way out and written back as a bool on the way in, which replaced the
			// destination site's checklist array with `true` — after which reading
			// a step off it warned on PHP 8 and the checklist stopped working.
			'setup_done'     => (array) get_option( 'pizzatier_setup_done', [] ),
			'wc_products'    => $this->collect_wc_pizza_products(),
		];

		return $payload;
	}

	/**
	 * Snapshot the cart & pricing settings option.
	 *
	 * The cart & pricing screen stores all its settings inside one array option key,
	 * `pizzatier_options`, which is the cleanest possible export.
	 */
	private function collect_settings(): array {
		$opt = get_option( Settings::OPTION_NAME, [] );
		return is_array( $opt ) ? $opt : [];
	}

	/**
	 * Collect every WooCommerce product of type "pizza".
	 *
	 * Pro doesn't own these posts — they're regular WC product posts
	 * whose `product_type` taxonomy term is `pizza` and which carry
	 * their own meta. The main exporter
	 * doesn't walk WC products, so we do it here.
	 *
	 * If WooCommerce isn't active on the source site, this returns [].
	 */
	private function collect_wc_pizza_products(): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return [];
		}

		$query = new \WP_Query( [
			'post_type'      => 'product',
			'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- WooCommerce stores product type as a taxonomy term; there is no meta or column alternative. Result sets are small and admin-only.
				[
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => 'pizza',
				],
			],
			'fields'         => 'all',
		] );

		$out = [];
		foreach ( $query->posts as $post ) {
			$out[] = $this->serialize_wc_product( $post );
		}
		wp_reset_postdata();

		return $out;
	}

	/**
	 * Serialize a WC product into a transport-safe array.
	 *
	 * Captures: standard post fields, all post meta (filtered the same
	 * way the free plugin filters CPT meta), the product_type term,
	 * and any standard WooCommerce taxonomy terms (categories, tags).
	 */
	private function serialize_wc_product( \WP_Post $post ): array {
		$id = (int) $post->ID;

		// Walk all meta, strip WP-internal noise. Mirrors the main exporter's
		// is_internal_meta_key() exactly so the import side reads cleanly.
		$raw_meta = get_post_meta( $id );
		$meta     = [];
		foreach ( $raw_meta as $key => $values ) {
			if ( $this->is_internal_meta_key( $key ) ) {
				continue;
			}
			if ( count( $values ) === 1 ) {
				$meta[ $key ] = maybe_unserialize( $values[0] );
			} else {
				$meta[ $key ] = array_map( 'maybe_unserialize', $values );
			}
		}

		// Capture standard WC taxonomy term slugs so the destination
		// can re-attach categories/tags (terms must already exist there).
		$wc_taxonomies = [ 'product_cat', 'product_tag', 'product_type' ];
		$terms_by_tax  = [];
		foreach ( $wc_taxonomies as $tax ) {
			$tobjs = wp_get_object_terms( $id, $tax );
			if ( is_wp_error( $tobjs ) || empty( $tobjs ) ) {
				continue;
			}
			$slugs = [];
			foreach ( $tobjs as $t ) {
				$slugs[] = (string) $t->slug;
			}
			$terms_by_tax[ $tax ] = $slugs;
		}

		// Featured image — track URL only so destination can sideload.
		$thumb_id  = (int) get_post_thumbnail_id( $id );
		$thumb_url = '';
		$thumb_alt = '';
		if ( $thumb_id > 0 ) {
			$thumb_url = (string) wp_get_attachment_url( $thumb_id );
			$thumb_alt = (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
		}

		return [
			'slug'        => (string) $post->post_name,
			'title'       => (string) $post->post_title,
			'content'     => (string) $post->post_content,
			'excerpt'     => (string) $post->post_excerpt,
			'status'      => (string) $post->post_status,
			'menu_order'  => (int) $post->menu_order,
			'meta'        => $meta,
			'taxonomies'  => $terms_by_tax,
			'thumbnail'   => $thumb_url ? [ 'url' => $thumb_url, 'alt' => $thumb_alt ] : null,
		];
	}

	/**
	 * Filter out WP-internal meta keys. Mirrors the main exporter.
	 */
	private function is_internal_meta_key( string $key ): bool {
		if ( $key === '_thumbnail_id' )                { return true; } // re-resolved on import
		if ( strpos( $key, '_edit_' ) === 0 )           { return true; }
		if ( strpos( $key, '_wp_old' ) === 0 )          { return true; }
		if ( $key === '_wp_trash_meta_status' )         { return true; }
		if ( $key === '_wp_trash_meta_time' )           { return true; }
		return false;
	}

	/* ═══════════════════════════════════════════════════════════════════
	   IMPORT — consume the commerce section
	   ═══════════════════════════════════════════════════════════════════ */

	/**
	 * Action callback: apply the `commerce` section of an import payload.
	 *
	 * @param array $payload Full import payload as decoded from the JSON.
	 * @param array $results Free-plugin results array (counts).
	 */
	public function consume_import( $payload, $results ): void {
		if ( ! is_array( $payload ) || empty( $payload['commerce'] ) || ! is_array( $payload['commerce'] ) ) {
			return;
		}

		$commerce = $payload['commerce'];

		// Settings — overwrite (consistent with free-plugin settings policy).
		if ( ! empty( $commerce['settings'] ) && is_array( $commerce['settings'] ) ) {
			$this->import_settings( $commerce['settings'] );
		}

		// Setup-done flag — overwrite if present.
		if ( isset( $commerce['setup_done'] ) && is_array( $commerce['setup_done'] ) ) {
			$flags = [];
			foreach ( $commerce['setup_done'] as $flag_key => $flag_val ) {
				$flags[ sanitize_key( (string) $flag_key ) ] = (bool) $flag_val;
			}
			update_option( 'pizzatier_setup_done', array_merge(
				(array) get_option( 'pizzatier_setup_done', [] ),
				$flags
			) );
		}

		// WC products — create-only by slug. Skipped if WooCommerce isn't active.
		if ( ! empty( $commerce['wc_products'] ) && is_array( $commerce['wc_products'] ) ) {
			$this->import_wc_products( $commerce['wc_products'] );
		}
	}

	/**
	 * Apply the cart & pricing settings array. Sanitization is delegated to the
	 * Settings::sanitize() callback registered on the option,
	 * which fires automatically on update_option() since
	 * register_setting() wired it up. We strip unknown keys against
	 * the defaults() shape to avoid pollution.
	 */
	private function import_settings( array $incoming ): int {
		$pro_settings = new Settings();
		$defaults     = $pro_settings->defaults();

		// Keep only known keys.
		$filtered = [];
		foreach ( $incoming as $k => $v ) {
			$k = (string) $k;
			if ( array_key_exists( $k, $defaults ) ) {
				$filtered[ $k ] = $v;
			}
		}

		if ( empty( $filtered ) ) {
			return 0;
		}

		// Merge over current settings rather than wholesale replace, so
		// any keys present locally but absent from the export survive
		// (forward-compat for partial imports between plugin versions).
		$current = get_option( Settings::OPTION_NAME, [] );
		if ( ! is_array( $current ) ) {
			$current = [];
		}
		$merged = array_merge( $current, $filtered );

		// update_option triggers the registered sanitize callback automatically.
		update_option( Settings::OPTION_NAME, $merged );

		return count( $filtered );
	}

	/**
	 * Import WC pizza products with create-only-by-slug semantics.
	 */
	private function import_wc_products( array $products ): array {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return [ 'created' => 0, 'skipped' => count( $products ), 'no_wc' => true ];
		}

		$created = 0;
		$skipped = 0;
		$thumbs_loaded = 0;
		$thumbs_failed = 0;

		foreach ( $products as $row ) {
			if ( ! is_array( $row ) ) {
				$skipped++;
				continue;
			}

			$slug  = isset( $row['slug'] )  ? sanitize_title( (string) $row['slug'] ) : '';
			$title = isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '';

			if ( ! $slug ) {
				$skipped++;
				continue;
			}

			// Create-only-by-slug.
			$existing = get_page_by_path( $slug, OBJECT, 'product' );
			if ( $existing instanceof \WP_Post ) {
				$skipped++;
				continue;
			}

			$status_in = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'publish';
			$allowed_statuses = [ 'publish', 'draft', 'pending', 'private', 'future' ];
			if ( ! in_array( $status_in, $allowed_statuses, true ) ) {
				$status_in = 'publish';
			}

			$post_id = wp_insert_post( [
				'post_type'    => 'product',
				'post_name'    => $slug,
				'post_title'   => $title ?: $slug,
				'post_content' => isset( $row['content'] ) ? wp_kses_post( (string) $row['content'] ) : '',
				'post_excerpt' => isset( $row['excerpt'] ) ? wp_kses_post( (string) $row['excerpt'] ) : '',
				'post_status'  => $status_in,
				'menu_order'   => isset( $row['menu_order'] ) ? (int) $row['menu_order'] : 0,
			], true );

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				$skipped++;
				continue;
			}

			// Meta — copy everything that survived the export filter.
			if ( ! empty( $row['meta'] ) && is_array( $row['meta'] ) ) {
				foreach ( $row['meta'] as $mkey => $mval ) {
					$mkey_safe = sanitize_key( (string) $mkey );
					if ( ! $mkey_safe || $this->is_internal_meta_key( $mkey_safe ) ) {
						continue;
					}
					if ( is_array( $mval ) ) {
						delete_post_meta( $post_id, $mkey_safe );
						foreach ( $mval as $v ) {
							add_post_meta( $post_id, $mkey_safe, $this->sanitize_meta_value( $v ) );
						}
					} else {
						update_post_meta( $post_id, $mkey_safe, $this->sanitize_meta_value( $mval ) );
					}
				}
			}

			// Taxonomies — re-attach using slugs (terms must exist locally).
			// product_type=pizza is the critical one; we ensure it exists.
			if ( ! empty( $row['taxonomies'] ) && is_array( $row['taxonomies'] ) ) {
				foreach ( $row['taxonomies'] as $tax => $slugs ) {
					$tax_safe = sanitize_key( (string) $tax );
					if ( ! taxonomy_exists( $tax_safe ) || ! is_array( $slugs ) ) {
						continue;
					}

					// product_type=pizza: ensure the term exists.
					if ( $tax_safe === 'product_type' && in_array( 'pizza', $slugs, true ) ) {
						if ( ! get_term_by( 'slug', 'pizza', 'product_type' ) ) {
							wp_insert_term( 'Pizza', 'product_type', [ 'slug' => 'pizza' ] );
						}
					}

					$slugs_clean = array_filter( array_map( 'sanitize_title', $slugs ) );
					if ( $slugs_clean ) {
						wp_set_object_terms( $post_id, $slugs_clean, $tax_safe, false );
					}
				}
			}

			// Thumbnail — sideload from URL if reachable.
			if ( ! empty( $row['thumbnail'] ) && is_array( $row['thumbnail'] ) ) {
				$ok = $this->sideload_thumbnail( $post_id, $row['thumbnail'] );
				if ( $ok ) {
					$thumbs_loaded++;
				} else {
					$thumbs_failed++;
				}
			}

			$created++;
		}

		return [
			'created'        => $created,
			'skipped'        => $skipped,
			'thumbs_loaded'  => $thumbs_loaded,
			'thumbs_failed'  => $thumbs_failed,
		];
	}

	/**
	 * Best-effort sanitization for arbitrary meta values from import.
	 * Mirrors the main importer's logic so behaviour is consistent.
	 */
	private function sanitize_meta_value( $value ) {
		if ( is_array( $value ) ) {
			return array_map( [ $this, 'sanitize_meta_value' ], $value );
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		return wp_kses_post( (string) $value );
	}

	/**
	 * Sideload a featured-image URL into the media library and attach it.
	 */
	private function sideload_thumbnail( int $post_id, array $thumb ): bool {
		$url = isset( $thumb['url'] ) ? esc_url_raw( (string) $thumb['url'] ) : '';
		if ( ! $url ) {
			return false;
		}

		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			return false;
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		$filename = $path ? basename( $path ) : 'thumbnail.jpg';
		$filename = sanitize_file_name( $filename );

		$file_array = [
			'name'     => $filename,
			'tmp_name' => $tmp,
		];

		$attachment_id = media_handle_sideload( $file_array, $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			return false;
		}

		$alt = isset( $thumb['alt'] ) ? sanitize_text_field( (string) $thumb['alt'] ) : '';
		if ( $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}

		set_post_thumbnail( $post_id, (int) $attachment_id );
		return true;
	}
}
