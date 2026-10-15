<header class="page-header">
    <div>
        <span class="eyebrow">Rapoarte</span>
        <h1>Situatie operationala</h1>
    </div>
</header>

<section class="kpi-grid compact">
    <article class="kpi"><span>Ceara custodie</span><strong><?= h(grams_to_kg($data['dashboard']['wax_custody_g'])) ?></strong></article>
    <article class="kpi"><span>Ceara proprie</span><strong><?= h(grams_to_kg($data['dashboard']['wax_owned_g'])) ?></strong></article>
    <article class="kpi"><span>Faguri marfa</span><strong><?= h(grams_to_kg($data['dashboard']['foundation_merchandise_g'])) ?></strong></article>
    <article class="kpi"><span>Loturi in validare</span><strong><?= h((string) $data['dashboard']['pending_lots']) ?></strong></article>
</section>

<?php
$feeReport = $data['processing_fee_receipts'];
$feeTotals = $feeReport['totals'];
$feePagination = $feeReport['pagination'];
$pageUrl = static function (int $page) use ($feeReport): string {
    return '?' . http_build_query([
        'page' => 'reports',
        'date_start' => $feeReport['date_start'],
        'date_end' => $feeReport['date_end'],
        'register_page' => $page,
    ]);
};
?>

<section class="panel report-register">
    <div class="section-heading report-register-heading">
        <div>
            <span class="eyebrow">Registru de casa</span>
            <h2>Incasari taxa de procesare</h2>
            <p><?= h($feeReport['store']['name']) ?> · <?= h(date('d.m.Y', strtotime($feeReport['date_start']))) ?> - <?= h(date('d.m.Y', strtotime($feeReport['date_end']))) ?></p>
        </div>
        <div class="report-actions">
            <a class="button secondary" href="?page=reports&amp;period=previous_month">Luna anterioara</a>
            <a class="button" href="?<?= h(http_build_query([
                'page' => 'processing_fee_register_pdf',
                'date_start' => $feeReport['date_start'],
                'date_end' => $feeReport['date_end'],
            ])) ?>">Descarca PDF</a>
        </div>
    </div>

    <form method="get" class="date-filter-form">
        <input type="hidden" name="page" value="reports">
        <label>
            De la
            <input type="date" name="date_start" value="<?= h($feeReport['date_start']) ?>">
        </label>
        <label>
            Pana la
            <input type="date" name="date_end" value="<?= h($feeReport['date_end']) ?>">
        </label>
        <button type="submit">Afiseaza</button>
    </form>

    <div class="kpi-grid compact report-register-totals">
        <article class="kpi"><span>Bonuri</span><strong><?= h((string) $feeTotals['receipt_count']) ?></strong></article>
        <article class="kpi"><span>Numerar</span><strong><?= h(money($feeTotals['cash_cents'])) ?></strong></article>
        <article class="kpi"><span>Card</span><strong><?= h(money($feeTotals['card_cents'])) ?></strong></article>
        <article class="kpi"><span>Total fara TVA</span><strong><?= h(money($feeTotals['net_cents'])) ?></strong></article>
        <article class="kpi"><span>TVA <?= h((string) $feeReport['vat_rate']) ?>%</span><strong><?= h(money($feeTotals['vat_cents'])) ?></strong></article>
        <article class="kpi"><span>Total cu TVA</span><strong><?= h(money($feeTotals['total_cents'])) ?></strong></article>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Data</th>
                <th class="numeric">Bonuri</th>
                <th class="numeric">Numerar<br><small>TVA inclus</small></th>
                <th class="numeric">Card<br><small>TVA inclus</small></th>
                <th class="numeric">Total<br><small>fara TVA</small></th>
                <th class="numeric">TVA <?= h((string) $feeReport['vat_rate']) ?>%</th>
                <th class="numeric">Total<br><small>cu TVA</small></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$feeReport['rows']): ?>
                <tr><td colspan="7" class="empty-state">Nu exista incasari in intervalul selectat.</td></tr>
            <?php endif; ?>
            <?php foreach ($feeReport['rows'] as $row): ?>
                <tr>
                    <td><?= h(date('d.m.Y', strtotime($row['receipt_date']))) ?></td>
                    <td class="numeric"><?= h((string) $row['receipt_count']) ?></td>
                    <td class="numeric"><?= h(money((int) $row['cash_cents'])) ?></td>
                    <td class="numeric"><?= h(money((int) $row['card_cents'])) ?></td>
                    <td class="numeric"><?= h(money((int) $row['net_cents'])) ?></td>
                    <td class="numeric"><?= h(money((int) $row['vat_cents'])) ?></td>
                    <td class="numeric"><strong><?= h(money((int) $row['total_cents'])) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($feePagination['total_pages'] > 1): ?>
        <nav class="pagination" aria-label="Paginare registru incasari">
            <a class="button secondary <?= $feePagination['page'] <= 1 ? 'disabled' : '' ?>" href="<?= h($pageUrl(max(1, $feePagination['page'] - 1))) ?>">Anterior</a>
            <span>Pagina <?= h((string) $feePagination['page']) ?> din <?= h((string) $feePagination['total_pages']) ?> · 50 randuri pe pagina</span>
            <a class="button secondary <?= $feePagination['page'] >= $feePagination['total_pages'] ? 'disabled' : '' ?>" href="<?= h($pageUrl(min($feePagination['total_pages'], $feePagination['page'] + 1))) ?>">Urmator</a>
        </nav>
    <?php elseif ($feePagination['total_rows'] > 0): ?>
        <p class="table-summary"><?= h((string) $feePagination['total_rows']) ?> zile afisate · maximum 50 randuri pe pagina</p>
    <?php endif; ?>
</section>

<section class="two-column">
    <div class="panel">
        <h2>Procesare</h2>
        <?php
        $processingByStatus = array_count_values(array_map(fn ($lot) => $lot['status'], $data['processing']));
        ?>
        <ul class="report-list">
            <li><span>Loturi deschise</span><strong><?= h((string) count($data['processing'])) ?></strong></li>
            <li><span>Acceptate</span><strong><?= h((string) ($processingByStatus['Acceptat'] ?? 0)) ?></strong></li>
            <li><span>Respinse</span><strong><?= h((string) ($processingByStatus['Respins'] ?? 0)) ?></strong></li>
            <li><span>In validare</span><strong><?= h((string) ($processingByStatus['In Validare'] ?? 0)) ?></strong></li>
        </ul>
    </div>
    <div class="panel">
        <h2>Achizitie</h2>
        <?php
        $purchaseByType = array_count_values(array_map(fn ($lot) => $lot['supplier_type'], $data['purchases']));
        ?>
        <ul class="report-list">
            <li><span>Achizitii totale</span><strong><?= h((string) count($data['purchases'])) ?></strong></li>
            <li><span>PF</span><strong><?= h((string) ($purchaseByType['PF'] ?? 0)) ?></strong></li>
            <li><span>Producator agricol</span><strong><?= h((string) ($purchaseByType['Producator agricol'] ?? 0)) ?></strong></li>
            <li><span>PFA/SRL</span><strong><?= h((string) ($purchaseByType['PFA/SRL'] ?? 0)) ?></strong></li>
        </ul>
    </div>
</section>

