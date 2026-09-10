<?php
/**
 * WP_List_Table implementation for the Metas admin screen.
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
 * Class KWPERF_Metas_List_Table
 *
 * Renders a searchable, sortable, paginated table of one post type's
 * published entries with inline-editable meta title/description fields.
 */
class KWPERF_Metas_List_Table extends WP_List_Table {

	/**
	 * Post type this table instance lists.
	 *
	 * @var string
	 */
	private $post_type;

	/**
	 * Constructor.
	 *
	 * @param string $post_type Post type slug to list.
	 */
	public function __construct( $post_type ) {
		$this->post_type = $post_type;

		parent::__construct(
			array(
				'singular' => 'kwperf_meta',
				'plural'   => 'kwperf_metas',
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
			'title'            => __( 'Page Title', 'kw-performance' ),
			'permalink'        => __( 'Page Link', 'kw-performance' ),
			'meta_title'       => __( 'Meta Title', 'kw-performance' ),
			'meta_description' => __( 'Meta Description', 'kw-performance' ),
		);
	}

	/**
	 * Define which columns are sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'title' => array( 'title', false ),
		);
	}

	/**
	 * Page title column, linked to the post's edit screen.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_title( $item ) {
		$edit_link = get_edit_post_link( $item['ID'] );
		$label     = $item['title'] ? $item['title'] : __( '(no title)', 'kw-performance' );

		if ( ! $edit_link ) {
			return '<strong>' . esc_html( $label ) . '</strong>';
		}

		return sprintf( '<strong><a href="%s">%s</a></strong>', esc_url( $edit_link ), esc_html( $label ) );
	}

	/**
	 * Page link column.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_permalink( $item ) {
		if ( empty( $item['permalink'] ) ) {
			return '&#8212;';
		}

		return sprintf(
			'<a href="%1$s" target="_blank" rel="noopener noreferrer">%1$s</a>',
			esc_url( $item['permalink'] )
		);
	}

	/**
	 * Meta title column: an inline-editable text field.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_meta_title( $item ) {
		return $this->render_editable_field( $item['ID'], 'title', $item['meta_title'] );
	}

	/**
	 * Meta description column: an inline-editable textarea.
	 *
	 * @param array $item Row data.
	 * @return string
	 */
	public function column_meta_description( $item ) {
		return $this->render_editable_field( $item['ID'], 'description', $item['meta_description'] );
	}

	/**
	 * Render the shared markup for an inline-editable meta field.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $field   'title' or 'description'.
	 * @param string $value   Current value.
	 * @return string
	 */
	private function render_editable_field( $post_id, $field, $value ) {
		$input = 'title' === $field
			? sprintf( '<input type="text" class="regular-text kwperf-meta-input" value="%s" />', esc_attr( $value ) )
			: sprintf( '<textarea class="large-text kwperf-meta-input" rows="2">%s</textarea>', esc_textarea( $value ) );

		return sprintf(
			'<div class="kwperf-meta-edit" data-post-id="%1$d" data-field="%2$s">%3$s<br /><button type="button" class="button button-small kwperf-save-meta">%4$s</button> <span class="kwperf-meta-save-result kwperf-inline-result"></span></div>',
			absint( $post_id ),
			esc_attr( $field ),
			$input,
			esc_html__( 'Save', 'kw-performance' )
		);
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
		$orderby  = isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'title'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order    = isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'asc'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$query = new WP_Query(
			array(
				'post_type'              => $this->post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => $per_page,
				'paged'                  => $paged,
				's'                      => $search,
				'orderby'                => 'title' === $orderby ? 'title' : 'date',
				'order'                  => 'desc' === strtolower( $order ) ? 'DESC' : 'ASC',
				'update_post_term_cache' => false,
			)
		);

		$items = array();

		foreach ( $query->posts as $post ) {
			// A third-party plugin owning this post type (e.g. a custom permalink
			// or title filter on a listings/property CPT) can throw when its own
			// assumptions aren't met outside a normal front-end request — that
			// shouldn't be able to take down the whole Metas screen, just this row.
			try {
				$items[] = array(
					'ID'               => $post->ID,
					'title'            => get_the_title( $post ),
					'permalink'        => get_permalink( $post ),
					'meta_title'       => KWPERF_Meta_Manager::get_title( $post->ID ),
					'meta_description' => KWPERF_Meta_Manager::get_description( $post->ID ),
				);
			} catch ( Throwable $e ) {
				error_log( sprintf( 'KW Performance: Metas row failed for post %d (%s): %s', $post->ID, $this->post_type, $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				$items[] = array(
					'ID'               => $post->ID,
					'title'            => __( '(error loading this item)', 'kw-performance' ),
					'permalink'        => '',
					'meta_title'       => '',
					'meta_description' => '',
				);
			}
		}

		$this->items = $items;

		$this->set_pagination_args(
			array(
				'total_items' => $query->found_posts,
				'per_page'    => $per_page,
				'total_pages' => $query->max_num_pages,
			)
		);
	}
}
