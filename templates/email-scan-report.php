<?php
/**
 * HTML email template for the scan report notification.
 *
 * Expects: array $summary, array $broken_occurrences.
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$site_name  = wp_specialchars_decode( get_bloginfo( 'name' ) );
$scan_date  = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $summary['scan_date'] );
$logs_url   = admin_url( 'admin.php?page=kwperf-logs' );
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
</head>
<body style="font-family:Arial,Helvetica,sans-serif;background:#f1f1f1;margin:0;padding:20px;color:#23282d;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:680px;margin:0 auto;background:#ffffff;border-radius:4px;overflow:hidden;">
		<?php if ( $logo_url ) : ?>
			<tr>
				<td style="background:#ffffff;padding:20px 24px;text-align:center;">
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" height="50" style="height:50px;width:auto;max-width:100%;display:inline-block;" />
				</td>
			</tr>
		<?php endif; ?>
		<tr>
			<td style="background:#1d2327;padding:20px 24px;">
				<h1 style="color:#ffffff;margin:0;font-size:18px;"><?php echo esc_html( $site_name ); ?> &mdash; <?php esc_html_e( 'KW Performance Scan Report', 'kw-performance' ); ?></h1>
			</td>
		</tr>
		<tr>
			<td style="padding:24px;">
				<p style="margin-top:0;"><?php echo esc_html( sprintf(
					/* translators: %s: scan date/time */
					__( 'Scan completed on %s.', 'kw-performance' ),
					$scan_date
				) ); ?></p>

				<table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;margin-bottom:20px;">
					<tr>
						<td style="border:1px solid #dcdcde;background:#f6f7f7;"><?php esc_html_e( 'Pages Scanned', 'kw-performance' ); ?></td>
						<td style="border:1px solid #dcdcde;"><?php echo esc_html( $summary['pages_scanned'] ); ?></td>
					</tr>
					<tr>
						<td style="border:1px solid #dcdcde;background:#f6f7f7;"><?php esc_html_e( 'Total Links Checked', 'kw-performance' ); ?></td>
						<td style="border:1px solid #dcdcde;"><?php echo esc_html( $summary['links_scanned'] ); ?></td>
					</tr>
					<tr>
						<td style="border:1px solid #dcdcde;background:#f6f7f7;"><?php esc_html_e( 'Broken Links Found', 'kw-performance' ); ?></td>
						<td style="border:1px solid #dcdcde;color:#d63638;font-weight:bold;"><?php echo esc_html( $summary['broken_links_found'] ); ?></td>
					</tr>
					<tr>
						<td style="border:1px solid #dcdcde;background:#f6f7f7;"><?php esc_html_e( 'Working Links', 'kw-performance' ); ?></td>
						<td style="border:1px solid #dcdcde;"><?php echo esc_html( $summary['working_links'] ); ?></td>
					</tr>
				</table>

				<?php if ( ! empty( $broken_occurrences ) ) : ?>
					<h2 style="font-size:15px;border-bottom:1px solid #dcdcde;padding-bottom:8px;"><?php esc_html_e( 'Broken Links Detail', 'kw-performance' ); ?></h2>

					<?php foreach ( $broken_occurrences as $item ) : ?>
						<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin-bottom:14px;border:1px solid #dcdcde;">
							<tr>
								<td style="background:#f6f7f7;width:140px;"><strong><?php esc_html_e( 'Broken URL', 'kw-performance' ); ?></strong></td>
								<td><a href="<?php echo esc_url( $item['broken_url'] ); ?>"><?php echo esc_html( $item['broken_url'] ); ?></a></td>
							</tr>
							<tr>
								<td style="background:#f6f7f7;"><strong><?php esc_html_e( 'Found On', 'kw-performance' ); ?></strong></td>
								<td><a href="<?php echo esc_url( $item['source_permalink'] ); ?>"><?php echo esc_html( $item['source_title'] ? $item['source_title'] : $item['source_permalink'] ); ?></a></td>
							</tr>
							<tr>
								<td style="background:#f6f7f7;"><strong><?php esc_html_e( 'Section', 'kw-performance' ); ?></strong></td>
								<td><?php echo esc_html( $item['section'] ? $item['section'] : __( '(unknown)', 'kw-performance' ) ); ?></td>
							</tr>
							<tr>
								<td style="background:#f6f7f7;"><strong><?php esc_html_e( 'Status', 'kw-performance' ); ?></strong></td>
								<td><?php echo esc_html( $item['http_status'] ); ?></td>
							</tr>
						</table>
					<?php endforeach; ?>
				<?php endif; ?>

				<p><a href="<?php echo esc_url( $logs_url ); ?>" style="display:inline-block;background:#2271b1;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:4px;"><?php esc_html_e( 'View Full Log', 'kw-performance' ); ?></a></p>
			</td>
		</tr>
	</table>
</body>
</html>
