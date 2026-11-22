<?php
namespace PizzaTier\Core;

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Atomic fixed-window rate limiter for the public endpoints (2.4.3).
 *
 * Same technique as the order limiter in Orders\OrderSubmission: one atomic
 * increment per request, so a burst of parallel requests cannot all read the
 * same count. With a persistent object cache it uses wp_cache_incr();
 * otherwise it upserts the transient's own option row in one statement.
 *
 * The REST render limiter it replaces read a transient, compared, then wrote
 * it back, so concurrent requests slipped through.
 *
 * PHP 7.4 compatible.
 *
 * @since 2.4.3
 */
final class RateLimiter {

	/**
	 * Count one request against a bucket and say whether it is allowed.
	 *
	 * @param string $bucket Short name for the endpoint, e.g. 'price'.
	 * @param int    $max    Requests allowed per window. 0 disables the limit.
	 * @param int    $window Window length in seconds.
	 */
	public static function hit( string $bucket, int $max, int $window ): bool {
		if ( $max <= 0 || $window <= 0 ) {
			return true;
		}

		// One count per request and bucket. The REST server calls permission
		// callbacks a second time to build the Allow header, which would
		// otherwise halve the effective limit.
		static $decided = [];
		if ( isset( $decided[ $bucket ] ) ) {
			return $decided[ $bucket ];
		}
		$decided[ $bucket ] = self::count( $bucket, $max, $window );
		return $decided[ $bucket ];
	}

	private static function count( string $bucket, int $max, int $window ): bool {
		$client = self::client_key();
		if ( '' === $client ) {
			return true; // Cannot identify the caller; do not block real traffic.
		}

		// The window index is part of the key, so each window starts at zero.
		$slot = (int) floor( time() / $window );
		$key  = 'pzt_rl_' . sanitize_key( $bucket ) . '_' . md5( $client . '|' . $slot . '|' . wp_salt( 'nonce' ) );

		if ( wp_using_ext_object_cache() ) {
			$group = 'pizzatier_rate_limit';
			wp_cache_add( $key, 0, $group, $window );
			$count = wp_cache_incr( $key, 1, $group );
			if ( false !== $count ) {
				return (int) $count <= $max;
			}
		}

		global $wpdb;
		get_transient( $key ); // Purges an expired row.
		$value_name   = '_transient_' . $key;
		$timeout_name = '_transient_timeout_' . $key;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic counter; the options cache is cleared below.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)",
				$timeout_name,
				(string) ( ( $slot + 1 ) * $window ),
				'no'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)
				 ON DUPLICATE KEY UPDATE option_value = CAST( option_value AS UNSIGNED ) + 1",
				$value_name,
				'1',
				'no'
			)
		);
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $value_name ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		wp_cache_delete( $value_name, 'options' );
		wp_cache_delete( $timeout_name, 'options' );

		return null === $count ? true : (int) $count <= $max;
	}

	/**
	 * The caller's address, IPv6 grouped by /64 (one subscriber), or ''.
	 *
	 * REMOTE_ADDR only; sites behind a proxy supply the real address through
	 * the 'pizzatier_client_ip' filter.
	 */
	public static function client_key(): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$remote = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';

		/**
		 * Filter the client address used by PizzaTier's public rate limits.
		 *
		 * @since 2.4.3
		 *
		 * @param string $ip Validated REMOTE_ADDR, or ''.
		 */
		$ip = trim( (string) apply_filters( 'pizzatier_client_ip', $remote ) );
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		if ( false !== strpos( $ip, ':' ) && function_exists( 'inet_pton' ) ) {
			$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Validated above.
			if ( false !== $packed && 16 === strlen( $packed ) ) {
				return bin2hex( substr( $packed, 0, 8 ) ) . '::/64';
			}
		}
		return $ip;
	}
}
