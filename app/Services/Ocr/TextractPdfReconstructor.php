<?php

declare(strict_types=1);

namespace App\Services\Ocr;

use App\Support\Pdf\OcrTcpdf;
use InvalidArgumentException;

class TextractPdfReconstructor
{
    private string $pageFormat;
    private string $orientation;
    private float  $pageWidthMm;
    private float  $pageHeightMm;

    private string $fontFamily;
    private int    $minFontPt;
    private int    $maxFontPt;

    // New tuning knobs
    private float  $rightClipMm;       // small right “gutter” to avoid touching the edge
    private bool   $shrinkToFit;       // reduce font size to fit available width

    private bool   $drawSignatures;
    private bool   $dimLowConfidence;
    private float  $lowConfidenceThreshold;

    public function __construct(array $options = [])
    {
        $this->pageFormat   = $options['page_format']  ?? 'A4';
        $this->orientation  = $options['orientation']  ?? 'P';
        $this->fontFamily   = $options['font_family']  ?? 'dejavusans';
        $this->minFontPt    = (int)($options['min_font_pt'] ?? 7);
        $this->maxFontPt    = (int)($options['max_font_pt'] ?? 22);

        $this->rightClipMm  = (float)($options['right_clip_mm'] ?? 1.5);
        $this->shrinkToFit  = (bool)($options['shrink_to_fit'] ?? true);

        $this->drawSignatures = (bool)($options['draw_signatures'] ?? true);
        $this->dimLowConfidence = (bool)($options['dim_low_confidence'] ?? false);
        $this->lowConfidenceThreshold = (float)($options['low_confidence_threshold'] ?? 0);

        // Probe page size with our subclass (no header/footer)
        $tmp = new OcrTcpdf($this->orientation, 'mm', $this->pageFormat, true, 'UTF-8', false);
        $this->pageWidthMm  = $tmp->getPageWidth();
        $this->pageHeightMm = $tmp->getPageHeight();
        unset($tmp);
    }

    public function render(OcrDocument $doc, string $outputPath): string
    {
        if (empty($doc->pages)) {
            throw new InvalidArgumentException('Document has no pages.');
        }

        $pdf = new OcrTcpdf($this->orientation, 'mm', $this->pageFormat, true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetCreator('OCR PDF Reconstructor');
        $pdf->SetAuthor(config('app.name', 'Laravel'));
        $pdf->SetTitle('Reconstructed PDF');
        $pdf->SetSubject('Textract OCR layout');
        $pdf->SetKeywords('OCR, Textract, PDF');

        foreach ($doc->pages as $page) {
            $pdf->AddPage($this->orientation, $this->pageFormat);

            foreach ($page->lines as $line) {
                // Normalize to mm
                [$x, $y, $w, $h] = $this->toMm($line->left, $line->top, $line->width, $line->height);

                // Compute available width up to the right clip
                $avail = max(1.0, $this->pageWidthMm - $this->rightClipMm - $x);

                // Respect OCR bbox if it’s narrower than remaining page width
                if ($w > 0) {
                    $avail = min($avail, $w);
                }

                // Estimate font size from bbox height
                $fontPt = $this->estimateFontPt($h);
                $style  = $line->style;

                // Optional dimming
                if ($this->dimLowConfidence && $line->confidence > 0 && $line->confidence < $this->lowConfidenceThreshold) {
                    $pdf->SetTextColor(80, 80, 80);
                } else {
                    $pdf->SetTextColor(0, 0, 0);
                }

                // Fit font into available width if needed
                if ($this->shrinkToFit && $avail > 1) {
                    $fontPt = $this->fitFontSizeToWidth($pdf, $line->text, $this->fontFamily, $style, $fontPt, $avail);
                }

                // Draw text (single line). Use Cell with width=avail so we never exceed the right edge.
                $pdf->SetFont($this->fontFamily, $style, $fontPt);
                $pdf->SetXY($x, $y);
                $pdf->Cell($avail, 0, $line->text, 0, 0, 'L', false, '', 0, false, 'T', 'T');

                // If text still doesn’t fit (rare with non-Latin fonts), fallback to MultiCell
                if ($pdf->GetStringWidth($line->text, $this->fontFamily, $style, $fontPt) > $avail) {
                    $pdf->SetXY($x, $y);
                    $lineHeight = max(3.0, $h ?: 4.0);
                    $pdf->MultiCell($avail, $lineHeight, $line->text, 0, 'L', false, 1, '', '', true, 0, false, true, $lineHeight, 'T');
                }
            }

            // Draw signature boxes but keep rectangles within page bounds
            if ($this->drawSignatures && !empty($page->signatures)) {
                $pdf->SetDrawColor(10, 80, 160);
                $pdf->SetLineStyle(['width' => 0.3, 'dash' => '3,2']);
                foreach ($page->signatures as $box) {
                    [$x, $y, $w, $h] = $this->toMm($box->left, $box->top, $box->width, $box->height);

                    // Clamp rectangle inside page bounds
                    $x = max(0, min($x, $this->pageWidthMm - $this->rightClipMm));
                    $w = max(0, min($w, $this->pageWidthMm - $this->rightClipMm - $x));
                    $y = max(0, min($y, $this->pageHeightMm));
                    $h = max(0, min($h, $this->pageHeightMm - $y));

                    if ($w > 0.5 && $h > 0.5) {
                        $pdf->Rect($x, $y, $w, $h);
                    }
                }
            }
        }

        $pdf->Output($outputPath, 'F');
        return $outputPath;
    }

    private function toMm(float $l, float $t, float $w, float $h): array
    {
        $x = max(0.0, min(1.0, $l)) * $this->pageWidthMm;
        $y = max(0.0, min(1.0, $t)) * $this->pageHeightMm;
        $W = max(0.0, min(1.0, $w)) * $this->pageWidthMm;
        $H = max(0.0, min(1.0, $h)) * $this->pageHeightMm;
        return [$x, $y, $W, $H];
    }

    private function estimateFontPt(float $heightMm): int
    {
        if ($heightMm <= 0) {
            return 10;
        }
        $pt = ($heightMm / 0.352778) * 0.80; // slight shrink to keep it within bbox
        $pt = (int)round($pt);
        return max($this->minFontPt, min($this->maxFontPt, $pt));
    }

    private function fitFontSizeToWidth(OcrTcpdf $pdf, string $text, string $font, string $style, int $fontPt, float $maxWidthMm): int
    {
        $pt = max($this->minFontPt, min($this->maxFontPt, $fontPt));
        // Coarse loop to reduce font until it fits; stops at minFontPt
        for ($i = 0; $i < 12; $i++) {
            $w = $pdf->GetStringWidth($text, $font, $style, $pt);
            if ($w <= $maxWidthMm || $pt <= $this->minFontPt) {
                break;
            }
            $pt = max($this->minFontPt, $pt - 1);
        }
        return $pt;
    }
}
