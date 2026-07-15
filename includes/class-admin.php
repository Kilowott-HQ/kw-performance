<?php
/**
 * Admin menus, screens, and asset loading.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KWPERF_Admin
 *
 * Registers the plugin's admin menu pages and renders their templates.
 */
class KWPERF_Admin {

	/**
	 * Page hook suffixes registered by this plugin, used to scope asset loading.
	 *
	 * @var string[]
	 */
	private $page_hooks = array();

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'maybe_render_settings_notice' ) );
		add_filter( 'plugin_action_links_' . KWPERF_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Register the admin menu and submenus.
	 */
	public function register_menus() {
		$this->page_hooks[] = add_menu_page(
			__( 'KW Performance', 'kw-performance' ),
			__( 'KW Performance', 'kw-performance' ),
			'manage_options',
			'kwperf-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-editor-unlink',
			80
		);

		$this->page_hooks[] = add_submenu_page(
			'kwperf-settings',
			__( 'Settings', 'kw-performance' ),
			__( 'Settings', 'kw-performance' ),
			'manage_options',
			'kwperf-settings',
			array( $this, 'render_settings_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'kwperf-settings',
			__( '404 Log', 'kw-performance' ),
			__( '404 Log', 'kw-performance' ),
			'manage_options',
			'kwperf-logs',
			array( $this, 'render_logs_page' )
		);

		$this->page_hooks[] = add_submenu_page(
			'kwperf-settings',
			__( 'Scan History', 'kw-performance' ),
			__( 'Scan History', 'kw-performance' ),
			'manage_options',
			'kwperf-scan-history',
			array( $this, 'render_scan_history_page' )
		);
	}

	/**
	 * Enqueue admin CSS/JS only on this plugin's own screens.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, $this->page_hooks, true ) ) {
			return;
		}

		wp_enqueue_style( 'kwperf-admin', KWPERF_PLUGIN_URL . 'assets/css/admin.css', array(), KWPERF_VERSION );

		wp_enqueue_script( 'kwperf-admin', KWPERF_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), KWPERF_VERSION, true );

		wp_localize_script(
			'kwperf-admin',
			'kwperfAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'kwperf_admin_nonce' ),
				'i18n'    => array(
					'scanning'            => __( 'Scanning your site for broken links… this may take a while.', 'kw-performance' ),
					/* translators: %1$d and %2$d are replaced client-side with the current and total page counts. */
					'pageProgress'        => __( 'Page %1$d of %2$d', 'kw-performance' ),
					'scanComplete'        => __( 'Scan complete!', 'kw-performance' ),
					'scanFailed'          => __( 'Scan failed. Please try again.', 'kw-performance' ),
					'confirmClear'        => __( 'Are you sure you want to clear all logs? This cannot be undone.', 'kw-performance' ),
					'confirmClearHistory' => __( 'Are you sure you want to clear all scan history? This cannot be undone.', 'kw-performance' ),
					'confirmDelete'       => __( 'Delete this log entry?', 'kw-performance' ),
					'confirmBulk'         => __( 'Delete the selected log entries?', 'kw-performance' ),
					'noSelection'         => __( 'Please select at least one row.', 'kw-performance' ),
					'testingSlack'        => __( 'Sending test message…', 'kw-performance' ),
					'noWebhookUrl'        => __( 'Enter a webhook URL first.', 'kw-performance' ),
					'testSlackFailed'     => __( 'Could not reach Slack. Please check the webhook URL.', 'kw-performance' ),
					'rechecking'          => __( 'Rechecking selected links…', 'kw-performance' ),
				),
			)
		);
	}

	/**
	 * Show a "settings saved" admin notice.
	 */
	public function maybe_render_settings_notice() {
		if ( ! isset( $_GET['page'], $_GET['settings-updated'] ) || 'kwperf-settings' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'kw-performance' ) . '</p></div>';
	}

	/**
	 * Add a "Settings" link to this plugin's row on the Plugins screen.
	 *
	 * @param string[] $links Existing action links (Deactivate, etc.).
	 * @return string[]
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=kwperf-settings' ) ),
			esc_html__( 'Settings', 'kw-performance' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Render the Settings screen.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'kw-performance' ) );
		}

		$stats = KWPERF_Logger::get_summary_stats();
		include KWPERF_PLUGIN_DIR . 'templates/settings-page.php';
	}

	/**
	 * Render the 404 Log screen.
	 */
	public function render_logs_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'kw-performance' ) );
		}

		$list_table = new KWPERF_Logs_List_Table();
		$list_table->prepare_items();

		include KWPERF_PLUGIN_DIR . 'templates/logs-page.php';
	}

	/**
	 * Render the Scan History screen.
	 */
	public function render_scan_history_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'kw-performance' ) );
		}

		$list_table = new KWPERF_History_List_Table();
		$list_table->prepare_items();

		include KWPERF_PLUGIN_DIR . 'templates/scan-history-page.php';
	}
}
