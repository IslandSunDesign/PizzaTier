<?php
namespace PizzaTier\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * PizzaTier Site Migration
 *
 * Full-site backup / migration tool. Exports every piece of PizzaTier
 * configuration on a site so it can be restored on another installation:
 *
 *   • All pizzatier_setting_* options (via Settings::get_option_keys())
 *   • All eight CPT post types (toppings, crusts, sauces, cheeses,
 *     drizzles, cuts, sizes, presets) — title, slug, content, excerpt,
 *     status, menu_order, full post_meta, and assigned taxonomy terms
 *   • The pizzatier_ingredient_group taxonomy tree (parent/child)
 *   • Image references — URL + filename + alt + caption — instead of
 *     the binary files. On import, images are sideloaded into the new
 *     site's media library by URL.
 *   • Anything PizzaTier chooses to contribute via the
 *     pizzatier_export_payload filter (and consume via
 *     pizzatier_import_payload).
 *
 * Import policy: create-new-only by slug. If a post or term with the
 * same slug already exists, it's skipped. This is the safest default —
 * importing on top of an existing site will never destroy or overwrite
 * data; it will only fill in gaps.
 *
 * Schema:
 *
 *   {
 *     "schema":  "pizzatier-site-export",
 *     "version": 1,
 *     "exported_at": "2026-05-04T12:34:56Z",
 *     "source_site": "https://example.com",
 *     "plugin_version": "1.4.0",
 *     "settings": { key: value, ... },
 *     "taxonomy_terms": [ { slug, name, description, parent_slug }, ... ],
 *     "posts": [ {
 *         post_type, slug, title, content, excerpt, status, menu_order,
 *         meta: { key: value, ... },
 *         terms: [ slug, ... ],
 *         layer_image: { url, filename, alt, caption } | null
 *     }, ... ],
 *     "commerce": { ... cart & pricing data ... } | null
 *   }
 */
class SiteMigration {

	public const SCHEMA_NAME    = 'pizzatier-site-export';

	/**
	 * Schema 2 (2.0.4) adds: settings_images, state, images-per-post and the
	 * optional orders section. Schema 1 files still import — every added
	 * section is read defensively.
	 */
	public const SCHEMA_VERSION = 2;

	/** Lowest schema version this importer accepts. */
	private const MIN_SCHEMA_VERSION = 1;

	/** All eight CPT post types this plugin owns. */
	private const POST_TYPES = [
		'pizzatier_toppings',
		'pizzatier_crusts',
		'pizzatier_sauces',
		'pizzatier_cheeses',
		'pizzatier_drizzles',
		'pizzatier_cuts',
		'pizzatier_sizes',
		'pizzatier_presets',
	];

	private const TAXONOMY = 'pizzatier_ingredient_group';

	/**
	 * The native order CPT.
	 *
	 * Never included unless the operator ticks the box on both sides. Orders
	 * carry customer names, phone numbers and delivery addresses, so they stay
	 * out of a routine configuration backup by default.
	 */
	private const ORDER_POST_TYPE = 'pizzatier_order';

	/**
	 * Post meta holding an attachment ID rather than a value.
	 *
	 * Attachment IDs are meaningless on another install — importing them
	 * verbatim points the post at whatever unrelated image happens to occupy
	 * that ID. These are lifted out of the meta payload and re-resolved as
	 * portable URL references instead.
	 */
	private const ATTACHMENT_ID_META = [
		'_thumbnail_id',
		'_pizzatier_layer_image_id',
	];

	/** Cap on import file size — full sites with many CPTs can be a few MB. */
	private const MAX_IMPORT_BYTES = 25 * 1024 * 1024; // 25 MB

	/* ═══════════════════════════════════════════════════════════════════
	   ROUTING
	   ═══════════════════════════════════════════════════════════════════ */

