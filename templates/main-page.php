<?php
/**
 * Shared wrapper for the single KW Performance admin page: heading (with
 * version number), tab navigation, and the active tab's content.
 *
 * Expects: array $tabs (tab key => label, in display order), string $active_tab.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap kwperf-wrap">
	<h1>
		<?php esc_html_e( 'KW Performance', 'kw-performance' ); ?>
		<span class="kwperf-version">
			<?php
			printf(
				/* translators: %s: plugin version number */
				esc_html__( 'Version %s', 'kw-performance' ),
				esc_html( KWPERF_VERSION )
			);
			?>
		</span>
	</h1>

	<h2 class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<?php
			$tab_url = add_query_arg(
				array(
					'page' => 'kwperf-settings',
					'tab'  => $tab_key,
				),
				admin_url( 'options-general.php' )
			);
			?>
			<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab <?php echo $tab_key === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</h2>

	<?php
	switch ( $active_tab ) {
		case 'history':
			$this->render_history_tab();
			break;
		case 'tracking':
			$this->render_tracking_tab();
			break;
		case 'metas':
			$this->render_metas_tab();
			break;
		case 'logs':
			$this->render_logs_tab();
			break;
		default:
			$this->render_settings_tab();
			break;
	}
	?>
</div>
