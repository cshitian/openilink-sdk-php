<?php

declare(strict_types=1);

namespace OpenILink\Exception;

use RuntimeException;

final class RequestException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $statusCode = null,
        private readonly ?string $responseBody = null,
        private readonly ?int $curlErrno = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public function getCurlErrno(): ?int
    {
        return $this->curlErrno;
    }

    public function isTimeout(): bool
    {
        return $this->curlErrno === 28;
    }
}
