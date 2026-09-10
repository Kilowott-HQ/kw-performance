<?php
/**
 * PDF export of the Metas screen's meta title/description data, styled to
 * match the companion Security Dashboard's own PDF exports (dark banded
 * table header, zebra-striped rows, clean sans-serif report).
 *
 * @package KW_Performance
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'FPDF' ) ) {
	require_once KWPERF_PLUGIN_DIR . 'vendor/fpdf/fpdf.php';
}

/**
 * Class KWPERF_Pdf_Export
 *
 * Builds and streams a PDF report of one post type's Page Title / Page Link /
 * Meta Title / Meta Description, matching what the Metas admin screen shows.
 */
class KWPERF_Pdf_Export extends FPDF {

	/**
	 * Report title printed at the top of every page.
	 *
	 * @var string
	 */
	private $report_title = '';

	/**
	 * Report subtitle ("Generated …") printed under the title.
	 *
	 * @var string
	 */
	private $report_subtitle = '';

	/**
	 * Column widths in mm, in display order.
	 *
	 * @var float[]
	 */
	private $col_widths = array( 40, 45, 40, 55 );

	/**
	 * Column header labels, in display order.
	 *
	 * @var string[]
	 */
	private $col_labels = array();

	/**
	 * Line height (mm) used for wrapped body text.
	 *
	 * @var float
	 */
	private $line_height = 5;

	/**
	 * Whether the next body row should use the zebra fill color.
	 *
	 * @var bool
	 */
	private $shade_row = false;

	/**
	 * Build the PDF for a post type (optionally filtered by search term) and
	 * stream it to the browser as a download. Exits on completion.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $search    Optional search term (matches the on-screen filter).
	 */
	public static function stream( $post_type, $search ) {
		$rows        = self::fetch_rows( $post_type, $search );
		$type_object = get_post_type_object( $post_type );
		$type_label  = $type_object ? $type_object->labels->name : $post_type;

		$pdf = new self();
		$pdf->SetMargins( 15, 15, 15 );
		// Auto-break disabled (AcceptPageBreak() is overridden below) so the table
		// header row can be redrawn manually, but the 20mm margin is still needed —
		// draw_row()'s own break check reads it back via $this->bMargin.
		$pdf->SetAutoPageBreak( false, 20 );

		$pdf->report_title    = sprintf(
			/* translators: 1: site name, 2: post type label */
			__( '%1$s — Metas (%2$s)', 'kw-performance' ),
			wp_specialchars_decode( get_bloginfo( 'name' ) ),
			$type_label
		);
		$pdf->report_subtitle = sprintf(
			/* translators: %s: generated date/time */
			__( 'Generated %s', 'kw-performance' ),
			wp_date( 'M j, Y, g:i A' )
		);
		$pdf->col_labels       = array(
			__( 'Page Title', 'kw-performance' ),
			__( 'Page Link', 'kw-performance' ),
			__( 'Meta Title', 'kw-performance' ),
			__( 'Meta Description', 'kw-performance' ),
		);

		$pdf->AliasNbPages();
		$pdf->SetTitle( $pdf->report_title, true ); // Raw UTF-8 in, isUTF8=true — FPDF does its own UTF-8→UTF-16 conversion for metadata.
		$pdf->AddPage();

		$pdf->SetFont( 'Helvetica', 'B', 12 );
		$pdf->SetTextColor( 29, 35, 39 );
		$pdf->Cell( 0, 8, self::to_latin1( sprintf( __( 'Metas (%s)', 'kw-performance' ), $type_label ) ), 0, 1 );
		$pdf->Ln( 2 );

		if ( empty( $rows ) ) {
			$pdf->SetFont( 'Helvetica', '', 10 );
			$pdf->SetTextColor( 0 );
			$pdf->Cell( 0, 8, self::to_latin1( __( 'No published items found.', 'kw-performance' ) ), 0, 1 );
		} else {
			$pdf->draw_table_header();

			foreach ( $rows as $row ) {
				$pdf->draw_row( $row );
			}
		}

		$pdf->Ln( 6 );
		$pdf->SetFont( 'Helvetica', '', 8 );
		$pdf->SetTextColor( 100, 105, 112 );
		$pdf->MultiCell(
			0,
			4,
			self::to_latin1(
				sprintf(
					/* translators: %s: "Yoast SEO", "Rank Math", both, or a fallback label */
					__( 'Generated directly by this site\'s KW Performance plugin. Meta Title and Meta Description reflect what is currently saved to %s at the time of export.', 'kw-performance' ),
					KWPERF_Meta_Manager::source_label()
				)
			)
		);

		$filename = 'kwperf-metas-' . sanitize_key( $post_type ) . '-' . gmdate( 'Y-m-d' ) . '.pdf';

		nocache_headers();
		$pdf->Output( 'D', $filename );
		exit;
	}

