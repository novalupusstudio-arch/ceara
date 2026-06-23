<?php

declare(strict_types=1);

namespace Ceara;

use Ceara\Documents\DocumentIssuer;
use PDO;

final class DocumentService
{
    /**
     * @param callable(int):void $renderDocumentFile
     */
    public function __construct(
        private PDO $pdo,
        private $renderDocumentFile
    ) {
    }

    public function issue(string $type, string $referenceType, int $referenceId, int $storeId, string $status, string $notes, array $links = []): int
    {
        return (new DocumentIssuer($this->pdo))->issue(
            $type,
            $referenceType,
            $referenceId,
            $storeId,
            $status,
            $notes,
            $links,
            $this->renderDocumentFile
        );
    }
}
