<header class="page-header">
    <div>
        <span class="eyebrow">Procesare</span>
        <h1>Loturi procesare ceara</h1>
    </div>
</header>

<section class="panel">
    <h2>Lot nou</h2>
    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="create_processing">
        <label>
            Client
            <input name="customer_name" required placeholder="Nume client">
        </label>
        <label>
            Telefon
            <input name="customer_phone" placeholder="Optional">
        </label>
        <label>
            Cantitate ceara kg
            <input name="gross_kg" required inputmode="decimal" placeholder="12.500">
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
        <label class="check-line">
            <input type="checkbox" name="known_customer" checked>
            Client cunoscut, accepta direct lotul
        </label>
        <button class="primary" type="submit">Creeaza lot</button>
    </form>
</section>

<section class="panel">
    <h2>Lista loturi</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Lot</th>
                    <th>Client</th>
                    <th>Status</th>
                    <th>Ceara</th>
                    <th>Faguri calculati</th>
                    <th>Gestiune</th>
                    <th>Actiuni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['lots'] as $lot): ?>
                    <tr>
                        <td><?= h($lot['lot_number']) ?></td>
                        <td><?= h($lot['customer_name']) ?></td>
                        <td><span class="status"><?= h($lot['status']) ?></span></td>
                        <td><?= h(grams_to_kg((int) $lot['gross_g'])) ?></td>
                        <td><?= h(grams_to_kg((int) $lot['foundation_g'])) ?></td>
                        <td><?= h($lot['store_name']) ?></td>
                        <td class="actions-cell">
                            <?php
                            $actions = [
                                'In Validare' => ['accept' => 'Accepta', 'reject' => 'Respinge'],
                                'Acceptat' => ['send_factory' => 'Preda fabricii'],
                                'Respins' => ['return' => 'Returneaza'],
                            ][$lot['status']] ?? [];
                            ?>
                            <?php foreach ($actions as $transition => $label): ?>
                                <form method="post">
                                    <input type="hidden" name="action" value="transition_processing">
                                    <input type="hidden" name="lot_id" value="<?= h((string) $lot['id']) ?>">
                                    <input type="hidden" name="transition" value="<?= h($transition) ?>">
                                    <button class="small" type="submit"><?= h($label) ?></button>
                                </form>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['lots']): ?>
                    <tr><td colspan="7" class="empty">Nu exista loturi inca.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

