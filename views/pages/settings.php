<?php
$store = $data['stores'][0] ?? null;
$processor = $data['processors'][0] ?? null;
?>

<header class="page-header">
    <div>
        <span class="eyebrow">Administrare</span>
        <h1>Setari MVP</h1>
    </div>
</header>

<form method="post" class="settings-form">
    <input type="hidden" name="action" value="save_settings">
    <input type="hidden" name="store_id" value="<?= h((string) $store['id']) ?>">
    <input type="hidden" name="processor_id" value="<?= h((string) $processor['id']) ?>">

    <section class="panel">
        <h2>Gestiune implicita</h2>
        <div class="form-grid">
            <label>Cod <input name="store_code" value="<?= h($store['code']) ?>" required></label>
            <label>Denumire <input name="store_name" value="<?= h($store['name']) ?>" required></label>
            <label class="wide">Adresa <input name="store_address" value="<?= h($store['address']) ?>"></label>
        </div>
    </section>

    <section class="panel">
        <h2>Procesator implicit</h2>
        <div class="form-grid">
            <label>Denumire <input name="processor_name" value="<?= h($processor['name']) ?>" required></label>
            <label>CUI <input name="processor_cui" value="<?= h($processor['cui']) ?>"></label>
            <label>Contact <input name="processor_contact" value="<?= h($processor['contact']) ?>"></label>
            <label>Pret procesare <input name="processing_price" inputmode="decimal" value="<?= h((string) ($processor['processing_price_cents'] / 100)) ?>"></label>
            <label>Scazamant schimb % <input name="exchange_shrinkage_pct" inputmode="decimal" value="<?= h((string) $processor['exchange_shrinkage_pct']) ?>"></label>
            <label>Scazamant achizitie % <input name="purchase_shrinkage_pct" inputmode="decimal" value="<?= h((string) $processor['purchase_shrinkage_pct']) ?>"></label>
        </div>
    </section>

    <section class="panel">
        <h2>Serii documente</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Tip</th><th>Serie</th><th>Urmatorul numar</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($data['series'] as $series): ?>
                        <tr>
                            <td><?= h($series['document_type']) ?></td>
                            <td><input name="series[<?= h((string) $series['id']) ?>][series]" value="<?= h($series['series']) ?>"></td>
                            <td><input name="series[<?= h((string) $series['id']) ?>][next_number]" inputmode="numeric" value="<?= h((string) $series['next_number']) ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <button class="primary" type="submit">Salveaza setarile</button>
</form>

