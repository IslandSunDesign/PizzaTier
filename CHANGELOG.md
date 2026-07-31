# Changelog

All notable changes to PizzaTier are documented here.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
Versions follow [Semantic Versioning](https://semver.org/).

---

## 2.1.0

**Order routing**

Where an order goes when a customer places it is now a setting rather than a consequence of which button happens to be on screen. Orders → Ordering Settings gains a "When a customer orders" choice with five routes:

* **Pizza order list** — record the order in WordPress. No cart, no payment step.
* **WooCommerce cart** — add the pizza and let the customer keep shopping.
* **WooCommerce checkout** — add the pizza and go straight to payment, skipping the cart page.
* **Both** — one button that records the order *and* adds it to the cart.
* **Notify only** — email the ticket and POST it to a webhook, then keep no record.

* Added: `PizzaTier\Orders\OrderRoute` is now the source of truth for where orders go. `ActionBarMode` survives as a derived view answering the narrower question of which bar renders; its constants, option key and `pizzatier_action_bar_mode` filter are unchanged, so code written against it keeps working.
* Added: `PizzaTier\Orders\RouteDispatcher` carries a submitted order to its destination — cart, webhook, checkout redirect — separately from the validation that builds it.
* Added: an order webhook. Every placed order is POSTed as JSON to a configurable endpoint, for a kitchen display, a POS, or an automation service. When a secret is set the body is signed with HMAC-SHA256 in the `X-PizzaTier-Signature` header.
* Added: a "Pizza product" setting. The cart routes need a WooCommerce product, and a builder embedded by shortcode on an ordinary page has none; this is the product those routes fall back to. On a product page the product itself is always used.
* Added: `PizzaTier\Orders\OrderProduct` resolves that product — posted ID, then queried object, then the configured fallback — validating every candidate, so an untrusted `product_id` can only ever select another real pizza product.

**Native orders are no longer priced at zero**

* Fixed: `pizzatier_order_item_price` had been applied since the ordering feature shipped and documented as the seam a premium extension would fill. Nothing ever filled it — the calculator lived in PizzaTierPro, and when Pro merged into the free plugin for 2.0 the seam was left unconnected. Every order recorded in the pizza order list therefore had a line total of zero. `PizzaTier\Orders\OrderPricing` now connects the commerce price grid to it.
* An order that cannot be priced — no grid on the product, a size that does not map, a layer the product does not permit — is still recorded, unpriced, for staff to quote by hand. Pricing never fails a submission.
* Added: `pizzatier_order_price_size` filters which grid size a native order is priced at when the order's own size does not match a grid column.

**Changed behaviour**

* Changed: "both" no longer means two buttons. It used to draw the Add to Cart bar and the Order Now bar together and let the *customer* choose the destination; it now draws one button and the *store* chooses. Sites upgrading with `action_bar_mode` set to `both` are migrated to the new route and shown a one-time admin notice explaining the change.
* Changed: the upgrade step writes each site's resolved route into an explicit setting instead of leaving it derived from the pre-2.1.0 options, so a later change to the fallback logic cannot move a working store.

**Safety and privacy**

* Added: the "notify only" route never discards an order it could not deliver. If neither the store email nor the webhook succeeded, the record is kept regardless of the setting, so a network failure costs a customer's dinner rather than the store's only copy of it. The settings screen warns when the route is active with nowhere to send.
* Added: `pizzatier_order_discarded` fires while a notify-only order is still fully readable, as the last chance for an integration to copy anything off it.
* Fixed: the site exporter no longer carries the webhook secret or the pizza product ID to another site. A secret in an exported file has stopped being a secret, and a post ID means something different on every install. Both are still deleted on uninstall. `OptionRegistry::is_portable()` and the `pizzatier_option_is_portable` filter control this.

**Also**

* Added: after a "both" order the confirmation panel tells the customer the pizza is in their cart and links to it, and reports it plainly if the cart add failed while the order itself succeeded.
* Added: `pizzatier_order_route`, `pizzatier_order_product_id`, `pizzatier_order_dispatch_result`, `pizzatier_order_webhook_payload`, `pizzatier_order_checkout_redirect` filters and the `pizzatier_order_webhook_failed` action.

---

## 2.0.7

* Fixed: `src/Core/OptionRegistry.php` used a compound direct-access guard (`! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' )`) that the WordPress.org Plugin Check scanner does not recognise. Replaced with the canonical single-condition guard. The second clause was redundant — `uninstall_plugin()` includes `uninstall.php` from inside a fully loaded WordPress, so `ABSPATH` is always defined when this file is required.

---

## 2.0.6

**Resilience against damaged installs**

* Fixed: a missing or unreadable file under `src/` no longer takes the whole site down. The four shortcodes were the only classes instantiated lazily on `init`, so an incomplete upload threw an uncaught `Error` out of `do_action( 'init' )` — fatalling every request including wp-admin and locking the site owner out. Each shortcode is now registered independently and skipped if its class cannot be loaded.
* Added: the autoloader now records and logs every class it cannot resolve, naming the expected file path and whether it is absent or merely unreadable, instead of returning silently and leaving a bare "Class not found" fatal.
* Added: an admin notice listing any files the autoloader could not load, with instructions to re-extract the plugin server-side.

---

## 2.0.5

**Personal data / GDPR tooling for native orders**

* Added: orders are now included in Tools → Export Personal Data. A request returns the order number, date, status, contact details, delivery address, instructions, order notes, line items and total.
* Added: orders are now included in Tools → Erase Personal Data. Erasure **anonymises rather than deletes** — name, contact details, address and notes are cleared while the order number, date, items and total survive, because tax and accounting rules require the store to keep a record of the transaction. The requester is told this in the confirmation message.
* Added: staff notes are included in personal-data exports by default. Notes written about an identifiable customer are that customer's data and a subject access request generally reaches them; "staff-only" is a display choice, not a legal exemption. Sites needing to withhold them can use the new `pizzatier_privacy_export_staff_notes` filter.
* Added: suggested privacy-policy text, surfaced in Settings → Privacy, describing exactly what the plugin stores.
* Added: optional retention sweep. Set "Auto-anonymise after" on the Orders settings screen and orders older than that are anonymised on a daily cron. Off by default.
* Added: orders are now indexed by customer email so a personal-data request can actually find them — the customer record is a serialised array and could not be queried. Existing orders are backfilled on upgrade. Guest orders are covered, not just orders from registered users.
* Added: the Orders settings screen now warns when "Require email" is off, because orders without an email address cannot be located by WordPress's email-keyed personal-data tools and must be handled manually.
* Added: `pizzatier_order_anonymised` action, fired after an order's personal fields are cleared.
* The retention cron is removed on deactivation.

## 2.0.4

**Site Migration — completeness**

* Fixed: migration exported only 98 of the 300+ options the plugin owns. Template settings for Colorbox, Command Center, Metro, NightPie, PocketPie and Rustic (157 keys) were silently dropped, so a migrated site landed on template defaults. All eight templates now migrate.
* Fixed: the entire native ordering configuration (20 settings) was never exported or imported.
* Fixed: global layout, typography, branding, topping-display and accessibility settings were missing from the export.
* Fixed: importing flattened array options to the string "Array", collapsed booleans to '1'/'', and wrote an empty string for options that were merely unset on the source — shadowing the destination's own defaults. Values now keep their type.
* Fixed: featured images and SCF/ACF image fields exported raw attachment IDs, which point at unrelated images on another install. Images now travel as URL references and are sideloaded into the destination media library.
* Fixed: template background images (e.g. Metro's container background) stayed hotlinked to the source site. They are now pulled into the destination's media library.
* Fixed: setup progress was only restored when the unrelated "Cart & pricing" box was ticked.
* Added: customer orders can be included in a migration. Opt-in on both sides and unticked by default, since order records hold names, phone numbers and addresses. Matched by order number, so re-importing never duplicates. Private staff notes are never exported.
* Added: `PizzaTier\Core\OptionRegistry` is now the single source of truth for option keys, discovering template settings from each template's own `pztp-template-options.php`. Export, import and uninstall can no longer drift apart. Extensions can join all three via the new `pizzatier_option_keys` filter.
* Export schema is now version 2. Version 1 files still import.

**Uninstall**

* Fixed: 34 template options (mostly Colorbox and Command Center) and all ordering settings were left behind in `wp_options` after uninstall.

**WordPress.org compliance**

* Added the missing `Requires PHP` header to the main plugin file.
* Replaced error-suppressed `@unlink()` calls with `wp_delete_file()`.
* Preset and layout-preview output now passes through a literal `wp_kses()` call at the point of output.
* Corrected the template count in the readme (8, not 7 — Command Center was missing).

## [2.0.3] - 2026-07-23

Code-quality release prepared for WordPress.org review. No functional changes, no
database or option changes, and no changes to any public CSS class, DOM ID or
JavaScript global.

### Fixed

- **`phpcs.xml.dist` renamed to `phpcs.xml`.** Plugin Check rejects the `.dist`
  extension, which maps to `application/octet-stream`, as a disallowed
  application file. This was the only hard error in the scan.
- **Prefixed `$instance_idx`** in the `pizza.php` WooCommerce add-to-cart
  template override, the one variable in the plugin that genuinely occupied
  global scope.
- **The copy-from-product picker no longer uses `post__not_in`.** It excluded a
  single ID from an IDs-only query, so the exclusion moved into PHP and the
  query no longer builds a `NOT IN` subquery.
- **`uninstall.php` order line-item cleanup** now interpolates
  `{$wpdb->prefix}` directly rather than passing a pre-built table name
  variable into the statement, so no variable reaches `$wpdb->query()` at all.
- Removed a duplicated `@var` docblock line above `WC_Product_Pizza::$product_type`.

### Changed

- **`Presets::$section_defs` array key renamed from `meta_key` to `state_key`.**
  These were never database meta keys — they are keys into the saved preset
  state array — but the literal name tripped the slow-query sniff eight times.
  The key *values* (`crust_id`, `sauce_id`, and so on) are unchanged, so stored
  preset data and the `data-meta-key` attribute are unaffected.

### Internal

- Documented, with justification, the sniff suppressions for cases that cannot
  be renamed: WooCommerce's `$product` global, the `WC_Product_Pizza` class name
  required verbatim by `WC_Product_Factory`, the checkout-bar layout variables
  that only appear global because the partial is included from inside a method,
  and the `tax_query` lookups that have no non-taxonomy equivalent.

### Upgrade note for existing installs

If a copy of this plugin has been installed since before 2.0.0, delete the
leftover `src/Pro/` directory and any old `phpcs.xml.dist` from the plugin
folder. The `PizzaTier\Pro\` namespace became `PizzaTier\Commerce\` in 2.0.0;
plugin updates overwrite files but never remove them, so the superseded copies
linger and will be reported by Plugin Check even though nothing loads them.

---

## [2.0.2] - 2026-07-23

Fixes an unreachable dashboard. **Update immediately from 2.0.0 or 2.0.1.**

### Fixed
- **`admin.php?page=pizzatier` redirected to itself, so the dashboard failed
  with "too many redirects".**

  2.0.0-alpha.6 added a redirect map sending the retired PizzaTierPro screens to
  their PizzaTier equivalents, including `'pizzatierpro' => 'pizzatier'`. The
  brand sweep in alpha.9 then rewrote every occurrence of the old name, turning
  that entry into `'pizzatier' => 'pizzatier'` — the dashboard redirecting to
  itself, unconditionally, on every admin request to that page.

  The whole redirect map is removed. It could not survive alpha.9 in any case:
  redirecting *from* the old routes requires naming them, and alpha.9 removed
  every reference to those names by design.

  The alpha.9 changelog claimed these redirects had been removed. They had not.
  Only the two blocks in the main plugin file were — the settings migration
  chain and the plugin-deactivation guard, the latter of which the same sweep
  had pointed at PizzaTier's own file. This third block lived in `src/Plugin.php`
  and was missed, and the changelog recorded an intent rather than a check.

### Added
- A regression test that requests every registered admin page, fires
  `admin_init`, and asserts none of them redirects to itself. It was confirmed
  to fail against the broken code before being accepted, so it tests something
  real. All 20 pages pass.

### Notes
- Audited for the same failure mode elsewhere. Twelve `'x' => 'x'` mappings
  exist in the codebase; all are legitimate normalisation tables — layer-type
  aliases, font names, CSS keywords — not sweep damage. Every other
  `wp_safe_redirect()` was checked for a self-referential path: the settings
  wizard's is a standard POST-redirect-GET, the customizer stub targets a
  different page, and the content-hub redirect is guarded on a different slug.

## [2.0.1] - 2026-07-23

Bug-fix release. Everything here was found by running PHPStan against the
plugin with WordPress and WooCommerce stubs after 2.0.0 was packaged — a class
of check the earlier phases had not applied.

**Three of these were introduced by the merge itself.** They are listed first,
because they are the ones that would not have existed otherwise.

### Fixed — regressions introduced during the merge
- **Importing a site export silently skipped every WooCommerce pizza product.**
  When the export payload key was renamed from `pro` to `commerce`, two
  references in the importer were missed. `$pro['wc_products']` evaluated to an
  undefined variable, so the check `! empty( $pro['wc_products'] )` was always
  false: the products were present in the file, read, and never applied. No
  error, no warning in the import summary.
- **The Colorbox and NightPie progress dots rendered blank.** Renaming a loop
  variable that shadowed a WordPress global changed `foreach ( … as $s )` but
  not the `$s` used inside the loop body, leaving it undefined.
- **The Site Migration export summary lost a line and logged a warning.**
  Removing a capability check left the `if ( $pro_active )` that depended on it.

### Fixed — pre-existing
- **Radio groups were shared between builders on the Plainlist template.** The
  item builder used `$instance_id` for the radio group `name`, but the variable
  was defined at file scope and the function never received it, so the name was
  emitted without its instance segment. Two builders on one page shared a group:
  choosing a crust in one cleared the choice in the other.
- **`wp_die()` was passing the HTTP status as the page title** in four handlers,
  so failed AJAX requests returned 200 with "400" or "403" rendered as a
  heading. Client code checking the response status saw success.
- `add_option()` and `update_option()` were passed the legacy `'no'` string for
  `$autoload` where a boolean is expected.
- A `catch ( \Throwable )` around a constructor that cannot throw.

### Removed
- **`assets/js/admin-grid.js` — 388 lines that were never enqueued.** 16 of the
  19 element ids and 8 of the 10 classes it looks for do not exist anywhere in
  the plugin: it targets a grid setup wizard that was removed from the PHP at
  some point without the script following. This is also the origin of the stale
  translations noticed earlier — "Grid Editor", "Setup Wizard", "What sizes do
  you offer?" — which had no source to match.
- A `pizzatierRusticSettings` object localised into every page using the Fornaia
  template. No script has ever read it; the labels it carries are rendered
  server-side by the template's own partial, from the same options.

### Notes
- Also verified rather than assumed: all 16 internal `class_exists()` string
  literals resolve to a declared class, every AJAX action posted by JavaScript
  has a PHP handler, and every nonce verified is created somewhere. These are
  exactly the contracts a large rename breaks silently, and none were broken.
- The remaining static-analysis output is understood and benign: `esc_attr()`
  receiving integers, which PHP coerces; `return` after functions that already
  terminate; and scope tracking that PHPStan loses across inline-HTML blocks
  inside a method.

## [2.0.0] - 2026-07-23

**PizzaTierPro merged into PizzaTier. One plugin, fully free.**

The twelve `2.0.0-alpha.*` entries below are the working record of how this was
done, step by step, and are kept for anyone tracing why the codebase looks the
way it does. This entry is the summary.

### The shape of it

PizzaTier and PizzaTierPro were 15,800 and 18,600 lines of PHP maintained as two
plugins with a deliberate one-way dependency between them. They are now one
plugin of roughly 34,000 lines, with the former Pro sources under
`src/Commerce/` and no trace of the old branding anywhere in the code, styles,
scripts, admin routes or translations.

Sequence, each step delivered and validated on its own: remove the licensing
layer; fold the sources in unchanged; unify namespace, text domain and
translations; reconcile internal identifiers; dismantle the integration bridges;
consolidate the admin menu, then the duplicated screens; consolidate settings;
reconcile duplicated features and rename the database keys; remove the
two-product framing; fix uninstall and add an upgrade routine; and finally a
WordPress.org compliance pass.

### Bugs found and fixed along the way

None of these were the point of the merge. All of them were found by reading
code that had to be touched anyway:

- Settings export omitted the entire cart and pricing configuration, so
  exporting from one site and importing on another silently lost every price
  grid default, cart and checkout option.
- Uninstall left behind the cart and pricing settings, every ordering setting,
  all price grids, and all pizza presets.
- Site export cast the setup checklist to a boolean, so importing replaced the
  destination site's checklist with `true` and broke it.
- The pricing, presets and nutrition admin disappeared entirely when WooCommerce
  was inactive, though the code was written to degrade rather than vanish.
- Two settings governed the builder action bar with opposite defaults; the
  native bar rendered only when both happened to agree.
- Admin forms saved text without unslashing, corrupting every apostrophe.
- Order emails and the order screen printed unescaped values.
- All eight template partials used `$post` as a loop variable, shadowing a
  WordPress global.
- A German translation carried an invalid `printf` directive.
- The readme changelog had been over the 5,000-character guideline for some time.

And one introduced by the merge itself and caught before release: folding the
integration bridge in unconditionally changed the default action bar for sites
that had never run PizzaTierPro, replacing a working order bar with an Add to
Cart button wired to a product that did not exist.

### Upgrading

Sites coming from PizzaTierPro must run the separate **PizzaTier Key Migrator**
plugin, which converts the database keys inherited from the old naming. Nothing
in PizzaTier reads the old names any more. The migrator surveys before it
writes, copies rather than moves, skips rows already converted so it is safe to
re-run, and works in batches so a long order history does not time out.

### Known and deliberate

- Merged help and setup-guide sections keep their original styling, so the seam
  between the two former plugins is still visible on those screens. Cosmetic.
- The Cart & Pricing settings remain their own screen rather than folding into
  the main Settings page. The two use incompatible storage and form-submission
  models; combining them would put two independent forms under one Save button,
  where pressing the visible one discards the other's edits.
- The three wizards remain three. They do genuinely different jobs.
- `SlowDBQuery` warnings are excluded with the reason recorded in
  `phpcs.xml.dist`; WordPress.org's own check will still report them.

### Verification

Every step was validated with PHP lint, `node --check`, CSS brace balance,
`msgfmt -c`, and a WordPress stub harness that boots the plugin with and without
WooCommerce, in admin and front-end contexts. The final compliance pass ran the
real WordPress Coding Standards and PHPCompatibility, not approximations.

**This plugin has never been executed against a real WordPress installation.**
The harness catches load-time and registration errors, not rendering, database
behaviour or WooCommerce interaction. Test on a staging site before deploying.

## [2.0.0-alpha.12] - 2026-07-23

Pre-release. WordPress.org compliance. **Not intended for production sites.**

Run against the real WordPress Coding Standards (WPCS 3.x on PHP_CodeSniffer
4.0.2) plus PHPCompatibility, rather than approximated with greps. The first
pass found **78 errors and 3 warnings across 18 files**, overwhelmingly in the
commerce code, which had never been through a WordPress.org review. All are
resolved.

### Fixed
- **Input was not unslashed before sanitizing** in the New Pizza wizard and the
  product Pizza Configurator tab. WordPress slashes superglobals, so
  `sanitize_text_field( $_POST['x'] )` without `wp_unslash()` stores a literal
  backslash before every apostrophe. A data-corruption bug, not a lint nag —
  product names and descriptions were affected.
- **Values escaped into a variable rather than at the point of output** in the
  order-email summary, the order pizza meta box and the wizard. Not exploitable,
  but PHPCS cannot verify escaping it cannot see, and Plugin Check rejects it.
  Escaping moved to each echo site.
- **Genuinely unescaped output** in the dashboard, the product mode cards and the
  preset picker, including a case where translated text containing a link was
  printed without `wp_kses()`.
- **Loop variables shadowing WordPress globals.** All eight template partials
  used `foreach ( $layers as $post )`, and several used `$tab` and `$s`.
  Function-scoped today, so harmless today — but a file that is included at
  global scope one day would silently clobber `$post` for everything after it.
  102 occurrences renamed rather than suppressed. `uninstall.php` had the same
  pattern.
- **`fputcsv()` and `str_getcsv()` relied on PHP's default `$escape`**, which
  8.4 deprecates. Now passed explicitly as an empty string, which also means
  exports use plain RFC 4180 quoting instead of PHP's proprietary backslash
  escaping — what spreadsheet software actually expects.
- `urlencode()` → `rawurlencode()` for a URL component.
- 17 `printf`/`sprintf` calls on translatable strings had no `translators:`
  comment, leaving translators to guess what each placeholder holds.

### Removed
- The PHP 8.1 `$title` null-safety guard. It assigned to a WordPress global,
  which WordPress.org prohibits, and the screen that originally triggered it
  stopped existing when the licensing layer was removed.

### Added
- **`phpcs.xml.dist`**, so this is reproducible rather than something that
  happened once. Scoped to the sniffs that decide whether a plugin is accepted;
  deliberately not the whole WordPress standard, whose formatting rules would
  bury real findings under thousands of cosmetic ones.

### Notes
- **28 findings were annotated rather than changed, each with a specific
  reason.** The largest group is nonce verification in handlers that verify
  through a helper which `wp_die()`s on failure — PHPCS cannot follow a call
  into a method. The helpers were read and confirmed correct before annotating.
  The rest are values sanitized element-by-element just after the read, an
  in-memory `php://memory` stream that is not a filesystem operation, `base64`
  used to build data URIs from inline SVG, and one table name in a `DELETE`,
  which cannot be a placeholder.
- **`SlowDBQuery` warnings are excluded, with the reason recorded in the
  ruleset.** Meta-key and taxonomy queries are flagged as slow and are, but
  WooCommerce pizza products are identified by a `product_type` term and layer
  posts by their meta — there is no other way to find them. The queries are
  bounded, admin-side, and off the front-end path. WordPress.org's own check
  will still surface them.
- PHPCompatibility against PHP 7.4 and up is clean.

## [2.0.0-alpha.11] - 2026-07-23

Pre-release. Uninstall coverage and a version-tracked upgrade routine.
**Not intended for production sites.**

### Fixed
- **Uninstall left most of the merged plugin's data behind.** Auditing what the
  plugin writes against what `uninstall.php` removes turned up:
  - **`pizzatier_options` was never deleted** — the entire cart and pricing
    configuration. The file contained the string `pizzatier_options`, but only
    as the name of the local variable holding the list of option keys, so a
    search for it looked like coverage. The variable is now
    `$pizzatier_option_keys` so the two cannot be confused again.
  - **`pizzatier_presets` was missing from the post-type deletion loop.** Every
    saved pizza preset, and its meta, survived a full uninstall.
  - **All ordering settings survived.** `OrderSettings` writes them under a
    shared prefix rather than from an enumerated list, so nothing named them.
    Removed by prefix now.
  - **Cart and pricing post meta survived**: builder template and position,
    enabled and default layers, pricing mode, product and per-ingredient price
    grids, preset layers, and the per-serving nutrition fields added in
    alpha.8. Product configuration lives on WooCommerce products, which are not
    ours to delete, so those keys are removed directly.
  - Per-user admin state survived.

### Added
- **`Core\Upgrade`**, running one-time steps on a version change. Activation
  hooks do not fire on an update — not from the updater, not from WP-CLI, not
  from replacing the directory over FTP — so anything that must happen once per
  release cannot rely on them. Steps are keyed by the version that introduced
  them and run in order, so a site jumping several releases gets each exactly
  once.
- The 2.0.0 step flushes rewrite rules, since post types and rewrite rules moved
  during the merge and a stale cache shows up as 404s on archive URLs.

### Notes
- **A site with no stored version is ambiguous** — it is either a fresh install
  or an upgrade from any release before 2.0.0, which is when version tracking
  arrived. Treating both as fresh would silently skip the steps for every
  existing site, which is the case that actually needs them. The presence of
  existing settings distinguishes them. `Activator` also records the version on
  activation, so fresh installs are unambiguous from the start. All five paths
  are covered by tests: fresh install, untracked upgrade, same version,
  earlier alpha, and an immediate re-run.
- **WooCommerce order line-item meta is removed only when the site has opted
  into deleting order records.** Otherwise a store that deliberately keeps its
  order history would find those orders emptied of what was actually ordered.
  Leaving a few rows behind is the lesser harm.
- The database key conversion is deliberately not an upgrade step. It belongs to
  the separate key migrator, which can survey before writing, work in batches
  and be re-run — none of which fits a routine that fires unattended on the
  first admin request after an update.

## [2.0.0-alpha.10] - 2026-07-23

Pre-release. Removes the last of the two-product framing. **Not intended for
production sites.**

Every remaining string, label and comment that described cart and pricing as a
separate product has been rewritten. After the alpha.9 rename these had become
actively wrong rather than merely dated — the plugin was telling readers that
features were "provided by PizzaTier", and the Site Migration instructions read
"install PizzaTier (and PizzaTier if you used it on the source)".

### Removed
- **The upgrade advert on the dashboard** — a dismissable "Supercharge with
  PizzaTier" panel with a "Learn more" link to a product page, plus the per-user
  dismissal state behind it.
- `pzt_has_pricing_addon()`'s two remaining call sites. It existed to decide
  whether to show the advert and whether to offer the commerce section of an
  export; both questions now have one answer.

### Changed
- **Site Migration** no longer presents cart and pricing as an optional section
  contributed by something else. The "Pro" pill is gone, the import checkbox is
  no longer conditionally disabled with a "not detected" warning, and the
  instructions describe one export containing everything.
- **Export payload key** `pro` → `commerce`. Export files written by earlier
  releases will import their settings and content but not their cart and pricing
  section.
- **Help** — the FAQ answer about WooCommerce previously said the integration was
  "provided by PizzaTier" with the base plugin handling the builder; it now
  describes what is actually there and points at the screen. The Site Migration
  and developer-hook sections, the code example, and the Sizes reference are all
  rewritten.
- **"Pro Settings"** as a label is gone throughout — 20-odd occurrences across
  the settings, help, setup-guide and dashboard screens, now "Cart & Pricing".
- **`readme.txt` rewritten** where it described a paid extension: the feature
  list, the shortcode and hook notes, the WooCommerce FAQ, and the "Pro Version"
  section, which is now "Selling pizzas" and covers both the WooCommerce path and
  the built-in ordering path.
- CSS class names carrying the old framing renamed: `plh-pro-cta` → `plh-cta`,
  `pset-pro-notice` → `pset-info-notice`, `psm-pill--pro` → `psm-pill--commerce`.
- Internal vocabulary: the order `price_source` value `'pro'` is now `'grid'`,
  and comments no longer speak of a "free plugin" and a separate premium one.

### Notes
- Ordinary English survives. "Add-on price per topping" is pricing terminology,
  not product framing, and "pro tips" is just an idiom.
- **The old product name is deliberately retained in two places:** this file's
  historical entries, and the upgrade instructions in `readme.txt`. Someone
  upgrading needs to recognise their own situation — an instruction to run the
  key migrator is useless if it cannot say what you are upgrading from.

## [2.0.0-alpha.9] - 2026-07-23

Pre-release. Complete removal of the PizzaTierPro naming. **Not intended for
production sites, and the first release that is not backwards compatible.**

5,030 replacements across 117 files. The plugin now contains zero occurrences of
`pizzatierpro` or `pztpro` in any case, anywhere — PHP, JavaScript, CSS,
translations and admin routes. The only remaining mentions are the historical
entries in this file.

### Changed
- **Namespace** `PizzaTier\Pro\…` → `PizzaTier\Commerce\…`, `src/Pro/` →
  `src/Commerce/`. Flattening into `PizzaTier\` directly was not possible —
  `Admin\Settings`, `Admin\Help` and `Admin\SetupGuide` exist on both sides.
- **CSS classes and DOM ids** `pztpro-*` → `pztc-*`. Not `pzt-*`: that prefix is
  already used by the ordering feature in 512 places and includes names such as
  `pzt-field`, so the obvious choice would have silently merged two unrelated
  rule sets.
- **JavaScript globals** `window.PizzaTierProBuilder` and
  `PizzaTierProBuilderInstances` → `PizzaTierBuilder` / `PizzaTierBuilderInstances`.
- **Snake-case identifiers** — AJAX actions, nonces, settings sections, filters
  — `pztpro_*` → `pizzatier_commerce_*`.
- **Admin page addresses:** `pizzatierpro-pricing-config` → `pizzatier-pricing`,
  `pizzatierpro-bulk-pricing` → `pizzatier-bulk-pricing`,
  `pizzatierpro-settings` → `pizzatier-commerce`,
  `pztpro-new-pizza` → `pizzatier-new-pizza`.
- **Child-theme override path** `your-theme/pizzatierpro/checkout-bar.php` →
  `your-theme/pizzatier/checkout-bar.php`.
- **Asset handles** `pizzatierpro-*` → `pizzatier-commerce-*`. Not
  `pizzatier-*`: several would have collided with existing handles, most
  visibly `pizzatier-settings`.
- Translation catalogues regenerated; obsolete entries and legacy headers
  stripped, so no shipped file carries the old name. 606 German and 574 Spanish
  strings remain translated — the drop from 621/588 is the strings whose text
  contained the old product name and therefore changed.

### Removed — breaking
- **The legacy key read fallback.** `Compat\MetaKeys`, added one release ago so
  the database rename could not lose anything, is gone: it existed only to know
  about the old names. **A site upgrading from PizzaTierPro must run the
  PizzaTier Key Migrator plugin**, or per-product builder configuration, price
  grids, presets and the pizza breakdown on existing orders will read as empty.
- The `pizzalayerpro_settings` → `pizzatierpro_settings` → `pizzatier_options`
  settings migration chain. The migrator plugin owns this now.
- The check that deactivated a still-active standalone PizzaTierPro plugin.
  **Note:** the sweep had rewritten that check's target to
  `pizzatier/pizzatier.php` — this plugin's own file — so on any admin request it
  would have deactivated PizzaTier itself. Caught by verifying the sweep rather
  than trusting it. Deactivating the old plugin is now called out in the
  migrator's instructions instead.
- Redirects from the retired `pizzatierpro-*` admin routes, which could not be
  kept without keeping the names they redirect from.

### Notes
- CHANGELOG.md keeps its historical entries. Rewriting them would misrepresent
  what was released and when, and they are the record of why the codebase looks
  the way it does.
- Verified after the sweep rather than assumed: no duplicate admin page slugs,
  no asset handle collisions, 255 `pztc-` CSS rules against 700 PHP emissions
  and 213 JavaScript references with no orphaned lookups, and a clean boot in
  all four context combinations with the action-bar ordering intact.

## [2.0.0-alpha.8] - 2026-07-23

Pre-release. Duplicate-feature reconciliation and the database key rename.
**Not intended for production sites.**

### Fixed
- **Site export corrupted the commerce setup checklist.** `Admin\Migration`
  exported the checklist as `(bool) get_option( ... )` and imported it with
  `update_option( ..., (bool) ... )`, replacing the destination site's array of
  ticked steps with `true`. Reading a step off a boolean then warned on PHP 8
  and the checklist stopped working. It is exported and imported as the array it
  is, and merged into any existing progress rather than replacing it.

### Changed
- **The two nutrition meta boxes merged into one.** Both registered on the same
  five ingredient post types, so every one of those edit screens carried two
  boxes asking for overlapping nutrition data. Only `calories` actually appeared
  in both, so the surviving box is the union: ingredients, serving size,
  calories, fat, carbs, protein, sodium, allergens, spice level, thickness and
  the dietary flags. Everything stores on this plugin's existing canonical
  `_pizzatier_*` keys.
- **Database keys renamed** from `_pztpro_*` to `_pizzatier_*` — 57 literals
  across 10 files, covering product configuration, presets, price grids,
  nutrition and WooCommerce order line items.
- The two setup-progress trackers merged into the single `pizzatier_setup_done`
  option. Their step keys did not overlap, so both checklists keep their state.
- PizzaTierPro's admin-bar contribution — a nine-line class adding one
  WooCommerce products link through a hook — folded directly into
  `Admin\AdminBar`. `ProAdminBar` is gone.

### Added
- **`PizzaTier\Compat\MetaKeys`**, which makes the key rename safe. Renaming
  stored data is the part of a rename that can actually lose something, so
  nothing depends on the conversion having been run: two core filters,
  `get_post_metadata` and `get_order_item_metadata`, fall back to the legacy key
  whenever the current one holds nothing. A site that never converts, or
  converts halfway, or is interrupted mid-run, keeps working. Writes always use
  the new key, so data converts naturally as records are edited.
- **PizzaTier Key Migrator**, a separate single-purpose plugin that converts
  existing rows in bulk. It surveys first and shows exactly what would change
  before touching anything; it copies rather than moves, leaving legacy rows in
  place unless explicitly told otherwise, so a rollback to a pre-2.0 release
  still finds its data; it never overwrites an existing new key, so it is safe
  to re-run; and it works in batches so a store with a long order history does
  not time out. Run it once and delete it.

### Notes
- **The three wizards were deliberately left as three.** New Pizza, Layer
  Builder and Settings Wizard look like duplication but do genuinely different
  jobs — creating a WooCommerce pizza product, creating a layer, and configuring
  the builder. Merging them would produce one wizard with three unrelated
  branches. This is a decision, not outstanding work.
- **`Admin\Migration` still contributes to the site export through the
  `pizzatier_export_payload` filter rather than being folded into
  `SiteMigration` directly.** That indirection is good structure, not merge
  residue: it keeps the exporter open to third parties and keeps the commerce
  export logic in one file. Collapsing it would have been churn for the look of
  the thing.
- Order line-item meta is renamed like everything else, but it is a historical
  record of what customers ordered rather than configuration. The read fallback
  matters most here: an interrupted migration cannot make an old order
  unreadable.
- Remaining `pztpro` identifiers — the namespace, roughly 4,000 CSS class and
  DOM id occurrences, JavaScript globals, asset handles, AJAX action names and
  admin page slugs — are code and markup rather than stored data, and are swept
  separately.

## [2.0.0-alpha.7] - 2026-07-23

Pre-release. Settings consolidation. **Not intended for production sites.**

### Fixed
- **Settings export silently omitted every cart and pricing setting.**
  `Settings::export_settings()` walks the `Settings::OPTIONS` allowlist, which
  covers the ~200 discrete `pizzatier_setting_*` options but not the single
  array option holding prices, cart behaviour, checkout, nutrition and order
  emails. Before the merge that option belonged to a different plugin, so the
  omission was arguably correct; afterwards it meant exporting settings,
  importing on a new site, and quietly losing all of it. The export now includes
  it, and the import restores it.
- Import routes the array through that screen's own `sanitize()` callback rather
  than a second sanitiser written here, so the two cannot disagree about what a
  valid value is. Verified with a full round-trip across every settings block —
  general, display, toppings, checkout-bar layout, cart, and order notes — with
  the destination site holding different values beforehand.

### Changed
- **The builder action-bar choice moved to the Orders screen.** It decides
  whether a customer gets a WooCommerce cart or an order recorded in WordPress,
  which is an ordering decision, and it now sits with the rest of the ordering
  settings. The WooCommerce options are disabled with an explanation when
  WooCommerce is not active. Its storage did not move — it stays in the
  cart-and-pricing option where `ActionBarMode` reads it, since moving the
  storage as well would mean migrating data for no benefit.
- **The Settings page's Cart Integration section rewritten.** It previously
  told the reader that "Pricing and cart features are provided by
  PizzaTierPro", with a "Learn more →" link to a product page. Pricing is built
  in now. The section explains what lives where and links to the Cart & Pricing
  settings, the price grids, and the Orders screen.
- Menu entry renamed from "Settings — Cart & Pricing" to "Cart & Pricing", the
  disambiguating suffix from alpha.5 no longer being needed.

### Notes
- **The Cart & Pricing settings deliberately remain their own screen rather than
  being folded into the Settings page.** The two use incompatible mechanisms:
  the Settings page is a single long form over ~200 discrete options with its
  own save handler and one sticky Save bar, while Cart & Pricing is a WordPress
  Settings API screen writing one array option through `options.php`. Combining
  them would put two independent forms on one page under a single Save button —
  edit pricing fields, press the visible Save, and the edits vanish with no
  warning. That is a worse outcome than two clearly-named screens, and it is the
  same reasoning that already keeps the ordering settings on the Orders screen.
  This is a deliberate decision, not deferred work.
- `Pro\Admin\Settings::sanitize()` only overwrites a block of keys when one of
  that block's keys is present in the submission, so that a save from one screen
  cannot wipe fields owned by another. That design is correct for partial form
  saves and also correct for import, because an exported file contains every
  key — confirmed by the round-trip test rather than assumed.

## [2.0.0-alpha.6] - 2026-07-23

Pre-release. Completes admin consolidation: the content merge that alpha.5's
menu restructure set up. **Not intended for production sites.**

### Changed
- **One Help page.** The cart-and-pricing documentation is now four sections of
  PizzaTier's own Help screen — Cart & Pricing Overview, Price Grids, Cart &
  Orders, and Cart Display Settings — bringing it to 14 sections. Pro's
  migration, FAQ and developer sections were deliberately not carried over as
  separate entries: PizzaTier's Help already has sections on all three subjects,
  and duplicating them would defeat the point.
- **One Setup Guide.** The WooCommerce setup steps appear as an optional
  "Selling pizzas" section, with their own progress bar and their own stored
  state, ticking back to the same screen.
- **One Dashboard.** The old PizzaTierPro dashboard is gone. Most of it was
  product marketing for a separately-sold plugin — a hero panel headed
  "WooCommerce Pizza Integration — Supercharged" — which stopped being true when
  the plugins became one. Its genuinely useful part, the status row, is now a
  compact "Cart & Pricing" card on the PizzaTier dashboard showing WooCommerce
  status, pizza product count and preset count, added through the existing
  `pizzatier_admin_home_cards` extension point.
- Section markup is **reused rather than transcribed.** `Pro\Admin\Help` and
  `Pro\Admin\SetupGuide` gained embed entry points, and the host pages call
  them. Retyping roughly 2,300 lines of markup into the host pages' conventions
  would have risked silent transcription errors for a purely cosmetic gain.
  `SetupGuide::render()` was split so the standalone screen and the embedded
  checklist share the same progress and step markup and cannot drift.

### Fixed
- **Setup copy that the alpha.5 gating fix made wrong.** The first setup step
  said "PizzaTierPro requires WooCommerce", which stopped being true when the
  pricing admin was made to work without it. It now explains that WooCommerce
  handles cart, checkout and payment, and that PizzaTier's own ordering system
  is the alternative.
- The step labelled "PizzaTier installed & active" is now "PizzaTier builder
  ready" — it was checking whether a separate plugin was present, which no
  longer means anything.

### Removed
- The `pizzatierpro`, `pizzatierpro-setup-guide` and `pizzatierpro-help` admin
  screens. All three redirect to their PizzaTier equivalents, so existing
  bookmarks and links in documentation land on the right screen rather than on
  WordPress's "you do not have sufficient permissions" error.

### Notes
- **The Cart & Pricing Settings screen is deliberately still separate.** Folding
  its nine sections into PizzaTier's Settings screen is settings consolidation,
  not admin consolidation, and the target is a 1,300-line file with its own tab
  system. It is the next step, along with moving the action-bar setting to the
  Orders screen.
- **The merged sections keep their original styling** — Pro's markup uses
  `pztpro-*` classes and dashicons while the host pages use `plhelp-*` / `psg-*`
  and emoji. Both stylesheets load, so everything renders, but the seam is
  visible. Harmonising it is cosmetic and better done once, after the settings
  merge, than piecemeal.
- Rendering was verified by invoking each merged section and checking output
  size and marker classes, and each retired route was checked to confirm it
  redirects to the right destination.

## [2.0.0-alpha.5] - 2026-07-23

Pre-release. Fifth step of the PizzaTierPro merge: admin menu consolidation.
**Not intended for production sites.** This is the first alpha in which the
admin visibly changes.

### Fixed
- **The pricing admin no longer disappears without WooCommerce.** The
  PizzaTierPro bootstrap returned early when WooCommerce was inactive, taking
  the entire Pro admin with it — Pricing, Bulk Pricing, the settings screen, the
  nutrition and price-grid meta boxes, the New Pizza Wizard. That was a bug
  rather than a design choice: `Pro\Plugin` re-checks for WooCommerce internally
  around each WooCommerce-specific registration and is written to degrade rather
  than vanish. A store can legitimately configure per-layer prices and presets
  before installing a shop, or take orders through PizzaTier's own ordering
  system and never install one. Those screens now load either way; the
  WooCommerce-specific parts remain gated.

### Changed
- **One admin menu.** PizzaTierPro's top-level menu — which sat at position 56,
  directly beside PizzaTier's own — is gone. Its screens are now submenus of
  PizzaTier under a new "Cart & Pricing" group header, between Content and
  Tools: Pricing, Bulk Pricing, Pizza Presets, New Pizza, and the three
  screens awaiting a content merge.
- **`PizzaTier\Admin\AdminMenu` is now the sole owner of the sidebar.** Menu
  registration was previously split across three classes (`AdminMenu`,
  `Pro\Admin\Dashboard`, `Pro\Admin\NewPizzaWizard`) hooking `admin_menu`
  independently, which made group placement depend on registration order.
  `Dashboard::register()` and `NewPizzaWizard::register_menu()` are now
  documented no-ops rather than deleted, so any code still calling them does not
  fatal.
- "Back to dashboard" links in Bulk Pricing and the New Pizza Wizard now point
  at the PizzaTier dashboard rather than the retired PizzaTierPro one.
- Menu labels no longer say "Pro". The three entries that still duplicate a base
  PizzaTier screen are suffixed "— Cart & Pricing" so they are tellable apart
  until their content is merged.

### Notes
- **No page slug changed and nothing became unreachable.** Every
  `pizzatierpro-*` slug is still registered, just under a different parent, so
  existing bookmarks and any links in documentation still resolve. The former
  PizzaTierPro dashboard (`admin.php?page=pizzatierpro`) is registered with an
  empty menu label: reachable by URL, but not adding a second dashboard entry
  beside PizzaTier's own.
- **This is step one of two.** The three duplicated screens — Settings, Setup
  Guide and Help — are relocated here but not yet merged. Folding their content
  into the base PizzaTier screens section by section, reconciling the two
  setup-progress trackers, and retiring the duplicate slugs behind redirects is
  the next step. It is separated because it is a content merge across roughly
  2,300 lines and deserves its own review.
- The action-bar setting's UI has still not moved to the Orders screen; that
  happens with the settings merge.
- Menu structure was verified by capturing the registered menu tree in both
  WooCommerce-active and WooCommerce-inactive states: one top-level menu, no
  submenus orphaned under a missing parent, and the group in the intended
  position.

## [2.0.0-alpha.4] - 2026-07-23

Pre-release. Fourth step of the PizzaTierPro merge: dismantling the integration
bridges. **Not intended for production sites.**

### Fixed
- **Regression introduced in alpha.1: the wrong action bar on PizzaTier-only
  sites.** Folding PizzaTierPro in meant its integration bridge registered
  unconditionally, and that bridge defaulted the action-bar area to
  WooCommerce-only. A site that had never run PizzaTierPro but had WooCommerce
  active for an unrelated shop therefore lost its native order bar and got an
  Add to Cart button instead — one wired to a WooCommerce product that, on such
  a site, generally does not exist.

  The default is now conditional on whether the site ever ran PizzaTierPro,
  detected by the presence of its options row rather than by its contents: such
  sites keep WooCommerce-only, and PizzaTier-only sites keep the native order
  bar. Both therefore behave exactly as they did before the merge.

### Changed
- **The action-bar choice is now one setting, not two.** It was governed by two
  options that could disagree and shipped with opposite defaults:
  `pizzatier_setting_orders_bar_mode` (PizzaTier's, two values, no UI, defaulting
  to "show my bar") and `pizzatier_options['action_bar_mode']` (PizzaTierPro's,
  three values, the only user-facing control, defaulting to "WooCommerce only").
  The native bar rendered only when both agreed. The PizzaTierPro setting is the
  one users could see and change, so it survives as the source of truth; the
  PizzaTier option is still honoured as an input when no explicit choice is
  stored, so anything setting it programmatically keeps working.
- **New `PizzaTier\Orders\ActionBarMode`** owns that decision — resolution,
  validation, the conditional default, and the degrade-to-native-bar behaviour
  when WooCommerce is inactive. It lives in PizzaTier's own namespace because it
  is an ordering decision, not a WooCommerce one.
- **`AddonBridge` collapsed.** Each method now reads PizzaTier's own data
  directly and passes the result through its filter, instead of asking the
  filter first and falling back. Roughly half the call path is gone.

### Removed
- **`PizzaTierPro\Pro\Integration\PizzaTierBridge`**, deleted. It existed only
  to let a separate plugin answer PizzaTier's capability filters; with both in
  one plugin it was answering its own questions. Its mode logic moved to
  `ActionBarMode`; its three filter responders are unnecessary now that
  `AddonBridge` resolves directly.
- The `<= 1.8.4` back-compat fallbacks in `AddonBridge`, and its
  `pztpro_action_bar_mode` filter (replaced by `pizzatier_action_bar_mode`).

### Notes
- **Filter semantics changed.** `pizzatier_addon_setting`,
  `pizzatier_addon_sizes` and `pizzatier_has_pricing_addon` are retained as
  extension points and still fire on every call, but they now receive
  PizzaTier's *resolved* value rather than `null` / `[]` / `false`. A callback
  written in the old "only answer if nobody else did" style — returning early
  when the incoming value is non-null — will no longer take effect. Callbacks
  should inspect and override. This is arguably an improvement: a third party can
  now override a value PizzaTier resolved, which the old ordering did not allow.
- **The shipped checkout-bar templates were deliberately left calling
  `pzt_addon_setting()`** rather than being switched to direct reads. Those files
  are documented as child-theme-overridable, so customer copies call the same
  helper; and routing through the helper is what keeps the filters applying.
  Changing the call sites would have bought nothing and broken overrides.
- Action-bar ordering is unchanged and verified: WooCommerce Add to Cart
  registers on `pizzatier_builder_action_bar` at priority 10, the native order
  bar at 20, so "both" mode still shows Add to Cart first.
- Mode resolution was tested across sixteen scenarios — PizzaTier-only and
  ex-Pro sites, each of the three stored values, no stored value, a corrupt
  value, the legacy option, and an empty options row, each with WooCommerce
  active and inactive.
- The action-bar setting's UI has not moved yet; it is still on the Pro Settings
  screen under "Order Bar". It relocates to the Orders screen during admin
  consolidation, when that screen is being reworked anyway.

## [2.0.0-alpha.3] - 2026-07-23

Pre-release. Third step of the PizzaTierPro merge: internal identifier
reconciliation. **Not intended for production sites.**

No feature, admin page, menu location, CSS class, DOM id, post meta key, AJAX
action, shortcode or REST route changed. The admin looks and behaves exactly as
it did in alpha.2.

### Changed
- **Settings accessor renamed.** `pztpro_get_setting()` → `pizzatier_get_option()`
  across 152 call sites in 17 files, plus 5 references written as strings inside
  `function_exists()` checks.
- **Settings option renamed.** `pizzatierpro_settings` → `pizzatier_options`,
  via `Settings::OPTION_NAME`. Deliberately *not* named `pizzatier_settings`:
  that is one character from the `pizzatier_setting_*` prefix used by the ~200
  discrete base options, which would be a trap for anyone grepping the codebase
  or maintaining the export whitelist.
- `Settings::OPTION_GROUP` → `pizzatier_options_group`.
- Five `function_exists( 'pztpro_get_setting' )` guards removed from
  `LayoutRegistry` and `PizzaTierBridge`. Post-merge the function always exists,
  so the guards were dead branches.
- `AddonBridge` doc comments corrected. They described the class as bridging to
  an external premium plugin and named "PizzaTier Pro 1.8.4 and earlier" — both
  false since the merge. The structure is unchanged; the class collapses into
  direct calls in the next step.

### Added
- `pztpro_get_setting()` retained as a deprecated shim forwarding to
  `pizzatier_get_option()`, since PizzaTierPro exposed it as a public helper and
  third-party snippets may call it. It raises `_deprecated_function()`. Nothing
  inside PizzaTier calls it — verified with a tripwire during boot testing.
- Three-generation settings migration, covering
  `pizzalayerpro_settings` → `pizzatierpro_settings` → `pizzatier_options`.
  A site updating from any point in that history keeps its configuration,
  including one that skipped the PizzaTierPro rebrand entirely. Verified against
  five scenarios: fresh install, each legacy key alone, both present (newer
  wins), and already-migrated (not overwritten).

### Notes
- **Legacy option keys are not deleted.** `pizzatierpro_settings` and
  `pizzalayerpro_settings` are copied forward and left in place so a site can
  roll back to a pre-merge release without losing its settings. They are removed
  by the consolidated upgrade routine later.
- **Export/import is unaffected.** `Admin\Migration` reads and writes through
  `Settings::OPTION_NAME` and stores the payload under the structural key
  `settings`, so previously-exported JSON files still import correctly.
- **Deliberately not renamed**, all for the same reason — an external contract
  where renaming risks breaking customer customisations for no user-visible
  benefit:
  - `pztpro-*` CSS class names and DOM ids (shared with stylesheets, cart JS and
    child-theme checkout-bar overrides)
  - `_pztpro_*` post meta keys (stored data; renaming needs a migration for no
    gain)
  - `window.PizzaTierProBuilder` / `…BuilderInstances` JavaScript globals
  - `pztpro_setup_done`, which is about to be reconciled with PizzaTier's own
    setup tracker during admin consolidation — migrating it now then merging it
    later would be two migrations for one flag
  - `pizzatierpro-*` admin page slugs and `pztpro_section_*` settings section
    ids, all retired behind redirects during admin consolidation
- The `sanitize_option_pizzatierpro_settings` filter documented in
  `Admin\Settings` becomes `sanitize_option_pizzatier_options`. It was
  documented as an extension point, so any third-party code hooking it needs
  updating.

## [2.0.0-alpha.2] - 2026-07-23

Pre-release. Second step of the PizzaTierPro merge: the mechanical naming sweep.
**Not intended for production sites.**

No feature, admin page, setting, option key, post meta key, CSS class, DOM id,
menu slug, AJAX action, shortcode or REST route changed. Stored data is
untouched. This step only renames code identifiers and unifies the text domain.

### Changed
- **Namespace unified.** `PizzaTierPro\Pro\…` → `PizzaTier\Pro\…` across 41
  files (149 references). This includes five class references written as
  *strings* rather than symbols — in `AddonBridge`, `OrderMeta`,
  `FrontendEmbed` and `LayoutHelpers` — which a symbol-only rename would have
  silently broken at runtime rather than at lint time.
- **Text domain unified.** 1,156 `'pizzatierpro'` literals → `'pizzatier'`.
  The one occurrence deliberately left alone is `Dashboard::MENU_SLUG`, which is
  the literal string `'pizzatierpro'` used as an admin menu slug, not a text
  domain; changing it would have broken the menu. It is retired during admin
  consolidation.
- **Constants unified.** `PIZZATIERPRO_VERSION`, `PIZZATIERPRO_PLUGIN_DIR` and
  `PIZZATIERPRO_PLUGIN_URL` (40 references) replaced with their PizzaTier
  equivalents.
- **Translations merged.** PizzaTierPro's German and Spanish catalogues folded
  into PizzaTier's own, which had 857 strings and — as it turns out — **zero**
  translations; every translated string in the plugin came from PizzaTierPro.
  The merged catalogues carry 625 German and 593 Spanish translations. The 38
  msgids present in both catalogues had identical translations, so there were no
  conflicts to resolve.
- `pizzatier.pot` regenerated from the merged source: 1,822 strings, up from
  1,044, as it now covers the Pro sources and the template and partial files.

### Removed
- The transitional second autoloader prefix, the `PIZZATIERPRO_*` compatibility
  constants and the explicit `pizzatierpro` text-domain loader added in
  alpha.1 — all now unnecessary.
- `pizzatierpro.pot` and the `pizzatierpro-de_DE` / `pizzatierpro-es_ES`
  catalogues, superseded by the merged ones.

### Notes
- **62 German and 59 Spanish translations were marked obsolete**, not lost. They
  are preserved as `#~` entries in the `.po` files. These are translations for
  UI strings — grid editor and setup wizard labels such as "Grid Editor",
  "Half pizza" and "What sizes do you offer?" — that no longer exist anywhere in
  the source. The catalogues had simply never been re-merged against a fresh
  template after those features changed, so the stale entries accumulated.
- **PizzaTier's own locale files were empty stubs.** `pizzatier-de_DE.po` and
  `pizzatier-es_ES.po` each carried 857 msgids and not one translation. They
  have been shipping as dead weight; they now carry the merged translations.
- **JavaScript globals were deliberately not renamed.**
  `window.PizzaTierProBuilder` and `window.PizzaTierProBuilderInstances` are a
  cross-file contract between `frontend-builder.js`, `cart.js` and the per-
  template `custom.js` files — and potentially customer customisations. Renaming
  them is cosmetic churn with real breakage risk, the same reasoning applied to
  the `pztpro-*` CSS class names. They are invisible to users.
- User-facing strings containing the word "PizzaTierPro" were also left alone.
  Changing them would change their msgids and invalidate the translations just
  merged; they are rewritten during admin consolidation and the de-upsell pass,
  when the surrounding UI is being reworked anyway.

## [2.0.0-alpha.1] - 2026-07-23

Pre-release. First structural step of merging PizzaTierPro into PizzaTier as a
single, fully free plugin. **Not intended for production sites.**

This step is deliberately pure file movement plus the minimum wiring needed to
make it boot. No feature was renamed, moved, redesigned or removed; no option
key, post meta key, CSS class, DOM id, menu slug, AJAX action, shortcode or REST
route changed. PizzaTierPro's admin menu still appears separately, exactly where
it was.

### Added
- All PizzaTierPro sources under `src/Pro/` (37 files, ~18,600 lines), carried
  over with their original `PizzaTierPro\` namespace intact.
- PizzaTierPro's assets merged into this plugin's `assets/` directory. There
  were no filename collisions between the two sets, so every existing asset path
  in the Pro sources resolves unchanged.
- PizzaTierPro's WooCommerce template override directory (`woocommerce/`) and its
  de_DE / es_ES translation catalogues.
- Second autoloader prefix resolving `PizzaTierPro\…` to `src/…`, so Pro classes
  load from `src/Pro/` without being renamed. Transitional.
- Compatibility constants `PIZZATIERPRO_VERSION`, `PIZZATIERPRO_PLUGIN_DIR` and
  `PIZZATIERPRO_PLUGIN_URL`, aliased to their PizzaTier equivalents, so the ~35
  references in the Pro sources need no edit. Transitional.
- `pztpro_get_setting()` and the one-time `pizzalayerpro_settings` →
  `pizzatierpro_settings` migration, both ported verbatim from the PizzaTierPro
  bootstrap.
- Explicit `load_plugin_textdomain()` for the `pizzatierpro` domain, hooked on
  `init`. The Pro sources still carry ~1,100 strings on that domain, and because
  it is not this plugin's declared text domain it is not loaded just-in-time.
  Hooked on `init` rather than `plugins_loaded` because WordPress 6.7+ warns
  about loading translations earlier.
- Safety check that deactivates a still-active standalone PizzaTierPro (or
  PizzaLayerPro) plugin and explains why. Without it, the old plugin would
  re-define the `PIZZATIERPRO_*` constants and register a duplicate admin menu.
- `Plugin::boot_pro_features()`, a faithful port of the PizzaTierPro bootstrap
  tail: integration bridge, preset meta box, and `[pizza_preset]` unconditionally;
  the WooCommerce feature set behind `class_exists( 'WooCommerce' )`.

### Removed
- The separate PizzaTierPro `plugins_loaded` bootstrap, its base-plugin presence
  check, its `PIZZATIERPRO_MIN_BASE_VERSION` gate and the associated admin
  notices. The base plugin is now the same plugin, so there is nothing to check.

### Notes
- **Boot timing changed.** The Pro feature set used to register on
  `plugins_loaded` at priority 20; it now registers at priority 10, inside
  PizzaTier's own boot. Everything involved is hook registration rather than
  immediate work, and `class_exists( 'WooCommerce' )` is already settled at any
  `plugins_loaded` priority, so the two should be equivalent. This is the only
  behavioural difference in the step and the thing most worth confirming on a
  live store.
- The builder action-bar ordering is unchanged and verified: the WooCommerce Add
  to Cart bar registers on `pizzatier_builder_action_bar` at priority 10 and the
  native order bar at 20, so "both" mode still shows Add to Cart first.
- Without WooCommerce, the entire Pro admin (Dashboard, Pricing, Bulk Pricing,
  Pro Settings) does not load — only the bridge, preset meta box and preset
  shortcode do. `Pro\Plugin` re-checks for WooCommerce internally and looks like
  it was meant to degrade rather than disappear, so this is arguably a bug. It
  predates the merge and is preserved here deliberately rather than fixed
  silently; it is resolved during admin consolidation.
- Two admin menus are expected in this build. Consolidation is a later step.

## [1.17.0] - 2026-07-19

### Added — Native pizza orders (Phase 1: data foundation)
PizzaTier can now record customer orders on its own. This release ships the
data layer; the front-end checkout, admin management screens and customer
private notes follow in subsequent releases. Nothing in this feature depends on
PizzaTierPro, WooCommerce, or any other plugin.

- **`pizzatier_order` custom post type** (`PizzaTier\Orders\OrderPostType`).
  Private by design: `public => false`, `publicly_queryable => false`,
  `exclude_from_search => true`, no rewrite, no query var, and
  `show_in_rest => false` because order records can carry customer contact
  details. `show_ui` is on but `show_in_menu` is off — orders are surfaced
  through PizzaTier's own admin screens. Registration args are filterable via
  `pizzatier_order_cpt_args`; the managing capability is filterable via
  `pizzatier_orders_capability` (default `manage_options`) so sites can hand
  order management to a shop-manager role.
- **Kitchen-oriented order statuses** (`PizzaTier\Orders\OrderStatuses`),
  registered as real WordPress post statuses so counting, filtering and
  `WP_Query` work natively without meta queries: New, Confirmed, Preparing,
  Ready, Out for Delivery, Completed, Cancelled, Refunded, Failed. Each carries
  a label, badge colour, description, and an "open" flag marking whether it
  still needs staff attention. Extendable via `pizzatier_order_statuses`. All
  status names are kept at or under 20 characters, the width of the
  `post_status` column.
- **Order model** (`PizzaTier\Orders\Order`) — the single read/write surface
  for an order record, covering: customer (name, email, phone, company), linked
  WP user ID for logged-in customers, fulfilment method and address, itemised
  pizzas (template, size, every chosen layer with its coverage fraction and
  source post ID, quantity, per-pizza notes, unit and line price, and where the
  price came from), whole-order totals (subtotal, tax, delivery fee, discount,
  tip, total, currency), the customer's own order note, internal staff notes,
  a timestamped status-transition history, and provenance (origin, page, URL,
  referrer, IP, user agent, active template). All input is sanitised on write,
  so raw request data can be passed straight to the setters.
- **Sequential order numbering.** `PZT-0001` by default, backed by an option so
  numbers stay short, stay sequential, and are never reused after a deletion.
  Prefix, padding and the final number are each filterable
  (`pizzatier_order_number_prefix`, `pizzatier_order_number_pad`,
  `pizzatier_order_number`).
- **Extension points:** `pizzatier_order_created`,
  `pizzatier_order_status_changed`, `pizzatier_order_cpt_registered`,
  `pizzatier_order_statuses_registered`, `pizzatier_order_fulfillment_methods`.

### Quality assurance (Phase 7)
- Verified across both plugins: PHP lint on every file, `node --check` on every
  script, brace balance on every stylesheet, PSR-4 namespace-to-path agreement
  for all classes, and no unguarded duplicate function definitions.
- **Cross-plugin hook contract audited in both directions.** Every hook
  PizzaTierPro consumes exists in PizzaTier, and every hook PizzaTier fires for
  add-ons is answered. No orphans.
- **End-to-end lifecycle test:** builder submission through order creation,
  server-side layer resolution, status progression across all six kitchen
  states, staff notes, history logging, and the store notification email.
  Confirmed a layer slug that does not exist is dropped rather than recorded,
  that rate limiting engages at the configured threshold, that a filled honeypot
  creates no record while returning a success-shaped response, that a forged
  nonce is rejected, and that internal staff notes never appear in the
  notification email.
- **Visual QA** of the order bar and checkout panel at 1440px, 860px and 390px.
  No horizontal overflow at any width; the bar stacks and the panel goes
  full-screen on mobile as intended.
- Translation template regenerated: 184 new singular strings and 3 plural forms
  from the ordering feature added to `pizzatier.pot`, taking it to 1,044 entries.
  Structurally validated — no entry is missing its `msgstr`.

### Known issues, not introduced by this release
- `templates/{rustic,nightpie,colorbox}/pztp-containers-presentation.php` each
  define `pizzatier_toppings_visualizer_func()` without a `function_exists()`
  guard. Harmless today because nothing includes those files — they appear to be
  legacy — but two of them loading together would be a fatal redeclare. Left
  alone rather than modified blind.
- The `.pot` contains one duplicate msgid, `Ingredient Groups`, which comes from
  a `_x()` call whose context is not being emitted. Present before this release.

---

### Fixed — PizzaTier no longer depends on the premium extension (Phase 5)
- **Five checkout bar templates called `pztpro_get_setting()` with no guard at
  all** — `metro`, `nightpie`, `plainlist`, `pocketpie` and `rustic`, on the
  lines reading `max_quantity`, `enable_order_notes` and
  `order_note_placeholder`. Including any of those files on a site without the
  premium extension raised a fatal "call to undefined function". It had not
  surfaced in practice only because those files were, until now, included
  exclusively by premium code. All eight bars now render correctly with nothing
  else installed, verified by rendering each one in isolation with no premium
  functions or classes defined.

### Changed — one compatibility seam instead of forty call sites
- **New `PizzaTier\Compat\AddonBridge`.** Everything PizzaTier might want from a
  premium extension now goes through this single class, which asks via filters
  and returns a safe default when nobody answers. Three global helpers wrap it
  for template use: `pzt_addon_setting()`, `pzt_addon_sizes()` and
  `pzt_has_pricing_addon()`.
- New extension points: `pizzatier_addon_setting`, `pizzatier_addon_sizes`,
  `pizzatier_has_pricing_addon`.
- All eight `checkout-bar.php` templates now read their settings through
  `pzt_addon_setting()`.
- All eight `pztp-containers-menu.php` size-grid helpers now resolve sizes
  through `pzt_addon_sizes()` instead of constructing a premium price-grid class.
- The Dashboard upgrade prompt, the Settings upgrade notice and the Site
  Migration screen detect a premium extension through the bridge rather than by
  testing for a hard-coded class name.

### Notes
- **The dependency now points one way only:** extensions know about PizzaTier,
  PizzaTier does not know about extensions. Nothing in PizzaTier changes
  behaviour based on a premium class being present; the bridge only decides
  whether to *offer* optional pricing UI and upgrade prompts.
- Two guarded back-compat paths remain inside `AddonBridge`, and nowhere else.
  PizzaTier Pro 1.8.4 and earlier expose a global settings function and a price
  grid class rather than hooking the filters above, and a site mid-upgrade would
  otherwise silently lose its saved settings and size selector. Both paths are
  clearly marked and can be deleted once those releases are out of circulation.
- The `pztpro-*` CSS class names and DOM ids in the checkout bar markup are
  **deliberately unchanged**. They are the shared contract that the premium
  stylesheet and cart script bind to; renaming them would break every installed
  copy of the extension for no functional gain. They are naming, not dependency —
  the markup renders identically with nothing else installed.
- Historical comments in `Settings.php` recording where pricing options moved in
  1.2.0 are left in place. They document a real migration and removing them would
  lose information for anyone tracing an old option key.

---

### Added — Native pizza orders (Phase 4: private customer notes)
- **Private, staff-only notes about customers**, stored as user meta under
  `_pzt_customer_private_notes`. These are notes the store writes *about* a
  customer — "buzzer is broken, call on arrival", "disputed an order in March" —
  and they are deliberately kept off every customer-facing surface.
- **On the user profile**, under a "PizzaTier — Store Notes" heading, alongside a
  list of that customer's five most recent orders linking straight to each order
  detail screen.
- **On the order detail screen**, in a visually distinct card, so staff can read
  and update a customer's notes without leaving the order they are working on.
- **On the Users list**, a Store Notes column showing a short preview that links
  to the full note. Only a preview is rendered, so a long or sensitive note is
  not broadcast across a shared back-office display.
- **Guest orders are matched to accounts by email address.** When someone who
  normally has an account orders without logging in, their notes still surface,
  and the order screen says the match was made by email rather than implying the
  customer was signed in.

### Security
- Three independent measures keep these notes contained:
  1. The meta key is underscore-prefixed, so WordPress treats it as protected —
     it never appears in the Custom Fields box and is not exposed over REST.
  2. Every public read and write path is gated on the orders capability;
     `CustomerNotes::get()` returns an empty string for anyone without it, so a
     stray call from a theme or template cannot leak the contents.
  3. The profile field only renders for users holding that capability, so a
     customer visiting their own profile never sees notes written about them.
- The order screen re-derives the customer's user ID from the order and checks it
  against the submitted form field, so a tampered request cannot write notes onto
  an unrelated account.
- Notes are capped at 5,000 characters, using `mb_substr()` where available and
  falling back to `substr()` because mbstring is not guaranteed on shared hosting.

### Notes
- Private notes are removed on uninstall under the same opt-in that governs order
  history, rather than lingering as orphaned user meta.
- These notes are **not** included in WordPress's personal-data export or erasure
  tools. They are store-internal records, but they are also personal data about
  an identifiable person, so whether they belong in a subject-access response is
  a judgement call that depends on jurisdiction. Nothing here prevents adding
  exporter and eraser callbacks later.

---

### Added — Native pizza orders (Phase 3: back-end management)
- **Pizza Orders admin screen** under the PizzaTier menu, with an
  awaiting-attention bubble on the menu label counting orders in an open status
  (New, Confirmed, Preparing, Ready, Out for Delivery).
- **Orders list** built on `WP_List_Table`: order number, relative time for
  anything placed in the last day, customer with click-to-call and mailto links,
  a preview of the pizzas ordered, fulfilment method with address or table,
  total, and a colour-coded status pill. Sortable by number and date, filterable
  by status with live counts, and searchable across the order number, customer
  name, phone, email and delivery address.
- **Bulk actions** for every status change, plus Trash. A Trash view with
  Restore and Delete permanently appears once anything is in it.
- **Order detail screen** showing the whole record: each pizza with every layer,
  its coverage fraction and a link to the source content item, per-pizza notes,
  totals, the customer's own note, contact details with a link to their profile,
  fulfilment details, and where the order came from.
- **Status management** with an optional reason, recorded in a timestamped
  history log alongside internal staff notes. Staff notes are clearly labelled
  as never appearing on receipts or in customer email.
- **Ordering Settings** screen covering the master switch, button label, login
  requirement, starting status, fulfilment methods offered, requested time, size
  picker, which contact fields are required, notes and quantity limits, store
  notification address, confirmation message, rate limit, and the opt-in for
  deleting order history on uninstall.

### Changed
- The bare `pizzatier_order` edit and list screens now redirect to the
  purpose-built order views. The post type only supports `title`, so the default
  editor had nothing useful to show.
- The new-order notification email links straight to the order detail screen.

### Fixed
- Restoring an order from the trash now returns it to the status it held before
  trashing. Since WordPress 5.6 an untrashed post is restored to `draft` unless a
  filter says otherwise, and `draft` is not a valid order status — a restored
  order would have vanished from every view.
- Removed a duplicate bulk-action nonce field on the orders list.
  `WP_List_Table` emits `bulk-pizza_orders` itself.

### Notes
- Orders settings live on the Orders screen rather than as a tab on the main
  Settings page. The ordering feature is self-contained, and keeping it out of
  the 1,300-line Settings monolith avoids destabilising unrelated options.
- Every order query passes an explicit status list. The `pzt-*` statuses are
  registered with `exclude_from_search => true`, so a `post_status => 'any'`
  query would silently match nothing.
- Money is formatted with a small symbol map for common currencies, falling back
  to a trailing currency code. PizzaTier does not price pizzas, so where no total
  has been recorded the list shows the pizza count instead of a misleading 0.00.

---

### Added — Native pizza orders (Phase 2: front-end checkout)
- **Order bar in the builder.** PizzaTier now renders its own order bar into the
  builder action-bar area, using the `pizzatier_builder_action_bar` hook that all
  eight bundled templates already fire — no template markup was changed. The bar
  shows a live summary of the pizza, an optional quantity stepper, and the
  call-to-action.
- **Checkout panel** printed once per page in `wp_footer`, so the dialog is never
  trapped inside an overflow-constrained builder column. Covers review of the
  built pizza, an optional size picker (shown when Size posts exist), fulfilment
  method with conditional delivery-address and dine-in table fields, requested
  time, contact details, and a notes field for kitchen instructions.
- **Partial override chain** for both the bar and the panel:
  child theme → parent theme → `templates/{slug}/` → bundled default. Filterable
  via `pizzatier_orders_partial_candidates`.
- **`OrderSettings`** — one home for every ordering option, with defaults defined
  in a single place so the front end and the Settings screen cannot drift apart.
- **`pizzatier-orders.js`** reads the current pizza from `window.PizzaTierAPI`.
  Three different state shapes ship across the bundled templates and the
  normaliser flattens all of them: the standard `{crust, sauce, …, toppings:{}}`
  object, PlainList's `{exclusive:{…}}` grouping, and Command Center's
  `{selections:{…}}` wrapper with toppings as an array and the cut layer named
  `slicing`.
- **Styling** in `assets/css/pizzatier-orders.css`, driven entirely by custom
  properties so a template stylesheet can restyle the whole bar and panel by
  redefining a handful of variables under its own `--{slug}` modifier class.
  Includes a mobile full-screen panel layout and a `prefers-reduced-motion` rule.

### Security
- Order submissions are nonce-verified, honeypot-screened, and rate-limited per
  IP (configurable, default 10/hour, backed by a transient keyed on a salted
  hash so no raw address is written to the options table).
- **Nothing the client sends is trusted for display or pricing.** Layer names,
  post IDs, size labels and diameters are all re-resolved from the CPTs
  server-side using only the submitted slugs; unknown slugs and unknown layer
  types are dropped rather than recorded, and coverage fractions are checked
  against the site's enabled list. A tampered payload cannot invent an item,
  rename one on the kitchen ticket, or attach a price.
- The honeypot returns a success-shaped response so a bot learns nothing from
  the difference between acceptance and rejection.
- An optional plain-text notification email is sent to the store on each new
  order; it deliberately omits internal staff notes.

### Fixed
- Order note truncation no longer calls `mb_substr()` directly. The mbstring
  extension is not guaranteed on shared hosting, so truncation now prefers it
  when present and falls back to `substr()` otherwise.

---

### Changed
- `Core\Activator` seeds the order-number sequence and registers the order post
  type and statuses during activation, so `flush_rewrite_rules()` sees them.

### Notes
- **Uninstall is non-destructive for orders.** Order records are customer
  transaction data, so they survive plugin removal unless the site explicitly
  opts in via `pizzatier_setting_delete_orders_on_uninstall`. The cleanup query
  reads the `pizzatier_order` rows directly, because the `pzt-*` statuses are
  not registered while `uninstall.php` runs and a `post_status => 'any'` query
  would silently match nothing.
- Order meta uses the brand-neutral `_pzt_order_*` prefix and carries a
  `_pzt_order_schema` version for future migrations.

---

## [1.16.0] - 2026-07-16

### Security / hardening (WordPress.org review round)
- **Interactive builder output is now escaped at the output boundary.** The per-template layer-card grids (`crusts_html`, `sauces_html`, `cheeses_html`, `toppings_html`, `drizzles_html`, `cuts_html`, chip/section markup) and the initial pizza preview from `PizzaBuilder::build_dynamic()` are escaped at output with core `wp_kses()`, using an allowlist (`pzt_card_allowed_html()`, filterable via `pizzatier_card_kses`) covering exactly the markup the builder emits (interactive elements, form controls, ARIA state, `data-*` hooks and inline SVG icons, plus the `onclick`/`onkeydown` handlers the cards use). `<script>`, `<style>`, `<iframe>`, `<object>`, `<form>` and any unlisted attribute are stripped. The allowlist is filterable via `pizzatier_card_kses` for Pro and third-party templates.
- **Output-buffer hardening.** Every buffered card function now wraps its `ob_start()`…capture region in `try { … } finally { ob_end_clean(); }` within the same function scope, so the buffer close cannot be bypassed by a `pizzatier_before_layer_card` / `pizzatier_after_layer_card` hook. Hook output remains inside the buffer, preserving render order.
- **Namespaced JS global.** The Layer Image Maker's localized config global was renamed `plimConfig` → `pizzatierLimConfig` (updated in `AssetManager`, the JS reader, and the doc comment).
- **Site Import upload sanitization.** `SiteMigration::handle_import_upload()` now reads each `$_FILES` member with the appropriate sanitizer at the point of use (error code cast, `is_uploaded_file()`, size cap, `sanitize_file_name()`, `wp_unslash()` on `tmp_name`/`name`).

### Changed
- Regenerated `languages/pizzatier.pot` from current source and reconciled the bundled `de_DE` / `es_ES` translations with `msgmerge`; removed msgids left over from the Custom CSS/JS fields retired in 1.14.0.

### Notes
- The `pizzatier_import_payload` action contract is unchanged: each consumer (including PizzaTier Pro) remains responsible for sanitizing its own namespaced section, which Pro does against its settings allowlist. No PizzaTier Pro code changes are required for compatibility with this release.

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
