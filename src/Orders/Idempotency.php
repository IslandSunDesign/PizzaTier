<?php
namespace PizzaTier\Orders;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * One order per checkout attempt.
 *
 * The checkout panel sends a random key with each attempt and re-sends the
 * same key when it retries after a lost response. The first request to claim
 * the key places the order. A retry with that key while the order is being
 * placed gets "in progress", and a retry afterwards gets the original response
 * back, so the kitchen sees one ticket and the store gets one email (2.4.3).
 *
 * Keys are scoped to the requester (account, or hashed address for guests),
 * so one visitor cannot read another's stored response by guessing a key.
 *
 * Storage is a transient, claimed atomically: wp_cache_add() with a persistent
 * object cache, otherwise an INSERT IGNORE on the transient's own option row.
 *
 * PHP 7.4 compatible.
 *
 * @since 2.4.3
 */
final class Idempotency {

	const PREFIX = 'pzt_idem_';

	/** How long a claim may stay "pending" before a retry may try again. */
	const PENDING_TTL = 120;

	/** How long a finished attempt's response is replayed. */
	const DONE_TTL = DAY_IN_SECONDS;

	/**
	 * The scoped transient key for this request, or '' when the client sent
	 * no usable key (older cached front-end scripts): those requests are
	 * processed exactly as before.
	 */
	public static function key_from_request( string $scope ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The order handler verifies the nonce before calling this.
		$raw = isset( $_POST['idempotency_key'] ) ? sanitize_text_field( wp_unslash( $_POST['idempotency_key'] ) ) : '';
		if ( ! preg_match( '/^[A-Za-z0-9_-]{16,64}$/', $raw ) ) {
			return '';
		}
		// md5 keeps the option name well under the 191-character limit.
		return self::PREFIX . md5( $scope . '|' . $raw );
	}

	/**
	 * @return array{state:string,response?:array}|null
	 */
	public static function lookup( string $key ): ?array {
		if ( '' === $key ) {
			return null;
		}
		$value = get_transient( $key );
		return is_array( $value ) && isset( $value['state'] ) ? $value : null;
	}

	/** Atomically claim a key. False when another request already holds it. */
	public static function claim( string $key ): bool {
		if ( '' === $key ) {
			return true;
		}
		$pending = [ 'state' => 'pending' ];

		if ( wp_using_ext_object_cache() ) {
			// set_transient() stores in the 'transient' group under the bare key.
			return (bool) wp_cache_add( $key, $pending, 'transient', self::PENDING_TTL );
		}

		global $wpdb;

		// Purges the row first when an earlier claim has expired.
		get_transient( $key );

		$value_name   = '_transient_' . $key;
		$timeout_name = '_transient_timeout_' . $key;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic claim; the options caches are cleared below.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
				$value_name,
				maybe_serialize( $pending ),
				'no'
			)
		);
		$claimed = ( 1 === (int) $wpdb->rows_affected );
		if ( $claimed ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)
					 ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
					$timeout_name,
					(string) ( time() + self::PENDING_TTL ),
					'no'
				)
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		wp_cache_delete( $value_name, 'options' );
		wp_cache_delete( $timeout_name, 'options' );
		$notoptions = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $notoptions ) ) {
			unset( $notoptions[ $value_name ], $notoptions[ $timeout_name ] );
			wp_cache_set( 'notoptions', $notoptions, 'options' );
		}

		return $claimed;
	}

	/** Record the finished attempt's response for replay. */
	public static function complete( string $key, array $response ): void {
		if ( '' === $key ) {
			return;
		}
		set_transient( $key, [ 'state' => 'done', 'response' => $response ], self::DONE_TTL );
	}

	/** Give a key back after a failure, so the customer can try again. */
	public static function release( string $key ): void {
		if ( '' !== $key ) {
			delete_transient( $key );
		}
	}
}
