<?php
namespace PizzaTier\Template;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Locates and loads the active PizzaTier template.
 *
 * Each template directory must contain pztp-template-info.php returning an array.
 * Third-party templates can register additional directories via the
 * pizzatier_template_dirs filter.
 *
 * Template info keys:
 *   name             (string) Display name
 *   author           (string)
 *   author_url       (string)
 *   description      (string)
 *   version          (string)
 *   license          (string)
 *   tags             (string[])
 *   function_prefix  (string) Snake-case prefix used for PHP helper functions
 *                             in pztp-containers-menu.php. Must be unique per
 *                             template to avoid fatal collisions.
 *                             Convention: pzt_{slug}   e.g. pzt_colorbox
 */
class TemplateLoader {

	/**
	 * Template root directories, highest precedence first.
	 *
	 * A theme can ship its own templates in a pzttemplates/ folder: the child
	 * theme's folder wins over the parent theme's, and both win over the
	 * plugin's bundled templates. Directories added through the
	 * pizzatier_template_dirs filter keep their previous precedence among
	 * themselves (a later directory overrides an earlier one).
	 *
	 * @return array<int, array{dir:string,url:string,source:string}>
	 */
	public function get_template_roots(): array {
		$roots = [];
		$seen  = [];

		$add = static function ( string $dir, string $url, string $source ) use ( &$roots, &$seen ): void {
			$dir = trailingslashit( wp_normalize_path( $dir ) );
			if ( isset( $seen[ $dir ] ) || ! is_dir( $dir ) ) { return; }
			$seen[ $dir ] = true;
			$roots[]      = [ 'dir' => $dir, 'url' => trailingslashit( $url ), 'source' => $source ];
		};

		if ( function_exists( 'get_stylesheet_directory' ) ) {
			$add( get_stylesheet_directory() . '/pzttemplates/', get_stylesheet_directory_uri() . '/pzttemplates/', 'theme' );
			$add( get_template_directory() . '/pzttemplates/', get_template_directory_uri() . '/pzttemplates/', 'theme' );
		}

		$dirs = (array) apply_filters( 'pizzatier_template_dirs', [ PIZZATIER_TEMPLATES_DIR ] );
		foreach ( array_reverse( $dirs ) as $dir ) {
			if ( ! is_string( $dir ) || '' === $dir ) { continue; }
			$norm = trailingslashit( wp_normalize_path( $dir ) );
			if ( trailingslashit( wp_normalize_path( PIZZATIER_TEMPLATES_DIR ) ) === $norm ) {
				$add( $dir, PIZZATIER_TEMPLATES_URL, 'plugin' );
			} else {
				$add( $dir, $this->url_for_dir( $norm ), 'custom' );
			}
		}

		return $roots;
	}

