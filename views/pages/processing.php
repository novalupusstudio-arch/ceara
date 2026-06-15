<?php
$processorsForJs = [];
foreach ($data['processors'] as $processor) {
    $processorsForJs[] = [
        'id' => (int) $processor['id'],
        'name' => $processor['name'],
        'processing_price_cents' => (int) $processor['processing_price_cents'],
        'exchange_shrinkage_pct' => (float) $processor['exchange_shrinkage_pct'],
    ];
}
$defaultProcessorId = isset($data['default_processor']['id']) ? (int) $data['default_processor']['id'] : 0;
$assignedStore = $data['assigned_store'] ?? null;
?>

<header class="page-header">
    <div>
        <span class="eyebrow">Procesare</span>
        <h1>Loturi procesare ceara</h1>
    </div>
</header>

<section class="panel">
    <h2>Lot nou</h2>
    <form method="post" class="processing-form" data-processing-form data-processors='<?= h(json_encode($processorsForJs, JSON_UNESCAPED_UNICODE)) ?>'>
        <input type="hidden" name="action" value="create_processing">
        <input type="hidden" name="existing_customer_id" value="0" data-existing-customer-id>
        <input type="hidden" name="force_new_customer" value="0" data-force-new-customer>
        <input type="hidden" name="known_customer" value="1">

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

        <div class="form-grid">
            <label data-pf-field data-name-field>
                <span data-name-label>Nume client</span>
                <input name="customer_name" required placeholder="Nume client PF" data-customer-name>
            </label>
            <label data-pf-field data-phone-field>
                <span data-phone-label>Telefon</span>
                <input name="customer_phone" required placeholder="07xxxxxxxx" data-customer-phone>
            </label>
            <label class="wide" data-pf-field data-common-field>
                Adresa
                <input name="customer_address" required placeholder="Adresa client" data-customer-address>
            </label>
            <label data-pj-field class="is-hidden" hidden style="display:none">
                Nume PJ
                <input name="customer_name_pj" placeholder="Denumire companie" data-customer-name-pj>
            </label>
            <label class="wide is-hidden" data-pj-field hidden style="display:none">
                Adresa PJ
                <input name="customer_address_pj" placeholder="Adresa sediu" data-customer-address-pj>
            </label>
            <label data-pj-field class="is-hidden" hidden style="display:none">
                Telefon PJ
                <input name="customer_phone_pj" placeholder="07xxxxxxxx" data-customer-phone-pj>
            </label>
            <label data-pj-field class="is-hidden" hidden style="display:none">
                CUI
                <input name="customer_cui" placeholder="RO123456" data-customer-cui>
            </label>
            <label class="wide is-hidden" data-pj-field hidden style="display:none">
                Reprezentant
                <input name="customer_representative" placeholder="Nume reprezentant" data-customer-representative>
            </label>

            <label>
                Cantitate ceara kg
                <input name="gross_kg" required inputmode="decimal" placeholder="12.500">
            </label>
            <label>
                Gestiune
                <input value="<?= h($assignedStore ? $assignedStore['name'] : 'Fara gestiune alocata') ?>" readonly>
            </label>
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
                <input value="0.00 lei" readonly data-processing-price>
            </label>
            <label>
                Scazamant %
                <input value="0.000" readonly data-processing-shrinkage>
            </label>
            <button class="primary" type="submit">Creeaza lot</button>
        </div>
    </form>
</section>
