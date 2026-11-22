=== PizzaTier ===
Contributors: islandsundesign
Tags: pizza, restaurant, woocommerce, customizer, builder
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.4.3
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

= 2.4.3 =

Security release.

* Security: fixed DOM-based cross-site scripting on pizza product pages. The builder decoded the cart "Edit pizza" configuration from the address bar without checking its signature, and the Metro, Colorbox, NightPie and Rustic templates printed its coverage and slug values as HTML. A crafted link could run script for anyone who opened it. The server now verifies the link's signature and passes the builder a sanitised copy; the templates also escape these values and only accept known coverage values.
* Security: password-protected and unpublished ingredients are no longer shown or priced anywhere on the front end: builder menus, the static and preset shortcodes, the optional REST routes and pricing. Staff who can edit an ingredient still see it in previews.
* Security: an ingredient allowlist on a product is now matched by post ID, and a request for a layer type that has no ingredient with that slug is refused. Before, a sauce and a drizzle sharing a slug could stand in for each other.
* Security: "Maximum toppings", "Minimum toppings" and "Max of same topping" (Cart & Pricing → Topping Rules) are now enforced on the server, not only in the builder. A minimum of 0 still allows a plain pizza.
* Security: the public price endpoint, the add-to-cart request and the optional render routes now cap the request size and are rate-limited per visitor with an atomic counter. The ingredient index used for pricing is cached and rebuilt when an ingredient changes, instead of loaded on every quote. New filters: `pizzatier_price_rate_limit`, `pizzatier_price_rate_window`, `pizzatier_cart_rate_limit`, `pizzatier_client_ip`.
* Security: native orders now follow the same rules as pricing, so the kitchen ticket, the email, the webhook and the cart describe what was charged. Repeated toppings follow the Topping Multiplier limits, only toppings carry a coverage, an unknown size is refused, and a size the price grid does not cover is no longer charged as the first size. When an order cannot be priced from the grid it is held in the new **Needs Review** status, with a staff note, instead of being sent to the kitchen.
* Security: the "Extra portions" line on kitchen tickets is now written by the server from the validated toppings. Customers can no longer add their own "Extra portions:" line, including when order notes are switched off.
* Security: each checkout attempt carries a one-time key, so retrying after a lost connection returns the first result instead of placing a second order and sending a second ticket and email.
* Security: native ordering now honours Cart & Pricing → "Allow guest pizza orders" on routes that add to the WooCommerce cart.
* Security: the order notification address, webhook URL and secret, and the uninstall setting now require the new `pizzatier_orders_integration_capability` (default `manage_options`). A site that lowers `pizzatier_orders_capability` for kitchen staff no longer lets them redirect order data. The saved webhook secret is no longer printed into the settings page; leave the field blank to keep it.
* Security: webhooks to public addresses must use https. Local-network endpoints allowed by `pizzatier_order_webhook_allow_local` may still use http. To keep a public http endpoint, a developer can opt in with `pizzatier_order_webhook_allow_http`.
* Privacy: the personal-data exporter and eraser now match orders only on the exact customer email field (and the linked account). Before, an address typed into the name or company field also matched. The email index is rebuilt on update.
* Privacy: erasure and the retention sweep now also clear the customer's account ID from the order's author and from the "Order created" history entry. Orders anonymised earlier are repaired on update. New orders no longer record the customer as post author.
* Privacy: orders are excluded from Tools → Export (WordPress XML). PizzaTier's Site Migration still exports them when you choose to include orders.
* Fixed: Site Migration no longer copies the source site's user IDs onto imported orders, where they pointed at unrelated accounts. An order is linked only to an account with the same email address on the new site; staff names in the history and notes are kept as text. If you imported orders with an earlier version, check that the "Customer account" on those orders is right. Filter: `pizzatier_import_order_user_id`.
* Fixed: Site Migration restored orders from the bin as new, live orders. They are now imported back into the bin with their previous status.
* Hardened: the page URL stored on an order is kept only when it points at this site. The order rate limit groups IPv6 addresses by /64. Layer image uploads check the encoded size before decoding, and the layer-image meta box only accepts ingredient posts.

= 2.4.2 =

