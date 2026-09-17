<?php
/**
 * Tracking page template — Google Tag Manager configuration.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap kwperf-wrap">
	<h1><?php esc_html_e( 'Tracking', 'kw-performance' ); ?></h1>

	<p class="description"><?php esc_html_e( 'All tracking on this site is done through Google Tag Manager.', 'kw-performance' ); ?></p>

	<?php settings_errors( KWPERF_Settings::OPTION_KEY ); ?>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'kwperf_settings_group' );
		do_settings_sections( 'kwperf-tracking' );
		submit_button( __( 'Save Settings', 'kw-performance' ) );
		?>
	</form>
</div>
