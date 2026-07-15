<?php
/**
 * WP_List_Table implementation for the 404 Log admin screen.
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
 * Class KWPERF_Logs_List_Table
 *
 * Renders the searchable, sortable, paginated table of broken link logs.
 */
class KWPERF_Logs_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'kwperf_log',
				'plural'   => 'kwperf_logs',
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
			'cb'              => '<input type="checkbox" />',
			'broken_url'      => __( 'Broken Link', 'kw-performance' ),
			'http_status'     => __( 'HTTP Status', 'kw-performance' ),
			'redirect_status' => __( 'Redirect Status', 'kw-performance' ),
			'source_title'    => __( 'Page Title', 'kw-performance' ),
			'source_permalink'=> __( 'Page Permalink', 'kw-performance' ),
			'section'         => __( 'Section/Class', 'kw-performance' ),
			'last_checked'    => __( 'Last Checked', 'kw-performance' ),
			'detection_count' => __( 'Times Detected', 'kw-performance' ),
		);
	}

	/**
	 * Define which columns are sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'broken_url'      => array( 'broken_url', false ),
			'http_status'     => array( 'http_status', false ),
			'source_title'    => array( 'source_title', false ),
			'last_checked'    => array( 'last_checked', true ),
			'detection_count' => array( 'detection_count', false ),
		);
	}

	/**
	 * Define bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'recheck' => __( 'Recheck Selected', 'kw-performance' ),
			'delete'  => __( 'Delete', 'kw-performance' ),
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="log_ids[]" value="%d" />', absint( $item['id'] ) );
	}

	/**
	 * Broken link column with row actions.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_broken_url( $item ) {
		$url = esc_url( $item['broken_url'] );

		$actions = array(
			'open'     => sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				$url,
				esc_html__( 'Open URL', 'kw-performance' )
			),
			'recheck'  => sprintf(
				'<a href="#" class="kwperf-recheck-single" data-id="%d">%s</a>',
				absint( $item['id'] ),
				esc_html__( 'Recheck', 'kw-performance' )
			),
			'delete'   => sprintf(
				'<a href="#" class="kwperf-delete-single" data-id="%d">%s</a>',
				absint( $item['id'] ),
				esc_html__( 'Delete', 'kw-performance' )
			),
		);

		return sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%1$s</a>%2$s',
			$url,
			$this->row_actions( $actions )
		);
	}

	/**
	 * HTTP status column with a colored badge.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_http_status( $item ) {
		$status = (int) $item['http_status'];
		$class  = 'kwperf-badge';

		if ( 0 === $status ) {
			$class .= ' kwperf-badge-error';
			$label  = __( 'Timeout/Error', 'kw-performance' );
		} elseif ( $status >= 500 ) {
			$class .= ' kwperf-badge-error';
			$label  = (string) $status;
		} elseif ( 404 === $status || 410 === $status ) {
			$class .= ' kwperf-badge-broken';
			$label  = (string) $status;
		} else {
			$class .= ' kwperf-badge-warning';
			$label  = (string) $status;
		}

		return sprintf( '<span class="%s">%s</span>', esc_attr( $class ), esc_html( $label ) );
	}

	/**
	 * Redirect status column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_redirect_status( $item ) {
		if ( 'redirect_loop' === $item['redirect_status'] ) {
			return '<span class="kwperf-badge kwperf-badge-error">' . esc_html__( 'Redirect Loop', 'kw-performance' ) . '</span>';
		}

		if ( '' === $item['redirect_status'] ) {
			return '&#8212;';
		}

		return esc_html( $item['redirect_status'] ) . ' (' . absint( $item['redirect_count'] ) . ')';
	}

	/**
	 * Page permalink column with "view page" link.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_source_permalink( $item ) {
		if ( empty( $item['source_permalink'] ) ) {
			return '&#8212;';
		}

		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $item['source_permalink'] ),
			esc_html( $item['source_permalink'] )
		);
	}

	/**
	 * Section/class column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_section( $item ) {
		$section = $item['section'] ? $item['section'] : __( '(unknown)', 'kw-performance' );
		return '<code>' . esc_html( $section ) . '</code>';
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
	 * Extra controls above/below the table: search box and status filter.
	 *
	 * @param string $which 'top' or 'bottom'.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$current_filter = isset( $_REQUEST['status_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$options = array(
			''         => __( 'All Statuses', 'kw-performance' ),
			'404'      => __( '404 Not Found', 'kw-performance' ),
			'410'      => __( '410 Gone', 'kw-performance' ),
			'5xx'      => __( 'Server Errors (5xx)', 'kw-performance' ),
			'redirect' => __( 'Broken Redirects', 'kw-performance' ),
		);
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="kwperf-status-filter"><?php esc_html_e( 'Filter by status', 'kw-performance' ); ?></label>
			<select name="status_filter" id="kwperf-status-filter">
				<?php foreach ( $options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_filter, $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'kw-performance' ), '', 'filter_action', false ); ?>
			<button type="button" class="button kwperf-recheck-all"><?php esc_html_e( 'Recheck All', 'kw-performance' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Fetch and prepare rows for display.
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page = 20;
		$paged    = $this->get_pagenum();
		$search   = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_filter = isset( $_REQUEST['status_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby  = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'last_checked'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order    = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'desc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$result = KWPERF_Logger::get_logs(
			array(
				'search'        => $search,
				'orderby'       => $orderby,
				'order'         => $order,
				'per_page'      => $per_page,
				'paged'         => $paged,
				'status_filter' => $status_filter,
			)
		);

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
