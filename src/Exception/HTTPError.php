<?php

declare(strict_types=1);

namespace OpenILink\Exception;

use RuntimeException;

final class HTTPError extends RuntimeException
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly string $body,
        private readonly array $headers = [],
    ) {
        parent::__construct(sprintf('ilink: http %d: %s', $statusCode, $body));
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
