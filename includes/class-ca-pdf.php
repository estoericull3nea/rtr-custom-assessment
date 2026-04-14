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
			return $this->get_binary_with_tcpdf($html);
		}
		if (class_exists('Dompdf\Dompdf')) {
			return $this->get_binary_with_dompdf($html);
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
		$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
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
		$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
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
		$text = trim(wp_strip_all_tags((string) $html));
		if ('' === $text) {
			$text = 'Assessment Results';
		}

		$lines = preg_split('/\r\n|\r|\n/', $text);
		if (!is_array($lines) || empty($lines)) {
			$lines = array($text);
		}

		$content = "BT\n/F1 11 Tf\n50 792 Td\n";
		$line_count = 0;
		foreach ($lines as $line) {
			$line = trim((string) $line);
			if ('' === $line) {
				continue;
			}

			$line = preg_replace('/\s+/', ' ', $line);
			$chunks = str_split($line, 95);
			foreach ($chunks as $chunk) {
				$escaped = str_replace(array('\\', '(', ')'), array('\\\\', '\(', '\)'), $chunk);
				$content .= '(' . $escaped . ") Tj\n0 -14 Td\n";
				$line_count++;
				// Basic single-page safety cap.
				if ($line_count >= 300) {
					break 2;
				}
			}
		}
		$content .= "ET\n";

		$objects = array();
		$objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
		$objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
		$objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
		$objects[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
		$objects[] = "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n";

		$pdf = "%PDF-1.4\n";
		$offsets = array(0);
		foreach ($objects as $object) {
			$offsets[] = strlen($pdf);
			$pdf .= $object;
		}

		$xref_offset = strlen($pdf);
		$pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
		$pdf .= "0000000000 65535 f \n";
		for ($i = 1; $i <= count($objects); $i++) {
			$pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
		}
		$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
		$pdf .= "startxref\n" . $xref_offset . "\n%%EOF";

		return $pdf;
	}
}

