<?php
/**
 * Frontend crawler, link extractor, and URL validator.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KWPERF_Scanner
 *
 * Crawls public frontend content, extracts links, validates them over HTTP,
 * detects the surrounding page section, and persists results via KWPERF_Logger.
 */
class KWPERF_Scanner {

	/**
	 * In-memory cache of URL check results for the current scan run.
	 * Prevents re-requesting the same URL twice during one scan.
	 *
	 * @var array<string,array>
	 */
	private $url_cache = array();

	/**
	 * Aggregate counters for the current scan run.
	 *
	 * @var array
	 */
	private $stats = array(
		'pages_scanned'      => 0,
		'links_scanned'      => 0,
		'broken_links_found' => 0,
		'working_links'      => 0,
		'errors'             => array(),
	);

	/**
	 * Broken link occurrences collected during the current scan run.
	 * Each item includes source page + section context.
	 *
	 * @var array
	 */
	private $broken_occurrences = array();

	/**
	 * Run a full site scan.
	 *
	 * @param string $scan_type 'manual' or 'cron'.
	 * @return array Scan summary.
	 */
	public function run_scan( $scan_type = 'manual' ) {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$start_time  = microtime( true );
		$scan_start  = current_time( 'mysql' );

		$this->url_cache          = array();
		$this->stats              = array(
			'pages_scanned'      => 0,
			'links_scanned'      => 0,
			'broken_links_found' => 0,
			'working_links'      => 0,
			'errors'             => array(),
		);
		$this->broken_occurrences = array();

		$pages       = $this->get_pages_to_scan();
		$total_pages = count( $pages );

		$this->update_progress( 0, $total_pages );

		foreach ( $pages as $index => $page ) {
			$this->scan_page( $page['url'], $page['id'], $page['title'] );
			$this->update_progress( $index + 1, $total_pages );
		}

		// Persist broken link occurrences (insert new / update existing detection counts).
		foreach ( $this->broken_occurrences as $occurrence ) {
			KWPERF_Logger::upsert_log( $occurrence );
		}

		// Remove log rows that were not re-confirmed as broken in this run (i.e. resolved issues).
		KWPERF_Logger::purge_stale_logs( $scan_start );

		$duration = round( microtime( true ) - $start_time, 2 );

		$history_id = KWPERF_Logger::record_scan_history(
			array(
				'scan_date'           => $scan_start,
				'duration'            => $duration,
				'pages_scanned'       => $this->stats['pages_scanned'],
				'links_scanned'       => $this->stats['links_scanned'],
				'broken_links_found'  => $this->stats['broken_links_found'],
				'working_links'       => $this->stats['working_links'],
				'errors_encountered'  => implode( "\n", $this->stats['errors'] ),
				'scan_type'           => $scan_type,
			)
		);

		update_option(
			'kwperf_last_scan',
			array(
				'date'                => $scan_start,
				'pages_scanned'       => $this->stats['pages_scanned'],
				'links_scanned'       => $this->stats['links_scanned'],
				'broken_links_found'  => $this->stats['broken_links_found'],
				'working_links'       => $this->stats['working_links'],
			)
		);

		$summary = array(
			'history_id'          => $history_id,
			'scan_date'           => $scan_start,
			'duration'            => $duration,
			'pages_scanned'       => $this->stats['pages_scanned'],
			'links_scanned'       => $this->stats['links_scanned'],
			'broken_links_found'  => $this->stats['broken_links_found'],
			'working_links'       => $this->stats['working_links'],
			'broken_occurrences'  => $this->broken_occurrences,
			'errors'              => $this->stats['errors'],
		);

		/**
		 * Fires after a scan (manual or cron) completes.
		 *
		 * @param array  $summary   Scan summary data.
		 * @param string $scan_type 'manual' or 'cron'.
		 */
		do_action( 'kwperf_scan_completed', $summary, $scan_type );

		$this->update_progress( $total_pages, $total_pages, 'done' );

		return $summary;
	}

