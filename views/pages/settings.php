<?php
$rolePermissions = $data['role_permissions'];
$canManageSecurity = is_initial_admin();
$currentRole = current_user()['role'];
$canManageStores = (bool) ($rolePermissions[$currentRole]['STORE_MANAGE'] ?? false);
$canManageProcessors = (bool) ($rolePermissions[$currentRole]['PROCESSOR_MANAGE'] ?? false);
$availableTabs = ['password' => 'Schimba parola'];
if ($canManageSecurity) {
    $availableTabs['roles'] = 'Roluri si drepturi';
    $availableTabs['users'] = 'Creare useri';
}
if ($canManageStores) {
    $availableTabs['stores'] = 'Gestiuni';
}
if ($canManageProcessors) {
    $availableTabs['processors'] = 'Procesatori';
}

$activeTab = $_GET['settings_tab'] ?? 'password';
if (!isset($availableTabs[$activeTab])) {
    $activeTab = 'password';
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
            <div class="wide store-checks">
                <span class="field-title">Gestiuni alocate</span>
                <?php foreach ($data['stores'] as $store): ?>
                    <label class="check-line">
                        <input type="checkbox" name="store_ids[]" value="<?= h((string) $store['id']) ?>" checked>
                        <?= h($store['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <button class="primary" type="submit">Creeaza user</button>
        </form>
    </section>

    <section class="panel">
        <h2>Useri existenti</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Nume</th>
                        <th>Rol</th>
                        <th>Gestiuni</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['users'] as $listedUser): ?>
                        <?php
                        $assignedNames = [];
                        foreach (($data['user_stores'][(int) $listedUser['id']] ?? []) as $storeId) {
                            if (isset($storeNamesById[$storeId])) {
                                $assignedNames[] = $storeNamesById[$storeId];
                            }
                        }
                        ?>
                        <tr>
                            <td><?= h($listedUser['username']) ?></td>
                            <td><?= h($listedUser['full_name']) ?></td>
                            <td><span class="status"><?= h($listedUser['role']) ?></span></td>
                            <td><?= h($assignedNames ? implode(', ', $assignedNames) : '-') ?></td>
                            <td><?= $listedUser['active'] ? 'Activ' : 'Inactiv' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php if ($activeTab === 'stores' && $canManageStores): ?>
    <section class="panel">
        <h2>Adauga gestiune</h2>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="save_store">
            <input type="hidden" name="store_id" value="0">
            <label>Cod <input name="store_code" required placeholder="GEST2"></label>
            <label>Denumire <input name="store_name" required placeholder="Magazin nou"></label>
            <label class="wide">Adresa <input name="store_address"></label>
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
                    <label>Cod <input name="store_code" value="<?= h($store['code']) ?>" required></label>
                    <label>Denumire <input name="store_name" value="<?= h($store['name']) ?>" required></label>
                    <label>Adresa <input name="store_address" value="<?= h($store['address']) ?>"></label>
                    <button class="small" type="submit">Salveaza</button>
                </form>
            <?php endforeach; ?>
        </div>
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
            <label>Pret procesare <input name="processing_price" inputmode="decimal" value="0"></label>
            <label>Scazamant % <input name="exchange_shrinkage_pct" inputmode="decimal" value="0"></label>
            <input type="hidden" name="purchase_shrinkage_pct" value="0">
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
                    <label>Pret procesare <input name="processing_price" inputmode="decimal" value="<?= h((string) ($processor['processing_price_cents'] / 100)) ?>"></label>
                    <label>Scazamant % <input name="exchange_shrinkage_pct" inputmode="decimal" value="<?= h((string) $processor['exchange_shrinkage_pct']) ?>"></label>
                    <input type="hidden" name="purchase_shrinkage_pct" value="<?= h((string) $processor['purchase_shrinkage_pct']) ?>">
                    <button class="small" type="submit">Salveaza</button>
                </form>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
