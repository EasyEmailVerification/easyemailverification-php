<?php

declare(strict_types=1);

namespace EasyEmailVerification;

/**
 * Official PHP client for the Easy Email Verification API.
 * API reference: https://www.easyemailverification.com/en-US/api/reference
 */
final class Client
{
    public const VERSION = '2.0.0';
    public const DEFAULT_BASE_URL = 'https://api.easyemailverification.com/v1';

    /**
     * Public test key: no account and no credits. Works on verify, verifyBatch and credits (not bulk),
     * only for addresses at sandbox.easyemailverification.com (plus typo@gmial.com), with fixed answers.
     */
    public const SANDBOX_API_KEY = 'eev_sandbox_key';

    /** Maximum addresses per verifyBatch call. */
    public const MAX_BATCH = 50;

    public readonly Bulk $bulk;
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    /**
     * @param string|null $apiKey  defaults to the EEV_API_KEY environment variable
     * @param int         $timeout seconds per request (batch uses at least 120, bulk uploads and downloads 300)
     */
    public function __construct(?string $apiKey = null, string $baseUrl = self::DEFAULT_BASE_URL, int $timeout = 30)
    {
        $apiKey = $apiKey ?? (getenv('EEV_API_KEY') ?: null);
        if ($apiKey === null || $apiKey === '') {
            throw new EEVException('Missing API key: pass $apiKey or set EEV_API_KEY (use Client::SANDBOX_API_KEY to test)');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->bulk = new Bulk($this);
    }

    /**
     * Turns a verification result into a decision:
     * "accept"  valid and safe to send;
     * "reject"  invalid (will bounce);
     * "suggest" did_you_mean has a corrected address (ask the user);
     * "review"  unknown, catch-all or disposable: your own policy decides.
     */
    public static function decide(array $result): string
    {
        if (($result['did_you_mean'] ?? '') !== '') {
            return 'suggest';
        }
        if (($result['result'] ?? null) === 'valid' && !empty($result['safe_to_send'])) {
            return 'accept';
        }
        if (($result['result'] ?? null) === 'invalid') {
            return 'reject';
        }
        return 'review';
    }

    /** Verifies one address. Uses one credit; unknown results do not. */
    public function verify(string $email): array
    {
        return $this->request('GET', '/verify?email=' . rawurlencode($email));
    }

    /**
     * Verifies up to 50 addresses in one request; returns one result per address.
     *
     * @param string[] $emails
     */
    public function verifyBatch(array $emails): array
    {
        if ($emails === []) {
            throw new EEVException('verifyBatch needs a non-empty array');
        }
        if (count($emails) > self::MAX_BATCH) {
            throw new EEVException('verifyBatch accepts at most ' . self::MAX_BATCH . ' addresses; use bulk for larger lists');
        }
        return $this->request('POST', '/verify', ['json' => ['emails' => array_values($emails)], 'timeout' => max($this->timeout, 120)]);
    }

    /** Remaining credits (free). */
    public function credits(): array
    {
        return $this->request('GET', '/credits');
    }

    /**
     * @internal used by Bulk
     * @param array{json?: mixed, multipart?: array{content: string, filename: string}, timeout?: int, raw?: bool} $options
     * @return array|string
     */
    public function request(string $method, string $path, array $options = [])
    {
        $headers = ['X-API-Key: ' . $this->apiKey, 'User-Agent: easyemailverification-php/' . self::VERSION];
        $ch = curl_init($this->baseUrl . $path);
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $options['timeout'] ?? $this->timeout,
        ];
        if (array_key_exists('json', $options)) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_POSTFIELDS] = json_encode($options['json']);
        } elseif (isset($options['multipart'])) {
            $tmp = tempnam(sys_get_temp_dir(), 'eev');
            file_put_contents($tmp, $options['multipart']['content']);
            $opts[CURLOPT_POSTFIELDS] = ['file' => new \CURLFile($tmp, 'text/csv', $options['multipart']['filename'])];
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if (isset($tmp)) {
            @unlink($tmp);
        }
        if ($body === false) {
            throw new EEVException('Request failed: ' . $error);
        }

        $ok = $status >= 200 && $status < 300;
        if ($ok && !empty($options['raw'])) {
            return $body;
        }
        $data = $body === '' ? null : json_decode($body, true);
        if ($body !== '' && json_last_error() !== JSON_ERROR_NONE) {
            if ($ok) {
                throw new EEVException('Unexpected response from the API', $status, $body);
            }
            $data = null;
        }
        if (!$ok) {
            $message = is_array($data) && !empty($data['message']) ? (string)$data['message'] : 'HTTP ' . $status;
            throw new EEVException($message, $status, $data ?? $body);
        }
        return $data;
    }
}
