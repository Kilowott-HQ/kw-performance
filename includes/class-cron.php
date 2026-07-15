<?php
/**
 * WP-Cron scheduling for automated scans.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KWPERF_Cron
 *
 * Registers the custom cron schedule/event and runs the scheduled scan.
 */
class KWPERF_Cron {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	const HOOK = 'kwperf_scheduled_scan';

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( self::HOOK, array( $this, 'run_scheduled_scan' ) );
	}

	/**
	 * Schedule the cron event on activation if it isn't already scheduled.
	 */
	public static function schedule() {
		if ( ! KWPERF_Settings::get( 'enable_scan', 1 ) ) {
			return;
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			$interval  = KWPERF_Settings::get( 'scan_interval', 'daily' );
			$scan_time = KWPERF_Settings::get( 'scan_time', '' );
			wp_schedule_event( self::get_next_run_timestamp( $scan_time ), $interval, self::HOOK );
		}
	}

	/**
	 * Resolve the "HH:mm" scan start time to the next matching Unix timestamp
	 * (today if that time hasn't passed yet, otherwise tomorrow), based on the
	 * site's configured timezone.
	 *
	 * @param string $time_string Start time in "HH:mm" 24-hour format.
	 * @return int Unix timestamp.
	 */
	private static function get_next_run_timestamp( $time_string ) {
		if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', (string) $time_string, $matches ) ) {
			return time() + HOUR_IN_SECONDS;
		}

		$timezone = wp_timezone();
		$now      = new DateTime( 'now', $timezone );

		$next = new DateTime( 'now', $timezone );
		$next->setTime( (int) $matches[1], (int) $matches[2], 0 );

		if ( $next <= $now ) {
			$next->modify( '+1 day' );
		}

		return $next->getTimestamp();
	}

	/**
	 * Remove the scheduled cron event.
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::HOOK );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
			$timestamp = wp_next_scheduled( self::HOOK );
		}
	}

	/**
	 * Re-schedule the cron event when settings change (enabled state, interval,
	 * or start time).
	 *
	 * @param bool   $enabled   Whether scheduled scanning is enabled.
	 * @param string $interval  Cron schedule slug.
	 * @param string $scan_time Start time in "HH:mm" 24-hour format.
	 */
	public static function reschedule( $enabled, $interval, $scan_time = '' ) {
		self::unschedule();

		if ( $enabled ) {
			wp_schedule_event( self::get_next_run_timestamp( $scan_time ), $interval, self::HOOK );
		}
	}

	/**
	 * Callback executed by WP-Cron: runs the scan and sends notification email.
	 */
	public function run_scheduled_scan() {
		// Prevent overlapping scans (manual scan already running).
		if ( get_transient( 'kwperf_scan_in_progress' ) ) {
			return;
		}

		set_transient( 'kwperf_scan_in_progress', 1, HOUR_IN_SECONDS );

		$scanner = new KWPERF_Scanner();
		$summary = $scanner->run_scan( 'cron' );

		$emailer = new KWPERF_Email();
		$emailer->maybe_send_scan_report( $summary );

		$slack = new KWPERF_Slack();
		$slack->maybe_send_scan_report( $summary );

		delete_transient( 'kwperf_scan_in_progress' );
	}
}
