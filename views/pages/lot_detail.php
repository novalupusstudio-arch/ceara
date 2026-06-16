<?php
$summary = $data['summary'];
$lot = $summary['lot'];
$documents = $data['documents'] ?? [];

$movementLabels = [
    'RECEIVE_WAX_FROM_CLIENT' => 'Primire ceara client',
    'EXCHANGE_WAX_WITH_CLIENT' => 'Schimb ceara client',
    'RETURN_WAX_TO_CLIENT' => 'Retur ceara client',
    'SEND_WAX_TO_FACTORY' => 'Predare fabrica',
    'RECEIVE_FOUNDATION_FROM_FACTORY' => 'Receptie faguri fabrica',
    'FACTORY_REJECT_WAX' => 'Refuz fabrica',
    'RECORD_LOSS' => 'Pierdere',
    'RECOVER_FOUNDATION_FROM_CLIENT' => 'Recuperare faguri client',
];

$docButton = static function (array $lot, int $movementId, string $type, string $label, array $documents): void {
    $key = $movementId . ':' . $type;
    $exists = !empty($documents[$key]);
    $buttonLabel = ($exists ? 'Print ' : 'Genereaza ') . $label;
    ?>
    <form method="post" class="inline-form">
        <input type="hidden" name="action" value="processing_document">
        <input type="hidden" name="lot_id" value="<?= h((string) $lot['id']) ?>">
        <input type="hidden" name="movement_id" value="<?= h((string) $movementId) ?>">
        <input type="hidden" name="document_type" value="<?= h($type) ?>">
        <button class="secondary compact" type="submit"><?= h($buttonLabel) ?></button>
    </form>
    <?php
};
?>

<header class="page-header">
    <div>
        <span class="eyebrow">Detaliu lot</span>
        <h1><?= h($lot['lot_number']) ?></h1>
    </div>
    <a class="button secondary" href="index.php?page=lots">Inapoi la loturi</a>
</header>

<section class="panel">
    <div class="lot-detail-header">
        <div>
            <span class="muted block">Client</span>
            <strong><?= h($lot['customer_name']) ?></strong>
            <span class="muted block"><?= h($lot['customer_type']) ?></span>
        </div>
        <div>
            <span class="muted block">Gestiune</span>
            <strong><?= h($lot['store_name']) ?></strong>
        </div>
        <div>
            <span class="muted block">Procesator implicit</span>
            <strong><?= h($lot['processor_name'] ?: '-') ?></strong>
        </div>
        <div>
            <span class="muted block">Creat</span>
            <strong><?= h(date('d.m.Y H:i', strtotime($lot['created_at']))) ?></strong>
        </div>
        <div>
            <span class="muted block">Cantitate initiala</span>
            <strong><?= h(grams_to_kg((int) $lot['gross_g'])) ?></strong>
        </div>
        <div>
            <span class="muted block">Scazamant</span>
            <strong><?= h(number_format((float) $lot['shrinkage_pct'], 3, '.', '')) ?>%</strong>
        </div>
        <div>
            <span class="muted block">Pret procesare</span>
            <strong><?= h(money((int) $lot['processing_price_cents'])) ?></strong>
        </div>
        <div>
            <span class="muted block">Status calculat</span>
            <strong><?= h($summary['calculated_status']) ?></strong>
        </div>
    </div>
</section>

<section class="panel">
    <h2>Situatie curenta lot</h2>
    <div class="lot-metric-grid">
        <div><span>Total ceara primita</span><strong><?= h(grams_to_kg((int) $summary['total_received_g'])) ?></strong></div>
        <div><span>Ceara in custodie</span><strong><?= h(grams_to_kg((int) $summary['wax_custody_g'])) ?></strong></div>
        <div><span>Ceara schimbata client</span><strong><?= h(grams_to_kg((int) $summary['wax_exchanged_g'])) ?></strong></div>
        <div><span>Faguri predati client</span><strong><?= h(grams_to_kg((int) $summary['foundation_delivered_g'])) ?></strong></div>
        <div><span>Ceara de procesat</span><strong><?= h(grams_to_kg((int) $summary['wax_to_factory_g'])) ?></strong></div>
        <div><span>Ceara predat fabricii</span><strong><?= h(grams_to_kg((int) $summary['wax_sent_factory_g'])) ?></strong></div>
        <div><span>Faguri primiti fabrica</span><strong><?= h(grams_to_kg((int) $summary['foundation_received_factory_g'])) ?></strong></div>
        <div><span>Ceara refuzata fabrica</span><strong><?= h(grams_to_kg((int) $summary['wax_rejected_factory_g'])) ?></strong></div>
        <div><span>Ceara returnata client</span><strong><?= h(grams_to_kg((int) $summary['wax_returned_client_g'])) ?></strong></div>
        <div><span>Faguri de recuperat</span><strong><?= h(grams_to_kg((int) $summary['foundation_to_recover_g'])) ?></strong></div>
        <div><span>Faguri de predat</span><strong><?= h(grams_to_kg((int) $summary['foundation_to_client_g'])) ?></strong></div>
        <div><span>Pierdere</span><strong><?= h(grams_to_kg((int) $summary['loss_g'])) ?></strong></div>
    </div>
