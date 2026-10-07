<?php

/**
 * Backward-compatible wrapper for code written for the first version of this SDK.
 * New code should use EasyEmailVerification\Client, which throws EEVException on errors.
 *
 * @deprecated use EasyEmailVerification\Client
 */
class EasyEmailVerificationClient
{
    private \EasyEmailVerification\Client $client;

    public function __construct(string $apiKey)
    {
        if (!class_exists(\EasyEmailVerification\Client::class)) {
            // without Composer: load the classes next to this file
            foreach (['EEVException', 'Bulk', 'Client'] as $c) {
                require_once __DIR__ . '/EasyEmailVerification/' . $c . '.php';
            }
        }
        $this->client = new \EasyEmailVerification\Client($apiKey);
    }

    /**
     * Verifies an email address. Returns the API response as an array; on errors it returns
     * ['success' => false, 'message' => ..., 'http_code' => ...] instead of throwing, as before.
     */
    public function verifyEmail(string $email): array
    {
        try {
            return $this->client->verify($email);
        } catch (\EasyEmailVerification\EEVException $e) {
            $body = $e->getBody();
            return is_array($body) ? $body + ['success' => false]
                : ['success' => false, 'message' => $e->getMessage(), 'http_code' => (string)$e->getStatus()];
        }
    }
}
