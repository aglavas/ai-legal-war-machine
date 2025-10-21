<?php

declare(strict_types=1);

namespace App\Support\Pdf;

use TCPDF;

class OcrTcpdf extends TCPDF
{
    public function Header(): void {}
    public function Footer(): void {}
}
