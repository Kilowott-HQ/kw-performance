<?php
/**
 * Slack notifications for scan results.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KWPERF_Slack
 *
 * Posts scan summaries to a Slack channel via an Incoming Webhook, using
 * Slack's Block Kit format for a readable, structured message.
 */
class KWPERF_Slack {

	/**
	 * Maximum number of individual broken links listed in one Slack message.
	 *
	 * @var int
	 */
	const MAX_LISTED_LINKS = 10;

	/**
	 * Option used to surface the last scan-report send failure on the
	 * settings page, since cron and manual scans don't otherwise show
	 * the caller anything if the Slack post fails.
	 *
	 * @var string
	 */
	const LAST_ERROR_OPTION = 'kwperf_slack_last_error';

	/**
	 * Send the scan report to Slack if enabled and there are broken links
	 * (or always, if "notify even when nothing is broken" is on).
	 *
	 * @param array $summary Scan summary from KWPERF_Scanner::run_scan().
	 * @return true|WP_Error|false False when Slack notifications are off or skipped by the empty-result setting.
	 */
	public function maybe_send_scan_report( $summary ) {
		if ( ! KWPERF_Settings::get( 'slack_enabled', 0 ) ) {
			return false;
		}

		$notify_on_empty = KWPERF_Settings::get( 'notify_on_empty', 0 );

		if ( empty( $summary['broken_links_found'] ) && ! $notify_on_empty ) {
			return false;
		}

		$result = $this->post_message( $this->build_payload( $summary ) );

		if ( is_wp_error( $result ) ) {
			update_option(
				self::LAST_ERROR_OPTION,
				array(
					'message' => $result->get_error_message(),
					'time'    => current_time( 'mysql' ),
				),
				false
			);
		} else {
			delete_option( self::LAST_ERROR_OPTION );
		}

		return $result;
	}

	/**
	 * Get the last recorded scan-report send failure, if any.
	 *
	 * @return array{message:string,time:string}|null
	 */
	public static function get_last_error() {
		$error = get_option( self::LAST_ERROR_OPTION );
		return $error ? $error : null;
	}

	/**
	 * Send a simple confirmation message to verify a webhook URL works.
	 * Used by the "Send Test Notification" button, before the URL is even saved.
	 *
	 * @param string $webhook_url Webhook URL to test.
	 * @return true|WP_Error
	 */
	public function send_test( $webhook_url ) {
		$payload = array(
			'text' => sprintf(
				/* translators: %s: site name */
				__( ':white_check_mark: KW Performance is connected to Slack for %s.', 'kw-performance' ),
				wp_specialchars_decode( get_bloginfo( 'name' ) )
			),
		);

		return $this->post_message( $payload, $webhook_url );
	}

