<?php

namespace App\Services\Ocr;

final class OcrLine
{
    public function __construct(
        public string $text,
        public float $left,
        public float $top,
        public float $width,
        public float $height,
        public float $confidence = 0.0,
        public string $style = '',
        public bool $isHeader = false,
        public ?string $id = null,
    ) {}
}
