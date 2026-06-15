<?php
$selectedStatuses = array_fill_keys($data['selected_statuses'], true);
$statusTimeline = $data['timeline'] ?? [];

$formatTime = static function (?string $value): string {
    return $value ? date('d.m.Y H:i', strtotime($value)) : '—';
};

$docSvg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 2h8l4 4v16H6z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M14 2v6h6" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 11h8M8 15h8M8 19h5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$checkSvg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 12l5 5L20 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
$banSvg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="8.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8.5 8.5l7 7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
$backSvg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M19 12H7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M11 7l-5 5 5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>

<header class="page-header">
    <div>
        <span class="eyebrow">Loturi ceara</span>
        <h1>Flux loturi ceara</h1>
    </div>
</header>

<section class="panel">
    <div class="status-filter" data-lot-filter>
        <div class="status-filter-row">
            <?php foreach ($data['all_statuses'] as $status): ?>
                <label class="status-toggle">
                    <input type="checkbox" name="status[]" value="<?= h($status) ?>" <?= isset($selectedStatuses[$status]) ? 'checked' : '' ?>>
                    <span><?= h($status) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="panel">
    <div class="table-wrap">
        <table class="lot-board" data-lot-board>
            <thead>
                <tr>
                    <th>Nume client</th>
                    <th>Numar lot</th>
                    <th>In validare</th>
                    <th>Acceptat</th>
                    <th>Predat fabricii</th>
                    <th>Respins</th>
                    <th>Returnat</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['lots'] as $lot): ?>
                    <?php $timeline = $statusTimeline[(int) $lot['id']] ?? []; ?>
                    <tr data-lot-status="<?= h($lot['status']) ?>">
                        <td>
                            <strong><?= h($lot['customer_name']) ?></strong>
                            <span class="muted block"><?= h($lot['customer_type']) ?> · <?= h($lot['store_name']) ?></span>
                        </td>
                        <td>
                            <strong><?= h($lot['lot_number']) ?></strong>
                            <span class="muted block"><?= h(grams_to_kg((int) $lot['gross_g'])) ?> · <?= h(money((int) $lot['processing_price_cents'])) ?></span>
                        </td>

                        <td class="status-board-cell">
                            <span class="status"><?= h($formatTime($timeline['In Validare'] ?? null)) ?></span>
                            <div class="cell-actions">
                                <form method="post">
                                    <input type="hidden" name="action" value="processing_document">
                                    <input type="hidden" name="lot_id" value="<?= h((string) $lot['id']) ?>">
                                    <input type="hidden" name="document_type" value="PV-CUST">
                                    <button class="icon-button" type="submit" title="Print PV de preluare" aria-label="Print PV de preluare">
                                        <?= $docSvg ?>
                                    </button>
                                </form>
                            </div>
                        </td>

                        <td class="status-board-cell">
                            <span class="status"><?= h($formatTime($timeline['Acceptat'] ?? null)) ?></span>
                            <div class="cell-actions">
                                <?php if (!empty($timeline['Acceptat'])): ?>
                                    <form method="post">
                                        <input type="hidden" name="action" value="processing_document">
                                        <input type="hidden" name="lot_id" value="<?= h((string) $lot['id']) ?>">
                                        <input type="hidden" name="document_type" value="FACT">
                                        <button class="icon-button" type="submit" title="Print factura" aria-label="Print factura">
                                            <?= $docSvg ?>
                                        </button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="action" value="processing_document">
                                        <input type="hidden" name="lot_id" value="<?= h((string) $lot['id']) ?>">
                                        <input type="hidden" name="document_type" value="BON">
                                        <button class="icon-button" type="submit" title="Print bon casa" aria-label="Print bon casa">
                                            <?= $docSvg ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($lot['status'] === 'In Validare'): ?>
                                    <form method="post">
                                        <input type="hidden" name="action" value="transition_processing">
                                        <input type="hidden" name="lot_id" value="<?= h((string) $lot['id']) ?>">
                                        <input type="hidden" name="transition" value="accept">
                                        <button class="icon-button primary-icon" type="submit" title="Accepta" aria-label="Accepta">
                                            <?= $checkSvg ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td class="status-board-cell">
                            <span class="status"><?= h($formatTime($timeline['Predat Fabricii'] ?? null)) ?></span>
                            <div class="cell-actions">
                                <?php if (!empty($timeline['Predat Fabricii'])): ?>
                                    <form method="post">
                                        <input type="hidden" name="action" value="processing_document">
                                        <input type="hidden" name="lot_id" value="<?= h((string) $lot['id']) ?>">
                                        <input type="hidden" name="document_type" value="AVIZ">
                                        <button class="icon-button" type="submit" title="Print aviz" aria-label="Print aviz">
                                            <?= $docSvg ?>
                                        </button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="action" value="processing_document">
                                        <input type="hidden" name="lot_id" value="<?= h((string) $lot['id']) ?>">
                                        <input type="hidden" name="document_type" value="NIR">
                                        <button class="icon-button" type="submit" title="Print NIR" aria-label="Print NIR">
                                            <?= $docSvg ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td class="status-board-cell">
                            <span class="status"><?= h($formatTime($timeline['Respins'] ?? null)) ?></span>
                            <div class="cell-actions">
                                <?php if ($lot['status'] === 'In Validare'): ?>
                                    <form method="post">
                                        <input type="hidden" name="action" value="transition_processing">
                                        <input type="hidden" name="lot_id" value="<?= h((string) $lot['id']) ?>">
                                        <input type="hidden" name="transition" value="reject">
                                        <button class="icon-button danger-icon" type="submit" title="Respinge" aria-label="Respinge">
                                            <?= $banSvg ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td class="status-board-cell">
                            <span class="status"><?= h($formatTime($timeline['Returnat'] ?? null)) ?></span>
                            <div class="cell-actions">
                                <?php if (!empty($timeline['Returnat'])): ?>
                                    <form method="post">
                                        <input type="hidden" name="action" value="processing_document">
                                        <input type="hidden" name="lot_id" value="<?= h((string) $lot['id']) ?>">
                                        <input type="hidden" name="document_type" value="PV-RET">
                                        <button class="icon-button" type="submit" title="Print PV retur" aria-label="Print PV retur">
                                            <?= $docSvg ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($lot['status'] === 'Respins'): ?>
                                    <form method="post">
                                        <input type="hidden" name="action" value="transition_processing">
                                        <input type="hidden" name="lot_id" value="<?= h((string) $lot['id']) ?>">
                                        <input type="hidden" name="transition" value="return">
                                        <button class="icon-button primary-icon" type="submit" title="Returneaza" aria-label="Returneaza">
                                            <?= $backSvg ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['lots']): ?>
                    <tr>
                        <td colspan="7" class="empty">Nu exista loturi pentru filtrele curente.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
