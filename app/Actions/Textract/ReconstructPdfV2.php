<?php

namespace App\Actions\Textract;

use App\Services\Ocr\OcrDocument;
use App\Services\Ocr\TextractPdfReconstructor;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Action: ReconstructPdfV2
 * Purpose: Build a searchable PDF using the new TextractPdfReconstructor (replaces old PdfReconstructor).
 */
class ReconstructPdfV2
{
    use AsAction;

    /**
     * @param OcrDocument $doc
     * @param string $driveFileId
     * @return string Absolute path to the reconstructed PDF
     */
    public function handle(OcrDocument $doc, string $driveFileId): string
    {
        $rel = 'textract/output/' . $driveFileId . '-searchable.pdf';
        // Ensure directory exists under the 'local' disk root
        Storage::disk('local')->makeDirectory('textract/output');
        $targetLocal = Storage::disk('local')->path($rel);

        $reconstructor = new TextractPdfReconstructor([
            'page_format'  => 'A4',
            'orientation'  => 'P',
            'font_family'  => 'dejavusans',      // supports čćšžđ
            'min_font_pt'  => 7,
            'max_font_pt'  => 22,
            'draw_signatures' => true,           // or false to hide signature frames
            'dim_low_confidence' => false,
        ]);

        $reconstructor->render($doc, $targetLocal);
        return $targetLocal;
    }
}
