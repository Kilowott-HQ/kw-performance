<?php
/**
 * 404 Log page template.
 *
 * Expects: KWPERF_Logs_List_Table $list_table (already prepared).
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$export_url = wp_nonce_url(
	add_query_arg(
		array( 's' => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		admin_url( 'admin-post.php?action=kwperf_export_logs' )
	),
	'kwperf_export_logs'
);
?>
<div class="wrap kwperf-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( '404 Log', 'kw-performance' ); ?></h1>
	<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'kw-performance' ); ?></a>

	<div id="kwperf-log-action-result" class="notice" style="display:none;"></div>

	<form method="get" id="kwperf-logs-filter-form">
		<input type="hidden" name="page" value="<?php echo esc_attr( isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : 'kwperf-logs' ); ?>" />
		<?php $list_table->search_box( __( 'Search Broken Links', 'kw-performance' ), 'kwperf-logs' ); ?>
		<?php $list_table->display(); ?>
	</form>
</div>
