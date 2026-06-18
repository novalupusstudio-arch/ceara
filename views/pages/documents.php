<header class="page-header">
    <div>
        <span class="eyebrow">Mock fiscal</span>
        <h1>Documente generate</h1>
    </div>
</header>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Document</th>
                    <th>Gestiune</th>
                    <th>Referinta</th>
                    <th>Status</th>
                    <th>Note</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['documents'] as $doc): ?>
                    <tr>
                        <td>
                            <a class="table-link" href="<?= h($doc['url']) ?>" target="_blank" rel="noopener"><?= h($doc['label']) ?></a><br>
                            <span class="muted"><?= h($doc['document_type']) ?></span>
                        </td>
                        <td><?= h($doc['store_name']) ?></td>
                        <td><?= h($doc['reference_type']) ?> #<?= h((string) $doc['reference_id']) ?></td>
                        <td><span class="status"><?= h($doc['status']) ?></span></td>
                        <td><?= h($doc['notes']) ?></td>
                        <td><?= h($doc['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['documents']): ?>
                    <tr><td colspan="6" class="empty">Nu exista documente generate.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

