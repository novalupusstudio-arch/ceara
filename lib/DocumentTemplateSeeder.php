<?php

declare(strict_types=1);

namespace Ceara;

use PDO;
use RuntimeException;

final class DocumentTemplateSeeder
{
    public function __construct(private PDO $pdo)
    {
    }

    public function run(): void
    {
        foreach ($this->defaultDocumentTemplates() as $template) {
            $this->pdo->prepare(
                'INSERT IGNORE INTO document_templates
                (code, name, description, body_html, variables_json, active)
                VALUES (?, ?, ?, ?, ?, 1)'
            )->execute([
                $template['code'],
                $template['name'],
                $template['description'],
                $template['body_html'],
                json_encode($template['variables'], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    private function templateFile(string $fileName): string
    {
        $path = __DIR__ . '/templates/' . $fileName;
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Lipseste template-ul de document: ' . $fileName);
        }
        return $content;
    }

    private function defaultDocumentTemplates(): array
    {
        return [
            [
                'code' => 'PV-CUST',
                'name' => 'PV primire ceara bruta in custodie',
                'description' => 'Proces-verbal pentru luarea in custodie a cerii brute de la client.',
                'variables' => [
                    'document_number',
                    'document_date',
                    'company_name',
                    'company_vat_number',
                    'company_registry_number',
                    'company_address',
                    'store_name',
                    'store_address',
                    'operator_name',
                    'customer_name',
                    'customer_identifier',
                    'customer_address',
                    'customer_phone',
                    'customer_type',
                    'lot_number',
                    'gross_wax_kg',
                    'package_count',
                    'wax_observations',
                    'app_name',
                    'generated_at',
                ],
                'body_html' => $this->templateFile('pv-cust.html'),
            ],
            [
                'code' => 'PV-FAG',
                'name' => 'PV predare faguri client',
                'description' => 'Proces-verbal pentru predarea fagurilor catre client in urma schimbului de ceara.',
                'variables' => [
                    'document_number',
                    'document_date',
                    'company_name',
                    'company_vat_number',
                    'company_registry_number',
                    'company_address',
                    'store_name',
                    'store_address',
                    'operator_name',
                    'customer_name',
                    'customer_identifier',
                    'customer_address',
                    'customer_phone',
                    'lot_number',
                    'wax_processed_kg',
                    'shrinkage_pct',
                    'foundation_delivered_kg',
                    'service_value',
                    'notes',
                    'app_name',
                    'generated_at',
                ],
                'body_html' => <<<'HTML'
<style>
  @page {
    margin: 18mm 12mm 15mm 12mm;
  }

  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 11px;
    color: #000;
    line-height: 1.25;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
  }

  p {
    margin: 6px 0;
  }

  .header-table td {
    vertical-align: top;
  }

  .company-name {
    font-size: 14px;
    font-weight: bold;
    text-transform: uppercase;
  }

  .doc-box {
    border: 1px solid #000;
    padding: 6px;
    text-align: center;
  }

  .document-title {
    text-align: center;
    font-size: 16px;
    font-weight: bold;
    margin: 16px 0 5px;
    text-transform: uppercase;
  }

  .document-subtitle {
    text-align: center;
    margin-bottom: 12px;
  }

  .info-table td,
  .items-table th,
  .items-table td {
    border: 1px solid #000;
    padding: 5px;
  }

  .label,
  .items-table th {
    font-weight: bold;
    background: #f2f2f2;
  }

  .label {
    width: 25%;
  }

  .items-table {
    margin-top: 10px;
  }

  .notes-box {
    border: 1px solid #000;
    min-height: 42px;
    padding: 6px;
    margin-top: 6px;
  }

  .legal-box {
    border: 1px solid #000;
    padding: 7px;
    margin-top: 12px;
    font-size: 10px;
  }

  .sign-table {
    margin-top: 35px;
  }

  .sign-table td {
    width: 50%;
    text-align: center;
    vertical-align: top;
  }

  .signature-line {
    margin-top: 38px;
    border-top: 1px solid #000;
    display: inline-block;
    width: 75%;
    padding-top: 3px;
  }

  .text-right {
    text-align: right;
  }

  .text-center {
    text-align: center;
  }
</style>

<table class="header-table">
  <tr>
    <td style="width:70%;">
      <div class="company-name">[company_name]</div>
      CUI: [company_vat_number]<br>
      Nr. Reg. Com.: [company_registry_number]<br>
      [company_address]<br>
      Gestiune: [store_name]<br>
      [store_address]
    </td>
    <td style="width:30%;">
      <div class="doc-box">
        <strong>PV-FAG</strong><br>
        Nr. [document_number]<br>
        Data: [document_date]
      </div>
    </td>
  </tr>
</table>

<div class="document-title">PROCES-VERBAL DE PREDARE FAGURI</div>
<div class="document-subtitle">aferent serviciului de procesare ceara</div>

<table class="info-table">
  <tr>
    <td class="label">Client</td>
    <td>[customer_name]</td>
    <td class="label">Lot</td>
    <td>[lot_number]</td>
  </tr>
  <tr>
    <td class="label">CNP / CI / CUI</td>
    <td>[customer_identifier]</td>
    <td class="label">Telefon</td>
    <td>[customer_phone]</td>
  </tr>
  <tr>
    <td class="label">Adresa</td>
    <td colspan="3">[customer_address]</td>
  </tr>
  <tr>
    <td class="label">Operator</td>
    <td>[operator_name]</td>
    <td class="label">Data predarii</td>
    <td>[document_date]</td>
  </tr>
</table>

<table class="items-table">
  <thead>
    <tr>
      <th style="width:8%;">Nr.</th>
      <th style="width:42%;">Denumire</th>
      <th style="width:10%;">UM</th>
      <th style="width:15%;">Cantitate ceara</th>
      <th style="width:15%;">Scazamant</th>
      <th style="width:10%;">Faguri predati</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="text-center">1</td>
      <td>Faguri de ceara rezultati din serviciul de procesare</td>
      <td class="text-center">kg</td>
      <td class="text-right">[wax_processed_kg]</td>
      <td class="text-right">[shrinkage_pct] %</td>
      <td class="text-right">[foundation_delivered_kg]</td>
    </tr>
    <tr>
      <td colspan="5" class="text-right"><strong>Total faguri predati</strong></td>
      <td class="text-right"><strong>[foundation_delivered_kg]</strong></td>
    </tr>
  </tbody>
</table>

<h4>Observatii</h4>
<div class="notes-box">[notes]</div>

<div class="legal-box">
  Prin prezentul proces-verbal clientul confirma primirea cantitatii de faguri mentionate mai sus.
  Cantitatea de faguri a fost determinata prin aplicarea scazamantului aferent serviciului de procesare asupra cantitatii de ceara preluate.
  Predarea se realizeaza in sistem de echivalent cantitativ si calitativ, conform conditiilor generale ale serviciului de procesare ceara.
</div>

<table class="sign-table">
  <tr>
    <td>
      <strong>Predat de</strong><br>
      [operator_name]
      <div class="signature-line">Semnatura</div>
    </td>
    <td>
      <strong>Primit de</strong><br>
      [customer_name]
      <div class="signature-line">Semnatura client</div>
    </td>
  </tr>
</table>

HTML,
            ],
            [
                'code' => 'PV-RET',
                'name' => 'PV retur ceara respinsa',
                'description' => 'Proces-verbal pentru returul cerii respinse catre client.',
                'variables' => [
                    'document_number',
                    'document_date',
                    'company_name',
                    'company_vat_number',
                    'company_registry_number',
                    'company_address',
                    'store_name',
                    'store_address',
                    'operator_name',
                    'customer_name',
                    'customer_identifier',
                    'customer_address',
                    'customer_phone',
                    'lot_number',
                    'wax_returned_kg',
                    'notes',
                    'app_name',
                    'generated_at',
                ],
                'body_html' => <<<'HTML'
<style>
  @page {
    margin: 18mm 12mm 15mm 12mm;
  }

  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 11px;
    color: #000;
    line-height: 1.25;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
  }

  p {
    margin: 6px 0;
  }

  .header-table td {
    vertical-align: top;
  }

  .company-name {
    font-size: 14px;
    font-weight: bold;
    text-transform: uppercase;
  }

  .doc-box {
    border: 1px solid #000;
    padding: 6px;
    text-align: center;
  }

  .document-title {
    text-align: center;
    font-size: 16px;
    font-weight: bold;
    margin: 16px 0 5px;
    text-transform: uppercase;
  }

  .document-subtitle {
    text-align: center;
    margin-bottom: 12px;
  }

  .info-table td,
  .items-table th,
  .items-table td {
    border: 1px solid #000;
    padding: 5px;
  }

  .label,
  .items-table th {
    font-weight: bold;
    background: #f2f2f2;
  }

  .label {
    width: 25%;
  }

  .items-table {
    margin-top: 10px;
  }

  .notes-box {
    border: 1px solid #000;
    min-height: 42px;
    padding: 6px;
    margin-top: 6px;
  }

  .legal-box {
    border: 1px solid #000;
    padding: 7px;
    margin-top: 12px;
    font-size: 10px;
  }

  .sign-table {
    margin-top: 35px;
  }

  .sign-table td {
    width: 50%;
    text-align: center;
    vertical-align: top;
  }

  .signature-line {
    margin-top: 38px;
    border-top: 1px solid #000;
    display: inline-block;
    width: 75%;
    padding-top: 3px;
  }

  .text-right {
    text-align: right;
  }

  .text-center {
    text-align: center;
  }
</style>

<table class="header-table">
  <tr>
    <td style="width:70%;">
      <div class="company-name">[company_name]</div>
      CUI: [company_vat_number]<br>
      Nr. Reg. Com.: [company_registry_number]<br>
      [company_address]<br>
      Gestiune: [store_name]<br>
      [store_address]
    </td>
    <td style="width:30%;">
      <div class="doc-box">
        <strong>PV-RET</strong><br>
        Nr. [document_number]<br>
        Data: [document_date]
      </div>
    </td>
  </tr>
</table>

<div class="document-title">PROCES-VERBAL DE RETUR CEARA</div>
<div class="document-subtitle">ceara respinsa / neacceptata la procesare</div>

<table class="info-table">
  <tr>
    <td class="label">Client</td>
    <td>[customer_name]</td>
    <td class="label">Lot</td>
    <td>[lot_number]</td>
  </tr>
  <tr>
    <td class="label">CNP / CI / CUI</td>
    <td>[customer_identifier]</td>
    <td class="label">Telefon</td>
    <td>[customer_phone]</td>
  </tr>
  <tr>
    <td class="label">Adresa</td>
    <td colspan="3">[customer_address]</td>
  </tr>
  <tr>
    <td class="label">Operator</td>
    <td>[operator_name]</td>
    <td class="label">Data returului</td>
    <td>[document_date]</td>
  </tr>
</table>

<table class="items-table">
  <thead>
    <tr>
      <th style="width:8%;">Nr.</th>
      <th style="width:62%;">Denumire</th>
      <th style="width:10%;">UM</th>
      <th style="width:20%;">Cantitate returnata</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="text-center">1</td>
      <td>Ceara bruta returnata clientului ca respinsa / neacceptata la procesare</td>
      <td class="text-center">kg</td>
      <td class="text-right">[wax_returned_kg]</td>
    </tr>
    <tr>
      <td colspan="3" class="text-right"><strong>Total ceara returnata</strong></td>
      <td class="text-right"><strong>[wax_returned_kg]</strong></td>
    </tr>
  </tbody>
</table>

<h4>Observatii / motiv retur</h4>
<div class="notes-box">[notes]</div>

<div class="legal-box">
  Prin prezentul proces-verbal clientul confirma primirea cantitatii de ceara mentionate mai sus.
  Returul se face ca urmare a respingerii sau neacceptarii cerii pentru serviciul de procesare.
  Predarea cerii returnate inchide evidenta operationala aferenta cantitatii returnate din lot.
</div>

<table class="sign-table">
  <tr>
    <td>
      <strong>Predat de</strong><br>
      [operator_name]
      <div class="signature-line">Semnatura</div>
    </td>
    <td>
      <strong>Primit de</strong><br>
      [customer_name]
      <div class="signature-line">Semnatura client</div>
    </td>
  </tr>
</table>

HTML,
            ],
            [
                'code' => 'NIR',
                'name' => 'NIR faguri intrati de la fabrica',
                'description' => 'Nota de intrare receptie pentru fagurii intrati pe baza de aviz de la fabrica.',
                'variables' => [
                    'document_number',
                    'document_date',
                    'company_name',
                    'company_vat_number',
                    'company_registry_number',
                    'company_address',
                    'store_name',
                    'store_address',
                    'operator_name',
                    'processor_name',
                    'processor_identifier',
                    'processor_address',
                    'aviz_number',
                    'aviz_date',
                    'adjustment_type',
                    'adjustment_label',
                    'foundation_qty_kg',
                    'foundation_qty_g',
                    'item_name',
                    'item_unit',
                    'item_qty',
                    'item_unit_price',
                    'item_value',
                    'notes',
                    'app_name',
                    'generated_at',
                ],
                'body_html' => <<<'HTML'
<style>
  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 11px;
    line-height: 1.25;
  }

  h2 {
    font-size: 16px;
    margin: 0 0 8px;
    text-align: center;
  }

  p {
    margin: 6px 0;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
  }

  th,
  td {
    border: 1px solid #222;
    padding: 5px;
  }
</style>

<h2>NOTA DE INTRARE RECEPTIE</h2>

<p>
  <strong>Nr.:</strong> [nir_number] &nbsp;&nbsp;
  <strong>Data:</strong> [nir_date]
</p>

<p>
  <strong>Societate:</strong> [company_name]<br>
  <strong>CUI:</strong> [company_vat_number]<br>
  <strong>Nr. Reg. Com.:</strong> [company_registry_number]<br>
  <strong>Sediu:</strong> [company_address]<br>
  <strong>Gestiune:</strong> [store_name] - [store_address]
</p>

<p>
  <strong>Furnizor / Procesator:</strong> [processor_name]<br>
  <strong>CUI:</strong> [processor_identifier]<br>
  <strong>Adresa:</strong> [processor_address]
</p>

<p>
  <strong>Aviz:</strong> [aviz_number] din [aviz_date]<br>
  <strong>Tip miscare:</strong> [adjustment_label]
</p>

<table>
  <thead>
    <tr>
      <th>Produs</th>
      <th>UM</th>
      <th>Cantitate</th>
      <th>Pret unitar</th>
      <th>Valoare</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>[item_name]</td>
      <td>[item_unit]</td>
      <td style="text-align:right;">[item_qty]</td>
      <td style="text-align:right;">[item_unit_price]</td>
      <td style="text-align:right;">[item_value]</td>
    </tr>
  </tbody>
</table>

<p>
  <strong>Observatii:</strong><br>
  [notes]
</p>

<table style="margin-top:40px; border:0;">
  <tr>
    <td style="width:50%; text-align:center; border:0;">
      <strong>Gestionar</strong><br><br>
      [operator_name]<br><br>
      Semnatura: ______________________
    </td>
    <td style="width:50%; text-align:center; border:0;">
      <strong>Comisie receptie</strong><br><br><br><br>
      Semnatura: ______________________
    </td>
  </tr>
</table>

HTML,
            ],
            [
                'code' => 'AVIZ',
                'name' => 'Aviz predare ceara la fabrica',
                'description' => 'Aviz pentru predarea cerii catre fabrica, cu loturi multiple.',
                'variables' => [
                    'document_number',
                    'document_date',
                    'company_name',
                    'company_vat_number',
                    'company_registry_number',
                    'company_address',
                    'store_name',
                    'store_address',
                    'operator_name',
                    'processor_name',
                    'processor_identifier',
                    'processor_address',
                    'factory_batch_number',
                    'factory_items_rows',
                    'factory_wax_total_kg',
                    'factory_foundation_expected_kg',
                    'notes',
                    'app_name',
                    'generated_at',
                ],
                'body_html' => <<<'HTML'
<style>
  @page {
    margin: 18mm 12mm 15mm 12mm;
  }

  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 11px;
    color: #000;
    line-height: 1.25;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
  }

  p {
    margin: 6px 0;
  }

  .header-table td {
    vertical-align: top;
  }

  .company-name {
    font-size: 14px;
    font-weight: bold;
    text-transform: uppercase;
  }

  .doc-box {
    border: 1px solid #000;
    padding: 6px;
    text-align: center;
  }

  .document-title {
    text-align: center;
    font-size: 16px;
    font-weight: bold;
    margin: 16px 0 5px;
    text-transform: uppercase;
  }

  .document-subtitle {
    text-align: center;
    margin-bottom: 12px;
  }

  .info-table td,
  .items-table th,
  .items-table td {
    border: 1px solid #000;
    padding: 5px;
  }

  .label,
  .items-table th {
    font-weight: bold;
    background: #f2f2f2;
  }

  .label {
    width: 25%;
  }

  .items-table {
    margin-top: 10px;
  }

  .legal-box {
    border: 1px solid #000;
    padding: 7px;
    margin-top: 12px;
    font-size: 10px;
  }

  .sign-table {
    margin-top: 35px;
  }

  .sign-table td {
    width: 50%;
    text-align: center;
    vertical-align: top;
  }

  .signature-line {
    margin-top: 38px;
    border-top: 1px solid #000;
    display: inline-block;
    width: 75%;
    padding-top: 3px;
  }

  .text-right {
    text-align: right;
  }

  .text-center {
    text-align: center;
  }
</style>

<table class="header-table">
  <tr>
    <td style="width:70%;">
      <div class="company-name">[company_name]</div>
      CUI: [company_vat_number]<br>
      Nr. Reg. Com.: [company_registry_number]<br>
      [company_address]<br>
      Gestiune: [store_name]<br>
      [store_address]
    </td>
    <td style="width:30%;">
      <div class="doc-box">
        <strong>AVIZ</strong><br>
        Nr. [document_number]<br>
        Data: [document_date]
      </div>
    </td>
  </tr>
</table>

<div class="document-title">AVIZ DE PREDARE CEARA CATRE FABRICA</div>
<div class="document-subtitle">pentru serviciul de procesare ceara</div>

<table class="info-table">
  <tr>
    <td class="label">Procesator</td>
    <td>[processor_name]</td>
    <td class="label">Batch</td>
    <td>[factory_batch_number]</td>
  </tr>
  <tr>
    <td class="label">CUI procesator</td>
    <td>[processor_identifier]</td>
    <td class="label">Data predarii</td>
    <td>[document_date]</td>
  </tr>
  <tr>
    <td class="label">Adresa procesator</td>
    <td colspan="3">[processor_address]</td>
  </tr>
  <tr>
    <td class="label">Operator</td>
    <td>[operator_name]</td>
    <td class="label">Gestiune</td>
    <td>[store_name]</td>
  </tr>
</table>

<table class="items-table">
  <thead>
    <tr>
      <th style="width:8%;">Nr.</th>
      <th style="width:32%;">Lot</th>
      <th style="width:40%;">Client</th>
      <th style="width:20%;">Ceara predata</th>
    </tr>
  </thead>
  <tbody>
    [factory_items_rows]
    <tr>
      <td colspan="3" class="text-right"><strong>Total ceara predata</strong></td>
      <td class="text-right"><strong>[factory_wax_total_kg]</strong></td>
    </tr>
  </tbody>
</table>

<p>
  <strong>Faguri estimati dupa procesare:</strong> [factory_foundation_expected_kg]
</p>

<div class="legal-box">
  Ceara mentionata mai sus este predata catre procesator in vederea procesarii.
  Loturile raman evidentiate operational separat, iar cantitatile receptionate ulterior de la procesator se vor inregistra pe baza documentelor de receptie.
</div>

<table class="sign-table">
  <tr>
    <td>
      <strong>Predat de</strong><br>
      [operator_name]
      <div class="signature-line">Semnatura</div>
    </td>
    <td>
      <strong>Primit de procesator</strong><br>
      [processor_name]
      <div class="signature-line">Semnatura / stampila</div>
    </td>
  </tr>
</table>

HTML,
            ],
        ];
    }
}
