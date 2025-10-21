<?php

namespace App\Services\Ocr;

final class OcrPage
{
    /** @param OcrBox[] $signatures */
    public function __construct(
        public int $number,
        public array $lines = [],
        public array $signatures = [],
    ) {}
}
