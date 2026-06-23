<?php
$rolePermissions = $data['role_permissions'];
$canManageSecurity = is_initial_admin();
$currentRole = current_user()['role'];
$canManageStores = (bool) ($rolePermissions[$currentRole]['STORE_MANAGE'] ?? false);
$canManageProcessors = (bool) ($rolePermissions[$currentRole]['PROCESSOR_MANAGE'] ?? false);
$canManageDocumentTemplates = (bool) ($rolePermissions[$currentRole]['DOCUMENT_TEMPLATE_MANAGE'] ?? false);

$availableTabs = [];
if ($canManageSecurity) {
    $availableTabs['company'] = 'Date societate';
}
if ($canManageProcessors) {
    $availableTabs['processors'] = 'Procesatori';
}
if ($canManageStores) {
    $availableTabs['stores'] = 'Gestiuni';
    $availableTabs['document_series'] = 'Serii documente';
}
if ($canManageSecurity) {
    $availableTabs['roles'] = 'Roluri si drepturi';
    $availableTabs['users'] = 'Creare useri';
}
if ($canManageDocumentTemplates) {
    $availableTabs['document_templates'] = 'Template documente';
}
$availableTabs['password'] = 'Schimba parola';

$activeTab = $_GET['settings_tab'] ?? array_key_first($availableTabs);
if (!isset($availableTabs[$activeTab])) {
    $activeTab = array_key_first($availableTabs);
}

$storeNamesById = [];
foreach ($data['stores'] as $store) {
    $storeNamesById[(int) $store['id']] = $store['name'];
}
?>

<header class="page-header">
    <div>
        <span class="eyebrow">Administrare</span>
        <h1>Setari</h1>
    </div>
</header>

<nav class="tabs">
    <?php foreach ($availableTabs as $key => $label): ?>
        <a class="<?= $activeTab === $key ? 'active' : '' ?>" href="index.php?page=settings&settings_tab=<?= h($key) ?>">
            <?= h($label) ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($activeTab === 'password'): ?>
    <section class="panel">
        <h2>Schimba parola</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="change_password">
            <label>
                Parola noua
                <input type="password" name="new_password" autocomplete="new-password" required>
            </label>
            <label>
                Confirma parola
                <input type="password" name="confirm_password" autocomplete="new-password" required>
            </label>
            <button class="primary" type="submit">Schimba parola</button>
        </form>
    </section>
<?php endif; ?>

<?php if ($activeTab === 'company' && $canManageSecurity): ?>
    <?php $company = $data['company_settings'] ?? []; ?>
    <section class="panel">
        <h2>Date societate</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="save_company_settings">
            <label>
                Societate
                <input name="company_name" value="<?= h((string) ($company['company_name'] ?? '')) ?>">
            </label>
            <label>
                CUI
                <input name="vat_number" value="<?= h((string) ($company['vat_number'] ?? '')) ?>">
            </label>
            <label>
                Nr. Reg. Com.
                <input name="registry_number" value="<?= h((string) ($company['registry_number'] ?? '')) ?>">
            </label>
            <label>
                Telefon
                <input name="phone" value="<?= h((string) ($company['phone'] ?? '')) ?>">
            </label>
            <label>
                Email
                <input name="email" type="email" value="<?= h((string) ($company['email'] ?? '')) ?>">
            </label>
            <label class="wide">
                Sediu
                <input name="address" value="<?= h((string) ($company['address'] ?? '')) ?>">
            </label>
            <label class="wide">
                URL FGO
                <input name="fgo_url" value="<?= h((string) ($company['fgo_url'] ?? '')) ?>" placeholder="https://api-testuat.fgo.ro/v1">
            </label>
            <label class="wide">
                Token FGO
                <input type="password" name="fgo_token" autocomplete="off" value="<?= h((string) ($company['fgo_token'] ?? '')) ?>">
            </label>
            <button class="primary" type="submit">Salveaza datele societatii</button>
        </form>
    </section>
<?php endif; ?>

