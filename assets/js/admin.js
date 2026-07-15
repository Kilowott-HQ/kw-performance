/**
 * KW Performance — admin screen behaviors (manual scan, log management).
 */
( function ( $ ) {
	'use strict';

	var kwperf = window.kwperfAdmin || {};
	var scanInProgress = false;

	/**
	 * Perform an AJAX POST against admin-ajax.php with the shared nonce.
	 *
	 * @param {string} action Ajax action name (without the kwperf_ prefix requirement).
	 * @param {Object} data   Extra POST data.
	 * @return {jQuery.Promise}
	 */
	function kwperfRequest( action, data ) {
		return $.post(
			kwperf.ajaxUrl,
			$.extend( { action: action, nonce: kwperf.nonce }, data || {} )
		);
	}

	function showNotice( $el, message, isError ) {
		$el
			.removeClass( 'notice-success notice-error' )
			.addClass( isError ? 'notice-error' : 'notice-success' )
			.html( '<p>' + message + '</p>' )
			.show();
	}

	// --- Manual scan (Settings page) -----------------------------------

	var progressPollTimer = null;

	function renderProgress( progress ) {
		var $fill = $( '#kwperf-progress-bar-fill' );
		var $text = $( '#kwperf-scan-progress' ).find( '.kwperf-scan-progress-text' );

		if ( ! progress || ! progress.total_pages ) {
			$fill.css( 'width', '5%' );
			$text.text( kwperf.i18n.scanning );
			return;
		}

		var percent = Math.min( 100, Math.round( ( progress.current_page / progress.total_pages ) * 100 ) );
		$fill.css( 'width', percent + '%' );
		$text.text(
			percent + '% — ' +
				kwperf.i18n.pageProgress
					.replace( '%1$d', progress.current_page )
					.replace( '%2$d', progress.total_pages ) +
				' (' + progress.links_scanned + ' links checked, ' + progress.broken_links_found + ' broken so far)'
		);
	}

	function pollScanProgress() {
		kwperfRequest( 'kwperf_scan_status' ).done( function ( response ) {
			if ( response.success && response.data.progress ) {
				renderProgress( response.data.progress );
			}
		} );
	}

	function startProgressPolling() {
		renderProgress( null );
		pollScanProgress();
		progressPollTimer = setInterval( pollScanProgress, 1500 );
	}

	function stopProgressPolling() {
		if ( progressPollTimer ) {
			clearInterval( progressPollTimer );
			progressPollTimer = null;
		}
	}

	$( '#kwperf-run-scan' ).on( 'click', function () {
		if ( scanInProgress ) {
			return;
		}

		var $button  = $( this );
		var $progress = $( '#kwperf-scan-progress' );
		var $result  = $( '#kwperf-scan-result' );

		scanInProgress = true;
		$button.prop( 'disabled', true );
		$progress.show();
		$result.hide();
		startProgressPolling();

		kwperfRequest( 'kwperf_run_scan' )
			.done( function ( response ) {
				if ( response.success ) {
					var d = response.data;
					renderProgress( { current_page: 1, total_pages: 1, links_scanned: d.links_scanned, broken_links_found: d.broken_links_found } );
					showNotice(
						$result,
						kwperf.i18n.scanComplete +
							' ' +
							d.pages_scanned + ' pages, ' +
							d.links_scanned + ' links checked, ' +
							d.broken_links_found + ' broken, ' +
							d.working_links + ' working. (' + d.duration + 's)',
						false
					);
					setTimeout( function () {
						window.location.reload();
					}, 1500 );
				} else {
					showNotice( $result, ( response.data && response.data.message ) || kwperf.i18n.scanFailed, true );
				}
			} )
			.fail( function () {
				showNotice( $result, kwperf.i18n.scanFailed, true );
			} )
			.always( function () {
				scanInProgress = false;
				stopProgressPolling();
				$button.prop( 'disabled', false );
				$progress.hide();
			} );
	} );

	// --- Clear logs (Settings page) -------------------------------------

	$( '#kwperf-clear-logs' ).on( 'click', function () {
		if ( ! window.confirm( kwperf.i18n.confirmClear ) ) {
			return;
		}

		var $result = $( '#kwperf-scan-result' );

		kwperfRequest( 'kwperf_clear_logs' ).done( function ( response ) {
			if ( response.success ) {
				showNotice( $result, response.data.message, false );
				setTimeout( function () {
					window.location.reload();
				}, 1000 );
			}
		} );
	} );

	// --- Slack test notification (Settings page) -------------------------

	$( '#kwperf-test-slack' ).on( 'click', function () {
		var webhookUrl = $( '#kwperf_slack_webhook_url' ).val();
		var $resultEl  = $( '#kwperf-slack-test-result' );

		if ( ! webhookUrl ) {
			$resultEl.removeClass( 'kwperf-inline-success' ).addClass( 'kwperf-inline-error' ).text( kwperf.i18n.noWebhookUrl );
			return;
		}

		$resultEl.removeClass( 'kwperf-inline-success kwperf-inline-error' ).text( kwperf.i18n.testingSlack );

		kwperfRequest( 'kwperf_test_slack', { webhook_url: webhookUrl } )
			.done( function ( response ) {
				if ( response.success ) {
					$resultEl.removeClass( 'kwperf-inline-error' ).addClass( 'kwperf-inline-success' ).text( response.data.message );
				} else {
					$resultEl.removeClass( 'kwperf-inline-success' ).addClass( 'kwperf-inline-error' ).text( ( response.data && response.data.message ) || kwperf.i18n.testSlackFailed );
				}
			} )
			.fail( function () {
				$resultEl.removeClass( 'kwperf-inline-success' ).addClass( 'kwperf-inline-error' ).text( kwperf.i18n.testSlackFailed );
			} );
	} );

	// --- Post types "Select All" (Settings page) --------------------------

	var $postTypeCheckboxes = $( '.kwperf-post-type-checkbox' );
	var $selectAllCheckbox  = $( '#kwperf_post_types_select_all' );

	$selectAllCheckbox.on( 'change', function () {
		$postTypeCheckboxes.prop( 'checked', this.checked );
	} );

	$postTypeCheckboxes.on( 'change', function () {
		$selectAllCheckbox.prop( 'checked', $postTypeCheckboxes.length === $postTypeCheckboxes.filter( ':checked' ).length );
	} );

	// --- Clear scan history (Scan History page) -------------------------

	$( '#kwperf-clear-history' ).on( 'click', function () {
		if ( ! window.confirm( kwperf.i18n.confirmClearHistory ) ) {
			return;
		}

		var $result = $( '#kwperf-history-action-result' );

		kwperfRequest( 'kwperf_clear_scan_history' ).done( function ( response ) {
			if ( response.success ) {
				showNotice( $result, response.data.message, false );
				setTimeout( function () {
					window.location.reload();
				}, 1000 );
			}
		} );
	} );

	// --- 404 Log page: single row actions --------------------------------

	$( document ).on( 'click', '.kwperf-delete-single', function ( e ) {
		e.preventDefault();

		if ( ! window.confirm( kwperf.i18n.confirmDelete ) ) {
			return;
		}

		var id      = $( this ).data( 'id' );
		var $result = $( '#kwperf-log-action-result' );

		kwperfRequest( 'kwperf_delete_log', { id: id } ).done( function ( response ) {
			if ( response.success ) {
				showNotice( $result, response.data.message, false );
				window.location.reload();
			}
		} );
	} );

	$( document ).on( 'click', '.kwperf-recheck-single', function ( e ) {
		e.preventDefault();

		var id      = $( this ).data( 'id' );
		var $result = $( '#kwperf-log-action-result' );

		showNotice( $result, kwperf.i18n.rechecking, false );

		kwperfRequest( 'kwperf_recheck_logs', { 'ids[]': id } ).done( function ( response ) {
			if ( response.success ) {
				showNotice( $result, response.data.message, false );
				window.location.reload();
			}
		} );
	} );

	$( document ).on( 'click', '.kwperf-recheck-all', function ( e ) {
		e.preventDefault();

		var $result = $( '#kwperf-log-action-result' );

		showNotice( $result, kwperf.i18n.rechecking, false );

		kwperfRequest( 'kwperf_recheck_logs', { all: 1 } ).done( function ( response ) {
			if ( response.success ) {
				showNotice( $result, response.data.message, false );
				window.location.reload();
			}
		} );
	} );

	// --- 404 Log page: bulk actions ---------------------------------------

	function getCheckedLogIds() {
		var ids = [];
		$( 'input[name="log_ids[]"]:checked' ).each( function () {
			ids.push( $( this ).val() );
		} );
		return ids;
	}

	function handleBulkAction( selectName ) {
		var action = $( 'select[name="' + selectName + '"]' ).val();
		var ids    = getCheckedLogIds();

		if ( '-1' === action || ! action ) {
			return;
		}

		if ( 0 === ids.length ) {
			window.alert( kwperf.i18n.noSelection );
			return;
		}

		var $result = $( '#kwperf-log-action-result' );

		if ( 'delete' === action ) {
			if ( ! window.confirm( kwperf.i18n.confirmBulk ) ) {
				return;
			}

			kwperfRequest( 'kwperf_bulk_delete_logs', { ids: ids } ).done( function ( response ) {
				if ( response.success ) {
					showNotice( $result, response.data.message, false );
					window.location.reload();
				}
			} );
		} else if ( 'recheck' === action ) {
			showNotice( $result, kwperf.i18n.rechecking, false );

			kwperfRequest( 'kwperf_recheck_logs', { ids: ids } ).done( function ( response ) {
				if ( response.success ) {
					showNotice( $result, response.data.message, false );
					window.location.reload();
				}
			} );
		}
	}

	$( document ).on( 'click', '#doaction', function ( e ) {
		e.preventDefault();
		handleBulkAction( 'action' );
	} );

	$( document ).on( 'click', '#doaction2', function ( e ) {
		e.preventDefault();
		handleBulkAction( 'action2' );
	} );
} )( jQuery );
