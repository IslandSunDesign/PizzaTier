<?php
namespace PizzaTier\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Runs the work that has to happen once, when the installed version changes.
 *
 * Activation hooks are not enough on their own: WordPress does not fire them on
 * an update, whether that update came from the updater, from WP-CLI, or from
 * someone replacing the directory over FTP. Comparing a stored version against
 * the running one catches all of those.
 *
 * Steps are keyed by the version that introduced them and run in order, so a
 * site jumping several releases at once still gets each step exactly once.
 *
 * @since 2.0.0
 */
final class Upgrade {

	/** Option holding the version whose upgrade steps have run. */
	const VERSION_OPTION = 'pizzatier_db_version';

	/** Flag set when a site's "both" behaviour changed under 2.1.0. */
	const NOTICE_OPTION = 'pizzatier_route_change_notice';

	public function register(): void {
		// admin_init rather than plugins_loaded: an upgrade step may need the
		// full admin API, and there is no reason to do this work on front-end
		// requests. Late enough that post types are registered.
		add_action( 'admin_init', [ $this, 'maybe_upgrade' ] );

		// Priority 1 so a dismissal is processed before the notice renders.
		add_action( 'admin_init', [ $this, 'maybe_dismiss_route_notice' ], 1 );
		add_action( 'admin_notices', [ $this, 'maybe_show_route_notice' ] );
	}

	/**
	 * Compare the stored version against the running one and run what is due.
	 */
	public function maybe_upgrade(): void {
		$from = (string) get_option( self::VERSION_OPTION, '' );
		$to   = (string) PIZZATIER_VERSION;

		if ( $from === $to ) {
			return;
		}

		if ( '' === $from ) {
			// No stored version means one of two very different things: a fresh
			// install, or an upgrade from a release that predates version
			// tracking — everything before 2.0.0. Telling them apart matters,
			// because the second still needs its steps run. Existing settings
			// are the signal: a genuinely fresh install has none.
			if ( false === get_option( 'pizzatier_setting_global_template', false ) ) {
				$this->record( $to );
				return;
			}

			// Oldest version whose steps could still be outstanding.
			$from = '0.0.1';
		}

		foreach ( $this->steps() as $introduced_in => $callback ) {
			if ( version_compare( $from, $introduced_in, '<' ) ) {
				$callback();
			}
		}

		$this->record( $to );
	}

	/**
	 * Upgrade steps, keyed by the version that introduced them.
	 *
	 * Keep them ordered oldest first, and keep each one safe to run against a
	 * site that is already in the target state — a step that has to be correct
	 * only once is a step that eventually is not.
	 *
	 * @return array<string,callable>
	 */
	private function steps(): array {
		return [
			'2.0.0' => [ $this, 'step_200' ],
			'2.0.5' => [ $this, 'step_205' ],
			'2.1.0' => [ $this, 'step_210' ],
		];
	}

	/**
	 * 2.0.0 — the merge.
	 *
	 * Post types, taxonomies and rewrite rules all moved during the merge, and
	 * a stale rewrite cache shows up as 404s on archive URLs. Everything else
	 * this release needs — converting the database keys inherited from the
	 * previous naming — is deliberately not here: it belongs to the separate
	 * key migrator, which can survey first, work in batches, and be re-run.
	 */
	private function step_200(): void {
		flush_rewrite_rules( false );
	}

	/**
	 * 2.0.5 — index existing orders by customer email.
	 *
	 * The personal-data exporter and eraser find orders by address. Orders
	 * placed before this release only carry the email inside the serialised
	 * customer block, which cannot be queried, so it is copied out to a flat
	 * key here. Idempotent: re-running simply rewrites the same values.
	 */
	private function step_205(): void {
		if ( ! class_exists( '\\PizzaTier\\Orders\\OrderStatuses' ) ) { return; }

		$statuses = array_keys( \PizzaTier\Orders\OrderStatuses::all() );
		if ( ! $statuses ) { return; }

		$paged = 1;

		do {
			$order_ids = (array) get_posts( [
				'post_type'      => \PizzaTier\Orders\OrderPostType::POST_TYPE,
				'post_status'    => $statuses,
				'posts_per_page' => 200,
				'paged'          => $paged,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			] );

			foreach ( $order_ids as $order_id ) {
				$customer = get_post_meta( (int) $order_id, \PizzaTier\Orders\Order::META_CUSTOMER, true );
				$email    = is_array( $customer ) && ! empty( $customer['email'] ) ? (string) $customer['email'] : '';

				if ( '' !== $email && is_email( $email ) ) {
					update_post_meta( (int) $order_id, \PizzaTier\Orders\Privacy::META_EMAIL_INDEX, strtolower( $email ) );
				}
			}

			$paged++;
		} while ( count( $order_ids ) === 200 && $paged < 100 );
	}

