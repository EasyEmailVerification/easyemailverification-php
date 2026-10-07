<?php

declare(strict_types=1);

namespace EasyEmailVerification;

/** Error returned by the API (getStatus() is the HTTP status) or raised by the client (status 0). */
class EEVException extends \RuntimeException
{
    public function __construct(string $message, private int $status = 0, private mixed $body = null)
    {
        parent::__construct($message, $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /** Decoded JSON error body, or the raw text. */
    public function getBody(): mixed
    {
        return $this->body;
    }
}
