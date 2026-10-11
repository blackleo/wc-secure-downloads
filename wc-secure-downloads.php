<?php
/**
 * Plugin Name: WC Secure Downloads
 * Description: Hardens WooCommerce digital downloads: rate-limits download attempts, blocks scraping/indexing of download URLs, and sends no-cache/no-sniff headers.
 * Version:     1.0.0
 * License:     GPL-2.0-or-later
 *
 * Install as a normal plugin or drop in wp-content/mu-plugins/.
 * Pair with the server rules in /server-config so files in
 * wp-content/uploads/woocommerce_uploads/ can never be fetched directly.
 */

defined( 'ABSPATH' ) || exit;

// Max download requests per IP per window (successful + failed). Filterable.
const WCSD_MAX_ATTEMPTS = 20;
const WCSD_WINDOW_SECS  = 600; // 10 minutes.

/** Is the current request a WooCommerce download request? */
function wcsd_is_download_request(): bool {
	return isset( $_GET['download_file'], $_GET['order'], $_GET['key'] ); // phpcs:ignore WordPress.Security.NonceVerification
}

/** Hashed client key so raw IPs are not stored. */
function wcsd_client_key(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	return 'wcsd_' . hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
}

/**
 * Run before WooCommerce's own handler (init priority 10).
 * WooCommerce still performs the real checks (order key + email/user + download
 * limit + expiry). This layer only makes guessing/brute-forcing impractical.
 */
add_action(
	'init',
	function () {
		if ( ! wcsd_is_download_request() ) {
			return;
		}

		$max    = (int) apply_filters( 'wcsd_max_attempts', WCSD_MAX_ATTEMPTS );
		$window = (int) apply_filters( 'wcsd_window_secs', WCSD_WINDOW_SECS );
		$key    = wcsd_client_key();
		$count  = (int) get_transient( $key );

		if ( $count >= $max ) {
			status_header( 429 );
			header( 'Retry-After: ' . $window );
			header( 'Cache-Control: no-store' );
			wp_die( esc_html__( 'Too many download attempts. Please wait a few minutes and try again.', 'wc-secure-downloads' ), '', array( 'response' => 429 ) );
		}

		set_transient( $key, $count + 1, $window );

		// Headers for the download response.
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
	},
	1
);

/** Keep download endpoints out of search engines and out of the sitemap. */
add_filter(
	'robots_txt',
	function ( $output ) {
		return $output . "\nDisallow: /*download_file=*\nDisallow: /wp-content/uploads/woocommerce_uploads/\n";
	}
);

/** Log blocked/failed attempts (no personal data) for the written security report. */
add_action(
	'woocommerce_download_product',
	function ( $email, $order_key, $product_id ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf( '[wcsd] download served product=%d', (int) $product_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	},
	10,
	3
);