	/**
	 * Build the Slack Block Kit payload for a scan summary.
	 *
	 * @param array $summary Scan summary data.
	 * @return array
	 */
	private function build_payload( $summary ) {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ) );
		$broken    = (int) $summary['broken_links_found'];
		$logs_url  = admin_url( 'admin.php?page=kwperf-logs' );

		$headline = $broken > 0
			? sprintf(
				/* translators: 1: site name, 2: number of broken links found */
				__( ':rotating_light: %1$s — %2$d broken link(s) found', 'kw-performance' ),
				$site_name,
				$broken
			)
			: sprintf(
				/* translators: %s: site name */
				__( ':white_check_mark: %s — no broken links found', 'kw-performance' ),
				$site_name
			);

		// Slack's "header" block enforces a hard 150-character limit on plain_text.
		if ( mb_strlen( $headline ) > 150 ) {
			$headline = mb_substr( $headline, 0, 149 ) . '…';
		}

		$blocks = array(
			array(
				'type' => 'header',
				'text' => array(
					'type'  => 'plain_text',
					'text'  => $headline,
					'emoji' => true,
				),
			),
			array(
				'type'   => 'section',
				'fields' => array(
					array(
						'type' => 'mrkdwn',
						'text' => "*Pages Scanned*\n" . (int) $summary['pages_scanned'],
					),
					array(
						'type' => 'mrkdwn',
						'text' => "*Links Checked*\n" . (int) $summary['links_scanned'],
					),
					array(
						'type' => 'mrkdwn',
						'text' => "*Broken Links*\n" . $broken,
					),
					array(
						'type' => 'mrkdwn',
						'text' => "*Working Links*\n" . (int) $summary['working_links'],
					),
				),
			),
		);

		$occurrences = isset( $summary['broken_occurrences'] ) ? $summary['broken_occurrences'] : array();
		$groups      = $this->group_occurrences_by_url( $occurrences );

		if ( ! empty( $groups ) ) {
			// Slack's "section" block enforces a hard 3000-character limit on mrkdwn
			// text, so lines are added one at a time and stop once they'd exceed it —
			// long URLs/titles on a real site can hit that limit well before 10 links.
			$max_section_length = 3000;
			$lines              = array();
			$listed             = 0;
			$running_length     = 0;

			foreach ( array_slice( $groups, 0, self::MAX_LISTED_LINKS ) as $group ) {
				$found_on_lines = array();

				// The same broken link commonly appears on more than one page (e.g. a
				// sitewide menu/header), so every page it was found on gets its own
				// "Found on" line grouped under one entry, instead of a separate,
				// identical-looking top-level entry per page.
				foreach ( $group['found_on'] as $location ) {
					$page_label = $location['source_title'] ? $location['source_title'] : $location['source_permalink'];

					$found_on_lines[] = sprintf(
						__( 'Found on', 'kw-performance' ) . ' <%s|%s>%s',
						$this->escape_mrkdwn( esc_url_raw( $location['source_permalink'] ) ),
						$this->escape_mrkdwn( $page_label ),
						$location['section'] ? ' — _' . $this->escape_mrkdwn( $location['section'] ) . '_' : ''
					);
				}

				$line = sprintf(
					"*%d* — <%s|%s>\n%s",
					(int) $group['http_status'],
					$this->escape_mrkdwn( esc_url_raw( $group['broken_url'] ) ),
					$this->escape_mrkdwn( $group['broken_url'] ),
					implode( "\n", $found_on_lines )
				);

				// Reserve room for a trailing "…and N more." line (~60 chars is generous).
				if ( $running_length + mb_strlen( $line ) + 2 > $max_section_length - 60 ) {
					break;
				}

				$lines[]         = $line;
				$running_length += mb_strlen( $line ) + 2;
				++$listed;
			}

			$remaining = count( $groups ) - $listed;
			if ( $remaining > 0 ) {
				$lines[] = sprintf(
					/* translators: %d: number of additional broken links not shown in the message */
					_n( '…and %d more.', '…and %d more.', $remaining, 'kw-performance' ),
					$remaining
				);
			}

			$blocks[] = array( 'type' => 'divider' );
			$blocks[] = array(
				'type' => 'section',
				'text' => array(
					'type' => 'mrkdwn',
					'text' => implode( "\n\n", $lines ),
				),
			);
		}

		$blocks[] = array(
			'type'     => 'actions',
			'elements' => array(
				array(
					'type' => 'button',
					'text' => array(
						'type' => 'plain_text',
						'text' => __( 'View Full Log', 'kw-performance' ),
					),
					'url'  => $logs_url,
				),
			),
		);

		return array(
			'text'   => $headline, // Fallback text for notifications/clients that don't render blocks.
			'blocks' => $blocks,
		);
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
	 * Escape the characters Slack's mrkdwn format requires to be escaped
	 * (https://api.slack.com/reference/surfaces/formatting#escaping) so that
	 * page titles, URLs, or section names containing "&", "<", or ">" don't
	 * produce a malformed message that Slack rejects outright.
	 *
	 * @param string $text Raw text to include in a mrkdwn field.
	 * @return string
	 */
	private function escape_mrkdwn( $text ) {
		return str_replace( array( '&', '<', '>' ), array( '&amp;', '&lt;', '&gt;' ), $text );
	}

	/**
	 * POST a Block Kit payload to a Slack Incoming Webhook.
	 *
	 * @param array  $payload     Slack message payload.
	 * @param string $webhook_url Webhook URL to use instead of the saved setting (optional).
	 * @return true|WP_Error
	 */
	private function post_message( $payload, $webhook_url = '' ) {
		if ( '' === $webhook_url ) {
			$webhook_url = KWPERF_Settings::get( 'slack_webhook_url', '' );
		}

		if ( '' === $webhook_url || ! wp_http_validate_url( $webhook_url ) ) {
			return new WP_Error( 'kwperf_slack_no_webhook', __( 'No valid Slack webhook URL is configured.', 'kw-performance' ) );
		}

		$response = wp_safe_remote_post(
			$webhook_url,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'kwperf_slack_http_error',
				sprintf(
					/* translators: 1: HTTP status code, 2: response body from Slack */
					__( 'Slack responded with HTTP %1$d: %2$s', 'kw-performance' ),
					$code,
					wp_remote_retrieve_body( $response )
				)
			);
		}

		return true;
	}
}
