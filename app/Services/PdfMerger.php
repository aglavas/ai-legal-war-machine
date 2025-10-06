<?php

namespace App\Services;

use setasign\Fpdi\Tcpdf\Fpdi;

class PdfMerger
{
    /**
     * Merge multiple PDFs into a single PDF at destPath.
     * @param array<int,string> $pdfPaths
     */
    public function merge(array $pdfPaths, string $destPath): string
    {
        @mkdir(dirname($destPath), 0775, true);

        $pdf = new Fpdi();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetCreator('Laravel PDF Merger');
        $pdf->SetAuthor('Laravel App');

        foreach ($pdfPaths as $path) {
            if (!is_file($path)) continue;
            try {
                $pageCount = $pdf->setSourceFile($path);
            } catch (\Throwable $e) {
                continue;
            }
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tplId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($tplId);
                $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($tplId, 0, 0, $size['width'], $size['height'], true);
            }
        }

        $pdf->Output($destPath, 'F');
        return $destPath;
    }
}
