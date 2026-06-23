<?php

declare(strict_types=1);

namespace Ceara\Documents;

use PDO;

final class DocumentFiles
{
    public function __construct(private PDO $pdo, private string $storageRoot)
    {
        $this->storageRoot = rtrim(str_replace('\\', '/', $storageRoot), '/');
    }

    public function findById(int $documentId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM documents WHERE id = ? LIMIT 1');
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();

        return $document ?: null;
    }

    public function pdfById(int $documentId, callable $renderMissingDocument): ?string
    {
        $doc = $this->findById($documentId);
        if (!$doc) {
            return null;
        }

        $filePath = (string) ($doc['file_path'] ?? '');
        if ($filePath === '' || strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'pdf') {
            $renderMissingDocument($documentId);
            $doc = $this->findById($documentId);
            $filePath = (string) ($doc['file_path'] ?? '');
            if ($filePath === '' || strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) !== 'pdf') {
                return null;
            }
        }

        $absolutePath = $this->storagePath($filePath);
        if (!is_file($absolutePath)) {
            $renderMissingDocument($documentId);
            $doc = $this->findById($documentId);
            $filePath = (string) ($doc['file_path'] ?? '');
            $absolutePath = $filePath === '' ? '' : $this->storagePath($filePath);
            if ($absolutePath === '' || !is_file($absolutePath)) {
                return null;
            }
        }

        $pdf = file_get_contents($absolutePath);
        return $pdf === false ? null : $pdf;
    }

    public function relativePath(array $doc, array $store, string $label, string $extension): string
    {
        $storeCode = $this->safePathPart((string) ($store['code'] ?? 'gestiune'));
        $fileName = $this->safePathPart($label) . '.' . $extension;

        return 'documents/' . $storeCode . '/' . $fileName;
    }

    public function storagePath(string $relativePath): string
    {
        return $this->storageRoot . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    public function removeReplacedHtmlDocument(string $oldRelativePath, string $newRelativePath): void
    {
        if ($oldRelativePath === '' || $oldRelativePath === $newRelativePath || strtolower(pathinfo($oldRelativePath, PATHINFO_EXTENSION)) !== 'html') {
            return;
        }

        $absolutePath = $this->storagePath($oldRelativePath);
        $documentsRoot = $this->storageRoot . '/documents/';
        if (is_file($absolutePath) && str_starts_with(str_replace('\\', '/', $absolutePath), $documentsRoot)) {
            unlink($absolutePath);
        }
    }

    private function safePathPart(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($value));
        return trim((string) $safe, '_') ?: 'document';
    }
}
