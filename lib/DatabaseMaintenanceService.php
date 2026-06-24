<?php

declare(strict_types=1);

namespace Ceara;

use PDO;
use RuntimeException;

final class DatabaseMaintenanceService
{
    /**
     * @param callable(int,string,string,?int,?string,?string):void $logAudit
     */
    public function __construct(
        private PDO $pdo,
        private array $config,
        private string $storageRoot,
        private $logAudit
    ) {
    }

    public function data(): array
    {
        return [
            'database_name' => (string) ($this->config['db']['name'] ?? ''),
            'backup_dir' => $this->displayPath($this->backupsDir()),
            'available' => $this->resolveMysqlBinary('mysqldump') !== '' && $this->resolveMysqlBinary('mysql') !== '',
            'files' => $this->backupFiles(),
        ];
    }

    public function createBackup(int $userId): array
    {
        $dbName = $this->databaseName();
        $this->ensureDirectory($this->backupsDir());
        $fileName = sprintf('%s-%s.sql', $this->safeName($dbName), date('Ymd-His'));
        $targetPath = $this->backupsDir() . DIRECTORY_SEPARATOR . $fileName;

        $command = $this->buildDumpCommand($targetPath);
        $this->runCommand($command, 'Nu am putut genera backup-ul SQL.');

        ($this->logAudit)($userId, 'DATABASE_BACKUP_CREATE', 'database_backup', null, null, $fileName);

        return [
            'file_name' => $fileName,
            'path' => $targetPath,
        ];
    }

