<?php

declare(strict_types=1);

/**
 * PdfLite — a dependency-free minimal PDF generator.
 *
 * Builds simple multi-page PDFs with the 14 core PDF fonts (no embedding,
 * no images): text, wrapped paragraphs, lines and rectangles. Output is a
 * valid PDF 1.4 document as a string or a file.
 *
 * Coordinates are in points (1/72 inch), origin at the top-left of the
 * page — like a screen, not like raw PDF. Page sizes: A4 (595.28 x 841.89)
 * and Letter (612 x 792), portrait or landscape.
 *
 * Text is ASCII/WinAnsi only: characters outside the printable ASCII range
 * are replaced with '?' on output (the core fonts have no UTF-8 support).
 *
 * Usage:
 *
 *   $pdf = new PdfLite();
 *   $pdf->setTitle('Invoice #1042');
 *   $pdf->addPage();
 *   $pdf->setFont('helvetica', 'B', 18);
 *   $pdf->text(40, 50, 'Invoice #1042');
 *   $pdf->setFont('helvetica', '', 11);
 *   $pdf->writeText('Thank you for your business. ...', 40, 80, 515);
 *   $pdf->line(40, 60, 555, 60);
 *   $pdf->save('/tmp/invoice.pdf');
 */
class PdfLite
{
    /** @var float Page width in points. */
    private $pageWidth;

    /** @var float Page height in points. */
    private $pageHeight;

    /** @var array Pages: each is ['content' => string]. */
    private $pages = array();

    /** @var int Index of the current page (-1 when none). */
    private $currentPage = -1;

    /** @var string Current font family key, e.g. 'helvetica'. */
    private $fontFamily = 'helvetica';

    /** @var string Current font style: '', 'B', 'I' or 'BI'. */
    private $fontStyle = '';

    /** @var float Current font size in points. */
    private $fontSize = 12.0;

    /** @var float Left/right/top/bottom margins in points. */
    private $margin = 40.0;

    /** @var float Current cursor Y (top-down), used by writeText(). */
    private $cursorY;

    /** @var float Line height multiplier. */
    private $lineHeightFactor = 1.4;

    /** @var array [r, g, b] 0..1 text colour. */
    private $textColor = array(0.0, 0.0, 0.0);

    /** @var array [r, g, b] 0..1 fill colour. */
    private $fillColor = array(0.0, 0.0, 0.0);

    /** @var array [r, g, b] 0..1 stroke colour. */
    private $drawColor = array(0.0, 0.0, 0.0);

    /** @var float Stroke width in points. */
    private $lineWidth = 1.0;

    /** @var string|null Document title (metadata). */
    private $title;

    /** @var string|null Document author (metadata). */
    private $author;

    /** @var string[] Font keys actually used, e.g. 'helvetica-B'. */
    private $usedFonts = array();

    /** Page sizes in points. */
    private static $pageSizes = array(
        'A4'     => array(595.28, 841.89),
        'LETTER' => array(612.0, 792.0),
    );

    /** Core font names by family + style. */
    private static $coreFonts = array(
        'helvetica' => array('' => 'Helvetica', 'B' => 'Helvetica-Bold', 'I' => 'Helvetica-Oblique', 'BI' => 'Helvetica-BoldOblique'),
        'times'     => array('' => 'Times-Roman', 'B' => 'Times-Bold', 'I' => 'Times-Italic', 'BI' => 'Times-BoldItalic'),
        'courier'   => array('' => 'Courier', 'B' => 'Courier-Bold', 'I' => 'Courier-Oblique', 'BI' => 'Courier-BoldOblique'),
    );

    /**
     * Character widths (units per 1000 em) for printable ASCII 32-126.
     * Helvetica table: standard AFM data. Times table: standard AFM data.
     * Courier is monospace (600). Characters outside 32-126 fall back to 556.
     */
    private static $charWidths = array(
        'helvetica' => array(
            278, 278, 355, 556, 556, 889, 667, 222, 333, 333, 389, 584, 278, 333, 278, 278,
            556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
            1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
            667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
            333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
            556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
        ),
        'times' => array(
            250, 333, 408, 500, 500, 833, 778, 333, 333, 333, 500, 564, 250, 333, 250, 278,
            500, 500, 500, 500, 500, 500, 500, 500, 500, 500, 278, 278, 564, 564, 564, 444,
            921, 722, 667, 667, 722, 611, 556, 722, 722, 333, 389, 722, 611, 889, 722, 722,
            556, 722, 667, 556, 611, 722, 722, 944, 722, 722, 611, 333, 278, 333, 469, 500,
            333, 444, 500, 444, 500, 444, 333, 500, 500, 278, 278, 500, 278, 778, 500, 500,
            500, 500, 333, 389, 278, 500, 500, 722, 500, 500, 444, 480, 200, 480, 541,
        ),
    );

