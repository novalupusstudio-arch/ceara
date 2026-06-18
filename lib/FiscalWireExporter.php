<?php

final class FiscalWireExporter
{
    public function __construct(private array $config)
    {
    }

    public function enabled(): bool
    {
        return !empty($this->config['enabled']);
    }

    public function buildReceipt(array $data): string
    {
        $printer = (int) ($this->config['logical_printer'] ?? 1);
        $prefix = ',' . $printer . ',______,_,__;';
        $lines = [];

        $articleName = $this->cleanText((string) ($data['article_name'] ?? $this->config['article_name'] ?? 'Servicii procesare ceara'), 72);
        $price = $this->money((int) $data['amount_cents']);
        $vat = $this->vatCode();
        $department = (int) ($this->config['department'] ?? 1);
        $group = (int) ($this->config['group'] ?? 1);
        $lines[] = 'S' . $prefix . $articleName . ';' . $price . ';1.00;' . $department . ';' . $group . ';' . $vat . ';0;0;';

        $paymentType = $data['payment_method'] === 'card' ? 1 : 0;
        $lines[] = 'T' . $prefix . $paymentType . ';' . $price . ';;;;';

        return implode("\r\n", $lines) . "\r\n";
    }

    public function writeReceipt(string $fileName, string $content): string
    {
        $dir = (string) ($this->config['export_dir'] ?? '');
        if ($dir === '') {
            throw new RuntimeException('Folderul FiscalWire nu este configurat.');
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = rtrim(str_replace('\\', '/', $dir), '/') . '/' . $fileName;
        $tmp = $path . '.tmp';
        file_put_contents($tmp, $content);
        rename($tmp, $path);

        return $path;
    }

    public function extension(): string
    {
        return trim((string) ($this->config['extension'] ?? 'bon'), '.') ?: 'bon';
    }

    private function vatCode(): string
    {
        $code = trim((string) ($this->config['vat_code'] ?? ''));
        if ($code !== '') {
            return $code;
        }

        $vat = (float) ($this->config['vat_rate'] ?? 21);
        return rtrim(rtrim(number_format($vat, 2, '.', ''), '0'), '.') . '%';
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function cleanText(string $value, int $maxLength): string
    {
        $value = preg_replace('/[;\r\n]+/', ' ', $value) ?? '';
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        return substr($value, 0, $maxLength);
    }

}
