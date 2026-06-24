<?php

declare(strict_types=1);

namespace Ceara;

use PDO;
use RuntimeException;

final class CustomerService
{
    /**
     * @param callable(string,int):?array $find
     */
    public function __construct(
        private PDO $pdo,
        private array $config,
        private $find
    ) {
    }

    public function sirutaCounties(): array
    {
        return $this->pdo->query(
            'SELECT county_code, name
             FROM siruta_counties
             ORDER BY name'
        )->fetchAll();
    }

    public function sirutaLocalities(string $countyCode, string $term = ''): array
    {
        $countyCode = trim($countyCode);
        if ($countyCode === '') {
            return [];
        }

        $term = trim($term);
        if ($term !== '') {
            $stmt = $this->pdo->prepare(
                'SELECT siruta_code, county_code, name, display_name, postal_code, parent_name, parent_type
                 FROM siruta_localities
                 WHERE county_code = ?
                    AND (normalized_name LIKE ? OR display_name LIKE ?)
                 ORDER BY display_name
                 LIMIT 120'
            );
            $stmt->execute([$countyCode, '%' . $this->normalizeLocationName($term) . '%', '%' . $term . '%']);
            return $stmt->fetchAll();
        }

        $stmt = $this->pdo->prepare(
            'SELECT siruta_code, county_code, name, display_name, postal_code, parent_name, parent_type
             FROM siruta_localities
             WHERE county_code = ?
             ORDER BY display_name
             LIMIT 400'
        );
        $stmt->execute([$countyCode]);
        return $stmt->fetchAll();
    }