	/**
	 * Page header, repeated automatically on every page by FPDF.
	 */
	public function Header() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->SetFont( 'Helvetica', 'B', 15 );
		$this->SetTextColor( 29, 35, 39 );
		$this->Cell( 0, 8, self::to_latin1( $this->report_title ), 0, 1 );

		$this->SetFont( 'Helvetica', '', 9 );
		$this->SetTextColor( 100, 105, 112 );
		$this->Cell( 0, 6, self::to_latin1( $this->report_subtitle ), 0, 1 );
		$this->SetTextColor( 0 );
		$this->Ln( 3 );
	}

	/**
	 * Page footer, repeated automatically on every page by FPDF.
	 */
	public function Footer() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->SetY( -15 );
		$this->SetFont( 'Helvetica', '', 8 );
		$this->SetTextColor( 130 );
		$this->Cell( 0, 10, 'Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'C' );
		$this->SetTextColor( 0 );
	}

	/**
	 * Prevent FPDF's built-in auto page break from firing mid-row — page
	 * breaks are checked and issued manually in draw_row() instead, so the
	 * table header row can be redrawn at the top of each new page.
	 *
	 * @return bool
	 */
	public function AcceptPageBreak() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return false;
	}

	/**
	 * Draw the dark, white-text table header row (column labels).
	 */
	private function draw_table_header() {
		$row_height = 7;

		$this->SetFont( 'Helvetica', 'B', 9 );
		$this->SetFillColor( 29, 35, 39 );
		$this->SetTextColor( 255 );

		$x = $this->GetX();
		$y = $this->GetY();
		$cur_x = $x;

		foreach ( $this->col_widths as $i => $width ) {
			$this->Rect( $cur_x, $y, $width, $row_height, 'F' );
			$this->SetXY( $cur_x, $y );
			$this->Cell( $width, $row_height, self::to_latin1( $this->col_labels[ $i ] ), 0, 0, 'L' );
			$cur_x += $width;
		}

		$this->SetXY( $x, $y + $row_height );
		$this->SetTextColor( 0 );
		$this->shade_row = false;
	}

	/**
	 * Draw one zebra-striped, word-wrapped data row, breaking to a new page
	 * (and repeating the table header) first if the row wouldn't fit.
	 *
	 * @param array $row Row data from fetch_rows().
	 */
	private function draw_row( $row ) {
		$this->SetFont( 'Helvetica', '', 9 );

		$title_missing = '' === trim( (string) $row['meta_title'] );
		$desc_missing  = '' === trim( (string) $row['meta_description'] );

		// Converted to the font's single-byte encoding exactly once, then
		// reused for both line-count measurement and drawing — converting
		// an already-converted string a second time would corrupt it.
		$values = array_map(
			array( __CLASS__, 'to_latin1' ),
			array(
				$row['title'] ? $row['title'] : __( '(no title)', 'kw-performance' ),
				$row['permalink'],
				$title_missing ? __( '(missing)', 'kw-performance' ) : $row['meta_title'],
				$desc_missing ? __( '(missing)', 'kw-performance' ) : $row['meta_description'],
			)
		);

		$is_missing = array( false, false, $title_missing, $desc_missing );

		$height = $this->line_height; // At least one line tall.
		foreach ( $this->col_widths as $i => $width ) {
			$height = max( $height, $this->NbLines( $width, $values[ $i ] ) * $this->line_height );
		}

		if ( $this->GetY() + $height > $this->h - $this->bMargin ) {
			$this->AddPage( $this->CurOrientation );
			$this->draw_table_header();
			$this->SetFont( 'Helvetica', '', 9 ); // draw_table_header() leaves the font set to Bold for its labels.
		}

		$x = $this->GetX();
		$y = $this->GetY();

		$this->SetFillColor( 246, 247, 247 );
		if ( $this->shade_row ) {
			$this->Rect( $x, $y, array_sum( $this->col_widths ), $height, 'F' );
		}
		$this->shade_row = ! $this->shade_row;

		$cur_x = $x;
		foreach ( $this->col_widths as $i => $width ) {
			$this->SetXY( $cur_x, $y );

			if ( 1 === $i ) { // Page Link column, styled like a hyperlink.
				$this->SetTextColor( 34, 113, 177 );
			} elseif ( $is_missing[ $i ] ) {
				$this->SetTextColor( 150 );
			} else {
				$this->SetTextColor( 30 );
			}

			$this->MultiCell( $width, $this->line_height, $values[ $i ], 0, 'L' );
			$cur_x += $width;
		}

		$this->SetTextColor( 0 );
		$this->SetXY( $x, $y + $height );
	}

	/**
	 * Count how many lines FPDF's MultiCell would wrap a string into at a
	 * given column width, using the current font. Standard FPDF cookbook
	 * recipe — mirrors MultiCell's own internal word/character wrapping so
	 * row heights can be computed before anything is drawn.
	 *
	 * @param float  $width Column width (0 = remaining page width).
	 * @param string $text  Text already converted to the font's single-byte encoding.
	 * @return int
	 */
	private function NbLines( $width, $text ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$cw = $this->CurrentFont['cw'];

		if ( 0 == $width ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			$width = $this->w - $this->rMargin - $this->x;
		}

		$wmax = ( $width - 2 * $this->cMargin ) * 1000 / $this->FontSize;
		$s    = str_replace( "\r", '', (string) $text );
		$nb   = strlen( $s );

		if ( $nb > 0 && "\n" === $s[ $nb - 1 ] ) {
			--$nb;
		}

		$sep = -1;
		$i   = 0;
		$j   = 0;
		$l   = 0;
		$nl  = 1;

		while ( $i < $nb ) {
			$c = $s[ $i ];

			if ( "\n" === $c ) {
				++$i;
				$sep = -1;
				$j   = $i;
				$l   = 0;
				++$nl;
				continue;
			}

			if ( ' ' === $c ) {
				$sep = $i;
			}

			$l += isset( $cw[ $c ] ) ? $cw[ $c ] : 0;

			if ( $l > $wmax ) {
				if ( -1 === $sep ) {
					if ( $i === $j ) {
						++$i;
					}
				} else {
					$i = $sep + 1;
				}
				$j  = $i;
				$l  = 0;
				++$nl;
			} else {
				++$i;
			}
		}

		return $nl;
	}

	/**
	 * Query every published entry of a post type (optionally filtered by
	 * search term), matching what the Metas list table shows minus pagination.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $search    Optional search term.
	 * @return array List of array{title,permalink,meta_title,meta_description}.
	 */
	private static function fetch_rows( $post_type, $search ) {
		$query = new WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				's'                      => $search,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$rows = array();

		foreach ( $query->posts as $post ) {
			// See the matching try/catch in KWPERF_Metas_List_Table::prepare_items() —
			// a third-party CPT's own filters can throw outside a normal front-end
			// request; that shouldn't abort the whole PDF, just skip this row.
			try {
				$rows[] = array(
					'title'            => get_the_title( $post ),
					'permalink'        => get_permalink( $post ),
					'meta_title'       => KWPERF_Meta_Manager::get_title( $post->ID ),
					'meta_description' => KWPERF_Meta_Manager::get_description( $post->ID ),
				);
			} catch ( Throwable $e ) {
				error_log( sprintf( 'KW Performance: Metas PDF row failed for post %d (%s): %s', $post->ID, $post->post_type, $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		return $rows;
	}

	/**
	 * Convert a UTF-8 string to the single-byte encoding FPDF's core fonts
	 * expect. Falls back gracefully if iconv/mbstring aren't available.
	 *
	 * @param string $text UTF-8 text.
	 * @return string
	 */
	private static function to_latin1( $text ) {
		$text = (string) $text;

		if ( '' === $text ) {
			return '';
		}

		if ( function_exists( 'iconv' ) ) {
			$converted = @iconv( 'UTF-8', 'CP1252//TRANSLIT//IGNORE', $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false !== $converted ) {
				return $converted;
			}
		}

		if ( function_exists( 'mb_convert_encoding' ) ) {
			return mb_convert_encoding( $text, 'Windows-1252', 'UTF-8' );
		}

		return preg_replace( '/[^\x00-\x7F]/', '?', $text );
	}
}
