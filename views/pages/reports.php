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

