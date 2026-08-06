=== PizzaTier ===
Contributors: islandsundesign
Tags: pizza, restaurant, woocommerce, customizer, builder
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An interactive pizza builder and visualizer for WordPress. Let customers build their perfect pizza with a live layered image preview.

== Description ==

**PizzaTier** is a fully-featured, interactive pizza customizer for WordPress. Customers select their crust, sauce, cheese, toppings, drizzle, and cut style while watching a live layered pizza image update in real time. Embed the builder anywhere with a simple shortcode or Gutenberg block.

Built and maintained by [Ryan Bishop](https://islandsundesign.com) at [Island Sun Design](https://islandsundesign.com).

= Key Features =

* **Live visual pizza builder** — layered transparent PNG images stack and update as customers make selections
* **8 built-in templates** — choose from Colorbox, Metro, NightPie, Fornaia, PocketPie, Plainlist, Command Center, and Scaffold
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
* **Built-in WooCommerce integration** — a "Pizza" product type, the builder embedded on its product page, server-verified pricing, and the full build stored on the order line item
* **Per-layer price grids** — price every ingredient by size and coverage fraction, with bulk editing and CSV import/export
* **Built-in ordering** — take orders without WooCommerce at all, recorded straight in WordPress with no cart or payment step
* **Pizza presets** — save complete pizzas customers can pick in one click

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

**`[pizza_layer_info]`** — Renders text metadata about a layer (name, description, price).

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
* `pizzatier_builder_action_bar` — Fires inside the builder action bar (where the Add to Cart and Order Now bars render)

See the plugin documentation at [pizzatier.com](https://pizzatier.com) for a full hook reference.

= Custom Templates =

Duplicate the **Scaffold** template folder, give it a unique `function_prefix` in `pztp-template-info.php`, and register it via the `pizzatier_template_dirs` filter. The Scaffold template includes detailed comments and modular HTML partials designed for this purpose.

= Selling pizzas =

Everything needed to sell is included — there is no separate add-on to buy.

**With WooCommerce**
* A dedicated "Pizza" WooCommerce product type with its own configurator tab
* The builder embedded automatically on pizza product pages, with a size selector and a live price bar
* Size x coverage price grids per product, per ingredient, or site-wide, with CSV import/export and a bulk editor
* Six pricing models — add-on per layer, flat per size, highest layer wins, tiered by topping count, free first N, and bundle
* Server-verified pricing on add to cart, with the full build stored as order line-item meta
* Pizza configuration shown in the cart, on the order, and in order emails

**Without WooCommerce**
* PizzaTier's own ordering system records orders straight in WordPress — no cart, no payment step
* Kitchen-oriented order statuses, an orders screen, and staff-only customer notes
* Suits pay-on-collection, phone-order and delivery-on-account shops

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

No. The builder, price grids, presets and PizzaTier's own ordering system all work without it. WooCommerce is only needed if you want a cart, a checkout and online payment.

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

== Upgrade Notice ==

= 2.2.1 =
Compliance release for the WordPress.org plugin review. No functional changes.

= 2.2.0 =
Pizza Orders gets its own top-level admin menu with a live dashboard: status cards, a kitchen queue with one-tap next-step buttons, sound alerts, printable tickets, and a filterable list. Existing order URLs are unchanged.

= 2.1.1 =
Compliance release for the WordPress.org submission: adds direct-file-access guards to the block script asset files, and fixes the bundled Spanish/German translations, which never loaded.

= 2.1.0 =
Adds order routing: choose whether orders go to the WooCommerce cart, the pizza order list, both, or straight out by email and webhook. If your builder currently shows both an Add to Cart button and an Order Now button, it will now show one button that does both — see the changelog.

== Changelog ==

= 2.2.1 =

* Changed: removed the manual `load_plugin_textdomain()` call flagged by Plugin Check — WordPress.org language packs are loaded automatically since WP 4.6. The `/languages` catalogues remain in the repo as the translation source.
* Changed: shortened the 2.2.0 upgrade notice to fit the 300-character readme limit.

= 2.2.0 =

**Pizza Orders dashboard** — Pizza Orders is now its own top-level admin menu with a live dashboard built for running a shop during service. Everything updates in place over AJAX; the page never reloads.

* Added: status count cards for every order status, plus All and Needs-attention totals. Tapping a card filters the list.
* Added: an incoming-orders board — open orders oldest first, each with a single one-tap next-step button (Confirm → Start preparing → Mark ready → Complete, with Out for Delivery inserted automatically for delivery orders). Orders waiting over 15 minutes turn amber; over 30, red.
* Added: today at a glance — orders, pizzas, revenue, average order and the pickup/delivery split for the current day.
* Added: live polling with a pause toggle, an optional new-order chime, and the open-order count mirrored into the browser tab title.
* Added: a fully filterable, sortable order list — search by order number, name, phone or email; filter by status, fulfilment method and date range; sort by date, number, customer, total or status; bulk status changes, trash, restore and delete — all without a page refresh.
* Added: a quick-view drawer with the full ticket, one-tap advance, status change, internal notes and history — plus a printable kitchen ticket sized for receipt printers.
* Changed: the classic server-rendered list remains available at Pizza Orders → Classic List, and is the automatic fallback when JavaScript is off.
* Changed: the Pizza Orders entry under the PizzaTier menu is now a link to the new top-level menu. The page slug is unchanged, so bookmarked URLs and links in order-notification emails keep working.
* Added: `pizzatier_orders_next_step_map` filter to customise the one-tap workflow buttons.

= 2.1.1 =

* Fixed: the three `block.asset.php` script-dependency files did not guard against direct URL access. Each now exits when `ABSPATH` is undefined, satisfying the Plugin Check `missing_direct_file_access_protection` rule. No functional change — WordPress always defines `ABSPATH` before reading these files.
* Fixed: the bundled Spanish and German translations never loaded. The plugin ships `.mo` files in `/languages` but never called `load_plugin_textdomain()`, and WordPress's just-in-time loader only reads language packs from `wp-content/languages/plugins/`, not from inside the plugin. Now loaded on `init`.

= 2.1.0 =

**Order routing** — where an order goes is now a setting, not a consequence of which button is on screen. Orders → Ordering Settings gains five routes: record it in the pizza order list; add it to the WooCommerce cart; add it and go straight to checkout; both (one button records the order *and* carts it); or notify only, which emails and webhooks the ticket and keeps no record.

* Added: order webhook. Every order is POSTed as JSON to a configurable endpoint for a kitchen display, POS or automation service, signed with HMAC-SHA256 when a secret is set.
* Added: a "Pizza product" setting, so the cart routes work from a shortcode on an ordinary page and not only on a product page.
* Fixed: orders recorded in the pizza order list were always priced at zero. The `pizzatier_order_item_price` filter had never been connected to the price grid after the Pro merge in 2.0. It is now. An order that cannot be priced is still recorded, unpriced, rather than failing.
* Changed: "both" no longer means two buttons for the customer to choose between — it is one button, and the store chooses. Affected sites are migrated and shown a one-time notice.
* Added: the notify-only route never discards an order it could not deliver. If neither the email nor the webhook succeeded, the record is kept regardless of the setting.
* Fixed: the site exporter no longer carries the webhook secret or the pizza product ID to another site.
* Fixed: block editor scripts now declare their wp-* dependencies, removing an editor load-order race.

Older releases are documented in CHANGELOG.md inside the plugin.
