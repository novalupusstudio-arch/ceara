<?php
$activeFlow = $data['active_flow'] ?? '';
$flowTitle = match ($activeFlow) {
    'processing' => 'Flux procesare activ',
    'purchase' => 'Flux achizitie activ',
    default => 'Selectati flux',
};
?>

<header class="page-header">
    <div>
        <span class="eyebrow">Dashboard</span>
        <h1>Operatiuni ceara</h1>
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
        <span>Ceara proprie</span>
        <strong><?= h(grams_to_kg($data['wax_owned_g'])) ?></strong>
    </article>
</section>

<section class="panel">
    <h2><?= h($flowTitle) ?></h2>
    <div class="flow-selector" role="radiogroup" aria-label="Selectie flux de lucru">
        <form method="post">
            <input type="hidden" name="action" value="select_flow">
            <input type="hidden" name="flow" value="processing">
            <button class="flow-choice <?= $activeFlow === 'processing' ? 'is-active' : '' ?>" type="submit" role="radio" aria-checked="<?= $activeFlow === 'processing' ? 'true' : 'false' ?>">
                <strong>Schimb de ceara</strong>
                <span>Procesare ceara, loturi, predare fabrica si buffer fabrica.</span>
            </button>
        </form>

        <button class="flow-choice is-disabled" type="button" role="radio" aria-checked="false" aria-disabled="true">
            <strong>Achizitie ceara</strong>
            <span>Flux separat, cu stocuri proprii. Va fi reconstruit de la zero.</span>
        </button>
    </div>
</section>