* Added: the **Separate layers** method has its own control in all eight templates. Customers tap "Add another layer"; each layer is listed under the topping, and in the template's topping list (summary, tray or receipt), as its own entry ("Pepperoni — layer 2") with a remove button. Before, both methods showed the same Amount stepper.
* Changed: the **Thickness** method's stepper is now labelled "Thickness" and appears on the selected topping and in its coverage dialog; the template's topping list shows the level ("Mushrooms · Triple").
* Changed: Separate layers no longer require linked topping layers. Without one, each added layer is a rotated copy of the topping, priced by the topping's pricing rule. Before 2.4.2 such a topping silently behaved as Thickness. Linked layers still use their own artwork and price grid.
* Changed: on the price breakdown, cart and order, separate layers read "Pepperoni (3 layers)", "Pepperoni — layer 2" … New wording settings: layers control label, add button, topping line and added layer line.
* Changed: the "Toppings at a glance" table on PizzaTier → Topping Multiplier shows whether each limit is the topping's own or the store default, and notes when the store's maximum toppings per pizza also caps added portions.
* Fixed: on narrow topping cards (Metro, PlainList rows) the multiplier controls could run past the card edge.

= 2.4.1 =

* Fixed: product pages showed parts of the builder twice by default — a "Choose your size" panel above templates that already have a Size step, and a second Add to Cart button below the builder's own checkout bar. Only Colorbox, which has no Size step, keeps the panel. Filters: `pizzatier_show_product_size_selector`, `pizzatier_show_product_add_to_cart_button`.
* Fixed: a `[pizzatier_builder]` shortcode or Pizza Builder block left in a pizza product's description rendered a second, unconnected builder. It is now hidden on that product's page (filter `pizzatier_suppress_description_builder`).
* Changed: **Topping Multiplier has no fixed limit.** Customers can add any number of portions (previously at most six, three by default). "Maximum portions per topping" under PizzaTier → Topping Multiplier now defaults to no limit; enter a number to cap it, and each topping can still set its own. Sites that saved a limit before keep it. A safety ceiling of 99 portions applies to every request (filter `pizzatier_multiplier_portion_ceiling`).
* Changed: every added portion is drawn as another rotated copy of the topping; beyond the sixth, copies turn by the golden angle so no two overlap exactly. With "Separate layers", amounts above the last linked layer repeat that layer.
* Changed: native orders allow extra layers per pizza for added portions, and repeated layers are looked up once per order.
* Fixed: the live price could be one cent below the charged price when a total ended in exactly half a cent (e.g. $33.475): the builder now rounds the same way as the server.
* Fixed: the checkout bar's Add to Cart button lost its cart icon after being clicked in some layouts.

= 2.4.0 =

**Topping Multiplier** — customers can now add more than one portion of the same topping (double, triple, up to 6×). Formerly the separate "PizzaTier Topping Multiplier" add-on; if it is installed it is deactivated automatically and its settings and per-topping options are carried over.

* Added: an Amount control (− Double +) on each selected topping card and in the coverage dialog, in all eight templates.
* Added: PizzaTier → Topping Multiplier settings (default method, maximum portions, pricing, wording, preview style, limits) and an "Extra Portions" box on every topping. Two methods per topping: **Thickness** re-uses the topping's own image and charges each added portion as a full topping, a percentage, a fixed amount or nothing; **Separate layers** stacks topping layers you add (with their own artwork and price grid) for Double, Triple and so on. Linked layers are hidden from the menus.
* Added: added portions follow the base topping's coverage, show on the pizza, are listed in the price breakdown, cart, order, emails and kitchen notes, and count toward the topping limit (optional).

**Pricing & checkout**

* Fixed (security): the server now looks up every ingredient by its slug and takes its price grid, type, name and coverage from the database. Previously a modified request could price an ingredient from another item's grid, label a topping as a free "cut", or add made-up items. Unknown and duplicated layers are dropped, and a topping's price column now follows the coverage the customer picked (a request could claim "Quarter" for a topping spread over the whole pizza).
* Fixed: saving an ingredient without filling in its Layer Pricing table stored an all-zero grid, making that ingredient free at checkout while the builder showed a price. Blank cells are now "not set" (they fall back to the global or product price). A notice lists ingredients affected by earlier versions and removes their empty tables in one click (or keeps them if they are meant to be free).
* Fixed: the live price could differ from the price charged. The builder now receives every ingredient's own price grid (not only the product's enabled layers), the site-wide grids from the Pricing page and legacy flat prices, and applies the minimum/maximum topping price, "nearest $0.50 / $1" rounding and discount rounding exactly as the server does.
* Fixed: "Flat per size" and "Tiered by count" pricing only read the product's own grid, so stores priced from the Pricing page got an error at checkout. They now use the global grid too.
* Fixed: in tiered mode, sauce, cheese and drizzle were charged a full Tier 1 price.
* Fixed: "Always round up" overcharged by a cent on some totals (e.g. $16.10 → $16.11); the Settings preview had the same error.
* Fixed: a coverage label such as "Quarter" could match a "Three-Quarter" column.
* Fixed: an empty tier-thresholds setting put every pizza in Tier 2.
* Fixed: prices of draft and private products were readable through the price endpoint, and the endpoint dropped each topping's half/quarter position, so its quote could differ from the cart.
* Fixed: "Enable REST price endpoint" and "Allow guest pizza orders" were saved but never applied. Removed four settings that were never implemented (tax display, tax class override, tax-inclusive grids, grid caching).
* Added: "Cart item name template", "Order note template" and "Cart item thumbnail" now work (they were saved but never applied).

