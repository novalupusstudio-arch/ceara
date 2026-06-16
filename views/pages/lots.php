<?php
$selectedStatuses = array_fill_keys($data['selected_statuses'], true);
?>

<header class="page-header">
    <div>
        <span class="eyebrow">Loturi ceara</span>
        <h1>Situatie loturi</h1>
    </div>
</header>

<section class="panel">
    <form method="get" class="status-filter">
        <input type="hidden" name="page" value="lots">
        <div class="status-filter-row">
            <?php foreach ($data['all_statuses'] as $status): ?>
                <label class="status-toggle">
                    <input type="checkbox" name="status[]" value="<?= h($status) ?>" <?= isset($selectedStatuses[$status]) ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span><?= h($status) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </form>
</section>

<section class="panel">
    <div class="table-wrap">
        <table class="lot-board lot-summary-table">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Lot</th>
                    <th>Total primit</th>
                    <th>Ceara custodie</th>
                    <th>Ceara schimbata</th>
                    <th>Faguri predati</th>
                    <th>De procesat</th>
                    <th>Predat fabricii</th>
                    <th>Faguri fabrica</th>
                    <th>Refuzata</th>
                    <th>Returnata</th>
                    <th>De recuperat</th>
                    <th>De predat</th>
                    <th>Pierdere</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['lots'] as $summary): ?>
                    <?php $lot = $summary['lot']; ?>
                    <tr>
                        <td>
                            <strong><?= h($lot['customer_name']) ?></strong>
                            <span class="muted block"><?= h($lot['customer_type']) ?> - <?= h($lot['store_name']) ?></span>
                        </td>
                        <td>
                            <a class="table-link" href="index.php?page=lot_detail&amp;lot_id=<?= h((string) $lot['id']) ?>"><?= h($lot['lot_number']) ?></a>
                            <span class="muted block"><?= h(date('d.m.Y', strtotime($lot['created_at']))) ?></span>
                        </td>
                        <td><?= h(grams_to_kg((int) $summary['total_received_g'])) ?></td>
                        <td><?= h(grams_to_kg((int) $summary['wax_custody_g'])) ?></td>
                        <td><?= h(grams_to_kg((int) $summary['wax_exchanged_g'])) ?></td>
                        <td><?= h(grams_to_kg((int) $summary['foundation_delivered_g'])) ?></td>
                        <td><?= h(grams_to_kg((int) $summary['wax_to_factory_g'])) ?></td>
                        <td><?= h(grams_to_kg((int) $summary['wax_sent_factory_g'])) ?></td>
                        <td><?= h(grams_to_kg((int) $summary['foundation_received_factory_g'])) ?></td>
                        <td><?= h(grams_to_kg((int) $summary['wax_rejected_factory_g'])) ?></td>
                        <td><?= h(grams_to_kg((int) $summary['wax_returned_client_g'])) ?></td>
                        <td><?= h(grams_to_kg((int) $summary['foundation_to_recover_g'])) ?></td>
                        <td><?= h(grams_to_kg((int) $summary['foundation_to_client_g'])) ?></td>
                        <td><?= h(grams_to_kg((int) $summary['loss_g'])) ?></td>
                        <td><span class="status"><?= h($summary['calculated_status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['lots']): ?>
                    <tr>
                        <td colspan="15" class="empty">Nu exista loturi pentru filtrele curente.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
