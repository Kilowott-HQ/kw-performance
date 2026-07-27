<?php
/**
 * KW Performance – Plugin Update Checker
 *
 * Hooks into WordPress's update system to surface new releases from the
 * plugin's GitHub repository on the Dashboard > Updates and Plugins screens.
 *
 * Release workflow:
 *   1. Bump Version in kw-performance.php (both plugin header and KWPERF_VERSION).
 *   2. Create a GitHub release tagged with that exact version string (e.g. 26.05.05).
 *   3. Attach a zip whose root folder is named "kw-performance" — WP uses this
 *      as the plugin directory name when installing the update.
 *
 * The update notice will appear on Plugins > Installed Plugins and
 * Dashboard > Updates just like any WordPress.org plugin.
 *
 * @package KW_Performance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once KWPERF_PLUGIN_DIR . 'vendor/plugin-update-checker/load-v5p6.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

PucFactory::buildUpdateChecker(
	'https://github.com/Kilowott-HQ/kw-performance/',
	KWPERF_PLUGIN_FILE,
	'kw-performance'
);
