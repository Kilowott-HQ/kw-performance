/**
 * KW Performance — turns the "KW Performance" item inside the core Settings
 * fly-out into a trigger for its own nested fly-out (404 Log / Metas /
 * Tracking / Scan History), since WordPress's admin menu has no native
 * third level. Runs on every wp-admin screen (the Settings fly-out is part
 * of the persistent sidebar, not just this plugin's own pages).
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	ready( function () {
		var settings = window.kwperfMenuFlyout;
		if ( ! settings || ! settings.items || ! settings.items.length ) {
			return;
		}

		var adminMenu = document.getElementById( 'adminmenu' );
		if ( ! adminMenu ) {
			return;
		}

		// Identify the "KW Performance" link by its target URL rather than
		// its (translatable) text, and specifically the copy of it living
		// inside Settings' own fly-out — not, say, a matching link elsewhere.
		var candidates = adminMenu.querySelectorAll( 'a[href*="page=kwperf-settings"]' );
		var triggerLink = null;
		var parentSubmenu = null;

		for ( var i = 0; i < candidates.length; i++ ) {
			var submenu = candidates[ i ].closest( '.wp-submenu' );
			if ( submenu ) {
				triggerLink = candidates[ i ];
				parentSubmenu = submenu;
				break;
			}
		}

		if ( ! triggerLink ) {
			return;
		}

		var triggerLi = triggerLink.closest( 'li' );
		if ( ! triggerLi || triggerLi.querySelector( '.kwperf-submenu-flyout' ) ) {
			return; // Nowhere to attach to, or already set up.
		}

		var flyout = document.createElement( 'ul' );
		flyout.className = 'kwperf-submenu-flyout';

		settings.items.forEach( function ( item ) {
			var li = document.createElement( 'li' );
			var a = document.createElement( 'a' );
			a.href = item.url;
			a.textContent = item.label;
			li.appendChild( a );
			flyout.appendChild( li );
		} );

		// Match whatever admin color scheme is active instead of hardcoding
		// colors that would only look right in the default one.
		if ( parentSubmenu && window.getComputedStyle ) {
			var computed = window.getComputedStyle( parentSubmenu );
			flyout.style.backgroundColor = computed.backgroundColor;
			flyout.style.color = computed.color;
		}

		triggerLi.classList.add( 'kwperf-has-flyout' );
		triggerLi.appendChild( flyout );
	} );
} )();
