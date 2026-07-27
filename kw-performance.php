<?php
/**
 * Plugin Name:       KW Performance
 * Plugin URI:        https://kilowott.com
 * Description:       Automatically crawls your frontend pages, posts, and custom post types to detect broken links (404s, 410s, broken redirects, and server errors), logs them with page/section context, and notifies admins on a schedule.
 * Version:           26.07.01
 * Requires at least: 5.8
 * Requires PHP:      8.0
 * Author:            KW Developers
 * Author URI:        https://kilowott.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       kw-performance
 * Domain Path:       /languages
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'KWPERF_VERSION', '26.07.01' );
define( 'KWPERF_PLUGIN_FILE', __FILE__ );
define( 'KWPERF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KWPERF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KWPERF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'KWPERF_DB_VERSION', '1.0.0' );

/**
 * Autoload plugin classes on demand.
 *
 * @param string $class_name Fully qualified class name.
 */
function kwperf_autoloader( $class_name ) {
	if ( 0 !== strpos( $class_name, 'KWPERF_' ) ) {
		return;
	}

	$file_name = 'class-' . strtolower( str_replace( '_', '-', substr( $class_name, 7 ) ) ) . '.php';
	$file_path = KWPERF_PLUGIN_DIR . 'includes/' . $file_name;

	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
}
spl_autoload_register( 'kwperf_autoloader' );

// Self-hosted update checker (GitHub releases) — not a KWPERF_ class, so it
// isn't picked up by the autoloader above and needs an explicit require.
require_once KWPERF_PLUGIN_DIR . 'includes/updater.php';

/**
 * Returns the single instance of the core plugin class, instantiating it on first call.
 *
 * @return KWPERF_Plugin
 */
function kwperf() {
	return KWPERF_Plugin::instance();
}

// Boot the plugin.
kwperf();

// Activation / deactivation hooks.
register_activation_hook( __FILE__, array( 'KWPERF_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'KWPERF_Plugin', 'deactivate' ) );
