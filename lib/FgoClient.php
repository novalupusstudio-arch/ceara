<?php

final class FgoClient
{
    public function __construct(private array $config)
    {
    }

    public function enabled(): bool
    {
        return !empty($this->config['enabled']);
    }

    public function emitInvoice(array $payload, string $clientName): array
    {
        $this->assertConfigured();

        $payload = array_merge([
            'CodUnic' => (string) $this->config['cod_unic'],
            'Hash' => $this->hashForClient($clientName),
            'Serie' => (string) $this->config['serie'],
            'Valuta' => 'RON',
            'TipFactura' => 'Factura',
            'PlatformaUrl' => (string) $this->config['platforma_url'],
        ], $payload);

        return $this->post('/factura/emitere', $payload);
    }

    private function assertConfigured(): void
    {
        foreach (['base_url', 'cod_unic', 'private_key', 'platforma_url', 'serie'] as $key) {
            if (trim((string) ($this->config[$key] ?? '')) === '') {
                throw new RuntimeException('Configurarea FGO este incompleta: lipseste ' . $key . '.');
            }
        }
    }

    private function hashForClient(string $clientName): string
    {
        return strtoupper(sha1((string) $this->config['cod_unic'] . (string) $this->config['private_key'] . $clientName));
    }

    private function post(string $path, array $payload): array
    {
        $url = rtrim((string) $this->config['base_url'], '/') . $path;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Nu am putut serializa payload-ul FGO.');
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            $raw = curl_exec($ch);
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($raw === false || $raw === '') {
                throw new RuntimeException('FGO nu a raspuns: ' . ($error ?: 'raspuns gol'));
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $json,
                    'timeout' => 20,
                    'ignore_errors' => true,
                ],
            ]);
            $raw = file_get_contents($url, false, $context);
            $status = 0;
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) {
                $status = (int) $match[1];
            }
            if ($raw === false || $raw === '') {
                throw new RuntimeException('FGO nu a raspuns.');
            }
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Raspuns FGO invalid: ' . substr($raw, 0, 300));
        }

        if ($status >= 400) {
            $message = (string) ($decoded['Message'] ?? $decoded['Error'] ?? 'eroare HTTP ' . $status);
            throw new RuntimeException('Eroare FGO: ' . $message);
        }

        if (isset($decoded['Success']) && !$decoded['Success']) {
            $message = (string) ($decoded['Message'] ?? $decoded['Error'] ?? 'operatiune respinsa');
            throw new RuntimeException('Eroare FGO: ' . $message);
        }

        return $decoded;
    }
}
