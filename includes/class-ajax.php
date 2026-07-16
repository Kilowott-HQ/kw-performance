<?php
/**
 * AJAX and admin-post request handlers.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KWPERF_Ajax
 *
 * Handles all admin-ajax.php and admin-post.php endpoints used by the
 * plugin's admin screens (manual scan, log management, CSV export).
 */
class KWPERF_Ajax {

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'wp_ajax_kwperf_run_scan', array( $this, 'ajax_run_scan' ) );
		add_action( 'wp_ajax_kwperf_scan_status', array( $this, 'ajax_scan_status' ) );
		add_action( 'wp_ajax_kwperf_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_kwperf_clear_scan_history', array( $this, 'ajax_clear_scan_history' ) );
		add_action( 'wp_ajax_kwperf_delete_log', array( $this, 'ajax_delete_log' ) );
		add_action( 'wp_ajax_kwperf_bulk_delete_logs', array( $this, 'ajax_bulk_delete_logs' ) );
		add_action( 'wp_ajax_kwperf_recheck_logs', array( $this, 'ajax_recheck_logs' ) );
		add_action( 'wp_ajax_kwperf_test_slack', array( $this, 'ajax_test_slack' ) );

		add_action( 'admin_post_kwperf_export_logs', array( $this, 'export_logs_csv' ) );
	}

	/**
	 * Verify the shared admin nonce and capability for AJAX requests.
	 */
	private function verify_request() {
		check_ajax_referer( 'kwperf_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'kw-performance' ) ), 403 );
		}
	}

	/**
	 * AJAX: run a manual scan and return the summary.
	 */
	public function ajax_run_scan() {
		$this->verify_request();

		if ( get_transient( 'kwperf_scan_in_progress' ) ) {
			wp_send_json_error( array( 'message' => __( 'A scan is already in progress. Please wait for it to finish.', 'kw-performance' ) ) );
		}

		set_transient( 'kwperf_scan_in_progress', 1, HOUR_IN_SECONDS );

		try {
			// "Run Scan Now" always starts from a clean slate, rather than merging
			// with whatever the previous scan (or a since-fixed issue) left behind.
			KWPERF_Logger::clear_all_logs();

			$scanner = new KWPERF_Scanner();
			$summary = $scanner->run_scan( 'manual' );

			$emailer = new KWPERF_Email();
			$emailer->maybe_send_scan_report( $summary );

			$slack = new KWPERF_Slack();
			$slack->maybe_send_scan_report( $summary );
		} finally {
			delete_transient( 'kwperf_scan_in_progress' );
			delete_transient( 'kwperf_scan_progress' );
		}

		wp_send_json_success(
			array(
				'message'            => __( 'Scan complete.', 'kw-performance' ),
				'pages_scanned'      => (int) $summary['pages_scanned'],
				'links_scanned'      => (int) $summary['links_scanned'],
				'broken_links_found' => (int) $summary['broken_links_found'],
				'working_links'      => (int) $summary['working_links'],
				'duration'           => $summary['duration'],
				'scan_date'          => mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $summary['scan_date'] ),
			)
		);
	}

	/**
	 * AJAX: report whether a scan is currently running (used to prevent overlapping scans across tabs).
	 */
	public function ajax_scan_status() {
		$this->verify_request();

		$progress = get_transient( 'kwperf_scan_progress' );

		wp_send_json_success(
			array(
				'in_progress' => (bool) get_transient( 'kwperf_scan_in_progress' ),
				'progress'    => $progress ? $progress : null,
			)
		);
	}

	/**
	 * AJAX: clear every log entry.
	 */
	public function ajax_clear_logs() {
		$this->verify_request();

		KWPERF_Logger::clear_all_logs();

		wp_send_json_success( array( 'message' => __( 'All logs cleared.', 'kw-performance' ) ) );
	}

	/**
	 * AJAX: clear every scan history entry.
	 */
	public function ajax_clear_scan_history() {
		$this->verify_request();

		KWPERF_Logger::clear_scan_history();

		wp_send_json_success( array( 'message' => __( 'Scan history cleared.', 'kw-performance' ) ) );
	}

	/**
	 * AJAX: delete a single log entry.
	 */
	public function ajax_delete_log() {
		$this->verify_request();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid log ID.', 'kw-performance' ) ) );
		}

		KWPERF_Logger::delete_log( $id );

		wp_send_json_success( array( 'message' => __( 'Log entry deleted.', 'kw-performance' ) ) );
	}

	/**
	 * AJAX: bulk delete selected log entries.
	 */
	public function ajax_bulk_delete_logs() {
		$this->verify_request();

		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No log entries selected.', 'kw-performance' ) ) );
		}

		$deleted = KWPERF_Logger::bulk_delete_logs( $ids );

		wp_send_json_success(
			array(
				/* translators: %d: number of deleted rows */
				'message' => sprintf( __( '%d log entries deleted.', 'kw-performance' ), $deleted ),
			)
		);
	}

	/**
	 * AJAX: re-check one or more logged broken links on demand.
	 */
	public function ajax_recheck_logs() {
		$this->verify_request();

		$recheck_all = ! empty( $_POST['all'] );
		$ids         = $recheck_all
			? KWPERF_Logger::get_all_log_ids()
			: ( isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array() );

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No log entries selected.', 'kw-performance' ) ) );
		}

		$scanner = new KWPERF_Scanner();
		$result  = $scanner->recheck_logs( $ids );

		wp_send_json_success(
			array(
				/* translators: 1: number resolved, 2: number still broken */
				'message'      => sprintf(
					__( 'Recheck complete: %1$d resolved, %2$d still broken.', 'kw-performance' ),
					$result['resolved'],
					$result['still_broken']
				),
				'resolved'     => $result['resolved'],
				'still_broken' => $result['still_broken'],
			)
		);
	}

	/**
	 * AJAX: send a test notification to Slack using whatever webhook URL is
	 * currently in the field, so it can be verified before saving.
	 */
	public function ajax_test_slack() {
		$this->verify_request();

		$webhook_url = isset( $_POST['webhook_url'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['webhook_url'] ) ) ) : '';

		$slack  = new KWPERF_Slack();
		$result = $slack->send_test( $webhook_url );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Test message sent — check your Slack channel.', 'kw-performance' ) ) );
	}

	/**
	 * admin-post handler: stream the logs table as a CSV download.
	 */
	public function export_logs_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'kw-performance' ) );
		}

		check_admin_referer( 'kwperf_export_logs' );

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$rows   = KWPERF_Logger::get_all_logs_for_export( $search );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=kwperf-logs-' . current_time( 'Y-m-d-H-i-s' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );

		fputcsv(
			$output,
			array(
				__( 'Broken Link', 'kw-performance' ),
				__( 'Final URL', 'kw-performance' ),
				__( 'HTTP Status', 'kw-performance' ),
				__( 'Redirect Status', 'kw-performance' ),
				__( 'Redirect Count', 'kw-performance' ),
				__( 'Page Title', 'kw-performance' ),
				__( 'Page Permalink', 'kw-performance' ),
				__( 'Section/Class', 'kw-performance' ),
				__( 'Link Text', 'kw-performance' ),
				__( 'First Detected', 'kw-performance' ),
				__( 'Last Checked', 'kw-performance' ),
				__( 'Times Detected', 'kw-performance' ),
			)
		);

		foreach ( $rows as $row ) {
			fputcsv(
				$output,
				array(
					$row['broken_url'],
					$row['final_url'],
					$row['http_status'],
					$row['redirect_status'],
					$row['redirect_count'],
					$row['source_title'],
					$row['source_permalink'],
					$row['section'],
					$row['link_text'],
					$row['first_detected'],
					$row['last_checked'],
					$row['detection_count'],
				)
			);
		}

		fclose( $output );
		exit;
	}
}