    public function lookupAnafCompany(string $cui): array
    {
        $cui = preg_replace('/\D+/', '', strtoupper(trim($cui))) ?? '';
        if ($cui === '') {
            throw new RuntimeException('CUI-ul este obligatoriu pentru preluarea datelor ANAF.');
        }

        $url = 'https://demoanaf.ro/api/company/' . rawurlencode($cui);
        $context = stream_context_create([
            'http' => [
                'timeout' => 12,
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            throw new RuntimeException('Nu am putut contacta serviciul ANAF.');
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload) || empty($payload['success']) || !is_array($payload['data'] ?? null)) {
            throw new RuntimeException('Serviciul ANAF nu a returnat date valide pentru CUI-ul cautat.');
        }

        $data = $payload['data'];
        $hq = is_array($data['headquartersAddress'] ?? null) ? $data['headquartersAddress'] : [];
        $countyName = (string) ($hq['county'] ?? '');
        $localityRaw = (string) ($hq['locality'] ?? '');
        $match = $this->matchSirutaLocation($countyName, $localityRaw, (string) ($hq['postalCode'] ?? $data['postalCode'] ?? ''));

        $address = $this->cleanCompanyAddress(
            (string) ($data['address'] ?? ''),
            $match['county_name'] ?: $countyName,
            $match['locality_name'] ?: $localityRaw,
            (string) ($hq['postalCode'] ?? $data['postalCode'] ?? '')
        );

        return [
            'customer_type' => 'PJ',
            'name' => (string) ($data['name'] ?? ''),
            'identifier' => $cui,
            'cui' => $cui,
            'phone' => '',
            'representative' => '',
            'address' => $address,
            'registry_number' => (string) ($data['registrationNumber'] ?? ''),
            'legal_form' => (string) ($data['legalForm'] ?? ''),
            'vat_status' => (string) ($data['vatStatus'] ?? ''),
            'postal_code' => (string) ($hq['postalCode'] ?? $data['postalCode'] ?? ''),
            'county_name' => $match['county_name'] ?: $countyName,
            'county_code' => $match['county_code'],
            'locality_name' => $match['locality_name'] ?: $localityRaw,
            'locality_siruta' => $match['locality_siruta'],
            'locality_display_name' => $match['locality_display_name'],
            'external_source' => 'anaf',
            'external_checked_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function searchCustomers(string $customerType, string $term): array
    {
        $customerType = $customerType === 'PJ' ? 'PJ' : 'PF';
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        if ($customerType === 'PJ') {
            $stmt = $this->pdo->prepare(
                "SELECT id, customer_type, name, phone, address, identifier, cui, representative,
                        county_code, county_name, locality_siruta, locality_name, postal_code,
                        registry_number, legal_form, vat_status, external_source, external_checked_at
                 FROM customers
                 WHERE customer_type = ? AND (identifier LIKE ? OR cui LIKE ?)
                 ORDER BY id DESC
                 LIMIT 8"
            );
            $stmt->execute([$customerType, '%' . $term . '%', '%' . $term . '%']);
            return $stmt->fetchAll();
        }

        $stmt = $this->pdo->prepare(
            "SELECT id, customer_type, name, phone, address, identifier, cui, representative,
                    county_code, county_name, locality_siruta, locality_name, postal_code,
                    registry_number, legal_form, vat_status, external_source, external_checked_at
             FROM customers
             WHERE customer_type = ? AND (phone LIKE ? OR identifier LIKE ? OR cui LIKE ?)
             ORDER BY id DESC
             LIMIT 8"
        );
        $stmt->execute([$customerType, '%' . $term . '%', '%' . $term . '%', '%' . $term . '%']);
        return $stmt->fetchAll();
    }

    public function userPrimaryStore(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, p.name AS processor_name
             FROM user_stores us
             JOIN stores s ON s.id = us.store_id
             LEFT JOIN processors p ON p.id = s.processor_id
             WHERE us.user_id = ?
             ORDER BY us.store_id
             LIMIT 1'
        );
        $stmt->execute([$userId]);
        $store = $stmt->fetch();
        return $store ?: null;
    }

    public function defaultProcessorForUser(int $userId): ?array
    {
        $store = $this->userPrimaryStore($userId);
        if (!$store) {
            throw new RuntimeException('Utilizatorul nu are o gestiune alocata.');
        }

        $processorId = (int) ($store['processor_id'] ?? 0);
        if ($processorId <= 0) {
            return null;
        }

        $processor = ($this->find)('processors', $processorId);
        if (!$processor) {
            return null;
        }

        return $processor;
    }

    public function resolveProcessingCustomer(array $data): array
    {
        $customerType = $data['customer_type'] === 'PJ' ? 'PJ' : 'PF';
        $existingCustomerId = (int) ($data['existing_customer_id'] ?? 0);
        $isNewCustomer = !empty($data['force_new_customer']);

        if ($existingCustomerId > 0 && !$isNewCustomer) {
            $existing = ($this->find)('customers', $existingCustomerId);
            if (!$existing) {
                throw new RuntimeException('Clientul selectat nu exista.');
            }

            $customerPayload = [
                'customer_type' => $customerType,
                'name' => $customerType === 'PJ'
                    ? trim((string) ($data['customer_name_pj'] ?? $data['customer_name']))
                    : trim((string) $data['customer_name']),
                'phone' => $customerType === 'PJ'
                    ? trim((string) ($data['customer_phone_pj'] ?? $data['customer_phone']))
                    : trim((string) $data['customer_phone']),
                'address' => trim((string) ($data['customer_address'] ?? '')),
                'identifier' => $customerType === 'PF'
                    ? trim((string) ($data['customer_identifier'] ?? ''))
                    : $this->processingCompanyIdentifier($data),
                'cui' => trim((string) ($data['customer_cui'] ?? '')),
                'representative' => trim((string) ($data['customer_representative'] ?? '')),
            ] + $this->customerLocationPayload($data);
            $this->updateCustomer($existingCustomerId, $customerPayload);
            $updated = ($this->find)('customers', $existingCustomerId);
            if (!$updated) {
                throw new RuntimeException('Clientul nu a putut fi actualizat.');
            }
            return $updated;
        }

        $customerId = $this->upsertCustomer([
            'customer_type' => $customerType,
            'name' => $customerType === 'PJ'
                ? trim((string) ($data['customer_name_pj'] ?? $data['customer_name']))
                : trim((string) $data['customer_name']),
            'phone' => $customerType === 'PJ'
                ? trim((string) ($data['customer_phone_pj'] ?? $data['customer_phone']))
                : trim((string) $data['customer_phone']),
            'address' => $customerType === 'PJ'
                ? trim((string) ($data['customer_address_pj'] ?? $data['customer_address']))
                : trim((string) $data['customer_address']),
            'identifier' => $customerType === 'PF'
                ? trim((string) ($data['customer_identifier'] ?? ''))
                : $this->processingCompanyIdentifier($data),
            'cui' => trim((string) ($data['customer_cui'] ?? '')),
            'representative' => trim((string) ($data['customer_representative'] ?? '')),
            'known_customer' => !empty($data['known_customer']),
        ] + $this->customerLocationPayload($data));

        $created = ($this->find)('customers', $customerId);
        if (!$created) {
            throw new RuntimeException('Clientul nu a putut fi creat.');
        }

        return $created;
    }

    private function upsertCustomer(array $customer): int
    {
        if ($customer['name'] === '' || $customer['phone'] === '' || $customer['address'] === '') {
            throw new RuntimeException('Numele, telefonul si adresa sunt obligatorii.');
        }

        if ($customer['customer_type'] === 'PJ' && ($customer['identifier'] === '' || $customer['representative'] === '')) {
            throw new RuntimeException('Pentru PJ sunt obligatorii CUI-ul si reprezentantul.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO customers
            (customer_type, name, phone, address, identifier, cui, representative, county_code, county_name, locality_siruta, locality_name, postal_code, registry_number, legal_form, vat_status, external_source, external_checked_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $customer['customer_type'],
            $customer['name'],
            $customer['phone'],
            $customer['address'],
            $customer['identifier'],
            $customer['cui'],
            $customer['representative'],
            $customer['county_code'],
            $customer['county_name'],
            $customer['locality_siruta'],
            $customer['locality_name'],
            $customer['postal_code'],
            $customer['registry_number'],
            $customer['legal_form'],
            $customer['vat_status'],
            $customer['external_source'],
            $customer['external_checked_at'],
            date('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function updateCustomer(int $customerId, array $customer): void
    {
        if ($customer['name'] === '' || $customer['phone'] === '' || $customer['address'] === '') {
            throw new RuntimeException('Numele, telefonul si adresa sunt obligatorii.');
        }

        if ($customer['customer_type'] === 'PJ' && ($customer['identifier'] === '' || $customer['representative'] === '')) {
            throw new RuntimeException('Pentru PJ sunt obligatorii CUI-ul si reprezentantul.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE customers
             SET customer_type = ?, name = ?, phone = ?, address = ?, identifier = ?, cui = ?, representative = ?,
                 county_code = ?, county_name = ?, locality_siruta = ?, locality_name = ?, postal_code = ?,
                 registry_number = ?, legal_form = ?, vat_status = ?, external_source = ?, external_checked_at = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $customer['customer_type'],
            $customer['name'],
            $customer['phone'],
            $customer['address'],
            $customer['identifier'],
            $customer['cui'],
            $customer['representative'],
            $customer['county_code'],
            $customer['county_name'],
            $customer['locality_siruta'],
            $customer['locality_name'],
            $customer['postal_code'],
            $customer['registry_number'],
            $customer['legal_form'],
            $customer['vat_status'],
            $customer['external_source'],
            $customer['external_checked_at'],
            $customerId,
        ]);
    }

    private function customerLocationPayload(array $data): array
    {
        return [
            'county_code' => trim((string) ($data['customer_county_code'] ?? '')),
            'county_name' => trim((string) ($data['customer_county_name'] ?? '')),
            'locality_siruta' => (int) ($data['customer_locality_siruta'] ?? 0),
            'locality_name' => trim((string) ($data['customer_locality_name'] ?? '')),
            'postal_code' => trim((string) ($data['customer_postal_code'] ?? '')),
            'registry_number' => trim((string) ($data['customer_registry_number'] ?? '')),
            'legal_form' => trim((string) ($data['customer_legal_form'] ?? '')),
            'vat_status' => trim((string) ($data['customer_vat_status'] ?? '')),
            'external_source' => trim((string) ($data['customer_external_source'] ?? '')),
            'external_checked_at' => $this->normalizeNullableDateTime($data['customer_external_checked_at'] ?? null),
        ];
    }

    private function normalizeNullableDateTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function processingCompanyIdentifier(array $data): string
    {
        $identifier = trim((string) ($data['customer_identifier'] ?? ''));
        if ($identifier !== '') {
            return $identifier;
        }

        return trim((string) ($data['customer_cui'] ?? ''));
    }

    private function matchSirutaLocation(string $countyName, string $localityText, string $postalCode = ''): array
    {
        $empty = [
            'county_code' => '',
            'county_name' => '',
            'locality_siruta' => 0,
            'locality_name' => '',
            'locality_display_name' => '',
        ];

        $countyNorm = $this->normalizeLocationName($countyName);
        if ($countyNorm === '') {
            return $empty;
        }

        $county = $this->findCountyByName($countyName, $countyNorm);
        if (!$county) {
            return $empty;
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $localityText))));
        $candidate = $parts[0] ?? $localityText;
        $candidate = preg_replace('/^(SAT|SATUL|COMUNA|ORASUL|ORAS|MUNICIPIUL)\s+/iu', '', $candidate) ?? $candidate;
        $candidateNorm = $this->normalizeLocationName($candidate);
        if ($candidateNorm === '') {
            return [
                'county_code' => (string) $county['county_code'],
                'county_name' => (string) $county['name'],
                'locality_siruta' => 0,
                'locality_name' => '',
                'locality_display_name' => '',
            ];
        }

        $parentNorm = '';
        if (isset($parts[1])) {
            $parent = preg_replace('/^(COMUNA|ORASUL|ORAS|MUNICIPIUL)\s+/iu', '', $parts[1]) ?? $parts[1];
            $parentNorm = $this->normalizeLocationName($parent);
        }

        $sql = 'SELECT *
                FROM siruta_localities
                WHERE county_code = ? AND normalized_name = ?';
        $params = [(string) $county['county_code'], $candidateNorm];
        if ($postalCode !== '') {
            $sql .= ' ORDER BY (postal_code = ?) DESC, display_name LIMIT 20';
            $params[] = $postalCode;
        } else {
            $sql .= ' ORDER BY display_name LIMIT 20';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $matches = $stmt->fetchAll();

        $locality = $matches[0] ?? null;
        if ($parentNorm !== '') {
            foreach ($matches as $match) {
                if ($this->normalizeLocationName((string) $match['parent_name']) === $parentNorm) {
                    $locality = $match;
                    break;
                }
            }
        }

        return [
            'county_code' => (string) $county['county_code'],
            'county_name' => (string) $county['name'],
            'locality_siruta' => $locality ? (int) $locality['siruta_code'] : 0,
            'locality_name' => $locality ? (string) $locality['name'] : '',
            'locality_display_name' => $locality ? (string) $locality['display_name'] : '',
        ];
    }

    private function findCountyByName(string $countyName, string $countyNorm): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM siruta_counties WHERE normalized_name = ? LIMIT 1');
        $stmt->execute([$countyNorm]);
        $county = $stmt->fetch();
        if ($county) {
            return $county;
        }

        $likeNorm = '%' . $countyNorm . '%';
        $likeName = '%' . strtoupper(trim($countyName)) . '%';
        $stmt = $this->pdo->prepare(
            'SELECT * FROM siruta_counties
             WHERE normalized_name LIKE ?
                OR UPPER(name) LIKE ?
             ORDER BY CASE WHEN normalized_name LIKE ? THEN 0 ELSE 1 END, county_code
             LIMIT 1'
        );
        $stmt->execute([$likeNorm, $likeName, $likeNorm]);
        $county = $stmt->fetch();

        return $county ?: null;
    }

    private function cleanCompanyAddress(string $address, string $countyName, string $localityName, string $postalCode): string
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $address)), fn ($part) => $part !== ''));
        if (!$parts) {
            return '';
        }

