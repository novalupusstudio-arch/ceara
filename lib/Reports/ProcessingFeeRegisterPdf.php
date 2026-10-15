<?php

declare(strict_types=1);

namespace Ceara\Reports;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

final class ProcessingFeeRegisterPdf
{
    public function render(array $report, array $company): string
    {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException('Dompdf nu este instalat. Ruleaza composer install.');
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($report, $company), 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $dompdf->getCanvas()->page_text(748, 566, 'Pagina {PAGE_NUM} din {PAGE_COUNT}', $font, 8, [0.35, 0.38, 0.36]);

        return $dompdf->output();
    }

    private function html(array $report, array $company): string
    {
        $rows = '';
        foreach ($report['rows'] as $row) {
            $rows .= '<tr>'
                . '<td>' . $this->escape(date('d.m.Y', strtotime((string) $row['receipt_date']))) . '</td>'
                . '<td class="number count">' . (int) $row['receipt_count'] . '</td>'
                . '<td class="number">' . $this->money((int) $row['cash_cents']) . '</td>'
                . '<td class="number">' . $this->money((int) $row['card_cents']) . '</td>'
                . '<td class="number">' . $this->money((int) $row['net_cents']) . '</td>'
                . '<td class="number">' . $this->money((int) $row['vat_cents']) . '</td>'
                . '<td class="number total">' . $this->money((int) $row['total_cents']) . '</td>'
                . '</tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="7" class="empty">Nu exista incasari in intervalul selectat.</td></tr>';
        }

        $totals = $report['totals'];
        $companyName = trim((string) ($company['company_name'] ?? '')) ?: 'Societate';
        $companyMeta = array_filter([
            trim((string) ($company['vat_number'] ?? '')),
            trim((string) ($company['registry_number'] ?? '')),
        ]);
        $period = date('d.m.Y', strtotime((string) $report['date_start']))
            . ' - ' . date('d.m.Y', strtotime((string) $report['date_end']));
        $vatRate = (int) $report['vat_rate'];

        return '<!doctype html><html lang="ro"><head><meta charset="utf-8"><style>
            @page { margin: 34px 34px 44px; }
            body { color: #222824; font-family: "DejaVu Sans", sans-serif; font-size: 9px; margin: 0; }
            .header { border-bottom: 2px solid #245b3c; margin-bottom: 16px; padding-bottom: 12px; width: 100%; }
            .header td { border: 0; padding: 0; vertical-align: top; }
            .company { color: #245b3c; font-size: 10px; font-weight: bold; text-transform: uppercase; }
            h1 { font-size: 20px; margin: 5px 0 3px; }
            .subtitle { color: #5f6762; font-size: 9px; }
            .store { text-align: right; }
            .store strong { display: block; font-size: 13px; margin-bottom: 3px; }
            .period { background: #edf3ef; border: 1px solid #ccd9d0; margin-bottom: 14px; padding: 8px 10px; }
            .period strong { color: #245b3c; }
            table.register { border-collapse: collapse; table-layout: fixed; width: 100%; }
            .register th { background: #245b3c; color: #fff; font-size: 8px; line-height: 1.25; padding: 7px 5px; text-align: right; }
            .register th:first-child { text-align: left; width: 12%; }
            .register th:nth-child(2) { width: 8%; }
            .register td { border-bottom: 1px solid #d9dfdb; padding: 6px 5px; }
            .register tbody tr:nth-child(even) { background: #f5f7f5; }
            .number { text-align: right; white-space: nowrap; }
            .count { text-align: center; }
            .total { font-weight: bold; }
            .register tfoot td { background: #e6eee9; border-bottom: 0; border-top: 2px solid #245b3c; font-weight: bold; padding: 8px 5px; }
            .empty { color: #68706b; padding: 20px !important; text-align: center; }
            .note { color: #68706b; font-size: 8px; margin-top: 10px; }
        </style></head><body>'
            . '<table class="header"><tr><td><div class="company">' . $this->escape($companyName) . '</div>'
            . '<h1>Registru incasari - taxa de procesare</h1>'
            . '<div class="subtitle">' . $this->escape(implode(' · ', $companyMeta)) . '</div></td>'
            . '<td class="store"><span>Gestiune</span><strong>' . $this->escape((string) $report['store']['name']) . '</strong>'
            . '<span>' . $this->escape((string) ($report['store']['address'] ?? '')) . '</span></td></tr></table>'
            . '<div class="period"><strong>Perioada raportata:</strong> ' . $this->escape($period)
            . ' &nbsp;&nbsp; <strong>Cota TVA:</strong> ' . $vatRate . '%</div>'
            . '<table class="register"><thead><tr><th>Data</th><th>Nr. bonuri</th><th>Numerar<br>(TVA inclus)</th><th>Card<br>(TVA inclus)</th><th>Total<br>fara TVA</th><th>TVA ' . $vatRate . '%</th><th>Total<br>cu TVA</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody><tfoot><tr><td>TOTAL PERIOADA</td><td class="count">' . (int) $totals['receipt_count'] . '</td>'
            . '<td class="number">' . $this->money((int) $totals['cash_cents']) . '</td>'
            . '<td class="number">' . $this->money((int) $totals['card_cents']) . '</td>'
            . '<td class="number">' . $this->money((int) $totals['net_cents']) . '</td>'
            . '<td class="number">' . $this->money((int) $totals['vat_cents']) . '</td>'
            . '<td class="number">' . $this->money((int) $totals['total_cents']) . '</td></tr></tfoot></table>'
            . '<div class="note">Document generat la ' . date('d.m.Y H:i') . '. Valorile nete si TVA sunt calculate din incasarile cu TVA inclus.</div>'
            . '</body></html>';
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.') . ' lei';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
