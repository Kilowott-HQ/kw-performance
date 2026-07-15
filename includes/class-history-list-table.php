<?php
/**
 * WP_List_Table implementation for the Scan History admin screen.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class KWPERF_History_List_Table
 *
 * Renders the paginated table of past scan runs.
 */
class KWPERF_History_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'kwperf_scan',
				'plural'   => 'kwperf_scans',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Define the columns shown in the table.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'scan_date'           => __( 'Date', 'kw-performance' ),
			'scan_type'           => __( 'Type', 'kw-performance' ),
			'duration'            => __( 'Duration (s)', 'kw-performance' ),
			'pages_scanned'       => __( 'Pages Scanned', 'kw-performance' ),
			'links_scanned'       => __( 'Links Scanned', 'kw-performance' ),
			'broken_links_found'  => __( 'Broken Links', 'kw-performance' ),
			'working_links'       => __( 'Working Links', 'kw-performance' ),
			'errors_encountered'  => __( 'Errors', 'kw-performance' ),
		);
	}

	/**
	 * Scan date column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_scan_date( $item ) {
		return esc_html(
			mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['scan_date'] )
		);
	}

	/**
	 * Scan type column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_scan_type( $item ) {
		return 'cron' === $item['scan_type']
			? esc_html__( 'Scheduled', 'kw-performance' )
			: esc_html__( 'Manual', 'kw-performance' );
	}

	/**
	 * Errors column, truncated with a title attribute for the full text.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_errors_encountered( $item ) {
		if ( empty( $item['errors_encountered'] ) ) {
			return '&#8212;';
		}

		$lines = array_filter( array_map( 'trim', explode( "\n", $item['errors_encountered'] ) ) );
		$count = count( $lines );
		$first = $lines ? reset( $lines ) : '';

		return sprintf(
			'<span title="%s">%s</span>',
			esc_attr( $item['errors_encountered'] ),
			esc_html( $first ) . ( $count > 1 ? ' &hellip; (' . (int) $count . ')' : '' )
		);
	}

	/**
	 * Default column renderer.
	 *
	 * @param array  $item        Row data.
	 * @param string $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	/**
	 * Fetch and prepare rows for display.
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = array();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page = 20;
		$paged    = $this->get_pagenum();

		$result = KWPERF_Logger::get_scan_history( $per_page, $paged );

		$this->items = $result['items'];

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
				'total_pages' => ceil( $result['total'] / $per_page ),
			)
		);
	}
}
