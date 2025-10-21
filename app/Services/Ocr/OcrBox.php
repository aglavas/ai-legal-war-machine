<?php

namespace App\Services\Ocr;

final class OcrBox
{
    public function __construct(
        public float $left,
        public float $top,
        public float $width,
        public float $height,
    ) {}
}

