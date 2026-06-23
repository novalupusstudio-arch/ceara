<?php

declare(strict_types=1);

namespace Ceara;

use PDO;
use RuntimeException;

final class SupplierService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function resolvePurchaseSupplier(array $data): array
    {
        $supplierType = $this->normalizeSupplierType((string) ($data['supplier_type'] ?? 'PF'));
        $supplier = $this->purchaseSupplierPayload($supplierType, $data);

        $existingId = $this->upsertSupplier($supplier);
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM suppliers
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$existingId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Furnizorul nu a putut fi salvat.');
        }

        return $row;
    }

    private function normalizeSupplierType(string $supplierType): string
    {
        return match ($supplierType) {
            'Producator agricol' => 'Producator agricol',
            'PJ/PFA', 'PFA/SRL' => 'PJ/PFA',
            default => 'PF',
        };
    }

    private function purchaseSupplierPayload(string $supplierType, array $data): array
    {
        $name = trim((string) ($data['supplier_name'] ?? ''));
        $identifier = trim((string) ($data['supplier_identifier'] ?? ''));
        $cui = trim((string) ($data['supplier_cui'] ?? ''));
        $address = trim((string) ($data['supplier_address'] ?? ''));
        $countyCode = trim((string) ($data['supplier_county_code'] ?? ''));
        $countyName = trim((string) ($data['supplier_county_name'] ?? ''));
        $localitySiruta = (int) ($data['supplier_locality_siruta'] ?? 0);
        $localityName = trim((string) ($data['supplier_locality_name'] ?? ''));
        $postalCode = trim((string) ($data['supplier_postal_code'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('Numele furnizorului este obligatoriu.');
        }
        if ($supplierType === 'PF' && $identifier === '') {
            throw new RuntimeException('CNP/CI este obligatoriu pentru persoanele fizice.');
        }
        if ($supplierType === 'PJ/PFA' && $cui === '') {
            throw new RuntimeException('CUI-ul este obligatoriu pentru PJ/PFA.');
        }

        return [
            'name' => $name,
            'supplier_type' => $supplierType,
            'phone' => trim((string) ($data['supplier_phone'] ?? '')),
            'identifier' => $identifier,
            'cui' => $cui,
            'address' => $address,
            'county_code' => $countyCode,
            'county_name' => $countyName,
            'locality_siruta' => $localitySiruta,
            'locality_name' => $localityName,
            'postal_code' => $postalCode,
        ];
    }

    private function upsertSupplier(array $supplier): int
    {
        $lookupSql = $supplier['supplier_type'] === 'PJ/PFA'
            ? 'SELECT id FROM suppliers WHERE supplier_type = ? AND cui = ? AND cui <> "" LIMIT 1'
            : 'SELECT id FROM suppliers WHERE supplier_type = ? AND name = ? AND identifier = ? LIMIT 1';
        $lookupParams = $supplier['supplier_type'] === 'PJ/PFA'
            ? [$supplier['supplier_type'], $supplier['cui']]
            : [$supplier['supplier_type'], $supplier['name'], $supplier['identifier']];

        $stmt = $this->pdo->prepare($lookupSql);
        $stmt->execute($lookupParams);
        $existingId = (int) $stmt->fetchColumn();

        if ($existingId > 0) {
            $this->pdo->prepare(
                'UPDATE suppliers
                 SET name = ?, phone = ?, identifier = ?, cui = ?, address = ?, county_code = ?, county_name = ?,
                     locality_siruta = ?, locality_name = ?, postal_code = ?
                 WHERE id = ?'
            )->execute([
                $supplier['name'],
                $supplier['phone'],
                $supplier['identifier'],
                $supplier['cui'],
                $supplier['address'],
                $supplier['county_code'],
                $supplier['county_name'],
                $supplier['locality_siruta'] ?: null,
                $supplier['locality_name'],
                $supplier['postal_code'],
                $existingId,
            ]);

            return $existingId;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO suppliers
            (name, supplier_type, phone, identifier, cui, address, county_code, county_name, locality_siruta, locality_name, postal_code)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $supplier['name'],
            $supplier['supplier_type'],
            $supplier['phone'],
            $supplier['identifier'],
            $supplier['cui'],
            $supplier['address'],
            $supplier['county_code'],
            $supplier['county_name'],
            $supplier['locality_siruta'] ?: null,
            $supplier['locality_name'],
            $supplier['postal_code'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