    /**
     * @param string     $pageSize    'A4', 'Letter', or [width, height] in points.
     * @param string     $orientation 'P' (portrait) or 'L' (landscape).
     * @param float|null $margin      Margin in points (default 40).
     */
    public function __construct($pageSize = 'A4', $orientation = 'P', $margin = 40.0)
    {
        if (is_array($pageSize)) {
            list($w, $h) = array((float) $pageSize[0], (float) $pageSize[1]);
        } else {
            $key = strtoupper((string) $pageSize);
            if (!isset(self::$pageSizes[$key])) {
                throw new InvalidArgumentException("Unknown page size: {$pageSize}");
            }
            list($w, $h) = self::$pageSizes[$key];
        }
        if (strtoupper($orientation) === 'L') {
            list($w, $h) = array($h, $w);
        }
        $this->pageWidth  = $w;
        $this->pageHeight = $h;
        $this->margin     = (float) $margin;
        $this->cursorY    = $this->margin;
    }

    public function pageWidth() { return $this->pageWidth; }
    public function pageHeight() { return $this->pageHeight; }

    public function setTitle($title) { $this->title = (string) $title; }
    public function setAuthor($author) { $this->author = (string) $author; }

    public function setMargins($margin) { $this->margin = (float) $margin; }

    /** Start a new page and reset the cursor to the top margin. */
    public function addPage()
    {
        $this->pages[]     = array('content' => '');
        $this->currentPage = count($this->pages) - 1;
        $this->cursorY     = $this->margin;
        return $this;
    }

    /**
     * Select a core font.
     *
     * @param string $family 'helvetica', 'times' or 'courier'.
     * @param string $style  '', 'B', 'I' or 'BI'.
     * @param float  $size   Font size in points.
     */
    public function setFont($family, $style = '', $size = 12.0)
    {
        $family = strtolower($family);
        if (!isset(self::$coreFonts[$family])) {
            throw new InvalidArgumentException("Unknown font family: {$family}");
        }
        $style = strtoupper($style);
        if (!isset(self::$coreFonts[$family][$style])) {
            throw new InvalidArgumentException("Unknown font style: {$style}");
        }
        $this->fontFamily = $family;
        $this->fontStyle  = $style;
        $this->fontSize   = (float) $size;
        $this->usedFonts[$this->fontKey()] = true;
        return $this;
    }

    /** Set the text colour from 0-255 RGB values. */
    public function setTextColor($r, $g, $b)
    {
        $this->textColor = array($r / 255, $g / 255, $b / 255);
        return $this;
    }

    /** Set the fill colour (rectangles) from 0-255 RGB values. */
    public function setFillColor($r, $g, $b)
    {
        $this->fillColor = array($r / 255, $g / 255, $b / 255);
        return $this;
    }

    /** Set the stroke colour (lines, rectangle borders) from 0-255 RGB values. */
    public function setDrawColor($r, $g, $b)
    {
        $this->drawColor = array($r / 255, $g / 255, $b / 255);
        return $this;
    }

    public function setLineWidth($width)
    {
        $this->lineWidth = (float) $width;
        return $this;
    }

    /** Current cursor Y (top-down). Useful for chaining writeText() calls. */
    public function getY() { return $this->cursorY; }
    public function setY($y) { $this->cursorY = (float) $y; return $this; }