        $countyNorm = $this->normalizeLocationName($countyName);
        $localityNorm = $this->normalizeLocationName($localityName);
        $postalCode = trim($postalCode);
        $clean = [];

        foreach ($parts as $part) {
            $normalized = $this->normalizeLocationName($part);
            if ($postalCode !== '' && preg_replace('/\D+/', '', $part) === $postalCode) {
                continue;
            }
            if ($countyNorm !== '' && str_contains($normalized, $countyNorm)) {
                continue;
            }
            if ($localityNorm !== '' && ($normalized === $localityNorm || str_contains($normalized, $localityNorm))) {
                continue;
            }
            if (in_array($normalized, ['ROMANIA', 'RO'], true)) {
                continue;
            }
            $clean[] = $part;
        }

        if (!$clean) {
            $clean = [$parts[0]];
        }

        return implode(', ', array_unique($clean));
    }

    private function normalizeLocationName(string $value): string
    {
        if (function_exists('mb_convert_encoding')) {
            try {
                $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-2, UTF-8');
            } catch (ValueError) {
            }
        }
        $value = strtr($value, [
            'Ãƒâ€žÃ¢â‚¬Å¡' => 'A', 'ÃƒÆ’Ã¢â‚¬Å¡' => 'A', 'ÃƒÆ’Ã…Â½' => 'I', 'ÃƒË†Ã‹Å“' => 'S', 'Ãƒâ€¦Ã…Â¾' => 'S', 'ÃƒË†Ã…Â¡' => 'T', 'Ãƒâ€¦Ã‚Â¢' => 'T',
            'Ãƒâ€žÃ†â€™' => 'a', 'ÃƒÆ’Ã‚Â¢' => 'a', 'ÃƒÆ’Ã‚Â®' => 'i', 'ÃƒË†Ã¢â€žÂ¢' => 's', 'Ãƒâ€¦Ã…Â¸' => 's', 'ÃƒË†Ã¢â‚¬Âº' => 't', 'Ãƒâ€¦Ã‚Â£' => 't',
            'ÃƒÆ’Ã…Â¾' => 'T', 'ÃƒÆ’Ã‚Â¾' => 't', 'Ãƒâ€šÃ‚Âª' => 'S', 'Ãƒâ€šÃ‚Âº' => 's',
        ]);
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
