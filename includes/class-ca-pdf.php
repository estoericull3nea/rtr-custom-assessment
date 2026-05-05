<?php
/**
 * PDF generation class for exporting submissions.
 */

if (!defined('ABSPATH')) {
	exit;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Class uses plugin-specific prefix.
class Rtr_Custom_Assessment_Pdf
{
	/**
	 * Build PDF binary content from HTML.
	 *
	 * @param string $html
	 * @return string|false
	 */
	public function get_pdf_binary($html)
	{
		if (class_exists('TCPDF')) {
			try {
				return $this->get_binary_with_tcpdf($html);
			} catch (\Throwable $e) {
				// Fallback below.
			}
		}
		if (class_exists('Dompdf\Dompdf')) {
			try {
				return $this->get_binary_with_dompdf($html);
			} catch (\Throwable $e) {
				// Fallback below.
			}
		}
		return $this->get_binary_with_simple_pdf($html);
	}

	/**
	 * Build and save a fully-graphical PDF from structured report data.
	 * No external library required — uses raw PDF operators.
	 *
	 * @param array  $data
	 * @param string $absolute_path
	 * @return bool
	 */
	public function save_pdf_from_data( array $data, $absolute_path ) {
		$binary = $this->build_pdf_report_binary( $data );
		if ( false === $binary || '' === $binary ) {
			return false;
		}
		$dir = dirname( $absolute_path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return false !== file_put_contents( $absolute_path, $binary );
	}

	/**
	 * Render a fully-graphical assessment results report as a PDF binary string.
	 *
	 * Draws: dark header bar, overall score donut, category cards with coloured
	 * score badges, and a Q&A table — all using native PDF path/text operators
	 * (no TCPDF / Dompdf required).
	 *
	 * @param array $data Keys: name, email, total_score, overall_percent,
	 *                    categories[]{name,percent,level,summary},
	 *                    responses[]{question,answer}
	 * @return string|false
	 */
	private function build_pdf_report_binary( array $data ) {
		$pw = 612; $ph = 792;
		$ml = 45;  $mr = 45; $mb = 50;
		$cw = $pw - $ml - $mr; // 522 pt usable width

		// Colours: [R, G, B] on 0–1 scale
		$white  = array( 1.00, 1.00, 1.00 );
		$dark   = array( 0.07, 0.09, 0.13 );
		$gray   = array( 0.40, 0.43, 0.48 );
		$lgray  = array( 0.94, 0.95, 0.96 );
		$navy   = array( 0.11, 0.14, 0.20 );
		$border = array( 0.83, 0.85, 0.87 );
		$brand  = array( 0.69, 0.10, 0.10 );
		$green  = array( 0.12, 0.62, 0.32 );
		$orange = array( 0.85, 0.35, 0.04 );
		$red    = array( 0.75, 0.10, 0.10 );

		$level_col = function ( $level ) use ( $green, $orange, $red ) {
			if ( 'high'   === $level ) { return $green; }
			if ( 'medium' === $level ) { return $orange; }
			return $red;
		};

		// ---- Object pool ----
		$obj  = array();
		$aobj = function ( $body ) use ( &$obj ) {
			$obj[] = $body;
			return count( $obj );
		};

		$fr = $aobj( '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>' );
		$fb = $aobj( '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>' );
		$logo = $this->build_pdf_logo_image_object( isset( $data['logo_url'] ) ? (string) $data['logo_url'] : '' );
		$logo_obj_id = 0;
		if ( is_array( $logo ) && ! empty( $logo['object_body'] ) ) {
			$logo_obj_id = $aobj( (string) $logo['object_body'] );
		}
		$po = $aobj( '<< /Type /Pages /Kids [] /Count 0 >>' );

		// ---- Page state ----
		$pages = array();
		$s     = '';             // current content stream
		$y     = (float) $ph;   // current Y (top of page, decreasing)

		$fp = null;
		$fp = function () use ( &$s, &$y, &$pages, $ph, $mb, $pw, $fr, $fb, $po, $aobj, $logo_obj_id ) {
			$xobject = $logo_obj_id > 0 ? ' /XObject << /Im1 ' . $logo_obj_id . ' 0 R >>' : '';
			$co      = $aobj( '<< /Length ' . strlen( $s ) . " >>\nstream\n{$s}endstream" );
			$pages[] = $aobj(
				"<< /Type /Page /Parent {$po} 0 R"
				. " /MediaBox [0 0 {$pw} {$ph}]"
				. " /Resources << /Font << /F1 {$fr} 0 R /F2 {$fb} 0 R >>{$xobject} >>"
				. " /Contents {$co} 0 R >>"
			);
			$s = '';
			$y = (float) ( $ph - $mb );
		};

		// Ensure $h pts of vertical space; flush page if needed.
		$ns = function ( $h ) use ( &$y, $mb, &$fp ) {
			if ( $y - (float) $h < (float) $mb ) {
				$fp();
			}
		};

		// ---- Drawing helpers (all append to $s) ----

		// Filled rectangle (x,y = bottom-left corner)
		$rf = function ( $x, $gy, $w, $h, $c ) use ( &$s ) {
			$s .= sprintf(
				"q %.3f %.3f %.3f rg %.4f %.4f %.4f %.4f re f Q\n",
				$c[0], $c[1], $c[2], (float) $x, (float) $gy, (float) $w, (float) $h
			);
		};

		// Stroked rectangle
		$rs = function ( $x, $gy, $w, $h, $c, $lw = 0.5 ) use ( &$s ) {
			$s .= sprintf(
				"q %.3f %.3f %.3f RG %.2f w %.4f %.4f %.4f %.4f re S Q\n",
				$c[0], $c[1], $c[2], (float) $lw, (float) $x, (float) $gy, (float) $w, (float) $h
			);
		};

		// Horizontal line
		$ln = function ( $x1, $y1, $x2, $y2, $c, $lw = 0.5 ) use ( &$s ) {
			$s .= sprintf(
				"q %.3f %.3f %.3f RG %.2f w %.4f %.4f m %.4f %.4f l S Q\n",
				$c[0], $c[1], $c[2], (float) $lw,
				(float) $x1, (float) $y1, (float) $x2, (float) $y2
			);
		};

		// Filled circle via 4 cubic Bézier curves
		$circle = function ( $cx, $cy, $r, $c ) use ( &$s ) {
			$cx = (float) $cx; $cy = (float) $cy; $r = (float) $r;
			$k  = $r * 0.5523;
			$s .= sprintf( "q %.3f %.3f %.3f rg\n", $c[0], $c[1], $c[2] );
			$s .= sprintf( "%.4f %.4f m\n", $cx, $cy + $r );
			$s .= sprintf( "%.4f %.4f %.4f %.4f %.4f %.4f c\n", $cx+$k,$cy+$r, $cx+$r,$cy+$k, $cx+$r,$cy );
			$s .= sprintf( "%.4f %.4f %.4f %.4f %.4f %.4f c\n", $cx+$r,$cy-$k, $cx+$k,$cy-$r, $cx,$cy-$r );
			$s .= sprintf( "%.4f %.4f %.4f %.4f %.4f %.4f c\n", $cx-$k,$cy-$r, $cx-$r,$cy-$k, $cx-$r,$cy );
			$s .= sprintf( "%.4f %.4f %.4f %.4f %.4f %.4f c\n", $cx-$r,$cy+$k, $cx-$k,$cy+$r, $cx,$cy+$r );
			$s .= "f Q\n";
		};

		// Single text line at absolute position (Tm = absolute text matrix)
		$tl = function ( $text, $x, $ty, $fn, $sz, $c ) use ( &$s ) {
			$e = preg_replace( '/[^\x20-\x7E]/', '', (string) $text );
			$e = str_replace( array( '\\', '(', ')' ), array( '\\\\', '\(', '\)' ), $e );
			$s .= sprintf(
				"BT /%s %.2f Tf %.3f %.3f %.3f rg 1 0 0 1 %.4f %.4f Tm (%s) Tj ET\n",
				$fn, (float) $sz, $c[0], $c[1], $c[2], (float) $x, (float) $ty, $e
			);
		};

		// Word-wrap text to fit within $max_w pts at $pt_sz font size.
		$ww = function ( $text, $max_w, $pt_sz ) {
			// Helvetica average char width ≈ 0.52 × point size
			$chars   = max( 20, (int) ( (float) $max_w / max( 1.0, (float) $pt_sz * 0.52 ) ) );
			$wrapped = wordwrap( (string) $text, $chars, "\n", false );
			return array_map( 'trim', explode( "\n", $wrapped ) );
		};

		// =================== PAGE 1 HEADER ===================

		$hh = 82;
		$rf( 0, $ph - $hh, $pw, $hh, $navy );

		// Brand logo image (fallback to text if unavailable).
		$logo_drawn = false;
		if ( $logo_obj_id > 0 && is_array( $logo ) && ! empty( $logo['width'] ) && ! empty( $logo['height'] ) ) {
			$src_w = max( 1.0, (float) $logo['width'] );
			$src_h = max( 1.0, (float) $logo['height'] );
			$max_w = 72.0;
			$max_h = 44.0;
			$scale = min( $max_w / $src_w, $max_h / $src_h );
			$dw = $src_w * $scale;
			$dh = $src_h * $scale;
			$dx = $ml;
			$dy = $ph - 54.0;
			$s .= sprintf(
				"q %.4f 0 0 %.4f %.4f %.4f cm /Im1 Do Q\n",
				(float) $dw,
				(float) $dh,
				(float) $dx,
				(float) $dy
			);
			$logo_drawn = true;
		}
		if ( ! $logo_drawn ) {
			$tl( 'root.',   $ml,      $ph - 25, 'F2', 15, $brand );
			$tl( 'to rise', $ml,      $ph - 43, 'F2', 15, $white );
		}
		// Report title + subtitle
		$tl( 'Natural Attributes Cataloging', $ml + 78, $ph - 27, 'F2', 12, $white );
		$tl( 'Full Results Report',            $ml + 78, $ph - 44, 'F1',  9, array( 0.68, 0.70, 0.74 ) );
		// Meta line
		$meta_str = ( $data['name'] ?? '' ) . '   |   ' . ( $data['email'] ?? '' )
		          . '   |   Total Score: ' . ( $data['total_score'] ?? '' );
		$tl( $meta_str, $ml, $ph - 65, 'F1', 8, array( 0.60, 0.63, 0.68 ) );

		$y = (float) ( $ph - $hh );

		// =================== OVERALL SCORE BAND ===================

		$band_h = 80;
		$rf( 0, $y - $band_h, $pw, $band_h, $lgray );
		$ln( 0, $y - $band_h, (float) $pw, $y - $band_h, $border );

		$overall_pct = (int) ( $data['overall_percent'] ?? 0 );
		$ovr_level   = $overall_pct >= 80 ? 'high' : ( $overall_pct >= 50 ? 'medium' : 'low' );
		$ovr_col     = $level_col( $ovr_level );

		// Donut circle
		$cr  = 30.0;
		$ccx = (float) ( $ml + $cr + 8 );
		$ccy = $y - $band_h / 2.0;
		$circle( $ccx, $ccy, $cr, $ovr_col );
		$circle( $ccx, $ccy, $cr * 0.58, $lgray ); // hole

		// Percentage inside donut
		$pct_s   = $overall_pct . '%';
		$pct_off = max( 3.0, ( $cr * 2 - strlen( $pct_s ) * 6.0 ) / 2.0 );
		$tl( $pct_s, $ccx - $cr + $pct_off, $ccy - 4.5, 'F2', 10, $dark );

		// Congratulations text beside the donut
		$tx = $ccx + $cr + 18;
		$tl( 'Congratulations on Completing Your Discovery Journey!', $tx, $y - 18, 'F2', 11, $dark );
		$tl( 'Your personalized results and score breakdown are included below.', $tx, $y - 34, 'F1', 8.5, $gray );
		$tl( 'Overall Score: ' . $overall_pct . '%  [' . strtoupper( $ovr_level ) . ']', $tx, $y - 51, 'F2', 9.5, $ovr_col );
		$name_email = ( $data['name'] ?? '' ) . '   –   ' . ( $data['email'] ?? '' );
		$tl( $name_email, $tx, $y - 66, 'F1', 8, $gray );

		$y -= (float) ( $band_h + 20 );

		// =================== CATEGORY CARDS ===================

		$tl( 'Category Results', $ml, $y, 'F2', 13, $dark );
		$ln( $ml, $y - 5, $ml + 148, $y - 5, $brand, 1.5 );
		$y -= 22.0;

		$badge_w = 94;

		foreach ( ( $data['categories'] ?? array() ) as $cat ) {
			$pct     = (int) ( $cat['percent'] ?? 0 );
			$lv      = (string) ( $cat['level']   ?? 'low' );
			$col     = $level_col( $lv );
			$name    = (string) ( $cat['name']    ?? '' );
			$summary = (string) ( $cat['summary'] ?? '' );

			$sum_max_w = (float) ( $cw - $badge_w - 28 );
			$sum_lines = $ww( $summary, $sum_max_w, 9.0 );
			$card_h    = (float) max( 78.0, 42.0 + count( $sum_lines ) * 12.5 );

			$ns( $card_h + 10 );

			$cb = $y - $card_h; // card bottom Y

			// Card body
			$rf( $ml, $cb, $cw, $card_h, $white );
			$rs( $ml, $cb, $cw, $card_h, $border, 0.6 );

			// Score badge (right column)
			$bx = (float) ( $ml + $cw - $badge_w );
			$rf( $bx, $cb, (float) $badge_w, $card_h, $col );

			$tl( 'Your score', $bx + 8, $y - 17, 'F1', 8, $white );

			$pstr   = $pct . '%';
			$pstr_x = $bx + max( 4.0, ( (float) $badge_w - strlen( $pstr ) * 10.5 ) / 2.0 );
			$tl( $pstr, $pstr_x, $y - 44, 'F2', 18, $white );

			// Level pill at bottom of badge
			$pill_w = 58.0; $pill_h = 14.0;
			$pill_x = $bx + ( (float) $badge_w - $pill_w ) / 2.0;
			$pill_y = $cb + 8.0;
			$rf( $pill_x, $pill_y, $pill_w, $pill_h, $white );
			$lv_label = strtoupper( $lv );
			$lv_x     = $pill_x + max( 3.0, ( $pill_w - strlen( $lv_label ) * 6.5 ) / 2.0 );
			$tl( $lv_label, $lv_x, $pill_y + 3.5, 'F2', 8, $col );

			// Category name
			$tl( $name, $ml + 12, $y - 18, 'F2', 12, $dark );

			// Summary (word-wrapped)
			$sy = $y - 35.0;
			foreach ( $sum_lines as $sl ) {
				if ( '' !== $sl ) {
					$tl( $sl, $ml + 12, $sy, 'F1', 9.0, $gray );
				}
				$sy -= 12.5;
			}

			$y = $cb - 8.0;
		}

		// =================== QUESTION RESPONSES ===================

		$ns( 55 );
		$y -= 14.0;
		$tl( 'Question Responses', $ml, $y, 'F2', 13, $dark );
		$ln( $ml, $y - 5, $ml + 174, $y - 5, $brand, 1.5 );
		$y -= 22.0;

		// Table header
		$th     = 18.0;
		$resp_x = (float) ( $ml + $cw - 72 );
		$rf( $ml, $y - $th, (float) $cw, $th, $navy );
		$tl( 'Question', $ml + 8,     $y - 13, 'F2', 8.5, $white );
		$tl( 'Response', $resp_x + 5, $y - 13, 'F2', 8.5, $white );
		$y -= $th;

		$even = false;
		foreach ( ( $data['responses'] ?? array() ) as $row ) {
			$q  = (string) ( $row['question'] ?? '' );
			$a  = (string) ( $row['answer']   ?? '' );
			$ql = $ww( $q, (float) ( $cw - 95 ), 8.5 );
			$rh = (float) max( 18.0, count( $ql ) * 11.0 + 8.0 );

			$ns( $rh );

			if ( $even ) {
				$rf( $ml, $y - $rh, (float) $cw, $rh, array( 0.96, 0.97, 0.98 ) );
			}
			$ln( $ml, $y - $rh, (float) ( $ml + $cw ), $y - $rh, $border, 0.3 );

			$qy = $y - 10.5;
			foreach ( $ql as $ql_line ) {
				if ( '' !== $ql_line ) {
					$tl( $ql_line, $ml + 8, $qy, 'F1', 8.5, $dark );
				}
				$qy -= 11.0;
			}
			$tl( $a, $resp_x + 5, $y - 10.5, 'F2', 8.5, $dark );

			$y    -= $rh;
			$even  = ! $even;
		}

		$fp(); // flush final page

		// ---- Finalise PDF structure ----
		$kids_str       = implode( ' ', array_map( function ( $p ) { return "{$p} 0 R"; }, $pages ) );
		$obj[ $po - 1 ] = "<< /Type /Pages /Kids [{$kids_str}] /Count " . count( $pages ) . ' >>';
		$cat_n          = $aobj( '<< /Type /Catalog /Pages ' . $po . ' 0 R >>' );

		$pdf  = "%PDF-1.4\n";
		$offs = array( 0 );
		foreach ( $obj as $i => $b ) {
			$offs[] = strlen( $pdf );
			$pdf   .= ( $i + 1 ) . " 0 obj\n{$b}\nendobj\n";
		}
		$xoff = strlen( $pdf );
		$n    = count( $obj ) + 1;
		$pdf .= "xref\n0 {$n}\n0000000000 65535 f \n";
		for ( $i = 1; $i < $n; $i++ ) {
			$pdf .= sprintf( '%010d 00000 n ', $offs[ $i ] ) . "\n";
		}
		$pdf .= "trailer\n<< /Size {$n} /Root {$cat_n} 0 R >>\nstartxref\n{$xoff}\n%%EOF";

		return $pdf;
	}

	/**
	 * Save PDF to an absolute path.
	 *
	 * @param string $html
	 * @param string $absolute_path
	 * @return bool
	 */
	public function save_pdf($html, $absolute_path)
	{
		$binary = $this->get_pdf_binary($html);
		if (false === $binary || '' === $binary) {
			return false;
		}

		$dir = dirname($absolute_path);
		if (!is_dir($dir)) {
			wp_mkdir_p($dir);
		}

		return false !== file_put_contents($absolute_path, $binary);
	}

	/**
	 * Build and save a PDF directly from an array of pre-formatted text lines.
	 * Bypasses all HTML parsing; the binary builder is called directly.
	 *
	 * @param string[] $lines
	 * @param string   $absolute_path
	 * @return bool
	 */
	public function save_pdf_from_lines( array $lines, $absolute_path ) {
		if ( empty( $lines ) ) {
			$lines = array( 'No content.' );
		}

		$binary = $this->build_binary_from_lines( $lines );
		if ( false === $binary || '' === $binary ) {
			return false;
		}

		$dir = dirname( $absolute_path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		return false !== file_put_contents( $absolute_path, $binary );
	}

	/**
	 * Build PDF binary from pre-formatted lines (font size aware, bold markers).
	 *
	 * Lines prefixed with "##H1 " use 14pt Helvetica-Bold.
	 * Lines prefixed with "##H2 " use 11pt Helvetica-Bold.
	 * Lines of "##HR" render as a visual separator.
	 * All other lines use 10pt Helvetica.
	 *
	 * @param string[] $lines
	 * @return string|false
	 */
	private function build_binary_from_lines( array $lines ) {
		$page_width  = 612;
		$page_height = 792;
		$margin_x    = 50;
		$margin_y    = 50;
		$usable_h    = $page_height - ( 2 * $margin_y );

		$objects    = array();
		$add_object = function ( $body ) use ( &$objects ) {
			$objects[] = $body;
			return count( $objects );
		};

		$font_reg  = $add_object( '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>' );
		$font_bold = $add_object( '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>' );
		$pages_obj = $add_object( '<< /Type /Pages /Kids [] /Count 0 >>' );

		// Split lines into pages based on accumulated height.
		$pages_lines  = array();
		$current_page = array();
		$used_h       = 0;

		foreach ( $lines as $line ) {
			if ( strncmp( $line, '##H1 ', 5 ) === 0 ) {
				$lh = 22;
			} elseif ( strncmp( $line, '##H2 ', 5 ) === 0 ) {
				$lh = 18;
			} elseif ( '##HR' === $line ) {
				$lh = 12;
			} else {
				$lh = 14;
			}

			if ( $used_h + $lh > $usable_h && ! empty( $current_page ) ) {
				$pages_lines[] = $current_page;
				$current_page  = array();
				$used_h        = 0;
			}

			$current_page[] = array( 'line' => $line, 'lh' => $lh );
			$used_h        += $lh;
		}
		if ( ! empty( $current_page ) ) {
			$pages_lines[] = $current_page;
		}

		$page_objects = array();
		foreach ( $pages_lines as $page_entries ) {
			$content = "BT\n";
			$y       = $page_height - $margin_y;

			foreach ( $page_entries as $entry ) {
				$line = $entry['line'];
				$lh   = $entry['lh'];

				if ( strncmp( $line, '##H1 ', 5 ) === 0 ) {
					$text    = substr( $line, 5 );
					$font_sz = "/F2 14 Tf\n";
				} elseif ( strncmp( $line, '##H2 ', 5 ) === 0 ) {
					$text    = substr( $line, 5 );
					$font_sz = "/F2 11 Tf\n";
				} elseif ( '##HR' === $line ) {
					$text    = str_repeat( '_', 88 );
					$font_sz = "/F1 8 Tf\n";
				} else {
					$text    = $line;
					$font_sz = "/F1 10 Tf\n";
				}

				// Use Tm (text matrix) for absolute positioning — avoids
				// cumulative-offset bugs that Td causes with variable line heights.
				$escaped  = $this->pdf_escape_text( $text );
				$content .= $font_sz;
				$content .= "1 0 0 1 " . $margin_x . ' ' . $y . " Tm\n";
				$content .= '(' . $escaped . ") Tj\n";
				$y       -= $lh;
			}

			$content .= "ET\n";

			$content_obj  = $add_object( "<< /Length " . strlen( $content ) . " >>\nstream\n" . $content . "endstream" );
			$page_objects[] = $add_object(
				"<< /Type /Page /Parent " . $pages_obj . " 0 R"
				. " /MediaBox [0 0 " . $page_width . ' ' . $page_height . "]"
				. " /Resources << /Font << /F1 " . $font_reg . " 0 R /F2 " . $font_bold . " 0 R >> >>"
				. " /Contents " . $content_obj . " 0 R >>"
			);
		}

		$kids = array();
		foreach ( $page_objects as $po ) {
			$kids[] = $po . ' 0 R';
		}
		$objects[ $pages_obj - 1 ] = "<< /Type /Pages /Kids [ " . implode( ' ', $kids ) . " ] /Count " . count( $page_objects ) . " >>";

		$catalog_obj = $add_object( "<< /Type /Catalog /Pages " . $pages_obj . " 0 R >>" );

		$pdf     = "%PDF-1.4\n";
		$offsets = array( 0 );
		for ( $i = 0, $len = count( $objects ); $i < $len; $i++ ) {
			$offsets[] = strlen( $pdf );
			$pdf      .= ( $i + 1 ) . " 0 obj\n" . $objects[ $i ] . "\nendobj\n";
		}

		$xref_offset = strlen( $pdf );
		$pdf        .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n";
		$pdf        .= "0000000000 65535 f \n";
		for ( $i = 1; $i <= count( $objects ); $i++ ) {
			$pdf .= sprintf( '%010d 00000 n ', $offsets[ $i ] ) . "\n";
		}
		$pdf .= "trailer\n<< /Size " . ( count( $objects ) + 1 ) . " /Root " . $catalog_obj . " 0 R >>\n";
		$pdf .= "startxref\n" . $xref_offset . "\n%%EOF";

		return $pdf;
	}

	/**
	 * Generate PDF from HTML content.
	 *
	 * @param string $html
	 */
	public function generate($html)
	{
		// Use TCPDF if available, otherwise use a simple approach
		if (class_exists('TCPDF')) {
			$this->generate_with_tcpdf($html);
		} else {
			$this->generate_simple($html);
		}
	}

	/**
	 * Export PDF with proper headers and filename.
	 *
	 * @param string $html
	 * @param string $filename
	 */
	public function export_pdf($html, $filename)
	{
		// Set headers for PDF download
		header('Content-Type: application/pdf');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Cache-Control: no-cache, no-store, must-revalidate');
		header('Pragma: no-cache');
		header('Expires: 0');

		// Try using TCPDF or DomPDF, fallback to a built-in minimal PDF renderer.
		if (class_exists('TCPDF')) {
			$this->generate_with_tcpdf($html);
		} elseif (class_exists('Dompdf\Dompdf')) {
			$this->generate_with_dompdf($html);
		} else {
			$binary = $this->get_binary_with_simple_pdf($html);
			if (false === $binary || '' === $binary) {
				return;
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw PDF binary output.
			echo $binary;
		}
	}

	/**
	 * Generate PDF using TCPDF library.
	 *
	 * @param string $html
	 */
	private function generate_with_tcpdf($html)
	{
		$pdf = new \TCPDF();
		$mono_font = defined('PDF_FONT_MONOSPACED') ? PDF_FONT_MONOSPACED : 'courier';
		$pdf->SetDefaultMonospacedFont($mono_font);
		$pdf->SetMargins(15, 15, 15);
		$pdf->SetAutoPageBreak(true, 15);
		$pdf->AddPage();
		$pdf->SetFont('helvetica', '', 10);
		$pdf->writeHTML($html, true, false, true, false, '');
		$pdf->Output('', 'I');
	}

	/**
	 * Generate PDF binary via TCPDF.
	 *
	 * @param string $html
	 * @return string
	 */
	private function get_binary_with_tcpdf($html)
	{
		$pdf = new \TCPDF();
		$mono_font = defined('PDF_FONT_MONOSPACED') ? PDF_FONT_MONOSPACED : 'courier';
		$pdf->SetDefaultMonospacedFont($mono_font);
		$pdf->SetMargins(15, 15, 15);
		$pdf->SetAutoPageBreak(true, 15);
		$pdf->AddPage();
		$pdf->SetFont('helvetica', '', 10);
		$pdf->writeHTML($html, true, false, true, false, '');
		return $pdf->Output('', 'S');
	}

	/**
	 * Generate PDF using DomPDF library.
	 *
	 * @param string $html
	 */
	private function generate_with_dompdf($html)
	{
		$dompdf = new \Dompdf\Dompdf();
		$dompdf->loadHtml($html);
		$dompdf->setPaper('A4');
		$dompdf->render();
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Dompdf returns binary PDF content, not HTML output.
		echo $dompdf->output();
	}

	/**
	 * Generate PDF binary via Dompdf.
	 *
	 * @param string $html
	 * @return string
	 */
	private function get_binary_with_dompdf($html)
	{
		$dompdf = new \Dompdf\Dompdf();
		$dompdf->loadHtml($html);
		$dompdf->setPaper('A4');
		$dompdf->render();
		return $dompdf->output();
	}

	/**
	 * Generate PDF using a minimal built-in text renderer.
	 *
	 * @param string $html
	 */
	private function generate_simple($html)
	{
		$binary = $this->get_binary_with_simple_pdf($html);
		if (false === $binary || '' === $binary) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw PDF binary output.
		echo $binary;
	}

	/**
	 * Build a valid PDF binary without external libraries (text-only fallback).
	 *
	 * @param string $html
	 * @return string|false
	 */
	private function get_binary_with_simple_pdf($html)
	{
		$lines = $this->extract_structured_lines_from_html((string) $html);
		if (empty($lines)) {
			$lines = $this->extract_basic_lines_from_html((string) $html);
		}
		if (empty($lines)) {
			$lines = array('Assessment Results');
		}

		$page_width = 612;
		$page_height = 792;
		$margin_x = 50;
		$margin_y = 50;
		$line_height = 14;
		$max_lines_per_page = max(1, (int) floor(($page_height - (2 * $margin_y)) / $line_height));
		$line_pages = array_chunk($lines, $max_lines_per_page);

		$objects = array();
		$add_object = function ($body) use (&$objects) {
			$objects[] = $body;
			return count($objects);
		};

		$font_obj = $add_object("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>");
		$pages_obj = $add_object("<< /Type /Pages /Kids [] /Count 0 >>");
		$page_objects = array();

		foreach ($line_pages as $page_lines) {
			$content = "BT\n/F1 10 Tf\n" . $margin_x . ' ' . ($page_height - $margin_y) . " Td\n";
			foreach ($page_lines as $line) {
				$escaped = $this->pdf_escape_text($line);
				$content .= '(' . $escaped . ") Tj\n0 -" . $line_height . " Td\n";
			}
			$content .= "ET\n";

			$content_obj = $add_object("<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream");
			$page_obj = $add_object(
				"<< /Type /Page /Parent " . $pages_obj . " 0 R /MediaBox [0 0 " . $page_width . ' ' . $page_height . "] /Resources << /Font << /F1 " . $font_obj . " 0 R >> >> /Contents " . $content_obj . " 0 R >>"
			);
			$page_objects[] = $page_obj;
		}

		$kids = array();
		foreach ($page_objects as $page_obj) {
			$kids[] = $page_obj . ' 0 R';
		}
		$objects[$pages_obj - 1] = "<< /Type /Pages /Kids [ " . implode(' ', $kids) . " ] /Count " . count($page_objects) . " >>";

		$catalog_obj = $add_object("<< /Type /Catalog /Pages " . $pages_obj . " 0 R >>");

		$pdf = "%PDF-1.4\n";
		$offsets = array(0);
		for ($i = 0, $len = count($objects); $i < $len; $i++) {
			$offsets[] = strlen($pdf);
			$obj_num = $i + 1;
			$pdf .= $obj_num . " 0 obj\n" . $objects[$i] . "\nendobj\n";
		}

		$xref_offset = strlen($pdf);
		$pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
		$pdf .= "0000000000 65535 f \n";
		for ($i = 1; $i <= count($objects); $i++) {
			$pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
		}
		$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root " . $catalog_obj . " 0 R >>\n";
		$pdf .= "startxref\n" . $xref_offset . "\n%%EOF";

		return $pdf;
	}

	/**
	 * Very safe HTML-to-lines parser (no DOM/libxml dependency).
	 *
	 * @param string $html
	 * @return string[]
	 */
	private function extract_basic_lines_from_html($html)
	{
		$normalized = (string) $html;
		$break_tags = array('</tr>', '</p>', '</div>', '</h1>', '</h2>', '</h3>', '</h4>', '<br>', '<br/>', '<br />');
		$normalized = str_ireplace($break_tags, "\n", $normalized);
		$normalized = str_ireplace(array('</td>', '</th>'), ' | ', $normalized);
		$normalized = wp_strip_all_tags($normalized);
		$normalized = html_entity_decode($normalized, ENT_QUOTES, 'UTF-8');
		$normalized = preg_replace('/[ \t]+/', ' ', $normalized);
		$normalized = preg_replace("/\n{2,}/", "\n", (string) $normalized);

		$raw_lines = explode("\n", (string) $normalized);
		$lines = array();
		foreach ($raw_lines as $line) {
			$line = $this->normalize_text($line);
			if ('' === $line) {
				continue;
			}
			$wrapped = $this->wrap_text_line($line, 95);
			foreach ($wrapped as $wline) {
				$lines[] = $wline;
			}
		}

		return $lines;
	}

	/**
	 * Convert report HTML into readable lines, preserving table-like structure.
	 *
	 * @param string $html
	 * @return string[]
	 */
	private function extract_structured_lines_from_html($html)
	{
		$lines = array();
		if (class_exists('DOMDocument') && function_exists('libxml_use_internal_errors')) {
			$dom = new \DOMDocument();
			$html_doc = '<!doctype html><html><body>' . $html . '</body></html>';
			$prev_use_internal_errors = libxml_use_internal_errors(true);
			$loaded = false;
			try {
				$loaded = $dom->loadHTML($html_doc);
			} catch (\Throwable $e) {
				$loaded = false;
			}
			libxml_clear_errors();
			libxml_use_internal_errors($prev_use_internal_errors);
			if ($loaded) {
				$body = $dom->getElementsByTagName('body')->item(0);
				if ($body) {
					foreach ($body->childNodes as $child) {
						$this->collect_node_lines($child, $lines);
					}
				}
			}
		}

		if (empty($lines)) {
			$fallback = html_entity_decode(wp_strip_all_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$fallback = preg_replace('/\s+/', ' ', $fallback);
			$fallback = trim((string) $fallback);
			if ('' !== $fallback) {
				$lines = $this->wrap_text_line($fallback, 95);
			}
		}

		return $lines;
	}

	/**
	 * Walk DOM nodes and collect line-oriented text.
	 *
	 * @param \DOMNode $node
	 * @param string[] $lines
	 * @return void
	 */
	private function collect_node_lines($node, &$lines)
	{
		if (!isset($node->nodeType)) {
			return;
		}

		if (XML_TEXT_NODE === $node->nodeType) {
			$text = $this->normalize_text($node->nodeValue);
			if ('' !== $text) {
				$wrapped = $this->wrap_text_line($text, 95);
				foreach ($wrapped as $wline) {
					$lines[] = $wline;
				}
			}
			return;
		}

		if (XML_ELEMENT_NODE !== $node->nodeType) {
			return;
		}

		$name = strtolower((string) $node->nodeName);
		if ('table' === $name) {
			$table_lines = $this->table_node_to_lines($node);
			foreach ($table_lines as $line) {
				$lines[] = $line;
			}
			$lines[] = '';
			return;
		}

		if (in_array($name, array('h1', 'h2', 'h3', 'h4'), true)) {
			$text = $this->normalize_text($node->textContent);
			if ('' !== $text) {
				$lines[] = $text;
				$lines[] = str_repeat('-', min(95, strlen($text)));
			}
			$lines[] = '';
			return;
		}

		if (in_array($name, array('p', 'div', 'li'), true)) {
			$text = $this->normalize_text($node->textContent);
			if ('' !== $text) {
				$wrapped = $this->wrap_text_line($text, 95);
				foreach ($wrapped as $wline) {
					$lines[] = $wline;
				}
			}
			$lines[] = '';
			return;
		}

		foreach ($node->childNodes as $child) {
			$this->collect_node_lines($child, $lines);
		}
	}

	/**
	 * Render HTML table node as fixed-width text table lines.
	 *
	 * @param \DOMNode $table
	 * @return string[]
	 */
	private function table_node_to_lines($table)
	{
		$rows = array();
		foreach ($table->childNodes as $child) {
			$name = strtolower((string) $child->nodeName);
			if ('tr' === $name) {
				$rows[] = $child;
				continue;
			}
			if (in_array($name, array('thead', 'tbody', 'tfoot'), true)) {
				foreach ($child->childNodes as $sub_row) {
					if ('tr' === strtolower((string) $sub_row->nodeName)) {
						$rows[] = $sub_row;
					}
				}
			}
		}

		$matrix = array();
		$col_count = 0;
		$header_cells = array();
		foreach ($rows as $row) {
			$cols = array();
			$is_header_row = false;
			foreach ($row->childNodes as $cell) {
				$cell_name = strtolower((string) $cell->nodeName);
				if (!in_array($cell_name, array('th', 'td'), true)) {
					continue;
				}
				if ('th' === $cell_name) {
					$is_header_row = true;
				}
				$cols[] = $this->normalize_text($cell->textContent);
			}
			if (!empty($cols)) {
				if ($is_header_row && empty($header_cells)) {
					$header_cells = $cols;
					$col_count = max($col_count, count($cols));
					continue;
				}
				$matrix[] = $cols;
				$col_count = max($col_count, count($cols));
			}
		}

		if (empty($matrix) || $col_count <= 0) {
			return array();
		}
		$lines = array();
		if (!empty($header_cells)) {
			$lines[] = implode(' | ', $header_cells);
			$lines[] = str_repeat('-', 95);
		}

		foreach ($matrix as $cols) {
			$lines[] = str_repeat('-', 95);
			for ($i = 0; $i < $col_count; $i++) {
				$label = isset($header_cells[$i]) && '' !== $header_cells[$i] ? $header_cells[$i] : ('Column ' . ($i + 1));
				$value = isset($cols[$i]) ? $cols[$i] : '';
				if ('' === $value) {
					continue;
				}
				$wrapped = $this->wrap_text_line($value, 78);
				if (empty($wrapped)) {
					continue;
				}
				$lines[] = $label . ': ' . array_shift($wrapped);
				foreach ($wrapped as $cont) {
					$lines[] = '  ' . $cont;
				}
			}
		}
		$lines[] = str_repeat('-', 95);

		return $lines;
	}

	/**
	 * Build an image XObject body for a PNG/JPEG logo URL.
	 *
	 * Converts PNG to JPEG via GD when possible to keep PDF embedding simple.
	 *
	 * @param string $logo_url
	 * @return array|null
	 */
	private function build_pdf_logo_image_object( $logo_url ) {
		$path = $this->resolve_local_logo_path( (string) $logo_url );
		if ( '' === $path || ! is_readable( $path ) ) {
			return null;
		}

		$info = @getimagesize( $path );
		if ( ! is_array( $info ) || empty( $info[0] ) || empty( $info[1] ) ) {
			return null;
		}

		$mime = isset( $info['mime'] ) ? strtolower( (string) $info['mime'] ) : '';
		$w = (int) $info[0];
		$h = (int) $info[1];
		$jpeg_binary = '';

		if ( 'image/jpeg' === $mime || 'image/jpg' === $mime ) {
			$jpeg_binary = (string) @file_get_contents( $path );
		} elseif ( 'image/png' === $mime && function_exists( 'imagecreatefrompng' ) && function_exists( 'imagejpeg' ) ) {
			$img = @imagecreatefrompng( $path );
			if ( $img ) {
				if ( function_exists( 'imagecreatetruecolor' ) ) {
					$bg = imagecreatetruecolor( (int) $w, (int) $h );
					$white = imagecolorallocate( $bg, 255, 255, 255 );
					imagefilledrectangle( $bg, 0, 0, (int) $w, (int) $h, $white );
					imagecopy( $bg, $img, 0, 0, 0, 0, (int) $w, (int) $h );
					imagedestroy( $img );
					$img = $bg;
				}
				ob_start();
				imagejpeg( $img, null, 90 );
				$jpeg_binary = (string) ob_get_clean();
				imagedestroy( $img );
			}
		}

		if ( '' === $jpeg_binary ) {
			return null;
		}

		$body = "<< /Type /XObject /Subtype /Image /Width {$w} /Height {$h} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen( $jpeg_binary ) . " >>\nstream\n" . $jpeg_binary . "\nendstream";
		return array(
			'object_body' => $body,
			'width'       => $w,
			'height'      => $h,
		);
	}

	/**
	 * Resolve logo URL into an absolute local filesystem path.
	 *
	 * @param string $logo_url
	 * @return string
	 */
	private function resolve_local_logo_path( $logo_url ) {
		$logo_url = trim( (string) $logo_url );
		if ( '' === $logo_url ) {
			return '';
		}

		if ( 0 === strpos( $logo_url, '/' ) ) {
			$logo_url = home_url( $logo_url );
		}

		$uploads = wp_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? (string) $uploads['baseurl'] : '';
		$basedir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';

		if ( '' !== $baseurl && '' !== $basedir && 0 === strpos( $logo_url, $baseurl ) ) {
			$rel = ltrim( substr( $logo_url, strlen( $baseurl ) ), '/' );
			return trailingslashit( $basedir ) . $rel;
		}

		$site_url = (string) home_url( '/' );
		if ( '' !== $site_url && 0 === strpos( $logo_url, $site_url ) ) {
			$rel_path = ltrim( substr( $logo_url, strlen( $site_url ) ), '/' );
			if ( '' !== $rel_path && defined( 'ABSPATH' ) ) {
				return rtrim( ABSPATH, '/\\' ) . '/' . $rel_path;
			}
		}

		return '';
	}

	/**
	 * Normalize text from HTML node to plain readable content.
	 *
	 * @param string $text
	 * @return string
	 */
	private function normalize_text($text)
	{
		$text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		// Preserve readability for flattened inline nodes (e.g., "score33%low").
		$text = preg_replace('/([A-Za-z])(\d)/', '$1 $2', $text);
		$text = preg_replace('/(\d)([A-Za-z])/', '$1 $2', $text);
		$text = preg_replace('/(%)([A-Za-z])/', '$1 $2', $text);
		$text = preg_replace('/\s+/', ' ', $text);
		$text = trim((string) $text);
		if ('' === $text) {
			return '';
		}
		if (function_exists('iconv')) {
			$conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
			if (false !== $conv) {
				$text = $conv;
			}
		}
		return preg_replace('/[^\x20-\x7E]/', '', $text);
	}

	/**
	 * Wrap a long text line into smaller lines.
	 *
	 * @param string $text
	 * @param int    $width
	 * @return string[]
	 */
	private function wrap_text_line($text, $width)
	{
		$text = trim((string) $text);
		if ('' === $text) {
			return array();
		}
		$wrapped = wordwrap($text, max(10, (int) $width), "\n", true);
		$parts = explode("\n", $wrapped);
		$out = array();
		foreach ($parts as $part) {
			$part = trim((string) $part);
			if ('' !== $part) {
				$out[] = $part;
			}
		}
		return $out;
	}

	/**
	 * Escape text for PDF content stream.
	 *
	 * @param string $text
	 * @return string
	 */
	private function pdf_escape_text($text)
	{
		return str_replace(array('\\', '(', ')'), array('\\\\', '\(', '\)'), (string) $text);
	}
}

