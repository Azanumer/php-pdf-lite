# PHP PDF Lite

A dependency-free minimal PDF generator for PHP. Build simple multi-page PDFs with the 14 core PDF fonts — no extensions, no embedded fonts, no images, just a single class.

## Features

- **Valid PDF 1.4 output** — correct object graph and cross-reference table, readable by any PDF viewer
- **Top-left coordinates** — points (1/72 inch), origin at the top-left like a screen, not raw bottom-up PDF
- **Text + wrapping** — `text()` for absolute placement, `writeText()` for word-wrapped paragraphs with automatic page breaks
- **Shapes** — `line()` and `rect()` (filled, stroked, or both) with RGB colours
- **Core fonts** — Helvetica, Times and Courier in regular/bold/italic/bold-italic, with real AFM width tables so wrapping measures correctly
- **Metadata** — document title and author in the Info object
- **Zero dependencies** — pure PHP 7.4+, one file, no extensions

## Usage

```php
require 'src/PdfLite.php';

$pdf = new PdfLite('A4', 'P');          // or 'Letter', or [width, height] in points
$pdf->setTitle('Invoice #1042');
$pdf->addPage();

$pdf->setFont('helvetica', 'B', 20);
$pdf->text(40, 60, 'Invoice #1042');    // x, y (baseline), text

$pdf->setFont('helvetica', '', 11);
$pdf->writeText('Thank you for your business. ...', 40, 110, 515); // x, y, max width

$pdf->setDrawColor(180, 180, 180);
$pdf->line(40, 90, 555, 90);

$pdf->save('/tmp/invoice.pdf');         // or $pdf->output() for the raw string
```

`writeText()` returns the height used and moves the cursor down, so you can chain paragraphs with `getY()` / `setY()`. Over-long words are hard-split at the column edge.

**Limits:** text is ASCII/WinAnsi only — characters outside printable ASCII are replaced with `?` (core fonts have no UTF-8 support). No images, no tables, no hyperlinks — for those, use a full library such as TCPDF or mPDF.

## Requirements

- PHP 7.4+

## License

MIT — see [LICENSE](LICENSE).
