<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

class PdfRenderer
{
    public function renderArticle(array $ctx, string $destPath): void
    {
        // $ctx sadrži: law_title, law_eli, law_pub_date, article_number, article_html, source_citation, etc.
        $html = View::make('pdf.article', $ctx)->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait');

        @mkdir(dirname($destPath), 0775, true);
        $pdf->save($destPath);

        // Free memory after rendering each article
        unset($pdf, $html);
    }
}
