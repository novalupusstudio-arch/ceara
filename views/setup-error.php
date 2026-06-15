<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ceara - setup</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body class="setup-page">
    <main class="setup-panel">
        <h1>Conexiunea MySQL nu este disponibila</h1>
        <p>Aplicatia incearca sa creeze si sa migreze automat baza de date `ceara` folosind setarile din `config/config.php`.</p>
        <pre><?= h($setupError) ?></pre>
        <p>Verifica MySQL in XAMPP si credentialele. Implicit: host `127.0.0.1`, user `root`, parola goala.</p>
    </main>
</body>
</html>