**WooCommerce**

* Added: the WooCommerce Cart and Checkout blocks (the default cart since WooCommerce 8.3) now show the "Edit pizza" link and apply "Cart item name template" and "Cart item thumbnail"; before, these only worked in the classic cart.
* Fixed: the builder never appeared on classic themes in the default "Before add to cart" position.
* Fixed: shop-page "Add to cart" buttons and ?add-to-cart= links added an unconfigured pizza at base price. Pizzas can only be added from the builder (block cart / Store API included).
* Fixed: "Order again" put pizzas back in the cart empty at base price; they are now rebuilt and re-priced.
* Fixed: the checkout-bar quantity stepper was ignored, and a second identical pizza could not be added. "Disable quantity selector" now controls one-per-line.
* Fixed: the cart "Edit" link failed for some configurations (unencoded payload), and editing from the cart now restores the pizza in the builder, including coverage.
* Fixed: a note typed in the checkout bar was lost when using the main Add to Cart button.
* Fixed: default toppings set on a product never reached the builder.
* Fixed: an ingredient slug shared by two types (e.g. a "bbq" sauce and drizzle) could be priced from the wrong one.
* Fixed: "Out of stock" and store-hours / catalogue plugins were ignored for pizza products.
* Fixed: the separate pizza summary email was re-sent each time an order returned to Processing.
* Fixed: the Add to Cart button lost its icon after the first click; a dead Add to Cart bar on non-product pages; a PHP fatal when WooCommerce filtered quantity inputs without a product.
* Fixed: on a product using a different template from the site default, both templates' scripts loaded and the live price could not read the builder. A `[pizzatier_builder template="…"]` using a non-default template now loads its assets.

**Admin**

* Fixed: the product-editor preview endpoint shadowed the public `pizzatier/v1/render` route (now `admin-render`).
* Fixed: renaming a size or coverage column in the product price-grid editor could lose prices; removing a rebuilt column did nothing; currency symbols showed as `&#36;`; labels with quotes broke inputs.
* Fixed: Bulk Pricing turned "1,50" into 150.00 and marked rejected rows as saved.
* Fixed: price-grid save errors on an ingredient are now shown.
* Fixed: New Pizza Wizard "Start over" showed a blank page; preset toppings were dropped; an invalid grid created a product without prices.
* Fixed: a PHP 8 fatal in the setup checklist after importing an older site.
* Fixed: Site Migration lost site-wide price grids, split array meta (such as price grids) into separate rows, and imported products pointing at the wrong ingredients.
* Fixed (security): CSV exports neutralise spreadsheet formulas in labels; the preset summary no longer inserts ingredient titles as HTML.

**Pizza Orders**

* Fixed: bulk actions on the classic orders list always failed with "The link you followed has expired."
* Fixed (privacy): personal-data erasure skipped every order after the first page; a lookup for one email address also matched longer addresses containing it (ann@… matched joann@…); erasure now also removes the IP address, browser, referrer, pizza notes and status-history notes. Export, erasure and the retention sweep now include trashed orders.
* Fixed (security): orders used page permissions, so editors could change them through Quick Edit, and bulk restore/delete accepted any post ID. Orders now have their own capability.
* Fixed (security): an order submission may contain at most 20 pizzas and 60 layers each (filters `pizzatier_order_max_items`, `pizzatier_order_max_layers`). The rate limit is now atomic and counts every attempt. New `pizzatier_order_client_ip` filter for sites behind a proxy or CDN.
* Fixed (security): webhooks no longer follow redirects to internal addresses. A local webhook URL (e.g. a kitchen display on your network) is kept with a warning instead of being erased; allow it with the `pizzatier_order_webhook_allow_local` filter. The webhook secret is stored exactly as entered.
* Fixed: two orders placed at the same moment could get the same order number.
* Fixed: the order note was stored twice; with "Disable plugin CSS" on, the Place Order button reappeared on the confirmation screen and could resubmit the order.
* Changed: webhook failures are no longer shown to the customer. They are added as a staff note and reported in a new `admin_errors` key of `pizzatier_order_dispatch_result` (previously in `errors`).
* Fixed: a partly failed cart add is now reported; notify-only emails no longer link to an order that is not stored; a PHP 8.1 notice on the order screen; dashboard preferences fail safely when browser storage is blocked.

