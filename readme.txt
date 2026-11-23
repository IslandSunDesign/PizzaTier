=== PizzaTier ===
Contributors: islandsundesign
Tags: pizza, restaurant, woocommerce, customizer, builder
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.4.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Interactive build-your-own pizza builder with a live layered preview, 8 templates, WooCommerce pricing and built-in ordering.

== Description ==

**PizzaTier** turns any WordPress page into an interactive build-your-own pizza experience. Customers pick a crust, sauce, cheese, toppings, drizzle and cut style while a layered pizza image updates in front of them in real time. When they are done, the finished pizza goes straight into a WooCommerce cart with a server-verified price, or into PizzaTier's own built-in order queue for shops that do not sell online.

Everything is included in this one plugin: the visual builder, eight ready-made templates, per-ingredient price grids, the WooCommerce "Pizza" product type and a live Pizza Orders dashboard built for running a kitchen during service. There is no separate paid add-on.

Plugin site, docs and support: [pizzatier.com](https://pizzatier.com)
Built and maintained by [Island Sun Design](https://islandsundesign.com) in Hilo, Hawaiʻi.

= Who it is for =

PizzaTier is built for independent and small-chain pizzerias, food trucks and pop-ups, and the agencies and freelancers who build their websites. Site owners get a guided setup wizard and a plain-English admin; developers get hooks, a public PHP and JavaScript API and a Scaffold template to build from.

= The builder =

* **Live layered preview** — each ingredient is a transparent PNG layer, stacked and updated as the customer makes selections, with no page reloads
* **Six ingredient categories** — crusts, sauces, cheeses, toppings, drizzles and cut styles, plus pizza sizes, each managed as a WordPress custom post type
* **Coverage control** — whole, half-and-half and quarter placement for toppings
* **Double, triple and more** — two ways per topping, with no fixed limit: a **Thickness** control (Regular, Double, Triple …) on the topping and in its coverage dialog, or **Separate layers**, where each tap of "Add another layer" adds a layer the customer sees listed and can remove. Added portions are charged as a full topping, a percentage, a fixed amount or free, and a layer can use a linked topping layer with its own artwork and prices. Every portion shows on the pizza, prices live and is listed on the order
* **Rules** — maximum toppings, required crust or sauce, hidden tabs, restricted ingredient sets and default selections, all set per shortcode
* **Presets** — save complete pizzas that customers can pick in one click, and show them anywhere with `[pizzatier_preset]`
* **Embed anywhere** — the `[pizzatier_builder]` shortcode or the Pizza Builder block; multiple builders can share a page
* **Static pizza images** — render a non-interactive layered pizza with `[pizzatier_static]`, or a single ingredient image with `[pizzatier_layer]`

= Eight templates =

Switch templates with one setting. Every template is themeable through CSS custom properties, so colours, spacing and typography can be matched to your theme without touching the template files.

* **Colorbox** — bright, playful builder with colourful category tiles and pill tabs. Great for family-friendly and fast-casual brands.
* **Metro** — clean, modern single-scroll layout with the pizza floating in a centred hero. Built for fast-casual and artisan brands.
* **NightPie** — dark UI with a sticky split-screen preview, tabbed sections, fly-to animation and a "Your Pizza" summary panel.
* **Fornaia** — warm, homestyle template with earthy tones, aged-paper texture, serif typography and vintage badge accents. Ideal for Neapolitan and wood-fired pizzerias.
* **PocketPie** — compact mobile-first builder with Corner Quad, Layer Deck, Slide Drawer and Stack Panel layout modes. Ideal for embedded storefronts and small spaces.
* **Plainlist** — text-first checklist with no visual canvas. Accessible, print-friendly, and available in single-scroll or step-by-step wizard modes.
* **Command Center** — dark navy UI with a numbered step wizard, persistent order-summary sidebar and a bold red accent. Built for chains that want a premium digital-ordering feel.
* **Scaffold** — a bare-bones developer starter with fully modular HTML partials and clean hooks. Duplicate any partial and build from there.

= Selling with WooCommerce =

* A dedicated **Pizza** WooCommerce product type with its own configurator tab
* The builder embedded automatically on Pizza product pages, with a size selector and a live price bar
* **Price grids** by size and coverage, set per product, per ingredient or site-wide, with a bulk editor and CSV import/export
* **Six pricing models** — add-on per layer, flat per size, highest layer wins, tiered by topping count, free first N, and bundle
* Prices are recalculated and verified on the server when the pizza is added to the cart
* The full build is stored as order line-item meta and shown in the cart, on the order screen and in order emails
* Works with both classic post-based orders and High-Performance Order Storage (HPOS) orders

= Selling without WooCommerce =

PizzaTier ships its own lightweight ordering system, so a builder can take orders on a site with no cart and no payment step. It suits pay-on-collection, phone-order and delivery-on-account shops.

* Orders are recorded in WordPress with kitchen-oriented statuses — new, confirmed, preparing, ready, out for delivery and completed, plus cancelled, refunded and failed
* Order routing — send each order to the WooCommerce cart, the pizza order list, both, or straight out by email and webhook
* Customer notes visible to staff only

= Pizza Orders dashboard =

A top-level **Pizza Orders** menu with a live dashboard designed for service hours. Everything updates in place over AJAX; the page never reloads.

* Status count cards, with tap-to-filter
* An incoming-orders board with one-tap next-step buttons (Confirm → Start preparing → Mark ready → Complete). Orders waiting over 15 minutes turn amber; over 30, red.
* Today at a glance — orders, pizzas, revenue, average order and the pickup/delivery split
* Live polling with a pause toggle, an optional new-order chime and the open-order count mirrored into the browser tab title
* Search, filter, sort and bulk-update the full order list; a quick-view drawer with a printable kitchen ticket sized for receipt printers

= Admin suite =

* **Settings Wizard** — guided first-run setup
* **Layer Builder Wizard** — step-by-step workflow for adding a new ingredient
* **Layer Image Maker** — generate and upload transparent layer images from inside the admin
* **Content Hub** — manage every ingredient post type from one screen
* **Site Migration** — export and import all PizzaTier settings and content between sites
* **Admin dark mode** for the PizzaTier screens
* **GDPR tools** — personal-data export and erasure integrated with the WordPress privacy tools; erasure anonymises orders while keeping the records a shop is legally required to retain

= Shortcodes =

**`[pizzatier_builder]`** — renders the full interactive builder.
Attributes: `id`, `template`, `max_toppings`, `show_tabs`, `hide_tabs`, `default_crust`, `default_sauce`, `default_cheese`, `pizza_shape`, `pizza_aspect`, `pizza_radius`, `layer_anim`, `layer_anim_speed`, `restrict`

**`[pizzatier_static]`** — renders a non-interactive layered pizza image.
Attributes: `crust`, `sauce`, `cheese`, `toppings`, `drizzle`, `cut`, `preset`
Example: `[pizzatier_static crust="thin-crust" sauce="classic-tomato" cheese="mozzarella" toppings="pepperoni,mushrooms"]`

**`[pizzatier_layer]`** — renders a single ingredient layer image.
Attributes: `type`, `slug`, `size`

**`[pizzatier_layer_info]`** — renders text metadata about a layer (name, description, price).

**`[pizzatier_preset]`** — renders a saved preset pizza.
Attributes: `id`, `template`, `title`, `size`, `align`

= REST API =

The public REST API is disabled by default. Enable it under **PizzaTier → Settings → Advanced**.

* `POST /wp-json/pizzatier/v1/render` — render a pizza layer stack and return HTML
* `GET /wp-json/pizzatier/v1/layer-url` — retrieve the image URL for a given layer type and slug

Both endpoints are read-only and rate-limited (`pizzatier_rest_rate_limit` and `pizzatier_rest_rate_window` filters).

= For developers =

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

A selection of the filters and actions available:

* `pizzatier_template_dirs` — register additional template directory paths
* `pizzatier_query_args_{type}` — modify the WP_Query args used to fetch a layer type, e.g. `pizzatier_query_args_toppings`
* `pizzatier_builder_atts` / `pizzatier_static_atts` — filter parsed shortcode attributes before render
* `pizzatier_layer_html` — filter the markup of a single layer image
* `pizzatier_order_statuses`, `pizzatier_order_route`, `pizzatier_order_webhook_payload` — customise the built-in ordering system
* `pizzatier_orders_next_step_map` — customise the one-tap workflow buttons on the orders dashboard
* `pizzatier_before_builder` / `pizzatier_after_builder` — fire around the builder canvas
* `pizzatier_builder_action_bar` — fires inside the builder action bar, where the Add to Cart and Order Now bars render
* `pizzatier_order_created`, `pizzatier_order_status_changed` — fire as orders move through the kitchen

The full hook reference is at [pizzatier.com](https://pizzatier.com).

= Custom templates =

Duplicate the **Scaffold** template folder, give it a unique `function_prefix` in `pztp-template-info.php`, and register it via the `pizzatier_template_dirs` filter. Scaffold includes detailed comments and modular HTML partials designed for exactly this.

= Privacy and external services =

PizzaTier does not contact any external service on its own. The only outbound requests it makes are the ones you configure: order-notification emails through your site's mail setup, and an optional order webhook to a URL you supply under PizzaTier → Settings. The public REST API is off until you turn it on. Order records can be exported and erased through the standard WordPress privacy tools.

= Translations =

Translation-ready with a bundled `.pot` file. Spanish (es_ES) and German (de_DE) translations are included, and further languages can be contributed through translate.wordpress.org.

== Installation ==

1. In your WordPress admin go to **Plugins → Add New**, search for **PizzaTier** and click **Install Now**, then **Activate**. (Or upload the `pizzatier` folder to `/wp-content/plugins/` and activate it from the Plugins screen.)
2. Open **PizzaTier → Setup Guide** and follow the Settings Wizard. It walks you through choosing a template, adding your first crust, sauce, cheese and toppings, and uploading layer images.
3. Add the builder to any page with the `[pizzatier_builder]` shortcode or the **Pizza Builder** block.
4. **Selling with WooCommerce?** Create a product, set its type to **Pizza**, and configure sizes and price grids on the Configurator tab. The builder appears on the product page automatically.
5. **Selling without WooCommerce?** Under **PizzaTier → Settings → Ordering**, turn on built-in ordering and choose where orders go. Orders appear under **Pizza Orders**.

Layer images should be transparent PNGs at a 1:1 aspect ratio (800×800 px or 1200×1200 px recommended). The Layer Image Maker under the PizzaTier menu can produce these from ordinary photos.

== Frequently Asked Questions ==

= Is WooCommerce required? =

No. The builder, price grids, presets and PizzaTier's own ordering system all work without it. WooCommerce is only needed if you want a cart, a checkout and online payment.

= What image format should I use for ingredient layers? =

Transparent PNG. Every layer is stacked on top of the ones below it, so transparency is required for the lower layers to show through. Recommended size: 800×800 px or 1200×1200 px at a 1:1 aspect ratio.

= Can I use multiple builders on the same page? =

Yes. Each `[pizzatier_builder]` shortcode generates a unique instance ID, so you can place as many builders on a single page as you need.

= How do I match the builder's colours to my theme? =

Go to **PizzaTier → Settings → Colours**. All colour values are applied as CSS custom properties and cascade through the active template. You can also add custom CSS under **Settings → Advanced**.

= Can I create my own template? =

Yes. Duplicate the **Scaffold** template folder, give it a unique `function_prefix` in `pztp-template-info.php`, and register it via the `pizzatier_template_dirs` filter.

= Does it work with page builders like Elementor or Divi? =

The `[pizzatier_builder]` shortcode works anywhere shortcodes are supported, which includes Elementor, Divi, Beaver Builder and the block editor. A dedicated Elementor widget is on the roadmap.

= I upgraded and my pizza builder disappeared. What happened? =

Version 2.3.0 renamed the shortcodes from `pizza_*` to `pizzatier_*` to meet the WordPress.org unique-prefix guideline. Update your pages to the new tags, or add `add_filter( 'pizzatier_register_legacy_shortcodes', '__return_true' );` to your theme's functions.php or a small must-use plugin to keep the old tags working while you migrate.

= What PHP version is required? =

PHP 7.4 or higher. The plugin is tested on PHP 7.4, 8.0, 8.1 and 8.2.

= Does the REST API expose my ingredient data publicly? =

The REST API is disabled by default. When enabled under Settings → Advanced, the `/render` and `/layer-url` endpoints are public, read-only and rate-limited, and return the same rendered HTML and image URLs already visible on the front end. No write endpoints are exposed.

= Does the plugin send data anywhere? =

Not unless you tell it to. Order emails go through your site's own mail configuration and the order webhook only fires if you enter a URL for it. Nothing is sent to pizzatier.com or any third party.

= Where can I get support? =

Use the [WordPress.org support forum](https://wordpress.org/support/plugin/pizzatier/) or visit [pizzatier.com/support](https://pizzatier.com/support).

== Screenshots ==

1. Live visual builder — customers watch the layered pizza update as they pick crust, sauce, cheese, toppings, drizzle and cut, with the price bar tracking every change.
2. Eight built-in templates — Colorbox, Metro, NightPie, Fornaia, PocketPie, Plainlist, Command Center and the Scaffold developer starter.
3. WooCommerce pricing — size × coverage price grids per ingredient, and the full build stored on the order line item.
4. Pizza Orders dashboard — status cards, a live kitchen queue with one-tap next-step buttons, and late-order highlighting.
5. Admin suite — the Layer Builder Wizard and Layer Image Maker, with every layer type managed as a WordPress post type.

== Upgrade Notice ==

= 2.4.4 =
Code-standards update for WordPress.org Plugin Check. No changes to features or settings. Includes all 2.4.3 security fixes; update promptly if you are on 2.4.2 or earlier.

= 2.4.3 =
Security release — update promptly. Fixes a script-injection flaw in the cart "Edit pizza" link on product pages, and hardens orders, pricing, privacy tools and site migration. Plain-http webhooks to public addresses now stop sending; switch them to https. See the changelog.

= 2.4.2 =
Every template now offers both Topping Multiplier methods: a Thickness stepper (card and coverage dialog) or "Add another layer", with each layer listed and removable. Separate layers no longer need linked layer posts.

= 2.4.1 =
Product pages no longer show a second size picker, Add to Cart button or builder. Customers can now add any number of portions of a topping; set a limit under PizzaTier → Topping Multiplier (empty = no limit). Fixes a one-cent live-price difference.

= 2.4.0 =
Adds Topping Multiplier (double/triple toppings), built in — the separate add-on is deactivated automatically and its settings kept. Fixes ingredients saved with an empty price table being free at checkout (see the notice after updating) and live prices that differed from the cart.

= 2.3.0 =
WordPress.org review compliance. All shortcodes renamed from the pizza_ prefix to pizzatier_ (e.g. pizza_builder is now pizzatier_builder). Update your pages, or see the changelog for the filter that re-enables the old tags while you migrate.

= 2.2.1 =
Compliance release for the WordPress.org plugin review. No functional changes.

= 2.2.0 =
Pizza Orders gets its own top-level admin menu with a live dashboard: status cards, a kitchen queue with one-tap next-step buttons, sound alerts, printable tickets, and a filterable list. Existing order URLs are unchanged.

= 2.1.1 =
Compliance release for the WordPress.org submission: adds direct-file-access guards to the block script asset files, and fixes the bundled Spanish/German translations, which never loaded.

= 2.1.0 =
Adds order routing: choose whether orders go to the WooCommerce cart, the pizza order list, both, or straight out by email and webhook. If your builder currently shows both an Add to Cart button and an Order Now button, it will now show one button that does both — see the changelog.

== Changelog ==

= 2.4.4 =

Maintenance release for WordPress.org Plugin Check. No changes to features, settings or stored data.

* Changed: "Tested up to" is declared only in readme.txt.
* Changed: removed redundant `suppress_filters` arguments from internal queries and replaced an `exclude` argument in the Topping Multiplier meta box with an equivalent filter.
* Changed: corrected code-standards annotations for justified exceptions.

= 2.4.3 =

Security release.

* Security: fixed cross-site scripting through crafted cart "Edit pizza" links on product pages (Metro, Colorbox, NightPie and Rustic templates). The server now verifies the link's signature and the templates escape restored values.
* Security: unpublished and password-protected ingredients are no longer shown or priced on the front end.
* Security: product ingredient allowlists match by post ID, and topping minimum, maximum and "max of same topping" are enforced on the server.
* Security: the public price, add-to-cart and render requests are size-capped and rate-limited per visitor; the ingredient index used for pricing is cached.
* Security: native orders are recorded exactly as priced. Unknown sizes are refused, and orders the price grid cannot price are held in a new **Needs Review** status.
* Security: the kitchen "Extra portions" line is written by the server; a retried checkout no longer creates a duplicate order; guest cart orders follow "Allow guest pizza orders".
* Security: notification and webhook settings need the new `pizzatier_orders_integration_capability` (default `manage_options`); the webhook secret is no longer shown; public webhooks must use https.
* Privacy: exports and erasure match only the exact customer email; erasure clears remaining account links; orders are excluded from Tools → Export.
* Fixed: Site Migration no longer links imported orders to unrelated accounts, and binned orders stay binned. If you imported orders with an earlier version, check their customer accounts.

= 2.4.2 =

* Added: a dedicated "Add another layer" control for the Topping Multiplier's Separate layers method in all eight templates; separate layers no longer need linked layer posts.
* Changed: clearer Thickness and layer wording in the builder, cart and orders.

= 2.4.1 =

* Changed: the Topping Multiplier has no fixed portion limit (safety ceiling 99).
* Fixed: duplicate size panel, Add to Cart button and description builder on product pages; a one-cent live-price rounding difference.

= Earlier versions =

The complete version history, including 2.4.0 (Topping Multiplier, pricing and WooCommerce fixes) and the 2.3.0 WordPress.org compliance release, is available at https://pizzatier.com/changelog/
