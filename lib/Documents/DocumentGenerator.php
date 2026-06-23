<?php

declare(strict_types=1);

namespace Ceara\Documents;

use PDO;

final class DocumentGenerator
{
    public function __construct(
        private PDO $pdo,
        private DocumentFiles $files,
        private TemplateRenderer $templateRenderer,
        private PdfRenderer $pdfRenderer
    ) {
    }

    public function renderFile(int $documentId, callable $variablesForDocument, callable $labelForDocument): void
    {
        $doc = $this->files->findById($documentId);
        if (!$doc) {
            return;
        }

        $template = $this->templateByCode((string) $doc['document_type']);
        if (!$template) {
            return;
        }

        $store = $this->findStore((int) $doc['store_id']);
        if (!$store) {
            return;
        }

        $oldRelativePath = (string) ($doc['file_path'] ?? '');
        $body = $this->templateRenderer->render(
            (string) $template['body_html'],
            $variablesForDocument($doc, $store)
        );
        $html = $this->templateRenderer->wrapDocument($body);
        $relativePath = $this->files->relativePath($doc, $store, (string) $labelForDocument($doc), 'pdf');
        $absolutePath = $this->files->storagePath($relativePath);
        $dir = dirname($absolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($absolutePath, $this->pdfRenderer->renderA4Portrait($html));
        $this->pdo->prepare('UPDATE documents SET file_path = ? WHERE id = ?')
            ->execute([$relativePath, $documentId]);
        $this->files->removeReplacedHtmlDocument($oldRelativePath, $relativePath);
    }

    private function templateByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM document_templates WHERE code = ? AND active = 1 LIMIT 1');
        $stmt->execute([$code]);
        $template = $stmt->fetch();

        return $template ?: null;
    }

    private function findStore(int $storeId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM stores WHERE id = ? LIMIT 1');
        $stmt->execute([$storeId]);
        $store = $stmt->fetch();

        return $store ?: null;
    }
}
