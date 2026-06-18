<?php
$rows = $data['rows'];
$formatSignedKg = static function (int $grams): string {
    return ($grams > 0 ? '+' : '') . grams_to_kg($grams);
};
?>

<header class="page-header">
    <div>
        <span class="eyebrow">Registru gestiune</span>
        <h1>Achizitie - <?= h($data['store']['name']) ?></h1>
    </div>
</header>

<section class="kpi-grid">
    <article class="kpi">
        <span>Stoc ceara achizitionata</span>
        <strong><?= h(grams_to_kg((int) $data['stock_g'])) ?></strong>
    </article>
    <article class="kpi">
        <span>Sold inceput perioada</span>
        <strong><?= h(grams_to_kg((int) $data['opening_g'])) ?></strong>
    </article>
    <article class="kpi">
        <span>Sold final perioada</span>
        <strong><?= h(grams_to_kg((int) $data['closing_g'])) ?></strong>
    </article>
</section>

<section class="panel">
    <div class="panel-heading-row">
        <h2>Registru achizitie</h2>
        <form method="get" class="date-filter-form">
            <input type="hidden" name="page" value="purchase_register">
            <label>
                Data start
                <input type="date" name="date_start" value="<?= h($data['date_start']) ?>">
            </label>
            <label>
                Data final
                <input type="date" name="date_end" value="<?= h($data['date_end']) ?>">
            </label>
            <button class="secondary compact" type="submit">Filtreaza</button>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Partener</th>
                    <th>Document</th>
                    <th>Pozitie</th>
                    <th>Data</th>
                    <th>Ceara achizitionata</th>
                    <th>Operator</th>
                    <th>Observatii</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= h($row['partner']) ?></td>
                        <td><?= h($row['document']) ?></td>
                        <td><?= h($row['position'] ?: '-') ?></td>
                        <td><?= h(date('d.m.Y H:i', strtotime($row['created_at']))) ?></td>
                        <td><?= h($formatSignedKg((int) $row['qty_g'])) ?></td>
                        <td><?= h($row['operator']) ?></td>
                        <td><?= h($row['notes'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="empty">Nu exista miscari in registrul de achizitie.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>Loturi achizitie</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Lot</th>
                    <th>Data</th>
                    <th>Furnizor</th>
                    <th>Document</th>
                    <th>Cantitate</th>
                    <th>Pret</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($data['lots'] ?? []) as $lot): ?>
                    <tr>
                        <td><?= h($lot['lot_number']) ?></td>
                        <td><?= h(date('d.m.Y', strtotime($lot['purchase_date'] ?: $lot['created_at']))) ?></td>
                        <td><?= h($lot['supplier_name']) ?></td>
                        <td><?= h(trim($lot['external_document_series'] . '-' . $lot['external_document_number'], '-')) ?></td>
                        <td><?= h(grams_to_kg((int) $lot['gross_g'])) ?></td>
                        <td><?= h(money((int) $lot['purchase_price_cents_per_kg'])) ?>/kg</td>
                        <td><?= h(money((int) $lot['total_amount_cents'])) ?></td>
                        <td><span class="status"><?= h($lot['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
