<?php
/**
 * PDF generation class for exporting submissions.
 */

if (!defined('ABSPATH')) {
	exit;
}

class RTR_Custom_Assessment_PDF
{

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

		// Try using TCPDF or DomPDF, fallback to HTML print
		if (class_exists('TCPDF')) {
			$this->generate_with_tcpdf($html);
		} elseif (class_exists('Dompdf\Dompdf')) {
			$this->generate_with_dompdf($html);
		} else {
			// Fallback: Output as HTML
			header('Content-Type: text/html; charset=utf-8');
			header('Content-Disposition: inline');
			$this->generate_simple($html);
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
	 * Fallback simple PDF generation using DomPDF if available.
	 *
	 * @param string $html
	 */
	private function generate_simple($html)
	{
		// Try to use dompdf if composer installed it
		if (class_exists('Dompdf\Dompdf')) {
			$this->generate_with_dompdf($html);
			return;
		}

		// Fallback: Output as HTML content with print styling
		// This allows users to print as PDF from their browser
		echo '<!DOCTYPE html>
		<html lang="en">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title>Submission Report</title>
			<style>
				@media print {
					body { margin: 0; }
					.no-print { display: none; }
				}
				body { font-family: Arial, sans-serif; margin: 20px; }
				.print-button { margin-bottom: 20px; }
			</style>
		</head>
		<body>
			<button class="print-button no-print" onclick="window.print()">Print / Save as PDF</button>
			' . wp_kses_post($html) . '
		</body>
		</html>';
	}
}