	/**
	 * admin-post handler for the export download. Wired up in AdminMenu.
	 */
	public function handle_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( -1 ); }
		check_admin_referer( 'pizzatier_site_export' );

		// Customer orders are opt-in on the way out as well as the way in, so
		// a routine settings backup never contains personal data by accident.
		$include_orders = ! empty( $_POST['pizzatier_export_orders'] );

		$this->stream_export( $include_orders );
	}

	/**
	 * Page renderer. Handles the import POST inline before drawing the UI
	 * so the success/error notice can appear above the form on the same
	 * page load.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		$notice = '';

		if ( isset( $_POST['pizzatier_site_import_submit'], $_POST['_wpnonce'] )
		     && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'pizzatier_site_import' ) ) {
			$notice = $this->handle_import_upload();
		}

		$this->render_page( $notice );
	}

	/* ═══════════════════════════════════════════════════════════════════
	   EXPORT
	   ═══════════════════════════════════════════════════════════════════ */

	/**
	 * Build the full payload, JSON-encode it, and stream as a download.
	 */
	private function stream_export( bool $include_orders = false ): void {
		$payload = $this->build_payload( $include_orders );
		$json    = (string) wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		$filename = 'pizzatier-site-' . gmdate( 'Y-m-d-His' ) . '.json';

		// Discard any buffered output so download headers can be sent cleanly.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		// Mirror the headers-already-sent fallback used by Settings::export_settings.
		if ( headers_sent() ) {
			// Headers already committed before this admin_post handler ran, so
			// a file download can't be sent. Fall back to a no-JavaScript page
			// presenting the export JSON in a read-only textarea to copy/save.
			$back = wp_get_referer() ?: admin_url( 'admin.php?page=pizzatier-migration' );
			echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html__( 'PizzaTier Site Export', 'pizzatier' ) . '</title></head><body style="font-family:sans-serif;padding:24px;max-width:820px;margin:0 auto;">';
			echo '<h1 style="font-size:18px;">' . esc_html__( 'Site export', 'pizzatier' ) . '</h1>';
			echo '<p>' . esc_html__( 'Automatic download was unavailable on this server. Copy the text below and save it with this file name:', 'pizzatier' ) . ' <code>' . esc_html( $filename ) . '</code></p>';
			echo '<textarea readonly rows="20" style="width:100%;box-sizing:border-box;font-family:monospace;font-size:12px;">' . esc_textarea( $json ) . '</textarea>';
			echo '<p><a href="' . esc_url( $back ) . '">' . esc_html__( 'Back', 'pizzatier' ) . '</a></p>';
			echo '</body></html>';
			exit;
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $json ) );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw JSON file download (Content-Type: application/json).
		exit;
	}

	/**
	 * Assemble the full export payload.
	 */
	private function build_payload( bool $include_orders = false ): array {
		$payload = [
			'schema'          => self::SCHEMA_NAME,
			'version'         => self::SCHEMA_VERSION,
			'exported_at'     => gmdate( 'c' ),
			'source_site'     => home_url( '/' ),
			'plugin_version'  => defined( 'PIZZATIER_VERSION' ) ? PIZZATIER_VERSION : 'unknown',
			'settings'        => $this->collect_settings(),
			'settings_images' => $this->collect_settings_images(),
			'state'           => $this->collect_state(),
			'taxonomy_terms'  => $this->collect_terms(),
			'posts'           => $this->collect_posts(),
			'orders'          => $include_orders ? $this->collect_orders() : null,
			'commerce'        => null,
		];

		/**
		 * Filter the full export payload before serialization.
		 *
		 * PizzaTier hooks here to add its own settings, post meta,
		 * pricing grids, etc. under the 'commerce' key. Other extensions can
		 * also add top-level keys, but should namespace them clearly
		 * (e.g. 'mycompany_pizza_extension').
		 *
		 * @param array $payload Full export payload.
		 */
		$payload = apply_filters( 'pizzatier_export_payload', $payload );

		return is_array( $payload ) ? $payload : [];
	}

	/**
	 * Snapshot every plugin-managed option.
	 *
	 * The key list comes from OptionRegistry, which discovers each template's
	 * settings from its own pztp-template-options.php. Before 2.0.4 this
	 * walked a hand-maintained list that covered two of the eight templates
	 * and none of the ordering options.
	 *
	 * Options that are unset on this site are omitted rather than written as
	 * null, so importing never shadows the destination's own defaults with an
	 * empty value.
	 */
	private function collect_settings(): array {
		$out = [];
		foreach ( Settings::get_option_keys() as $key ) {
			// Credentials and site-specific post IDs stay behind. See
			// OptionRegistry::NON_PORTABLE for why each one is excluded.
			if ( ! \PizzaTier\Core\OptionRegistry::is_portable( $key ) ) { continue; }
			$value = get_option( $key, null );
			if ( null === $value ) { continue; }
			$out[ $key ] = $value;
		}
		return $out;
	}

	/**
	 * Portable references for settings that hold an image URL.
	 *
	 * Template backgrounds (e.g. metro_setting_container_bg_image) store a URL
	 * pointing into this site's uploads folder. Left alone, the destination
	 * would hotlink the source site forever — and break the day it goes away.
	 * Each is recorded so the importer can pull the file into the destination's
	 * own media library and rewrite the option.
	 *
	 * @return array<string, array>
	 */
	private function collect_settings_images(): array {
		$out = [];

		foreach ( array_keys( \PizzaTier\Core\OptionRegistry::image_option_keys() ) as $key ) {
			$url = (string) get_option( $key, '' );
			if ( '' === $url || strpos( $url, 'http' ) !== 0 ) { continue; }

			$path = wp_parse_url( $url, PHP_URL_PATH );
			$out[ $key ] = [
				'url'      => esc_url_raw( $url ),
				'filename' => sanitize_file_name( $path ? basename( $path ) : '' ),
			];
		}

		return $out;
	}

	/**
	 * Install-state values worth carrying across: setup progress and the
	 * template preview page. Deliberately excludes the order-number counter,
	 * which belongs to the destination's own order sequence.
	 */
	private function collect_state(): array {
		$out = [];
		foreach ( \PizzaTier\Core\OptionRegistry::state_keys() as $key ) {
			if ( 'pizzatier_order_sequence' === $key || 'pizzatier_db_version' === $key ) { continue; }
			$value = get_option( $key, null );
			if ( null === $value ) { continue; }
			$out[ $key ] = $value;
		}
		return $out;
	}

	/**
	 * Walk the ingredient-group taxonomy tree.
	 *
	 * Records each term's slug, name, description, and parent slug
	 * (resolved from parent ID) so the hierarchy can be reconstructed
	 * on import without depending on numeric IDs.
	 */
	private function collect_terms(): array {
		$terms = get_terms( [
			'taxonomy'   => self::TAXONOMY,
			'hide_empty' => false,
		] );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) { return []; }

		// Build slug-by-id map for parent resolution.
		$id_to_slug = [];
		foreach ( $terms as $t ) { $id_to_slug[ (int) $t->term_id ] = (string) $t->slug; }

		$out = [];
		foreach ( $terms as $t ) {
			$out[] = [
				'slug'        => (string) $t->slug,
				'name'        => (string) $t->name,
				'description' => (string) $t->description,
				'parent_slug' => $t->parent ? ( $id_to_slug[ (int) $t->parent ] ?? '' ) : '',
			];
		}
		return $out;
	}

	/**
	 * Collect every PizzaTier-owned post across all eight CPTs.
	 *
	 * Includes: full post body, all post meta, term assignments, and a
	 * resolved layer-image reference (URL + filename + alt + caption)
	 * suitable for sideload-by-URL on the destination site.
	 */
	private function collect_posts(): array {
		$out = [];

		foreach ( self::POST_TYPES as $post_type ) {
			$query = new \WP_Query( [
				'post_type'      => $post_type,
				'post_status'    => [ 'publish', 'draft', 'pending', 'private', 'future' ],
				'posts_per_page' => -1,
				'orderby'        => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
				'no_found_rows'  => true,
				'fields'         => 'all',
			] );

			foreach ( $query->posts as $post ) {
				$out[] = $this->serialize_post( $post );
			}

			wp_reset_postdata();
		}

		return $out;
	}

	/**
	 * Serialize a single post into a transport-safe array.
	 */
	private function serialize_post( \WP_Post $post ): array {
		$id = (int) $post->ID;

		// Strip WordPress-internal meta keys (revisions, edit lock, etc.)
		// and any obviously private underscored keys EXCEPT ones we know
		// about (the layer-image attachment ID, which we re-resolve to
		// URL below anyway). Capturing arbitrary user-added meta as-is
		// is the whole point.
		$raw_meta = get_post_meta( $id );
		$meta     = [];
		foreach ( $raw_meta as $key => $values ) {
			if ( $this->is_internal_meta_key( $key ) ) { continue; }
			// get_post_meta returns array of strings; collapse single-value
			// keys to a scalar to keep the payload readable, preserve arrays
			// for true multi-value keys.
			if ( count( $values ) === 1 ) {
				$meta[ $key ] = maybe_unserialize( $values[0] );
			} else {
				$meta[ $key ] = array_map( 'maybe_unserialize', $values );
			}
		}

		// Resolve term assignments by slug.
		$term_objs = wp_get_object_terms( $id, self::TAXONOMY );
		$term_slugs = [];
		if ( is_array( $term_objs ) ) {
			foreach ( $term_objs as $t ) { $term_slugs[] = (string) $t->slug; }
		}

		// Lift every image-bearing meta key out of $meta and record it as a
		// portable URL reference. Leaving them in place would write this site's
		// attachment IDs (or its URLs) onto the destination.
		$images = $this->resolve_image_meta( $id, $meta );

		// The featured image is stripped from $meta by is_internal_meta_key(),
		// so it is resolved from the post itself rather than the meta walk.
		$thumb_id = (int) get_post_thumbnail_id( $id );
		if ( $thumb_id > 0 ) {
			$thumb_ref = $this->attachment_reference( $thumb_id, 'id' );
			if ( $thumb_ref ) {
				$images['_thumbnail_id'] = $thumb_ref;
			}
		}

		// resolve_layer_image() already carries the canonical layer image, so
		// drop the legacy key here rather than sideloading the same file twice.
		$layer_image = $this->resolve_layer_image( $id );
		if ( $layer_image ) {
			unset( $images['pzl_layer_image'] );
		}

		return [
			'post_type'   => (string) $post->post_type,
			'slug'        => (string) $post->post_name,
			'title'       => (string) $post->post_title,
			'content'     => (string) $post->post_content,
			'excerpt'     => (string) $post->post_excerpt,
			'status'      => (string) $post->post_status,
			'menu_order'  => (int)    $post->menu_order,
			'meta'        => $meta,
			'terms'       => $term_slugs,
			'layer_image' => $layer_image,
			'images'      => $images,
		];
	}

	/**
	 * Turn every image-bearing meta key into a portable reference, removing it
	 * from the meta payload by reference.
	 *
	 * Two shapes are handled:
	 *
	 *   • Attachment IDs — _thumbnail_id, and any SCF/ACF image field, whose
	 *     keys end in _image or _image_id. An ID means nothing on another
	 *     install, so it is resolved to a URL here and re-attached on import.
	 *   • URL strings — legacy fields such as pzl_layer_image and the
	 *     {type}_layer_image variants ContentHub reads.
	 *
	 * Each entry records whether the destination should write back an
	 * attachment ID ('id') or a URL ('url'), so the restored value matches
	 * whatever the field originally held.
	 *
	 * @param int   $post_id Source post.
	 * @param array $meta    Meta map, modified in place.
	 * @return array<string, array>
	 */
	private function resolve_image_meta( int $post_id, array &$meta ): array {
		$out = [];

		foreach ( $meta as $key => $value ) {
			if ( is_array( $value ) || '' === $value || null === $value ) { continue; }

			$is_id_key  = in_array( $key, self::ATTACHMENT_ID_META, true )
				|| ( '_id' === substr( $key, -3 ) && false !== strpos( $key, 'image' ) );
			$is_img_key = '_image' === substr( $key, -6 ) || '_layer_image' === substr( $key, -12 );

			if ( ! $is_id_key && ! $is_img_key ) { continue; }

			$ref = null;

			if ( is_numeric( $value ) && (int) $value > 0 ) {
				// Attachment ID — resolve to something portable.
				$ref = $this->attachment_reference( (int) $value, 'id' );
			} elseif ( is_string( $value ) && strpos( $value, 'http' ) === 0 ) {
				// Already a URL.
				$path = wp_parse_url( $value, PHP_URL_PATH );
				$ref  = [
					'url'      => esc_url_raw( $value ),
					'filename' => sanitize_file_name( $path ? basename( $path ) : '' ),
					'alt'      => '',
					'caption'  => '',
					'store_as' => 'url',
				];
			}

			if ( null === $ref ) { continue; }

			$out[ $key ] = $ref;

			// Drop the raw value — the importer rebuilds it locally.
			unset( $meta[ $key ] );
		}

		return $out;
	}

	/**
	 * Describe an attachment in a form another site can rebuild it from.
	 *
	 * @param int    $attachment_id Attachment post ID.
	 * @param string $store_as      'id' or 'url' — how to write the value back.
	 * @return array|null
	 */
	private function attachment_reference( int $attachment_id, string $store_as ) {
		$url = (string) wp_get_attachment_url( $attachment_id );
		if ( ! $url ) { return null; }

		$file     = (string) get_attached_file( $attachment_id );
		$attached = get_post( $attachment_id );

		return [
			'url'      => esc_url_raw( $url ),
			'filename' => sanitize_file_name( $file ? basename( $file ) : '' ),
			'alt'      => sanitize_text_field( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ),
			'caption'  => $attached instanceof \WP_Post ? wp_kses_post( (string) $attached->post_excerpt ) : '',
			'store_as' => 'url' === $store_as ? 'url' : 'id',
		];
	}

	/**
	 * Build a portable image reference for the post's layer image.
	 *
	 * Looks at the standard meta keys this plugin uses:
	 *   • _pizzatier_layer_image_id   — attachment ID (preferred)
	 *   • pzl_layer_image              — URL string (fallback / legacy)
	 *
	 * Returns null if the post has no resolvable layer image.
	 */
	private function resolve_layer_image( int $post_id ) {
		$attachment_id = (int) get_post_meta( $post_id, '_pizzatier_layer_image_id', true );
		$url           = '';
		$filename      = '';
		$alt           = '';
		$caption       = '';

		if ( $attachment_id > 0 ) {
			$url      = (string) wp_get_attachment_url( $attachment_id );
			$file     = (string) get_attached_file( $attachment_id );
			$filename = $file ? basename( $file ) : '';
			$alt      = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			$attached = get_post( $attachment_id );
			if ( $attached instanceof \WP_Post ) {
				$caption = (string) $attached->post_excerpt;
			}
		} else {
			$legacy_url = (string) get_post_meta( $post_id, 'pzl_layer_image', true );
			if ( $legacy_url ) {
				$url      = $legacy_url;
				$filename = basename( wp_parse_url( $legacy_url, PHP_URL_PATH ) ?: '' );
			}
		}

		if ( ! $url ) { return null; }

		return [
			'url'      => esc_url_raw( $url ),
			'filename' => sanitize_file_name( $filename ),
			'alt'      => sanitize_text_field( $alt ),
			'caption'  => wp_kses_post( $caption ),
		];
	}

	/**
	 * Filter out WordPress-managed meta keys we should never export.
	 *
	 * We intentionally KEEP arbitrary user-added meta (e.g. allergens,
	 * topping_ingredients, melt_factor). We strip:
	 *   • _pizzatier_layer_image_id (re-derived from URL on import)
	 *   • Core WP meta (_edit_lock, _edit_last, _wp_old_*)
	 */
	private function is_internal_meta_key( string $key ): bool {
		if ( $key === '_pizzatier_layer_image_id' ) { return true; }
		// Re-resolved through the images map; a raw ID would point at an
		// unrelated attachment on the destination.
		if ( $key === '_thumbnail_id' )             { return true; }
		if ( strpos( $key, '_edit_' ) === 0 )         { return true; }
		if ( strpos( $key, '_wp_old' ) === 0 )        { return true; }
		if ( $key === '_wp_trash_meta_status' )       { return true; }
		if ( $key === '_wp_trash_meta_time' )         { return true; }
		return false;
	}

	/* ═══════════════════════════════════════════════════════════════════
	   IMPORT
	   ═══════════════════════════════════════════════════════════════════ */

	/**
	 * Receive the import upload, validate it, and run the import.
	 * Returns an HTML notice (success or error) for inline display.
	 */
	private function handle_import_upload(): string {
		check_admin_referer( 'pizzatier_site_import' );

		if ( empty( $_FILES['pizzatier_site_import_file']['tmp_name'] ) ) {
			return $this->error_notice( __( 'No file received.', 'pizzatier' ) );
		}

		// Read each $_FILES member individually with the correct sanitizer at the
		// point of use, rather than trusting the raw array. tmp_name is a
		// PHP-generated server path further validated by is_uploaded_file() below.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Superglobal array access; each member is validated/sanitized on the following lines.
		$file = isset( $_FILES['pizzatier_site_import_file'] ) && is_array( $_FILES['pizzatier_site_import_file'] ) ? $_FILES['pizzatier_site_import_file'] : array();

		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( $error !== UPLOAD_ERR_OK ) {
			return $this->error_notice( __( 'Upload error.', 'pizzatier' ) );
		}

		$tmp = isset( $file['tmp_name'] ) ? sanitize_text_field( wp_unslash( $file['tmp_name'] ) ) : '';
		if ( ! $tmp || ! is_uploaded_file( $tmp ) ) {
			return $this->error_notice( __( 'Invalid upload.', 'pizzatier' ) );
		}

		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $size > self::MAX_IMPORT_BYTES ) {
			return $this->error_notice( sprintf(
				/* translators: %s = max size with unit */
				__( 'File too large (max %s).', 'pizzatier' ),
				size_format( self::MAX_IMPORT_BYTES )
			) );
		}

		$orig_name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';
		if ( $orig_name === '' || strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) ) !== 'json' ) {
			return $this->error_notice( __( 'Expected a .json file.', 'pizzatier' ) );
		}

		$raw = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $raw ) {
			return $this->error_notice( __( 'Could not read file.', 'pizzatier' ) );
		}

		$payload = json_decode( $raw, true );
		if ( ! is_array( $payload ) ) {
			return $this->error_notice( __( 'Invalid JSON.', 'pizzatier' ) );
		}

		// Schema sanity check.
		$schema = isset( $payload['schema'] ) ? (string) $payload['schema'] : '';
		if ( $schema !== self::SCHEMA_NAME ) {
			return $this->error_notice( __( 'Not a PizzaTier site export file.', 'pizzatier' ) );
		}
		$version = isset( $payload['version'] ) ? (int) $payload['version'] : 0;
		if ( $version < self::MIN_SCHEMA_VERSION || $version > self::SCHEMA_VERSION ) {
			return $this->error_notice( sprintf(
				/* translators: %d = schema version found */
				__( 'Unsupported export schema version (%d).', 'pizzatier' ),
				$version
			) );
		}

		// Determine which sections the user wants to import.
		$do_settings = ! empty( $_POST['pizzatier_import_settings_section'] );
		$do_terms    = ! empty( $_POST['pizzatier_import_terms_section'] );
		$do_posts    = ! empty( $_POST['pizzatier_import_posts_section'] );
		$do_images   = ! empty( $_POST['pizzatier_import_images'] );
		$do_commerce = ! empty( $_POST['pizzatier_import_commerce_section'] );
		// Opt-in, unchecked by default: order records carry customer PII.
		$do_orders   = ! empty( $_POST['pizzatier_import_orders_section'] );

		$results = [
			'settings'      => 0,
			'terms_created' => 0,
			'terms_skipped' => 0,
			'posts_created' => 0,
			'posts_skipped' => 0,
			'images_loaded' => 0,
			'images_failed' => 0,
			'orders_created' => 0,
			'orders_skipped' => 0,
		];

		if ( $do_settings && ! empty( $payload['settings'] ) && is_array( $payload['settings'] ) ) {
			$results['settings'] = $this->import_settings( $payload['settings'] );

			// Setup-progress flags travel with the settings, not with the
			// cart & pricing section they used to be attached to.
			if ( ! empty( $payload['state'] ) && is_array( $payload['state'] ) ) {
				$this->import_state( $payload['state'] );
			}

			// Pull template background images into this site's media library
			// and repoint the options at the local copies.
			if ( $do_images && ! empty( $payload['settings_images'] ) && is_array( $payload['settings_images'] ) ) {
				$this->import_settings_images( $payload['settings_images'] );
			}
		}

		if ( $do_terms && ! empty( $payload['taxonomy_terms'] ) && is_array( $payload['taxonomy_terms'] ) ) {
			[ $created, $skipped ] = $this->import_terms( $payload['taxonomy_terms'] );
			$results['terms_created'] = $created;
			$results['terms_skipped'] = $skipped;
		}

		if ( $do_posts && ! empty( $payload['posts'] ) && is_array( $payload['posts'] ) ) {
			$post_results = $this->import_posts( $payload['posts'], $do_images );
			$results['posts_created']  = $post_results['created'];
			$results['posts_skipped']  = $post_results['skipped'];
			$results['images_loaded']  = $post_results['images_loaded'];
			$results['images_failed']  = $post_results['images_failed'];
		}

		if ( $do_orders && ! empty( $payload['orders'] ) && is_array( $payload['orders'] ) ) {
			$order_results = $this->import_orders( $payload['orders'] );
			$results['orders_created'] = $order_results['created'];
			$results['orders_skipped'] = $order_results['skipped'];
		}

		// Hand the whole payload over so the cart & pricing section is consumed
		// (and anything else it expects). Nothing else acts on this.
		if ( $do_commerce ) {
			/**
			 * Fires after the free-plugin import sections have run.
			 *
			 * The cart & pricing importer hooks here to consume the 'commerce' key and anything else
			 * it added during export. The implementation is responsible
			 * for its own create-only-by-slug semantics.
			 *
			 * @param array $payload Full import payload.
			 * @param array $results Counts populated by the free importer.
			 */
			do_action( 'pizzatier_import_payload', $payload, $results );
		}

		return $this->success_notice( $results );
	}

	/**
	 * Import settings using the same allowlist & sanitization rules as
	 * Settings::import_settings, applied directly here so we don't have
	 * to expose that private method publicly.
	 *
	 * @return int Number of settings restored.
	 */
	private function import_settings( array $data ): int {
		$allowed = array_flip( Settings::get_option_keys() );
		$count   = 0;

		foreach ( $data as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) { continue; }
			$key_safe = sanitize_key( $key );
			if ( '' === $key_safe ) { continue; }

			// Guarded on the way in as well as on the way out: an archive
			// written by an older build, or hand-edited, can still contain a
			// webhook secret or a foreign product ID.
			if ( ! \PizzaTier\Core\OptionRegistry::is_portable( $key_safe ) ) { continue; }

			// Type-preserving. Until 2.0.4 every non-array option was written
			// as wp_kses_post( (string) $value ), which flattened arrays to the
			// word "Array", collapsed booleans to '1' or '', and turned an
			// option that was simply unset on the source into an empty string
			// that then shadowed the destination's default.
			$clean = \PizzaTier\Core\OptionRegistry::sanitize_value( $key_safe, $value );
			if ( null === $clean ) { continue; }

			update_option( $key_safe, $clean );
			$count++;
		}

		return $count;
	}

	/**
	 * Restore setup-progress and preview-page state.
	 */
	private function import_state( array $state ): int {
		$allowed = array_flip( \PizzaTier\Core\OptionRegistry::state_keys() );
		$count   = 0;

		foreach ( $state as $key => $value ) {
			if ( ! isset( $allowed[ $key ] ) ) { continue; }
			$key_safe = sanitize_key( $key );
			if ( '' === $key_safe ) { continue; }

			// The destination keeps its own order numbering and schema version.
			if ( 'pizzatier_order_sequence' === $key_safe || 'pizzatier_db_version' === $key_safe ) { continue; }

			$clean = \PizzaTier\Core\OptionRegistry::sanitize_value( $key_safe, $value );
			if ( null === $clean ) { continue; }

			update_option( $key_safe, $clean );
			$count++;
		}

		return $count;
	}

	/**
	 * Sideload template background images and repoint their options locally.
	 */
	private function import_settings_images( array $images ): int {
		$allowed = \PizzaTier\Core\OptionRegistry::image_option_keys();
		$count   = 0;

		foreach ( $images as $key => $ref ) {
			$key_safe = sanitize_key( (string) $key );
			if ( ! isset( $allowed[ $key_safe ] ) || ! is_array( $ref ) ) { continue; }

			$attachment_id = $this->sideload_reference( $ref );
			if ( ! $attachment_id ) { continue; }

			$url = (string) wp_get_attachment_url( $attachment_id );
			if ( ! $url ) { continue; }

			update_option( $key_safe, esc_url_raw( $url ) );
			$count++;
		}

		return $count;
	}

	/**
	 * Import taxonomy terms with create-only-by-slug semantics.
	 *
	 * Two-pass: create terms in slug order first, then a second pass to
	 * wire up parent relationships once all terms exist.
	 *
	 * @return array{0:int,1:int} [created, skipped]
	 */
	private function import_terms( array $terms ): array {
		$created = 0;
		$skipped = 0;
		$slug_to_id = [];

		// Map existing terms first.
		$existing = get_terms( [ 'taxonomy' => self::TAXONOMY, 'hide_empty' => false ] );
		if ( is_array( $existing ) ) {
			foreach ( $existing as $t ) { $slug_to_id[ $t->slug ] = (int) $t->term_id; }
		}

		// Pass 1 — create.
		foreach ( $terms as $t ) {
			if ( ! is_array( $t ) ) { continue; }
			$slug = isset( $t['slug'] ) ? sanitize_title( (string) $t['slug'] ) : '';
			$name = isset( $t['name'] ) ? sanitize_text_field( (string) $t['name'] ) : '';
			if ( ! $slug || ! $name ) { continue; }

			if ( isset( $slug_to_id[ $slug ] ) ) {
				$skipped++;
				continue;
			}

			$result = wp_insert_term( $name, self::TAXONOMY, [
				'slug'        => $slug,
				'description' => isset( $t['description'] ) ? wp_kses_post( (string) $t['description'] ) : '',
			] );
			if ( is_wp_error( $result ) ) {
				$skipped++;
				continue;
			}
			$slug_to_id[ $slug ] = (int) $result['term_id'];
			$created++;
		}

		// Pass 2 — wire parent relationships for terms we just created.
		foreach ( $terms as $t ) {
			if ( ! is_array( $t ) ) { continue; }
			$slug        = isset( $t['slug'] ) ? sanitize_title( (string) $t['slug'] ) : '';
			$parent_slug = isset( $t['parent_slug'] ) ? sanitize_title( (string) $t['parent_slug'] ) : '';
			if ( ! $slug || ! $parent_slug )                  { continue; }
			if ( ! isset( $slug_to_id[ $slug ], $slug_to_id[ $parent_slug ] ) ) { continue; }
			wp_update_term( $slug_to_id[ $slug ], self::TAXONOMY, [
				'parent' => $slug_to_id[ $parent_slug ],
			] );
		}

		return [ $created, $skipped ];
	}

	/**
	 * Import posts with create-only-by-slug semantics.
	 *
	 * For each post: skip if a post with the same slug already exists on
	 * the same post_type; otherwise insert, copy meta, attach taxonomy
	 * terms, and (optionally) sideload the layer image by URL.
	 */
	private function import_posts( array $posts, bool $do_images ): array {
		$created = 0;
		$skipped = 0;
		$images_loaded = 0;
		$images_failed = 0;

		foreach ( $posts as $row ) {
			if ( ! is_array( $row ) ) { continue; }

			$post_type = isset( $row['post_type'] ) ? sanitize_key( (string) $row['post_type'] ) : '';
			$slug      = isset( $row['slug'] )      ? sanitize_title( (string) $row['slug'] )      : '';
			$title     = isset( $row['title'] )     ? sanitize_text_field( (string) $row['title'] ) : '';

			if ( ! $post_type || ! $slug || ! in_array( $post_type, self::POST_TYPES, true ) ) {
				$skipped++;
				continue;
			}

			// Create-only-by-slug: skip if an existing post on this type already uses the slug.
			$existing = get_page_by_path( $slug, OBJECT, $post_type );
			if ( $existing instanceof \WP_Post ) {
				$skipped++;
				continue;
			}

			$status_in = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'publish';
			$allowed_statuses = [ 'publish', 'draft', 'pending', 'private', 'future' ];
			if ( ! in_array( $status_in, $allowed_statuses, true ) ) { $status_in = 'publish'; }

			$post_id = wp_insert_post( [
				'post_type'    => $post_type,
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

			// Meta — wp_insert_post returns a fresh ID, so copy meta now.
			if ( ! empty( $row['meta'] ) && is_array( $row['meta'] ) ) {
				foreach ( $row['meta'] as $mkey => $mval ) {
					$mkey_safe = sanitize_key( (string) $mkey );
					if ( ! $mkey_safe ) { continue; }
					if ( $this->is_internal_meta_key( $mkey_safe ) ) { continue; }

					if ( is_array( $mval ) ) {
						// Multi-value meta: clear, then add each value.
						delete_post_meta( $post_id, $mkey_safe );
						foreach ( $mval as $v ) {
							add_post_meta( $post_id, $mkey_safe, $this->sanitize_meta_value( $v ) );
						}
					} else {
						update_post_meta( $post_id, $mkey_safe, $this->sanitize_meta_value( $mval ) );
					}
				}
			}

			// Taxonomy terms — only those that already exist on the destination
			// (term import pass should have run first, but we don't force it).
			if ( ! empty( $row['terms'] ) && is_array( $row['terms'] ) ) {
				$term_ids = [];
				foreach ( $row['terms'] as $term_slug ) {
					$term_slug = sanitize_title( (string) $term_slug );
					if ( ! $term_slug ) { continue; }
					$existing_term = get_term_by( 'slug', $term_slug, self::TAXONOMY );
					if ( $existing_term && ! is_wp_error( $existing_term ) ) {
						$term_ids[] = (int) $existing_term->term_id;
					}
				}
				if ( $term_ids ) {
					wp_set_object_terms( $post_id, $term_ids, self::TAXONOMY, false );
				}
			}

			// Layer image — sideload from URL if requested.
			if ( $do_images && ! empty( $row['layer_image'] ) && is_array( $row['layer_image'] ) ) {
				$ok = $this->sideload_layer_image( $post_id, $row['layer_image'] );
				if ( $ok ) { $images_loaded++; } else { $images_failed++; }
			}

			// Featured image and any SCF/ACF image fields (schema 2+).
			if ( $do_images && ! empty( $row['images'] ) && is_array( $row['images'] ) ) {
				[ $ok_count, $fail_count ] = $this->restore_post_images( $post_id, $row['images'] );
				$images_loaded += $ok_count;
				$images_failed += $fail_count;
			}

			$created++;
		}

		return [
			'created'       => $created,
			'skipped'       => $skipped,
			'images_loaded' => $images_loaded,
			'images_failed' => $images_failed,
		];
	}

	/**
	 * Best-effort sanitization for arbitrary meta values from import.
	 *
	 * Strings → kses_post. Numeric → preserved. Bool → preserved. Arrays
	 * → recursively sanitized. Anything else → coerced to string and kses'd.
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
	 * Rebuild every image-bearing meta value on a freshly-imported post.
	 *
	 * Each reference records whether the field originally held an attachment
	 * ID or a URL, so the restored value matches the shape the rest of the
	 * plugin expects to read back.
	 *
	 * @param int   $post_id Destination post.
	 * @param array $images  meta_key => reference map.
	 * @return array{0:int,1:int} [loaded, failed]
	 */
	private function restore_post_images( int $post_id, array $images ): array {
		$loaded = 0;
		$failed = 0;

		foreach ( $images as $meta_key => $ref ) {
			$key_safe = sanitize_key( (string) $meta_key );
			if ( '' === $key_safe || ! is_array( $ref ) ) { continue; }

			$attachment_id = $this->sideload_reference( $ref );
			if ( ! $attachment_id ) {
				$failed++;
				continue;
			}

			if ( '_thumbnail_id' === $key_safe ) {
				set_post_thumbnail( $post_id, $attachment_id );
			} elseif ( isset( $ref['store_as'] ) && 'url' === $ref['store_as'] ) {
				$url = (string) wp_get_attachment_url( $attachment_id );
				update_post_meta( $post_id, $key_safe, esc_url_raw( $url ) );
			} else {
				update_post_meta( $post_id, $key_safe, $attachment_id );
			}

			$loaded++;
		}

		return [ $loaded, $failed ];
	}

	/**
	 * Download an image reference into the media library.
	 *
	 * Shared by the post, settings and order image paths so the SSRF guards
	 * and temp-file cleanup live in exactly one place.
	 *
	 * @param array $ref Reference with at least a 'url' key.
	 * @return int Attachment ID, or 0 on failure.
	 */
	private function sideload_reference( array $ref ): int {
		$url = isset( $ref['url'] ) ? esc_url_raw( (string) $ref['url'] ) : '';
		if ( ! $url ) { return 0; }

		// SSRF hardening: http(s) only, and no loopback/private/reserved hosts
		// unless the site has explicitly opted in via http_request_host_is_external.
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		if ( 'http' !== $scheme && 'https' !== $scheme ) { return 0; }
		if ( ! wp_http_validate_url( $url ) )            { return 0; }

		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) { return 0; }

		$path     = wp_parse_url( $url, PHP_URL_PATH );
		$filename = ! empty( $ref['filename'] )
			? sanitize_file_name( (string) $ref['filename'] )
			: sanitize_file_name( $path ? basename( $path ) : 'image.png' );
		if ( '' === $filename ) { $filename = 'image.png'; }

		$caption = isset( $ref['caption'] ) ? wp_kses_post( (string) $ref['caption'] ) : '';

		$attachment_id = media_handle_sideload(
			[ 'name' => $filename, 'tmp_name' => $tmp ],
			0,
			$caption ?: null
		);

		if ( is_wp_error( $attachment_id ) ) {
			// media_handle_sideload() cleans the temp file on success only.
			if ( file_exists( $tmp ) ) { wp_delete_file( $tmp ); }
			return 0;
		}

		$alt = isset( $ref['alt'] ) ? sanitize_text_field( (string) $ref['alt'] ) : '';
		if ( $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}

		return (int) $attachment_id;
	}

	/**
	 * Sideload a layer image from an external URL into the media library
	 * and attach it to the freshly-imported post via the standard meta keys.
	 *
	 * Mirrors the upload pattern used by LayerImageMaker / LayerImageMetaBox.
	 */
	private function sideload_layer_image( int $post_id, array $img ): bool {
		$attachment_id = $this->sideload_reference( $img );
		if ( ! $attachment_id ) { return false; }

		// Write both keys: the ID for code that prefers a resolved attachment,
		// the URL for templates still reading the legacy field.
		$resolved_url = (string) wp_get_attachment_url( $attachment_id );
		update_post_meta( $post_id, '_pizzatier_layer_image_id', $attachment_id );
		update_post_meta( $post_id, 'pzl_layer_image', esc_url_raw( $resolved_url ) );

		return true;
	}

	/* ═══════════════════════════════════════════════════════════════════
	   ORDERS (opt-in — contains customer personal data)
	   ═══════════════════════════════════════════════════════════════════ */

	/**
	 * Serialize every native order.
	 *
	 * Only reached when the operator ticks "Include customer orders" on the
	 * export form. Orders hold names, phone numbers, email addresses and
	 * delivery addresses, so they are never part of a routine config backup.
	 *
	 * Staff-only customer notes are deliberately excluded: they live in user
	 * meta keyed to WordPress accounts that will not exist on the destination.
	 *
	 * @return array
	 */
	private function collect_orders(): array {
		$statuses = array_keys( \PizzaTier\Orders\OrderStatuses::all() );
		if ( ! $statuses ) { $statuses = [ \PizzaTier\Orders\OrderStatuses::DEFAULT_STATUS ]; }

		// Custom statuses registered with exclude_from_search => true are not
		// matched by post_status => 'any', so they are queried by name.
		$query = new \WP_Query( [
			'post_type'      => self::ORDER_POST_TYPE,
			'post_status'    => $statuses,
			'posts_per_page' => -1,
			'orderby'        => [ 'date' => 'ASC' ],
			'no_found_rows'  => true,
			'fields'         => 'all',
		] );

		$out = [];
		foreach ( $query->posts as $post ) {
			$id       = (int) $post->ID;
			$raw_meta = get_post_meta( $id );
			$meta     = [];

			foreach ( $raw_meta as $key => $values ) {
				if ( $this->is_internal_meta_key( $key ) ) { continue; }
				$meta[ $key ] = count( $values ) === 1
					? maybe_unserialize( $values[0] )
					: array_map( 'maybe_unserialize', $values );
			}

			$out[] = [
				'slug'       => (string) $post->post_name,
				'title'      => (string) $post->post_title,
				'content'    => (string) $post->post_content,
				'status'     => (string) $post->post_status,
				'date_gmt'   => (string) $post->post_date_gmt,
				'menu_order' => (int) $post->menu_order,
				'meta'       => $meta,
			];
		}

		wp_reset_postdata();

		return $out;
	}

	/**
	 * Restore orders, create-only by order number.
	 *
	 * Matching is on the stored order number rather than the post slug, since
	 * WordPress will happily hand two different orders the same auto-slug.
	 *
	 * @return array{created:int,skipped:int}
	 */
	private function import_orders( array $orders ): array {
		$created = 0;
		$skipped = 0;

		$number_key = \PizzaTier\Orders\Order::META_NUMBER;

		foreach ( $orders as $row ) {
			if ( ! is_array( $row ) || empty( $row['meta'] ) || ! is_array( $row['meta'] ) ) {
				$skipped++;
				continue;
			}

			$number = isset( $row['meta'][ $number_key ] )
				? sanitize_text_field( (string) $row['meta'][ $number_key ] )
				: '';

			// Without an order number there is no reliable identity to
			// de-duplicate on, so re-running the import would keep creating
			// copies. Skip rather than risk that.
			if ( '' === $number ) {
				$skipped++;
				continue;
			}

			if ( $this->order_number_exists( $number, $number_key ) ) {
				$skipped++;
				continue;
			}

			$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
			if ( ! \PizzaTier\Orders\OrderStatuses::is_valid( $status ) ) {
				$status = \PizzaTier\Orders\OrderStatuses::DEFAULT_STATUS;
			}

			$postarr = [
				'post_type'    => self::ORDER_POST_TYPE,
				'post_title'   => isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : $number,
				'post_content' => isset( $row['content'] ) ? wp_kses_post( (string) $row['content'] ) : '',
				'post_status'  => $status,
				'menu_order'   => isset( $row['menu_order'] ) ? (int) $row['menu_order'] : 0,
			];

			// Preserve when the order was placed, so history and sorting survive.
			if ( ! empty( $row['date_gmt'] ) ) {
				$date_gmt = sanitize_text_field( (string) $row['date_gmt'] );
				if ( $date_gmt && '0000-00-00 00:00:00' !== $date_gmt ) {
					$postarr['post_date_gmt'] = $date_gmt;
					$postarr['post_date']     = get_date_from_gmt( $date_gmt );
				}
			}

			$post_id = wp_insert_post( $postarr, true );
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				$skipped++;
				continue;
			}

			foreach ( $row['meta'] as $mkey => $mval ) {
				$mkey_safe = sanitize_key( (string) $mkey );
				if ( '' === $mkey_safe || $this->is_internal_meta_key( $mkey_safe ) ) { continue; }

				if ( is_array( $mval ) ) {
					delete_post_meta( $post_id, $mkey_safe );
					foreach ( $mval as $v ) {
						add_post_meta( $post_id, $mkey_safe, $this->sanitize_meta_value( $v ) );
					}
				} else {
					update_post_meta( $post_id, $mkey_safe, $this->sanitize_meta_value( $mval ) );
				}
			}

			$created++;
		}

		return [ 'created' => $created, 'skipped' => $skipped ];
	}

	/**
	 * Whether an order with this number already exists.
	 */
	private function order_number_exists( string $number, string $number_key ): bool {
		$statuses = array_keys( \PizzaTier\Orders\OrderStatuses::all() );
		if ( ! $statuses ) { $statuses = [ \PizzaTier\Orders\OrderStatuses::DEFAULT_STATUS ]; }

		$found = get_posts( [
			'post_type'      => self::ORDER_POST_TYPE,
			// 'any' matches nothing here: the pzt-* statuses are registered
			// with exclude_from_search => true, so they must be named.
			'post_status'    => $statuses,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Order number is the only stable identity across installs; admin-only, runs once per imported row.
			'meta_key'       => $number_key,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- See above.
			'meta_value'     => $number,
		] );

		return ! empty( $found );
	}

	/* ═══════════════════════════════════════════════════════════════════
	   UI
	   ═══════════════════════════════════════════════════════════════════ */

	private function render_page( string $notice ): void {


		// Quick statistics for the export preview.
		$post_counts = [];
		$total_posts = 0;
		foreach ( self::POST_TYPES as $pt ) {
			$count = wp_count_posts( $pt );
			$n = isset( $count->publish ) ? (int) $count->publish : 0;
			$n += isset( $count->draft ) ? (int) $count->draft : 0;
			$n += isset( $count->private ) ? (int) $count->private : 0;
			$post_counts[ $pt ] = $n;
			$total_posts += $n;
		}
		$settings_count = count( Settings::get_option_keys() );
		$term_count     = (int) wp_count_terms( [ 'taxonomy' => self::TAXONOMY, 'hide_empty' => false ] );

		?>
		<div class="wrap psm-wrap">
		<?php $this->render_styles(); ?>

		<div class="psm-header">
			<span class="dashicons dashicons-migrate psm-header__icon"></span>
			<div style="flex:1;">
				<h1 class="psm-header__title"><?php esc_html_e( 'Site Migration', 'pizzatier' ); ?></h1>
				<p class="psm-header__sub"><?php esc_html_e( 'Move an entire PizzaTier setup — settings, ingredients, custom fields, taxonomy, prices and cart configuration — to another WordPress installation.', 'pizzatier' ); ?></p>
			</div>
		</div>

		<?php if ( $notice ) { echo $notice; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-built, escaped notice. */ } ?>

		<div class="psm-grid">

			<!-- ═════ EXPORT CARD ═════ -->
			<div class="psm-card">
				<div class="psm-card__head">
					<h2><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export', 'pizzatier' ); ?></h2>
					<p><?php esc_html_e( 'Build a complete JSON snapshot of this site\'s PizzaTier setup. Layer images travel as URL references, not packaged files.', 'pizzatier' ); ?></p>
				</div>
				<div class="psm-card__body">

					<h3 class="psm-section-h"><?php esc_html_e( 'What\'s included', 'pizzatier' ); ?></h3>
					<ul class="psm-stats">
						<li><strong><?php echo (int) $settings_count; ?></strong> <?php esc_html_e( 'plugin settings', 'pizzatier' ); ?></li>
						<li><strong><?php echo (int) $total_posts; ?></strong> <?php esc_html_e( 'total content items', 'pizzatier' ); ?></li>
						<li><strong><?php echo (int) $term_count; ?></strong> <?php esc_html_e( 'ingredient groups', 'pizzatier' ); ?></li>
						<li><?php esc_html_e( 'Cart &amp; pricing settings, price grids and pizza products', 'pizzatier' ); ?></li>
						<li><?php esc_html_e( 'All template designs, ordering configuration and setup progress', 'pizzatier' ); ?></li>
					</ul>

					<details class="psm-details">
						<summary><?php esc_html_e( 'Per-type breakdown', 'pizzatier' ); ?></summary>
						<table class="psm-table">
							<tbody>
							<?php foreach ( $post_counts as $pt => $n ) :
								$label = ucwords( str_replace( '_', ' ', str_replace( 'pizzatier_', '', $pt ) ) );
							?>
								<tr>
									<td><?php echo esc_html( $label ); ?></td>
									<td><strong><?php echo (int) $n; ?></strong></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</details>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'pizzatier_site_export' ); ?>
						<input type="hidden" name="action" value="pizzatier_site_export">

						<div class="psm-checks">
							<label>
								<input type="checkbox" name="pizzatier_export_orders" value="1">
								<?php esc_html_e( 'Include customer orders', 'pizzatier' ); ?>
								<span class="psm-warn"><?php esc_html_e( '— contains personal data', 'pizzatier' ); ?></span>
							</label>
						</div>

						<p class="psm-note">
							<?php esc_html_e( 'Order records hold customer names, phone numbers, email addresses and delivery addresses. Leave this unticked for a settings-only backup. Private staff notes are never exported.', 'pizzatier' ); ?>
						</p>

						<button type="submit" class="button button-primary psm-export-btn">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Download Full Export (JSON)', 'pizzatier' ); ?>
						</button>
					</form>

					<p class="psm-note">
						<?php esc_html_e( 'Image URLs in the export point to this site. The destination installation must be able to reach those URLs over HTTP at import time.', 'pizzatier' ); ?>
					</p>
				</div>
			</div>

			<!-- ═════ IMPORT CARD ═════ -->
			<div class="psm-card">
				<div class="psm-card__head">
					<h2><span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Import', 'pizzatier' ); ?></h2>
					<p><?php esc_html_e( 'Restore a JSON site export. Imports are create-only: any existing post or term with the same slug is skipped, never overwritten.', 'pizzatier' ); ?></p>
				</div>
				<div class="psm-card__body">
					<form method="post" enctype="multipart/form-data" action="">
						<?php wp_nonce_field( 'pizzatier_site_import' ); ?>

						<h3 class="psm-section-h"><?php esc_html_e( 'Sections to restore', 'pizzatier' ); ?></h3>
						<div class="psm-checks">
							<label><input type="checkbox" name="pizzatier_import_settings_section" value="1" checked> <?php esc_html_e( 'Settings (all PizzaTier options)', 'pizzatier' ); ?> <span class="psm-warn"><?php esc_html_e( '— overwrites current settings', 'pizzatier' ); ?></span></label>
							<label><input type="checkbox" name="pizzatier_import_terms_section" value="1" checked> <?php esc_html_e( 'Ingredient groups (taxonomy)', 'pizzatier' ); ?></label>
							<label><input type="checkbox" name="pizzatier_import_posts_section" value="1" checked> <?php esc_html_e( 'Content items (toppings, crusts, sauces, cheeses, drizzles, cuts, sizes, presets) and their custom fields', 'pizzatier' ); ?></label>
							<label><input type="checkbox" name="pizzatier_import_images" value="1" checked> <?php esc_html_e( 'Sideload layer images from their URLs into this site\'s media library', 'pizzatier' ); ?></label>
							<label>
								<input type="checkbox" name="pizzatier_import_commerce_section" value="1" checked>
								<?php esc_html_e( 'Cart &amp; pricing settings, price grids and pizza products', 'pizzatier' ); ?>
							</label>
							<label>
								<input type="checkbox" name="pizzatier_import_orders_section" value="1">
								<?php esc_html_e( 'Customer orders', 'pizzatier' ); ?>
								<span class="psm-warn"><?php esc_html_e( '— personal data; only if the export included them', 'pizzatier' ); ?></span>
							</label>
						</div>

						<h3 class="psm-section-h"><?php esc_html_e( 'Export file', 'pizzatier' ); ?></h3>
						<input type="file" name="pizzatier_site_import_file" accept=".json,application/json" required class="psm-file">

						<div class="psm-warning-box">
							<span class="dashicons dashicons-warning"></span>
							<div>
								<strong><?php esc_html_e( 'Heads up:', 'pizzatier' ); ?></strong>
								<?php esc_html_e( 'Importing settings WILL overwrite this site\'s current PizzaTier settings. Posts, terms, price grids and pizza products are create-only by slug — already-present items will be left untouched.', 'pizzatier' ); ?>
							</div>
						</div>

						<button type="submit" name="pizzatier_site_import_submit" value="1" class="button button-primary psm-import-btn"
						        onclick="return confirm('<?php echo esc_attr__( 'Run the import now? Settings will be overwritten; posts and terms will be created if missing.', 'pizzatier' ); ?>');">
							<span class="dashicons dashicons-upload"></span>
							<?php esc_html_e( 'Run Import', 'pizzatier' ); ?>
						</button>
					</form>
				</div>
			</div>

		</div><!-- /.psm-grid -->

		<!-- ═════ HOW IT WORKS ═════ -->
		<div class="psm-howto">
			<h2><?php esc_html_e( 'How site migration works', 'pizzatier' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'On the source site: click Download Full Export. You get a single JSON file containing settings, all 8 content types with their custom fields, the ingredient-group taxonomy, and your cart and pricing configuration.', 'pizzatier' ); ?></li>
				<li><?php esc_html_e( 'On the destination site: install PizzaTier, upload the JSON, and click Run Import.', 'pizzatier' ); ?></li>
				<li><?php esc_html_e( 'Layer images are sideloaded from their original URLs into the destination media library. The source site must be reachable over HTTP at import time.', 'pizzatier' ); ?></li>
				<li><?php esc_html_e( 'Existing posts/terms with the same slug are skipped — re-running an import is safe and idempotent for everything except settings.', 'pizzatier' ); ?></li>
				<li><?php esc_html_e( 'Customer orders are opt-in on both sides: tick the box when exporting to include them in the file, and again when importing to restore them. They are matched by order number, so re-running an import never duplicates them.', 'pizzatier' ); ?></li>
			</ol>
		</div>

		</div><!-- /.wrap -->
		<?php
	}

	private function render_styles(): void {
		?>
	<?php /* Styles moved to assets/css/admin/pizzatier-admin.css (enqueued admin-wide). */ ?>
		<?php
	}

	/* ═══════════════════════════════════════════════════════════════════
	   NOTICES
	   ═══════════════════════════════════════════════════════════════════ */

	private function error_notice( string $msg ): string {
		return '<div class="notice notice-error is-dismissible"><p><strong>'
			. esc_html__( 'Import failed:', 'pizzatier' )
			. '</strong> ' . esc_html( $msg ) . '</p></div>';
	}

	private function success_notice( array $r ): string {
		$lines = [];
		if ( $r['settings'] > 0 ) {
			$lines[] = sprintf(
				/* translators: %d = setting count */
				_n( '%d setting restored', '%d settings restored', $r['settings'], 'pizzatier' ),
				$r['settings']
			);
		}
		if ( $r['terms_created'] > 0 || $r['terms_skipped'] > 0 ) {
			$lines[] = sprintf(
				/* translators: 1: created count, 2: skipped count */
				__( 'Ingredient groups: %1$d created, %2$d skipped (already existed)', 'pizzatier' ),
				$r['terms_created'],
				$r['terms_skipped']
			);
		}
		if ( $r['posts_created'] > 0 || $r['posts_skipped'] > 0 ) {
			$lines[] = sprintf(
				/* translators: 1: created, 2: skipped */
				__( 'Content items: %1$d created, %2$d skipped (slug already existed)', 'pizzatier' ),
				$r['posts_created'],
				$r['posts_skipped']
			);
		}
		if ( $r['images_loaded'] > 0 || $r['images_failed'] > 0 ) {
			$lines[] = sprintf(
				/* translators: 1: loaded, 2: failed */
				__( 'Layer images: %1$d sideloaded, %2$d failed', 'pizzatier' ),
				$r['images_loaded'],
				$r['images_failed']
			);
		}
		if ( $r['orders_created'] > 0 || $r['orders_skipped'] > 0 ) {
			$lines[] = sprintf(
				/* translators: 1: created, 2: skipped */
				__( 'Customer orders: %1$d created, %2$d skipped (order number already existed)', 'pizzatier' ),
				$r['orders_created'],
				$r['orders_skipped']
			);
		}
		if ( ! $lines ) {
			$lines[] = __( 'Nothing to import (no sections selected or all entries skipped).', 'pizzatier' );
		}

		$body  = '<strong>' . esc_html__( 'Import complete.', 'pizzatier' ) . '</strong> ';
		$body .= '<ul style="margin:6px 0 0 18px;">';
		foreach ( $lines as $line ) {
			$body .= '<li>' . esc_html( $line ) . '</li>';
		}
		$body .= '</ul>';

		return '<div class="notice notice-success is-dismissible"><p>' . $body . '</p></div>';
	}
}
