<?php

namespace App\Services\Ocr;

/**
 * DTOs (consider moving each to its own file in production)
 */
final class OcrDocument
{
    /** @var OcrPage[] */
    public array $pages = [];
}
