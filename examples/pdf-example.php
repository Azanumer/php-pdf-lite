<?php

/**
 * Demo: build a one-page invoice PDF.
 * Run: php examples/pdf-example.php
 * Writes: examples/invoice.pdf
 */

require __DIR__ . '/../src/PdfLite.php';

$pdf = new PdfLite('A4', 'P');
$pdf->setTitle('Invoice #1042');
$pdf->setAuthor('Azan Umer');

$pdf->addPage();

// Heading.
$pdf->setFont('helvetica', 'B', 20);
$pdf->text(40, 60, 'Invoice #1042');

$pdf->setFont('helvetica', '', 11);
$pdf->setTextColor(90, 90, 90);
$pdf->text(40, 78, 'Billed to: Acme Ltd — 24 November 2026');
$pdf->setTextColor(0, 0, 0);

// Rule under the heading.
$pdf->setDrawColor(180, 180, 180);
$pdf->setLineWidth(0.5);
$pdf->line(40, 90, 555, 90);

// Wrapped paragraph.
$pdf->setFont('helvetica', '', 11);
$pdf->writeText(
    'Thank you for your business. Payment is due within 14 days of the invoice date. '
    . 'Please quote the invoice number with your payment. Late payments may be subject '
    . 'to a 2% monthly service charge.',
    40, 110, 515
);

// Simple table using rectangles + text.
$y = 220;
$pdf->setFillColor(240, 240, 240);
$pdf->rect(40, $y, 515, 22, true, false);
$pdf->setFont('helvetica', 'B', 11);
$pdf->text(48, $y + 15, 'Description');
$pdf->text(420, $y + 15, 'Amount');

$pdf->setFont('helvetica', '', 11);
$pdf->text(48, $y + 40, 'WordPress speed optimisation');
$pdf->text(420, $y + 40, '$180.00');
$pdf->text(48, $y + 62, 'Monthly maintenance');
$pdf->text(420, $y + 62, '$49.00');

$pdf->setDrawColor(180, 180, 180);
$pdf->line(40, $y + 78, 555, $y + 78);
$pdf->setFont('helvetica', 'B', 12);
$pdf->text(420, $y + 100, 'Total: $229.00');

$bytes = $pdf->save(__DIR__ . '/invoice.pdf');
echo "Wrote {$bytes} bytes to examples/invoice.pdf" . PHP_EOL;