	/**
	 * Persist current scan progress to a transient so the admin UI can poll
	 * it and render a live progress bar while a scan is running.
	 *
	 * @param int    $current_page Pages processed so far.
	 * @param int    $total_pages  Total pages queued for this scan.
	 * @param string $status       'running' or 'done'.
	 */
	private function update_progress( $current_page, $total_pages, $status = 'running' ) {
		set_transient(
			'kwperf_scan_progress',
			array(
				'status'             => $status,
				'current_page'       => $current_page,
				'total_pages'        => $total_pages,
				'pages_scanned'      => $this->stats['pages_scanned'],
				'links_scanned'      => $this->stats['links_scanned'],
				'broken_links_found' => $this->stats['broken_links_found'],
				'working_links'      => $this->stats['working_links'],
			),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Re-check a specific set of already-logged broken URLs (used by "Recheck" actions).
	 *
	 * @param int[] $log_ids Log row IDs to re-check.
	 * @return array Summary of the recheck.
	 */
	public function recheck_logs( $log_ids ) {
		global $wpdb;

		$this->url_cache = array();
		$resolved        = 0;
		$still_broken    = 0;

		$table = KWPERF_Database::logs_table();

		foreach ( $log_ids as $log_id ) {
			$log_id = absint( $log_id );
			$row    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $log_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			if ( ! $row ) {
				continue;
			}

			$result = $this->check_url( $row['broken_url'] );

			if ( $this->is_broken( $result ) ) {
				$wpdb->update(
					$table,
					array(
						'final_url'       => $result['final_url'],
						'http_status'     => $result['http_status'],
						'redirect_status' => $result['redirect_status'],
						'redirect_count'  => $result['redirect_count'],
						'last_checked'    => current_time( 'mysql' ),
					),
					array( 'id' => $log_id ),
					array( '%s', '%d', '%s', '%d', '%s' ),
					array( '%d' )
				);
				++$still_broken;
			} else {
				// No longer broken — remove the log entry.
				$wpdb->delete( $table, array( 'id' => $log_id ), array( '%d' ) );
				++$resolved;
			}
		}

		return array(
			'resolved'     => $resolved,
			'still_broken' => $still_broken,
		);
	}

	/**
	 * Build the list of frontend URLs to crawl based on configured post types.
	 *
	 * @return array List of arrays with 'id', 'url', 'title'.
	 */
	private function get_pages_to_scan() {
		$pages      = array();
		$post_types = (array) KWPERF_Settings::get( 'post_types', array( 'post', 'page' ) );

		// Front page.
		$pages[] = array(
			'id'    => (int) get_option( 'page_on_front' ),
			'url'   => home_url( '/' ),
			'title' => __( 'Home', 'kw-performance' ),
		);

		foreach ( $post_types as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}

			$query = new WP_Query(
				array(
					'post_type'              => $post_type,
					'post_status'            => 'publish',
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			foreach ( $query->posts as $post_id ) {
				$permalink = get_permalink( $post_id );
				if ( ! $permalink ) {
					continue;
				}
				$pages[] = array(
					'id'    => $post_id,
					'url'   => $permalink,
					'title' => get_the_title( $post_id ),
				);
			}

			// Post type archive, if it has one.
			if ( $post_type_object = get_post_type_object( $post_type ) ) { // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure
				if ( $post_type_object->has_archive ) {
					$archive_link = get_post_type_archive_link( $post_type );
					if ( $archive_link ) {
						$pages[] = array(
							'id'    => 0,
							'url'   => $archive_link,
							'title' => $post_type_object->labels->name,
						);
					}
				}
			}
		}

		/**
		 * Filter the final list of pages to be scanned.
		 *
		 * @param array $pages List of page descriptors.
		 */
		return apply_filters( 'kwperf_pages_to_scan', $pages );
	}

	/**
	 * Fetch and scan a single frontend page for broken links.
	 *
	 * @param string $url   Page URL to fetch.
	 * @param int    $id    Post ID (0 for non-post pages).
	 * @param string $title Page title.
	 */
	private function scan_page( $url, $id, $title ) {
		// Fetching and rendering a full page (theme, widgets, DB queries) is
		// inherently slower than a single link-status check, so give it a more
		// generous timeout than the per-link setting, plus one retry — a slow
		// or momentarily loaded server shouldn't drop an entire page from the scan.
		$timeout = max( 30, (int) KWPERF_Settings::get( 'request_timeout', 10 ) );
		$args    = array(
			'timeout'    => $timeout,
			'sslverify'  => apply_filters( 'kwperf_sslverify', true ),
			'user-agent' => 'KW-Performance/' . KWPERF_VERSION . '; ' . home_url( '/' ),
		);

		// Not wp_safe_remote_get(): this fetches our own trusted permalink, not an
		// arbitrary discovered URL, so it must work on local/dev hosts too.
		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			$response = wp_remote_get( $url, $args ); // One retry before giving up.
		}

		if ( is_wp_error( $response ) ) {
			$this->stats['errors'][] = sprintf(
				/* translators: 1: page URL, 2: error message */
				__( 'Could not fetch %1$s: %2$s', 'kw-performance' ),
				$url,
				$response->get_error_message()
			);
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === trim( $body ) ) {
			return;
		}

		++$this->stats['pages_scanned'];

		$links = $this->extract_links( $body, $url );

		foreach ( $links as $link ) {
			++$this->stats['links_scanned'];

			$result = $this->check_url( $link['href'] );

			if ( $this->is_broken( $result ) ) {
				++$this->stats['broken_links_found'];

				$this->broken_occurrences[] = array(
					'source_page_id'   => $id,
					'source_permalink' => $url,
					'source_title'     => $title,
					'broken_url'       => $link['href'],
					'final_url'        => $result['final_url'],
					'http_status'      => $result['http_status'],
					'redirect_status'  => $result['redirect_status'],
					'redirect_count'   => $result['redirect_count'],
					'section'          => $link['section'],
					'css_class'        => $link['css_class'],
					'link_text'        => $link['text'],
				);
			} else {
				++$this->stats['working_links'];
			}
		}
	}

	/**
	 * Parse HTML and extract qualifying <a> links with their surrounding context.
	 *
	 * @param string $html    Raw HTML body.
	 * @param string $base_url Page URL used to resolve relative hrefs.
	 * @return array List of link descriptors.
	 */
	private function extract_links( $html, $base_url ) {
		$results = array();
		$seen    = array();

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();

		$anchors = $dom->getElementsByTagName( 'a' );

		foreach ( $anchors as $anchor ) {
			$href = trim( (string) $anchor->getAttribute( 'href' ) );

			if ( ! $this->should_check_href( $href ) ) {
				continue;
			}

			$absolute = $this->resolve_url( $base_url, $href );
			if ( ! $absolute ) {
				continue;
			}

			// Avoid re-processing the exact same URL discovered multiple times on one page.
			$dedup_key = $absolute;
			if ( isset( $seen[ $dedup_key ] ) ) {
				continue;
			}
			$seen[ $dedup_key ] = true;

			$context = $this->detect_context( $anchor );

			$results[] = array(
				'href'      => $absolute,
				'section'   => $context['section'],
				'css_class' => $context['css_class'],
				'text'      => trim( wp_strip_all_tags( $anchor->textContent ) ),
			);
		}

		return $results;
	}

	/**
	 * Determine if an href should be validated.
	 *
	 * @param string $href Raw href attribute value.
	 * @return bool
	 */
	private function should_check_href( $href ) {
		if ( '' === $href ) {
			return false;
		}

		if ( '#' === $href[0] ) {
			return false;
		}

		return $this->is_checkable_href( $href );
	}

	/**
	 * Decide whether an href is a real page path worth validating over HTTP.
	 *
	 * Rejects any explicit non-http(s) scheme (mailto:, tel:, javascript:,
	 * skype:, sms:, callto:, whatsapp:, ftp:, data:, etc. — whatever the
	 * markup uses) and bare email addresses typed without the "mailto:"
	 * prefix, which would otherwise be resolved into a nonsensical
	 * "/someone@example.com" page path and reported as a false-positive 404.
	 *
	 * @param string $href Raw or already-decoded href value.
	 * @return bool
	 */
	private function is_checkable_href( $href ) {
		if ( preg_match( '/^([a-zA-Z][a-zA-Z0-9+.\-]*):/', $href, $matches ) ) {
			return in_array( strtolower( $matches[1] ), array( 'http', 'https' ), true );
		}

		if ( preg_match( '/^[^\s@\/]+@[^\s@\/]+\.[a-zA-Z]{2,}(\?.*)?$/', $href ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Resolve a possibly relative href into an absolute URL.
	 *
	 * @param string $base Base page URL.
	 * @param string $href Href value (absolute or relative).
	 * @return string|false
	 */
	private function resolve_url( $base, $href ) {
		if ( ! $this->is_checkable_href( $href ) ) {
			return false;
		}

		if ( preg_match( '#^https?://#i', $href ) ) {
			return $href;
		}

		if ( 0 === strpos( $href, '//' ) ) {
			$scheme = wp_parse_url( $base, PHP_URL_SCHEME ) ?: 'https';
			return $scheme . ':' . $href;
		}

		$base_parts = wp_parse_url( $base );
		if ( empty( $base_parts['scheme'] ) || empty( $base_parts['host'] ) ) {
			return false;
		}

		$root = $base_parts['scheme'] . '://' . $base_parts['host'] . ( isset( $base_parts['port'] ) ? ':' . $base_parts['port'] : '' );

		if ( '' === $href ) {
			return false;
		}

		if ( '/' === $href[0] ) {
			return $root . $href;
		}

		// Relative to current path.
		$base_path = isset( $base_parts['path'] ) ? $base_parts['path'] : '/';
		$dir       = trailingslashit( dirname( $base_path ) );

		return $root . $dir . $href;
	}

	/**
	 * Walk up the DOM from a link node to find its containing section/classes.
	 *
	 * Detects nearest ancestor with a class/id, plus Gutenberg block and
	 * Elementor widget markers when present.
	 *
	 * @param DOMElement $node The <a> element.
	 * @return array{section:string,css_class:string}
	 */
	private function detect_context( $node ) {
		$section          = '';
		$gutenberg_block  = '';
		$elementor_widget = '';
		$all_classes      = array();

		$current = $node->parentNode;
		$depth   = 0;

		while ( $current && $current instanceof DOMElement && $depth < 20 ) {
			$class_attr = trim( (string) $current->getAttribute( 'class' ) );
			$id_attr    = trim( (string) $current->getAttribute( 'id' ) );

			if ( '' !== $class_attr ) {
				$classes = preg_split( '/\s+/', $class_attr );
				foreach ( $classes as $class ) {
					if ( '' === $class ) {
						continue;
					}
					$all_classes[] = $class;

					if ( ! $gutenberg_block && 0 === stripos( $class, 'wp-block-' ) ) {
						$gutenberg_block = $class;
					}
					if ( ! $elementor_widget && ( 0 === stripos( $class, 'elementor-widget-' ) || 0 === stripos( $class, 'elementor-element' ) ) ) {
						$elementor_widget = $class;
					}
				}

				if ( '' === $section ) {
					$section = $class_attr;
				}
			} elseif ( '' === $section && '' !== $id_attr ) {
				$section = '#' . $id_attr;
			}

			$current = $current->parentNode;
			++$depth;
		}

		$labels = array();
		if ( $gutenberg_block ) {
			$labels[] = 'Gutenberg: ' . $gutenberg_block;
		}
		if ( $elementor_widget ) {
			$labels[] = 'Elementor: ' . $elementor_widget;
		}
		if ( $section ) {
			$labels[] = $section;
		}

		return array(
			'section'   => implode( ' | ', $labels ),
			'css_class' => implode( ' ', array_values( array_unique( $all_classes ) ) ),
		);
	}

	/**
	 * Validate a URL, following redirects manually so the full chain can be inspected.
	 * Results are cached per scan run to avoid duplicate requests.
	 *
	 * @param string $url URL to validate.
	 * @return array{final_url:string,http_status:int,redirect_count:int,redirect_status:string}
	 */
	public function check_url( $url ) {
		if ( isset( $this->url_cache[ $url ] ) ) {
			return $this->url_cache[ $url ];
		}

		$timeout       = (int) KWPERF_Settings::get( 'request_timeout', 10 );
		$max_redirects = (int) KWPERF_Settings::get( 'max_redirects', 5 );

		$current         = $url;
		$redirect_count  = 0;
		$redirect_codes  = array();
		$result          = array(
			'final_url'       => $url,
			'http_status'     => 0,
			'redirect_count'  => 0,
			'redirect_status' => '',
		);

		for ( $i = 0; $i <= $max_redirects; $i++ ) {
			$args = array(
				'timeout'    => $timeout,
				'redirection'=> 0,
				'sslverify'  => apply_filters( 'kwperf_sslverify', true ),
				'user-agent' => 'KW-Performance/' . KWPERF_VERSION,
			);

			$response = wp_safe_remote_head( $current, $args );

			if ( is_wp_error( $response ) || 0 === (int) wp_remote_retrieve_response_code( $response ) ) {
				$response = wp_safe_remote_get( $current, $args );
			}

			if ( is_wp_error( $response ) ) {
				$result['final_url']       = $current;
				$result['http_status']     = 0;
				$result['redirect_count']  = $redirect_count;
				$result['redirect_status'] = $redirect_codes ? implode( ' -> ', $redirect_codes ) : 'error';
				break;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );

			if ( in_array( $code, array( 301, 302, 307, 308 ), true ) ) {
				$location = wp_remote_retrieve_header( $response, 'location' );

				if ( ! $location ) {
					$result['final_url']       = $current;
					$result['http_status']     = $code;
					$result['redirect_count']  = $redirect_count;
					$result['redirect_status'] = implode( ' -> ', $redirect_codes );
					break;
				}

				$resolved = $this->resolve_url( $current, $location );
				$current  = $resolved ? $resolved : $location;

				$redirect_codes[] = $code;
				++$redirect_count;

				if ( $redirect_count > $max_redirects ) {
					$result['final_url']       = $current;
					$result['http_status']     = 0;
					$result['redirect_count']  = $redirect_count;
					$result['redirect_status'] = 'redirect_loop';
					break;
				}

				continue;
			}

			$result['final_url']       = $current;
			$result['http_status']     = $code;
			$result['redirect_count']  = $redirect_count;
			$result['redirect_status'] = $redirect_count > 0 ? implode( ' -> ', $redirect_codes ) : '';
			break;
		}

		$this->url_cache[ $url ] = $result;

		return $result;
	}

	/**
	 * Decide whether a URL check result represents a broken link.
	 *
	 * @param array $result Result from check_url().
	 * @return bool
	 */
	public function is_broken( $result ) {
		if ( 'redirect_loop' === $result['redirect_status'] ) {
			return true;
		}

		$status = (int) $result['http_status'];

		if ( 0 === $status ) {
			return true;
		}

		if ( 404 === $status || 410 === $status ) {
			return true;
		}

		if ( $status >= 500 && $status < 600 ) {
			return true;
		}

		return false;
	}
}
