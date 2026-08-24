<?php
/**
 * Signed REST API for the central Security Dashboard to pull the 404 log
 * remotely, and to clear it.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KWPERF_Rest_Api
 *
 * There is no logged-in WordPress user on these requests — they come from
 * the dashboard's own server, not a browser — so authorization here is a
 * signature check against the dashboard's RSA public key instead of the
 * nonce + current_user_can() pattern the wp-admin AJAX handlers use. This is
 * the same private key (never present here, only its public half) that the
 * sibling KW Security plugin's signed routes already trust, reused rather
 * than minting a second one.
 */
class KWPERF_Rest_Api {

	/**
	 * PEM-encoded RSA public key. Safe to embed here: it can only verify
	 * signatures, never produce them — the matching private key stays on
	 * the dashboard's own server and is never shared with any site.
	 */
	const DASHBOARD_PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAjtG3XkYTGtr3YoN5/BgJ
OHXBKcHKaY90xyw/6zxRFTHxVwGGCGqm1MGhcx/9EHHPNKJzBTzFSrzUY46Pc9lE
KWD4CdJnmgDKNzNw5xJR2cjlsVDK+fABDh2GC23XztAc0o/2m0tr57Gm2Ivcnael
vu81LbCfysLRAm6O75s8UawN/UEqpp0eaeMedBzWAB1RBEaDoe4aBPJc2ZQo+uLr
UirIbOYn69OyNWoxqG7AwwoKwXvun6WSONnnRC3btH88D1hKq3oAMALp0zHw8Fkc
Grty7dMqCwbdNKtwr9GL2i7Ve8YrhNCt7uT4NEhbi2JXnXDIqxBQwVumXsJ1taPx
YQIDAQAB
-----END PUBLIC KEY-----
PEM;

	/**
	 * How long a signature stays valid for, in seconds — bounds how long a
	 * captured request could be replayed.
	 */
	const TS_WINDOW = 300;

	/**
	 * Register the rest_api_init hook.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the two 404-log routes.
	 */
	public function register_routes() {
		register_rest_route(
			'kw-performance/v1',
			'/404-logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_logs' ),
				'permission_callback' => array( $this, 'verify_read_signature' ),
			)
		);

		register_rest_route(
			'kw-performance/v1',
			'/404-logs/clear',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'clear_logs' ),
				'permission_callback' => array( $this, 'verify_clear_signature' ),
			)
		);
	}

	/**
	 * Permission callback for the read route.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function verify_read_signature( $request ) {
		return $this->verify_signature( $request, '404-log' );
	}

	/**
	 * Permission callback for the clear route. A distinct topic string means
	 * a captured read-signature can never be replayed to trigger a clear.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public function verify_clear_signature( $request ) {
		return $this->verify_signature( $request, '404-log-clear' );
	}

	/**
	 * Verifies installation_id + timestamp + signature query params against
	 * the dashboard's public key. Same signed-read model as KW Security's
	 * own routes on this same dashboard.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $topic   Expected topic string baked into the signed message.
	 * @return true|WP_Error
	 */
	private function verify_signature( $request, $topic ) {
		$installation_id = $request->get_param( 'installation_id' );
		$timestamp        = $request->get_param( 'timestamp' );
		$signature        = $request->get_param( 'signature' );

		if ( ! $installation_id || ! $timestamp || ! $signature ) {
			return new WP_Error( 'kwperf_missing_params', 'Missing signature parameters', array( 'status' => 400 ) );
		}

		if ( abs( time() - (int) $timestamp ) > self::TS_WINDOW ) {
			return new WP_Error( 'kwperf_expired', 'Signature expired', array( 'status' => 403 ) );
		}

		$message = "{$installation_id}|{$topic}|{$timestamp}";
		$valid   = openssl_verify( $message, base64_decode( $signature ), self::DASHBOARD_PUBLIC_KEY, OPENSSL_ALGO_SHA256 );

		if ( 1 !== $valid ) {
			return new WP_Error( 'kwperf_invalid_signature', 'Invalid signature', array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Returns every 404-log row — same fields the wp-admin Logs page and its
	 * CSV export both already use. Never paginated: the dashboard mirrors
	 * nothing locally, so it needs the whole log on every visit.
	 *
	 * @return WP_REST_Response
	 */
	public function get_logs() {
		return rest_ensure_response(
			array(
				'ok'    => true,
				'items' => KWPERF_Logger::get_all_logs_for_export(),
			)
		);
	}

	/**
	 * Wipes the whole 404 log — the same action already available in
	 * wp-admin ("Clear all logs"), just reachable remotely with a valid
	 * signature.
	 *
	 * @return WP_REST_Response
	 */
	public function clear_logs() {
		KWPERF_Logger::clear_all_logs();
		return rest_ensure_response( array( 'ok' => true ) );
	}
}
