<?php
/**
 * Settings API registration and helpers.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KWPERF_Settings
 *
 * Registers the plugin's option group/fields via the Settings API and
 * exposes convenience getters with sane defaults.
 */
class KWPERF_Settings {

	/**
	 * Option name used to store all settings as a single array.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'kwperf_settings';

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Default settings values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'enable_scan'        => 1,
			'scan_interval'      => 'daily',
			'scan_time'          => '00:00',
			'admin_email'        => get_option( 'admin_email' ),
			'notify_on_empty'    => 0,
			'post_types'         => array( 'post', 'page' ),
			'request_timeout'    => 10,
			'max_redirects'      => 5,
			'delete_data_on_uninstall' => 0,
			'slack_enabled'      => 0,
			'slack_webhook_url'  => '',
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value if not found.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$settings = self::get_settings();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Register the setting, sections, and fields.
	 */
	public function register_settings() {
		register_setting(
			'kwperf_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'kwperf_main_section',
			__( 'Scan Settings', 'kw-performance' ),
			'__return_false',
			'kwperf-settings'
		);

		add_settings_field(
			'enable_scan',
			__( 'Enable Scheduled Scanning', 'kw-performance' ),
			array( $this, 'render_enable_scan_field' ),
			'kwperf-settings',
			'kwperf_main_section'
		);

		add_settings_field(
			'scan_interval',
			__( 'Scan Interval', 'kw-performance' ),
			array( $this, 'render_scan_interval_field' ),
			'kwperf-settings',
			'kwperf_main_section'
		);

		add_settings_field(
			'admin_email',
			__( 'Notification Email', 'kw-performance' ),
			array( $this, 'render_admin_email_field' ),
			'kwperf-settings',
			'kwperf_main_section'
		);

		add_settings_field(
			'notify_on_empty',
			__( 'Notify Even If No Broken Links', 'kw-performance' ),
			array( $this, 'render_notify_on_empty_field' ),
			'kwperf-settings',
			'kwperf_main_section'
		);

		add_settings_field(
			'post_types',
			__( 'Post Types To Scan', 'kw-performance' ),
			array( $this, 'render_post_types_field' ),
			'kwperf-settings',
			'kwperf_main_section'
		);

		add_settings_field(
			'delete_data_on_uninstall',
			__( 'Delete Data On Uninstall', 'kw-performance' ),
			array( $this, 'render_delete_data_field' ),
			'kwperf-settings',
			'kwperf_main_section'
		);

		add_settings_section(
			'kwperf_slack_section',
			__( 'Slack Notifications', 'kw-performance' ),
			'__return_false',
			'kwperf-settings'
		);

		add_settings_field(
			'slack_enabled',
			__( 'Enable Slack Notifications', 'kw-performance' ),
			array( $this, 'render_slack_enabled_field' ),
			'kwperf-settings',
			'kwperf_slack_section'
		);

		add_settings_field(
			'slack_webhook_url',
			__( 'Slack Webhook URL', 'kw-performance' ),
			array( $this, 'render_slack_webhook_field' ),
			'kwperf-settings',
			'kwperf_slack_section'
		);
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Raw posted settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$output    = array();
		$defaults  = self::defaults();
		$input     = is_array( $input ) ? $input : array();

		$output['enable_scan'] = empty( $input['enable_scan'] ) ? 0 : 1;

		$allowed_intervals        = array( 'hourly', 'twicedaily', 'daily', 'weekly' );
		$output['scan_interval']  = in_array( $input['scan_interval'] ?? '', $allowed_intervals, true )
			? $input['scan_interval']
			: $defaults['scan_interval'];

		$output['scan_time'] = preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $input['scan_time'] ?? '' )
			? $input['scan_time']
			: $defaults['scan_time'];

		$sanitized_emails       = self::sanitize_email_list( $input['admin_email'] ?? '' );
		$output['admin_email']  = '' !== $sanitized_emails ? $sanitized_emails : $defaults['admin_email'];

		$output['notify_on_empty'] = empty( $input['notify_on_empty'] ) ? 0 : 1;

		$post_types              = isset( $input['post_types'] ) && is_array( $input['post_types'] )
			? array_map( 'sanitize_key', $input['post_types'] )
			: array();
		$valid_post_types        = array_keys( get_post_types( array( 'public' => true ) ) );
		$output['post_types']    = array_values( array_intersect( $post_types, $valid_post_types ) );
		if ( empty( $output['post_types'] ) ) {
			$output['post_types'] = $defaults['post_types'];
		}

		$output['request_timeout']          = $defaults['request_timeout'];
		$output['max_redirects']            = $defaults['max_redirects'];
		$output['delete_data_on_uninstall'] = empty( $input['delete_data_on_uninstall'] ) ? 0 : 1;

		$slack_enabled     = empty( $input['slack_enabled'] ) ? 0 : 1;
		$slack_webhook_raw = isset( $input['slack_webhook_url'] ) ? trim( $input['slack_webhook_url'] ) : '';
		$slack_webhook_url = '';

