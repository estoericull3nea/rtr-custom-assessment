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
		$lines = $this->extract_basic_lines_from_html((string) $html);
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
	 * Normalize text from HTML node to plain readable content.
	 *
	 * @param string $text
	 * @return string
	 */
	private function normalize_text($text)
	{
		$text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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

