=== PizzaTier ===
Contributors: islandsundesign
Tags: pizza, restaurant, woocommerce, customizer, builder
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.15.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An interactive pizza builder and visualizer for WordPress. Let customers build their perfect pizza with a live layered image preview.

== Description ==

**PizzaTier** is a fully-featured, interactive pizza customizer for WordPress. Customers select their crust, sauce, cheese, toppings, drizzle, and cut style while watching a live layered pizza image update in real time. Embed the builder anywhere with a simple shortcode or Gutenberg block.

Built and maintained by [Ryan Bishop](https://islandsundesign.com) at [Island Sun Design](https://islandsundesign.com).

= Key Features =

* **Live visual pizza builder** — layered transparent PNG images stack and update as customers make selections
* **7 built-in templates** — choose from Colorbox, Metro, NightPie, Fornaia, PocketPie, Plainlist, and Scaffold
* **Shortcode & Gutenberg block support** — embed anywhere with `[pizza_builder]` or the Pizza Builder block
* **Custom Post Types** — manage Toppings, Crusts, Sauces, Cheeses, Drizzles, Cuts, and Sizes via the WordPress admin
* **Static pizza shortcode** — render a non-interactive layered pizza image with `[pizza_static]`
* **Layer image shortcode** — display a single ingredient layer image with `[pizza_layer]`
* **Layer info shortcode** — display ingredient metadata with `[pizza_layer_info]`
* **REST API** — render pizzas programmatically (opt-in, disabled by default)
* **Layer Image Maker** — generate and upload transparent layer images from inside the admin
* **Layer Builder Wizard** — step-by-step guided workflow for adding new ingredients
* **Settings Wizard** — guided first-run setup walkthrough
* **Admin dark mode** — toggle for the PizzaTier admin screens
* **Theme-compatible** — CSS custom properties let you match any theme's colour palette and typography
* **Developer-friendly** — action/filter hooks throughout, public PHP and JS APIs, Scaffold starter template for custom builds
* **Translation-ready** — `.pot` file included, Spanish and German translations bundled
* **WooCommerce ready** — pairs with the PizzaTier Pro extension for full cart, pricing, and order management

= Templates =

**Colorbox** — Bright, playful builder with colorful category tiles, pill tabs, and light dashboard-style surfaces. Great for family-friendly and fast-casual brands.

**Metro** — Clean, modern single-scroll layout. The pizza floats in a centered hero; ingredient sections flow below. Built for fast-casual and artisan brands.

**NightPie** — Modern dark UI with sticky split-screen pizza preview, tabbed sections, fly-to animation, and a "Your Pizza" summary panel.

**Fornaia** — Warm, homestyle template with earthy tones, aged-paper texture, serif typography, and vintage badge accents. Ideal for Neapolitan and wood-fired pizzerias.

**PocketPie** — Compact mobile-first builder with multiple layout modes: Corner Quad, Layer Deck, Slide Drawer, and Stack Panel. Ideal for embedded storefronts and small spaces.

**Plainlist** — Text-first checklist layout with no visual pizza canvas. Accessible, print-friendly, and available in single-scroll or step-by-step wizard modes.

**Scaffold** — A bare-bones developer starter template with fully modular HTML partials and clean hooks. Duplicate any partial and build from there.

= Shortcodes =

**`[pizza_builder]`** — Renders the full interactive builder.

Attributes: `id`, `template`, `max_toppings`, `show_tabs`, `hide_tabs`, `default_crust`, `default_sauce`, `default_cheese`, `pizza_shape`, `pizza_aspect`, `pizza_radius`, `layer_anim`, `layer_anim_speed`, `restrict`

**`[pizza_static]`** — Renders a non-interactive layered pizza image.

Attributes: `crust`, `sauce`, `cheese`, `toppings`, `drizzle`, `cut`, `preset`

Example: `[pizza_static crust="thin-crust" sauce="classic-tomato" cheese="mozzarella" toppings="pepperoni,mushrooms"]`

**`[pizza_layer]`** — Renders a single ingredient layer image.

Attributes: `type`, `slug`, `size`

**`[pizza_layer_info]`** — Renders text metadata about a layer (name, description, price if Pro is active).

= REST API =

The REST API is disabled by default. Enable it under **PizzaTier → Settings → Advanced**.

`POST /wp-json/pizzatier/v1/render` — Render a pizza layer stack and return HTML.

`GET /wp-json/pizzatier/v1/layer-url` — Retrieve the image URL for a given layer type and slug.

`GET /wp-json/pizzatier/v1/presets` — List available saved pizza presets.

= For Developers =

PizzaTier exposes a public PHP API for use in themes and other plugins:

    // Render a full pizza stack as HTML
    $html = PizzaTier\Builder\PizzaBuilder::render_pizza_stack([
        'crust'    => 'thin-crust',
        'sauce'    => 'classic-tomato',
        'cheese'   => 'mozzarella',
        'toppings' => ['pepperoni', 'mushrooms'],
        'drizzle'  => 'hot-honey',
        'cut'      => '8-slices',
    ]);

    // Get a layer image URL
    $url = PizzaTier\Builder\PizzaBuilder::get_layer_url( 'topping', 'pepperoni' );

**Filters:**

* `pizzatier_template_dirs` — Register additional template directory paths
* `pizzatier_query_args_toppings` — Modify the WP_Query args used to fetch toppings
* `pizzatier_tab_order` — Reorder or remove builder tabs
* `pizzatier_builder_shortcode_atts` — Filter parsed shortcode attributes before render

**Actions:**

* `pizzatier_before_builder` — Fires before the builder canvas renders
* `pizzatier_after_builder` — Fires after the builder canvas renders
* `pizzatier_builder_action_bar` — Fires inside the builder action bar (used by Pro for the checkout bar)

See the plugin documentation at [pizzatier.com](https://pizzatier.com) for a full hook reference.

= Custom Templates =

Duplicate the **Scaffold** template folder, give it a unique `function_prefix` in `pztp-template-info.php`, and register it via the `pizzatier_template_dirs` filter. The Scaffold template includes detailed comments and modular HTML partials designed for this purpose.

= Pro Version =

**PizzaTier Pro** extends this plugin with full WooCommerce integration:

* Custom "Pizza" WooCommerce product type
* Per-layer live pricing with 6 engine modes (add-on per layer, flat per size, highest wins, tiered by count, free first N, bundle)
* Add-to-cart AJAX flow with server-side price verification
* Order meta — full ingredient breakdown saved with every order and displayed in admin
* Cart display — size, toppings, base price, and order notes shown in cart and checkout
* Order emails — pizza configuration in WC order emails or as a standalone summary
* Nutrition display — calories and nutritional data per ingredient
* Cart editing — "Edit pizza" link in cart rehydrates the builder with saved configuration
* Order again — redirects to the builder pre-filled from a previous order
* JSON-LD schema markup for pizza products
* Full German and Spanish translations

Learn more at [pizzatier.com](https://pizzatier.com).

== Installation ==

1. Upload the `PizzaTier` folder to the `/wp-content/plugins/` directory, or install via the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **PizzaTier → Setup Guide** for a step-by-step walkthrough.
4. Add ingredient images via **PizzaTier → Content** for each CPT (Toppings, Crusts, Sauces, Cheeses, Drizzles, Cuts).
5. Choose a template under **PizzaTier → Template**.
6. Embed the builder using the `[pizza_builder]` shortcode or the **Pizza Builder** Gutenberg block.

== Frequently Asked Questions ==

= What image format should I use for ingredient layers? =

Use transparent PNG files. All layer images are stacked on top of each other, so transparency is required for the layers below to show through. Recommended size: 800×800px or 1200×1200px at a 1:1 aspect ratio.

= Can I use multiple builders on the same page? =

Yes. Each `[pizza_builder]` shortcode generates a unique instance ID. You can place as many builders on a single page as needed.

= How do I match the builder's colours to my theme? =

Go to **PizzaTier → Settings → Colours**. All colour values are applied as CSS custom properties and cascade through the active template. You can also add custom CSS under **Settings → Advanced**.

= Can I create my own template? =

Yes. Duplicate the **Scaffold** template folder, give it a unique `function_prefix` in `pztp-template-info.php`, and register it via the `pizzatier_template_dirs` filter. The Scaffold template includes detailed comments and modular HTML partials designed for this purpose.

= Does this plugin work with page builders like Elementor or Divi? =

The `[pizza_builder]` shortcode works anywhere shortcodes are supported. A dedicated Elementor widget is on the roadmap.

= Is WooCommerce required? =

No. PizzaTier is fully functional as a standalone visualizer and customizer without WooCommerce. The Pro extension adds WooCommerce integration for e-commerce functionality.

= What PHP version is required? =

PHP 7.4 or higher. The plugin is tested on PHP 7.4, 8.0, 8.1, and 8.2.

= Does the REST API expose my ingredient data publicly? =

The REST API is disabled by default. When enabled (under Settings → Advanced), the `/render` and `/layer-url` endpoints are public read-only endpoints that return rendered HTML or image URLs — the same data already visible on the front end. No write endpoints are exposed.

= Where can I get support? =

Visit [pizzatier.com/support](https://pizzatier.com/support) or use the WordPress.org support forum.

== Screenshots ==

1. NightPie template — dark split-screen builder with live pizza preview
2. Colorbox template — bright tile-based builder
3. Metro template — modern single-scroll layout
4. Fornaia template — warm artisan style with earthy tones
5. PocketPie template — compact mobile-first layout
6. Admin dashboard — PizzaTier overview and quick stats
7. Content Hub — manage all ingredient CPTs from one screen
8. Layer Image Maker — generate and upload transparent layer images from the admin
9. Settings — shape, layer spacing, and customer experience controls
10. Setup Guide — step-by-step guided walkthrough

== Changelog ==


= 1.15.0 =
* Renamed the plugin from **PizzaLayer** to **PizzaTier**. This is a name change only — no features added or removed. The text domain, plugin slug, namespace, constants, hooks/filters, block names, REST namespace, and bundled asset/translation filenames are all updated to the `pizzatier` naming, and the homepage is now https://pizzatier.com. Stored settings, content, and layer images are preserved (internal meta keys are unchanged), so the update is safe in place. The companion premium extension is renamed to **PizzaTier Pro** and should be updated alongside this release.

= 1.14.0 =
* Removed the Custom CSS and Custom JS code-insertion fields (global "Advanced & Developer" fields and the per-template Custom CSS boxes for Scaffold and PocketPie), per WordPress.org guidelines against saving arbitrary CSS/JavaScript. Each template's own appearance settings (colors, fonts, spacing, animation) are unchanged; for site-wide tweaks, use the Customizer's Additional CSS. Any previously stored custom code is ignored and cleaned up on uninstall.
* Static builder output from the `[pizza_static]` / `[pizzatier-static]` shortcodes and the matching block is now escaped at the output boundary through a filterable allowlist (`pizzatier_builder_kses`), so add-on markup stays sanitized.
* Admin styling that was previously inlined is now enqueued through the stylesheet pipeline: roughly 2,100 lines of admin CSS across thirteen screens moved into a single enqueued stylesheet, and the admin-bar and sidebar styles now load via `wp_add_inline_style()`. No visual change.

= 1.13.3 =
* Updated the plugin Author URI to https://islandsundesign.com (the Plugin URI remains https://pizzatier.com). No functional change.

= 1.13.2 =
* Plugin Check cleanup (no behavior change): replaced the version-gated `wp_is_serving_rest_request()` calls in the block render callbacks with a small internal `REST_REQUEST` check, so block editor previews are detected without referencing a function newer than the plugin's minimum WordPress version.
* Fixed the nonce-verification suppression on the admin sidebar's active-CPT highlight — the `phpcs:ignore` now sits on the line that actually reads `$_GET`, and the read is `wp_unslash()`'d before sanitizing. This is a read-only menu highlight with no state change.

= 1.13.1 =
* Security & hardening pass for WordPress.org Plugin Check: added defense-in-depth nonce re-checks to all settings/import save handlers, added missing `wp_unslash()` on AJAX uploads, sanitized the REST rate-limiter IP read, and moved output escaping to the point of output across the admin screens and the PocketPie/Plainlist/Scaffold templates (behavior unchanged).
* The Template-Choice preview-page lookup is now object-cached; fixed several `phpcs:ignore` comments that used an em-dash instead of `--` (which silently disabled the suppression, including real escaping cases).
* Internationalization: standardized the entire plugin to the `pizzatier` text domain (the 8 checkout-bar templates previously used `pizzatierpro`, which broke their translations and caused mismatch errors); added translator comments to every placeholder string; removed the redundant manual text-domain load (auto-loaded since WP 4.6).
* Compatibility: guarded `wp_is_serving_rest_request()` for WP < 6.5; "Tested up to" set to 7.0; prefixed globals in uninstall.php.

= 1.13.0 =
* **Fixed: template card & background settings that appeared to "do nothing."** A dormant global-skin override block in the Metro and Nightpie stylesheets was collapsing card and root styling to `inherit`, overriding each template's own values. This was the shared root cause behind: Metro's **Page Background Color** not applying, Nightpie's **Item Card Border** setting having no effect, and Nightpie's item cards losing their interior padding. All three now work as intended, and selected-card accent borders and idle card backgrounds resolve correctly again.
* **Metro → new UI Container Background setting.** Colour the builder panel (hero + ingredient sections) as one cohesive surface, separate from the Page Background (which now visibly frames the panel) and the card background.
* **Metro → new Card Text Color and Section Title Color settings.** Tune the ingredient-name text and the section headings/hero tagline independently.
* **Metro → roomier framing & uniform cards.** The Page Background now frames the builder, and each card's image area shares the card background so the whole card reads as one colour. Preset colour schemes updated to match.

For the complete version history, see CHANGELOG.md in the plugin folder or https://github.com/IslandSunDesign/PizzaTier

== Upgrade Notice ==

= 1.1.2 =
Maintenance release. Fixes inline script extraction (WordPress.org compliance), standardizes plugin name, and adds GPL license file. No database changes. Safe to update in place.

= 1.1.0 =
Feature release. Adds the Layer Builder Wizard, Settings Wizard, admin dark mode, and bundled Spanish/German translations. No database changes. Safe to update in place.

= 1.0.4 =
Fix release. Relocates the Pro checkout bar to the bottom of all templates. No database changes. Safe to update in place.

= 1.0.3 =
Security update. Hardens Layer Image Maker upload handling and REST endpoint sanitization. No database changes. Safe to update in place.

= 1.0.2 =
Security update. Adds capability checks, output escaping, and PHP 7.4 compatibility fixes. No database changes. Safe to update in place.

= 1.0.1 =
Fix release. Corrects plugin header text domain and resolves first-activation edge cases. No database changes. Safe to update in place.

== Credits ==

PizzaTier was created by **Ryan Bishop** of [Island Sun Design](https://islandsundesign.com).
