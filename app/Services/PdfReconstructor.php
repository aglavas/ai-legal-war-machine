<?php

namespace App\Services;

use setasign\Fpdi\Tcpdf\Fpdi;

class PdfReconstructor
{
    /**
     * Build a searchable PDF by overlaying invisible text on top of original page images.
     * @param array<int, array<int, array{text:string,left:float,top:float,width:float,height:float}>> $linesByPage
     * @return string target path
     */
    public function buildSearchablePdf(string $sourcePdfPath, array $linesByPage, string $targetPdfPath): string
    {
        @mkdir(dirname($targetPdfPath), 0775, true);

        $pdf = new Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetCreator('Laravel Textract');
        $pdf->SetAuthor('Laravel App');
        $pdf->SetTitle('OCR Searchable PDF');

        $pageCount = $pdf->setSourceFile($sourcePdfPath);

        // Use built-in DejaVu font for UTF-8 text
        $font = 'dejavusans';

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tplId);

            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]); // mm
            $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height'], true);

            $pageWidthMm  = $size['width'];
            $pageHeightMm = $size['height'];

            if (method_exists($pdf, 'SetAlpha')) {
                $pdf->SetAlpha(0.0); // fully invisible; still selectable/searchable
            }

            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont($font, '', 10);

            $lines = $linesByPage[$pageNo] ?? [];
            foreach ($lines as $line) {
                $leftMm   = $line['left']   * $pageWidthMm;
                $topMm    = $line['top']    * $pageHeightMm;
                $heightMm = $line['height'] * $pageHeightMm;

                $fontSizePt = max(6.0, $heightMm * 2.83465); // 1mm = 2.83465pt, clamp to 6pt
                $pdf->SetFont($font, '', $fontSizePt);

                $baselineY = $topMm + $heightMm; // TCPDF Text baseline

                $pdf->SetXY($leftMm, $baselineY);
                $pdf->Cell($line['width'] * $pageWidthMm, 0, $line['text'], 0, 1, 'L', false, '', 0, false, 'T', 'T');
            }

            if (method_exists($pdf, 'SetAlpha')) {
                $pdf->SetAlpha(1.0);
            }
        }

        $pdf->Output($targetPdfPath, 'F');
        return $targetPdfPath;
    }
}

