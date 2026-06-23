<?php
$stores = $data['stores'] ?? [];
$adjustments = $data['adjustments'] ?? [];
$currentStock = (int) ($data['current_stock_g'] ?? 0);
?>

<header class="page-header">
    <div>
        <span class="eyebrow">Buffer fabrica</span>
        <h1>Avize buffer faguri</h1>
    </div>
</header>

<section class="kpi-grid compact">
    <article class="kpi">
        <span>Stoc operational curent</span>
        <strong><?= h(grams_to_kg($currentStock)) ?></strong>
    </article>
    <article class="kpi">
        <span>Avize inregistrate</span>
        <strong><?= h((string) count($adjustments)) ?></strong>
    </article>
</section>

<section class="panel">
    <h2>Aviz nou</h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="factory_buffer_adjustment">

        <label>
            Tip aviz
            <select name="adjustment_type">
                <option value="plus">Plus - suplimentare buffer</option>
                <option value="minus">Minus - reducere buffer</option>
            </select>
        </label>

        <label>
            Numar aviz fabrica
            <input name="aviz_number" required placeholder="AVZ-001">
        </label>

        <label>
            Cantitate faguri kg
            <input name="qty_kg" required inputmode="decimal" type="number" min="0.001" step="0.001" placeholder="10.000">
        </label>

        <label>
            Gestiune
            <select name="store_id">
                <?php foreach ($stores as $store): ?>
                    <option value="<?= h((string) $store['id']) ?>"><?= h($store['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="wide">
            Observatii
            <input name="notes" placeholder="Detalii aviz / motiv ajustare">
        </label>

        <button class="primary" type="submit">Inregistreaza aviz</button>
    </form>
</section>

<section class="panel">
    <h2>Istoric avize buffer</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tip</th>
                    <th>Aviz</th>
                    <th>NIR</th>
                    <th>Cantitate</th>
                    <th>Gestiune</th>
                    <th>User</th>
                    <th>Observatii</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adjustments as $adjustment): ?>
                    <tr>
                        <td><?= h(date('d.m.Y H:i', strtotime($adjustment['created_at']))) ?></td>
                        <td><span class="status"><?= h($adjustment['adjustment_type'] === 'plus' ? 'Plus' : 'Minus') ?></span></td>
                        <td><strong><?= h($adjustment['aviz_number']) ?></strong></td>
                        <td>
                            <?php if (!empty($adjustment['nir_document_id'])): ?>
                                <a class="table-link" href="index.php?page=document_mock&amp;document_id=<?= h((string) $adjustment['nir_document_id']) ?>" target="_blank" rel="noopener">
                                    <?= h($adjustment['nir_series'] . '-' . str_pad((string) max(1, (int) $adjustment['nir_number']), 4, '0', STR_PAD_LEFT)) ?>
                                </a>
                            <?php else: ?>
                                <span class="muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h(($adjustment['adjustment_type'] === 'minus' ? '-' : '+') . grams_to_kg((int) $adjustment['qty_g'])) ?></td>
                        <td><?= h($adjustment['store_name']) ?></td>
                        <td><?= h($adjustment['username']) ?></td>
                        <td><?= h($adjustment['notes'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$adjustments): ?>
                    <tr>
                        <td colspan="8" class="empty">Nu exista avize de buffer inregistrate.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
