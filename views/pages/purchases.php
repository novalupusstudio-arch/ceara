<?php
$assignedStore = $data['assigned_store'] ?? [];
$defaultShrinkage = (string) ($assignedStore['purchase_shrinkage_pct'] ?? '0');
$defaultPrice = number_format(((int) ($assignedStore['purchase_price_cents_per_kg'] ?? 0)) / 100, 2, '.', '');
?>

<header class="page-header">
    <div>
        <span class="eyebrow">Achizitie</span>
        <h1>Achizitie ceara</h1>
    </div>
</header>

<section class="panel">
    <h2>Lot achizitie nou</h2>
    <form
        method="post"
        class="processing-form"
        data-purchase-form
        data-default-shrinkage="<?= h($defaultShrinkage) ?>"
        data-default-price="<?= h($defaultPrice) ?>"
    >
        <input type="hidden" name="action" value="create_purchase">

        <div class="customer-type-switch">
            <span class="field-title">Tip furnizor</span>
            <label class="radio-chip">
                <input type="radio" name="supplier_type" value="PF" checked data-purchase-type>
                <span>PF</span>
            </label>
            <label class="radio-chip">
                <input type="radio" name="supplier_type" value="Producator agricol" data-purchase-type>
                <span>Producator agricol</span>
            </label>
            <label class="radio-chip">
                <input type="radio" name="supplier_type" value="PJ/PFA" data-purchase-type>
                <span>PJ / PFA</span>
            </label>
        </div>

        <div class="processing-fields">
            <div class="processing-row columns-3" data-purchase-person-row>
                <label>
                    Nume furnizor
                    <input name="supplier_name" required placeholder="Nume furnizor">
                </label>
                <label>
                    Telefon
                    <input name="supplier_phone" placeholder="07xxxxxxxx">
                </label>
                <label data-purchase-identifier-field>
                    CNP / CI
                    <input name="supplier_identifier" placeholder="CJ123456">
                </label>
                <label class="is-hidden" data-purchase-cui-field hidden>
                    CUI
                    <input name="supplier_cui" placeholder="RO123456">
                </label>
            </div>

            <div class="processing-row columns-3">
                <label>
                    Judet
                    <select name="supplier_county_code" data-purchase-county>
                        <option value="">Alege judet</option>
                    </select>
                </label>
                <label>
                    Localitate
                    <select name="supplier_locality_siruta" data-purchase-locality disabled>
                        <option value="">Alege localitate</option>
                    </select>
                </label>
                <label>
                    Adresa
                    <input name="supplier_address" placeholder="Strada, numar">
                </label>
            </div>

            <input type="hidden" name="supplier_county_name" data-purchase-county-name>
            <input type="hidden" name="supplier_locality_name" data-purchase-locality-name>
            <input type="hidden" name="supplier_postal_code" data-purchase-postal-code>

            <div class="processing-row columns-4">
                <label>
                    Data achizitiei
                    <input type="date" name="purchase_date" value="<?= h(date('Y-m-d')) ?>" required>
                </label>
                <label>
                    Serie document
                    <input name="document_series" required placeholder="BA-2026-GEST1" data-purchase-doc-series>
                </label>
                <label>
                    Numar document
                    <input name="document_number" required placeholder="17" data-purchase-doc-number>
                </label>
                <label data-purchase-position-field>
                    Pozitie document
                    <input name="document_position" placeholder="17" data-purchase-doc-position>
                </label>
                <label class="is-hidden" data-purchase-doc-date-field hidden>
                    Data factura
                    <input type="date" name="document_date" data-purchase-doc-date>
                </label>
            </div>

            <div class="processing-row columns-4">
                <label>
                    Cantitate ceara kg
                    <input name="gross_kg" required inputmode="decimal" placeholder="20.000" data-purchase-gross>
                </label>
                <label>
                    Scazamant %
                    <input name="shrinkage_pct" required inputmode="decimal" value="<?= h($defaultShrinkage) ?>" data-purchase-shrinkage>
                </label>
                <label>
                    Pret cu TVA lei/kg
                    <input name="purchase_price" required inputmode="decimal" value="<?= h($defaultPrice) ?>" data-purchase-price>
                </label>
                <label>
                    Total
                    <input value="0.00 lei" readonly data-purchase-total>
                </label>
            </div>

            <div class="processing-row columns-3">
                <label>
                    Cantitate neta estimata
                    <input value="0,000 kg" readonly data-purchase-net>
                </label>
                <label>
                    Gestiune
                    <input value="<?= h((string) ($data['assigned_store']['name'] ?? '')) ?>" readonly>
                </label>
                <div class="form-actions">
                    <button class="primary" type="submit">Creeaza lot achizitie</button>
                </div>
            </div>
        </div>
    </form>
</section>
