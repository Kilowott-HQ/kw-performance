<?php
/**
 * Core plugin bootstrap: singleton, hook wiring, and lifecycle handlers.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KWPERF_Plugin
 *
 * Central singleton that instantiates the plugin's collaborating classes
 * and handles activation/deactivation lifecycle hooks.
 */
class KWPERF_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var KWPERF_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings sub-system.
	 *
	 * @var KWPERF_Settings
	 */
	public $settings;

	/**
	 * Cron sub-system.
	 *
	 * @var KWPERF_Cron
	 */
	public $cron;

	/**
	 * AJAX sub-system.
	 *
	 * @var KWPERF_Ajax
	 */
	public $ajax;

	/**
	 * Admin sub-system.
	 *
	 * @var KWPERF_Admin
	 */
	public $admin;

	/**
	 * Get (and lazily create) the singleton instance.
	 *
	 * @return KWPERF_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor: wire up sub-systems and load the text domain.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'init_subsystems' ) );
	}

	/**
	 * Load plugin translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'kw-performance', false, dirname( KWPERF_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Instantiate collaborating classes once all plugins have loaded.
	 */
	public function init_subsystems() {
		$this->settings = new KWPERF_Settings();
		$this->cron     = new KWPERF_Cron();
		$this->ajax     = new KWPERF_Ajax();

		if ( is_admin() ) {
			$this->admin = new KWPERF_Admin();
		}
	}

	/**
	 * Plugin activation: create tables, set defaults, schedule cron.
	 */
	public static function activate() {
		KWPERF_Database::create_tables();
		KWPERF_Database::maybe_migrate_from_kw404();

		// Clean up any orphaned cron event left by the plugin's previous name.
		$old_cron_timestamp = wp_next_scheduled( 'kw404_scheduled_scan' );
		while ( $old_cron_timestamp ) {
			wp_unschedule_event( $old_cron_timestamp, 'kw404_scheduled_scan' );
			$old_cron_timestamp = wp_next_scheduled( 'kw404_scheduled_scan' );
		}

		if ( false === get_option( 'kwperf_settings', false ) ) {
			add_option( 'kwperf_settings', KWPERF_Settings::defaults() );
		}

		KWPERF_Cron::schedule();

		if ( false === get_option( 'kwperf_last_scan', false ) ) {
			add_option(
				'kwperf_last_scan',
				array(
					'date'               => '',
					'pages_scanned'      => 0,
					'links_scanned'      => 0,
					'broken_links_found' => 0,
					'working_links'      => 0,
				)
			);
		}
	}

	/**
	 * Plugin deactivation: unschedule cron. Data and settings are preserved.
	 */
	public static function deactivate() {
		KWPERF_Cron::unschedule();
		delete_transient( 'kwperf_scan_in_progress' );
	}
}