**Core & settings**

* Fixed: uninstalling deleted the order-number counter even when order history was kept, and left ingredient groups behind.
* Fixed: saving Settings switched off template toggles; importing settings could be overwritten by a save in the same request.
* Fixed (security): `[pizzatier_layer_info]` and `[pizzatier_layer]` could read any post type (including orders), drafts, password-protected posts and private fields. They now only read published ingredients and public fields.
* Fixed: the Settings Wizard saved after the page had started printing, and kept backslashes in values.
* Added: templates placed in a theme's (or parent theme's) `pzttemplates/` folder are now listed and load correctly, including their checkout bar and order partials.
* Fixed: default layers chosen in Settings and the Shortcode Generator stopped working when an ingredient was renamed; images added with the Layer Builder Wizard did not appear in the builder.
* Fixed: Content Hub pagination; the template preview link showed the previous page after saving; updating re-ran first-activation setup; "Disable plugin CSS" missed template stylesheets; the setup checklist did not find a builder added as a block; the menu sort setting broke filtered menus.
* Fixed: Site Migration and upgrades looked for orders under the wrong statuses.

**Templates**

* Fixed: after Reset (or with Pro default layers) the pizza could go blank, and Reset in one builder cleared every builder on the page.
* Fixed: templates overwrote each other's `window.PizzaTierAPI`, so two different templates could not share a page. A shared registry now routes `PizzaTierAPI.getState( id )` / `setState( id, state )` to the right builder; templates register with `PizzaTierAPI.register()`.
* Fixed: restoring a pizza (cart Edit, presets) lost each topping's coverage in several templates; Metro and Rustic could not restore a pizza at all.
* Fixed: Colorbox ignored default layers; Command Center and PocketPie ignored configured defaults; a default drizzle or cut was shown but not priced.
* Fixed (Scaffold): the Size panel never appeared, base layers were missing from its state, coverage was not drawn, and item cards did not escape names. Scaffold and PocketPie builders without an `id` shared one ID.
* Fixed: removing a topping from the summary left its card selected; coverage buttons stayed on the old setting after removal or reset; PocketPie mixed up "cut" and "slicing"; a JavaScript error in Colorbox and NightPie; spinning the pizza twice made it spin faster; in Metro, clicking a card's Add button added and immediately removed the topping.
* Added: a message when the topping limit is reached (Command Center, PlainList, Scaffold); accessibility fixes to the mobile menu toggle.

= 2.3.0 =

WordPress.org plugin review compliance release.

* Changed: **shortcodes renamed** to satisfy the WordPress.org unique-prefix guideline — `[pizza_builder]`, `[pizza_static]`, `[pizza_layer]`, `[pizza_layer_info]` and `[pizza_preset]` are now `[pizzatier_builder]`, `[pizzatier_static]`, `[pizzatier_layer]`, `[pizzatier_layer_info]` and `[pizzatier_preset]`. Existing content using the old tags will render the tag as plain text until updated. To re-enable the old tags while you migrate, add `add_filter( 'pizzatier_register_legacy_shortcodes', '__return_true' );` to your theme's functions.php or a small must-use plugin. The `[pizzatier-menu]` and `[pizzatier-static]` aliases are unaffected.
* Changed: all remaining inline `<style>` and `<script>` blocks (19, across the Cart & Pricing screens, meta boxes and the order screen) moved into enqueued asset files via `wp_enqueue_style` / `wp_enqueue_script`, with data passed through `wp_localize_script` / `wp_add_inline_script`.
* Changed: the `[pizzatier_layer]` shortcode / PizzaTier Image block output is now passed through `wp_kses()` with an explicit allowlist at return time, so markup supplied via the `pizzatier_layer_html` filter is escaped late.
* Changed: product JSON-LD is now encoded without `JSON_UNESCAPED_SLASHES`, so a `</script>` sequence in filtered schema values can no longer terminate the script tag early.
* Changed: the WooCommerce product-data tab key `pizza_configurator` renamed to `pizzatier_configurator`.
* Added: WooCommerce High-Performance Order Storage (HPOS) compatibility declaration, so PizzaTier no longer shows as incompatible on the HPOS settings screen.
* Removed: the unused pre-merge Commerce dashboard class (dead code since the 2.0.0 menu consolidation).

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

Older releases are documented in CHANGELOG.md inside the plugin.
