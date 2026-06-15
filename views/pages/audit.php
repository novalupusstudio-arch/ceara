<header class="page-header">
    <div>
        <span class="eyebrow">Audit</span>
        <h1>Jurnal operatiuni</h1>
    </div>
</header>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>User</th>
                    <th>Operatiune</th>
                    <th>Entitate</th>
                    <th>Valoare veche</th>
                    <th>Valoare noua</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['entries'] as $entry): ?>
                    <tr>
                        <td><?= h($entry['created_at']) ?></td>
                        <td><?= h($entry['username'] ?? '-') ?></td>
                        <td><?= h($entry['operation']) ?></td>
                        <td><?= h($entry['entity']) ?> <?= $entry['entity_id'] ? '#' . h((string) $entry['entity_id']) : '' ?></td>
                        <td><?= h($entry['old_value']) ?></td>
                        <td><?= h($entry['new_value']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['entries']): ?>
                    <tr><td colspan="6" class="empty">Nu exista intrari de audit.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

