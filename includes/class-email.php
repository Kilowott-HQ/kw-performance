<?php
/**
 * Email notifications for scan results.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KWPERF_Email
 *
 * Builds and sends the HTML scan report email.
 */
class KWPERF_Email {

	/**
	 * Send the scan report email if there are broken links (or always, if configured).
	 *
	 * @param array $summary Scan summary from KWPERF_Scanner::run_scan().
	 * @return bool Whether an email was sent.
	 */
	public function maybe_send_scan_report( $summary ) {
		$notify_on_empty = KWPERF_Settings::get( 'notify_on_empty', 0 );

		if ( empty( $summary['broken_links_found'] ) && ! $notify_on_empty ) {
			return false;
		}

		$to = KWPERF_Settings::get_email_recipients();

		if ( empty( $to ) ) {
			return false;
		}

		$subject = sprintf(
			/* translators: 1: site name, 2: number of broken links found */
			__( '[%1$s] KW Performance Scan Report — %2$d broken link(s) found', 'kw-performance' ),
			wp_specialchars_decode( get_bloginfo( 'name' ) ),
			(int) $summary['broken_links_found']
		);

		$message = $this->build_message( $summary );

		add_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );
		add_action( 'wp_mail_failed', array( $this, 'log_mail_error' ) );

		$sent = wp_mail( $to, $subject, $message );

		remove_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );
		remove_action( 'wp_mail_failed', array( $this, 'log_mail_error' ) );

		if ( $sent ) {
			delete_option( 'kwperf_last_email_error' );
		}

		return $sent;
	}

	/**
	 * Force HTML content type for the notification email.
	 *
	 * @return string
	 */
	public function set_html_content_type() {
		return 'text/html';
	}

	/**
	 * Record the real reason wp_mail() failed, since it otherwise fails silently.
	 * Surfaced on the Settings screen so a broken mail transport is visible
	 * without needing server log access.
	 *
	 * @param WP_Error $wp_error Error from PHPMailer/wp_mail.
	 */
	public function log_mail_error( $wp_error ) {
		update_option(
			'kwperf_last_email_error',
			array(
				'message' => $wp_error->get_error_message(),
				'date'    => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Render the HTML email body from a template.
	 *
	 * @param array $summary Scan summary data.
	 * @return string
	 */
	private function build_message( $summary ) {
		$broken_occurrences = isset( $summary['broken_occurrences'] ) ? $summary['broken_occurrences'] : array();
		$broken_groups      = $this->group_occurrences_by_url( $broken_occurrences );
		$logo_url           = $this->get_site_logo_url();

		ob_start();
		include KWPERF_PLUGIN_DIR . 'templates/email-scan-report.php';
		return ob_get_clean();
	}

	/**
	 * Group broken link occurrences by URL, since the same broken link
	 * commonly appears on more than one page (e.g. a sitewide menu/header) —
	 * without this, it would be listed as a separate, identical-looking entry
	 * once per page instead of once with all the pages it was found on.
	 *
	 * @param array $occurrences Flat list of broken link occurrences.
	 * @return array List of array{broken_url:string,http_status:int,found_on:array}.
	 */
	private function group_occurrences_by_url( $occurrences ) {
		$groups = array();

		foreach ( $occurrences as $item ) {
			$key = $item['broken_url'];

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'broken_url'  => $item['broken_url'],
					'http_status' => $item['http_status'],
					'found_on'    => array(),
				);
			}

			$groups[ $key ]['found_on'][] = array(
				'source_permalink' => $item['source_permalink'],
				'source_title'     => $item['source_title'],
				'section'          => $item['section'],
			);
		}

		return array_values( $groups );
	}

	/**
	 * Get the site's Customizer-set logo URL, if one is set.
	 *
	 * Wide/rectangular logos (text + icon lockups) use the full-resolution
	 * original, since forcing them into a 300x300 intermediate can pick a
	 * square-cropped version that chops off most of the logo. Square/icon-only
	 * logos are downsized to 300x300 to avoid emailing a huge original file.
	 *
	 * @return string Logo URL, or an empty string if no logo is configured.
	 */
	private function get_site_logo_url() {
		$custom_logo_id = get_theme_mod( 'custom_logo' );

		if ( ! $custom_logo_id ) {
			return '';
		}

		$full = wp_get_attachment_image_src( $custom_logo_id, 'full' );

		if ( ! $full ) {
			return '';
		}

		list( $full_url, $full_width, $full_height ) = $full;

		if ( $full_width > $full_height * 1.2 ) {
			return $full_url;
		}

		$thumb = wp_get_attachment_image_src( $custom_logo_id, array( 300, 300 ) );

		return $thumb ? $thumb[0] : $full_url;
	}
}
