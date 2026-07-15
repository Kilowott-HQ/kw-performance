<?php
/**
 * Database table management.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KWPERF_Database
 *
 * Handles creation and access helpers for the plugin's custom tables:
 * - {prefix}kwperf_logs
 * - {prefix}kwperf_scan_history
 */
class KWPERF_Database {

	/**
	 * Get the fully qualified logs table name.
	 *
	 * @return string
	 */
	public static function logs_table() {
		global $wpdb;
		return $wpdb->prefix . 'kwperf_logs';
	}

	/**
	 * Get the fully qualified scan history table name.
	 *
	 * @return string
	 */
	public static function history_table() {
		global $wpdb;
		return $wpdb->prefix . 'kwperf_scan_history';
	}

	/**
	 * Create (or upgrade) the custom database tables.
	 *
	 * Uses dbDelta so this is safe to call on every activation/upgrade.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$logs_table      = self::logs_table();
		$history_table   = self::history_table();

		$sql_logs = "CREATE TABLE {$logs_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			source_page_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			source_permalink VARCHAR(2000) NOT NULL DEFAULT '',
			source_title VARCHAR(500) NOT NULL DEFAULT '',
			broken_url VARCHAR(2000) NOT NULL DEFAULT '',
			final_url VARCHAR(2000) NOT NULL DEFAULT '',
			http_status SMALLINT NOT NULL DEFAULT 0,
			redirect_status VARCHAR(20) NOT NULL DEFAULT '',
			redirect_count SMALLINT NOT NULL DEFAULT 0,
			section VARCHAR(500) NOT NULL DEFAULT '',
			css_class VARCHAR(1000) NOT NULL DEFAULT '',
			link_text VARCHAR(500) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'broken',
			first_detected DATETIME NOT NULL,
			last_checked DATETIME NOT NULL,
			detection_count INT UNSIGNED NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			KEY source_page_id (source_page_id),
			KEY http_status (http_status),
			KEY status (status),
			KEY broken_url (broken_url(191))
		) {$charset_collate};";

		$sql_history = "CREATE TABLE {$history_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			scan_date DATETIME NOT NULL,
			duration FLOAT NOT NULL DEFAULT 0,
			pages_scanned INT UNSIGNED NOT NULL DEFAULT 0,
			links_scanned INT UNSIGNED NOT NULL DEFAULT 0,
			broken_links_found INT UNSIGNED NOT NULL DEFAULT 0,
			working_links INT UNSIGNED NOT NULL DEFAULT 0,
			errors_encountered TEXT NULL,
			scan_type VARCHAR(20) NOT NULL DEFAULT 'manual',
			PRIMARY KEY  (id),
			KEY scan_date (scan_date)
		) {$charset_collate};";

		dbDelta( $sql_logs );
		dbDelta( $sql_history );

		update_option( 'kwperf_db_version', KWPERF_DB_VERSION );
	}

	/**
	 * One-time migration from the plugin's previous name (KW 404 Detector).
	 *
	 * If the old `{prefix}kw404_logs` / `{prefix}kw404_scan_history` tables
	 * exist (left behind by replacing the old plugin's files directly,
	 * without using WordPress's "Delete" action, which would have already
	 * removed them) and the new tables are still empty, copy the data across
	 * and drop the old tables. Safe to call on every activation — it's a
	 * no-op once the old tables are gone.
	 */
	public static function maybe_migrate_from_kw404() {
		global $wpdb;

		$old_logs_table    = $wpdb->prefix . 'kw404_logs';
		$old_history_table = $wpdb->prefix . 'kw404_scan_history';
		$new_logs_table    = self::logs_table();
		$new_history_table = self::history_table();

		$old_logs_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_logs_table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $old_logs_exists ) {
			$new_logs_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$new_logs_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( 0 === $new_logs_count ) {
				$wpdb->query( "INSERT INTO {$new_logs_table} SELECT * FROM {$old_logs_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}

			$wpdb->query( "DROP TABLE IF EXISTS {$old_logs_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$old_history_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_history_table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $old_history_exists ) {
			$new_history_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$new_history_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( 0 === $new_history_count ) {
				$wpdb->query( "INSERT INTO {$new_history_table} SELECT * FROM {$old_history_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}

			$wpdb->query( "DROP TABLE IF EXISTS {$old_history_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// Options, saved under the old plugin's names.
		$option_map = array(
			'kw404_settings'         => 'kwperf_settings',
			'kw404_last_scan'        => 'kwperf_last_scan',
			'kw404_last_email_error' => 'kwperf_last_email_error',
		);

		foreach ( $option_map as $old_key => $new_key ) {
			$old_value = get_option( $old_key, null );

			if ( null !== $old_value && false === get_option( $new_key, false ) ) {
				update_option( $new_key, $old_value );
			}

			delete_option( $old_key );
		}

		delete_option( 'kw404_db_version' );
	}

	/**
	 * Drop the custom tables (used on uninstall when the user opts in).
	 */
	public static function drop_tables() {
		global $wpdb;

		$logs_table    = self::logs_table();
		$history_table = self::history_table();

		// Table names are derived from $wpdb->prefix, not user input, so direct queries are safe here.
		$wpdb->query( "DROP TABLE IF EXISTS {$logs_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$history_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