    /** Width of a string in points at the current font/size. */
    public function textWidth($text)
    {
        $text  = $this->sanitizeText($text);
        $width = 0;
        $len   = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $width += $this->charWidth(ord($text[$i]));
        }
        return $width * $this->fontSize / 1000.0;
    }

    /**
     * Draw a single line of text at an absolute position.
     * $y is the baseline, measured from the top of the page.
     */
    public function text($x, $y, $text)
    {
        $this->requirePage();
        $text = $this->sanitizeText($text);
        if ($text === '') {
            return $this;
        }
        $pdfY = $this->pageHeight - (float) $y;
        $this->append($this->colorOps('text'));
        $this->append(sprintf(
            "BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET\n",
            $this->fontResourceName(),
            $this->fontSize,
            (float) $x,
            $pdfY,
            $this->escapeString($text)
        ));
        return $this;
    }

    /**
     * Write a paragraph, word-wrapped to $maxWidth, starting at ($x, $y).
     * The cursor moves down past the paragraph; returns the height used.
     * A new page is added automatically when the text would run off the
     * bottom margin.
     */
    public function writeText($text, $x = null, $y = null, $maxWidth = null)
    {
        $this->requirePage();
        $x        = $x === null ? $this->margin : (float) $x;
        $y        = $y === null ? $this->cursorY : (float) $y;
        $maxWidth = $maxWidth === null ? $this->pageWidth - $x - $this->margin : (float) $maxWidth;

        $lines       = $this->wrapText($text, $maxWidth);
        $lineHeight  = $this->fontSize * $this->lineHeightFactor;
        $height      = 0.0;
        foreach ($lines as $line) {
            if ($y + $lineHeight > $this->pageHeight - $this->margin) {
                $this->addPage();
                $y = $this->margin;
            }
            $this->text($x, $y + $this->fontSize, $line);
            $y      += $lineHeight;
            $height += $lineHeight;
        }
        $this->cursorY = $y;
        return $height;
    }

    /** Draw a line between two points. */
    public function line($x1, $y1, $x2, $y2)
    {
        $this->requirePage();
        $this->append($this->colorOps('draw'));
        $this->append(sprintf(
            "%.2F w %.2F %.2F m %.2F %.2F l S\n",
            $this->lineWidth,
            (float) $x1, $this->pageHeight - (float) $y1,
            (float) $x2, $this->pageHeight - (float) $y2
        ));
        return $this;
    }

    /**
     * Draw a rectangle. $fill draws a filled rectangle; $stroke (default)
     * draws the border. Pass both for a filled rectangle with a border.
     */
    public function rect($x, $y, $w, $h, $fill = false, $stroke = true)
    {
        $this->requirePage();
        $op = 're';
        if ($fill && $stroke) {
            $op = 'B';
        } elseif ($fill) {
            $op = 'f';
        } else {
            $op = 'S';
        }
        $this->append($this->colorOps('draw'));
        $this->append($this->colorOps('fill'));
        $this->append(sprintf(
            "%.2F w %.2F %.2F %.2F %.2F %s\n",
            $this->lineWidth,
            (float) $x, $this->pageHeight - (float) $y - (float) $h,
            (float) $w, (float) $h,
            $op
        ));
        return $this;
    }

    /** Render the whole document as a PDF string. */
    public function output()
    {
        if (empty($this->pages)) {
            $this->addPage();
        }

        // Object numbering:
        //   1 = Catalog, 2 = Pages, 3 = Info (metadata)
        //   then per page: page object + content-stream object
        //   then one object per used font.
        $objects = array();

        $fontNames = array();
        $i = 0;
        foreach (array_keys($this->usedFonts) as $key) {
            $fontNames[$key] = 'F' . (++$i);
        }
        // Fonts are always emitted, even if no text was drawn, so that the
        // resource dictionary stays valid; default to plain Helvetica.
        if (empty($fontNames)) {
            $fontNames['helvetica-'] = 'F1';
        }

        $pageCount  = count($this->pages);
        $firstPage  = 4;
        $fontStart  = $firstPage + $pageCount * 2;
        $kids       = array();
        for ($p = 0; $p < $pageCount; $p++) {
            $kids[] = ($firstPage + $p * 2) . ' 0 R';
        }

        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count {$pageCount} >>";
        $objects[3] = $this->infoObject();

        $fontObjNum = array();
        $n = $fontStart;
        foreach ($fontNames as $key => $resName) {
            $fontObjNum[$key] = $n++;
        }

        for ($p = 0; $p < $pageCount; $p++) {
            $pageObj    = $firstPage + $p * 2;
            $contentObj = $pageObj + 1;

            $resources = '';
            foreach ($fontNames as $key => $resName) {
                list($family, $style) = explode('-', $key, 2);
                $resources .= "/{$resName} {$fontObjNum[$key]} 0 R ";
            }
            $objects[$pageObj] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << %s>> >> /Contents %d 0 R >>",
                $this->pageWidth, $this->pageHeight, trim($resources), $contentObj
            );

            $stream = $this->pages[$p]['content'];
            $objects[$contentObj] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }

        foreach ($fontNames as $key => $resName) {
            list($family, $style) = explode('-', $key, 2);
            $objects[$fontObjNum[$key]] = "<< /Type /Font /Subtype /Type1 /BaseFont /" . self::$coreFonts[$family][$style] . " /Encoding /WinAnsiEncoding >>";
        }

        ksort($objects);
        $pdf     = "%PDF-1.4\n";
        $offsets = array();
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $maxObj  = max(array_keys($objects));
        $pdf    .= "xref\n0 " . ($maxObj + 1) . "\n";
        $pdf    .= "0000000000 65535 f \n";
        for ($num = 1; $num <= $maxObj; $num++) {
            $pdf .= sprintf("%010d 00000 n \n", isset($offsets[$num]) ? $offsets[$num] : 0);
        }
        $pdf .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R /Info 3 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }

    /** Write the PDF to a file. Returns the number of bytes written. */
    public function save($path)
    {
        $bytes = file_put_contents($path, $this->output(), LOCK_EX);
        if ($bytes === false) {
            throw new RuntimeException("Could not write PDF to: {$path}");
        }
        return $bytes;
    }

    // -- internals ---------------------------------------------------------

    private function fontKey()
    {
        return $this->fontFamily . '-' . $this->fontStyle;
    }

    private function fontResourceName()
    {
        $key = $this->fontKey();
        $i   = 0;
        $names = array();
        foreach (array_keys($this->usedFonts) as $k) {
            $names[$k] = 'F' . (++$i);
        }
        if (!isset($names[$key])) {
            // setFont() always registers the font, so this is unreachable in
            // normal use; fall back to the first font if it ever happens.
            return 'F1';
        }
        return $names[$key];
    }

    private function requirePage()
    {
        if ($this->currentPage < 0) {
            $this->addPage();
        }
    }

    private function append($str)
    {
        $this->pages[$this->currentPage]['content'] .= $str;
    }

    /**
     * Colour operators for the current colours.
     * 'text' sets the non-stroking colour used by Tj; 'draw' sets the
     * stroking colour; 'fill' sets the non-stroking colour.
     */
    private function colorOps($kind)
    {
        if ($kind === 'draw') {
            list($r, $g, $b) = $this->drawColor;
            return sprintf("%.3F %.3F %.3F RG\n", $r, $g, $b);
        }
        list($r, $g, $b) = $kind === 'fill' ? $this->fillColor : $this->textColor;
        return sprintf("%.3F %.3F %.3F rg\n", $r, $g, $b);
    }

    /** Width of one character code in units per 1000 em. */
    private function charWidth($code)
    {
        if ($this->fontFamily === 'courier') {
            return 600;
        }
        $table = self::$charWidths[$this->fontFamily];
        if ($code >= 32 && $code <= 126) {
            return $table[$code - 32];
        }
        return 556;
    }

    /** Replace non-ASCII characters with '?' (core fonts are WinAnsi). */
    private function sanitizeText($text)
    {
        return preg_replace('/[^\x20-\x7E]/', '?', (string) $text);
    }

    /** Escape a string for a PDF literal: backslash, ( and ). */
    private function escapeString($text)
    {
        return str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $text);
    }

    /** Greedy word wrap on spaces; over-long words are hard-split. */
    private function wrapText($text, $maxWidth)
    {
        $text  = $this->sanitizeText($text);
        $lines = array();
        foreach (preg_split('/\r\n|\r|\n/', $text) as $paragraph) {
            $words = preg_split('/\s+/', trim($paragraph));
            $line  = '';
            foreach ($words as $word) {
                if ($word === '') {
                    continue;
                }
                // Hard-split a single word that is wider than the column.
                while ($this->textWidth($word) > $maxWidth) {
                    $cut = $this->splitToWidth($word, $maxWidth);
                    if ($line !== '') {
                        $lines[] = $line;
                        $line    = '';
                    }
                    $lines[] = $cut[0];
                    $word    = $cut[1];
                }
                $candidate = $line === '' ? $word : $line . ' ' . $word;
                if ($this->textWidth($candidate) <= $maxWidth) {
                    $line = $candidate;
                } else {
                    $lines[] = $line;
                    $line    = $word;
                }
            }
            $lines[] = $line;
        }
        return $lines;
    }

    /**
     * Split $word into [head, rest] where head is the longest prefix that
     * fits in $maxWidth. Head is never empty (a single char always fits
     * when the column is wider than one character).
     */
    private function splitToWidth($word, $maxWidth)
    {
        $head = '';
        $len  = strlen($word);
        for ($i = 0; $i < $len; $i++) {
            $candidate = $head . $word[$i];
            if ($this->textWidth($candidate) > $maxWidth) {
                break;
            }
            $head = $candidate;
        }
        if ($head === '') {
            $head = $word[0];
            $rest = substr($word, 1);
        } else {
            $rest = substr($word, strlen($head));
        }
        return array($head, $rest);
    }

    private function infoObject()
    {
        $parts = array();
        if ($this->title !== null) {
            $parts[] = '/Title (' . $this->escapeString($this->sanitizeText($this->title)) . ')';
        }
        if ($this->author !== null) {
            $parts[] = '/Author (' . $this->escapeString($this->sanitizeText($this->author)) . ')';
        }
        $parts[] = '/Producer (PdfLite 1.0)';
        return '<< ' . implode(' ', $parts) . ' >>';
    }
}
