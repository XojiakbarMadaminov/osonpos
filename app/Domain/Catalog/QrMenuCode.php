<?php

namespace App\Domain\Catalog;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

class QrMenuCode
{
    public function svg(string $url): string
    {
        return (new SvgWriter)->write(new QrCode(data: $url, size: 480, margin: 16))->getString();
    }
}