</section>

<section class="panel">
    <h2>Operatii ceara</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tip operatie</th>
                    <th>Ceara</th>
                    <th>Faguri</th>
                    <th>Valoare serviciu</th>
                    <th>Documente</th>
                    <th>User</th>
                    <th>Observatii</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['movements'] as $movement): ?>
                    <tr>
                        <td><?= h(date('d.m.Y H:i', strtotime($movement['created_at']))) ?></td>
                        <td><?= h($movementLabels[$movement['movement_type']] ?? $movement['movement_type']) ?></td>
                        <td><?= h(grams_to_kg((int) $movement['wax_g'])) ?></td>
                        <td><?= h(grams_to_kg((int) $movement['foundation_g'])) ?></td>
                        <td><?= h(money((int) $movement['service_value_cents'])) ?></td>
                        <td>
                            <div class="doc-actions">
                                <?php if ($movement['movement_type'] === 'RECEIVE_WAX_FROM_CLIENT'): ?>
                                    <?php $docButton($lot, (int) $movement['id'], 'PV-CUST', 'PV primire', $documents); ?>
                                <?php endif; ?>
                                <?php if ($movement['movement_type'] === 'EXCHANGE_WAX_WITH_CLIENT'): ?>
                                    <?php $docButton($lot, (int) $movement['id'], 'FACT', 'Factura', $documents); ?>
                                    <?php $docButton($lot, (int) $movement['id'], 'BON', 'Bon', $documents); ?>
                                    <?php $docButton($lot, (int) $movement['id'], 'PV-FAG', 'PV Faguri', $documents); ?>
                                <?php endif; ?>
                                <?php if ($movement['movement_type'] === 'RETURN_WAX_TO_CLIENT'): ?>
                                    <?php $docButton($lot, (int) $movement['id'], 'PV-RET', 'PV retur', $documents); ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= h($movement['username']) ?></td>
                        <td><?= h($movement['notes'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['movements']): ?>
                    <tr><td colspan="8" class="empty">Nu exista operatii pentru acest lot.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="flow-grid">
    <div class="panel">
        <h2>Schimb ceara</h2>
        <form
            method="post"
            class="form-grid compact-form"
            data-exchange-form
            data-max-wax-kg="<?= h(number_format(((int) $summary['wax_available_for_exchange_g']) / 1000, 3, '.', '')) ?>"
            data-price-cents="<?= h((string) (int) $lot['processing_price_cents']) ?>"
            data-shrinkage-pct="<?= h((string) (float) $lot['shrinkage_pct']) ?>"
        >
            <input type="hidden" name="action" value="processing_exchange">
            <input type="hidden" name="lot_id" value="<?= h((string) $lot['id']) ?>">
            <label>
                Cantitate ceara de schimbat kg
                <input
                    name="exchange_kg"
                    required
                    inputmode="decimal"
                    type="number"
                    min="0"
                    max="<?= h(number_format(((int) $summary['wax_available_for_exchange_g']) / 1000, 3, '.', '')) ?>"
                    step="0.001"
                    placeholder="1.000"
                    data-exchange-qty
                >
            </label>
            <label>
                Ceara neschimbata
                <input value="<?= h(grams_to_kg((int) $summary['wax_available_for_exchange_g'])) ?>" readonly data-exchange-available>
            </label>
            <label>
                Faguri de schimbat
                <input value="0.000 kg" readonly data-exchange-foundation>
            </label>
            <label>
                Valoare manopera
                <input value="0.00 lei" readonly data-exchange-service>
            </label>
            <label>
                Stoc faguri operational
                <input value="<?= h(grams_to_kg((int) $data['foundation_stock_g'])) ?>" readonly data-exchange-stock>
            </label>
            <button class="primary" type="submit">Confirma schimb</button>
        </form>
    </div>

    <div class="panel">
        <h2>Retur ceara</h2>
        <form method="post" class="form-grid compact-form">
            <input type="hidden" name="action" value="processing_return">
            <input type="hidden" name="lot_id" value="<?= h((string) $lot['id']) ?>">
            <label>
                Cantitate ceara de returnat kg
                <input
                    name="return_kg"
                    required
                    inputmode="decimal"
                    type="number"
                    min="0"
                    max="<?= h(number_format(((int) $summary['wax_custody_g']) / 1000, 3, '.', '')) ?>"
                    step="0.001"
                    placeholder="1.000"
                >
            </label>
            <label>
                Ceara in custodie
                <input value="<?= h(grams_to_kg((int) $summary['wax_custody_g'])) ?>" readonly>
            </label>
            <label class="wide">
                Motiv / observatii
                <input name="return_notes" placeholder="Motiv retur">
            </label>
            <button class="primary" type="submit">Confirma retur</button>
        </form>
    </div>
</section>
