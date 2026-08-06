<?php
namespace PizzaTier\Orders\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

use PizzaTier\Orders\OrderPostType;

/**
 * Registers Pizza Orders as its own top-level WordPress admin menu.
 *
 * The slug stays `pizzatier-orders` — the same slug the feature used as a
 * PizzaTier submenu — so every existing link keeps resolving unchanged:
 * order-notification emails, the Upgrade notice, the Settings screen button,
 * and any URL a shop owner has bookmarked.
 *
 * The menu label carries the same awaiting-attention bubble WordPress uses
 * for pending comments, showing how many orders still need staff action.
 */
class OrdersMenu {

	/** Sidebar position — just below the WooCommerce block (55.x). */
	const POSITION = '56.7';

	public function register(): void {
		// Priority 9: registered before AdminMenu (default 10) so its
		// link-style entry under PizzaTier can point here safely.
		add_action( 'admin_menu', [ $this, 'add_menu' ], 9 );
	}

	public function add_menu(): void {
		$label = __( 'Pizza Orders', 'pizzatier' );
		$open  = OrderPostType::open_count();

		if ( $open > 0 ) {
			$label .= ' <span class="awaiting-mod"><span class="pending-count">'
				. esc_html( number_format_i18n( $open ) )
				. '</span></span>';
		}

		add_menu_page(
			__( 'Pizza Orders', 'pizzatier' ),
			$label,
			OrderPostType::capability(),
			OrdersPage::SLUG,
			[ $this, 'render' ],
			self::icon(),
			self::POSITION
		);

		// Named first submenu so the auto-generated duplicate reads "Dashboard".
		add_submenu_page(
			OrdersPage::SLUG,
			__( 'Pizza Orders', 'pizzatier' ),
			__( 'Dashboard', 'pizzatier' ),
			OrderPostType::capability(),
			OrdersPage::SLUG,
			[ $this, 'render' ]
		);

		// Link-style entries (slugs containing `.php` become plain links, the
		// same pattern AdminMenu uses for CPT links) — no duplicate page hooks.
		add_submenu_page(
			OrdersPage::SLUG,
			__( 'Classic List', 'pizzatier' ),
			__( 'Classic List', 'pizzatier' ),
			OrderPostType::capability(),
			'admin.php?page=' . OrdersPage::SLUG . '&view=classic'
		);

		add_submenu_page(
			OrdersPage::SLUG,
			__( 'Ordering Settings', 'pizzatier' ),
			__( 'Ordering Settings', 'pizzatier' ),
			OrderPostType::capability(),
			'admin.php?page=' . OrdersPage::SLUG . '&view=settings'
		);
	}

	public function render(): void {
		( new OrdersPage() )->render();
	}

	/**
	 * Pizza-slice menu icon as a base64 SVG data URI.
	 *
	 * WordPress only recolors dashicons automatically, not SVG data URIs, so
	 * the glyph ships pre-filled in the sidebar's default gray (#a7aaad),
	 * matching the other inactive menu icons in every core color scheme.
	 */
	private static function icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">'
			. '<path fill="#a7aaad" d="M10 1c-.3 0-.6.15-.77.42L1.9 13.1c-.9 1.44-.6 2.9.3 3.9.9 1 2.4 1.5 4 1.5h7.6c1.6 0 3.1-.5 4-1.5.9-1 1.2-2.46.3-3.9L10.77 1.42A.92.92 0 0 0 10 1zm0 2.6 6.5 10.4c.4.7.3 1.2-.1 1.6-.4.5-1.3.9-2.6.9H6.2c-1.3 0-2.2-.4-2.6-.9-.4-.4-.5-.9-.1-1.6L10 3.6z"/>'
			. '<circle fill="#a7aaad" cx="10" cy="8.2" r="1.3"/>'
			. '<circle fill="#a7aaad" cx="7.8" cy="12.2" r="1.3"/>'
			. '<circle fill="#a7aaad" cx="12.2" cy="12.2" r="1.3"/>'
			. '</svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Standard mechanism for inline SVG admin menu icons.
	}
}
