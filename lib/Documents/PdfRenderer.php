<?php

declare(strict_types=1);

namespace Ceara\Documents;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

final class PdfRenderer
{
    public function renderA4Portrait(string $html): string
    {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException('Dompdf nu este instalat. Ruleaza composer install.');
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
