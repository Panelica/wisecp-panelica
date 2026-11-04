<?php
declare(strict_types=1);

namespace WISECP\Modules\Servers;

use RuntimeException;

final class ApiClient implements PanelicaApi
{
    private string $baseUrl;
    private string $apiKey;
    private string $apiSecret;

    /** @var callable(string,array,array):void */
    private $logger;

    /** The HTTP status of the last answer, so a caller can tell 404 from a real failure. */
    private int $lastStatus = 0;

    public function __construct(array $server, callable $logger)
    {
        $host = trim((string) ($server['hostname'] ?? $server['ip'] ?? ''));
        if ($host === '') {
            throw new RuntimeException('Panelica sunucu adresi tanımlı değil.');
        }

        $scheme = !empty($server['secure']) ? 'https' : 'http';
        $port = (int) ($server['port'] ?? 8443);
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Panelica port bilgisi geçersiz.');
        }

        $host = preg_replace('#^https?://#i', '', $host) ?? $host;
        $host = trim($host, '/');
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $host = '[' . $host . ']';
        }
        if (preg_match('/^(?:[A-Za-z0-9.-]+|\[[0-9A-Fa-f:.]+\])$/D', $host) !== 1) {
            throw new RuntimeException('Panelica sunucu adresi geçersiz.');
        }

        $this->baseUrl = $scheme . '://' . $host . ':' . $port . '/api/external';
        $this->apiKey = trim((string) ($server['password'] ?? ''));
        $this->apiSecret = trim((string) ($server['access_hash'] ?? ''));
        $this->logger = $logger;

        if ($this->apiKey === '' || $this->apiSecret === '') {
            throw new RuntimeException('Panelica API anahtarı veya API sırrı sunucu ayarlarında eksik.');
        }
    }

    /** @return array<string,mixed> */
    public function request(string $method, string $path, ?array $body = null): array
    {
        if (!preg_match('#^/v1/[a-zA-Z0-9/_{}?&=.%+-]+$#', $path)) {
            throw new RuntimeException('Panelica API yolu geçersiz.');
        }

        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST', 'PATCH', 'DELETE'], true)) {
            throw new RuntimeException('Panelica API istek metodu desteklenmiyor.');
        }

        $timestamp = (string) time();
        $json = $body === null ? '' : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $signedBody = $method === 'DELETE' ? '' : $json;
        $signature = hash_hmac('sha256', $method . $path . $timestamp . $signedBody, $this->apiSecret);
        $url = $this->baseUrl . $path;
        $headers = [
            'X-API-Key: ' . $this->apiKey,
            'X-Timestamp: ' . $timestamp,
            'X-Signature: ' . $signature,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $requestForLog = ['method' => $method, 'path' => $path, 'body' => $this->redact($body ?? [])];
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Panelica API isteği başlatılamadı.');
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => $this->baseUrl !== '' && str_starts_with($this->baseUrl, 'https://'),
            CURLOPT_SSL_VERIFYHOST => str_starts_with($this->baseUrl, 'https://') ? 2 : 0,
            CURLOPT_POSTFIELDS => $json !== '' ? $json : null,
        ]);

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $this->lastStatus = $status;

        if ($raw === false) {
            ($this->logger)($path, $requestForLog, ['transport_error' => $curlError, 'http_status' => $status]);
            throw new RuntimeException('Panelica sunucusuna bağlanılamadı. Bağlantı ve SSL ayarlarını kontrol edin.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            ($this->logger)($path, $requestForLog, ['http_status' => $status, 'response' => 'Geçersiz JSON yanıtı']);
            throw new RuntimeException('Panelica API beklenmeyen bir yanıt döndürdü (HTTP ' . $status . ').');
        }

        $responseForLog = $this->redact($decoded);
        ($this->logger)($path, $requestForLog, ['http_status' => $status, 'response' => $responseForLog]);

        if ($status < 200 || $status >= 300 || ($decoded['status'] ?? null) === 'error') {
            $error = $decoded['error'] ?? $decoded['message'] ?? 'Panelica isteği reddetti.';
            if (is_array($error)) {
                $error = $error['message'] ?? 'Panelica isteği reddetti.';
            }
            $safeError = trim(strip_tags((string) $error));
            $safeError = str_replace([$this->apiKey, $this->apiSecret], '[MASKELENDİ]', $safeError);
            throw new RuntimeException($safeError !== '' ? $safeError . ' (HTTP ' . $status . ')' : 'Panelica isteği başarısız oldu (HTTP ' . $status . ').');
        }

        return $decoded;
    }

    /**
     * The payload of a GET, unwrapped: the panel answers {"status":…,"data":…}
     * and every caller wants the data, be it one object or a list.
     *
     * @return array<mixed>
     */
    public function get(string $path): array
    {
        return $this->payload($this->request('GET', $path));
    }

    /**
     * @param array<string,mixed>|null $body
     * @return array<mixed>
     */
    public function post(string $path, ?array $body = null): array
    {
        return $this->payload($this->request('POST', $path, $body));
    }

    /**
     * @param array<string,mixed> $body
     * @return array<mixed>
     */
    public function patch(string $path, array $body): array
    {
        return $this->payload($this->request('PATCH', $path, $body));
    }

    /** @return array<mixed> */
    public function delete(string $path): array
    {
        return $this->payload($this->request('DELETE', $path));
    }

    /** Whether the last answer carried this status. */
    public function lastStatusWas(int $status): bool
    {
        return $this->lastStatus === $status;
    }

    /**
     * @param array<string,mixed> $value
     * @return array<mixed>
     */
    private function payload(array $value): array
    {
        $data = $value['data'] ?? $value;

        return is_array($data) ? $data : [];
    }

    /** @return array<int,array<string,mixed>> */
    public function listPlans(): array
    {
        return $this->rows($this->request('GET', '/v1/plans'));
    }

    /** @return array<int,array<string,mixed>> */
    public function listAccounts(int $page = 1, int $limit = 100): array
    {
        return $this->rows($this->request('GET', '/v1/accounts?page=' . max(1, $page) . '&limit=' . min(100, max(1, $limit))));
    }

    /** @return array<int,array<string,mixed>> */
    public function listDomains(int $page = 1, int $limit = 100): array
    {
        return $this->rows($this->request('GET', '/v1/domains?page=' . max(1, $page) . '&limit=' . min(100, max(1, $limit))));
    }

    /** @param array<string,mixed> $value @return array<string,mixed> */
    public function data(array $value): array
    {
        $data = $value['data'] ?? $value;
        return is_array($data) && !array_is_list($data) ? $data : [];
    }

    /** @param array<string,mixed> $value @return array<int,array<string,mixed>> */
    private function rows(array $value): array
    {
        $data = $value['data'] ?? $value;
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }
        return is_array($data) && array_is_list($data) ? array_values(array_filter($data, 'is_array')) : [];
    }

    /** @param mixed $value @return mixed */
    private function redact(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $sensitive = ['password', 'new_password', 'api_secret', 'api_key', 'access_hash', 'token', 'signature'];
        foreach ($value as $key => $item) {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $value[$key] = '[MASKELENDİ]';
            } elseif (is_array($item)) {
                $value[$key] = $this->redact($item);
            }
        }
        return $value;
    }
}