	/**
	 * Best-effort URL for a directory under wp-content (for filtered dirs).
	 */
	private function url_for_dir( string $dir ): string {
		$content_dir = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );
		if ( 0 === strpos( $dir, $content_dir ) ) {
			return trailingslashit( content_url( substr( $dir, strlen( $content_dir ) ) ) );
		}
		$abspath = trailingslashit( wp_normalize_path( ABSPATH ) );
		if ( 0 === strpos( $dir, $abspath ) ) {
			return trailingslashit( site_url( substr( $dir, strlen( $abspath ) ) ) );
		}
		return '';
	}

	/**
	 * The root that provides a template slug, or null when none does.
	 *
	 * @return array{dir:string,url:string,source:string}|null
	 */
	public function get_template_root( string $slug ): ?array {
		if ( '' === $slug || $slug !== basename( $slug ) ) { return null; }
		foreach ( $this->get_template_roots() as $root ) {
			if ( file_exists( $root['dir'] . $slug . '/pztp-template-info.php' ) ) {
				return $root;
			}
		}
		return null;
	}

	/**
	 * Return all discovered template slugs → info arrays.
	 *
	 * @return array<string, array>
	 */
	public function get_available_templates(): array {
		$templates = [];

		foreach ( $this->get_template_roots() as $root ) {
			$found = glob( $root['dir'] . '*', GLOB_ONLYDIR );
			if ( ! is_array( $found ) ) { continue; }
			foreach ( $found as $template_dir ) {
				$slug = basename( $template_dir );
				// Higher-precedence roots come first and win.
				if ( isset( $templates[ $slug ] ) ) { continue; }
				$info_file = trailingslashit( $template_dir ) . 'pztp-template-info.php';
				if ( ! file_exists( $info_file ) ) { continue; }
				$info = include $info_file;
				if ( is_array( $info ) ) {
					$templates[ $slug ] = $info;
				}
			}
		}

		return $templates;
	}

	/**
	 * Get the active template slug.
	 */
	public function get_active_slug(): string {
		$slug      = (string) get_option( 'pizzatier_setting_global_template', 'nightpie' );
		$templates = $this->get_available_templates();
		// Fall back to the first available template rather than hard-coding a name
		if ( ! isset( $templates[ $slug ] ) ) {
			$slug = array_key_first( $templates ) ?? 'nightpie';
		}
		return $slug;
	}

	/**
	 * Get the PHP function prefix declared by a template.
	 * Falls back to pzt_{slug} if the key is absent (covers legacy templates).
	 */
	public function get_function_prefix( string $slug = '' ): string {
		if ( ! $slug ) { $slug = $this->get_active_slug(); }
		$templates = $this->get_available_templates();
		$prefix    = $templates[ $slug ]['function_prefix'] ?? ( 'pzt_' . $slug );
		return sanitize_key( $prefix );
	}

	/**
	 * Get the filesystem path to a file within a template directory.
	 */
	public function get_template_file( string $filename, string $slug = '' ): string {
		if ( ! $slug ) { $slug = $this->get_active_slug(); }
		$root = $this->get_template_root( $slug );
		$base = $root ? $root['dir'] : PIZZATIER_TEMPLATES_DIR;
		return $base . $slug . '/' . $filename;
	}

	/**
	 * Get the URL to a file within a template directory.
	 */
	public function get_template_url( string $filename, string $slug = '' ): string {
		if ( ! $slug ) { $slug = $this->get_active_slug(); }
		$root = $this->get_template_root( $slug );
		$base = ( $root && '' !== $root['url'] ) ? $root['url'] : PIZZATIER_TEMPLATES_URL;
		return $base . $slug . '/' . $filename;
	}

	/**
	 * Include a template file safely (path must be inside PIZZATIER_TEMPLATES_DIR).
	 */
	public function include_file( string $filename, string $slug = '' ): void {
		$path = $this->get_template_file( $filename, $slug );
		if ( $this->is_safe_path( $path ) && file_exists( $path ) ) {
			include $path;
		}
	}

	/**
	 * Load pztp-template-custom.php for the active (or given) template.
	 * Called once on wp_enqueue_scripts so the template can register its own
	 * actions/hooks without being coupled to the shortcode render path.
	 */
	public function load_template_custom( string $slug = '' ): void {
		$path = $this->get_template_file( 'pztp-template-custom.php', $slug );
		if ( $this->is_safe_path( $path ) && file_exists( $path ) ) {
			include_once $path;
		}
	}

	/**
	 * Confirm path is inside one of the template roots (traversal guard).
	 * Returns false and logs a debug notice if the directory doesn't exist.
	 */
	private function is_safe_path( string $path ): bool {
		$real_dir = realpath( dirname( $path ) );
		if ( $real_dir ) {
			$real_dir = trailingslashit( wp_normalize_path( $real_dir ) );
			foreach ( $this->get_template_roots() as $root ) {
				$real_base = realpath( $root['dir'] );
				if ( ! $real_base ) { continue; }
				$real_base = trailingslashit( wp_normalize_path( $real_base ) );
				// Strictly below the root: a template's own folder, never the root.
				if ( $real_dir !== $real_base && 0 === strpos( $real_dir, $real_base ) ) {
					return true;
				}
			}
		}
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			error_log( '[PizzaTier] TemplateLoader::is_safe_path() — path not found or outside template dirs: ' . $path );
		}
		return false;
	}
}
