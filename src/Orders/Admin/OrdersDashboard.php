<?php
namespace PizzaTier\Orders\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use PizzaTier\Orders\Order;
use PizzaTier\Orders\OrderSettings;
use PizzaTier\Orders\OrderStatuses;

/**
 * Renders the Pizza Orders dashboard — the default view of the top-level
 * Pizza Orders page.
 *
 * The markup here is an application shell: static chrome plus empty containers
 * that orders-dashboard.js fills and keeps fresh over admin-ajax. Anything a
 * shop owner acts on (count cards, incoming board, list, drawer) lives in JS
 * so the screen never needs a full page reload during service.
 *
 * A <noscript> block points at the classic server-rendered list so the page
 * still works with JavaScript off.
 */
class OrdersDashboard {

	public function render(): void {
		$settings_url = OrdersPage::list_url( [ 'view' => 'settings' ] );
		$classic_url  = OrdersPage::list_url( [ 'view' => 'classic' ] );
		$accepting    = OrderSettings::is_on( 'enabled' );
		?>

		<div class="pzt-orders-header">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Pizza Orders', 'pizzatier' ); ?></h1>

			<span id="pzt-dash-accepting"
			      class="pzt-dash-accepting <?php echo $accepting ? 'is-on' : 'is-off'; ?>"
			      title="<?php esc_attr_e( 'Whether the site is currently accepting new orders. Change it in Ordering Settings.', 'pizzatier' ); ?>">
				<span class="pzt-dash-accepting__dot"></span>
				<span class="pzt-dash-accepting__text">
					<?php $accepting ? esc_html_e( 'Accepting orders', 'pizzatier' ) : esc_html_e( 'Ordering is OFF', 'pizzatier' ); ?>
				</span>
			</span>

			<span class="pzt-dash-header-actions">
				<button type="button" id="pzt-dash-sound" class="button pzt-dash-toggle" aria-pressed="false"
				        title="<?php esc_attr_e( 'Play a chime when a new order arrives', 'pizzatier' ); ?>">
					🔔 <span class="pzt-dash-toggle__state"><?php esc_html_e( 'Sound off', 'pizzatier' ); ?></span>
				</button>
				<button type="button" id="pzt-dash-live" class="button pzt-dash-toggle is-on" aria-pressed="true"
				        title="<?php esc_attr_e( 'Automatically check for new orders every few seconds', 'pizzatier' ); ?>">
					<span class="pzt-dash-live-dot"></span>
					<span class="pzt-dash-toggle__state"><?php esc_html_e( 'Live', 'pizzatier' ); ?></span>
				</button>
				<a href="<?php echo esc_url( $settings_url ); ?>" class="page-title-action">
					<?php esc_html_e( 'Ordering Settings', 'pizzatier' ); ?>
				</a>
			</span>
		</div>

		<noscript>
			<div class="notice notice-warning">
				<p>
					<?php esc_html_e( 'The live dashboard needs JavaScript.', 'pizzatier' ); ?>
					<a href="<?php echo esc_url( $classic_url ); ?>"><?php esc_html_e( 'Use the classic order list instead', 'pizzatier' ); ?></a>
				</p>
			</div>
		</noscript>

		<div id="pzt-dash-toasts" class="pzt-dash-toasts" aria-live="polite"></div>

		<!-- ── Count cards ─────────────────────────────────────────────── -->
		<div id="pzt-dash-cards" class="pzt-dash-cards" role="tablist"
		     aria-label="<?php esc_attr_e( 'Order counts by status. Select one to filter the list.', 'pizzatier' ); ?>">
			<?php // Cards are rendered server-side once so the row has shape before the first snapshot lands. ?>
			<button type="button" class="pzt-dash-card is-current" data-status="" style="--pzt-card:#50575e">
				<span class="pzt-dash-card__count">–</span>
				<span class="pzt-dash-card__label"><?php esc_html_e( 'All', 'pizzatier' ); ?></span>
			</button>
			<button type="button" class="pzt-dash-card pzt-dash-card--open" data-status="open" style="--pzt-card:#d63638">
				<span class="pzt-dash-card__count">–</span>
				<span class="pzt-dash-card__label"><?php esc_html_e( 'Needs attention', 'pizzatier' ); ?></span>
			</button>
			<?php foreach ( OrderStatuses::labels() as $status => $label ) : ?>
				<button type="button" class="pzt-dash-card" data-status="<?php echo esc_attr( $status ); ?>"
				        style="--pzt-card:<?php echo esc_attr( OrderStatuses::color( $status ) ); ?>">
					<span class="pzt-dash-card__count">–</span>
					<span class="pzt-dash-card__label"><?php echo esc_html( $label ); ?></span>
				</button>
			<?php endforeach; ?>
			<button type="button" class="pzt-dash-card pzt-dash-card--trash" data-status="trash" style="--pzt-card:#8a8f98; display:none;">
				<span class="pzt-dash-card__count">–</span>
				<span class="pzt-dash-card__label"><?php esc_html_e( 'Trash', 'pizzatier' ); ?></span>
			</button>
		</div>

		<!-- ── Today at a glance ───────────────────────────────────────── -->
		<div id="pzt-dash-today" class="pzt-dash-today">
			<h2 class="pzt-dash-section-title"><?php esc_html_e( 'Today at a glance', 'pizzatier' ); ?></h2>
			<div class="pzt-dash-today__stats">
				<div class="pzt-dash-stat"><span class="pzt-dash-stat__value" data-stat="orders">–</span><span class="pzt-dash-stat__label"><?php esc_html_e( 'Orders today', 'pizzatier' ); ?></span></div>
				<div class="pzt-dash-stat"><span class="pzt-dash-stat__value" data-stat="pizzas">–</span><span class="pzt-dash-stat__label"><?php esc_html_e( 'Pizzas', 'pizzatier' ); ?></span></div>
				<div class="pzt-dash-stat"><span class="pzt-dash-stat__value" data-stat="revenue">–</span><span class="pzt-dash-stat__label"><?php esc_html_e( 'Revenue', 'pizzatier' ); ?></span></div>
				<div class="pzt-dash-stat"><span class="pzt-dash-stat__value" data-stat="average">–</span><span class="pzt-dash-stat__label"><?php esc_html_e( 'Average order', 'pizzatier' ); ?></span></div>
				<div class="pzt-dash-stat"><span class="pzt-dash-stat__value" data-stat="methods">–</span><span class="pzt-dash-stat__label"><?php esc_html_e( 'Pickup / Delivery', 'pizzatier' ); ?></span></div>
			</div>
		</div>

		<!-- ── Incoming board ──────────────────────────────────────────── -->
		<div class="pzt-dash-incoming">
			<h2 class="pzt-dash-section-title">
				<?php esc_html_e( 'Incoming orders', 'pizzatier' ); ?>
				<span class="pzt-dash-section-title__hint"><?php esc_html_e( 'oldest first — work from the left', 'pizzatier' ); ?></span>
			</h2>
			<div id="pzt-dash-incoming" class="pzt-dash-incoming__track">
				<p class="pzt-dash-loading"><?php esc_html_e( 'Loading…', 'pizzatier' ); ?></p>
			</div>
		</div>

		<!-- ── List ────────────────────────────────────────────────────── -->
		<div class="pzt-dash-list">
			<h2 class="pzt-dash-section-title"><?php esc_html_e( 'All orders', 'pizzatier' ); ?></h2>

			<div class="pzt-dash-filters">
				<input type="search" id="pzt-dash-search" class="pzt-dash-search"
				       placeholder="<?php esc_attr_e( 'Search order #, name, phone, email…', 'pizzatier' ); ?>" />

				<select id="pzt-dash-fulfillment">
					<option value=""><?php esc_html_e( 'All methods', 'pizzatier' ); ?></option>
					<?php foreach ( Order::fulfillment_methods() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<label class="pzt-dash-datelabel">
					<span><?php esc_html_e( 'From', 'pizzatier' ); ?></span>
					<input type="date" id="pzt-dash-date-from" />
				</label>
				<label class="pzt-dash-datelabel">
					<span><?php esc_html_e( 'To', 'pizzatier' ); ?></span>
					<input type="date" id="pzt-dash-date-to" />
				</label>

				<button type="button" id="pzt-dash-clear" class="button"><?php esc_html_e( 'Clear filters', 'pizzatier' ); ?></button>

				<span id="pzt-dash-result-count" class="pzt-dash-result-count"></span>
			</div>

			<div id="pzt-dash-bulkbar" class="pzt-dash-bulkbar" hidden>
				<span class="pzt-dash-bulkbar__count"></span>
				<select id="pzt-dash-bulk-op">
					<option value=""><?php esc_html_e( 'Bulk action…', 'pizzatier' ); ?></option>
					<?php foreach ( OrderStatuses::labels() as $status => $label ) : ?>
						<option value="status_<?php echo esc_attr( $status ); ?>">
							<?php
							/* translators: %s: order status label. */
							printf( esc_html__( 'Mark as %s', 'pizzatier' ), esc_html( $label ) );
							?>
						</option>
					<?php endforeach; ?>
					<option value="trash"><?php esc_html_e( 'Move to Trash', 'pizzatier' ); ?></option>
					<option value="untrash"><?php esc_html_e( 'Restore from Trash', 'pizzatier' ); ?></option>
					<option value="delete"><?php esc_html_e( 'Delete permanently', 'pizzatier' ); ?></option>
				</select>
				<button type="button" id="pzt-dash-bulk-apply" class="button"><?php esc_html_e( 'Apply', 'pizzatier' ); ?></button>
			</div>

			<table class="wp-list-table widefat fixed striped pzt-dash-table">
				<thead>
					<tr>
						<td class="check-column"><input type="checkbox" id="pzt-dash-check-all" /></td>
						<th class="pzt-dash-sort" data-sort="number"><?php esc_html_e( 'Order', 'pizzatier' ); ?></th>
						<th class="pzt-dash-sort is-sorted-desc" data-sort="date"><?php esc_html_e( 'Placed', 'pizzatier' ); ?></th>
						<th class="pzt-dash-sort" data-sort="customer"><?php esc_html_e( 'Customer', 'pizzatier' ); ?></th>
						<th><?php esc_html_e( 'Pizzas', 'pizzatier' ); ?></th>
						<th><?php esc_html_e( 'Method', 'pizzatier' ); ?></th>
						<th class="pzt-dash-sort" data-sort="total"><?php esc_html_e( 'Total', 'pizzatier' ); ?></th>
						<th class="pzt-dash-sort" data-sort="status"><?php esc_html_e( 'Status', 'pizzatier' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'pizzatier' ); ?></th>
					</tr>
				</thead>
				<tbody id="pzt-dash-rows">
					<tr><td colspan="9" class="pzt-dash-loading"><?php esc_html_e( 'Loading…', 'pizzatier' ); ?></td></tr>
				</tbody>
			</table>

			<div class="pzt-dash-pagination">
				<button type="button" id="pzt-dash-prev" class="button" disabled>‹ <?php esc_html_e( 'Previous', 'pizzatier' ); ?></button>
				<span id="pzt-dash-page-info"></span>
				<button type="button" id="pzt-dash-next" class="button" disabled><?php esc_html_e( 'Next', 'pizzatier' ); ?> ›</button>
			</div>

			<p class="pzt-orders-muted">
				<a href="<?php echo esc_url( $classic_url ); ?>"><?php esc_html_e( 'Prefer the classic list? Open it here.', 'pizzatier' ); ?></a>
			</p>
		</div>

		<!-- ── Quick-view drawer ───────────────────────────────────────── -->
		<div id="pzt-dash-drawer-backdrop" class="pzt-dash-drawer-backdrop" hidden></div>
		<aside id="pzt-dash-drawer" class="pzt-dash-drawer" hidden
		       role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Order details', 'pizzatier' ); ?>">
			<div class="pzt-dash-drawer__inner">
				<p class="pzt-dash-loading"><?php esc_html_e( 'Loading…', 'pizzatier' ); ?></p>
			</div>
		</aside>

		<!-- Print target for kitchen tickets; filled by JS just before window.print(). -->
		<div id="pzt-dash-print" class="pzt-dash-print" aria-hidden="true"></div>

		<?php
	}
}
