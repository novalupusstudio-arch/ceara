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

    public function defaultProcessor(): ?array
    {
        $stmt = $this->pdo->query('SELECT * FROM processors ORDER BY id LIMIT 1');
        $processor = $stmt->fetch();
        return $processor ?: null;
    }

    public function defaultProcessorForUser(int $userId): ?array
    {
        $store = $this->userPrimaryStore($userId);
        $processorId = (int) ($store['processor_id'] ?? 0);
        if ($processorId > 0) {
            $processor = ($this->find)('processors', $processorId);
            if ($processor) {
                $processor['processing_price_cents'] = (int) ($store['processing_price_cents'] ?? $processor['processing_price_cents']);
                $processor['exchange_shrinkage_pct'] = (float) ($store['processing_shrinkage_pct'] ?? $processor['exchange_shrinkage_pct']);
                $processor['purchase_shrinkage_pct'] = (float) ($store['purchase_shrinkage_pct'] ?? $processor['purchase_shrinkage_pct']);
                return $processor;
            }
        }

        return $this->defaultProcessor();
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

        $stmt = $this->pdo->prepare('SELECT * FROM siruta_counties WHERE normalized_name = ? LIMIT 1');
        $stmt->execute([$countyNorm]);
        $county = $stmt->fetch();
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
