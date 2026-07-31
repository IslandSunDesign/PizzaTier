<?php
/**
 * Handles server-side CSV export and import validation for the Price Grid.
 *
 * Export flow:
 *   Admin clicks "Export CSV" → JS calls admin-ajax with action pizzatier_commerce_export_grid
 *   → This class streams a CSV file response.
 *
 * Import flow:
 *   Admin selects a CSV file → JS parses it client-side (FileReader) and
 *   populates the grid inputs live. A separate AJAX call to pizzatier_commerce_validate_grid_csv
 *   performs server-side validation and returns any errors before the product is saved.
 *
 * Blank template flow:
 *   JS calls pizzatier_commerce_export_blank_grid_template to get a pre-formatted CSV
 *   using the current product's grid structure (or global defaults).
 *
 * @package PizzaTier\Commerce\PriceGrid
 */

namespace PizzaTier\Commerce\PriceGrid;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GridImportExport {

	/** @var Grid */
	private Grid $grid_model;

	public function __construct( Grid $grid_model ) {
		$this->grid_model = $grid_model;
	}

	// -------------------------------------------------------------------------
	// Hook registration
	// -------------------------------------------------------------------------

	public function register(): void {
		add_action( 'wp_ajax_pizzatier_commerce_export_grid',                [ $this, 'handle_export' ] );
		add_action( 'wp_ajax_pizzatier_commerce_export_blank_grid_template', [ $this, 'handle_blank_template' ] );
		add_action( 'wp_ajax_pizzatier_commerce_validate_grid_csv',          [ $this, 'handle_validate_csv' ] );
		// Per-layer grid AJAX actions (Phase 9).
		add_action( 'wp_ajax_pizzatier_commerce_export_layer_grid',          [ $this, 'handle_layer_export' ] );
		add_action( 'wp_ajax_pizzatier_commerce_import_layer_grid',          [ $this, 'handle_layer_import' ] );
		// Bulk tools.
		add_action( 'wp_ajax_pizzatier_commerce_get_pizza_products',         [ $this, 'handle_get_pizza_products' ] );
		add_action( 'wp_ajax_pizzatier_commerce_get_product_grid',           [ $this, 'handle_get_product_grid' ] );
		add_action( 'wp_ajax_pizzatier_commerce_validate_csv_text',          [ $this, 'handle_validate_csv_text' ] );
	}

	// -------------------------------------------------------------------------
	// Export — full grid
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler: export the saved price grid as a downloadable CSV.
	 *
	 * Expected POST params: product_id (int), nonce.
	 */
	public function handle_export(): void {
		$this->verify_nonce();
		$this->verify_capability();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by the guard called at the top of this handler, which wp_die()s on failure; PHPCS cannot trace it through a helper.
		$product_id = absint( $_POST['product_id'] ?? 0 );
		if ( ! $product_id ) {
			wp_die( esc_html__( 'Invalid product ID.', 'pizzatier' ), '', 400 );
		}

		$grid = $this->grid_model->get( $product_id );

		if ( ! $grid ) {
			// No grid saved yet — export the blank template instead.
			$sizes     = $this->grid_model->default_sizes();
			$fractions = $this->grid_model->default_fractions();
			$cells     = [];
		} else {
			$sizes     = $grid['sizes'];
			$fractions = $grid['fractions'];
			$cells     = $grid['cells'];
		}

		$filename = 'pizza-price-grid-' . $product_id . '.csv';
		$this->stream_csv( $filename, $sizes, $fractions, $cells );
	}

	// -------------------------------------------------------------------------
	// Export — blank template
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler: export a blank CSV template showing the expected format.
	 *
	 * Expected POST params: product_id (int), nonce.
	 */
	public function handle_blank_template(): void {
		$this->verify_nonce();
		$this->verify_capability();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by the guard called at the top of this handler, which wp_die()s on failure; PHPCS cannot trace it through a helper.
		$product_id = absint( $_POST['product_id'] ?? 0 );

		// Use the product's existing grid structure if available, otherwise defaults.
		if ( $product_id ) {
			$sizes     = $this->grid_model->get_sizes( $product_id );
			$fractions = $this->grid_model->get_fractions( $product_id );
		} else {
			$sizes     = $this->grid_model->default_sizes();
			$fractions = $this->grid_model->default_fractions();
		}

		$this->stream_csv( 'pizza-price-grid-template.csv', $sizes, $fractions, [] );
	}

