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
	 * Return all discovered template slugs → info arrays.
	 *
	 * @return array<string, array>
	 */
	public function get_available_templates(): array {
		$dirs      = apply_filters( 'pizzatier_template_dirs', [ PIZZATIER_TEMPLATES_DIR ] );
		$templates = [];

		foreach ( $dirs as $dir ) {
			if ( ! is_dir( $dir ) ) { continue; }
			foreach ( glob( trailingslashit( $dir ) . '*', GLOB_ONLYDIR ) as $template_dir ) {
				$info_file = trailingslashit( $template_dir ) . 'pztp-template-info.php';
				if ( ! file_exists( $info_file ) ) { continue; }
				$info = include $info_file;
				if ( is_array( $info ) ) {
					$slug              = basename( $template_dir );
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
		return PIZZATIER_TEMPLATES_DIR . $slug . '/' . $filename;
	}

	/**
	 * Get the URL to a file within a template directory.
	 */
	public function get_template_url( string $filename, string $slug = '' ): string {
		if ( ! $slug ) { $slug = $this->get_active_slug(); }
		return PIZZATIER_TEMPLATES_URL . $slug . '/' . $filename;
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
	 * Confirm path is inside the templates directory (traversal guard).
	 * Returns false and logs a debug notice if the directory doesn't exist.
	 */
	private function is_safe_path( string $path ): bool {
		$real_base = realpath( PIZZATIER_TEMPLATES_DIR );
		$real_dir  = realpath( dirname( $path ) );
		if ( ! $real_base || ! $real_dir ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions
				error_log( '[PizzaTier] TemplateLoader::is_safe_path() — path not found or outside templates dir: ' . $path );
			}
			return false;
		}
		return strpos( $real_dir, $real_base ) === 0;
	}
}
