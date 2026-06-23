<?php
$assignedStore = $data['assigned_store'] ?? [];
if (!$assignedStore) {
    throw new RuntimeException('Utilizatorul nu are o gestiune alocata.');
}

$defaultProcessorId = (int) ($assignedStore['processor_id'] ?? 0);
if ($defaultProcessorId <= 0) {
    throw new RuntimeException('Gestiunea utilizatorului nu are procesator asignat.');
}

$defaultPriceCents = (int) ($assignedStore['processing_price_cents'] ?? 0);
$defaultShrinkagePct = (float) ($assignedStore['processing_shrinkage_pct'] ?? 0);
?>

<header class="page-header">
    <div>
        <span class="eyebrow">Procesare</span>
        <h1>Loturi procesare ceara</h1>
    </div>
</header>

<section class="panel">
    <h2>Lot nou</h2>
    <form method="post" class="processing-form" data-processing-form autocomplete="off">
        <input type="hidden" name="action" value="create_processing">
        <input type="hidden" name="existing_customer_id" value="0" data-existing-customer-id>
        <input type="hidden" name="force_new_customer" value="0" data-force-new-customer>
        <input type="hidden" name="known_customer" value="0">

        <div class="customer-type-switch">
            <span class="field-title">Tip client</span>
            <label class="radio-chip">
                <input type="radio" name="customer_type" value="PF" checked data-customer-type>
                <span>PF</span>
            </label>
            <label class="radio-chip">
                <input type="radio" name="customer_type" value="PJ" data-customer-type>
                <span>PJ</span>
            </label>
        </div>

        <div class="customer-search-row">
            <label data-search-label>
                <span data-search-text>Cautare dupa telefon</span>
                <input name="customer_search" autocomplete="off" placeholder="Scrie pentru cautare" data-customer-search>
            </label>
            <button class="secondary" type="button" data-new-customer-button>Client nou</button>
        </div>

        <div class="lookup-results" data-lookup-results hidden></div>

        <div class="processing-fields">
            <div class="processing-row columns-3" data-pf-row>
                <label data-name-field>
                    <span data-name-label>Nume client</span>
                    <input name="customer_name" required placeholder="Nume client PF" data-customer-name autocomplete="off">
                </label>
                <label data-phone-field>
                    <span data-phone-label>Telefon</span>
                    <input name="customer_phone" required placeholder="07xxxxxxxx" data-customer-phone autocomplete="off">
                </label>
                <label data-identifier-field>
                    CNP/CI
                    <input name="customer_identifier" placeholder="CJ123456" data-customer-identifier autocomplete="off">
                </label>
            </div>

            <div class="processing-row columns-4 is-hidden" data-pj-row hidden>
                <label>
                    Nume PJ
                    <input name="customer_name_pj" placeholder="Denumire companie" data-customer-name-pj autocomplete="off">
                </label>
                <label>
                    CUI
                    <input name="customer_cui" placeholder="RO123456" data-customer-cui autocomplete="off">
                </label>
                <label>
                    Telefon PJ
                    <input name="customer_phone_pj" placeholder="07xxxxxxxx" data-customer-phone-pj autocomplete="off">
                </label>
                <label>
                    Reprezentant
                    <input name="customer_representative" placeholder="Nume reprezentant" data-customer-representative autocomplete="off">
                </label>
            </div>

            <div class="processing-row columns-3">
                <label>
                    Judet
                    <select name="customer_county_code" data-customer-county>
                        <option value="">Alege judet</option>
                    </select>
                </label>
                <label>
                    Localitate
                    <select name="customer_locality_siruta" data-customer-locality disabled>
                        <option value="">Alege localitate</option>
                    </select>
                </label>
                <label data-pf-address-field>
                    Adresa
                    <input name="customer_address" required placeholder="Adresa client" data-customer-address autocomplete="off">
                </label>
                <label class="is-hidden" data-pj-address-field hidden>
                    Adresa preluata
                    <input name="customer_address_pj" placeholder="Strada, numar" data-customer-address-pj autocomplete="off">
                </label>
            </div>

            <input type="hidden" name="customer_county_name" data-customer-county-name>
            <input type="hidden" name="customer_locality_name" data-customer-locality-name>
            <input type="hidden" name="customer_postal_code" data-customer-postal-code>
            <input type="hidden" name="customer_registry_number" data-customer-registry-number>
            <input type="hidden" name="customer_legal_form" data-customer-legal-form>
            <input type="hidden" name="customer_vat_status" data-customer-vat-status>
            <input type="hidden" name="customer_external_source" data-customer-external-source>
            <input type="hidden" name="customer_external_checked_at" data-customer-external-checked-at>

            <div class="processing-row columns-3">
                <label>
                    Procesator
                    <select name="processor_id" data-processor-select>
                        <?php foreach ($data['processors'] as $processor): ?>
                            <option value="<?= h((string) $processor['id']) ?>" <?= (int) $processor['id'] === $defaultProcessorId ? 'selected' : '' ?>>
                                <?= h($processor['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Pret procesare
                    <input name="processing_price" required inputmode="decimal" value="<?= h(number_format($defaultPriceCents / 100, 2, '.', '')) ?>" data-processing-price>
                </label>
                <label>
                    Scazamant %
                    <input name="shrinkage_pct" required inputmode="decimal" value="<?= h(number_format($defaultShrinkagePct, 3, '.', '')) ?>" data-processing-shrinkage>
                </label>
            </div>

            <div class="processing-row columns-3">
                <label>
                    Cantitate ceara kg
                    <input name="gross_kg" required inputmode="decimal" placeholder="12.500">
                </label>
                <label>
                    Cantitate faguri
                    <input value="0,000 kg" readonly data-processing-exchange>
                </label>
                <label>
                    Cost procesare
                    <input value="0.00 lei" readonly data-processing-cost>
                </label>
            </div>

            <div class="form-actions">
                <button class="primary" type="submit">Creeaza lot</button>
            </div>
        </div>
    </form>
</section>
