<?php
/**
 * Scan History tab content.
 *
 * Expects: KWPERF_History_List_Table $list_table (already prepared).
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p>
	<button type="button" id="kwperf-clear-history" class="button button-secondary"><?php esc_html_e( 'Clear Scan History', 'kw-performance' ); ?></button>
</p>

<div id="kwperf-history-action-result" class="notice" style="display:none;"></div>

<form method="get">
	<input type="hidden" name="page" value="kwperf-settings" />
	<input type="hidden" name="tab" value="history" />
	<?php $list_table->display(); ?>
</form>
