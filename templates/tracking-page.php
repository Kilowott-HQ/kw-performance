<?php
/**
 * Tracking tab content — Google Tag Manager configuration.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<p class="description"><?php esc_html_e( 'All tracking on this site is done through Google Tag Manager.', 'kw-performance' ); ?></p>

<?php settings_errors( KWPERF_Settings::OPTION_KEY ); ?>

<form method="post" action="options.php">
	<?php
	settings_fields( 'kwperf_settings_group' );
	do_settings_sections( 'kwperf-tracking' );
	submit_button( __( 'Save Settings', 'kw-performance' ) );
	?>
</form>
