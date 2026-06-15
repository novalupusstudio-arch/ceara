<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($config['app_name']) ?> - login</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-copy">
            <span class="eyebrow">Ceara MVP</span>
            <h1>Trasabilitate pentru ceara, loturi si documente.</h1>
            <p>Flux operational pentru procesare si achizitie, cu stocuri separate si audit.</p>
        </section>
        <form class="login-card" method="post">
            <input type="hidden" name="action" value="login">
            <h2>Autentificare</h2>
            <?php if ($message = flash()): ?>
                <div class="alert <?= h($message['type']) ?>"><?= h($message['message']) ?></div>
            <?php endif; ?>
            <label>
                Utilizator
                <input name="username" autocomplete="username" value="admin" required>
            </label>
            <label>
                Parola
                <input type="password" name="password" autocomplete="current-password" value="admin" required>
            </label>
            <button class="primary" type="submit">Intra in aplicatie</button>
            <p class="muted">Initial: admin / admin</p>
        </form>
    </main>
</body>
</html>

