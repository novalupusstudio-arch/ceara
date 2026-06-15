<?php
$selectedProcessor = $data['selected_processor'] ?? null;
$selectedProcessorId = $selectedProcessor ? (int) $selectedProcessor['id'] : 0;
$priceCents = $selectedProcessor ? (int) $selectedProcessor['processing_price_cents'] : 0;
$shrinkagePct = $selectedProcessor ? (float) $selectedProcessor['exchange_shrinkage_pct'] : 0.0;
?>

<header class="page-header">
    <div>
        <span class="eyebrow">Predare fabrica</span>
        <h1>Predare ceara la procesator</h1>
    </div>
</header>

<section class="panel">
    <form method="get" class="factory-topbar">
        <input type="hidden" name="page" value="factory_delivery">
        <label class="factory-processor">
            Procesator
            <select name="processor_id" onchange="this.form.submit()">
                <?php foreach ($data['processors'] as $processor): ?>
                    <option value="<?= h((string) $processor['id']) ?>" <?= (int) $processor['id'] === $selectedProcessorId ? 'selected' : '' ?>>
                        <?= h($processor['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
</section>

<section class="panel">
    <form method="post" class="factory-form" data-factory-form data-price-cents="<?= h((string) $priceCents) ?>" data-shrinkage-pct="<?= h((string) $shrinkagePct) ?>">
        <input type="hidden" name="action" value="create_factory_batch">
        <input type="hidden" name="processor_id" value="<?= h((string) $selectedProcessorId) ?>">

        <div class="factory-totals">
            <label>
                Total ceara de predat
                <input value="0.000 kg" readonly data-factory-total-wax>
            </label>
            <label>
                Cost procesare
                <input value="0.00 lei" readonly data-factory-total-cost>
            </label>
            <label>
                Faguri de primit
                <input value="0.000 kg" readonly data-factory-total-foundation>
            </label>
        </div>

        <div class="table-wrap">
            <table class="factory-table">
                <thead>
                    <tr>
                        <th>Nume client</th>
                        <th>Numar lot</th>
                        <th>Status</th>
                        <th>Ceara lot</th>
                        <th>Predare fabrica</th>
                        <th>Cost</th>
                        <th>Faguri</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['lots'] as $row): ?>
                        <?php $lot = $row['lot']; ?>
                        <tr data-factory-row data-price-cents="<?= h((string) $priceCents) ?>" data-shrinkage-pct="<?= h((string) $shrinkagePct) ?>">
                            <td>
                                <strong><?= h($lot['customer_name']) ?></strong>
                                <span class="muted block"><?= h($lot['customer_type']) ?> · <?= h($lot['store_name']) ?></span>
                            </td>
                            <td>
                                <strong><?= h($lot['lot_number']) ?></strong>
                                <span class="muted block">Ramas <?= h(grams_to_kg((int) $row['remaining_g'])) ?></span>
                            </td>
                            <td><span class="status"><?= h($lot['status']) ?></span></td>
                            <td>
                                <span class="muted block"><?= h(grams_to_kg((int) $lot['gross_g'])) ?></span>
                                <span class="muted block">Trimis pana acum: <?= h(grams_to_kg((int) $lot['factory_sent_g'])) ?></span>
                            </td>
                            <td>
                                <label class="factory-qty">
                                    <span class="sr-only">Cantitate de predat</span>
                                    <input type="number" step="0.001" min="0" max="<?= h(number_format(((int) $row['remaining_g']) / 1000, 3, '.', '')) ?>" name="lot_qty[<?= h((string) $lot['id']) ?>]" value="<?= h(number_format(((int) $row['selected_g']) / 1000, 3, '.', '')) ?>" data-factory-qty>
                                </label>
                            </td>
                            <td>
                                <span class="row-cost" data-row-cost><?= h(money((int) $row['cost_cents'])) ?></span>
                            </td>
                            <td>
                                <span class="row-foundation" data-row-foundation><?= h(grams_to_kg((int) $row['foundation_g'])) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$data['lots']): ?>
                        <tr>
                            <td colspan="7" class="empty">Nu exista loturi in validare sau acceptate pentru procesatorul selectat.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="panel-actions">
            <button class="primary" type="submit">Preda ceara</button>
        </div>
    </form>
</section>
