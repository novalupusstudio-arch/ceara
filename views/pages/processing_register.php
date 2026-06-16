<?php
$store = $data['store'];
$rows = $data['rows'];

$formatSignedKg = static function (int $grams): string {
    $prefix = $grams > 0 ? '+' : '';
    return $prefix . grams_to_kg($grams);
};

$waxTotal = 0;
$foundationTotal = 0;
foreach ($rows as $row) {
    $waxTotal += (int) $row['wax_g'];
    $foundationTotal += (int) $row['foundation_g'];
}
?>

<header class="page-header">
    <div>
        <span class="eyebrow">Registru gestiune</span>
        <h1>Procesare - <?= h($store['name']) ?></h1>
    </div>
</header>

<section class="kpi-grid">
    <article class="kpi">
        <span>Ceara in gestiune</span>
        <strong><?= h(grams_to_kg((int) $data['wax_total_g'])) ?></strong>
    </article>
    <article class="kpi">
        <span>Faguri in gestiune</span>
        <strong><?= h(grams_to_kg((int) $data['foundation_total_g'])) ?></strong>
    </article>
    <article class="kpi">
        <span>Procesator asignat</span>
        <strong><?= h($store['processor_name'] ?: '-') ?></strong>
    </article>
</section>

<section class="panel">
    <h2>Registru procesare</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Partner</th>
                    <th>Lot</th>
                    <th>Document</th>
                    <th>Data</th>
                    <th>Ceara in custodie</th>
                    <th>Faguri in custodie</th>
                    <th>Operator</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= h($row['partner']) ?></td>
                        <td>
                            <?php if (!empty($row['lot_url']) && !empty($row['lot_number'])): ?>
                                <a class="table-link" href="<?= h($row['lot_url']) ?>"><?= h($row['lot_number']) ?></a>
                            <?php else: ?>
                                <span class="muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($row['document']['url'])): ?>
                                <a class="table-link" href="<?= h($row['document']['url']) ?>" target="_blank" rel="noopener"><?= h($row['document']['label']) ?></a>
                            <?php else: ?>
                                <strong><?= h($row['document']['label'] ?? '-') ?></strong>
                            <?php endif; ?>
                        </td>
                        <td><?= h(date('d.m.Y H:i', strtotime($row['created_at']))) ?></td>
                        <td><?= h($formatSignedKg((int) $row['wax_g'])) ?></td>
                        <td><?= h($formatSignedKg((int) $row['foundation_g'])) ?></td>
                        <td><?= h($row['operator']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr>
                        <td colspan="7" class="empty">Nu exista miscari in registrul gestiunii curente.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4">Total miscari afisate</th>
                    <th><?= h($formatSignedKg($waxTotal)) ?></th>
                    <th><?= h($formatSignedKg($foundationTotal)) ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</section>