    public function importUploadedBackup(array $file, int $userId): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Fisierul SQL nu a fost incarcat corect.');
        }

        $originalName = trim((string) ($file['name'] ?? 'backup.sql'));
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'sql') {
            throw new RuntimeException('Se accepta doar fisiere .sql pentru import.');
        }

        $this->ensureDirectory($this->importsDir());
        $storedName = sprintf('import-%s-%s.sql', date('Ymd-His'), $this->safeName(pathinfo($originalName, PATHINFO_FILENAME)));
        $importPath = $this->importsDir() . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file((string) $file['tmp_name'], $importPath)) {
            throw new RuntimeException('Nu am putut salva fisierul SQL incarcat.');
        }

        $backup = $this->createBackup($userId);
        $this->resetCurrentDatabase();
        $command = $this->buildImportCommand($importPath);
        $this->runCommand($command, 'Nu am putut importa backup-ul SQL.');

        ($this->logAudit)($userId, 'DATABASE_BACKUP_IMPORT', 'database_backup', null, $backup['file_name'], $storedName);

        return [
            'import_file' => $storedName,
            'backup_file' => (string) $backup['file_name'],
        ];
    }

    private function backupFiles(): array
    {
        $dir = $this->backupsDir();
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        rsort($files, SORT_NATURAL);

        $rows = [];
        foreach (array_slice($files, 0, 15) as $file) {
            $rows[] = [
                'name' => basename($file),
                'size' => filesize($file) ?: 0,
                'modified_at' => date('Y-m-d H:i:s', filemtime($file) ?: time()),
            ];
        }

        return $rows;
    }

    private function resetCurrentDatabase(): void
    {
        $schema = $this->databaseName();
        $stmt = $this->pdo->prepare(
            "SELECT TABLE_NAME, TABLE_TYPE
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ?"
        );
        $stmt->execute([$schema]);
        $tables = $stmt->fetchAll();

        $views = [];
        $baseTables = [];
        foreach ($tables as $table) {
            if (($table['TABLE_TYPE'] ?? '') === 'VIEW') {
                $views[] = (string) $table['TABLE_NAME'];
            } else {
                $baseTables[] = (string) $table['TABLE_NAME'];
            }
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($views as $view) {
                $this->pdo->exec('DROP VIEW IF EXISTS `' . str_replace('`', '``', $view) . '`');
            }
            foreach ($baseTables as $table) {
                $this->pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
            }
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function buildDumpCommand(string $targetPath): string
    {
        $binary = $this->resolveMysqlBinary('mysqldump');
        if ($binary === '') {
            throw new RuntimeException('Nu am gasit executabilul mysqldump pe acest mediu.');
        }

        $db = $this->config['db'];
        $args = [
            $this->quoteArg($binary),
            '--host=' . $this->quoteArg((string) ($db['host'] ?? '')),
            '--port=' . $this->quoteArg((string) ($db['port'] ?? '3306')),
            '--user=' . $this->quoteArg((string) ($db['user'] ?? '')),
            '--default-character-set=utf8mb4',
            '--routines',
            '--triggers',
            '--single-transaction',
            '--skip-comments',
            $this->quoteArg($this->databaseName()),
        ];

        $password = (string) ($db['pass'] ?? '');
        if ($password !== '') {
            $args[] = '--password=' . $this->quoteArg($password);
        }

        return $this->wrapShellCommand(implode(' ', $args) . ' > ' . $this->quoteArg($targetPath));
    }

    private function buildImportCommand(string $sourcePath): string
    {
        $binary = $this->resolveMysqlBinary('mysql');
        if ($binary === '') {
            throw new RuntimeException('Nu am gasit executabilul mysql pe acest mediu.');
        }

        $db = $this->config['db'];
        $args = [
            $this->quoteArg($binary),
            '--host=' . $this->quoteArg((string) ($db['host'] ?? '')),
            '--port=' . $this->quoteArg((string) ($db['port'] ?? '3306')),
            '--user=' . $this->quoteArg((string) ($db['user'] ?? '')),
            '--default-character-set=utf8mb4',
            $this->quoteArg($this->databaseName()),
        ];

        $password = (string) ($db['pass'] ?? '');
        if ($password !== '') {
            $args[] = '--password=' . $this->quoteArg($password);
        }

        return $this->wrapShellCommand(implode(' ', $args) . ' < ' . $this->quoteArg($sourcePath));
    }

    private function runCommand(string $command, string $failureMessage): void
    {
        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException($failureMessage . ' ' . trim(implode("\n", $output)));
        }
    }

    private function wrapShellCommand(string $command): string
    {
        return $command;
    }

    private function resolveMysqlBinary(string $binary): string
    {
        $binary = strtolower($binary) === 'mysqldump' ? 'mysqldump' : 'mysql';

        $candidates = PHP_OS_FAMILY === 'Windows'
            ? [
                'D:\\xampp\\mysql\\bin\\' . $binary . '.exe',
                'C:\\xampp\\mysql\\bin\\' . $binary . '.exe',
                $binary . '.exe',
            ]
            : [
                '/usr/bin/' . $binary,
                '/usr/local/bin/' . $binary,
                $binary,
            ];

        foreach ($candidates as $candidate) {
            if (str_contains($candidate, DIRECTORY_SEPARATOR)) {
                if (is_file($candidate)) {
                    return $candidate;
                }
                continue;
            }

            return $candidate;
        }

        return '';
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Nu am putut crea directorul pentru backup-uri SQL.');
        }
    }

    private function backupsDir(): string
    {
        $configured = trim((string) ($this->config['database_maintenance']['backup_dir'] ?? ''));
        if ($configured !== '') {
            return $this->normalizePath($configured);
        }

        return $this->normalizePath($this->storageRoot . DIRECTORY_SEPARATOR . 'backups');
    }

    private function importsDir(): string
    {
        return $this->backupsDir() . DIRECTORY_SEPARATOR . 'imports';
    }

    private function databaseName(): string
    {
        $name = trim((string) ($this->config['db']['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Numele bazei de date nu este configurat.');
        }

        return $name;
    }

    private function safeName(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($value));
        return trim((string) $safe, '-') ?: 'backup';
    }

    private function quoteArg(string $value): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return escapeshellarg($value);
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return $path;
        }

        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    private function displayPath(string $path): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return str_replace('/', '\\', $path);
        }

        return str_replace('\\', '/', $path);
    }
}