	// -------------------------------------------------------------------------
	// Import validation
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler: validate a parsed CSV payload before the product is saved.
	 *
	 * JS sends the parsed grid data (not the raw CSV file) as JSON in the
	 * 'grid_data' POST field.  We validate it against Grid::validate() and
	 * return the result so the UI can show errors before save.
	 *
	 * Expected POST params: grid_data (JSON string), nonce.
	 */
	public function handle_validate_csv(): void {
		$this->verify_nonce();
		$this->verify_capability();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified by the guard called at the top of this handler, which wp_die()s on failure; PHPCS cannot trace it through a helper. Sanitized immediately below, per element, before use.
		$raw = isset( $_POST['grid_data'] ) ? wp_unslash( $_POST['grid_data'] ) : '';

		if ( ! $raw ) {
			wp_send_json_error( [ 'message' => __( 'No grid data received.', 'pizzatier' ) ] );
			return;
		}

		$data = json_decode( $raw, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not parse grid data.', 'pizzatier' ) ] );
			return;
		}

		$result = $this->grid_model->validate( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			] );
			return;
		}

		wp_send_json_success( [
			'message'   => __( 'Grid data is valid.', 'pizzatier' ),
			'sizes'     => $result['sizes'],
			'fractions' => $result['fractions'],
			'cell_count' => count( $result['cells'] ),
		] );
	}

	// -------------------------------------------------------------------------
	// Per-layer grid export (Phase 9)
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler: export a CPT layer's price grid as a downloadable CSV.
	 *
	 * Expected POST params: layer_post_id (int), nonce.
	 *
	 * If the layer has no custom grid yet, exports a blank template using
	 * the global default sizes and fractions so the admin can fill it in.
	 */
	public function handle_layer_export(): void {
		$this->verify_nonce();
		$this->verify_capability();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by the guard called at the top of this handler, which wp_die()s on failure; PHPCS cannot trace it through a helper.
		$layer_post_id = absint( $_POST['layer_post_id'] ?? 0 );
		if ( ! $layer_post_id ) {
			wp_die( esc_html__( 'Invalid layer post ID.', 'pizzatier' ), '', 400 );
		}

		$layer_grid = $this->grid_model->get_layer_grid( $layer_post_id );
		$post_name  = get_post_field( 'post_name', $layer_post_id ) ?: (string) $layer_post_id;

		if ( $layer_grid ) {
			$sizes     = $layer_grid['sizes'];
			$fractions = $layer_grid['fractions'];
			$cells     = $layer_grid['cells'];
		} else {
			// No custom grid — export blank template with global defaults.
			$sizes     = $this->grid_model->default_sizes();
			$fractions = $this->grid_model->default_fractions();
			$cells     = [];
		}

		$this->stream_csv( 'layer-grid-' . $post_name . '.csv', $sizes, $fractions, $cells );
	}

	// -------------------------------------------------------------------------
	// Per-layer grid import (Phase 9)
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler: validate and save a CSV payload as a layer's price grid.
	 *
	 * Unlike the product grid import (which only validates — the grid is
	 * saved by the standard WC product save), the layer grid has its own
	 * save button / AJAX flow so we validate AND persist in one request.
	 *
	 * Expected POST params:
	 *   layer_post_id (int)    — the CPT post ID
	 *   grid_data     (string) — JSON-encoded { sizes, fractions, cells }
	 *   nonce         (string)
	 *
	 * Success response:
	 *   { success: true, data: { message, sizes, fractions, cell_count } }
	 *
	 * Error response:
	 *   { success: false, data: { message, code? } }
	 */
	public function handle_layer_import(): void {
		$this->verify_nonce();
		$this->verify_capability();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by the guard called at the top of this handler, which wp_die()s on failure; PHPCS cannot trace it through a helper.
		$layer_post_id = absint( $_POST['layer_post_id'] ?? 0 );
		if ( ! $layer_post_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid layer post ID.', 'pizzatier' ) ] );
			return;
		}

		// Verify the post exists and is a PizzaTier CPT.
		$post = get_post( $layer_post_id );
		if ( ! $post instanceof \WP_Post ) {
			wp_send_json_error( [ 'message' => __( 'Layer post not found.', 'pizzatier' ) ] );
			return;
		}

		$allowed_types = [
			'pizzatier_toppings', 'pizzatier_crusts', 'pizzatier_sauces',
			'pizzatier_cheeses', 'pizzatier_drizzles', 'pizzatier_cuts', 'pizzatier_sizes',
		];
		if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
			wp_send_json_error( [ 'message' => __( 'Post is not a PizzaTier ingredient.', 'pizzatier' ) ] );
			return;
		}

		// Capability check on the specific post.
		if ( ! current_user_can( 'edit_post', $layer_post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to edit this post.', 'pizzatier' ) ] );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is verified by the guard called at the top of this handler, which wp_die()s on failure; PHPCS cannot trace it through a helper. Sanitized immediately below, per element, before use.
		$raw = isset( $_POST['grid_data'] ) ? wp_unslash( $_POST['grid_data'] ) : '';
		if ( ! $raw ) {
			wp_send_json_error( [ 'message' => __( 'No grid data received.', 'pizzatier' ) ] );
			return;
		}

		$data = json_decode( $raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not parse grid data.', 'pizzatier' ) ] );
			return;
		}

		$result = $this->grid_model->save_layer_grid( $layer_post_id, $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			] );
			return;
		}

		// Return the saved grid so the UI can repopulate.
		$saved = $this->grid_model->get_layer_grid( $layer_post_id );
		wp_send_json_success( [
			'message'    => __( 'Layer pricing grid imported and saved.', 'pizzatier' ),
			'sizes'      => $saved ? $saved['sizes']     : [],
			'fractions'  => $saved ? $saved['fractions'] : [],
			'cell_count' => $saved ? count( $saved['cells'] ) : 0,
		] );
	}

	// -------------------------------------------------------------------------
	// Get pizza products list (for copy-from-product dropdown)
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler: return a list of Pizza products (id + title) for the
	 * copy-from-product dropdown, excluding the current product.
	 *
	 * Expected POST params: exclude_id (int, optional), nonce.
	 */
	public function handle_get_pizza_products(): void {
		$this->verify_nonce();
		$this->verify_capability();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by the guard called at the top of this handler, which wp_die()s on failure; PHPCS cannot trace it through a helper.
		$exclude_id = absint( $_POST['exclude_id'] ?? 0 );

		// Query all products whose product_type term is 'pizza'.
		$query_args = [
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- WooCommerce stores product type as a taxonomy term; there is no meta or column alternative. Result sets are small and admin-only.
				[
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => 'pizza',
				],
			],
		];

		$ids      = get_posts( $query_args );
		$products = [];

		foreach ( (array) $ids as $pid ) {
			// Exclude the current product here rather than via post__not_in, which
			// makes the query build an expensive NOT IN subquery. Only IDs are
			// fetched, so skipping a single row in PHP is strictly cheaper.
			if ( $exclude_id && (int) $pid === $exclude_id ) {
				continue;
			}

			$has_grid = (bool) $this->grid_model->get( (int) $pid );
			$products[] = [
				'id'      => (int) $pid,
				'title'   => get_the_title( (int) $pid ),
				'hasGrid' => $has_grid,
			];
		}

		wp_send_json_success( [ 'products' => $products ] );
	}

	// -------------------------------------------------------------------------
	// Get product grid (for copy-from-product)
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler: return the price grid of another pizza product as JSON.
	 *
	 * Expected POST params: product_id (int), nonce.
	 */
	public function handle_get_product_grid(): void {
		$this->verify_nonce();
		$this->verify_capability();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by the guard called at the top of this handler, which wp_die()s on failure; PHPCS cannot trace it through a helper.
		$product_id = absint( $_POST['product_id'] ?? 0 );
		if ( ! $product_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product ID.', 'pizzatier' ) ] );
			return;
		}

		$grid = $this->grid_model->get( $product_id );

		if ( ! $grid ) {
			// Return defaults so the admin still gets a usable grid.
			$grid = [
				'sizes'     => $this->grid_model->default_sizes(),
				'fractions' => $this->grid_model->default_fractions(),
				'cells'     => [],
			];
		}

		// Build a cells object keyed by "Size|Fraction" with string values.
		$cells_out = [];
		foreach ( $grid['cells'] as $key => $price ) {
			$cells_out[ $key ] = number_format( (float) $price, 2, '.', '' );
		}

		wp_send_json_success( [
			'sizes'     => $grid['sizes'],
			'fractions' => $grid['fractions'],
			'cells'     => $cells_out,
			'csv'       => $this->build_csv( $grid['sizes'], $grid['fractions'], $grid['cells'] ),
		] );
	}

	// -------------------------------------------------------------------------
	// Validate pasted CSV text
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler: parse and validate a raw CSV string pasted into a textarea.
	 *
	 * Expected POST params: csv_text (string), nonce.
	 *
	 * Returns the parsed grid data on success so JS can rebuild the table.
	 */
	public function handle_validate_csv_text(): void {
		$this->verify_nonce();
		$this->verify_capability();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by the guard called at the top of this handler, which wp_die()s on failure; PHPCS cannot trace it through a helper.
		$csv_text = isset( $_POST['csv_text'] )
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by the guard called at the top of this handler, which wp_die()s on failure; PHPCS cannot trace it through a helper.
			? sanitize_textarea_field( wp_unslash( $_POST['csv_text'] ) )
			: '';

		if ( '' === trim( $csv_text ) ) {
			wp_send_json_error( [ 'message' => __( 'No CSV text received.', 'pizzatier' ) ] );
			return;
		}

		$parsed = $this->parse_csv( $csv_text );

		if ( is_wp_error( $parsed ) ) {
			wp_send_json_error( [
				'message' => $parsed->get_error_message(),
				'code'    => $parsed->get_error_code(),
			] );
			return;
		}

		$result = $this->grid_model->validate( $parsed );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [
				'message' => $result->get_error_message(),
				'code'    => $result->get_error_code(),
			] );
			return;
		}

		// Return cells as string values for JS.
		$cells_out = [];
		foreach ( $result['cells'] as $key => $price ) {
			$cells_out[ $key ] = number_format( (float) $price, 2, '.', '' );
		}

		wp_send_json_success( [
			'message'    => __( 'CSV is valid. Review the grid and save.', 'pizzatier' ),
			'sizes'      => $result['sizes'],
			'fractions'  => $result['fractions'],
			'cells'      => $cells_out,
			'cell_count' => count( $result['cells'] ),
		] );
	}

	// -------------------------------------------------------------------------
	// CSV generation
	// -------------------------------------------------------------------------

	/**
	 * Build a CSV string from the given grid data.
	 *
	 * Format:
	 *   Row 1 (header): "Size", [fraction1], [fraction2], …
	 *   Row 2+:         [size],  [price],    [price],    …
	 *
	 * Empty cells output as "0.00".
	 *
	 * @param string[]            $sizes
	 * @param string[]            $fractions
	 * @param array<string,float> $cells
	 * @return string
	 */
	public function build_csv( array $sizes, array $fractions, array $cells ): string {
		$rows = [];

		// Header row.
		$header = [ 'Size' ];
		foreach ( $fractions as $f ) {
			$header[] = $f;
		}
		$rows[] = $header;

		// Data rows.
		foreach ( $sizes as $size ) {
			$row = [ $size ];
			foreach ( $fractions as $fraction ) {
				$key     = $this->grid_model->cell_key( $size, $fraction );
				$price   = isset( $cells[ $key ] ) ? $cells[ $key ] : 0.00;
				$row[]   = number_format( (float) $price, 2, '.', '' );
			}
			$rows[] = $row;
		}

		// Write to an in-memory buffer. WP_Filesystem is the right tool for the
		// filesystem, but this never touches it: php://memory is a stream used
		// so fputcsv() can handle CSV quoting and escaping rather than this
		// code hand-rolling it.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory stream, not a file.
		$buffer = fopen( 'php://memory', 'r+' );
		foreach ( $rows as $row ) {
			// $escape passed explicitly. PHP 8.4 deprecates relying on the
			// default, and an empty string disables PHP's proprietary backslash
			// escaping in favour of plain RFC 4180 quoting — which is what
			// spreadsheet software actually expects. Supported since PHP 7.4,
			// this plugin's floor.
			fputcsv( $buffer, $row, ',', '"', '' );
		}
		rewind( $buffer );
		$csv = stream_get_contents( $buffer );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory stream, not a file.
		fclose( $buffer );

		return (string) $csv;
	}

	/**
	 * Parse a CSV string into a grid data array.
	 *
	 * Inverse of build_csv(). Returns an array shaped for Grid::validate()
	 * or a WP_Error if the format is unrecognisable.
	 *
	 * @param string $csv_string
	 * @return array|\WP_Error
	 */
	public function parse_csv( string $csv_string ) {
		$csv_string = trim( $csv_string );

		if ( '' === $csv_string ) {
			return new \WP_Error( 'pizzatier_commerce_csv_empty', __( 'The CSV file is empty.', 'pizzatier' ) );
		}

		// Normalise line endings.
		$csv_string = str_replace( [ "\r\n", "\r" ], "\n", $csv_string );

		$lines  = explode( "\n", $csv_string );
		$rows   = [];

		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$parsed = str_getcsv( $line, ',', '"', '' );
			$rows[] = $parsed;
		}

		if ( count( $rows ) < 2 ) {
			return new \WP_Error(
				'pizzatier_commerce_csv_too_short',
				__( 'CSV must have a header row and at least one data row.', 'pizzatier' )
			);
		}

		$header    = array_map( 'trim', $rows[0] );
		$fractions = array_slice( $header, 1 ); // everything after "Size"

		if ( empty( $fractions ) ) {
			return new \WP_Error(
				'pizzatier_commerce_csv_no_fractions',
				__( 'CSV header must contain at least one fraction column after "Size".', 'pizzatier' )
			);
		}

		$sizes = [];
		$cells = [];

		for ( $i = 1; $i < count( $rows ); $i++ ) {
			$row  = $rows[ $i ];
			$size = trim( $row[0] ?? '' );

			if ( '' === $size ) {
				continue;
			}

			$sizes[] = $size;

			foreach ( $fractions as $j => $fraction ) {
				$key        = $this->grid_model->cell_key( $size, $fraction );
				$raw_price  = trim( $row[ $j + 1 ] ?? '' );
				$cells[ $key ] = $raw_price;
			}
		}

		if ( empty( $sizes ) ) {
			return new \WP_Error(
				'pizzatier_commerce_csv_no_sizes',
				__( 'CSV contains no size rows.', 'pizzatier' )
			);
		}

		return [
			'sizes'     => $sizes,
			'fractions' => $fractions,
			'cells'     => $cells,
		];
	}

	// -------------------------------------------------------------------------
	// Stream helpers
	// -------------------------------------------------------------------------

	/**
	 * Send CSV data as a file download response and terminate.
	 *
	 * @param string              $filename
	 * @param string[]            $sizes
	 * @param string[]            $fractions
	 * @param array<string,float> $cells
	 */
	private function stream_csv( string $filename, array $sizes, array $fractions, array $cells ): void {
		$csv = $this->build_csv( $sizes, $fractions, $cells );

		// Sanitise filename — only allow safe characters.
		$filename = preg_replace( '/[^a-z0-9_\-\.]/i', '-', $filename );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $csv ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw CSV file download (Content-Type: text/csv); escaping would corrupt the payload.
		echo $csv;
		exit;
	}

	// -------------------------------------------------------------------------
	// Security helpers
	// -------------------------------------------------------------------------

	private function verify_nonce(): void {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'pizzatier_commerce_admin' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pizzatier' ), '', 403 );
		}
	}

	private function verify_capability(): void {
		if ( ! current_user_can( 'edit_products' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'pizzatier' ), '', 403 );
		}
	}
}
