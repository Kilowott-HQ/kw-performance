<?php
/**
 * Front-end Google Tag Manager container injection.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KWPERF_Gtm
 *
 * Reads the configured GTM container ID(s) and "Container code placement"
 * setting, then injects the dataLayer init + container snippets at the
 * right point in the page depending on that placement mode:
 *
 * - footer:    both the head-style script and the noscript iframe are
 *              printed on wp_footer (simplest, but delays tracking).
 * - custom:    the head-style script still prints on wp_head, but the
 *              noscript iframe is left for the theme to place manually via
 *              the kwperf_the_gtm_tag() template tag.
 * - codeless:  the head-style script prints on wp_head, and the noscript
 *              iframe is injected automatically right after the opening
 *              <body> tag via output buffering — no theme edit needed, but
 *              (like any output-buffer HTML rewrite) it's the one mode that
 *              could misfire on an unusual theme/plugin combination.
 * - off:       only the dataLayer initializer is printed; no container
 *              script/iframe at all.
 */
class KWPERF_Gtm {

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'maybe_output_head_snippet' ), 1 );
		add_action( 'wp_footer', array( $this, 'maybe_output_footer_snippet' ) );
		add_action( 'template_redirect', array( $this, 'maybe_start_codeless_buffer' ) );
	}

	/**
	 * Parse the configured GTM ID(s) setting into a clean list.
	 *
	 * @return string[]
	 */
	public static function get_ids() {
		$raw = (string) KWPERF_Settings::get( 'gtm_ids', '' );

		if ( '' === trim( $raw ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
	}

	/**
	 * The configured placement mode: 'footer', 'custom', 'codeless', or 'off'.
	 *
	 * @return string
	 */
	private static function get_placement() {
		return (string) KWPERF_Settings::get( 'gtm_placement', 'codeless' );
	}

	/**
	 * The dataLayer initializer, printed in every mode (including 'off') so
	 * events pushed before the container itself loads aren't lost.
	 *
	 * @return string
	 */
	private static function data_layer_script() {
		return "<script>window.dataLayer = window.dataLayer || [];</script>\n";
	}

	/**
	 * The standard GTM container <script> snippet for one container ID,
	 * normally placed as high in <head> as possible.
	 *
	 * @param string $id GTM container ID (already validated on save).
	 * @return string
	 */
	private static function container_script( $id ) {
		$id = esc_js( $id );

		return "<!-- Google Tag Manager -->\n"
			. "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n"
			. "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n"
			. "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n"
			. "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n"
			. "})(window,document,'script','dataLayer','{$id}');</script>\n"
			. "<!-- End Google Tag Manager -->\n";
	}

	/**
	 * The <noscript> fallback iframe for one container ID, normally placed
	 * immediately after the opening <body> tag.
	 *
	 * @param string $id GTM container ID (already validated on save).
	 * @return string
	 */
	private static function noscript_snippet( $id ) {
		$src = esc_url( 'https://www.googletagmanager.com/ns.html?id=' . rawurlencode( $id ) );

		return "<!-- Google Tag Manager (noscript) -->\n"
			. '<noscript><iframe src="' . $src . '" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n"
			. "<!-- End Google Tag Manager (noscript) -->\n";
	}

	/**
	 * Render the noscript iframe(s) for every configured container.
	 *
	 * Public template tag — themes call this manually right after their
	 * opening <body> tag when "Container code placement" is set to Custom.
	 * Works regardless of the current placement setting, so it's also a
	 * safe manual override in any mode.
	 *
	 * @return string
	 */
	public static function render_noscript_tags() {
		$output = '';
		foreach ( self::get_ids() as $id ) {
			$output .= self::noscript_snippet( $id );
		}
		return $output;
	}

	/**
	 * wp_head: print the dataLayer initializer always (when at least one ID
	 * is configured), plus the container <script> snippet(s) for every mode
	 * except 'footer' (deferred to wp_footer instead) and 'off' (script is
	 * intentionally withheld).
	 */
	public function maybe_output_head_snippet() {
		if ( is_admin() || is_feed() ) {
			return;
		}

		$ids = self::get_ids();
		if ( empty( $ids ) ) {
			return;
		}

		echo self::data_layer_script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$placement = self::get_placement();
		if ( in_array( $placement, array( 'footer', 'off' ), true ) ) {
			return;
		}

		foreach ( $ids as $id ) {
			echo self::container_script( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * wp_footer: in 'footer' mode only, print both the container script(s)
	 * and the noscript iframe(s) here instead of near the top of the page.
	 */
	public function maybe_output_footer_snippet() {
		if ( is_admin() || is_feed() || 'footer' !== self::get_placement() ) {
			return;
		}

		$ids = self::get_ids();
		if ( empty( $ids ) ) {
			return;
		}

		foreach ( $ids as $id ) {
			echo self::container_script( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		foreach ( $ids as $id ) {
			echo self::noscript_snippet( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * template_redirect: in 'codeless' mode only, buffer the entire response
	 * so the noscript iframe(s) can be spliced in right after the opening
	 * <body> tag without any theme changes. Skipped for admin/AJAX/REST/feed
	 * requests, where there either is no <body> or rewriting one would be
	 * actively harmful.
	 */
	public function maybe_start_codeless_buffer() {
		if ( 'codeless' !== self::get_placement() || empty( self::get_ids() ) ) {
			return;
		}

		if ( is_admin() || is_feed() || wp_doing_ajax() || wp_is_json_request() ) {
			return;
		}

		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		ob_start( array( $this, 'inject_after_body_tag' ) );
	}

	/**
	 * Output buffer callback for codeless injection: splice the noscript
	 * iframe(s) in right after the first opening <body> tag found in the
	 * final rendered HTML. If no <body> tag is found (e.g. the response
	 * wasn't actually an HTML page), the buffer is returned unchanged.
	 *
	 * @param string $html Full buffered page output.
	 * @return string
	 */
	public function inject_after_body_tag( $html ) {
		$snippet = self::render_noscript_tags();

		if ( '' === $snippet ) {
			return $html;
		}

		$replaced = preg_replace( '/(<body\b[^>]*>)/i', '$1' . $snippet, $html, 1 );

		return null !== $replaced ? $replaced : $html;
	}
}

/**
 * Template tag: output the GTM noscript iframe(s) for every configured
 * container. Call this immediately after your theme's opening <body> tag
 * when KW Performance's Tracking screen has "Container code placement" set
 * to Custom.
 */
if ( ! function_exists( 'kwperf_the_gtm_tag' ) ) {
	function kwperf_the_gtm_tag() {
		echo KWPERF_Gtm::render_noscript_tags(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
