<?php
/**
 * Settings page template.
 *
 * Expects: array $stats from KWPERF_Logger::get_summary_stats().
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$export_url = wp_nonce_url(
	admin_url( 'admin-post.php?action=kwperf_export_logs' ),
	'kwperf_export_logs'
);
?>
<div class="wrap kwperf-wrap">
	<h1><?php esc_html_e( 'KW Performance', 'kw-performance' ); ?></h1>

	<?php settings_errors( KWPERF_Settings::OPTION_KEY ); ?>

	<?php $last_email_error = get_option( 'kwperf_last_email_error' ); ?>
	<?php if ( $last_email_error ) : ?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'The last notification email failed to send.', 'kw-performance' ); ?></strong>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: error message from the mail transport, 2: date/time of the failure */
						__( '%1$s (%2$s)', 'kw-performance' ),
						$last_email_error['message'],
						mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_email_error['date'] )
					)
				);
				?>
				<?php esc_html_e( 'This usually means the host is blocking PHP\'s default mail sender — many managed hosts (Kinsta, Servebolt, WP Engine, etc.) require an SMTP plugin such as WP Mail SMTP for outgoing email to work at all.', 'kw-performance' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<div class="kwperf-stat-cards">
		<div class="kwperf-stat-card">
			<span class="kwperf-stat-label"><?php esc_html_e( 'Last Scan', 'kw-performance' ); ?></span>
			<span class="kwperf-stat-value">
				<?php
				echo $stats['last_scan_date']
					? esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $stats['last_scan_date'] ) )
					: esc_html__( 'Never', 'kw-performance' );
				?>
			</span>
		</div>
		<div class="kwperf-stat-card">
			<span class="kwperf-stat-label"><?php esc_html_e( 'Pages Scanned', 'kw-performance' ); ?></span>
			<span class="kwperf-stat-value"><?php echo esc_html( number_format_i18n( (int) $stats['pages_scanned'] ) ); ?></span>
		</div>
		<div class="kwperf-stat-card kwperf-stat-card-broken">
			<span class="kwperf-stat-label"><?php esc_html_e( 'Total Broken Links', 'kw-performance' ); ?></span>
			<span class="kwperf-stat-value"><?php echo esc_html( number_format_i18n( (int) $stats['total_broken'] ) ); ?></span>
		</div>
		<div class="kwperf-stat-card">
			<span class="kwperf-stat-label"><?php esc_html_e( 'Total Working Links (Last Scan)', 'kw-performance' ); ?></span>
			<span class="kwperf-stat-value"><?php echo esc_html( number_format_i18n( (int) $stats['working_links'] ) ); ?></span>
		</div>
	</div>

	<div class="kwperf-actions-bar">
		<button type="button" id="kwperf-run-scan" class="button button-primary"><?php esc_html_e( 'Run Scan Now', 'kw-performance' ); ?></button>
		<a href="<?php echo esc_url( $export_url ); ?>" class="button"><?php esc_html_e( 'Export Logs (CSV)', 'kw-performance' ); ?></a>
		<button type="button" id="kwperf-clear-logs" class="button button-secondary"><?php esc_html_e( 'Clear Logs', 'kw-performance' ); ?></button>
	</div>

	<div id="kwperf-scan-progress" class="kwperf-scan-progress" style="display:none;">
		<div class="kwperf-progress-bar">
			<div class="kwperf-progress-bar-fill" id="kwperf-progress-bar-fill"></div>
		</div>
		<p class="kwperf-scan-progress-text"></p>
	</div>

	<div id="kwperf-scan-result" class="notice" style="display:none;"></div>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'kwperf_settings_group' );
		do_settings_sections( 'kwperf-settings' );
		submit_button( __( 'Save Settings', 'kw-performance' ) );
		?>
	</form>
</div>