	/**
	 * 2.1.0 — pin the order route.
	 *
	 * OrderRoute can derive a route from the pre-2.1.0 `action_bar_mode`, so a
	 * site works correctly whether or not this runs. What it cannot do is stop
	 * deriving: a site left on the derived value would move again the next time
	 * the fallback logic changed. Writing the resolved value once turns an
	 * inference into the store's own recorded decision.
	 *
	 * The "both" case additionally changed meaning. It used to draw two bars and
	 * let the customer choose between the cart and a recorded order; it now
	 * draws one button that does both. That is a real change to what a customer
	 * sees, so those sites get a notice rather than a silent migration.
	 *
	 * Idempotent: a site that already has an explicit route is left alone.
	 */
	private function step_210(): void {
		if ( ! class_exists( '\\PizzaTier\\Orders\\OrderRoute' ) ) { return; }

		$key      = \PizzaTier\Orders\OrderSettings::PREFIX . \PizzaTier\Orders\OrderRoute::KEY;
		$existing = (string) get_option( $key, '' );

		if ( '' !== $existing && \PizzaTier\Orders\OrderRoute::is_valid( $existing ) ) {
			return;
		}

		$resolved = \PizzaTier\Orders\OrderRoute::get();
		update_option( $key, $resolved );

		if ( \PizzaTier\Orders\OrderRoute::BOTH === $resolved ) {
			add_option( self::NOTICE_OPTION, '1', '', false );
		}
	}

	/**
	 * One-time notice for sites whose "both" behaviour changed under 2.1.0.
	 */
	public function maybe_show_route_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		if ( '1' !== (string) get_option( self::NOTICE_OPTION, '' ) ) { return; }

		$dismiss = wp_nonce_url(
			add_query_arg( 'pizzatier_dismiss_route_notice', '1' ),
			'pizzatier_dismiss_route_notice'
		);

		$settings = admin_url( 'admin.php?page=pizzatier-orders&view=settings' );

		echo '<div class="notice notice-info is-dismissible"><p>';
		echo esc_html__(
			'PizzaTier 2.1 changed what "both" ordering means. Your builder used to show two buttons and let the customer pick between the cart and a recorded order. It now shows one button that records the order and adds it to the cart.',
			'pizzatier'
		);
		echo ' <a href="' . esc_url( $settings ) . '">' . esc_html__( 'Review your order routing', 'pizzatier' ) . '</a>';
		echo ' &middot; <a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'pizzatier' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * Clear the notice flag. Hooked early on admin_init so the notice is gone on
	 * the same request the link is followed.
	 */
	public function maybe_dismiss_route_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce is verified below; this only decides whether to look.
		if ( ! isset( $_GET['pizzatier_dismiss_route_notice'] ) ) { return; }
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This *is* the nonce read.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'pizzatier_dismiss_route_notice' ) ) { return; }

		delete_option( self::NOTICE_OPTION );
	}

	/**
	 * Store the version whose steps have now run.
	 *
	 * Written with autoload off: it is read once per admin request and never on
	 * the front end, so there is no reason for it to sit in the options cache
	 * on every page load.
	 */
	private function record( string $version ): void {
		if ( false === get_option( self::VERSION_OPTION, false ) ) {
			add_option( self::VERSION_OPTION, $version, '', false );
			return;
		}

		update_option( self::VERSION_OPTION, $version, false );
	}
}
