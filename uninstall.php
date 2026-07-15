<?php
/**
 * Uninstall handler.
 *
 * Runs when the plugin is deleted from the Plugins screen. Removes plugin
 * options always; custom database tables are only dropped if the site
 * owner opted into "Delete Data On Uninstall" in the settings screen.
 *
 * @package KW_Performance
 */

// Exit if accessed directly, or not via the genuine WP uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$kwperf_settings = get_option( 'kwperf_settings', array() );
$kwperf_delete_data = ! empty( $kwperf_settings['delete_data_on_uninstall'] );

// Always remove the scheduled cron event.
$kwperf_timestamp = wp_next_scheduled( 'kwperf_scheduled_scan' );
while ( $kwperf_timestamp ) {
	wp_unschedule_event( $kwperf_timestamp, 'kwperf_scheduled_scan' );
	$kwperf_timestamp = wp_next_scheduled( 'kwperf_scheduled_scan' );
}

delete_option( 'kwperf_settings' );
delete_option( 'kwperf_last_scan' );
delete_option( 'kwperf_db_version' );
delete_option( 'kwperf_last_email_error' );
delete_transient( 'kwperf_scan_in_progress' );
delete_transient( 'kwperf_scan_progress' );

if ( $kwperf_delete_data ) {
	global $wpdb;

	$kwperf_logs_table    = $wpdb->prefix . 'kwperf_logs';
	$kwperf_history_table = $wpdb->prefix . 'kwperf_scan_history';

	// Table names are derived from $wpdb->prefix, not user input.
	$wpdb->query( "DROP TABLE IF EXISTS {$kwperf_logs_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$kwperf_history_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}
