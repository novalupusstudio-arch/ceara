<header class="page-header">
    <div>
        <span class="eyebrow">Dashboard</span>
        <h1>Operatiuni ceara</h1>
    </div>
    <div class="header-actions">
        <a class="button primary" href="index.php?page=processing">Procesare ceara</a>
        <a class="button secondary" href="index.php?page=purchases">Achizitie ceara</a>
    </div>
</header>

<section class="kpi-grid">
    <article class="kpi">
        <span>Stoc faguri operational</span>
        <strong><?= h(grams_to_kg($data['foundation_operational_g'])) ?></strong>
    </article>
    <article class="kpi">
        <span>Ceara in custodie</span>
        <strong><?= h(grams_to_kg($data['wax_custody_g'])) ?></strong>
    </article>
    <article class="kpi">
        <span>Loturi in validare</span>
        <strong><?= h((string) $data['pending_lots']) ?></strong>
    </article>
    <article class="kpi">
        <span>Loturi respinse</span>
        <strong><?= h((string) $data['rejected_lots']) ?></strong>
    </article>
    <article class="kpi">
        <span>Stoc ceara proprie</span>
        <strong><?= h(grams_to_kg($data['wax_owned_g'])) ?></strong>
    </article>
    <article class="kpi">
        <span>Stoc faguri marfa</span>
        <strong><?= h(grams_to_kg($data['foundation_merchandise_g'])) ?></strong>
    </article>
</section>

<section class="panel">
    <h2>Fluxuri active</h2>
    <div class="flow-grid">
        <a class="flow-link" href="index.php?page=processing">
            <strong>Procesare ceara</strong>
            <span>PV custodie, acceptare, respingere, predare fabricii, documente mock.</span>
        </a>
        <a class="flow-link" href="index.php?page=purchases">
            <strong>Achizitie ceara</strong>
            <span>Borderou/factura, NIR, predare procesator, receptie faguri.</span>
        </a>
    </div>
</section>

