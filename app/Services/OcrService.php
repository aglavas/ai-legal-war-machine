<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class OcrService
{
    public function extractTextFromPdf(string $pdfPath): ?string
    {
        if (!is_file($pdfPath)) return null;

        // Try pdftotext first (layout preserved)
        $pdftotext = trim((string) shell_exec('which pdftotext'));
        if ($pdftotext !== '') {
            $proc = new Process([$pdftotext, '-enc', 'UTF-8', '-layout', $pdfPath, '-']);
            $proc->setTimeout(60);
            $proc->run();
            if ($proc->isSuccessful()) {
                $out = $proc->getOutput();
                $out = trim($out);
                if ($out !== '') return $out;
            }
        }

        // Optional fallback: tesseract OCR (requires convert to images)
        $tesseract = trim((string) shell_exec('which tesseract'));
        $convert = trim((string) shell_exec('which convert'));
        if ($tesseract !== '' && $convert !== '') {
            $tmpBase = tempnam(sys_get_temp_dir(), 'ocr_');
            @unlink($tmpBase);
            $imgBase = $tmpBase.'_page';
            // Convert each PDF page to TIFF and OCR
            $convertCmd = [$convert, '-density', '300', $pdfPath, $imgBase.'.tif'];
            $p1 = new Process($convertCmd);
            $p1->setTimeout(120);
            $p1->run();
            if ($p1->isSuccessful()) {
                $pages = glob($imgBase.'-*.tif') ?: glob($imgBase.'.tif');
                $texts = [];
                foreach ((array)$pages as $i => $img) {
                    $outTxt = $tmpBase.'_'.($i+1);
                    $p2 = new Process([$tesseract, $img, $outTxt, '-l', 'hr+eng']);
                    $p2->setTimeout(120);
                    $p2->run();
                    if (is_file($outTxt.'.txt')) {
                        $texts[] = file_get_contents($outTxt.'.txt');
                        @unlink($outTxt.'.txt');
                    }
                    @unlink($img);
                }
                if (!empty($texts)) {
                    return trim(preg_replace('/\s+/u', ' ', implode("\n\n", $texts)) ?? '');
                }
            }
        }

        return null;
    }
}

