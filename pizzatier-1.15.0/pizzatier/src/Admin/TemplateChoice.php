<?php
namespace PizzaTier\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Template selection page — with live iframe preview.
 *
 * Layout: split pane — template cards on the left, live iframe on the right.
 * Hovering a card loads that template into the iframe via a signed preview URL.
 * Clicking Activate writes to the DB.
 */
class TemplateChoice {

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		// Handle activation
		if ( isset( $_POST['pizzatier_activate_template'], $_POST['_wpnonce'] )
		     && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'pizzatier_activate_template' ) ) {
			$slug = sanitize_key( $_POST['pizzatier_activate_template'] );
			// Validate the slug against actually available templates before writing.
			$loader            = new \PizzaTier\Template\TemplateLoader();
			$available_slugs   = array_keys( (array) $loader->get_available_templates() );
			if ( $slug && in_array( $slug, $available_slugs, true ) ) {
				update_option( 'pizzatier_setting_global_template', $slug );
				echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( /* translators: %s = template name. */ esc_html__( 'Template %s activated.', 'pizzatier' ), '<strong>' . esc_html( $slug ) . '</strong>' ) . '</p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Invalid template.', 'pizzatier' ) . '</p></div>';
			}
		}

		$active = (string) get_option( 'pizzatier_setting_global_template', 'nightpie' );

		// ── Handle template settings save ──────────────────────────
		if ( isset( $_POST['pizzatier_template_settings_save'], $_POST['_wpnonce'] )
		     && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'pizzatier_template_settings_save' ) ) {
			$this->save_template_settings();
			echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Template settings saved.', 'pizzatier' ) . '</strong></p></div>';
			// Re-read active after save
			$active = (string) get_option( 'pizzatier_setting_global_template', 'nightpie' );
		}

		// ── Scan template directories ───────────────────────────────
		$plugin_dir = PIZZATIER_TEMPLATES_DIR;
		$plugin_url = PIZZATIER_TEMPLATES_URL;
		$theme_dir  = trailingslashit( get_stylesheet_directory() ) . 'pzttemplates/';
		$theme_url  = trailingslashit( get_stylesheet_directory_uri() ) . 'pzttemplates/';

		$templates = [];
		foreach ( [ [ $plugin_dir, $plugin_url, 'plugin' ], [ $theme_dir, $theme_url, 'theme' ] ] as [ $dir, $url, $source ] ) {
			if ( ! is_dir( $dir ) ) { continue; }
			foreach ( (array) scandir( $dir ) as $folder ) {
				if ( $folder === '.' || $folder === '..' || ! is_dir( $dir . $folder ) ) { continue; }
				$info_file = $dir . $folder . '/pztp-template-info.php';
				$info      = file_exists( $info_file ) ? include $info_file : [];
				if ( ! is_array( $info ) ) { $info = []; }

				$preview_url = '';
				foreach ( [ 'preview.jpg', 'preview.png', 'preview.webp' ] as $pf ) {
					if ( file_exists( $dir . $folder . '/' . $pf ) ) {
						$preview_url = $url . $folder . '/' . $pf;
						break;
					}
				}

				$templates[ $folder ] = [
					'slug'        => $folder,
					'source'      => $source,
					'dir'         => $dir . $folder . '/',
					'url'         => $url . $folder . '/',
					'info'        => $info,
					'preview_url' => $preview_url,
				];
			}
		}

		// ── Load active template settings fields ─────────────────────
		$template_settings = [];
		if ( $active ) {
			$options_paths = [
				get_stylesheet_directory() . '/pzttemplates/' . $active . '/pztp-template-options.php',
				PIZZATIER_TEMPLATES_DIR . $active . '/pztp-template-options.php',
			];
			foreach ( $options_paths as $options_file ) {
				if ( file_exists( $options_file ) ) {
					$template_settings = include $options_file;
					if ( ! is_array( $template_settings ) ) { $template_settings = []; }
					break;
				}
			}
		}

		// ── Preview page URL ────────────────────────────────────────
		$preview_page_url  = (string) get_option( 'pizzatier_template_preview_url', '' );
		$preview_page_auto = false; // true when we found a page automatically

		if ( ! $preview_page_url ) {
			// Search post_content for [pizza_builder] shortcode
			global $wpdb;
			$found_id = wp_cache_get( 'pizzatier_preview_page_id', 'pizzatier' );
			if ( false === $found_id ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- One-off admin lookup with static SQL (no user input); result cached below.
				$found_id = $wpdb->get_var(
					"SELECT ID FROM {$wpdb->posts}
					 WHERE post_status = 'publish'
					   AND post_type IN ('page','post')
					   AND post_content LIKE '%pizza_builder%'
					 LIMIT 1"
				);
				wp_cache_set( 'pizzatier_preview_page_id', $found_id, 'pizzatier', HOUR_IN_SECONDS );
			}
			if ( $found_id ) {
				$preview_page_url  = (string) get_permalink( (int) $found_id );
				$preview_page_auto = true;
			} else {
				$preview_page_url = home_url( '/' );
			}
		}
		$preview_page_url = trailingslashit( esc_url_raw( $preview_page_url ) );

		// Build per-template preview URLs (signed with a nonce)
		$preview_urls = [];
		foreach ( $templates as $slug => $tpl ) {
			$nonce = wp_create_nonce( 'pizzatier_preview_' . $slug );
			$preview_urls[ $slug ] = add_query_arg( [
				'pzl_preview' => $slug,
				'pzl_nonce'   => $nonce,
			], $preview_page_url );
		}

		// Active template preview URL (no override needed — just the raw page)
		$active_preview_url = $preview_urls[ $active ] ?? $preview_page_url;
		$active_name        = $templates[ $active ]['info']['name'] ?? ucwords( str_replace( '-', ' ', $active ) );

		// ── Per-browser preview override ────────────────────────────
		// $active above is the SAVED site-wide default. A theme or plugin may
		// additionally apply a per-visitor preview (e.g. the demo theme's
		// template switcher, which swaps templates per browser without changing
		// the saved default). Such tools supply the active slug through this
		// filter, so the base plugin stays fully decoupled from any specific
		// preview mechanism (no cookie names hardcoded here).
		$available_slugs   = array_keys( $templates );
		$user_preview      = sanitize_key( (string) apply_filters( 'pizzatier_active_user_template', '', $available_slugs ) );
		if ( $user_preview !== '' && ! in_array( $user_preview, $available_slugs, true ) ) {
			$user_preview = '';
		}
		// Only treat it as a distinct preview when it actually differs from the default.
		$has_user_preview  = ( $user_preview !== '' && $user_preview !== $active );
		$user_preview_name = $has_user_preview
			? ( $templates[ $user_preview ]['info']['name'] ?? ucwords( str_replace( '-', ' ', $user_preview ) ) )
			: '';

		?>
		<div class="wrap ptc-wrap">
		<?php $this->render_styles(); ?>

		<!-- ══ Header ════════════════════════════════════════════════ -->
		<div class="ptc-header">
			<span class="dashicons dashicons-admin-appearance ptc-header__icon"></span>
			<div>
				<h1 class="ptc-header__title"><?php esc_html_e( 'Choose a Template', 'pizzatier' ); ?></h1>
				<p class="ptc-header__sub"><?php esc_html_e( 'Select the visual style for your pizza builder. Preview any template live, then activate it — your content and settings stay intact.', 'pizzatier' ); ?></p>
			</div>
			<div class="ptc-header__actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-settings' ) ); ?>" class="button">
					<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Settings', 'pizzatier' ); ?>
				</a>
				<button type="button" class="button ptc-edit-preview-url" id="ptc-edit-preview-url">
					<span class="dashicons dashicons-admin-links"></span> <?php esc_html_e( 'Preview URL', 'pizzatier' ); ?>
				</button>
			</div>
		</div>

		<!-- ══ Preview URL editor (inline collapsible) ══════════════ -->
		<div class="ptc-preview-url-bar" id="ptc-preview-url-bar" style="display:none;">
			<form method="post" action="" class="ptc-preview-url-form">
				<?php wp_nonce_field( 'pizzatier_save_preview_url' ); ?>
				<input type="hidden" name="pizzatier_save_preview_url" value="1">
				<label class="ptc-preview-url-label">
					<span class="dashicons dashicons-admin-links"></span>
					<?php echo wp_kses_post( __( 'Preview page URL — enter any page on your site that contains <code>[pizza_builder]</code>:', 'pizzatier' ) ); ?>
				</label>
				<div class="ptc-preview-url-row">
					<input type="url" name="pizzatier_template_preview_url"
					       class="ptc-preview-url-input"
					       value="<?php echo esc_attr( (string) get_option( 'pizzatier_template_preview_url', '' ) ); ?>"
					       placeholder="<?php echo esc_attr( $preview_page_url ); ?>">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'pizzatier' ); ?></button>
					<button type="button" class="button ptc-cancel-preview-url" id="ptc-cancel-preview-url"><?php esc_html_e( 'Cancel', 'pizzatier' ); ?></button>
				</div>
			</form>
		</div>

		<?php
		// Handle preview URL save
		if ( isset( $_POST['pizzatier_save_preview_url'], $_POST['_wpnonce'] )
		     && wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'pizzatier_save_preview_url' ) ) {
			$url = esc_url_raw( wp_unslash( $_POST['pizzatier_template_preview_url'] ?? '' ) );
			update_option( 'pizzatier_template_preview_url', $url );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Preview URL saved.', 'pizzatier' ) . '</p></div>';
		}
		?>

		<?php if ( $preview_page_auto ) : ?>
		<div class="ptc-notice ptc-notice--info">
			<span class="dashicons dashicons-info-outline"></span>
			Auto-detected preview page: <strong><?php echo esc_html( $preview_page_url ); ?></strong>
			<?php esc_html_e( '— click', 'pizzatier' ); ?> <strong><?php esc_html_e( 'Preview URL', 'pizzatier' ); ?></strong> <?php esc_html_e( 'above to change it.', 'pizzatier' ); ?>
		</div>
		<?php elseif ( $preview_page_url === trailingslashit( home_url( '/' ) ) ) : ?>
		<div class="ptc-notice ptc-notice--warn">
			<span class="dashicons dashicons-warning"></span>
			No page with <code>[pizza_builder]</code> found — previewing the homepage.
			<?php esc_html_e( 'Click', 'pizzatier' ); ?> <strong><?php esc_html_e( 'Preview URL', 'pizzatier' ); ?></strong> <?php esc_html_e( 'above to set the correct page.', 'pizzatier' ); ?>
		</div>
		<?php endif; ?>

		<?php if ( empty( $templates ) ) : ?>
		<div class="ptc-card ptc-empty">
			<span class="dashicons dashicons-warning"></span>
			<p><?php echo wp_kses_post( __( 'No templates found. Make sure at least the <code>nightpie</code> folder exists in the plugin&#8217;s <code>/templates/</code> directory.', 'pizzatier' ) ); ?></p>
		</div>
		<?php else : ?>

		<!-- ══ Hero section ══════════════════════════════════════════ -->
		<div class="ptc-hero">
			<div class="ptc-hero__left">
				<div class="ptc-hero__badge">
					<span class="dashicons dashicons-admin-appearance"></span>
					<?php echo esc_html( count( $templates ) ); ?> template<?php echo count( $templates ) !== 1 ? 's' : ''; ?> available
				</div>
				<h2 class="ptc-hero__heading"><?php esc_html_e( 'Pick your style, then make it yours.', 'pizzatier' ); ?></h2>
				<p class="ptc-hero__body"><?php echo wp_kses_post( __( 'Each template is a complete, self-contained builder experience — different layout, different feel, same content. Hover a card to preview it live in the pane on the right. When you find the one, hit <strong>Activate</strong>.', 'pizzatier' ) ); ?></p>
				<p class="ptc-hero__body"><?php echo wp_kses_post( __( 'Once activated, the <strong>Template Settings</strong> panel below lets you fine-tune colors, fonts, and layout options specific to that template.', 'pizzatier' ) ); ?></p>
			</div>
			<div class="ptc-hero__right">
				<div class="ptc-hero__pill">
					<span class="dashicons dashicons-yes-alt ptc-hero__pill-icon ptc-hero__pill-icon--green"></span>
					<div>
						<span class="ptc-hero__pill-label"><?php echo $has_user_preview ? esc_html__( 'Saved Default', 'pizzatier' ) : esc_html__( 'Currently Active', 'pizzatier' ); ?></span>
						<span class="ptc-hero__pill-val"><?php echo esc_html( $active_name ); ?></span>
					</div>
				</div>
				<?php if ( $has_user_preview ) : ?>
				<div class="ptc-hero__pill ptc-hero__pill--preview">
					<span class="dashicons dashicons-visibility ptc-hero__pill-icon ptc-hero__pill-icon--blue"></span>
					<div>
						<span class="ptc-hero__pill-label"><?php esc_html_e( 'Previewing (this browser)', 'pizzatier' ); ?></span>
						<span class="ptc-hero__pill-val"><?php echo esc_html( $user_preview_name ); ?></span>
						<span class="ptc-hero__pill-note"><?php esc_html_e( 'A per-browser preview is active. It does not change the saved default above.', 'pizzatier' ); ?></span>
					</div>
				</div>
				<?php endif; ?>
				<div class="ptc-hero__pill">
					<span class="dashicons dashicons-welcome-learn-more ptc-hero__pill-icon"></span>
					<div>
						<span class="ptc-hero__pill-label"><?php esc_html_e( 'How it works', 'pizzatier' ); ?></span>
						<span class="ptc-hero__pill-val ptc-hero__pill-val--sm"><?php esc_html_e( 'Hover → Preview → Activate → Customise', 'pizzatier' ); ?></span>
					</div>
				</div>
				<div class="ptc-hero__pill">
					<span class="dashicons dashicons-shield ptc-hero__pill-icon"></span>
					<div>
						<span class="ptc-hero__pill-label"><?php esc_html_e( 'Safe to switch', 'pizzatier' ); ?></span>
						<span class="ptc-hero__pill-val ptc-hero__pill-val--sm"><?php esc_html_e( 'Content &amp; settings are never affected', 'pizzatier' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<!-- ══ Main split layout ══════════════════════════════════════ -->
		<div class="ptc-split">

			<!-- Left: template list -->
			<div class="ptc-list" id="ptc-list">
				<?php foreach ( $templates as $slug => $tpl ) :
					$info       = $tpl['info'];
					$is_active  = $slug === $active;                          // saved site default
					$is_preview = $has_user_preview && $slug === $user_preview; // per-browser preview
					$purl       = $preview_urls[ $slug ] ?? $preview_page_url;
				?>
				<div class="ptc-item<?php echo $is_active ? ' ptc-item--active' : ''; ?><?php echo $is_preview ? ' ptc-item--browser-preview' : ''; ?>"
				     id="ptc-item-<?php echo esc_attr( $slug ); ?>"
				     data-slug="<?php echo esc_attr( $slug ); ?>"
				     data-preview-url="<?php echo esc_attr( $purl ); ?>"
				     data-name="<?php echo esc_attr( $info['name'] ?? ucwords( str_replace( '-', ' ', $slug ) ) ); ?>">

					<!-- Thumbnail -->
					<div class="ptc-item__thumb">
						<?php if ( $tpl['preview_url'] ) : ?>
						<img src="<?php echo esc_url( $tpl['preview_url'] ); ?>"
						     alt="<?php echo esc_attr( $slug ); ?>" loading="lazy">
						<?php else : ?>
						<div class="ptc-item__thumb-placeholder">
							<span class="dashicons dashicons-admin-appearance"></span>
						</div>
						<?php endif; ?>
						<?php if ( $is_active ) : ?>
						<span class="ptc-item__active-dot" title="Active"></span>
						<?php elseif ( $is_preview ) : ?>
						<span class="ptc-item__active-dot ptc-item__active-dot--preview" title="Previewing in this browser"></span>
						<?php endif; ?>
					</div>

					<!-- Info -->
					<div class="ptc-item__info">
						<div class="ptc-item__name">
							<?php echo esc_html( $info['name'] ?? ucwords( str_replace( '-', ' ', $slug ) ) ); ?>
							<?php if ( $is_active ) : ?>
							<span class="ptc-item__active-badge"><?php esc_html_e( 'Active', 'pizzatier' ); ?></span>
							<?php endif; ?>
							<?php if ( $is_preview ) : ?>
							<span class="ptc-item__active-badge ptc-item__active-badge--preview"><?php esc_html_e( 'Previewing', 'pizzatier' ); ?></span>
							<?php endif; ?>
						</div>
						<?php if ( ! empty( $info['description'] ) ) : ?>
						<p class="ptc-item__desc"><?php echo esc_html( $info['description'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $info['tags'] ) && is_array( $info['tags'] ) ) : ?>
						<div class="ptc-item__tags">
							<?php foreach ( array_slice( $info['tags'], 0, 4 ) as $tag ) : ?>
							<span class="ptc-item__tag"><?php echo esc_html( $tag ); ?></span>
							<?php endforeach; ?>
						</div>
						<?php endif; ?>
					</div>

					<!-- Action -->
					<div class="ptc-item__action">
						<?php if ( $purl ) : ?>
						<button type="button" class="button ptc-preview-btn"
						        data-preview-url="<?php echo esc_attr( $purl ); ?>"
						        data-name="<?php echo esc_attr( $info['name'] ?? $slug ); ?>">
							<span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Preview', 'pizzatier' ); ?>
						</button>
						<?php endif; ?>
						<?php if ( $is_active ) : ?>
						<span class="ptc-item__check dashicons dashicons-yes-alt"></span>
						<?php else : ?>
						<button class="button button-primary ptc-activate-btn"
						        data-slug="<?php echo esc_attr( $slug ); ?>"
						        data-name="<?php echo esc_attr( $info['name'] ?? $slug ); ?>">
						<?php esc_html_e( 'Activate', 'pizzatier' ); ?>
						</button>
						<?php endif; ?>
					</div>

				</div>
				<?php endforeach; ?>

				<!-- Dev card at bottom of list -->
				<div class="ptc-list__devcard">
					<span class="dashicons dashicons-admin-plugins"></span>
					<div>
						<strong>Custom templates</strong> — add a folder at
						<code><?php echo esc_html( get_stylesheet_directory() ); ?>/pzttemplates/your-slug/</code>
					</div>
				</div>
			</div>

			<!-- Right: live preview iframe -->
			<div class="ptc-preview-pane" id="ptc-preview-pane">
				<div class="ptc-preview-bar">
					<div class="ptc-preview-bar__dots">
						<span></span><span></span><span></span>
					</div>
					<div class="ptc-preview-bar__url" id="ptc-preview-label">
						<?php echo esc_html( $info['name'] ?? ucwords( str_replace( '-', ' ', $active ) ) ); ?> — <?php esc_html_e( 'Live Preview', 'pizzatier' ); ?>
					</div>
					<div class="ptc-preview-bar__actions">
						<button type="button" class="ptc-preview-bar__btn" id="ptc-preview-reload" title="Reload preview">
							<span class="dashicons dashicons-image-rotate"></span>
						</button>
						<a href="<?php echo esc_url( $preview_page_url ); ?>" target="_blank"
						   class="ptc-preview-bar__btn" title="Open in new tab">
							<span class="dashicons dashicons-external"></span>
						</a>
					</div>
				</div>
				<div class="ptc-iframe-wrap" id="ptc-iframe-wrap">
					<div class="ptc-iframe-loading" id="ptc-iframe-loading">
						<div class="ptc-iframe-loading__spinner"></div>
						<p>Loading preview…</p>
					</div>
					<iframe
						id="ptc-preview-frame"
						class="ptc-preview-frame"
						src="<?php echo esc_attr( $active_preview_url ); ?>"
						title="Live template preview"
						sandbox="allow-scripts allow-same-origin allow-forms allow-popups"
					></iframe>
				</div>
			</div>

		</div><!-- /.ptc-split -->

		<?php endif; ?>

		<!-- ══ Confirmation modal ════════════════════════════════════ -->
		<div id="ptc-modal" class="ptc-modal" role="dialog" aria-modal="true"
		     aria-labelledby="ptc-modal-title" style="display:none;">
			<div class="ptc-modal__box">
				<div class="ptc-modal__header">
					<h2 id="ptc-modal-title" class="ptc-modal__title">
						<span class="dashicons dashicons-admin-appearance"></span> <?php esc_html_e( 'Activate Template?', 'pizzatier' ); ?>
					</h2>
				</div>
				<div class="ptc-modal__body">
					<p><?php esc_html_e( 'Activating', 'pizzatier' ); ?> <strong id="ptc-modal-name"></strong> <?php esc_html_e( 'will apply it to your live site immediately.', 'pizzatier' ); ?></p>
					<p class="ptc-modal__note"><?php esc_html_e( 'Your existing content and settings are unaffected — only the front-end visual design will change.', 'pizzatier' ); ?></p>
				</div>
				<div class="ptc-modal__footer">
					<button id="ptc-modal-cancel" class="button button-secondary"><?php esc_html_e( 'Cancel', 'pizzatier' ); ?></button>
					<form method="post" action="" id="ptc-activate-form" style="display:inline;">
						<?php wp_nonce_field( 'pizzatier_activate_template' ); ?>
						<input type="hidden" name="pizzatier_activate_template" id="ptc-modal-slug" value="">
						<button type="submit" class="button button-primary">
							<span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Yes, Activate', 'pizzatier' ); ?>
						</button>
					</form>
				</div>
			</div>
			<div class="ptc-modal__overlay" id="ptc-modal-overlay"></div>
		</div>

		<!-- ══ Template Settings panel ═════════════════════════════ -->
		<?php if ( $active && ! empty( $template_settings ) ) : ?>
		<div class="ptc-settings-card" id="template-settings">
			<div class="ptc-settings-card__head">
				<div>
					<h2>
						<span class="dashicons dashicons-admin-appearance"></span>
						<?php echo esc_html( ucwords( str_replace( '-', ' ', $active ) ) ); ?> Template Settings
						<span class="ptc-settings-card__badge"><?php esc_html_e( 'Active Template', 'pizzatier' ); ?></span>
					</h2>
					<p>These settings apply only to the <strong><?php echo esc_html( ucwords( str_replace( '-', ' ', $active ) ) ); ?></strong> template. Switching templates shows that template&rsquo;s settings instead.</p>
				</div>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=pizzatier-template' ) ); ?>#template-settings" class="ptc-settings-form">
				<?php wp_nonce_field( 'pizzatier_template_settings_save' ); ?>
				<input type="hidden" name="pizzatier_template_settings_save" value="1">

				<?php
				// ── Color-scheme/preset chips ──────────────────────────────
				$has_metro_schemes    = ( $active === 'metro' );
				$has_plainlist_presets = ( $active === 'plainlist' );
				$schemes = [];
				if ( $has_metro_schemes ) {
					$schemes = $this->get_metro_color_schemes();
				} elseif ( $has_plainlist_presets ) {
					$schemes = $this->get_plainlist_presets();
				}
				if ( ! empty( $schemes ) ) :
				?>
				<div class="ptc-scheme-row">
					<span class="ptc-scheme-label">Quick Presets:</span>
					<div class="ptc-scheme-chips" id="ptc-scheme-chips">
						<?php foreach ( $schemes as $scheme ) :
							$colors_for_chips = isset( $scheme['colors'] ) ? $scheme['colors'] : array_values( $scheme['keys'] ?? [] );
							$data_key = isset( $scheme['keys'] ) ? 'keys' : 'colors';
							$safe = wp_json_encode( $has_metro_schemes ? $scheme['colors'] : $scheme['keys'] );
						?>
						<button type="button" class="ptc-scheme-chip"
						        data-scheme="<?php echo esc_attr( $safe ); ?>"
						        title="<?php echo esc_attr( $scheme['name'] ); ?>">
							<span class="ptc-scheme-chip__swatches">
								<?php foreach ( array_slice( $colors_for_chips, 0, 3 ) as $c ) : ?>
								<span class="ptc-scheme-chip__dot" style="background:<?php echo esc_attr( (string) $c ); ?>;"></span>
								<?php endforeach; ?>
							</span>
							<span class="ptc-scheme-chip__name"><?php echo esc_html( $scheme['name'] ); ?></span>
						</button>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<!-- Settings grid -->
				<div class="ptc-settings-grid">
				<?php foreach ( $template_settings as $field ) :
					if ( empty( $field['key'] ) || empty( $field['type'] ) ) { continue; }
					$fkey   = (string) ( $field['key'] ?? '' );
					$fval   = (string) get_option( $field['key'], $field['default'] ?? '' );
					$flabel = $field['label'] ?? $field['key'];
					$fdesc  = $field['desc']  ?? '';
				?>
				<div class="ptc-field<?php echo ( $field['type'] === 'textarea' || $field['type'] === 'text_wide' || $field['type'] === 'image' ) ? ' ptc-field--full' : ''; ?><?php echo ( $field['type'] === 'radio' ) ? ' ptc-field--full' : ''; ?>">
					<label class="ptc-field__label"><?php echo esc_html( $flabel ); ?></label>
					<?php if ( $fdesc ) : ?>
					<p class="ptc-field__desc"><?php echo esc_html( $fdesc ); ?></p>
					<?php endif; ?>
					<?php if ( $field['type'] === 'text' || $field['type'] === 'text_wide' ) : ?>
						<input type="text" name="<?php echo esc_attr( $fkey ); ?>" value="<?php echo esc_attr( $fval ); ?>" class="ptc-field__input<?php echo $field['type'] === 'text_wide' ? ' ptc-field__input--wide' : ''; ?>" placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>">
					<?php elseif ( $field['type'] === 'number' ) : ?>
						<input type="number" name="<?php echo esc_attr( $fkey ); ?>" value="<?php echo esc_attr( $fval ); ?>" class="ptc-field__input" min="<?php echo esc_attr( (string)( $field['min'] ?? '' ) ); ?>" max="<?php echo esc_attr( (string)( $field['max'] ?? '' ) ); ?>" step="<?php echo esc_attr( (string)( $field['step'] ?? '1' ) ); ?>">
					<?php elseif ( $field['type'] === 'color' ) : ?>
						<div class="ptc-color-wrap">
							<input type="color" name="<?php echo esc_attr( $fkey ); ?>" id="ptc-color-<?php echo esc_attr( $fkey ); ?>"
							       value="<?php echo esc_attr( $fval ?: ( $field['default'] ?? '#000000' ) ); ?>" class="ptc-color">
							<?php if ( ! empty( $field['default'] ) ) : ?>
							<button type="button" class="ptc-color-revert"
							        data-default="<?php echo esc_attr( $field['default'] ); ?>"
							        data-target="ptc-color-<?php echo esc_attr( $fkey ); ?>"
							        title="Revert to default (<?php echo esc_attr( $field['default'] ); ?>)">
								<span class="dashicons dashicons-image-rotate"></span>
							</button>
							<span class="ptc-color-swatch" style="background:<?php echo esc_attr( $field['default'] ); ?>;" title="Default: <?php echo esc_attr( $field['default'] ); ?>"></span>
							<?php endif; ?>
						</div>
					<?php elseif ( $field['type'] === 'image' ) : ?>
						<div class="ptc-image-wrap">
							<div class="ptc-image__row">
								<input type="text" name="<?php echo esc_attr( $fkey ); ?>" id="ptc-image-<?php echo esc_attr( $fkey ); ?>"
								       value="<?php echo esc_attr( $fval ); ?>"
								       class="ptc-field__input ptc-image__url"
								       placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>">
								<button type="button" class="button ptc-image-choose"
								        data-target="ptc-image-<?php echo esc_attr( $fkey ); ?>"
								        data-preview="ptc-image-preview-<?php echo esc_attr( $fkey ); ?>">
									<span class="dashicons dashicons-format-image"></span> <?php esc_html_e( 'Choose Image', 'pizzatier' ); ?>
								</button>
								<button type="button" class="button ptc-image-remove"
								        data-target="ptc-image-<?php echo esc_attr( $fkey ); ?>"
								        data-preview="ptc-image-preview-<?php echo esc_attr( $fkey ); ?>"<?php echo $fval ? '' : ' style="display:none;"'; ?>>
									<?php esc_html_e( 'Remove', 'pizzatier' ); ?>
								</button>
							</div>
							<div class="ptc-image__preview" id="ptc-image-preview-<?php echo esc_attr( $fkey ); ?>"<?php echo $fval ? '' : ' style="display:none;"'; ?>>
								<img src="<?php echo esc_url( $fval ); ?>" alt="" />
							</div>
						</div>
					<?php elseif ( $field['type'] === 'select' ) : ?>
						<select name="<?php echo esc_attr( $fkey ); ?>" class="ptc-field__select">
							<?php foreach ( $field['options'] ?? [] as $ov => $ol ) : ?>
							<option value="<?php echo esc_attr( $ov ); ?>"<?php selected( $fval, $ov ); ?>><?php echo esc_html( $ol ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php elseif ( $field['type'] === 'toggle' ) : ?>
						<label class="ptc-toggle">
							<input type="hidden" name="<?php echo esc_attr( $fkey ); ?>" value="no">
							<input type="checkbox" name="<?php echo esc_attr( $fkey ); ?>" value="yes"<?php checked( $fval, 'yes' ); ?>>
							<span class="ptc-toggle__track"><span class="ptc-toggle__thumb"></span></span>
							<span class="ptc-toggle__label"><?php echo esc_html( $field['toggle_label'] ?? 'Enabled' ); ?></span>
						</label>
					<?php elseif ( $field['type'] === 'textarea' ) : ?>
						<textarea name="<?php echo esc_attr( $fkey ); ?>" class="ptc-field__textarea" rows="<?php echo esc_attr( (string)( $field['rows'] ?? 3 ) ); ?>"><?php echo esc_textarea( $fval ); ?></textarea>
					<?php elseif ( $field['type'] === 'radio' ) : ?>
						<div class="ptc-radio-group">
							<?php foreach ( $field['options'] ?? [] as $ov => $ol ) : ?>
							<label class="ptc-radio-label">
								<input type="radio" name="<?php echo esc_attr( $fkey ); ?>" value="<?php echo esc_attr( $ov ); ?>"<?php checked( $fval, $ov ); ?>>
								<?php echo esc_html( $ol ); ?>
							</label>
							<?php endforeach; ?>
						</div>
					<?php elseif ( $field['type'] === 'range' ) : ?>
						<div class="ptc-range-wrap">
							<input type="range" name="<?php echo esc_attr( $fkey ); ?>" id="ptc-range-<?php echo esc_attr( $fkey ); ?>"
							       value="<?php echo esc_attr( $fval ?: ( $field['default'] ?? '0' ) ); ?>"
							       min="<?php echo esc_attr( (string)( $field['min'] ?? 0 ) ); ?>"
							       max="<?php echo esc_attr( (string)( $field['max'] ?? 100 ) ); ?>"
							       step="<?php echo esc_attr( (string)( $field['step'] ?? 1 ) ); ?>"
							       class="ptc-range"
							       oninput="document.getElementById('ptc-range-val-<?php echo esc_attr( $fkey ); ?>').textContent=this.value+'<?php echo esc_js( $field['unit'] ?? '' ); ?>'">
							<span class="ptc-range__val" id="ptc-range-val-<?php echo esc_attr( $fkey ); ?>"><?php echo esc_html( $fval ?: ( $field['default'] ?? '0' ) ); ?><?php echo esc_html( $field['unit'] ?? '' ); ?></span>
						</div>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
				</div><!-- /.ptc-settings-grid -->

				<div class="ptc-settings-save-row">
					<button type="submit" class="button button-primary ptc-settings-save-btn">
						<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Template Settings', 'pizzatier' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php elseif ( $active ) : ?>
		<div class="ptc-settings-card ptc-settings-card--empty">
			<div class="ptc-settings-card__head">
				<h2><span class="dashicons dashicons-admin-appearance"></span> <?php echo esc_html( ucwords( str_replace( '-', ' ', $active ) ) ); ?> Template Settings</h2>
				<p><?php esc_html_e( 'This template has no customizable settings.', 'pizzatier' ); ?></p>
			</div>
		</div>
		<?php endif; ?>

		</div><!-- /.wrap -->
		<?php
	}

	private function save_template_settings(): void {
		check_admin_referer( 'pizzatier_template_settings_save' );

		$active = (string) get_option( 'pizzatier_setting_global_template', '' );
		if ( ! $active ) { return; }
		// Load the option keys for this template
		$options_paths = [
			get_stylesheet_directory() . '/pzttemplates/' . $active . '/pztp-template-options.php',
			PIZZATIER_TEMPLATES_DIR . $active . '/pztp-template-options.php',
		];
		$fields = [];
		foreach ( $options_paths as $path ) {
			if ( file_exists( $path ) ) {
				$fields = include $path;
				if ( ! is_array( $fields ) ) { $fields = []; }
				break;
			}
		}
		foreach ( $fields as $field ) {
			if ( empty( $field['key'] ) || empty( $field['type'] ) ) { continue; }
			$key = sanitize_key( (string) $field['key'] );
			// Guard: only allow writing options that look like template/plugin settings.
			// This prevents a malicious template-options.php from overwriting core options
			// like `siteurl`, `admin_email`, etc.
			if ( $key === '' || strpos( $key, '_setting_' ) === false ) { continue; }
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Raw value; each field type below applies the appropriate wp_unslash() + sanitizer.
			$raw = $_POST[ $key ] ?? null;
			if ( $field['type'] === 'toggle' ) {
				// Hidden input sends 'no', checkbox overwrites with 'yes' if checked
				// We need to find the last value in the POST array for this key
				// PHP $_POST will have the checkbox value if checked, or the hidden 'no'
				$val = isset( $_POST[ $key ] ) ? sanitize_key( (string) $_POST[ $key ] ) : 'no';
				update_option( $key, $val === 'yes' ? 'yes' : 'no' );
			} elseif ( $field['type'] === 'color' ) {
				if ( $raw !== null ) { update_option( $key, sanitize_hex_color( (string) $raw ) ?: '' ); }
			} elseif ( $field['type'] === 'image' ) {
				if ( $raw !== null ) { update_option( $key, esc_url_raw( trim( wp_unslash( (string) $raw ) ) ) ); }
			} elseif ( $field['type'] === 'textarea' ) {
				if ( $raw !== null ) { update_option( $key, wp_kses_post( wp_unslash( (string) $raw ) ) ); }
			} elseif ( $field['type'] === 'number' || $field['type'] === 'range' ) {
				if ( $raw !== null ) {
					$int = (int) $raw;
					if ( isset( $field['min'] ) ) { $int = max( (int) $field['min'], $int ); }
					if ( isset( $field['max'] ) ) { $int = min( (int) $field['max'], $int ); }
					update_option( $key, (string) $int );
				}
			} else {
				// text, text_wide, select, radio
				if ( $raw !== null ) { update_option( $key, sanitize_text_field( wp_unslash( (string) $raw ) ) ); }
			}
		}
	}

	private function get_metro_color_schemes(): array {
		return [
			[ 'name' => 'Tomato',      'colors' => ['#e63946','#f7f7f5','#ffffff'], 'keys' => ['metro_setting_accent_color'=>'#e63946','metro_setting_background_color'=>'#f7f7f5','metro_setting_ui_bg_color'=>'#f7f7f5','metro_setting_card_bg_color'=>'#ffffff'] ],
			[ 'name' => 'Night Blue',  'colors' => ['#2563eb','#0f1729','#1e2d4a'], 'keys' => ['metro_setting_accent_color'=>'#2563eb','metro_setting_background_color'=>'#0f1729','metro_setting_ui_bg_color'=>'#0f1729','metro_setting_card_bg_color'=>'#1e2d4a','metro_setting_card_text_color'=>'#f0f0f4','metro_setting_title_color'=>'#f0f0f4'] ],
			[ 'name' => 'Garden',      'colors' => ['#2d6a4f','#f4f1e8','#fffef9'], 'keys' => ['metro_setting_accent_color'=>'#2d6a4f','metro_setting_background_color'=>'#f4f1e8','metro_setting_ui_bg_color'=>'#f4f1e8','metro_setting_card_bg_color'=>'#fffef9'] ],
			[ 'name' => 'Ember',       'colors' => ['#c2410c','#fdf4ec','#ffffff'], 'keys' => ['metro_setting_accent_color'=>'#c2410c','metro_setting_background_color'=>'#fdf4ec','metro_setting_ui_bg_color'=>'#fdf4ec','metro_setting_card_bg_color'=>'#ffffff'] ],
			[ 'name' => 'Slate Dark',  'colors' => ['#475569','#1e293b','#293548'], 'keys' => ['metro_setting_accent_color'=>'#475569','metro_setting_background_color'=>'#1e293b','metro_setting_ui_bg_color'=>'#1e293b','metro_setting_card_bg_color'=>'#293548','metro_setting_card_text_color'=>'#f0f0f4','metro_setting_title_color'=>'#f0f0f4'] ],
			[ 'name' => 'Rose',        'colors' => ['#be185d','#fff0f6','#ffffff'], 'keys' => ['metro_setting_accent_color'=>'#be185d','metro_setting_background_color'=>'#fff0f6','metro_setting_ui_bg_color'=>'#fff0f6','metro_setting_card_bg_color'=>'#ffffff'] ],
			[ 'name' => 'Golden Hour', 'colors' => ['#b45309','#fffbeb','#ffffff'], 'keys' => ['metro_setting_accent_color'=>'#b45309','metro_setting_background_color'=>'#fffbeb','metro_setting_ui_bg_color'=>'#fffbeb','metro_setting_card_bg_color'=>'#ffffff'] ],
			[ 'name' => 'Violet Night','colors' => ['#7c3aed','#1a0533','#2a1045'], 'keys' => ['metro_setting_accent_color'=>'#7c3aed','metro_setting_background_color'=>'#1a0533','metro_setting_ui_bg_color'=>'#1a0533','metro_setting_card_bg_color'=>'#2a1045','metro_setting_card_text_color'=>'#f0f0f4','metro_setting_title_color'=>'#f0f0f4'] ],
			[ 'name' => 'Sea Breeze',  'colors' => ['#0891b2','#f0f9ff','#ffffff'], 'keys' => ['metro_setting_accent_color'=>'#0891b2','metro_setting_background_color'=>'#f0f9ff','metro_setting_ui_bg_color'=>'#f0f9ff','metro_setting_card_bg_color'=>'#ffffff'] ],
			[ 'name' => 'Monochrome',  'colors' => ['#18181b','#f4f4f5','#ffffff'], 'keys' => ['metro_setting_accent_color'=>'#18181b','metro_setting_background_color'=>'#f4f4f5','metro_setting_ui_bg_color'=>'#f4f4f5','metro_setting_card_bg_color'=>'#ffffff'] ],
		];
	}

	private function get_plainlist_presets(): array {
		return [
			[ 'name' => 'Classic Black', 'colors' => ['#1a1a1a','#ffffff','#111111'], 'keys' => ['plainlist_setting_accent_color'=>'#1a1a1a','plainlist_setting_bg_color'=>'#ffffff','plainlist_setting_section_header_color'=>'#111111'] ],
			[ 'name' => 'Warm Paper',    'colors' => ['#7c3a00','#fdf6ec','#3d2000'], 'keys' => ['plainlist_setting_accent_color'=>'#7c3a00','plainlist_setting_bg_color'=>'#fdf6ec','plainlist_setting_section_header_color'=>'#3d2000'] ],
			[ 'name' => 'Dark Mode',     'colors' => ['#f97316','#18181b','#ffffff'], 'keys' => ['plainlist_setting_accent_color'=>'#f97316','plainlist_setting_bg_color'=>'#18181b','plainlist_setting_section_header_color'=>'#ffffff'] ],
			[ 'name' => 'Forest',        'colors' => ['#2d6a4f','#f4f9f6','#1b3d2d'], 'keys' => ['plainlist_setting_accent_color'=>'#2d6a4f','plainlist_setting_bg_color'=>'#f4f9f6','plainlist_setting_section_header_color'=>'#1b3d2d'] ],
			[ 'name' => 'Navy Clean',    'colors' => ['#1e3a8a','#f8faff','#0f2060'], 'keys' => ['plainlist_setting_accent_color'=>'#1e3a8a','plainlist_setting_bg_color'=>'#f8faff','plainlist_setting_section_header_color'=>'#0f2060'] ],
			[ 'name' => 'Rose',          'colors' => ['#be185d','#fff0f6','#7c103d'], 'keys' => ['plainlist_setting_accent_color'=>'#be185d','plainlist_setting_bg_color'=>'#fff0f6','plainlist_setting_section_header_color'=>'#7c103d'] ],
			[ 'name' => 'Slate',         'colors' => ['#475569','#f1f5f9','#1e293b'], 'keys' => ['plainlist_setting_accent_color'=>'#475569','plainlist_setting_bg_color'=>'#f1f5f9','plainlist_setting_section_header_color'=>'#1e293b'] ],
			[ 'name' => 'Newspaper',     'colors' => ['#222222','#f7f4ee','#000000'], 'keys' => ['plainlist_setting_accent_color'=>'#222222','plainlist_setting_bg_color'=>'#f7f4ee','plainlist_setting_section_header_color'=>'#000000','plainlist_setting_font_family'=>'georgia','plainlist_setting_check_style'=>'bullet'] ],
		];
	}

	private function render_styles(): void { ?>
	<?php /* Styles moved to assets/css/admin/pizzatier-admin.css (enqueued admin-wide). */ ?>
	<?php // Chip/revert JS is in assets/js/admin/template-choice.js (enqueued via AssetManager) ?>
	<?php }
}
