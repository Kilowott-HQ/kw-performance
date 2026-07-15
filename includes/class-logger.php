<?php
/**
 * Persistence layer for broken link logs and scan history.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KWPERF_Logger
 *
 * Reads and writes rows in the kwperf_logs and kwperf_scan_history tables.
 */
class KWPERF_Logger {

	/**
	 * Insert a new broken link log row, or update the existing matching row's
	 * counters/timestamp if the same broken URL was already logged for the
	 * same source page.
	 *
	 * @param array $data Occurrence data from the scanner.
	 * @return int Log row ID.
	 */
	public static function upsert_log( $data ) {
		global $wpdb;

		$table = KWPERF_Database::logs_table();
		$now   = current_time( 'mysql' );

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE source_page_id = %d AND broken_url = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$data['source_page_id'],
				$data['broken_url']
			)
		);

		if ( $existing_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET final_url = %s, http_status = %d, redirect_status = %s, redirect_count = %d, section = %s, css_class = %s, link_text = %s, source_permalink = %s, source_title = %s, last_checked = %s, detection_count = detection_count + 1 WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$data['final_url'],
					$data['http_status'],
					$data['redirect_status'],
					$data['redirect_count'],
					$data['section'],
					$data['css_class'],
					$data['link_text'],
					$data['source_permalink'],
					$data['source_title'],
					$now,
					$existing_id
				)
			);

			return (int) $existing_id;
		}

		$wpdb->insert(
			$table,
			array(
				'source_page_id'   => $data['source_page_id'],
				'source_permalink' => $data['source_permalink'],
				'source_title'     => $data['source_title'],
				'broken_url'       => $data['broken_url'],
				'final_url'        => $data['final_url'],
				'http_status'      => $data['http_status'],
				'redirect_status'  => $data['redirect_status'],
				'redirect_count'   => $data['redirect_count'],
				'section'          => $data['section'],
				'css_class'        => $data['css_class'],
				'link_text'        => $data['link_text'],
				'status'           => 'broken',
				'first_detected'   => $now,
				'last_checked'     => $now,
				'detection_count'  => 1,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Delete log rows that were not refreshed since the given scan start time.
	 * These represent previously-broken links that are now resolved.
	 *
	 * @param string $scan_start MySQL datetime string marking scan start.
	 */
	public static function purge_stale_logs( $scan_start ) {
		global $wpdb;

		$table = KWPERF_Database::logs_table();

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE last_checked < %s", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$scan_start
			)
		);
	}

	/**
	 * Record a completed scan in the scan history table.
	 *
	 * @param array $data Scan summary fields.
	 * @return int Inserted row ID.
	 */
	public static function record_scan_history( $data ) {
		global $wpdb;

		$table = KWPERF_Database::history_table();

		$wpdb->insert(
			$table,
			array(
				'scan_date'           => $data['scan_date'],
				'duration'            => $data['duration'],
				'pages_scanned'       => $data['pages_scanned'],
				'links_scanned'       => $data['links_scanned'],
				'broken_links_found'  => $data['broken_links_found'],
				'working_links'       => $data['working_links'],
				'errors_encountered'  => $data['errors_encountered'],
				'scan_type'           => $data['scan_type'],
			),
			array( '%s', '%f', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Query broken link logs with search/sort/pagination support.
	 *
	 * @param array $args Query args: search, orderby, order, per_page, paged.
	 * @return array{items:array,total:int}
	 */
	public static function get_logs( $args = array() ) {
		global $wpdb;

		$table = KWPERF_Database::logs_table();

		$defaults = array(
			'search'   => '',
			'orderby'  => 'last_checked',
			'order'    => 'DESC',
			'per_page' => 20,
			'paged'    => 1,
			'status_filter' => '',
		);
		$args = wp_parse_args( $args, $defaults );

		$allowed_orderby = array( 'broken_url', 'http_status', 'redirect_status', 'source_title', 'section', 'last_checked', 'detection_count', 'first_detected' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'last_checked';
		$order            = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$where  = 'WHERE 1=1';
		$params = array();

		if ( '' !== $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where  .= ' AND (broken_url LIKE %s OR source_title LIKE %s OR source_permalink LIKE %s OR section LIKE %s OR css_class LIKE %s)';
			$params  = array_merge( $params, array( $like, $like, $like, $like, $like ) );
		}

		if ( '' !== $args['status_filter'] ) {
			if ( '404' === $args['status_filter'] ) {
				$where   .= ' AND http_status = %d';
				$params[] = 404;
			} elseif ( '410' === $args['status_filter'] ) {
				$where   .= ' AND http_status = %d';
				$params[] = 410;
			} elseif ( '5xx' === $args['status_filter'] ) {
				$where .= ' AND http_status >= 500 AND http_status < 600';
			} elseif ( 'redirect' === $args['status_filter'] ) {
				$where .= " AND redirect_status != ''";
			}
		}

		$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		$sql       = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql_params = array_merge( $params, array( $per_page, $offset ) );

		$items = $wpdb->get_results( $wpdb->prepare( $sql, $sql_params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Fetch all logs matching a search/filter (no pagination) for CSV export.
	 *
	 * @param string $search Optional search term.
	 * @return array
	 */
	public static function get_all_logs_for_export( $search = '' ) {
		$result = self::get_logs(
			array(
				'search'   => $search,
				'per_page' => 100000,
				'paged'    => 1,
			)
		);

		return $result['items'];
	}

	/**
	 * Delete a single log row.
	 *
	 * @param int $id Log row ID.
	 * @return bool
	 */
	public static function delete_log( $id ) {
		global $wpdb;
		$table = KWPERF_Database::logs_table();
		return (bool) $wpdb->delete( $table, array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * Delete multiple log rows.
	 *
	 * @param int[] $ids Log row IDs.
	 * @return int Number of rows deleted.
	 */
	public static function bulk_delete_logs( $ids ) {
		$count = 0;
		foreach ( (array) $ids as $id ) {
			if ( self::delete_log( $id ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Delete every row in the logs table, and reset the last-scan snapshot
	 * used by the dashboard stat cards (Last Scan, Pages Scanned, Working
	 * Links) so they don't keep showing stale numbers from before the clear.
	 */
	public static function clear_all_logs() {
		global $wpdb;
		$table = KWPERF_Database::logs_table();
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		delete_option( 'kwperf_last_scan' );
	}

	/**
	 * Delete every row in the scan history table.
	 */
	public static function clear_scan_history() {
		global $wpdb;
		$table = KWPERF_Database::history_table();
		$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get every log row ID currently stored (used by "Recheck All").
	 *
	 * @return int[]
	 */
	public static function get_all_log_ids() {
		global $wpdb;
		$table = KWPERF_Database::logs_table();
		$ids   = $wpdb->get_col( "SELECT id FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array_map( 'absint', $ids );
	}

	/**
	 * Fetch a single log row.
	 *
	 * @param int $id Log row ID.
	 * @return array|null
	 */
	public static function get_log( $id ) {
		global $wpdb;
		$table = KWPERF_Database::logs_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $row ? $row : null;
	}

	/**
	 * Get aggregate counters for the settings page dashboard widgets.
	 *
	 * @return array
	 */
	public static function get_summary_stats() {
		global $wpdb;
		$table = KWPERF_Database::logs_table();

		$total_broken = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$last_scan = get_option( 'kwperf_last_scan', array() );

		return array(
			'last_scan_date'     => $last_scan['date'] ?? '',
			'pages_scanned'      => $last_scan['pages_scanned'] ?? 0,
			'total_broken'       => $total_broken,
			'working_links'      => $last_scan['working_links'] ?? 0,
		);
	}

	/**
	 * Query scan history rows with pagination.
	 *
	 * @param int $per_page Rows per page.
	 * @param int $paged    Page number.
	 * @return array{items:array,total:int}
	 */
	public static function get_scan_history( $per_page = 20, $paged = 1 ) {
		global $wpdb;
		$table = KWPERF_Database::history_table();

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$per_page = max( 1, (int) $per_page );
		$paged    = max( 1, (int) $paged );
		$offset   = ( $paged - 1 ) * $per_page;

		$items = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY scan_date DESC LIMIT %d OFFSET %d", $per_page, $offset ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}
}
