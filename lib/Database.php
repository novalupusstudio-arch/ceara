<?php

final class Database
{
    private array $config;
    private ?PDO $pdo = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $db = $this->config['db'];
        $this->assertDbConfigured($db);
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['name'],
            $db['charset']
        );

        $this->pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $this->pdo;
    }

    public function ensureDatabase(): void
    {
        $db = $this->config['db'];
        $this->assertDbConfigured($db);
        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['charset']
        );

        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $name = str_replace('`', '``', $db['name']);
        $charset = $db['charset'];
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET $charset COLLATE {$charset}_unicode_ci");
    }

    public function migrateAndSeed(): void
    {
        $pdo = $this->pdo();
        $schema = file_get_contents(__DIR__ . '/../db/schema.sql');
        if ($schema === false) {
            throw new RuntimeException('Schema file missing.');
        }

        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            $pdo->exec($statement);
        }

        (new \Ceara\DatabaseMigrator($pdo))->run();
        (new \Ceara\DatabaseSeeder($pdo, $this->config))->run();
    }

    private function assertDbConfigured(array $db): void
    {
        foreach (['host', 'port', 'name', 'user'] as $key) {
            if (trim((string) ($db[$key] ?? '')) === '') {
                throw new RuntimeException(
                    'Configuratia bazei de date este incompleta. Completeaza config/local.php sau variabilele CEARA_DB_*.'
                );
            }
        }
    }
}
