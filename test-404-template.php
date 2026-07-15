<?php
/**
 * Template Name: KW Performance Test Template
 *
 * A one-page torture test for KW Performance.
 *
 * How to use (both files are required — see README.md "Testing With
 * test-404-template.php" for the full case list and why two files):
 * 1. Copy this file into the root of your active theme (or child theme).
 * 2. Copy test-404-mu-plugin.php into wp-content/mu-plugins/ (create that
 *    folder if it doesn't exist yet). This is what makes the simulated
 *    status/redirect cases below respond correctly to HEAD requests, which
 *    is how the scanner checks most links — a plain page template only
 *    runs for full (GET) page loads, so the status/redirect cases would
 *    silently no-op under HEAD without it.
 * 3. In wp-admin, create a new Page, and under Page Attributes choose the
 *    "KW Performance Test Template" template, then publish it.
 * 4. Run a KW Performance scan (Run Scan Now, or wait for the cron).
 * 5. Open the 404 Log and compare it against the "expected result" table
 *    rendered on this page.
 *
 * @package KW_Performance
 */

get_header();

$kwperf_self_url   = get_permalink( get_queried_object_id() );
$kwperf_broken_url = home_url( '/kwperf-test-this-does-not-exist/' );
?>

<div style="max-width:760px;margin:40px auto;padding:0 20px;font-family:sans-serif;line-height:1.6;">

	<h1>KW Performance &mdash; Test Page</h1>
	<p>This page exists purely to exercise every case the scanner handles. After running a scan, compare the <strong>404 Log</strong> against the expected results below, then delete this page and remove the template from your theme.</p>

	<table border="1" cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse;margin-bottom:30px;">
		<thead>
			<tr><th>Case</th><th>Expected result</th></tr>
		</thead>
		<tbody>
			<tr><td>Anchor-only, empty href, mailto:, tel:, javascript:, bare email</td><td>Ignored &mdash; never checked, never logged</td></tr>
			<tr><td>Home link</td><td>Working</td></tr>
			<tr><td>Direct 404</td><td>Broken &mdash; HTTP 404</td></tr>
			<tr><td>Direct 410</td><td>Broken &mdash; HTTP 410</td></tr>
			<tr><td>Direct 500 / 503</td><td>Broken &mdash; 5xx</td></tr>
			<tr><td>Redirect &rarr; working (1 hop, 2 hops)</td><td>Working; redirect count 1 / 2</td></tr>
			<tr><td>Redirect &rarr; broken (1 hop, 2 hops)</td><td>Broken, final status 404; redirect count 1 / 2</td></tr>
			<tr><td>Redirect loop</td><td>Broken &mdash; flagged as a redirect loop</td></tr>
			<tr><td>External working link</td><td>Working</td></tr>
			<tr><td>External broken link</td><td>Broken &mdash; HTTP 404</td></tr>
			<tr><td>Duplicate broken link (2 sections)</td><td>Logged once, section from the first occurrence only</td></tr>
			<tr><td>Link with no visible text (image, no alt)</td><td>Broken &mdash; logs with an empty Link Text column, no PHP errors</td></tr>
		</tbody>
	</table>

	<section class="hero-banner">
		<h2>Section: hero-banner</h2>
		<p><a href="<?php echo esc_url( $kwperf_broken_url ); ?>">Direct 404 link</a></p>
		<p><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Working home link</a></p>
	</section>

	<div class="footer-links">
		<h2>Section: footer-links (ignored link types)</h2>
		<p><a href="#">Anchor only</a></p>
		<p><a href="">Empty href</a></p>
		<p><a href="mailto:test@example.com">Proper mailto</a></p>
		<p><a href="tel:+15551234567">Phone number</a></p>
		<p><a href="javascript:void(0)">javascript: link</a></p>
		<p><a href="test@example.com">Bare email, missing mailto: (should be ignored, not reported as a broken /test@example.com path)</a></p>
	</div>

	<div class="wp-block-cover kwperf-gutenberg-test">
		<h2>Section: Gutenberg-style block (wp-block-*)</h2>
		<p><a href="<?php echo esc_url( home_url( '/kwperf-test-this-does-not-exist-gutenberg/' ) ); ?>">Broken link inside a Gutenberg-style block</a></p>
	</div>

	<div class="elementor-widget-container">
		<div class="elementor-element elementor-element-abc123 elementor-widget elementor-widget-button">
			<h2>Section: Elementor-style widget</h2>
			<a href="<?php echo esc_url( home_url( '/kwperf-test-this-does-not-exist-elementor/' ) ); ?>" class="elementor-button">Broken link inside an Elementor-style widget</a>
		</div>
	</div>

	<div class="outer-wrapper container narrow">
		<h2>Section: multiple ancestor classes</h2>
		<p><a href="<?php echo esc_url( home_url( '/kwperf-test-this-does-not-exist-multiclass/' ) ); ?>">Broken link &mdash; ancestor has classes: outer-wrapper, container, narrow</a></p>
	</div>

	<div id="unique-section-id">
		<h2>Section identified by ID only (no class)</h2>
		<p><a href="<?php echo esc_url( home_url( '/kwperf-test-this-does-not-exist-idonly/' ) ); ?>">Broken link &mdash; ancestor has no class, only id="unique-section-id"</a></p>
	</div>

	<div class="redirect-tests">
		<h2>Section: redirects</h2>
		<p><a href="<?php echo esc_url( add_query_arg( 'kwperf_status', 'redirect_ok', $kwperf_self_url ) ); ?>">Redirect (1 hop) &rarr; working</a></p>
		<p><a href="<?php echo esc_url( add_query_arg( 'kwperf_status', 'redirect_chain_ok_1', $kwperf_self_url ) ); ?>">Redirect chain (2 hops) &rarr; working</a></p>
		<p><a href="<?php echo esc_url( add_query_arg( 'kwperf_status', 'redirect_broken', $kwperf_self_url ) ); ?>">Redirect (1 hop) &rarr; broken</a></p>
		<p><a href="<?php echo esc_url( add_query_arg( 'kwperf_status', 'redirect_chain_broken_1', $kwperf_self_url ) ); ?>">Redirect chain (2 hops) &rarr; broken</a></p>
		<p><a href="<?php echo esc_url( add_query_arg( 'kwperf_status', 'redirect_loop', $kwperf_self_url ) ); ?>">Redirect loop</a></p>
	</div>

	<div class="status-code-tests">
		<h2>Section: direct status codes</h2>
		<p><a href="<?php echo esc_url( add_query_arg( 'kwperf_status', '410', $kwperf_self_url ) ); ?>">410 Gone</a></p>
		<p><a href="<?php echo esc_url( add_query_arg( 'kwperf_status', '500', $kwperf_self_url ) ); ?>">500 Internal Server Error</a></p>
		<p><a href="<?php echo esc_url( add_query_arg( 'kwperf_status', '503', $kwperf_self_url ) ); ?>">503 Service Unavailable</a></p>
	</div>

	<div class="external-links">
		<h2>Section: external links</h2>
		<p><a href="https://wordpress.org/">Working external link</a></p>
		<p><a href="https://wordpress.org/kwperf-test-this-does-not-exist/">Broken external link</a></p>
	</div>

	<div class="duplicate-test-section-a">
		<h2>Section: duplicate link, occurrence A</h2>
		<p><a href="<?php echo esc_url( home_url( '/kwperf-test-this-does-not-exist-duplicate/' ) ); ?>">Same broken URL as occurrence B below</a></p>
	</div>

	<div class="duplicate-test-section-b">
		<h2>Section: duplicate link, occurrence B (should not be separately logged)</h2>
		<p><a href="<?php echo esc_url( home_url( '/kwperf-test-this-does-not-exist-duplicate/' ) ); ?>">Same broken URL as occurrence A above</a></p>
	</div>

	<div class="empty-text-link-test">
		<h2>Section: link with no visible text</h2>
		<p>
			<a href="<?php echo esc_url( home_url( '/kwperf-test-this-does-not-exist-notext/' ) ); ?>">
				<img src="<?php echo esc_url( includes_url( 'images/blank.gif' ) ); ?>" alt="" width="1" height="1" />
			</a>
		</p>
	</div>

</div>

<?php get_footer(); ?>