<?php if ($activeTab === 'roles' && $canManageSecurity): ?>
    <section class="panel">
        <h2>Asigneaza drepturi la roluri</h2>
        <form method="post">
            <input type="hidden" name="action" value="save_role_permissions">
            <div class="table-wrap">
                <table class="permission-table">
                    <thead>
                        <tr>
                            <th>Permisiune</th>
                            <th>Admin</th>
                            <th>Operator</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['permissions'] as $permission): ?>
                            <tr>
                                <td>
                                    <strong><?= h($permission['code']) ?></strong>
                                    <span class="muted block"><?= h($permission['label']) ?></span>
                                </td>
                                <?php foreach (['admin', 'operator'] as $role): ?>
                                    <td>
                                        <label class="matrix-check" title="<?= h($role . ' - ' . $permission['code']) ?>">
                                            <input
                                                type="checkbox"
                                                name="permissions[<?= h($role) ?>][<?= h($permission['code']) ?>]"
                                                <?= ($rolePermissions[$role][$permission['code']] ?? false) ? 'checked' : '' ?>
                                            >
                                            <span></span>
                                        </label>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="panel-actions">
                <button class="primary" type="submit">Salveaza drepturile</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php if ($activeTab === 'document_templates' && $canManageDocumentTemplates): ?>
    <section class="panel">
        <h2>Template documente</h2>
        <form method="post" class="document-template-form">
            <input type="hidden" name="action" value="save_document_templates">
            <?php foreach ($data['document_templates'] as $template): ?>
                <article class="document-template-editor">
                    <header>
                        <div>
                            <span class="eyebrow"><?= h($template['code']) ?></span>
                            <h3><?= h($template['name']) ?></h3>
                            <?php if ($template['description'] !== ''): ?>
                                <p class="muted"><?= h($template['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <a class="secondary compact" href="index.php?page=document_template_preview&template_id=<?= h((string) $template['id']) ?>" target="_blank" rel="noopener">Preview</a>
                    </header>
                    <div class="template-variable-list" aria-label="Variabile disponibile">
                        <?php foreach ($template['variables'] as $variable): ?>
                            <code>[<?= h((string) $variable) ?>]</code>
                        <?php endforeach; ?>
                    </div>
                    <label>
                        HTML template
                        <textarea class="html-template-editor" name="templates[<?= h((string) $template['id']) ?>][body_html]" rows="28" spellcheck="false"><?= h($template['body_html']) ?></textarea>
                    </label>
                </article>
            <?php endforeach; ?>
            <div class="panel-actions">
                <button class="primary" type="submit">Salveaza template-uri</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php if ($activeTab === 'users' && $canManageSecurity): ?>
    <section class="panel">
        <h2>Creare user</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="create_user">
            <label>
                Utilizator
                <input name="username" required>
            </label>
            <label>
                Nume complet
                <input name="full_name">
            </label>
            <label>
                Parola initiala
                <input type="password" name="password" required>
            </label>
            <label>
                Rol
                <select name="role">
                    <option value="operator">Operator</option>
                    <option value="admin">Admin</option>
                </select>
            </label>
            <label class="check-line">
                <input type="checkbox" name="active" checked>
                Activ
            </label>
            <label>
                Gestiune
                <select name="store_id">
                    <option value="0">Fara gestiune</option>
                    <?php foreach ($data['stores'] as $store): ?>
                        <option value="<?= h((string) $store['id']) ?>"><?= h($store['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="primary" type="submit">Creeaza user</button>
        </form>
    </section>

    <section class="panel">
        <h2>Useri existenti</h2>
        <div class="store-list">
            <?php foreach ($data['users'] as $listedUser): ?>
                <?php
                $assignedStoreId = (int) (($data['user_stores'][(int) $listedUser['id']][0] ?? 0));
                ?>
                <form method="post" class="store-row">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" value="<?= h((string) $listedUser['id']) ?>">
                    <label>
                        Utilizator
                        <input value="<?= h($listedUser['username']) ?>" disabled>
                    </label>
                    <label>
                        Nume complet
                        <input name="full_name" value="<?= h($listedUser['full_name']) ?>">
                    </label>
                    <label>
                        Parola noua
                        <input type="password" name="password" placeholder="Lasat gol = neschimbata">
                    </label>
                    <label>
                        Rol
                        <select name="role">
                            <option value="operator" <?= $listedUser['role'] === 'operator' ? 'selected' : '' ?>>Operator</option>
                            <option value="admin" <?= $listedUser['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </label>
                    <label>
                        Gestiune
                        <select name="store_id">
                            <option value="0" <?= $assignedStoreId === 0 ? 'selected' : '' ?>>Fara gestiune</option>
                            <?php foreach ($data['stores'] as $store): ?>
                                <option value="<?= h((string) $store['id']) ?>" <?= (int) $store['id'] === $assignedStoreId ? 'selected' : '' ?>>
                                    <?= h($store['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="check-line">
                        <input type="checkbox" name="active" <?= $listedUser['active'] ? 'checked' : '' ?>>
                        Activ
                    </label>
                    <div class="panel-actions">
                        <button class="primary" type="submit">Salveaza user</button>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($activeTab === 'stores' && $canManageStores): ?>
    <section class="panel">
        <h2>Adauga gestiune</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="save_store">
            <input type="hidden" name="store_id" value="0">
            <label title="Alege un cod scurt, de preferat 2 litere uppercase, ex. BC, CJ, IS.">Cod <input name="store_code" required placeholder="BC" maxlength="8" pattern="[A-Z0-9_-]+" title="Cod scurt pentru serii, de preferat 2 litere uppercase: BC, CJ, IS."></label>
            <label>Denumire <input name="store_name" required placeholder="Magazin nou"></label>
            <label title="Trebuie sa existe deja in FGO. Exemplu: WAX-BC.">Serie factura FGO <input name="store_fgo_series" placeholder="WAX-BC"></label>
            <label>
                Procesator asignat
                <select name="store_processor_id" required>
                    <?php foreach ($data['processors'] as $processor): ?>
                        <option value="<?= h((string) $processor['id']) ?>"><?= h($processor['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="wide">Adresa <input name="store_address"></label>
            <label title="Scazamant folosit in relatia gestiunii cu clientul.">Scazamant procesare client % <input name="store_processing_shrinkage_pct" inputmode="decimal" value="0"></label>
            <label title="Pret folosit in relatia gestiunii cu clientul.">Pret procesare client lei/kg <input name="store_processing_price" inputmode="decimal" value="0.00"></label>
            <label title="Scazamant folosit in relatia gestiunii cu furnizorul de achizitie.">Scazamant achizitie client % <input name="store_purchase_shrinkage_pct" inputmode="decimal" value="0"></label>
            <label title="Pret folosit in relatia gestiunii cu furnizorul de achizitie.">Pret achizitie client lei/kg <input name="store_purchase_price" inputmode="decimal" value="0.00"></label>
            <button class="primary" type="submit">Adauga gestiune</button>
        </form>
    </section>

    <section class="panel">
        <h2>Lista gestiuni</h2>
        <div class="store-list">
            <?php foreach ($data['stores'] as $store): ?>
                <form method="post" class="store-row">
                    <input type="hidden" name="action" value="save_store">
                    <input type="hidden" name="store_id" value="<?= h((string) $store['id']) ?>">
                    <label title="Alege un cod scurt, de preferat 2 litere uppercase, ex. BC, CJ, IS.">Cod <input name="store_code" value="<?= h($store['code']) ?>" required maxlength="8" pattern="[A-Z0-9_-]+" title="Cod scurt pentru serii, de preferat 2 litere uppercase: BC, CJ, IS."></label>
                    <label>Denumire <input name="store_name" value="<?= h($store['name']) ?>" required></label>
                    <label title="Trebuie sa existe deja in FGO. Exemplu: WAX-BC.">Serie factura FGO <input name="store_fgo_series" value="<?= h((string) ($store['fgo_series'] ?? '')) ?>"></label>
                    <label>Adresa <input name="store_address" value="<?= h($store['address']) ?>"></label>
                    <label title="Scazamant folosit in relatia gestiunii cu clientul.">Scazamant procesare client % <input name="store_processing_shrinkage_pct" inputmode="decimal" value="<?= h((string) ($store['processing_shrinkage_pct'] ?? '0')) ?>"></label>
                    <label title="Pret folosit in relatia gestiunii cu clientul.">Pret procesare client lei/kg <input name="store_processing_price" inputmode="decimal" value="<?= h(number_format(((int) ($store['processing_price_cents'] ?? 0)) / 100, 2, '.', '')) ?>"></label>
                    <label title="Scazamant folosit in relatia gestiunii cu furnizorul de achizitie.">Scazamant achizitie client % <input name="store_purchase_shrinkage_pct" inputmode="decimal" value="<?= h((string) ($store['purchase_shrinkage_pct'] ?? '0')) ?>"></label>
                    <label title="Pret folosit in relatia gestiunii cu furnizorul de achizitie.">Pret achizitie client lei/kg <input name="store_purchase_price" inputmode="decimal" value="<?= h(number_format(((int) ($store['purchase_price_cents_per_kg'] ?? 0)) / 100, 2, '.', '')) ?>"></label>
                    <label>
                        Procesator
                        <select name="store_processor_id" required>
                            <?php foreach ($data['processors'] as $processor): ?>
                                <option value="<?= h((string) $processor['id']) ?>" <?= (int) $processor['id'] === (int) ($store['processor_id'] ?? 0) ? 'selected' : '' ?>>
                                    <?= h($processor['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="small" type="submit">Salveaza</button>
                </form>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($activeTab === 'document_series' && $canManageStores): ?>
    <?php
    $seriesLabels = [
        'PV-CUST' => 'PV primire ceara',
        'PV-FAG' => 'PV predare faguri',
        'PV-RET' => 'PV retur ceara',
        'AVIZ' => 'Aviz predare fabrica',
        'NIR' => 'NIR',
        'BON' => 'Bon',
        'BORD' => 'Borderou',
    ];
    ?>
    <section class="panel">
        <h2>Serii documente</h2>
        <p class="muted">Seria facturilor FGO se configureaza din Gestiuni. Aici administrezi doar seriile documentelor interne.</p>
        <form method="post">
            <input type="hidden" name="action" value="save_document_series">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Gestiune</th>
                            <th>Tip document</th>
                            <th>Serie</th>
                            <th>Urmatorul numar</th>
                            <th>Exemplu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['series'] as $series): ?>
                            <?php
                            $nextNumber = max(1, (int) $series['next_number']);
                            $documentType = (string) $series['document_type'];
                            $seriesValue = (string) $series['series'];
                            ?>
                            <tr>
                                <td><?= h((string) $series['store_name']) ?></td>
                                <td><?= h($seriesLabels[$documentType] ?? $documentType) ?></td>
                                <td>
                                    <input name="series[<?= h((string) $series['id']) ?>][series]" value="<?= h($seriesValue) ?>" required>
                                </td>
                                <td>
                                    <input name="series[<?= h((string) $series['id']) ?>][next_number]" type="number" min="1" step="1" value="<?= h((string) $nextNumber) ?>" required>
                                </td>
                                <td><?= h($seriesValue . '-' . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="panel-actions">
                <button class="primary" type="submit">Salveaza seriile</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php if ($activeTab === 'processors' && $canManageProcessors): ?>
    <section class="panel">
        <h2>Adauga procesator PJ</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="save_processor">
            <input type="hidden" name="processor_id" value="0">
            <label>Nume <input name="processor_name" required placeholder="Procesator SRL"></label>
            <label>CUI <input name="processor_cui" required placeholder="RO123456"></label>
            <label class="wide">Adresa <input name="processor_address" required></label>
            <label title="Pretul nostru in relatia cu procesatorul.">Pret procesare procesator lei/kg <input name="processing_price" inputmode="decimal" value="0"></label>
            <label title="Scazamantul nostru in relatia cu procesatorul.">Scazamant procesator % <input name="exchange_shrinkage_pct" inputmode="decimal" value="0"></label>
            <button class="primary" type="submit">Adauga procesator</button>
        </form>
    </section>

    <section class="panel">
        <h2>Lista procesatori</h2>
        <div class="store-list">
            <?php foreach ($data['processors'] as $processor): ?>
                <form method="post" class="processor-row">
                    <input type="hidden" name="action" value="save_processor">
                    <input type="hidden" name="processor_id" value="<?= h((string) $processor['id']) ?>">
                    <label>Nume <input name="processor_name" value="<?= h($processor['name']) ?>" required></label>
                    <label>CUI <input name="processor_cui" value="<?= h($processor['cui']) ?>" required></label>
                    <label>Adresa <input name="processor_address" value="<?= h($processor['address'] ?? '') ?>" required></label>
                    <label title="Pretul nostru in relatia cu procesatorul.">Pret procesare procesator lei/kg <input name="processing_price" inputmode="decimal" value="<?= h((string) ($processor['processing_price_cents'] / 100)) ?>"></label>
                    <label title="Scazamantul nostru in relatia cu procesatorul.">Scazamant procesator % <input name="exchange_shrinkage_pct" inputmode="decimal" value="<?= h((string) $processor['exchange_shrinkage_pct']) ?>"></label>
                    <button class="small" type="submit">Salveaza</button>
                </form>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