		if ( '' !== $slack_webhook_raw && wp_http_validate_url( $slack_webhook_raw ) && 'https' === wp_parse_url( $slack_webhook_raw, PHP_URL_SCHEME ) ) {
			$slack_webhook_url = esc_url_raw( $slack_webhook_raw );
		}

		if ( $slack_enabled && '' === $slack_webhook_url ) {
			$slack_enabled = 0;
			add_settings_error(
				self::OPTION_KEY,
				'kwperf_slack_webhook_invalid',
				__( 'Slack notifications were turned off because the webhook URL is missing or invalid. It must be a valid https:// URL.', 'kw-performance' )
			);
		}

		$output['slack_enabled']     = $slack_enabled;
		$output['slack_webhook_url'] = $slack_webhook_url;

		// Reschedule cron if the interval, start time, or enabled state changed.
		if ( class_exists( 'KWPERF_Cron' ) ) {
			KWPERF_Cron::reschedule( $output['enable_scan'], $output['scan_interval'], $output['scan_time'] );
		}

		return $output;
	}

	/**
	 * Sanitize a comma-separated list of email addresses, dropping any
	 * that aren't valid and de-duplicating what's left.
	 *
	 * @param string $raw Raw comma-separated input.
	 * @return string Comma-separated list of valid, unique email addresses (possibly empty).
	 */
	public static function sanitize_email_list( $raw ) {
		$candidates = array_map( 'trim', explode( ',', (string) $raw ) );
		$valid      = array();

		foreach ( $candidates as $candidate ) {
			if ( '' === $candidate ) {
				continue;
			}
			if ( is_email( $candidate ) ) {
				$valid[] = sanitize_email( $candidate );
			}
		}

		return implode( ', ', array_values( array_unique( $valid ) ) );
	}

	/**
	 * Get the configured notification recipients as an array of individual addresses.
	 *
	 * @return string[]
	 */
	public static function get_email_recipients() {
		$raw = self::get( 'admin_email', get_option( 'admin_email' ) );
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $raw ) ), 'is_email' ) );
	}

	/**
	 * Render: Enable scan checkbox.
	 */
	public function render_enable_scan_field() {
		$value    = self::get( 'enable_scan' );
		$next_run = class_exists( 'KWPERF_Cron' ) ? wp_next_scheduled( KWPERF_Cron::HOOK ) : false;
		?>
		<label for="kwperf_enable_scan">
			<input type="checkbox" id="kwperf_enable_scan" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_scan]" value="1" <?php checked( $value, 1 ); ?> />
			<?php esc_html_e( 'Automatically scan the site on a schedule', 'kw-performance' ); ?>
		</label>
		<?php if ( $value && $next_run ) : ?>
			<p class="description">
				<?php
				$seconds_left = max( 0, $next_run - time() );
				$hours_left   = floor( $seconds_left / HOUR_IN_SECONDS );
				$mins_left    = floor( ( $seconds_left % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

				printf(
					/* translators: 1: next scan time, 2: hours until next scan, 3: minutes until next scan */
					esc_html__( '%1$s - Next scan in %2$dHr %3$dmins', 'kw-performance' ),
					esc_html( wp_date( get_option( 'time_format' ), $next_run ) ),
					(int) $hours_left,
					(int) $mins_left
				);
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render: Scan interval select.
	 */
	public function render_scan_interval_field() {
		$value   = self::get( 'scan_interval' );
		$options = array(
			'hourly'     => __( 'Hourly', 'kw-performance' ),
			'twicedaily' => __( 'Twice Daily', 'kw-performance' ),
			'daily'      => __( 'Every 24 Hours', 'kw-performance' ),
			'weekly'     => __( 'Weekly', 'kw-performance' ),
		);

		$scan_time = self::get( 'scan_time' );
		?>
		<select id="kwperf_scan_interval" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[scan_interval]">
			<?php foreach ( $options as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<select id="kwperf_scan_time" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[scan_time]" style="margin-left:6px;">
			<?php foreach ( self::get_time_options() as $time_value => $time_label ) : ?>
				<option value="<?php echo esc_attr( $time_value ); ?>" <?php selected( $scan_time, $time_value ); ?>><?php echo esc_html( $time_label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'How often the automated scan should run, and the time of day each cycle should start.', 'kw-performance' ); ?></p>
		<?php
	}

	/**
	 * Build the list of selectable start times, in 30-minute increments,
	 * for the scan interval field's time dropdown.
	 *
	 * @return array<string,string> Map of "HH:mm" value to a 12-hour label.
	 */
	private static function get_time_options() {
		$options = array();

		for ( $minutes = 0; $minutes < DAY_IN_SECONDS / MINUTE_IN_SECONDS; $minutes += 30 ) {
			$hour   = (int) floor( $minutes / 60 );
			$minute = $minutes % 60;
			$value  = sprintf( '%02d:%02d', $hour, $minute );

			$hour_12 = 0 === $hour % 12 ? 12 : $hour % 12;
			$meridiem = $hour < 12 ? __( 'AM', 'kw-performance' ) : __( 'PM', 'kw-performance' );

			$options[ $value ] = sprintf( '%d:%02d %s', $hour_12, $minute, $meridiem );
		}

		return $options;
	}

	/**
	 * Render: Admin notification email field.
	 */
	public function render_admin_email_field() {
		$value = self::get( 'admin_email' );
		?>
		<input type="text" class="regular-text" id="kwperf_admin_email" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[admin_email]" value="<?php echo esc_attr( $value ); ?>" placeholder="admin@example.com, editor@example.com" />
		<p class="description"><?php esc_html_e( 'Scan reports will be sent to this address. Separate multiple addresses with commas.', 'kw-performance' ); ?></p>
		<?php
	}

	/**
	 * Render: Notify on empty results checkbox.
	 */
	public function render_notify_on_empty_field() {
		$value = self::get( 'notify_on_empty' );
		?>
		<label for="kwperf_notify_on_empty">
			<input type="checkbox" id="kwperf_notify_on_empty" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[notify_on_empty]" value="1" <?php checked( $value, 1 ); ?> />
			<?php esc_html_e( 'Send an email even when no broken links are found', 'kw-performance' ); ?>
		</label>
		<?php
	}

	/**
	 * Render: Post types checkbox list.
	 */
	public function render_post_types_field() {
		$selected    = (array) self::get( 'post_types' );
		$post_types  = get_post_types( array( 'public' => true ), 'objects' );
		$all_checked = ! empty( $post_types ) && ! array_diff( wp_list_pluck( $post_types, 'name' ), $selected );
		?>
		<fieldset>
			<label style="display:block;margin-bottom:4px;">
				<input type="checkbox" id="kwperf_post_types_select_all" <?php checked( $all_checked ); ?> />
				<strong><?php esc_html_e( 'Select All', 'kw-performance' ); ?></strong>
			</label>
			<hr style="margin:6px 0;border-top:1px solid #dcdcde;max-width:200px;" />
			<?php foreach ( $post_types as $post_type ) : ?>
				<label style="display:block;margin-bottom:4px;">
					<input type="checkbox" class="kwperf-post-type-checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_types][]" value="<?php echo esc_attr( $post_type->name ); ?>" <?php checked( in_array( $post_type->name, $selected, true ) ); ?> />
					<?php echo esc_html( $post_type->labels->singular_name ); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p class="description"><?php esc_html_e( 'Content types included when crawling the site for links.', 'kw-performance' ); ?></p>
		<?php
	}

	/**
	 * Render: Enable Slack notifications checkbox.
	 */
	public function render_slack_enabled_field() {
		$value = self::get( 'slack_enabled' );
		?>
		<label for="kwperf_slack_enabled">
			<input type="checkbox" id="kwperf_slack_enabled" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[slack_enabled]" value="1" <?php checked( $value, 1 ); ?> />
			<?php esc_html_e( 'Send scan reports to a Slack channel via an Incoming Webhook', 'kw-performance' ); ?>
		</label>
		<?php
	}

	/**
	 * Render: Slack webhook URL field, with an inline "send test" button.
	 */
	public function render_slack_webhook_field() {
		$value = self::get( 'slack_webhook_url' );
		?>
		<input type="url" class="regular-text" id="kwperf_slack_webhook_url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[slack_webhook_url]" value="<?php echo esc_attr( $value ); ?>" placeholder="https://hooks.slack.com/services/…" />
		<button type="button" class="button" id="kwperf-test-slack"><?php esc_html_e( 'Send Test Notification', 'kw-performance' ); ?></button>
		<span id="kwperf-slack-test-result" class="kwperf-inline-result"></span>
		<p class="description"><?php esc_html_e( 'Create an Incoming Webhook at api.slack.com/apps, then paste its URL here. "Send Test Notification" uses whatever URL is currently in this field, even before you save.', 'kw-performance' ); ?></p>
		<?php
		$last_error = class_exists( 'KWPERF_Slack' ) ? KWPERF_Slack::get_last_error() : null;
		if ( $last_error ) :
			?>
			<p class="description" style="color:#b32d2e;">
				<?php
				printf(
					/* translators: 1: error message, 2: date/time of the failure */
					esc_html__( 'Last scan report failed to send to Slack: %1$s (%2$s)', 'kw-performance' ),
					esc_html( $last_error['message'] ),
					esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_error['time'] ) )
				);
				?>
			</p>
			<?php
		endif;
	}

	/**
	 * Render: Delete data on uninstall checkbox.
	 */
	public function render_delete_data_field() {
		$value = self::get( 'delete_data_on_uninstall' );
		?>
		<label for="kwperf_delete_data_on_uninstall">
			<input type="checkbox" id="kwperf_delete_data_on_uninstall" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[delete_data_on_uninstall]" value="1" <?php checked( $value, 1 ); ?> />
			<?php esc_html_e( 'Permanently delete all logs, scan history, and settings when this plugin is uninstalled', 'kw-performance' ); ?>
		</label>
		<?php
	}
}
