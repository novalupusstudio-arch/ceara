<header class="page-header">
    <div>
        <span class="eyebrow">Achizitie</span>
        <h1>Iesire ceara achizitionata</h1>
    </div>
</header>

<section class="kpi-grid">
    <article class="kpi">
        <span>Stoc disponibil</span>
        <strong><?= h(grams_to_kg((int) $data['stock_g'])) ?></strong>
    </article>
    <article class="kpi">
        <span>Gestiune</span>
        <strong><?= h((string) ($data['store']['name'] ?? '-')) ?></strong>
    </article>
</section>

<section class="panel">
    <h2>Iesire noua</h2>
    <form method="post" class="processing-form">
        <input type="hidden" name="action" value="purchase_wax_exit">
        <div class="processing-fields">
            <div class="processing-row columns-3">
                <label>
                    Fabrica / partener
                    <input name="partner_name" required placeholder="Denumire partener">
                </label>
                <label>
                    CUI / identificator
                    <input name="partner_identifier" placeholder="RO123456">
                </label>
                <label>
                    Cantitate iesire kg
                    <input name="qty_kg" required inputmode="decimal" placeholder="10.000">
                </label>
            </div>

            <div class="processing-row columns-4">
                <label>
                    Tip document
                    <input name="document_type" required placeholder="Aviz / factura">
                </label>
                <label>
                    Serie document
                    <input name="document_series" placeholder="STU">
                </label>
                <label>
                    Numar document
                    <input name="document_number" required placeholder="123">
                </label>
                <label>
                    Data document
                    <input type="date" name="document_date" value="<?= h(date('Y-m-d')) ?>">
                </label>
            </div>

            <div class="processing-row columns-3">
                <label>
                    Stoc curent
                    <input value="<?= h(grams_to_kg((int) $data['stock_g'])) ?>" readonly>
                </label>
                <label class="wide">
                    Observatii
                    <input name="notes" placeholder="Detalii iesire">
                </label>
                <div class="form-actions">
                    <button class="primary" type="submit">Salveaza iesire</button>
                </div>
            </div>
        </div>
    </form>
</section>

<section class="panel">
    <h2>Ultimele iesiri</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Numar intern</th>
                    <th>Partener</th>
                    <th>Document</th>
                    <th>Cantitate</th>
                    <th>Data</th>
                    <th>Operator</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['exits'] as $exit): ?>
                    <tr>
                        <td><?= h($exit['exit_number']) ?></td>
                        <td><?= h($exit['partner_name']) ?></td>
                        <td><?= h(trim($exit['document_series'] . '-' . $exit['document_number'], '-')) ?></td>
                        <td>-<?= h(grams_to_kg((int) $exit['qty_g'])) ?></td>
                        <td><?= h(date('d.m.Y H:i', strtotime($exit['created_at']))) ?></td>
                        <td><?= h($exit['username']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['exits']): ?>
                    <tr><td colspan="6" class="empty">Nu exista iesiri inregistrate.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
