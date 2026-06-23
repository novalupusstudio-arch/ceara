<?php

declare(strict_types=1);

namespace Ceara;

use PDO;
use Throwable;
use ValueError;

final class SirutaSeeder
{
    public function __construct(private PDO $pdo)
    {
    }

    public function run(): void
    {
        if (((int) $this->pdo->query('SELECT COUNT(*) FROM siruta_localities')->fetchColumn()) > 0) {
            return;
        }

        $path = __DIR__ . '/../release/siruta.csv';
        if (!is_file($path)) {
            return;
        }

        $handle = fopen($path, 'rb');
        if (!$handle) {
            return;
        }

        $headers = fgetcsv($handle, 0, ';');
        if (!$headers) {
            fclose($handle);
            return;
        }

        $headers = array_map(fn ($value) => trim((string) $value, " \t\n\r\0\x0B\""), $headers);
        $rows = [];
        while (($line = fgetcsv($handle, 0, ';')) !== false) {
            if (count($line) < count($headers)) {
                continue;
            }
            $row = array_combine($headers, $line);
            if (!$row) {
                continue;
            }
            $rows[] = $row;
        }
        fclose($handle);

        $counties = [];
        foreach ($rows as $row) {
            $level = $this->sirutaInt($row['NIV'] ?? 0);
            $type = $this->sirutaInt($row['TIP'] ?? 0);
            if ($level !== 1 || $type !== 40) {
                continue;
            }

            $name = $this->sirutaCleanName((string) ($row['DENLOC'] ?? ''));
            $name = preg_replace('/^JUDETUL\s+/i', '', $name) ?? $name;
            $countyCode = trim((string) ($row['JUD'] ?? ''));
            if ($countyCode === '') {
                continue;
            }
            $counties[$countyCode] = [
                'siruta_code' => $this->sirutaInt($row['SIRUTA'] ?? 0),
                'name' => $name,
                'normalized_name' => $this->sirutaNormalize($name),
            ];
        }

        $this->pdo->beginTransaction();
        try {
            $countyStmt = $this->pdo->prepare(
                'INSERT INTO siruta_counties (county_code, siruta_code, name, normalized_name)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE siruta_code = VALUES(siruta_code), name = VALUES(name), normalized_name = VALUES(normalized_name)'
            );
            foreach ($counties as $countyCode => $county) {
                $countyStmt->execute([$countyCode, $county['siruta_code'], $county['name'], $county['normalized_name']]);
            }

            $localities = [];
            foreach ($rows as $row) {
                $level = $this->sirutaInt($row['NIV'] ?? 0);
                $type = $this->sirutaInt($row['TIP'] ?? 0);
                $countyCode = trim((string) ($row['JUD'] ?? ''));
                if ($level < 2 || $type === 40 || $countyCode === '' || !isset($counties[$countyCode])) {
                    continue;
                }

                $siruta = $this->sirutaInt($row['SIRUTA'] ?? 0);
                if ($siruta <= 0) {
                    continue;
                }

                $rawName = $this->sirutaCleanName((string) ($row['DENLOC'] ?? ''));
                $name = $this->sirutaDisplayBaseName($rawName);
                $localities[$siruta] = [
                    'siruta_code' => $siruta,
                    'county_code' => $countyCode,
                    'name' => $name,
                    'normalized_name' => $this->sirutaNormalize($name),
                    'display_name' => $name,
                    'postal_code' => (string) max(0, $this->sirutaInt($row['CODP'] ?? 0)),
                    'parent_siruta' => $this->sirutaInt($row['SIRSUP'] ?? 0),
                    'parent_name' => '',
                    'parent_type' => '',
                    'type_code' => $type,
                    'level_no' => $level,
                    'duplicate_name_in_county' => 0,
                ];
            }

            foreach ($localities as &$locality) {
                $parent = $localities[$locality['parent_siruta']] ?? null;
                if ($parent) {
                    $locality['parent_name'] = $parent['name'];
                    $locality['parent_type'] = $this->sirutaParentType((int) $parent['type_code']);
                }
            }
            unset($locality);

            $duplicates = [];
            foreach ($localities as $locality) {
                $key = $locality['county_code'] . '|' . $locality['normalized_name'];
                $duplicates[$key] = ($duplicates[$key] ?? 0) + 1;
            }

            foreach ($localities as &$locality) {
                $key = $locality['county_code'] . '|' . $locality['normalized_name'];
                if (($duplicates[$key] ?? 0) > 1 && $locality['parent_name'] !== '') {
                    $locality['duplicate_name_in_county'] = 1;
                    $locality['display_name'] = $locality['name'] . ' (' . trim($locality['parent_type'] . ' ' . $locality['parent_name']) . ')';
                }
            }
            unset($locality);

            $localityStmt = $this->pdo->prepare(
                'INSERT INTO siruta_localities
                (siruta_code, county_code, name, normalized_name, display_name, postal_code, parent_siruta, parent_name, parent_type, type_code, level_no, duplicate_name_in_county)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    county_code = VALUES(county_code),
                    name = VALUES(name),
                    normalized_name = VALUES(normalized_name),
                    display_name = VALUES(display_name),
                    postal_code = VALUES(postal_code),
                    parent_siruta = VALUES(parent_siruta),
                    parent_name = VALUES(parent_name),
                    parent_type = VALUES(parent_type),
                    type_code = VALUES(type_code),
                    level_no = VALUES(level_no),
                    duplicate_name_in_county = VALUES(duplicate_name_in_county)'
            );
            foreach ($localities as $locality) {
                $localityStmt->execute([
                    $locality['siruta_code'],
                    $locality['county_code'],
                    $locality['name'],
                    $locality['normalized_name'],
                    $locality['display_name'],
                    $locality['postal_code'] === '0' ? '' : $locality['postal_code'],
                    $locality['parent_siruta'] ?: null,
                    $locality['parent_name'],
                    $locality['parent_type'],
                    $locality['type_code'],
                    $locality['level_no'],
                    $locality['duplicate_name_in_county'],
                ]);
            }

            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    private function sirutaInt(mixed $value): int
    {
        return (int) preg_replace('/\D+/', '', (string) $value);
    }

    private function sirutaCleanName(string $value): string
    {
        $converted = $value;
        if (function_exists('mb_convert_encoding')) {
            try {
                $converted = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-2, UTF-8');
            } catch (ValueError) {
                $converted = $value;
            }
        }
        $converted = strtr($converted, [
            'ÃƒÆ’Ã¢â‚¬Å¾ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡' => 'A', 'ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡' => 'A', 'ÃƒÆ’Ã†â€™Ãƒâ€¦Ã‚Â½' => 'I', 'ÃƒÆ’Ã‹â€ Ãƒâ€¹Ã…â€œ' => 'S', 'ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€¦Ã‚Â¾' => 'S', 'ÃƒÆ’Ã‹â€ Ãƒâ€¦Ã‚Â¡' => 'T', 'ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¢' => 'T',
            'ÃƒÆ’Ã¢â‚¬Å¾Ãƒâ€ Ã¢â‚¬â„¢' => 'a', 'ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢' => 'a', 'ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â®' => 'i', 'ÃƒÆ’Ã‹â€ ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢' => 's', 'ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€¦Ã‚Â¸' => 's', 'ÃƒÆ’Ã‹â€ ÃƒÂ¢Ã¢â€šÂ¬Ã‚Âº' => 't', 'ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â£' => 't',
            'ÃƒÆ’Ã†â€™Ãƒâ€¦Ã‚Â¾' => 'T', 'ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¾' => 't', 'ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âª' => 'S', 'ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âº' => 's',
        ]);
        $converted = preg_replace('/\s+/', ' ', $converted) ?? $converted;
        return trim($converted);
    }

    private function sirutaDisplayBaseName(string $name): string
    {
        $name = preg_replace('/^(MUNICIPIUL|ORASUL|ORAS|COMUNA|SATUL|SAT)\s+/i', '', $name) ?? $name;
        return trim($name);
    }

    private function sirutaNormalize(string $value): string
    {
        $value = $this->sirutaCleanName($value);
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function sirutaParentType(int $typeCode): string
    {
        return match ($typeCode) {
            1 => 'mun.',
            2 => 'oras',
            3 => 'com.',
            default => 'loc.',
        };
    }
}
