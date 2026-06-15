<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($config['app_name']) ?></title>
    <link rel="stylesheet" href="assets/styles.css?v=<?= h($config['app_version']) ?>">
    <script src="assets/app.js?v=<?= h($config['app_version']) ?>" defer></script>
</head>
<body>
    <aside class="sidebar">
        <a class="brand" href="index.php">
            <span class="brand-mark">C</span>
            <span>
                <strong>Ceara</strong>
                <small>MVP operational</small>
            </span>
        </a>
        <nav>
            <?php foreach ([
                'dashboard' => 'Dashboard',
                'processing' => 'Procesare ceara',
                'purchases' => 'Achizitie ceara',
                'documents' => 'Documente',
                'reports' => 'Rapoarte',
                'settings' => 'Setari',
                'audit' => 'Audit',
            ] as $key => $label): ?>
                <a class="<?= $page === $key ? 'active' : '' ?>" href="index.php?page=<?= h($key) ?>"><?= h($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <form method="post" class="logout-form">
            <input type="hidden" name="action" value="logout">
            <button type="submit">Logout <?= h(current_user()['username']) ?></button>
        </form>
    </aside>
    <main class="main">
        <?php if ($message = flash()): ?>
            <div class="alert <?= h($message['type']) ?>"><?= h($message['message']) ?></div>
        <?php endif; ?>
        <?php require __DIR__ . '/pages/' . $page . '.php'; ?>
        <footer class="app-footer">
            <span><?= h($config['app_name']) ?></span>
            <span>v<?= h($config['app_version']) ?></span>
        </footer>
    </main>
</body>
</html>
