# Changelog

All notable changes to PizzaTier are documented here.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
Versions follow [Semantic Versioning](https://semver.org/).

---

## [1.15.0] - 2026-07-07

### Changed (rebrand)
- **Renamed the plugin from PizzaLayer to PizzaTier.** This is a name change only — no features were added or removed. Updated end to end: display name, text domain (`pizzalayer` → `pizzatier`), plugin slug, PSR-4 namespace (`PizzaLayer\` → `PizzaTier\`), version/URL constants (`PIZZALAYER_*` → `PIZZATIER_*`), action/filter hooks (`pizzalayer_*` → `pizzatier_*`), Gutenberg block names (`pizzalayer/*` → `pizzatier/*`), the REST namespace (`pizzalayer/v1` → `pizzatier/v1`), and the bundled asset, translation, and template filenames. The plugin homepage is now `https://pizzatier.com`.
- The companion premium extension is correspondingly renamed **PizzaLayer Pro → PizzaTier Pro** and must be updated in lockstep so its hook and text-domain references match.
- **Persisted data is preserved.** Internal short prefixes and stored meta keys (e.g. `pzl_layer_image`, `pzl_nutrition`) are intentionally left unchanged so existing content, settings, and images continue to load without a migration.

---

## [1.14.0] - 2026-07-02

### Removed (WordPress.org compliance)
- **Arbitrary Custom JS field** (`pizzatier_setting_adv_custom_js`) — removed end to end (settings field, front-end emitter, import/export). WordPress.org does not permit plugins to save and run arbitrary JavaScript.
- **Arbitrary Custom CSS field** (`pizzatier_setting_adv_custom_css`) — removed alongside the JS field. Use the Customizer's Additional CSS for site-wide styling.
- **Per-template Custom CSS boxes** (`scaffold_setting_custom_css`, `pocketpie_setting_custom_css`) — removed. Each template's structured appearance settings (colors, fonts, spacing, animation, instance CSS variables) are untouched; only the free-text CSS textareas are gone.
- Legacy values for all of the above are cleaned up on uninstall.

### Security
- **Escaped shortcode/block output at the boundary.** Added a shared, filterable `pzl_kses_builder_html()` helper (filter: `pizzatier_builder_kses`) and applied it to `StaticShortcode::render()`, which backs the `[pizza_static]` / `[pizzatier-static]` shortcodes and the `pizza-static` block. Attributes were already escaped at construction; this guarantees the output boundary is escaped even when add-ons inject markup via `pizzatier_layer_html` / `pizzatier_static_layers`.

### Changed (no behavior change)
- **Inline `<style>` → enqueued stylesheet.** ~2,100 lines of admin CSS across thirteen screens were extracted verbatim into a single enqueued stylesheet (`assets/css/admin/pizzatier-admin.css`). The admin-bar and sidebar-menu styles now go out via `wp_add_inline_style()` on dedicated handles, and the Scaffold instance-variable fallback no longer echoes a raw `<style>` tag.
- Hardened a false-positive in the Settings Wizard by making the option-key data flow self-evident (the key is always a hardcoded step key, never request input).

---

## [1.13.3] - 2026-06-26

### Changed
- Updated the plugin **Author URI** header to `https://islandsundesign.com`. The **Plugin URI** is unchanged (`https://pizzatier.com`). No functional change.

---

## [1.13.2] - 2026-06-26

### Plugin Check compliance (no behavior change)
- **Block render callbacks:** replaced the two `wp_is_serving_rest_request()` calls in `BlockRegistrar` with a small private `is_rest_request()` helper that checks the `REST_REQUEST` constant. The block editor previews server-side blocks through the REST block-renderer endpoint, which always defines `REST_REQUEST`, so detection is unchanged — but the plugin no longer references a function that requires WordPress 6.5 while declaring a 6.2 minimum (`wp_function_not_compatible_with_requires_wp`).
- **Admin sidebar highlight:** corrected the `WordPress.Security.NonceVerification.Recommended` suppression in `AdminMenu::render_menu_styles()`. The `phpcs:ignore` now annotates the line that actually reads `$_GET['pl_cpt']`, the read is `wp_unslash()`'d before `sanitize_key()`, and the two `isset()` checks are consolidated. This is a read-only menu highlight derived from the admin query string with no form processing or state change.

---

## [1.13.1] - 2026-06-26

### Security & Hardening (WordPress.org Plugin Check compliance)
- **Nonce verification:** added defense-in-depth `check_admin_referer()` at the entry of every settings/import save handler (Settings, Site Migration, Settings Wizard, Template Choice). Each was already only reachable through a nonce-gated path; the re-check makes that explicit and satisfies static analysis. Read-only admin `$_GET` navigation reads are sanitized and documented.
- **Input sanitization:** added missing `wp_unslash()` to AJAX uploads (layer image maker, layer-image metabox), sanitized the REST rate-limiter IP read, and documented the decode-then-sanitize pattern for JSON/array payloads.
- **Output escaping:** moved escaping to the point of output across admin screens and the PocketPie / Plainlist / Scaffold templates (previously escaped at assignment, which static analysis can't verify). Behavior is unchanged.
- **Direct DB:** the Template-Choice preview-page lookup is now object-cached; the Setup-Guide existence check is documented as using hardcoded (non-user) parameters.
- Fixed several `phpcs:ignore` annotations that used an em-dash separator instead of `--`, which silently disabled the suppression (including real escaping/notice cases).

### Internationalization
- Standardized the text domain to `pizzatier` across the entire plugin: the 8 `checkout-bar.php` templates previously used the `pizzatierpro` domain, which both broke their translation loading in the free plugin and triggered text-domain-mismatch errors. They now use `pizzatier` (the free plugin is always active when Pro is, so they still translate correctly at runtime).
- Added `/* translators: */` comments to all placeholder-bearing strings.
- Removed the redundant manual `load_plugin_textdomain()` call (translations auto-load since WP 4.6; minimum is 6.2).

### Compatibility & Metadata
- Guarded `wp_is_serving_rest_request()` for WordPress < 6.5.
- "Tested up to" set to 7.0; readme changelog trimmed to the Plugin Directory's length limit.
- Prefixed global variables in `uninstall.php`.

---

## [1.13.0] - 2026-06-26

### Fixed
- **The dormant `--pzl-*` "global skin" override block was silently wiping template card and root styling.** Both `metro/template.css` and `nightpie/template.css` ended with a block that re-declared card/root `background`, `border-color`, `border-radius` and `padding` as `var(--pzl-*, inherit)`. Those `--pzl-*` tokens are never populated anywhere in the plugin, so every property collapsed to its `inherit` fallback — overriding the templates' own values. This single block was the root cause of three separate "setting does nothing" reports:
  - **Metro → Page Background Color now applies.** `.mt-root` was being forced to `background: var(--pzl-bg, inherit)`, so the `--mt-bg` value chosen in settings never showed. The fallbacks now chain to the template's own tokens (`var(--pzl-bg, var(--mt-bg))`, etc.), so the block is a true no-op pass-through until/unless a global skin is injected.
  - **Nightpie → Item Card Border setting now works.** The card `border-color` was forced to `inherit`, defeating the `--np-card-border` variable the setting drives. Fixed; the border defaults to transparent (toggle off) and honours the chosen colour when enabled.
  - **Nightpie → item cards regained their interior padding.** Card `padding` was forced to `inherit` (collapsing the intended 18px). Restored via a new `--np-card-pad` token (default 18px).
  - Idle card backgrounds and selected-card accent borders (previously also collapsed to `inherit`) now resolve correctly in both templates.

### Added
- **Metro → UI Container Background (`metro_setting_ui_bg_color`).** New setting that colours the builder panel itself — the pizza hero and every ingredient section — as one cohesive surface (`--mt-ui-bg`), distinct from the Page Background (which now visibly frames the panel) and the card background. Misc chrome (search field, summary tray, section navs, modals) follows the container colour.
- **Metro → Card Text Color (`metro_setting_card_text_color`) and Section Title Color (`metro_setting_title_color`).** Wired to new `--mt-card-text` (ingredient names) and `--mt-title` (section headings + hero tagline) variables.

### Changed
- **Metro → Page Background now frames the builder.** `.mt-root` gained modest side/top padding so the Page Background Color is visible around the UI container panel (bottom padding still reserves summary-tray clearance).
- **Metro → card background covers the full card.** The card image area now uses the card background token instead of the secondary surface, so each card reads as one uniform colour.
- **Metro → preset colour schemes** now also set the UI Container Background (and, for the dark schemes, readable card-text/title colours).
- Bumped Metro template to 1.2.0 and Nightpie template to 1.1.1.

---

## [1.12.0] - 2026-06-26

### Added
- **Topping coverage now carries the specific portion, not just the fraction.** Previously a half/quarter topping recorded only its coverage *size* (Whole / Half / Quarter) as it flowed downstream, so "pepperoni on the left half" and "pepperoni on the right half" were indistinguishable once selected. A shared `window.PizzaTierCoverage` helper (in `pizzatier-main.js`, always enqueued before any template) now normalises any coverage value to `{ portion, fraction, label }` — e.g. `half-left` → `{ portion:'half-left', fraction:'half', label:'Left Half' }`. Templates that expose a normalised layers array (Plainlist, Scaffold, Command Center) now pass a clean generic `fraction` (the price-grid key) **plus** an explicit `portion` slug and human-readable `coverageLabel`, instead of overloading the `fraction` field with the specific slug. The object-state templates (Metro, Nightpie, Colorbox, Rustic, Pocketpie) already exposed the specific portion via `state.toppings[slug].coverage`, which PizzaTierPro now reads and preserves end-to-end to the cart, order, kitchen email, and admin order screen.

### Changed
- **Plainlist / Scaffold / Command Center** `getState()` payloads: `fraction` is now always the generic size (`whole` / `half` / `quarter`); the specific portion travels in `portion` + `coverageLabel`. Scaffold's own summary now shows the readable portion label ("Left Half") instead of the raw slug.

---

## [1.11.0] - 2026-06-26

### Added
- **Plainlist → per-topping coverage modal.** Each selected topping now shows a coverage chip; tapping it opens a shared modal (mirroring Colorbox's pattern) to choose Whole / Left Half / Right Half / the four quarters. The choice is written to `state.toppings[slug].coverage`, reflected on the row chip and in the running summary, applied to the visual layer via the base `tcg-*` classes, and flows through `getState().layers[].coverage` so PizzaTierPro prices each fraction correctly. Modal closes on backdrop click or Escape; the chip's click/keydown are stopped from toggling the row.
- **Metro → border settings.** New **Border Color** (`metro_setting_border_color`) and **Card & Panel Borders** toggle (`metro_setting_show_borders`), wired to the `--mt-border` / `--mt-border-hover` custom properties used throughout the template (hover tone derived automatically; toggling off makes them transparent for a flat look). Metro's existing container-background and card-background settings were confirmed already wired and working. New keys added to `uninstall.php`.
- **Nightpie → item-card border setting.** New **Item Card Border** toggle (`nightpie_setting_card_border`, default off → transparent) plus **Item Card Border Color** (`nightpie_setting_card_border_color`), driving a new `--np-card-border` variable on `.np-card`. Selected/hover cards keep their accent outline regardless. New keys added to `uninstall.php`.

### Changed
- **Nightpie → roomier ingredient cards.** Increased `.np-card` interior padding from 14px to 18px.
- **Colorbox → Add to Cart bar relocated for visibility.** PizzaTierPro's checkout/Add-to-Cart bar (via the `pizzatier_builder_action_bar` hook) was rendered inside the sticky pizza column (`max-height:100vh; overflow-y:auto`), where it could be clipped off screen. It now renders in a full-width `.cb-action-bar` block directly below the builder layout. The `.pztpro-checkout-bar--colorbox` styling was kept in `template.css` and adjusted to sit full-width.

### Fixed
- **PocketPie → toppings: coverage buttons were unclickable and the remove "✕" overlapped the thumbnail.** The remove control was a full-chip overlay (`position:absolute; inset:0`), so it both painted over the thumbnail and blocked the coverage buttons once a topping was selected. It is now a small top-left corner badge, and the coverage picker gets its own stacking context (`z-index:5`). CSS only.
- **Scaffold → Add to Cart reported "The pizza builder is not ready yet."** Scaffold's `PizzaTierAPI`/`getState()` shape did not match the contract Pro's frontend builder reads (Pro tries `getState('pztpro-{idx}')`, `getInstances()`, then bare `getState()`). `getState()` now returns the standard shape with `layers` as an **array** (plus `instanceId`, `toppings`, `size`, and a `baseLayers` map for the summary), the template exposes the full API surface (`registerInstance`/`getInstance`/`getInstances`/`getAllInstances`/`getState`/`setState`), registers each instance under its `pztpro-{idx}` root id, and fires `pizzatier_instance_ready`.

### Notes
- New option keys (`metro_setting_border_color`, `metro_setting_show_borders`, `nightpie_setting_card_border`, `nightpie_setting_card_border_color`) were added to `uninstall.php` cleanup.
- The front-end `&#36;` currency-symbol bug on Add-to-Cart rows is fixed separately in **PizzaTierPro 1.6.4** (the symbol originates from WooCommerce and is rendered by Pro's JS, not this plugin).

---

## [1.10.0] - 2026-06-25

### Added
- **Scaffold template → 1.1.0: Add to Cart CTA & checkout bar styling.** The PizzaTierPro checkout bar (`checkout-bar.php`) is now styled entirely from the Scaffold stylesheet rather than inheriting Pro's generic styles. Everything is scoped under `.pztpro-checkout-bar--scaffold` and driven by the builder's `--sc-*` instance tokens, so the price, size label, quantity stepper, order-note field, and CTA all follow the template's colours and geometry. The Add to Cart button now has a complete inline-flex layout with icon sizing and `:hover` / `:active` / `:focus-visible` / `:disabled` states. Markup and classes are unchanged, so Pro's cart bindings keep working.
- **Scaffold: two Add-to-Cart settings.** `scaffold_setting_cta_text` (CTA label, wired into `checkout-bar.php` with a Scaffold → default "Add to Cart" fallback chain) and `scaffold_setting_cta_show_icon` (toggle the cart icon for a text-only button).

### Fixed
- **Scaffold: Font Family setting silently broke for non-inherit fonts.** The per-instance CSS custom properties were built with `esc_attr()`, which HTML-encodes the quotes and commas in the System / Serif / Monospace / Custom font stacks (e.g. `"Segoe UI"` → `&quot;Segoe UI&quot;`). Those entities are invalid inside a `<style>` block, so the selected font never applied. Values are now passed through a CSS-context sanitiser that strips only declaration/rule-terminating characters while preserving quotes, commas, and parentheses. The block-preview fallback `<style>` branch no longer re-escapes the already-sanitised CSS (and a stray inline assignment was removed).
- **Scaffold: `template.css` was enqueued twice.** `pztp-template-css.php` enqueued the stylesheet under its own `pztp-scaffold` handle even though AssetManager already enqueues it under the canonical `pizzatier-template-scaffold` handle. It now defers to the canonical handle and only enqueues a fallback copy when that handle is absent.

### Notes
- **Scaffold Template Settings audit.** All Scaffold settings were traced from the options page through to the front end and confirmed to take effect; none were found inert or obsolete, so nothing was removed. The two new option keys were added to `uninstall.php` cleanup.

---

## [1.9.0] - 2026-06-25

### Added
- **Plainlist template → 1.1.0: Add to Cart CTA & checkout bar styling.** The PizzaTierPro checkout bar (`checkout-bar.php`) is now styled entirely from the Plainlist stylesheet rather than inheriting Pro's generic styles. Everything is scoped under `.pl-root .pztpro-checkout-bar--plainlist` and driven by the Plainlist CSS variables and a new set of Add-to-Cart settings, so the price, quantity stepper, order-note field, and CTA all follow the template's colours. Markup and classes are unchanged, so Pro's cart bindings keep working.
- **Plainlist: seven Add-to-Cart button settings.** `plainlist_setting_cart_btn_text` (CTA label, wired into `checkout-bar.php` with a Plainlist → Pro `cart_btn_text` → default fallback chain), `plainlist_setting_cart_btn_style` (solid / outline / text-link), `plainlist_setting_cart_btn_size` (small / medium / large), `plainlist_setting_cart_btn_bg`, `plainlist_setting_cart_btn_text_color`, `plainlist_setting_cart_btn_radius`, and `plainlist_setting_cart_btn_full_width`. Style/size/full-width drive whitelisted `.pl-root` modifier classes; colours and radius drive injected CSS variables (`--pl-cart-bg`, `--pl-cart-fg`, `--pl-cart-radius`).
- **Plainlist: four list-style settings.** `plainlist_setting_list_style` (plain / bordered / striped / card / underline → `.pl-root--rows-*`), `plainlist_setting_selected_style` (accent / filled / leftbar / bold → `.pl-root--sel-*`), `plainlist_setting_row_padding` (→ `--pl-row-pad`), and `plainlist_setting_label_weight` (→ `--pl-label-weight`). All option values are whitelisted before being emitted as class fragments.

### Fixed
- **Plainlist: faint list text on some themes.** The item labels used `color: inherit`, which let theme rules for `.entry-content li`, `label`, and `a` win on specificity and wash the list out to a light grey on white. Labels now bind directly to `--pl-item-color`, and a colour-hardening block re-asserts the item, heading, and accent colours scoped under `.pl-root` with `!important`, so the **Item Text Color** and **Section Header Color** settings always take effect regardless of the active theme.

### Notes
- **Plainlist Template Settings audit.** All Plainlist settings were traced from the options page through to the front end and confirmed to take effect; none were found inert or obsolete, so nothing was removed. The eleven new option keys were added to the Settings export/import whitelist (`Settings::OPTIONS`) and to `uninstall.php` cleanup.



### Added
- **Fornaia template → 1.1.0: Add to Cart CTA & checkout bar styling.** The PizzaTierPro checkout bar (`checkout-bar.php`) is now fully styled from the Fornaia stylesheet. The **Order Now** button is a terracotta, uppercase, serif-accented CTA with hover/active/focus-visible/disabled/loading/added states, alongside a styled price, quantity stepper, order-note field, and a hand-torn gold divider. Everything is scoped under `.pztpro-checkout-bar--rustic` and driven by the Fornaia CSS variables, so it follows the **Accent / Terracotta**, **Surface**, **Body Text**, and **Button Style** settings. The CTA radius tracks **Button Style** and its uppercasing tracks **Uppercase Button Labels**. Markup and classes are unchanged, so Pro's cart bindings keep working.
- **Fornaia: dedicated `--rp-stepnav-active` token.** The **Active Step Text Color** setting now drives its own CSS variable used by the active step's text, underline, and number.

### Changed
- **Fornaia front-end contrast.** Some serif titles rendered as light "aged ink" on the cream surfaces and fell below WCAG AA. The order-summary row titles (Size / Crust / Sauce / …) now use the mid-tone brown (`--rp-text-mid`), and the **Muted Text** token / setting default was darkened from `#9a7a56` to `#7a5c34` (clears AA on the cream backgrounds for hints, labels, and the step nav). The faint placeholder token was darkened from `#c5a882` to `#8a6c44` so "none selected" stays legible.

### Fixed
- **Fornaia setting collision: Accent vs. Active Step Text Color.** Both `rustic_setting_accent_color` and `rustic_setting_stepnav_active_color` wrote to `--rp-accent`, so the step-nav value silently overrode the global **Accent / Terracotta** setting. The active-step colour now maps to its own `--rp-stepnav-active` token; the Accent setting once again controls the template-wide accent, and the active-step colour is independent. Defaults are unchanged, so existing sites render identically until either is edited.

### Notes
- **Fornaia Template Settings audit.** All 36 Fornaia settings were traced from the options page through to the front end. Every one resolves to a real CSS variable, toggle selector, or piece of markup; aside from the accent/step-nav collision above, all take effect as labelled. No new option keys were introduced, so `uninstall.php` is unchanged.

---

### Added
- **Metro template → 1.1.0: "Container Background Image" setting.** A new setting lets you place an image behind the entire builder container. It layers over the existing **Page Background Color** and is painted onto `.mt-root` via the settings-driven CSS injector (centered, scaled to `cover`, no-repeat). Leave it empty for the previous solid-colour behaviour.
- **Reusable `image` field type in Template Settings.** The shared template-options renderer (`TemplateChoice`) now supports an `image` field: a URL input plus a **Choose Image** button wired to the WordPress media library, a live preview with a checkerboard backdrop, and a **Remove** button. It degrades to manual URL entry if the media frame is unavailable. Saved with `esc_url_raw`; `wp_enqueue_media()` is now loaded on the Template page. Any template can use `'type' => 'image'` in its options.

### Changed
- **Metro template → 1.1.0: Add to Cart CTA.** The PizzaTierPro checkout bar (`checkout-bar.php`) is now fully styled from the Metro stylesheet — an accent pill **Add to Cart** button with cart icon and hover/active/focus-visible/disabled/loading/added states, plus a styled price, quantity stepper and order-note input. Everything is scoped under `.pztpro-checkout-bar--metro` and driven by the Metro CSS variables, so it follows the **Accent Color** / **Card Background** settings. Markup and classes are unchanged, so Pro's cart bindings keep working.
- **Metro Template Settings audit.** All Metro settings were traced from the options page through to the front end and verified to take effect. The new `metro_setting_container_bg_image` key was added to `uninstall.php` cleanup.

### Fixed
- **Settings page "Template" quick-jump pill.** The pill linked to `#pset-body-template-settings`, but that anchor id was missing from the card, so the jump did nothing. The id was added.

## [1.7.1] - 2026-06-25

### Changed
- **NightPie template → 1.1.0.**
  - *Add to Cart CTA.* The PizzaTierPro checkout bar's Add to Cart button, glowing price, quantity stepper and order-note input are now fully styled from the NightPie template stylesheet — a neon-orange gradient pill CTA with hover/active/disabled states that tracks the **Accent Color** setting. Markup/classes are unchanged so Pro's cart bindings keep working.
  - *Step indicator below the choices.* The progress dots and the Prev/Next section navigation were moved out of the slot above the options into a new `.np-builder-footer` rendered beneath the panels. JS targets these by class/ID, so navigation is unaffected.
  - *One-column on tablets and below.* The desktop two-column split now engages at ≥1024px (was ≥900px). Tablet and smaller widths stack with the pizza preview on top and a compact tab bar (tighter padding, smaller labels).

### Fixed
- **NightPie:** the inline size selector rendered as light-on-white in some setups (PizzaTierPro's generic size-option styles winning). The size options are now scoped under `.np-root`, cover the `pztpro-*` classes, and use dark surfaces with light text plus an accent-highlighted active state.
- **NightPie Template Settings now all take effect.** *Background Color* (blocked by a hard `!important` root gradient) and *Font Family* (remapped to `--pzl-*` by a later rule) were inert, and *Accent Glow off* only suppressed variable-based glows. All NightPie settings are now verified to affect the front end; the NightPie setting keys were also added to `uninstall.php` cleanup.

## [1.7.0]

### Changed
- **PocketPie template → 1.1.0.**
  - *Corner Quad redesign.* The four corner triggers and the actions-row buttons now open the shared full-screen modal (with an X close button and backdrop) instead of small inward-expanding corner panels, so every category gets the same roomy selection surface. The old `.pp-cq-panel` markup/CSS and the now-obsolete **Panel Width** / **Panel Max Height** settings were removed.
  - *Larger pizza.* The centered Corner Quad pizza is significantly larger by default (160px → 300px), with the **Pizza Size** setting range widened to 120–480px and viewport-safe sizing on phones.
  - *Size selector relocated.* The standalone "Choose Pizza Size" row was removed; the PizzaTierPro size chips now open from the **Size** button in the actions row into the shared modal (other layouts surface them in their own drawer/sheet). Radio names/classes are unchanged so Pro price updates keep working.
  - *Review button.* Moved out from under the pizza into the actions row, right-aligned and styled as a prominent button.
  - *Add to Cart CTA.* The PocketPie checkout bar's Add to Cart button, quantity stepper, price and order-note input are now fully styled from the template stylesheet — a prominent amber→coral gradient CTA with hover/active/disabled states.
  - *Settings audit.* Verified every remaining PocketPie setting maps to real output; removed only the two corner-panel settings made obsolete by the modal redesign (their keys are retained in `uninstall.php` for cleanup of existing installs).
  - *Checkout bar (PizzaTierPro).* The Add to Cart bar was previously rendered only inside the optional order-summary sidebar, so it disappeared whenever "Show Order Summary Sidebar" was off and was cramped into the 220px column otherwise — the final custom pizza could not be added to the cart. The `pizzatier_builder_action_bar` hook now renders into a dedicated, always-present full-width checkout dock at the end of the wizard, independent of the sidebar. The dock collapses cleanly when Pro is inactive. `checkout-bar.php` was hardened with `function_exists()` guards on the Pro setting accessor (so it can never fatal), rebuilt with the canonical price/currency/amount and `pztpro-bar-row__btn-text` hooks Pro binds to, and the "Add to Cart" label is now editable from the template settings.
  - *Colorful step tabs.* Each builder step (Size, Crust, Sauce, Cheese, Toppings, Drizzle, Slicing) now has its own color on the wizard-header bubble/label and the panel step badge, so the flow reads as guided rather than one flat red. A new **Colorful Step Tabs** toggle collapses the palette back to a single accent color.
  - *Add to Cart CTA.* New **Add to Cart Button Color** and **Add to Cart Button Text** settings drive a dedicated `--cc-cta` token set used by the checkout-bar button, the live price, and the review-step CTA. All CTA styling lives in the template CSS.
  - *Settings audit.* Verified every Command Center setting takes effect. Surface, border, raised-surface, hover, checkout-bar and faint-text tokens are now derived from the chosen Surface / Text / Accent / CTA colors instead of staying on the hardcoded navy defaults, so a custom palette fully cascades. With the checkout bar moved out of the sidebar, the "Show Order Summary Sidebar" toggle no longer hides the Add to Cart action.
- **Colorbox template → 1.2.0.**
  - *Settings.* Audited every Colorbox setting and made each one take effect. Defined four design tokens that were referenced but never declared (`--cb-transition`, `--cb-shadow-sm`, `--cb-radius-pill`, `--cb-accent-glow`), restoring card/button transitions, hover shadows and pill rounding. The Accent glow now derives from the Accent Color, and the Card Surface Color now cascades to hover/thumb tints and borders. The slicing tab's color stripe (`--cb-c-slice`) is now driven by settings instead of a hardcoded value.
  - *New "Container Background" setting.* Adds a configurable background for the full builder container panel (`.cb-layout`), which was previously a hardcoded cream value with no control. "Background Color" now reads as a thin matte frame around that panel, so both settings are visibly distinct.
  - *Checkout bar.* Added a Colorbox-specific checkout bar (`checkout-bar.php` rebuilt with a `--colorbox` modifier and the size/price/quantity/notes/Add-to-Cart hooks Pro binds to) plus a full set of scoped styles in the template CSS — bright rounded bar, pill quantity stepper, and an accent "Add to Cart" CTA. It renders when PizzaTierPro is active via the `pizzatier_builder_action_bar` hook.
  - *Topping coverage is now modal.* Each topping card shows only the chosen coverage as a chip (defaulting to **Whole**); tapping it opens a per-builder picker dialog. Fixed the quarter swatch icons, which previously mapped to CSS classes that didn't exist.
  - *Selected-card checkmark.* The top-right "selected" badge now renders an actual checkmark glyph via CSS instead of depending on Font Awesome (which the plugin doesn't enqueue), so it no longer shows as an empty colored box. Accent coloration is unchanged.

---

## [1.6.5] - 2026-06-22

### Fixed
- **PizzaTier no longer requires SCF/ACF to render.** Front-end menu templates and the `[pizza_layer_info]` shortcode called `get_field()` directly, which caused a fatal error on sites without Secure Custom Fields / ACF active. Introduced a global `pzl_get_field()` accessor that delegates to `get_field()` when present and otherwise falls back to `get_post_meta()` (resolving stored attachment IDs to URLs for image fields). All previously unguarded calls now route through it.
- **Theme custom-template directory standardized to `pzttemplates/`.** The template discovery loop (`TemplateChoice`, `ShortcodeGenerator`) scanned `{theme}/pizzatier/`, while the options-file loader and all user-facing docs used `{theme}/pzttemplates/`. A theme override placed per the docs could be discovered but have its `pztp-template-options.php` ignored, or vice-versa. All paths now agree on `pzttemplates/`.
- **Layer Builder Wizard JSON handling.** The raw `meta` payload was passed through `sanitize_text_field()` before `json_decode()`, which can corrupt valid JSON. It is now unslashed and decoded directly; each decoded value is still sanitized individually against the existing key allowlist.
- **Removed stale `admin-tabs.js`.** The script posted to AJAX actions (`pizzatier_load_cpt_tab`, `pizzatier_quick_add_item`, `pizzatier_quick_delete_item`) that are no longer registered. The live admin UI uses the Content Hub panel endpoint. The shared `admin-tabs.css` (still a dependency) is retained.
- **Internationalization.** Removed a double-translation on the Settings "None / Plugin default" option and unwrapped dynamic-variable `__()`/`_x()` calls on post-type labels, both of which the WordPress.org i18n checks flag.

### Changed
- **Layer post types are no longer publicly queryable.** Ingredient post types now register with `publicly_queryable => false`, `has_archive => false`, `exclude_from_search => true`, and `show_in_nav_menus => false`, so they no longer produce front-end single pages, archives, or search results. `show_in_rest` remains `true` — REST access for apps and the block editor is unchanged.

### Security
- **SSRF hardening on import image sideloading.** Remote image URLs from a migration import are now restricted to `http`/`https` and validated with `wp_http_validate_url()` before `download_url()`, blocking fetches to loopback/private/reserved hosts. The import remains admin + nonce gated as before.
- **Decoded-size cap on base64 layer-image uploads.** Both layer-image AJAX endpoints now reject oversized decoded payloads, capped at the smaller of the upload limit and 8 MB and filterable via `pizzatier_max_layer_image_bytes`.
- **REST layer type allowlisting.** `PizzaBuilder::get_layer_url()` (the `/layer-url` REST endpoint) normalizes `type` against a fixed allowlist rather than constructing a post-type name from arbitrary input.

---

### Added
- **Template page now shows a per-browser preview alongside the saved default.** A new `pizzatier_active_user_template` filter lets a theme or plugin tell the admin Template page which template the current visitor is previewing (for example, the demo theme's front-end template switcher). When a preview is active and differs from the saved default, the page shows a second "Previewing (this browser)" pill and marks that template's card, while still clearly labelling the real saved default. The base plugin stays fully decoupled — it knows nothing about any specific preview mechanism (no cookie names hardcoded); it only exposes the filter and renders whatever slug a theme supplies (validated against installed templates). With no such filter wired, the page behaves exactly as before.

---

## [1.6.3] - 2026-06-21

### Security
- **REST API: per-IP rate limiting on the public endpoints.** `/render` and `/layer-url` are unauthenticated by design (and off by default), but had no throttle, so when enabled they could be used as a cheap way to flood the database. Both now run through a `check_public_access()` permission callback that enforces a per-IP fixed-window limit (default 120 requests / 60 s) using a transient counter. Limits are filterable — `pizzatier_rest_rate_limit` and `pizzatier_rest_rate_window`; set either to 0 to disable. The client IP is taken from `REMOTE_ADDR` only (X-Forwarded-* is spoofable and not trusted for a security control). Over-limit requests get a `429`.

### Performance
- **REST `/render` response caching.** When *Settings → Advanced → REST cache TTL* is greater than 0, `/render` caches its rendered HTML in a transient keyed on the normalised layer set, so repeated identical requests skip the slug→post resolution and image-field reads. TTL of 0 (the default) leaves caching off. The response includes a `cached` flag.

### Changed
- **Inline admin CSS/JS removed from output in favour of the enqueue pipeline:**
  - Content Hub list-table column widths now ship via `wp_add_inline_style()` on the already-enqueued `pizzatier-admin-tabs` handle (in `AssetManager::enqueue_admin()`), instead of an inline `<style>` echoed on `admin_head` during page render (which, registered from the render path, also ran too late to reliably apply).
  - The settings-export and site-export "headers already sent" fallbacks no longer emit an inline `<script>` Blob-download. They now render a no-JavaScript page that shows the export JSON in a read-only `<textarea>` to copy/save. This path only triggers in the rare case where output was sent before the `admin_post` handler ran; the normal flow still streams a proper file download.
- The only remaining inline `<style>`/`<script>` in front-end output is the **Custom CSS / Custom JS** feature itself, which is inherently inline (it emits the administrator's own code) and is gated on the `unfiltered_html` capability as of 1.6.2.

### Files
- `src/Api/PizzaRestApi.php` — `check_public_access()` rate-limit permission callback + `client_ip()`; optional `/render` transient cache.
- `src/Assets/AssetManager.php` — Content Hub column-width CSS via `wp_add_inline_style()`.
- `src/Admin/ContentHub.php` — removed the inline `admin_head` `<style>` echo.
- `src/Admin/Settings.php` — no-JS textarea fallback for the export headers-already-sent path.
- `src/Admin/SiteMigration.php` — no-JS textarea fallback for the export headers-already-sent path.

## [1.6.2] - 2026-06-21

### Security
- **Custom CSS / Custom JS now require the `unfiltered_html` capability, not just `manage_options`.** The two Advanced → Developer fields are stored raw and emitted verbatim inside `<style>` / `<script>` on every builder page. They were gated only on `manage_options`, which on **multisite** allowed a site administrator (who has `manage_options` but deliberately lacks `unfiltered_html`) to inject arbitrary CSS/JS that runs for all visitors — bypassing WordPress's multisite restriction. Writing these fields now additionally requires `current_user_can( 'unfiltered_html' )` on every path:
  - `Settings::save_settings()` — the live Settings form save.
  - `Settings::import_settings()` — the settings-JSON import.
  - `SiteMigration::import_settings()` — the full site-migration import.
  On single-site nothing changes (administrators already have `unfiltered_html`). Users without the capability now see the two fields rendered **read-only** with an inline explanation, and any submitted/imported value for those keys is ignored (the stored value is preserved). No other behaviour changes; the front-end output path is unchanged because the security boundary is enforced at write time.

### Files
- `src/Admin/Settings.php` — `unfiltered_html` gate on the raw CSS/JS write in `save_settings()` and `import_settings()`; read-only fields + notice for users without the capability.
- `src/Admin/SiteMigration.php` — `unfiltered_html` gate on the raw CSS/JS write in `import_settings()`.

## [1.6.1] - 2026-06-21

### Improved
- **Setup Guide: every step is now wired to real auto-detection.** Audited all 11 steps. The nine content/config steps were already auto-detected; the three that were manual-only now check off automatically:
  - **Prepare your layer images** — new `any_layer_image_exists()` bounded query detects a native `{type}_layer_image` meta value or a featured image on any published layer (works with or without SCF/ACF).
  - **Embed the Builder on a page** — new `builder_is_embedded()` query detects the `[pizza_builder]` shortcode in any published post content.
  - **View your builder on the front end** (renamed from "Place a test order end-to-end", which implied WooCommerce and didn't fit the free plugin) — `BuilderShortcode::render()` now sets a one-time `pizzatier_builder_viewed` option the first time the builder renders on a non-admin request, and the step reads it.
- **Cleaner action buttons.** Steps now carry explicit `manual` / `detected` flags. Manual "Mark done / Skip" buttons show only on steps that support a manual fallback (optional layers + the three detectable steps), and "Undo" appears only when a step is complete *because* it was hand-marked — never when an auto-signal is holding it true. Mandatory auto-detected steps (crusts, sauces, cheeses, toppings, template, settings) no longer show a no-op "Mark done" button.

### Files
- `src/Admin/SetupGuide.php` — auto-detection for images/shortcode/test; `manual`/`detected` flags; `any_layer_image_exists()` + `builder_is_embedded()` helpers.
- `src/Shortcodes/BuilderShortcode.php` — set `pizzatier_builder_viewed` on first front-end render.
- `uninstall.php` — clean up the `pizzatier_builder_viewed` flag.

## [1.6.0] - 2026-06-21

### Added
- **Nutrition & Ingredients meta box (`src/Admin/NutritionMetaBox.php`).** A native meta box on the edit/new screens of the edible layer CPTs — toppings, crusts, sauces, cheeses, drizzles. Fields: an **Ingredients** list (one per line), serving size, calories, spice level (toppings/sauces/drizzles), thickness (crusts), and the four dietary flags. Plain post-editor form fields saved on `save_post` (nonce + `edit_post` checked); no JS/AJAX. Everything writes to the plugin's canonical `_pizzatier_{key}` meta — the same keys the Layer Builder Wizard writes and Content Hub reads — so it works with or without ACF/SCF. A public `NutritionMetaBox::get_ingredients( $post_id )` helper returns the list as an array for templates. Cuts and sizes are intentionally excluded (ingredients/nutrition don't apply to slicing overlays or dimension records).
- **Content Hub: optional "Ingredients" column** for the edible types (hidden by default) — shows a count in Grid cards and a truncated list in the List table.
- **Setting: Disable the Content Layer Manager** (`pizzatier_setting_disable_content_hub`). When on, the sidebar CPT links and the dashboard stat boxes point to `edit.php?post_type=pizzatier_…`, and an `admin_init` guard redirects any direct hit on `?page=pizzatier-content` to the matching WP list.
- **Setting: Hide incomplete layers** (`pizzatier_setting_require_complete_data`). When on, `TemplateAPI::get_layer_posts()` drops layers that fail `layer_has_sufficient_data()` — image types without a resolvable image, sizes without a positive `diameter_inches` — so they're excluded from the builder and from any calculation that reads the same list. Overridable per-layer via the new `pizzatier_layer_is_usable` filter.

### Changed
- **Admin menu reorganized (`src/Admin/AdminMenu.php`).** New "Basics" group header sits directly below Dashboard and above "Content"; Template, Settings, Help, and Shortcode Generator moved there from "Tools". Tools now holds Layer Image Maker, Layer Builder Wizard, Setup Guide, Site Migration, and Settings Wizard.

### Files
- `src/Admin/NutritionMetaBox.php` (new), registered in `src/Plugin.php`.
- `src/Admin/Settings.php` — two new toggles + a "Content & Data" section (options + sanitization + quickjump).
- `src/Admin/AdminMenu.php` — Basics group + reorder; CPT links honor the disable setting; `maybe_redirect_disabled_hub()` on `admin_init`.
- `src/Template/TemplateAPI.php` — `get_layer_posts()` gating + `layer_has_sufficient_data()`.
- `src/Admin/AdminHome.php` — stat-box links honor the disable setting.
- `src/Admin/ContentHub.php` — Ingredients column.
- `uninstall.php` — clean up the new options and nutrition/ingredient meta keys.

## [1.5.4] - 2026-06-21

### Changed
- **Dashboard: removed the Light/Dark mode toggle.** The Auto/Light/Dark control in the dashboard header (and its handler) was removed. The underlying admin dark-mode application was also retired — `AdminMenu::maybe_add_dark_body_class` (the `admin_body_class` filter) and `AdminMenu::enqueue_admin_dark_css` (the `admin_enqueue_scripts` action that loaded `admin-dark.css`) are gone, since the toggle was the only way to set `pizzatier_setting_dark_mode` and leaving it active would have stranded OS-dark admins in a dark UI with no control. The `assets/css/admin-dark.css` file and the inert `body.pzl-admin-dark …` rules in `SiteMigration.php` remain on disk but are no longer referenced (safe to delete in a later pass).
- **Dashboard: Help promoted in the quick-access nav.** The Help tile moved to the second position and is rendered with a `--featured` treatment (accent border, tinted background, bold label, life-ring icon) so support is easy to find. Customizer's tile colour was nudged to avoid clashing with Help's accent.
- **Dashboard: removed the Layer Manager section and the Tips & Tricks card.** The `.plh-card--tabs` Layer Manager block and the Tips & Tricks rotator card were removed, along with their now-unused `$layer_tabs` / `$tips` data arrays and the associated tab/panel/rotator CSS. The features row (Shortcode Reference + Extend) reflows cleanly via its existing `auto-fit` grid. `assets/js/admin/admin-home.js` was reduced to an inert stub since it only drove the removed tabs and rotator.

### Added
- **Dashboard: layer-count boxes are now links.** Each box in the top stats row links through to the Content Hub: the six layer-type boxes open the Content Hub filtered to that CPT (`?page=pizzatier-content&pl_cpt=…`), Total Layers opens the Content Hub, and Active Template opens the Template page. Boxes gained hover/focus affordances.

### Files
- `src/Admin/AdminHome.php` — removed dark toggle, Layer Manager, Tips card and their arrays/CSS; linked stat boxes; featured Help in quick-nav.
- `src/Admin/AdminMenu.php` — removed admin dark-mode body class + stylesheet enqueue.
- `assets/js/admin/admin-home.js` — reduced to a stub (tabs/rotator removed).

## [1.5.3] - 2026-06-20

### Fixed
- **Content Hub bulk delete/trash threw "Cannot load pizzatier-content."** `ContentHub::maybe_handle_bulk()` — the handler that processes checkbox bulk actions (trash / untrash / restore / delete) and redirects afterward — was defined but never registered. Without it on `admin_init`, the bulk POST to `admin.php?page=pizzatier-content` was not intercepted and fell through to WordPress core's page-hook resolution in `wp-admin/admin.php`, which `wp_die()`s with "Cannot load …" when the hook doesn't resolve under that request context (made more likely by the full-URL submenu slugs used for the CPT menu items). Registered `maybe_handle_bulk` on `admin_init` in `Plugin.php`, so the action runs and `wp_safe_redirect()` fires before any output. Applies to both List and Grid view.
- **Content Hub List ⇄ Grid toggle could stick.** The localized config passed to `content-hub.js` never included the persisted view mode, so `currentView` always initialized to `'list'`. If a user's saved view was Grid, the server rendered Grid but the front-end thought it was in List, and clicking "List" no-opped (`view === currentView`). `AssetManager` now reads `pizzatier_hub_view` user meta and passes `view` in the localized data, keeping the toggle in sync with the rendered panel.

### Improved
- **Content Hub Grid (thumbnail) view.** Image-thumbnail card layout for each layer type as an alternative to the List table: responsive `auto-fill` grid, per-card checkbox for bulk selection, "Select all", inline Edit/Trash actions, search box, and numeric paging (24/page). Toggle List/Grid from the toolbar; the preference is stored per user (`pizzatier_hub_view`).
- **Content Hub custom columns.** Optional columns sourced from each CPT's real custom fields — `sort_order` (Order), `dietary`, `diameter_inches`, `calories`, `spice_level`, `thickness`, `slug`, `description`, `id` — scoped to the layer types they apply to. Most ship hidden by default (only Order, Dietary, and Diameter show out of the box); the rest toggle from the "Columns" dropdown and the selection persists per user, per CPT (`pizzatier_hub_cols`). The same values render as compact meta chips on Grid cards.
- **Content Hub tab-switch consistency.** Added the `presets` tab and a `wpListUrl` for every type to the localized CPT data, so the header (icon/label/description) and the "WP List" button update correctly when switching layer types via AJAX, and the Presets tab no longer leaves a stale header.

### Files
- `src/Plugin.php` — register `ContentHub::maybe_handle_bulk` on `admin_init`.
- `src/Assets/AssetManager.php` — localize persisted `view`, add `presets` + `wpListUrl` to `cptData`.

## [1.5.2] - 2026-06-11

### Fixed
- **`window.PizzaTierAPI.setState()` added to the Plainlist, Scaffold, and CommandCenter templates.** These three templates exposed `getState()` but not `setState()`, so PizzaTierPro's "Default Layers" (pre-selected ingredients) feature silently no-opped on them — selections were never applied and no error surfaced. All eight bundled templates now implement the same read/write API surface (`getState` / `setState` / `getAllInstances`), restoring full parity with Colorbox, Metro, NightPie, Fornaia (rustic), and PocketPie.
  - Plainlist: `setState()` resets, then replays exclusive/topping selections by clicking the matching `.pl-item[data-layer][data-slug]` element.
  - Scaffold: `setState()` resets, then clicks `.sc-card__btn--select` / `.sc-card__btn--add` on the matching card (cuts resolve under `data-layer="slicing"`).
  - CommandCenter: `setState()` resets, then triggers `.cc-btn--add` on the matching card; the `PizzaTierAPI` surface also gained `getAllInstances()` and `getState(id)` / `setState(id, state)` so Pro's instance-verification gate resolves correctly.

## [1.5.1] – 2026

### Fixed
- **Layer Image Maker — image source completely non-functional (showstopper).** `assets/js/admin/layer-image-maker.js` ended with a stray literal `</script>` tag left over from when the script was inlined in PHP. Loaded as a standalone file, that tag is invalid JavaScript and threw `Uncaught SyntaxError: Unexpected token '<'`, aborting the whole script so no handlers bound — drop-zone, file browse, and Media Library picker all dead. Tag removed.
- **Layer Image Maker — orphan comment printed on screen.** The `// JS enqueued via wp_enqueue_script( … )` note in `LayerImageMaker::render()` sat between a `?>` and `<?php`, so it was echoed into the page below the tool. Moved inside the PHP block.
- **Shortcode Generator — preset static shortcode dropped its toppings.** Selecting a preset emitted `[pizza_static preset="slug"]`, but `[pizza_static]` has no `preset` attribute; crust/sauce/cheese fell back to plugin defaults while toppings (which have no default) silently vanished. The generator now emits the correct `[pizza_preset id="…"]` shortcode (PizzaTierPro), keyed by preset post ID, which resolves the full saved pizza including toppings. Manual layer fields are disabled while a preset is selected.

### Changed
- **Layer-by-Layer Setup Guide moved to Help.** The tabbed per-layer setup walkthrough now lives on the **Help &amp; Reference** page as its own "Layer-by-Layer Setup" section, leaving the Setup Guide page focused on the progress checklist. Setup checklist items re-audited — all current and pointing to live destinations.

### Housekeeping
- Removed three empty brace-literal directories (`assets/{css,js}`, `includes/{css,js}`, `src/{Core,…}`) — accidental shell brace-expansion artifacts that would be flagged on WordPress.org SVN.

---

## [1.5.0] – 2026

### Fixed — Template settings audit (all 8 templates)
- **Plainlist: all 30 settings silently broken.** `pzt_plainlist_inject_css()` was hooked to `wp_head:99`, but `wp_print_styles` fires at `wp_head:8` — `wp_add_inline_style()` after that point is discarded. Re-hooked to `wp_enqueue_scripts:99` (the file loads during `wp_enqueue_scripts:10` via TemplateLoader, and later priorities registered mid-hook still fire).
- **Metro: three injection bugs.** (1) The tray/count/sticky/layout rules were `echo`'d raw into `<head>` with no `<style>` wrapper — invalid markup, never applied. (2) The CSS-variable block used `wp_add_inline_style` inside a `wp_head:20` closure — same too-late timing as Plainlist. (3) The Google-Font enqueue was registered as a nested `add_action('wp_enqueue_scripts', …, 10)` while that same priority was executing — WP_Hook iterates a copy of the current priority's callbacks, so it never ran. All output now builds one string attached on `wp_enqueue_scripts:99`; the font is enqueued directly.
- **PocketPie: every one of its 59 settings was decorative** — `pizzatier_template_pocketpie_generated_css()` returned `''` and nothing else consumed them. Now wired (49 settings kept):
  - *Generated CSS* (`pztp-template-css.php`): colour theme (8 preset palettes + Custom pickers, emitted as `--pp-*` vars), font family/custom font/base size, category label transform, widget max-width, per-layout pizza sizes (Corner Quad / Layer Deck / Slide Drawer / Stack Panel), Corner Quad panel width/max-height/trigger size/wrapper aspect, Layer Deck preview height/strip thumb width/selected-label toggle, drawer & sheet max heights, Stack Panel progress dots and step label toggles, chip thumbnail size/radius/grid columns/toppings columns/name labels, Slide Drawer pill bar position (`pp-sd-pills-pos--*`) and pill style (`pp-sd-pill-style--*`) variants, modal backdrop (blur/dark/none) and open animation (scale-fade/slide-up/fade/instant via `pp-modal-anim--*`), UI transition speed (`--pp-trans` overrides), chip hover lift, and the custom-CSS box (with `<` stripped to block tag injection).
  - *Markup* (`pztp-containers-menu.php`): `layout=` attr now falls back to the Default Layout Mode setting; Corner Quad TL/TR/BL/BR category settings drive corner assignment (visible-only, deduped, remainder appended); Reset and Review buttons toggle per settings in all four layouts; Review button label customisable; summary modal title customisable (mirrored to JS via `data-default`); backdrop-click-to-close gated; swipe-close flags and modal-anim class rendered on `.pp-root`; pill icon/label wrapped in targetable spans.
  - *JS* (`custom.js`): swipe-down-to-close honours the per-layout settings; summary title no longer hardcoded.
- Verified clean (CSS variables cross-checked against actual `template.css` consumption, hook timing valid): Colorbox (16), CommandCenter (14), NightPie (11), Rustic (36), Scaffold (16). Post-audit: **0 dead settings across 194 template settings.**

### Fixed
- **Pizza Shape settings never applied (showstopper).** `shortcode_atts()` defaults `pizza_shape` / `pizza_aspect` / `pizza_radius` / `layer_anim` to `''` (never `null`), so every template's `$atts['pizza_shape'] ?? get_option(...)` fallback could never reach the saved global option. `BuilderShortcode::render()` now resolves empty attributes from the global settings in one place, covering all 7 templates and the Gutenberg block. Per-shortcode attribute overrides keep precedence.
- **Crust Padding / Sauce Padding / Cheese Distance / Cheese Padding had no effect.** These options were saved but consumed nowhere. New `FrontendSettings::build_layer_inset_css()` emits inset CSS targeting the layer nodes every template renders (`[data-layer-id="layer-crust|sauce|cheese|topping-*"]` divs from each template's PizzaStack, plus Scaffold's `.sc-layer--*` `<img>` slots). Geometry: crust inset = crust padding; sauce inset = crust + sauce padding; cheese inset = cheese distance; topping inset = cheese distance + cheese padding. `width/height: calc(100% − 2n px)` is used so replaced elements (Scaffold's `<img>`s) shrink correctly.
- Scaffold stage previously applied the global aspect-ratio and border-radius inline regardless of shape — round/square pizzas could render as ovals or with square corners. The stage now mirrors `PizzaStack.applyShape()` semantics: aspect only for rectangle/custom, radius only for custom.

### Added
- **Demo / Announcement Bar now renders on the front end** — `BuilderShortcode::wrap_global_chrome()` prepends a `.pzl-announce` bar above the builder (once per page) on every template.
- **Help Screen Content now renders on the front end** — appended as a zero-JS `<details class="pzl-help">` "Need help?" panel below every builder instance. Styles live in `assets/css/pizzatier.css`.
- PocketPie: `data-pizza-shape="custom"` stage rule (previously fell back to round).

### Removed
- Five PocketPie settings with no corresponding feature in the template: Grain/Texture Overlay (no grain exists anywhere in the template), Coverage Picker Style and Coverage Picker Reveal (the coverage picker has a single baked-in implementation), Show Pizza Preview in Summary Modal (no such element), and Show Empty Rows in Summary (rows are JS-managed with no empty-state handling).
- Settings page sections whose options were not consumed anywhere: **Pizza Display, Animations (global UI), UI Styles, Branding, Layout, Typography, Colours, Spacing, Topping Display**, plus the dead `crust_aspectratio` key and the unused **Focus Ring / ARIA Language / Image Format / Client-Side Caching** A11y & Perf fields. The working Reduce Motion, High Contrast, Lazy-Load Images, and Preload Assets toggles remain.
- `FrontendSettings`: dead Typography/Colour/Spacing/Topping CSS generation and the `build_template_bridge()` var-mapping (≈300 lines) removed; `inject_inline_styles()` now carries only the layer-inset CSS and template-generated CSS. `localise_js_data()`, `apply_tab_order()`, `apply_sort_filter()`, a11y/perf hooks unchanged.
- `Settings.php`: unused duplicate preset helpers (`get_palette_presets`, `get_metro_color_schemes`, `get_plainlist_presets` — TemplateChoice has its own copies) and the orphaned `$color_options` / `$html_options` save loops.

### Changed
- Quick-jump pill nav rebuilt for the surviving sections: Default Layers, Toppings, Pizza Shape, Crust, Sauce & Cheese, Plugin, Pricing, A11y & Perf, Customer UX, Advanced, Import/Export (+ active template card).
- Admin-bar Settings sub-links updated to real `#pset-body-*` anchors (previously pointed at nonexistent `#shape`/`#branding` ids).
- `OPTIONS` const and `save_settings()` lists trimmed to match the page; settings import ignores removed keys automatically (filtered against `OPTIONS`).
- Crust section note now links to Pizza Shape (the Pizza Display anchor it referenced was removed).

### Notes
- Removed options are still deleted on uninstall via `uninstall.php`. The Settings Wizard still writes a few now-removed keys (e.g. branding colours); these are harmless orphans with no consumers — a wizard cleanup pass is recommended as a follow-up.

---

## [1.4.0] – 2026

### Added
- **Site Migration tool** — new `PizzaTier\Admin\SiteMigration` class plus a Tools-group submenu page at `pizzatier-migration`. Builds a single JSON export covering every plugin setting, all eight CPTs (toppings, crusts, sauces, cheeses, drizzles, cuts, sizes, presets) with title/slug/content/excerpt/status/menu_order/full meta/term assignments, the `pizzatier_ingredient_group` taxonomy tree (parent/child resolved by slug), and a layer-image reference per post (URL + filename + alt + caption). Importable on a fresh installation to reconstruct the entire setup.
- **Image-by-URL transport** — layer images are exported as URL references rather than packaged binaries. On import, `media_handle_sideload` pulls each one from its source URL into the destination media library, mirroring the existing pattern used by `LayerImageMaker` and `LayerImageMetaBox`. Keeps export files small (~10 KB for a small store, scales linearly with post count).
- **Create-only-by-slug import semantics** — `get_page_by_path` lookup per post-type/slug pair before insert; same pattern for taxonomy terms via `get_term_by('slug')`. Settings overwrite (matching existing Settings export/import behaviour), but posts and terms never do. Re-importing the same payload produces zero new posts or terms.
- **Two new extension hooks**:
  - `pizzatier_export_payload` (filter, `$payload`) — Pro and other add-ons contribute data to the export. Convention: contribute under the `pro` key (Pro) or a clearly namespaced top-level key (other add-ons).
  - `pizzatier_import_payload` (action, `$payload, $results`) — fires after the free-plugin import sections have run, regardless of whether a `pro` section was present. Add-ons consume their own slice of the payload here.
- **Dashboard quick-nav entry** — Site Migration card added to the AdminHome quicknav row with the migrate dashicon.
- **Help → Site Migration section** — full how-to plus developer extension example showing both filter and action usage.
- `PizzaTier\Admin\Settings::get_option_keys()` — public static accessor for the canonical option-key list. Existing internal `private const OPTIONS` is unchanged; the accessor is a thin read-only wrapper used by `SiteMigration` and available to extensions that need to enumerate plugin settings.

### Schema
- Export schema is versioned: `{"schema": "pizzatier-site-export", "version": 1, ...}`. Imports validate both fields and reject mismatches with a clear error. Future schema bumps will be backward-compatible at the importer level.
- WordPress-internal meta keys (`_edit_lock`, `_edit_last`, `_wp_old_*`, `_wp_trash_meta_*`) and the resolvable `_pizzatier_layer_image_id` are stripped from export. The image ID is re-derived from the sideloaded attachment on import.

### Docs
- Help → Developer Reference: new rows in the action-hooks table for `pizzatier_import_payload` and in the filter-hooks table for `pizzatier_export_payload`.
- Help → Developer Reference class map: added `Admin\SiteMigration`.
- Help → `pizzatier_cpt_registered` description and class-map entry corrected from "7 CPTs" to "8 CPTs (7 layer types + Presets)" — the Presets CPT was always registered, just undercounted in the docs.

### Limits
- Import file size capped at 25 MB (vs. 1 MB for the legacy settings-only import). A site with thousands of posts and verbose meta per post can comfortably fit; the cap mainly exists to reject malicious oversized uploads.
- Image sideload uses `download_url` with a 30-second timeout per image. Very large media libraries imported across slow networks may need to be re-run; create-only-by-slug guarantees the second pass picks up where the first left off (posts already created are skipped, but a missing layer image is independently re-attempted on a fresh-create flow only — flagged as a follow-up if real users hit this).

---

## [1.3.0] – 2026

### Added
- Command Center, NightPie, and Colorbox templates now expose configurable settings on the Templates page. Previously these three templates rendered an empty "no customizable settings" panel; they now ship with field definitions in `pztp-template-options.php` plus settings-driven CSS-variable injection in `pztp-template-custom.php`, matching the established Metro/Plainlist pattern.
- Command Center settings: 8 colors (accent, accent hover, completed-step, page bg, surface, raised surface, text, muted text), font family, base font size, corner radius, and three behavioural toggles (show step numbers, show summary sidebar, accent glow).
- NightPie settings: 6 colors, font family, base font size, corner radius, sticky-preview toggle, accent-glow toggle.
- Colorbox settings: 5 base colors, 7 individually configurable per-category tile colors (Sizes / Crust / Sauce / Cheese / Toppings / Drizzle / Cuts), font family, base font size, corner radius, and a master "Colorful Category Tiles" toggle that collapses tiles to a neutral surface when off.

### Fixed
- Templates page — stray PHP single-quote escape inside literal HTML output (`template\'s`) replaced with `&rsquo;`. Was rendering a visible backslash.

### Docs
- Help → Quickstart step 3: Colorbox was previously omitted from the list of seven user-facing templates. Added.
- Help → Quickstart step 2: now mentions the Settings Wizard as the recommended first-run path with a primary-button entry point.
- Help → Managing Content: now leads with a Layer Builder Wizard fast-path callout above the manual flow.
- Help → Template System lead: removed duplicate Scaffold mention; added Colorbox.
- Help → Template System CSS variable reference: rewrote the NightPie token block (previous values such as `--np-accent: #ff6b35`, `--np-bg: #1a1e23`, and a non-existent `--np-accent-hover` did not match the actual `template.css`); added a parallel block for Command Center; new "Per-template settings" intro paragraph pointing users at the Templates page UI before reaching for raw CSS.
- Help → Developer Reference class map: added `Core\Loader`, `Core\Activator`, `Core\Deactivator`, and `Admin\Customizer`.

---

## [1.2.1] – 2026

### Fixed
- Layer Builder Wizard now saves correctly — nonce action mismatch between `AssetManager` (`pizzatier_layer_builder`) and `LayerBuilderWizard::ajax_save_layer` (`pizzatier_wizard_save`) corrected; both ends now use `pizzatier_wizard_save`
- Layer Builder Wizard JS no longer ships with literal `<?php …?>` strings — all UI labels and alerts are now delivered via `wp_localize_script` (`pizzatierLBW.i18n`) with English fallbacks if a key is missing
- Layer Image Meta Box JS rewritten to remove leftover `<?php …?>` blocks and a stray `</script>` literal; element discovery now happens via scoped `querySelector` calls inside the wrap, so multiple meta boxes on the same screen no longer collide
- Settings import now correctly preserves Custom CSS and Custom JS — these capability-gated raw fields are no longer passed through `wp_kses_post` on import (matching the live save path)
- Settings import hardened: requires a real HTTP upload (`is_uploaded_file`), checks `UPLOAD_ERR_OK`, caps file size at 1 MB, requires a `.json` extension
- Template activation in Templates page now validates the posted slug against `TemplateLoader::get_available_templates()` before writing the option; invalid slugs no longer silently break the front end
- Layer Builder Wizard `ajax_save_layer` now verifies the supplied `image_id` is actually an attachment with an `image/*` MIME type before associating it with the new layer post
- Help page “Shortcodes” section icon — the malformed `</>` glyph (which some browsers stripped as an empty close tag) is now `⌨`

### Security
- Template settings save (both `TemplateChoice::save_template_settings` and `Settings::save_settings` template-options branch) now namespace-guards keys: only options whose key contains `_setting_` may be written, preventing a malicious template-options.php from overwriting core options like `siteurl`

---



### Fixed
- Settings Wizard option keys corrected throughout to match actual Settings page keys — saves now write to the correct database options (e.g. `pizzatier_setting_topping_maxtoppings`, `pizzatier_setting_layout_hide_empty`, `pizzatier_setting_cx_special_instructions`, `pizzatier_setting_perf_lazy_load`, `pizzatier_setting_typo_font_family`)
- Settings Wizard removed references to non-existent options: `dark_mode`, `cx_allow_name`, `cx_require_name`, `cx_show_price_live`, `layout_show_step_numbers`, `builder_title`, `confirm_button_text`, `builder_intro_text`, `builder_help_text`
- Settings Wizard `a11y_focus_ring` field corrected from toggle to select (options: theme default / bold / glow / none) matching the actual Settings page control
- Settings Wizard animation values corrected to match plugin values: `scale-in`, `slide-up`, `flip-in`, `drop-in`, `instant`
- Settings Wizard `pizza_shape` options corrected to `round`, `square`, `rectangle`, `custom`
- Settings Wizard Messaging step now uses real option keys: `pizzatier_setting_branding_tagline` and `pizzatier_setting_settings_demonotice`
- Help page template count corrected to six built-in templates (NightPie, Metro, Colorbox, Fornaia, PocketPie, Plainlist) plus Scaffold developer starter
- Help page Content Hub and Layer Types reference corrected from 8 to 7 layer types/CPTs
- Help page Developer Reference class map rebuilt with all actual classes, correct `PizzaTier\Api` namespace, and organised by category
- Help page `[pizza_layer_info]` shortcode added to Shortcodes section with full attribute table and copy-paste examples
- Help page Gutenberg info box updated to correctly state which three of the four shortcodes have native block equivalents

### Improved
- Settings Wizard Topping Rules step simplified to the one real setting (`max toppings`) — duplicate toppings toggle removed as it is not a standalone option in this version
- Settings Wizard now exposes `layout_step_by_step` and `cx_show_start_over` which are real, working settings previously missing from the wizard
- Settings Wizard Customer Experience step now shows the `cx_show_start_over` toggle alongside the other UX controls

---

## [1.1.1] – 2026

### Fixed
- Extracted all inline `<script>` blocks from admin PHP to properly enqueued JS files via `wp_enqueue_script` and `wp_localize_script` — resolves WordPress.org submission blocker
- Replaced per-instance inline `<style>` in Scaffold, Metro, and Plainlist templates with `wp_add_inline_style()` calls
- Added `Requires at least: 6.2` and `Tested up to: 6.7` to plugin header
- Removed artifact `includes/{css,js}/` directory from packaged build
- Fixed CHANGELOG.md release year timestamps

---

## [1.1.0] – 2026

### Added
- **Layer Builder Wizard** — step-by-step guided workflow for adding new ingredients with image upload, field population, and instant publish
- **Settings Wizard** — guided first-run configuration walkthrough covering template selection, fractions, colours, and layout
- **Admin dark mode** toggle for all PizzaTier admin screens
- Spanish (`es_ES`) and German (`de_DE`) translation files bundled
- `[pizza_layer_info]` shortcode for displaying layer metadata inline in content
- `pizzatier_builder_action_bar` action hook for Pro extension checkout bar integration
- `pizzatier_tab_order` filter for reordering or removing builder tabs
- `pizzatier_query_args_toppings` filter for customising topping query arguments
- `restrict` shortcode attribute — limit visible ingredients to a comma-separated slug list
- REST API `/presets` endpoint listing saved pizza presets

### Changed
- Colorbox template updated to v1.1.0 with improved touch targets and accessibility enhancements
- Template loader now falls back to first available template rather than hard-coded default when active template is missing
- Admin Content Hub consolidated ingredient management with AJAX panel switching
- Settings export uses a detached JS-constructed form to avoid nested-form issues
- `get_posts()` orderby uses array syntax for reliable `WP_Post` object returns

### Fixed
- `ServerSideRender` in Gutenberg block editor now returns a static branded preview when called via REST context (avoids missing template globals)
- PHP 7.4 compatibility — replaced all `str_ends_with()` / `str_starts_with()` calls with `substr()` / `strpos() === 0` equivalents

---

## [1.0.4] – 2025

### Fixed
- Checkout bar (Add to Cart row) relocated to the very bottom of all 7 template layouts
- Checkout bar now renders at 100% width regardless of template

---

## [1.0.3] – 2025

### Security
- Validate base64-decoded image bytes via `finfo::buffer()` before writing to disk in Layer Image Maker and Layer Image Meta Box upload handlers — rejects payloads that are not a recognised image type (PNG, JPEG, GIF, WebP)
- Derive file extension from the real MIME type of uploaded bytes rather than the client-supplied filename; pass the verified MIME type to `media_handle_sideload`
- Added `upload_files` capability check to Layer Image Meta Box AJAX handler
- Added allowlist validation to the `field_key` parameter in Layer Image Meta Box AJAX handler — only known layer image meta keys are accepted, preventing arbitrary meta key writes
- Added `sanitize_callback` to the `toppings` argument of the `/render` REST endpoint

### Docs
- Corrected REST API section of Help page — both endpoints are read-only and public; removed false "write endpoints require nonce" statement
- Corrected CPT count in Help page source reference

---

## [1.0.2] – 2025

### Security
- Added `current_user_can('manage_options')` capability check to template preview override handler (paired with existing nonce verification)
- Strip `</style>` sequences from admin-entered custom CSS before output to prevent style-block breakout
- Added `escHtml()` helper to settings-page admin JS; applied to all values injected via `innerHTML` in the layer picker modal and trigger button
- Added `scEscHtml()` helper to Scaffold template JS; applied to layer titles and coverage values in summary panel `innerHTML` construction
- Escaped media library attachment URL values before injecting into logo preview `innerHTML`

### Compatibility
- Replaced `str_ends_with()` calls in `LayerImageMaker.php` and `LayerImageMetaBox.php` with `substr()` equivalents for PHP 7.4
- Replaced `str_starts_with()` call in `TemplateLoader.php` with `strpos() === 0` for PHP 7.4

---

## [1.0.1] – 2025

Initial public release — see 1.0.0 for full feature list.

---

## [1.0.0] – 2025

### Added
- 7 built-in templates: Colorbox, Metro, NightPie, Fornaia, PocketPie, Plainlist, Scaffold
- Shortcodes: `[pizza_builder]`, `[pizza_static]`, `[pizza_layer]`, `[pizza_layer_info]`
- Gutenberg blocks: Pizza Builder, Pizza Layer Image, Pizza Static
- Custom Post Types: Toppings, Crusts, Sauces, Cheeses, Drizzles, Cuts, Sizes
- REST API endpoints: `/render`, `/layer-url`, `/presets` (opt-in, disabled by default)
- Full settings page: Typography, Colours, Spacing, Builder Layout, Customer Experience, Performance, Accessibility, Advanced
- Admin pages: Dashboard, Setup Guide, Content Hub, Shortcode Generator, Template Chooser, Help
- Layer Image Maker tool — generate and upload transparent layer PNG images from the admin
- WooCommerce-ready hooks for Pro extension integration
- Admin dark mode toggle
- Developer PHP and JS public APIs
- `.pot` translation file
