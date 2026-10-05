<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Contracts;

interface CertificatePdfDriver
{
    /**
     * Render certificate HTML into PDF binary contents.
     */
    public function pdf(string $html): string;
}
