<?php

namespace App\Services\LegalCitations;

interface CitationDetectorInterface
{
    public function detect(string $text): array;
}