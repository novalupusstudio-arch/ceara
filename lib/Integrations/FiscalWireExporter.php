<?php

declare(strict_types=1);

namespace Ceara\Integrations;

use RuntimeException;

final class FiscalWireExporter
{
    private const LOGICAL_PRINTER = 1;
    private const ARTICLE_NAME = 'Servicii procesare';
    private const DEPARTMENT = 1;
    private const GROUP = 1;
    private const EXPORT_DIR = '/storage/fiscalwire-out';
    private const EXTENSION = 'inp';
    private const VAT_CODE = '1';

    public function __construct(private array $config)
    {
    }

    public function enabled(): bool
    {
        return true;
    }

    public function buildReceipt(array $data): string
    {
        $printer = self::LOGICAL_PRINTER;
        $prefix = ',' . $printer . ',______,_,__;';
        $lines = [];

        $articleName = $this->cleanText((string) ($data['article_name'] ?? self::ARTICLE_NAME), 72);
        $price = $this->money((int) $data['amount_cents']);
        $vat = $this->vatCode();
        $department = self::DEPARTMENT;
        $group = self::GROUP;
        $lines[] = 'S' . $prefix . $articleName . ';' . $price . ';1.00;' . $department . ';' . $group . ';' . $vat . ';0;0;';

        $paymentType = $data['payment_method'] === 'card' ? 1 : 0;
        $lines[] = 'T' . $prefix . $paymentType . ';' . $price . ';;;;';

        return implode("\r\n", $lines) . "\r\n";
    }

    public function writeReceipt(string $fileName, string $content): string
    {
        $dir = dirname(__DIR__, 2) . self::EXPORT_DIR;
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
        return self::EXTENSION;
    }

    private function vatCode(): string
    {
        return self::VAT_CODE;
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
