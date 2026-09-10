<?php
/**
 * Metas page template.
 *
 * Expects: array $post_types (configured post type slugs), string $current_type,
 * KWPERF_Metas_List_Table $list_table (already prepared).
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$pdf_export_url = wp_nonce_url(
	add_query_arg(
		array(
			'post_type' => $current_type,
			's'         => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		),
		admin_url( 'admin-post.php?action=kwperf_export_metas_pdf' )
	),
	'kwperf_export_metas_pdf'
);
?>
<div class="wrap kwperf-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Metas', 'kw-performance' ); ?></h1>
	<a href="<?php echo esc_url( $pdf_export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Download PDF', 'kw-performance' ); ?></a>

	<p class="description">
		<?php
		printf(
			/* translators: %s: "Yoast SEO", "Rank Math", both, or a fallback label */
			esc_html__( 'Meta titles and descriptions are read from, and saved to, %s.', 'kw-performance' ),
			esc_html( KWPERF_Meta_Manager::source_label() )
		);
		?>
	</p>

	<h2 class="nav-tab-wrapper">
		<?php foreach ( $post_types as $type ) : ?>
			<?php
			$type_object = get_post_type_object( $type );
			if ( ! $type_object ) {
				continue;
			}
			$tab_url = add_query_arg(
				array(
					'page'      => 'kwperf-metas',
					'post_type' => $type,
				),
				admin_url( 'admin.php' )
			);
			?>
			<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab <?php echo $type === $current_type ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $type_object->labels->name ); ?>
			</a>
		<?php endforeach; ?>
	</h2>

	<div id="kwperf-meta-action-result" class="notice" style="display:none;"></div>

	<form method="get">
		<input type="hidden" name="page" value="kwperf-metas" />
		<input type="hidden" name="post_type" value="<?php echo esc_attr( $current_type ); ?>" />
		<?php $list_table->search_box( __( 'Search Pages', 'kw-performance' ), 'kwperf-metas' ); ?>
		<?php $list_table->display(); ?>
	</form>
</div>
