<header class="page-header">
    <div>
        <span class="eyebrow">Achizitie</span>
        <h1>Achizitii ceara</h1>
    </div>
</header>

<section class="panel">
    <h2>Achizitie noua</h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="create_purchase">
        <label>
            Furnizor
            <input name="supplier_name" required placeholder="Nume furnizor">
        </label>
        <label>
            Tip furnizor
            <select name="supplier_type" required>
                <option value="PF">PF</option>
                <option value="Producator agricol">Producator agricol</option>
                <option value="PFA/SRL">PFA/SRL</option>
            </select>
        </label>
        <label>
            CUI/CNP/CI
            <input name="supplier_cui" placeholder="Optional">
        </label>
        <label>
            Cantitate kg
            <input name="gross_kg" required inputmode="decimal" placeholder="20.000">
        </label>
        <label>
            Scazamant %
            <input name="shrinkage_pct" required inputmode="decimal" value="0">
        </label>
        <label>
            Gestiune
            <select name="store_id" required>
                <?php foreach ($data['stores'] as $store): ?>
                    <option value="<?= h((string) $store['id']) ?>"><?= h($store['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Procesator
            <select name="processor_id">
                <?php foreach ($data['processors'] as $processor): ?>
                    <option value="<?= h((string) $processor['id']) ?>"><?= h($processor['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="primary" type="submit">Creeaza achizitie</button>
    </form>
</section>

<section class="panel">
    <h2>Lista achizitii</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Lot</th>
                    <th>Furnizor</th>
                    <th>Tip</th>
                    <th>Status</th>
                    <th>Ceara</th>
                    <th>Faguri estimati</th>
                    <th>Actiuni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['lots'] as $lot): ?>
                    <tr>
                        <td><?= h($lot['lot_number']) ?></td>
                        <td><?= h($lot['supplier_name']) ?></td>
                        <td><?= h($lot['supplier_type']) ?></td>
                        <td><span class="status"><?= h($lot['status']) ?></span></td>
                        <td><?= h(grams_to_kg((int) $lot['gross_g'])) ?></td>
                        <td><?= h(grams_to_kg((int) $lot['foundation_g'])) ?></td>
                        <td>
                            <?php if ($lot['status'] !== 'Inchis'): ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="advance_purchase">
                                    <input type="hidden" name="lot_id" value="<?= h((string) $lot['id']) ?>">
                                    <button class="small" type="submit">Avanseaza</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['lots']): ?>
                    <tr><td colspan="7" class="empty">Nu exista achizitii inca.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

