<?php
/**
 * Reads and writes SEO meta title/description, staying in sync with
 * whichever SEO plugin (Yoast SEO, Rank Math) is active.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KWPERF_Meta_Manager
 *
 * Single point of read/write access for a post's meta title and meta
 * description, so the Metas admin screen and any other caller don't need
 * to know which SEO plugin (if any) actually owns that data.
 */
class KWPERF_Meta_Manager {

	const YOAST_TITLE_KEY = '_yoast_wpseo_title';
	const YOAST_DESC_KEY  = '_yoast_wpseo_metadesc';

	const RANKMATH_TITLE_KEY = 'rank_math_title';
	const RANKMATH_DESC_KEY  = 'rank_math_description';

	const FALLBACK_TITLE_KEY = '_kwperf_meta_title';
	const FALLBACK_DESC_KEY  = '_kwperf_meta_description';

	/**
	 * Whether Yoast SEO is active.
	 *
	 * @return bool
	 */
	public static function is_yoast_active() {
		return defined( 'WPSEO_VERSION' );
	}

	/**
	 * Whether Rank Math is active.
	 *
	 * @return bool
	 */
	public static function is_rankmath_active() {
		return defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' );
	}

	/**
	 * Get a post's meta title.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_title( $post_id ) {
		return self::read( $post_id, self::YOAST_TITLE_KEY, self::RANKMATH_TITLE_KEY, self::FALLBACK_TITLE_KEY );
	}

	/**
	 * Get a post's meta description.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function get_description( $post_id ) {
		return self::read( $post_id, self::YOAST_DESC_KEY, self::RANKMATH_DESC_KEY, self::FALLBACK_DESC_KEY );
	}

	/**
	 * Save a post's meta title.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $value   New value.
	 */
	public static function save_title( $post_id, $value ) {
		self::write( $post_id, $value, self::YOAST_TITLE_KEY, self::RANKMATH_TITLE_KEY, self::FALLBACK_TITLE_KEY );
	}

	/**
	 * Save a post's meta description.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $value   New value.
	 */
	public static function save_description( $post_id, $value ) {
		self::write( $post_id, $value, self::YOAST_DESC_KEY, self::RANKMATH_DESC_KEY, self::FALLBACK_DESC_KEY );
	}

	/**
	 * Read a meta value from whichever SEO plugin is active (Yoast preferred
	 * when both are active), falling back to KW Performance's own meta key
	 * when neither is active.
	 *
	 * @param int    $post_id      Post ID.
	 * @param string $yoast_key    Yoast SEO meta key.
	 * @param string $rankmath_key Rank Math meta key.
	 * @param string $fallback_key KW Performance's own meta key.
	 * @return string
	 */
	private static function read( $post_id, $yoast_key, $rankmath_key, $fallback_key ) {
		if ( self::is_yoast_active() ) {
			return (string) get_post_meta( $post_id, $yoast_key, true );
		}

		if ( self::is_rankmath_active() ) {
			return (string) get_post_meta( $post_id, $rankmath_key, true );
		}

		return (string) get_post_meta( $post_id, $fallback_key, true );
	}

	/**
	 * Write a meta value to every active SEO plugin's meta key (so Yoast and
	 * Rank Math both stay in sync if a site runs both), or to KW Performance's
	 * own meta key when neither plugin is active.
	 *
	 * @param int    $post_id      Post ID.
	 * @param string $value        New value.
	 * @param string $yoast_key    Yoast SEO meta key.
	 * @param string $rankmath_key Rank Math meta key.
	 * @param string $fallback_key KW Performance's own meta key.
	 */
	private static function write( $post_id, $value, $yoast_key, $rankmath_key, $fallback_key ) {
		$wrote_to_seo_plugin = false;

		if ( self::is_yoast_active() ) {
			update_post_meta( $post_id, $yoast_key, $value );
			$wrote_to_seo_plugin = true;
		}

		if ( self::is_rankmath_active() ) {
			update_post_meta( $post_id, $rankmath_key, $value );
			$wrote_to_seo_plugin = true;
		}

		if ( ! $wrote_to_seo_plugin ) {
			update_post_meta( $post_id, $fallback_key, $value );
		}
	}

	/**
	 * Human-readable label describing where meta title/description edits are
	 * currently saved, shown on the Metas admin screen.
	 *
	 * @return string
	 */
	public static function source_label() {
		$labels = array();

		if ( self::is_yoast_active() ) {
			$labels[] = __( 'Yoast SEO', 'kw-performance' );
		}

		if ( self::is_rankmath_active() ) {
			$labels[] = __( 'Rank Math', 'kw-performance' );
		}

		if ( empty( $labels ) ) {
			return __( 'KW Performance (no SEO plugin detected)', 'kw-performance' );
		}

		return implode( ' + ', $labels );
	}
}
